<?php

namespace Tests\Feature;

use App\Models\Agence;
use App\Models\Client;
use App\Models\DemandeLivraison;
use App\Models\DetailLocation;
use App\Models\Location;
use App\Models\ModePaiement;
use App\Models\Paiement;
use App\Models\Produit;
use App\Models\TvaCommande;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * GUICHETS DES LOCATIONS ET DES DEMANDES DE LIVRAISON (10/09/2026) : comme au
 * guichet des ventes, les affaires à encaisser se choisissent dans un TABLEAU À
 * CASES précédé d'un filtre client ; on en coche une ou plusieurs du même
 * client, ou toutes, et le montant s'impute de la plus ancienne à la plus
 * récente.
 */
class GuichetsLocationLivraisonTableauTest extends TestCase
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
        Auth::guard('web')->login($u);

        return $u;
    }

    private function client(): Client
    {
        $client = Client::where('statut', 1)->whereHas('user')->first();
        if (!$client) {
            $this->markTestSkipped('Aucun client.');
        }

        return $client;
    }

    private function modeAgence(): ModePaiement
    {
        return ModePaiement::listePourAgent()->first() ?: ModePaiement::first();
    }

    private function location(Client $client, float $prix, string $date): Location
    {
        $produit = Produit::where('statut', 1)->first() ?: Produit::first();
        $location = Location::create([
            'numero' => 'T' . random_int(100000, 999999), 'client_id' => $client->id, 'montant_total' => $prix,
            'etat_location' => \Help::$LOCATION_EN_ATTENTE, 'statut' => 1, 'remise' => 0, 'cout_livraison_client' => 0,
            'date_location' => $date, 'mode_paiement_id' => $this->modeAgence()->id,
        ]);
        DetailLocation::create([
            'produit_id' => $produit->id, 'location_id' => $location->id, 'qte' => 1, 'debut' => $date, 'fin' => $date,
            'prix' => $prix, 'nombre_jour' => 1, 'etat_location' => \Help::$LOCATION_EN_ATTENTE,
        ]);
        TvaCommande::create(['client_id' => $client->id, 'commande_id' => $location->id, 'montant' => 0, 'type_affaire' => \Help::$LOCATION]);

        return Location::find($location->id);
    }

    private function demande(Client $client, float $montant, string $date): DemandeLivraison
    {
        $d = DemandeLivraison::create([
            'numero' => 'T' . random_int(100000, 999999), 'client_id' => $client->id, 'montantTotal' => $montant,
            'etat_commande' => \Help::$COMMANDE_EN_ATTENTE, 'statut' => \Help::$STATUT_ACTIF, 'mode_paiement_id' => $this->modeAgence()->id,
        ]);
        DemandeLivraison::where('id', $d->id)->update(['created_at' => $date]);

        return DemandeLivraison::find($d->id);
    }

    public function test_le_guichet_des_locations_propose_un_filtre_et_un_tableau_a_cases(): void
    {
        $this->admin();
        $client = $this->client();
        $location = $this->location($client, 8000, now()->subDays(2)->toDateString());

        $html = $this->get('/encaissements/locations')->assertOk()->getContent();
        $this->assertStringContainsString('id="filtreClientLocations"', $html, 'Le filtre client manque.');
        $this->assertStringContainsString('id="toutCocherLocations"', $html, 'La case « tout cocher » manque.');
        $this->assertStringContainsString('name="numeros_location[]"', $html, 'Le tableau à cases manque.');
        $this->assertStringContainsString('value="' . $location->numero . '"', $html);
        $this->assertStringNotContainsString('name="numero_location"', $html, 'L\'ancienne liste déroulante doit avoir disparu.');
        $this->assertStringContainsString('select2.min.js', $html, 'Le filtre a besoin de Select2.');
        $this->assertStringContainsString('N° ' . $client->user_id . ' — ', $html, 'Le filtre liste les clients par numéro de compte.');
    }

    public function test_plusieurs_locations_du_meme_client_s_encaissent_de_la_plus_ancienne_a_la_plus_recente(): void
    {
        $this->admin();
        $client = $this->client();
        $ancienne = $this->location($client, 8000, now()->subDays(5)->toDateString());
        $recente  = $this->location($client, 6000, now()->subDay()->toDateString());

        $r = $this->post('/encaissements/locations', [
            'numeros_location' => [$recente->numero, $ancienne->numero],
            'mode_paiement_id' => $this->modeAgence()->id, 'montant' => 10000,
            'date_encaissement' => now()->toDateString(), 'notes' => 'Deux locations',
        ]);
        $this->assertNull($r->getSession()->get('erreur_caisse'), (string) $r->getSession()->get('erreur_caisse'));
        $this->assertSame([], $r->getSession()->get('errors')?->all() ?? [], 'validation');
        $this->assertSame(url('/encaissements/locations'), $r->headers->get('Location'), 'session : ' . json_encode($r->getSession()->all(), JSON_UNESCAPED_UNICODE));

        $pAncienne = Paiement::where('service', 'LOCATION')->where('service_id', $ancienne->id)->first();
        $pRecente  = Paiement::where('service', 'LOCATION')->where('service_id', $recente->id)->first();
        $this->assertNotNull($pAncienne);
        $this->assertNotNull($pRecente);
        $this->assertEqualsWithDelta(8000, (float) $pAncienne->montant_total, 0.01, 'La plus ancienne est servie en premier, en entier.');
        $this->assertEqualsWithDelta(2000, (float) $pRecente->montant_total, 0.01, 'La plus récente reçoit le reliquat.');
        $this->assertSame(2, (int) $pAncienne->statut, 'En attente de la seconde validation.');
    }

    public function test_deux_clients_differents_sont_refuses_et_l_ancien_champ_reste_accepte(): void
    {
        $this->admin();
        $client = $this->client();
        $autre  = Client::where('statut', 1)->where('id', '!=', $client->id)->first();
        $a = $this->location($client, 8000, now()->subDays(3)->toDateString());

        if ($autre) {
            $b = $this->location($autre, 5000, now()->subDays(2)->toDateString());
            $this->post('/encaissements/locations', [
                'numeros_location' => [$a->numero, $b->numero], 'mode_paiement_id' => $this->modeAgence()->id,
                'montant' => 1000, 'notes' => 'Mélange',
            ])->assertRedirect()->assertSessionHas('erreur_caisse');
            $this->assertSame(0, Paiement::where('service', 'LOCATION')->whereIn('service_id', [$a->id, $b->id])->count());
        }

        // Une seule location par l'ancien champ : toujours accepté.
        $r = $this->post('/encaissements/locations', [
            'numero_location' => $a->numero, 'mode_paiement_id' => $this->modeAgence()->id, 'montant' => 3000, 'notes' => 'Acompte',
        ]);
        $this->assertNull($r->getSession()->get('erreur_caisse'), (string) $r->getSession()->get('erreur_caisse'));
        $this->assertSame([], $r->getSession()->get('errors')?->all() ?? [], 'validation');
        $this->assertSame(url('/encaissements/locations'), $r->headers->get('Location'), 'session : ' . json_encode($r->getSession()->all(), JSON_UNESCAPED_UNICODE));
        $this->assertEqualsWithDelta(3000, (float) Paiement::where('service', 'LOCATION')->where('service_id', $a->id)->value('montant_total'), 0.01);
    }

    public function test_les_notes_sont_obligatoires_et_affichees_sur_les_deux_guichets(): void
    {
        $this->admin();
        $client = $this->client();
        $location = $this->location($client, 8000, now()->subDays(2)->toDateString());
        $demande  = $this->demande($client, 5000, now()->subDays(2)->toDateTimeString());

        // Sans notes : refus de validation, rien d'écrit.
        $this->post('/encaissements/locations', ['numeros_location' => [$location->numero], 'mode_paiement_id' => $this->modeAgence()->id, 'montant' => 1000])
            ->assertSessionHasErrors('notes');
        $this->post('/comptant/livraisons/encaissements', ['numeros_demande' => [$demande->numero], 'mode_paiement_id' => $this->modeAgence()->id, 'montant' => 1000])
            ->assertSessionHasErrors('notes');
        $this->assertSame(0, Paiement::whereIn('service_id', [$location->id, $demande->id])->whereIn('service', ['LOCATION', 'LIVRAISON'])->count());

        // Avec notes : enregistré, et la colonne « Notes » de chaque tableau la reprend.
        $this->post('/encaissements/locations', ['numeros_location' => [$location->numero], 'mode_paiement_id' => $this->modeAgence()->id, 'montant' => 1000, 'notes' => 'Acompte chantier Riviera']);
        $this->post('/comptant/livraisons/encaissements', ['numeros_demande' => [$demande->numero], 'mode_paiement_id' => $this->modeAgence()->id, 'montant' => 1000, 'notes' => 'Course du 12 septembre']);

        $html = $this->get('/encaissements/locations')->assertOk()->getContent();
        $this->assertStringContainsString('<th class="text-center">Notes</th>', $html, 'La colonne « Notes » manque au guichet des locations.');
        $this->assertStringContainsString('Acompte chantier Riviera', $html);
        $this->assertMatchesRegularExpression('/<textarea[^>]*name="notes"[^>]*required/', $html, 'Le champ Notes du guichet des locations doit être obligatoire.');

        $html = $this->get('/comptant/livraisons/encaissements')->assertOk()->getContent();
        $this->assertStringContainsString('<th class="text-center">Notes</th>', $html, 'La colonne « Notes » manque au guichet des livraisons.');
        $this->assertStringContainsString('Course du 12 septembre', $html);
        $this->assertMatchesRegularExpression('/<textarea[^>]*name="notes"[^>]*required/', $html, 'Le champ Notes du guichet des livraisons doit être obligatoire.');
    }

    /** La case d'en-tête n'était pas centrée (capture du 10/09/2026) : la règle des guichets couvre les deux tableaux. */
    public function test_les_cases_des_deux_tableaux_sont_remises_dans_le_flux(): void
    {
        $css = file_get_contents(public_path('backend/assets/css/premium-admin.css'));
        foreach (['#tableLocations', '#tableDemandes'] as $table) {
            $this->assertMatchesRegularExpression('/' . preg_quote($table, '/') . ' \.form-check-input[^{]*\{[^}]*position: static !important/s', $css,
                "{$table} : la case reste en position absolue (Bootstrap), donc hors de sa cellule.");
            $this->assertStringContainsString($table . ' th:first-child', $css, "{$table} : la première colonne n'est pas centrée.");
        }
        // Le cache des navigateurs ne peut pas garder l'ancienne feuille.
        $this->assertStringContainsString('premium-admin.css?v=1.8', file_get_contents(resource_path('views/layout/head.blade.php')));
    }

    /** LE SURPLUS DEVIENT UNE AVANCE (10/09/2026), comme au guichet des ventes ; et aucun guichet ne bloque la saisie par un attribut max. */
    public function test_le_surplus_devient_une_avance_sur_les_deux_guichets(): void
    {
        $admin  = $this->admin();
        $client = $this->client();
        $location = $this->location($client, 8000, now()->subDays(2)->toDateString());
        $demande  = $this->demande($client, 5000, now()->subDays(2)->toDateTimeString());
        $avancesAvant = \App\Models\AvanceClient::where('client_id', $client->id)->count();

        // Sans la case « surplus en avance » : refus, rien d'écrit.
        $this->post('/encaissements/locations', ['numeros_location' => [$location->numero], 'mode_paiement_id' => $this->modeAgence()->id, 'montant' => 10000, 'notes' => 'Trop'])
            ->assertRedirect()->assertSessionHas('erreur_caisse');
        $this->assertSame(0, Paiement::where('service', 'LOCATION')->where('service_id', $location->id)->count());

        // Avec la case : la location est réglée, l'excédent devient une avance en attente.
        $this->post('/encaissements/locations', ['numeros_location' => [$location->numero], 'mode_paiement_id' => $this->modeAgence()->id, 'montant' => 10000, 'notes' => 'Surplus', 'surplus_en_avance' => 1]);
        $this->assertEqualsWithDelta(8000, (float) Paiement::where('service', 'LOCATION')->where('service_id', $location->id)->value('montant_total'), 0.01);
        $avance = \App\Models\AvanceClient::where('client_id', $client->id)->orderByDesc('id')->first();
        $this->assertSame($avancesAvant + 1, \App\Models\AvanceClient::where('client_id', $client->id)->count(), 'Le surplus doit devenir une avance.');
        $this->assertEqualsWithDelta(2000, (float) $avance->montant, 0.01);
        $this->assertSame('SURPLUS', $avance->origine);
        $this->assertSame(\App\Models\AvanceClient::EN_ATTENTE, (int) $avance->statut, "L'avance attend sa seconde validation.");

        // Même règle au guichet des livraisons.
        $this->post('/comptant/livraisons/encaissements', ['numeros_demande' => [$demande->numero], 'mode_paiement_id' => $this->modeAgence()->id, 'montant' => 6500, 'notes' => 'Surplus', 'surplus_en_avance' => 1]);
        $this->assertEqualsWithDelta(5000, (float) Paiement::where('service', 'LIVRAISON')->where('service_id', $demande->id)->value('montant_total'), 0.01);
        $this->assertEqualsWithDelta(1500, (float) \App\Models\AvanceClient::where('client_id', $client->id)->orderByDesc('id')->value('montant'), 0.01);

        // Les fenêtres portent le bloc surplus, et AUCUN guichet ne pose l'attribut max sur le montant :
        // le navigateur refusait le formulaire avant que le surplus puisse devenir une avance.
        foreach (['/encaissements/locations', '/comptant/livraisons/encaissements', '/comptant/encaissements', '/clients-terme/paiements'] as $page) {
            $html = $this->get($page)->assertOk()->getContent();
            $this->assertStringContainsString('id="surplusEnAvance"', $html, "{$page} : le bloc surplus manque.");
            $this->assertStringNotContainsString("attr('max'", $html, "{$page} : le champ Montant ne doit pas être borné par max.");
            $this->assertMatchesRegularExpression('/<input[^>]*id="montant"(?![^>]*max=)[^>]*>/', $html, "{$page} : le champ Montant porte un attribut max.");
        }
    }

    public function test_le_guichet_des_livraisons_propose_le_tableau_et_encaisse_plusieurs_demandes(): void
    {
        $this->admin();
        $client = $this->client();
        $ancienne = $this->demande($client, 8000, now()->subDays(5)->toDateTimeString());
        $recente  = $this->demande($client, 6000, now()->subDay()->toDateTimeString());

        $html = $this->get('/comptant/livraisons/encaissements')->assertOk()->getContent();
        $this->assertStringContainsString('id="filtreClientDemandes"', $html);
        $this->assertStringContainsString('id="toutCocherDemandes"', $html);
        $this->assertStringContainsString('name="numeros_demande[]"', $html);
        $this->assertStringContainsString('value="' . $ancienne->numero . '"', $html);
        $this->assertStringNotContainsString('name="numero_demande"', $html);

        $r = $this->post('/comptant/livraisons/encaissements', [
            'numeros_demande' => [$recente->numero, $ancienne->numero],
            'mode_paiement_id' => $this->modeAgence()->id, 'montant' => 14000, 'notes' => 'Deux demandes',
        ]);
        $this->assertNull($r->getSession()->get('erreur_caisse'), (string) $r->getSession()->get('erreur_caisse'));
        $this->assertSame([], $r->getSession()->get('errors')?->all() ?? [], 'validation');
        $this->assertSame(url('/comptant/livraisons/encaissements'), $r->headers->get('Location'), 'session : ' . json_encode($r->getSession()->all(), JSON_UNESCAPED_UNICODE));

        $this->assertEqualsWithDelta(8000, (float) Paiement::where('service', 'LIVRAISON')->where('service_id', $ancienne->id)->value('montant_total'), 0.01);
        $this->assertEqualsWithDelta(6000, (float) Paiement::where('service', 'LIVRAISON')->where('service_id', $recente->id)->value('montant_total'), 0.01);

        // Dépassement : refusé, rien d'écrit de plus.
        $this->post('/comptant/livraisons/encaissements', [
            'numeros_demande' => [$ancienne->numero], 'mode_paiement_id' => $this->modeAgence()->id, 'montant' => 500, 'notes' => 'Trop',
        ])->assertRedirect()->assertSessionHas('erreur_caisse');
    }
}
