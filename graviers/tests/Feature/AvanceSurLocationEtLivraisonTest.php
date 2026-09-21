<?php

namespace Tests\Feature;

use App\Models\Agence;
use App\Models\Apporteur;
use App\Models\AvanceClient;
use App\Models\Client;
use App\Models\CommissionApporteur;
use App\Models\DemandeLivraison;
use App\Models\DetailLocation;
use App\Models\LignePaiement;
use App\Models\Location;
use App\Models\MouvementAvance;
use App\Models\Paiement;
use App\Models\Produit;
use App\Models\TvaCommande;
use App\Models\User;
use App\Services\Avances;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * L'AVANCE D'UN CLIENT S'IMPUTE AUSSI SUR SA LOCATION ET SA DEMANDE DE
 * LIVRAISON RÉGLÉES « EN AGENCE » (10/09/2026) — comme c'était déjà le cas
 * pour les ventes. Le règlement qui en naît porte le service de l'affaire,
 * produit les effets du guichet correspondant, et l'historique des avances
 * désigne l'affaire.
 */
class AvanceSurLocationEtLivraisonTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        $u = User::whereIn('type_user_id', [1, 2])->where('statut', 1)->orderBy('id')->first();
        if (!$u) {
            $this->markTestSkipped('Aucun administrateur.');
        }
        if (!$u->agence_id) {
            $u->agence_id = Agence::value('id');
            $u->save();
        }

        return $u;
    }

    private function clientSansDette(): Client
    {
        $client = Client::where('statut', 1)->whereHas('user')->get()
            ->first(fn (Client $c) => Avances::affairesNonSoldees($c)->isEmpty());
        if (!$client) {
            $this->markTestSkipped('Aucun client actif sans affaire non soldée.');
        }
        // Le test porte sur l'avance : un client au réel, sans AIRSI (10/09/2026).
        $client->update(['regime_imposition' => 'RNI']);

        return $client->fresh();
    }

    /** Une avance déjà validée par deux administrateurs. */
    private function avanceDisponible(Client $client, float $montant, User $admin): AvanceClient
    {
        return AvanceClient::create([
            'client_id'         => $client->id,
            'montant'           => $montant,
            'montant_consomme'  => 0,
            'statut'            => AvanceClient::DISPONIBLE,
            'numero_recu'       => 'RA-TEST-' . random_int(100, 999),
            'agence_id'         => $admin->agence_id,
            'caissier_id'       => $admin->id,
            'user_valide_id'    => $admin->id,
            'user_valide2_id'   => $admin->id,
            'date_depot'        => now(),
            'date_validation_1' => now(),
            'date_validation_2' => now(),
        ]);
    }

    private function location(Client $client, float $prix): Location
    {
        $produit = Produit::where('statut', 1)->first() ?: Produit::first();
        $location = Location::create([
            'numero'                => 'T' . random_int(100000, 999999),
            'client_id'             => $client->id,
            'montant_total'         => $prix,
            'etat_location'         => \Help::$LOCATION_EN_ATTENTE,
            'statut'                => 1,
            'remise'                => 0,
            'cout_livraison_client' => 0,
        ]);
        DetailLocation::create([
            'produit_id'    => $produit->id,
            'location_id'   => $location->id,
            'qte'           => 1,
            'debut'         => now()->toDateString(),
            'fin'           => now()->addDay()->toDateString(),
            'prix'          => $prix,
            'nombre_jour'   => 1,
            'etat_location' => \Help::$LOCATION_EN_ATTENTE,
        ]);
        TvaCommande::create([
            'client_id'    => $client->id,
            'commande_id'  => $location->id,
            'montant'      => 0,
            'type_affaire' => \Help::$LOCATION,
        ]);

        return Location::find($location->id);
    }

    private function demande(Client $client, float $montant): DemandeLivraison
    {
        $demande = DemandeLivraison::create([
            'numero'        => 'T' . random_int(100000, 999999),
            'client_id'     => $client->id,
            'montantTotal'  => $montant,
            'etat_commande' => \Help::$COMMANDE_EN_ATTENTE,
            'statut'        => \Help::$STATUT_ACTIF,
        ]);

        return DemandeLivraison::find($demande->id);
    }

    public function test_une_location_en_agence_consomme_l_avance_et_laisse_le_reliquat(): void
    {
        $admin  = $this->admin();
        $client = $this->clientSansDette();
        $avance = $this->avanceDisponible($client, 5000, $admin);
        $location = $this->location($client, 8000);
        $commissionsAvant = CommissionApporteur::count();

        $resultat = Avances::imputerSurLocation($location, $admin->id);

        $this->assertEqualsWithDelta(5000, $resultat['impute'], 0.01);
        $this->assertEqualsWithDelta(3000, $resultat['reste'], 0.01);
        $this->assertEqualsWithDelta(3000, Location::find($location->id)->montantRestantDu(), 0.01);
        $this->assertSame(2, (int) Location::find($location->id)->statut, 'Location partiellement réglée = statut 2, comme au guichet.');
        $this->assertSame(0.0, Avances::soldeDisponible($client), 'L\'avance est épuisée.');
        $this->assertSame('Épuisée', $avance->fresh()->libelleStatut());

        $reglement = Paiement::where('service', \Help::$LOCATION)->where('service_id', $location->id)->first();
        $this->assertNotNull($reglement, 'Le règlement issu de l\'avance porte service = LOCATION.');
        $this->assertSame(1, (int) $reglement->statut, 'Le règlement issu d\'une avance est déjà validé.');
        $this->assertStringStartsWith('AV-' . date('Y') . '-', $reglement->numero_recu);
        $this->assertSame((int) $admin->id, (int) $reglement->user_valide2_id);
        $this->assertStringContainsString('sur la location ' . $location->numero, $reglement->libelle);
        $ligne = LignePaiement::where('paiement_id', $reglement->id)->first();
        $this->assertSame(\Help::$LOCATION, $ligne->service);
        $this->assertSame((int) $location->id, (int) $ligne->service_id);
        $this->assertStringContainsString('Avance client', $ligne->moyen_paiement);

        $mouvement = MouvementAvance::where('avance_client_id', $avance->id)->where('type', 'DEDUCTION')->first();
        $this->assertNotNull($mouvement);
        $this->assertNull($mouvement->commande_id, 'Une location ne se range pas dans commande_id : cette colonne désigne une commande.');
        $this->assertSame((int) $reglement->id, (int) $mouvement->paiement_id);
        $this->assertSame('Location ' . $location->numero, $mouvement->libelleAffaire());

        // La commission de l'apporteur suit le barème des locations, si le client est parrainé.
        if ($client->code_parrain && Apporteur::where('code', $client->code_parrain)->exists()) {
            $this->assertSame($commissionsAvant + 1, CommissionApporteur::count());
            $this->assertSame('LOCATION', CommissionApporteur::orderByDesc('id')->first()->type_affaire);
        }

        // Le reçu du règlement dit d'où vient l'argent et sur quoi il porte.
        Auth::guard('web')->login($admin);
        $this->get('/recu/' . $reglement->id)->assertOk()->assertSee('Avance client');

        // Le message au client nomme la location.
        $message = Avances::messageImputation($resultat, \Help::$LOCATION);
        $this->assertStringContainsString('sur cette location', $message);
        $this->assertStringContainsString('Reste à régler en agence', $message);

        // L'historique des avances désigne la location, pas une commande homonyme.
        $html = $this->get('/avances')->assertOk()->getContent();
        $this->assertStringContainsString('<th class="text-center">Affaire</th>', $html);
        $this->assertStringContainsString('Location ' . $location->numero, $html);
    }

    public function test_une_location_couverte_en_entier_est_soldee(): void
    {
        $admin  = $this->admin();
        $client = $this->clientSansDette();
        $this->avanceDisponible($client, 9000, $admin);
        $location = $this->location($client, 4000);

        $resultat = Avances::imputerSurLocation($location, $admin->id);

        $this->assertEqualsWithDelta(4000, $resultat['impute'], 0.01);
        $this->assertSame(0.0, $resultat['reste']);
        $this->assertSame(3, (int) Location::find($location->id)->statut, 'Location soldée = statut 3, comme au guichet.');
        $this->assertEqualsWithDelta(5000, Avances::soldeDisponible($client), 0.01, 'Le reliquat de l\'avance reste disponible.');
        $this->assertStringContainsString('La location est entièrement réglée', Avances::messageImputation($resultat, \Help::$LOCATION));
    }

    public function test_une_demande_de_livraison_en_agence_consomme_l_avance(): void
    {
        $admin  = $this->admin();
        $client = $this->clientSansDette();
        $pointsAvant = (float) $client->point;
        $avance  = $this->avanceDisponible($client, 5000, $admin);
        $demande = $this->demande($client, 8000);
        $this->assertEqualsWithDelta(8000, $demande->montantRestantDu(), 0.01);

        $resultat = Avances::imputerSurDemandeLivraison($demande, $admin->id);

        $this->assertEqualsWithDelta(5000, $resultat['impute'], 0.01);
        $this->assertEqualsWithDelta(3000, $resultat['reste'], 0.01);
        $this->assertEqualsWithDelta(3000, DemandeLivraison::find($demande->id)->montantRestantDu(), 0.01);
        $this->assertSame(0.0, Avances::soldeDisponible($client));

        $reglement = Paiement::where('service', \Help::$LIVRAISON)->where('service_id', $demande->id)->first();
        $this->assertNotNull($reglement, 'Le règlement issu de l\'avance porte service = LIVRAISON.');
        $this->assertSame(1, (int) $reglement->statut);
        $this->assertSame(1, LignePaiement::where('paiement_id', $reglement->id)->where('service', \Help::$LIVRAISON)->where('statut', 1)->count());

        $mouvement = MouvementAvance::where('avance_client_id', $avance->id)->where('type', 'DEDUCTION')->first();
        $this->assertNull($mouvement->commande_id);
        $this->assertSame('Livraison ' . $demande->numero, $mouvement->libelleAffaire());

        // Une demande de livraison ne donne pas de points : même règle que son guichet.
        $this->assertSame($pointsAvant, (float) $client->fresh()->point);
        $this->assertStringContainsString('sur cette demande de livraison', Avances::messageImputation($resultat, \Help::$LIVRAISON));

        // Une seconde imputation ne fait rien : rien n'est disponible.
        $encore = Avances::imputerSurDemandeLivraison($demande, $admin->id);
        $this->assertSame(0.0, $encore['impute']);
    }

    public function test_le_message_et_le_circuit_de_la_commande_ne_changent_pas(): void
    {
        $message = Avances::messageImputation(['impute' => 3000, 'reste' => 0, 'recus' => []]);
        $this->assertStringContainsString('sur cette commande', $message);
        $this->assertStringContainsString('La commande est entièrement réglée', $message);

        // Le guichet des locations et l'imputation partagent UNE règle des effets.
        $source = file_get_contents(app_path('Http/Controllers/LocationComptantController.php'));
        $this->assertStringContainsString('ReglementValide::appliquerLocation', $source);
        $this->assertStringNotContainsString("'type_affaire' => 'LOCATION'", $source,
            'La commission de location se crée dans ReglementValide, plus dans le guichet.');
    }

    public function test_les_pages_de_fin_affichent_l_imputation(): void
    {
        $client = $this->clientSansDette();
        Auth::guard('web')->login($client->user);
        $demande = $this->demande($client, 8000);

        $html = $this->withSession(['avance_imputee' => 'Votre avance a été imputée sur cette demande de livraison : 5 000 fcfa déduits.'])
            ->get('/demande-de-livraison-validee-' . $demande->id)->assertOk()->getContent();
        $this->assertStringContainsString('js-avance-imputee', $html);
        $this->assertStringContainsString('imputée sur cette demande de livraison', $html);

        $location = $this->location($client, 4000);
        $html = view('orders.recapLocation', [
            'location'      => $location,
            'config'        => \App\Models\Configuration::first(),
            'messageAvance' => 'Votre avance a été imputée sur cette location : 4 000 fcfa déduits.',
        ])->render();
        $this->assertStringContainsString('js-avance-imputee', $html);
        $this->assertStringContainsString('imputée sur cette location', $html);

        $sans = view('orders.recapLocation', [
            'location' => $location,
            'config'   => \App\Models\Configuration::first(),
        ])->render();
        $this->assertStringNotContainsString('js-avance-imputee', $sans, 'Sans imputation, pas de bandeau.');
    }
    /**
     * LE FLUX COMPLET DU SITE : le client valide une demande de livraison en
     * choisissant « Paiement en agence » ; son avance est imputée avant la
     * page de fin, qui le lui dit.
     */
    public function test_le_flux_de_la_demande_de_livraison_en_agence_impute_l_avance(): void
    {
        $admin  = $this->admin();
        $client = $this->clientSansDette();
        $this->avanceDisponible($client, 5000, $admin);

        $mode  = \App\Models\ModePaiement::where('statut', 1)->where('en_ligne', 0)->where('libelle', 'like', '%agence%')->first();
        $type  = \App\Models\TypeLivraison::first();
        $ville = \App\Models\Ville::first();
        $unite = \App\Models\UniteProduit::value('id');
        if (!$mode || !$type || !$ville || !$unite) {
            $this->markTestSkipped('Il manque un mode en agence, un type de livraison, une ville ou une unité.');
        }

        Auth::guard('web')->login($client->user);
        $reponse = $this->withSession([
            'villePec' => $ville->id, 'longPec' => '-4.01', 'latPec' => '5.35', 'affichagePec' => 'Carrière de test',
            'villeDest' => $ville->id, 'longDest' => '-4.02', 'latDest' => '5.36', 'affichageDest' => 'Chantier de test',
            'produits' => [['nom_produit' => 'Sable', 'qte' => 2, 'unite' => $unite, 'desc' => '']],
            'paiement' => $mode->id,
            'type_livraison' => $type->libelle,
            'montant_total' => 8000, 'montantTva' => 0,
            'date' => now()->addDay()->toDateString(),
            'libelle' => 'Livraison de test', 'description' => 'Deux tonnes de sable',
        ])->get('/client-Validation-de-la-demande-de-livraison');

        $reponse->assertRedirect();
        $demande = DemandeLivraison::where('client_id', $client->id)->orderByDesc('id')->first();
        $this->assertNotNull($demande);
        $this->assertStringContainsString('demande-de-livraison-validee-' . $demande->id, $reponse->headers->get('Location'));

        // L'avance a été imputée : 5 000 réglés, 3 000 restent dus.
        $this->assertEqualsWithDelta(5000, $demande->montantPayeComptant(), 0.01);
        $this->assertEqualsWithDelta(3000, $demande->montantRestantDu(), 0.01);
        $this->assertSame(0.0, Avances::soldeDisponible($client));
        $this->assertSame(1, Paiement::where('service', \Help::$LIVRAISON)->where('service_id', $demande->id)->count());

        // La page de fin le dit au client.
        $html = $this->get($reponse->headers->get('Location'))->assertOk()->getContent();
        $this->assertStringContainsString('js-avance-imputee', $html);
        $this->assertStringContainsString('imputée sur cette demande de livraison', $html);
        $this->assertStringContainsString('Reste à régler en agence', $html);
    }

    /** Un mode EN LIGNE ne touche pas à l'avance, même sur une demande de livraison. */
    public function test_un_mode_en_ligne_ne_touche_pas_a_l_avance_de_la_livraison(): void
    {
        $admin  = $this->admin();
        $client = $this->clientSansDette();
        $this->avanceDisponible($client, 5000, $admin);

        $mode  = \App\Models\ModePaiement::where('statut', 1)->where('en_ligne', 1)->first();
        $type  = \App\Models\TypeLivraison::first();
        $ville = \App\Models\Ville::first();
        $unite = \App\Models\UniteProduit::value('id');
        if (!$mode || !$type || !$ville || !$unite) {
            $this->markTestSkipped('Il manque un mode en ligne, un type de livraison, une ville ou une unité.');
        }

        Auth::guard('web')->login($client->user);
        $this->withSession([
            'villePec' => $ville->id, 'longPec' => '-4.01', 'latPec' => '5.35', 'affichagePec' => 'Carrière de test',
            'villeDest' => $ville->id, 'longDest' => '-4.02', 'latDest' => '5.36', 'affichageDest' => 'Chantier de test',
            'produits' => [['nom_produit' => 'Sable', 'qte' => 2, 'unite' => $unite, 'desc' => '']],
            'paiement' => $mode->id,
            'type_livraison' => $type->libelle,
            'montant_total' => 8000, 'montantTva' => 0,
            'date' => now()->addDay()->toDateString(),
            'libelle' => 'Livraison de test', 'description' => 'Deux tonnes de sable',
        ])->get('/client-Validation-de-la-demande-de-livraison')->assertRedirect();

        $demande = DemandeLivraison::where('client_id', $client->id)->orderByDesc('id')->first();
        $this->assertNotNull($demande);
        $this->assertEqualsWithDelta(5000, Avances::soldeDisponible($client), 0.01, 'L\'avance est intacte.');
        $this->assertSame(0, Paiement::where('service', \Help::$LIVRAISON)->where('service_id', $demande->id)->where('numero_recu', 'like', 'AV-%')->count());
    }
}
