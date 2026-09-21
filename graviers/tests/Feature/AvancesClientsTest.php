<?php

namespace Tests\Feature;

use App\Models\Agence;
use App\Models\AvanceClient;
use App\Models\Client;
use App\Models\Commande;
use App\Models\DetailCommande;
use App\Models\LignePaiement;
use App\Models\ModePaiement;
use App\Models\MouvementAvance;
use App\Models\Paiement;
use App\Models\Produit;
use App\Models\TvaCommande;
use App\Models\User;
use App\Services\Avances;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * LES AVANCES DES CLIENTS (point 19, réponses du 07/09/2026).
 *
 *  - pas de dépôt tant qu'une affaire n'est pas soldée (Q3) ;
 *  - le dépôt attend une seconde validation, puis devient disponible (Q6) ;
 *  - une commande « en agence » s'impute d'elle-même, le reliquat reste dû
 *    (Q1) ; un règlement AV-… est créé, déjà validé ;
 *  - le surplus d'un encaissement devient une avance, mais seulement si le
 *    caissier le dit (Q3) ;
 *  - le reçu de dépôt porte la mention « non remboursable » (Q4).
 */
class AvancesClientsTest extends TestCase
{
    use DatabaseTransactions;

    private function admins(): array
    {
        $agence = Agence::first();
        $admins = User::whereIn('type_user_id', [1, 2])->where('statut', 1)->orderBy('id')->take(2)->get();
        if (!$agence || $admins->count() < 2) {
            $this->markTestSkipped('Il faut une agence et deux administrateurs.');
        }
        foreach ($admins as $a) {
            if (!$a->agence_id) {
                $a->agence_id = $agence->id;
                $a->save();
            }
        }
        return [$admins[0], $admins[1]];
    }

    /** Un client ordinaire actif qui ne doit rien. */
    private function clientSansDette(): Client
    {
        $client = Client::where('statut', 1)
            ->where(fn ($q) => $q->where('client_a_terme', 0)->orWhereNull('client_a_terme'))
            ->whereHas('user')
            ->get()
            ->first(fn (Client $c) => Avances::affairesNonSoldees($c)->isEmpty());
        if (!$client) {
            $this->markTestSkipped('Aucun client ordinaire sans affaire non soldée.');
        }
        return $client;
    }

    /** Une commande « en agence » du client, due, avec ses lignes et sa TVA. */
    private function commandeEnAgence(Client $client, float $prix, int $qte): Commande
    {
        $produit = Produit::where('statut', 1)->first() ?: Produit::first();
        if (!$produit) {
            $this->markTestSkipped('Aucun produit en base.');
        }
        $commande = Commande::create([
            'numero'         => 'T' . random_int(10000, 99999),
            'client_id'      => $client->id,
            'date_commande'  => now(),
            'etat_commande'  => \Help::$COMMANDE_EN_ATTENTE,
            'montant_total'  => $prix * $qte,
            'statut'         => 1,
            'remise'         => 0,
            'cout_livraison_client' => 0,
        ]);
        DetailCommande::create([
            'produit_id'  => $produit->id,
            'commande_id' => $commande->id,
            'qte'         => $qte,
            'prix'        => $prix,
        ]);
        TvaCommande::create([
            'client_id'    => $client->id,
            'montant'      => 0,
            'commande_id'  => $commande->id,
            'type_affaire' => 2,
        ]);
        return Commande::find($commande->id);
    }

    private function deposer(Client $client, float $montant, User $caissier): AvanceClient
    {
        $mode = ModePaiement::listePourAgent()->first() ?: ModePaiement::first();
        Auth::guard('web')->login($caissier);
        $this->post('/avances', [
            'client_id'        => $client->id,
            'montant'          => $montant,
            'mode_paiement_id' => $mode->id,
            'notes' => 'Recette automatique',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $avance = AvanceClient::where('client_id', $client->id)->orderByDesc('id')->first();
        $this->assertNotNull($avance);
        $this->assertSame(AvanceClient::EN_ATTENTE, (int) $avance->statut);
        $this->assertStringStartsWith('RA-' . date('Y') . '-', $avance->numero_recu);

        return $avance;
    }

    public function test_le_depot_est_refuse_quand_le_client_a_une_affaire_non_soldee(): void
    {
        [$caissier] = $this->admins();
        $client = $this->clientSansDette();
        $this->commandeEnAgence($client, 1000, 2);
        $mode = ModePaiement::listePourAgent()->first() ?: ModePaiement::first();

        Auth::guard('web')->login($caissier);
        $avant = AvanceClient::count();
        $this->post('/avances', [
            'client_id'        => $client->id,
            'montant'          => 5000,
            'mode_paiement_id' => $mode->id,
            'notes' => 'Recette automatique',
        ])->assertRedirect();

        $this->assertSame($avant, AvanceClient::count(), "Un client qui doit encore quelque chose ne dépose pas d'avance.");
        $this->assertSame(0.0, Avances::soldeDisponible($client));
    }

    public function test_un_depot_valide_donne_un_solde_qui_s_impute_sur_la_commande_suivante(): void
    {
        Mail::fake();
        [$caissier, $validateur] = $this->admins();
        $client = $this->clientSansDette();

        $avance = $this->deposer($client, 5000, $caissier);
        $this->assertSame(0.0, Avances::soldeDisponible($client), 'Rien n\'est disponible avant la seconde validation.');

        // Le caissier ne peut pas valider son propre dépôt.
        $this->post('/avances/' . $avance->id . '/valider')->assertRedirect();
        $this->assertSame(AvanceClient::EN_ATTENTE, (int) $avance->fresh()->statut, 'Le caissier ne valide pas son propre dépôt.');

        Auth::guard('web')->login($validateur);
        $this->post('/avances/' . $avance->id . '/valider')->assertRedirect();
        $avance->refresh();
        $this->assertSame(AvanceClient::DISPONIBLE, (int) $avance->statut);
        $this->assertSame(5000.0, Avances::soldeDisponible($client));
        $this->assertSame(1, MouvementAvance::where('avance_client_id', $avance->id)->where('type', 'DEPOT')->count());

        // Une commande de 8 000 réglée « en agence » : 5 000 imputés, 3 000 restent dus (Q1).
        $commande = $this->commandeEnAgence($client, 4000, 2);
        $resultat = Avances::imputerSurCommande($commande, $validateur->id);

        $this->assertEqualsWithDelta(5000, $resultat['impute'], 0.01);
        $this->assertEqualsWithDelta(3000, $resultat['reste'], 0.01);
        $this->assertEqualsWithDelta(3000, Commande::find($commande->id)->montantRestantDu(), 0.01);
        $this->assertSame(0.0, Avances::soldeDisponible($client), 'L\'avance est épuisée.');
        $this->assertSame('Épuisée', $avance->fresh()->libelleStatut());

        $reglement = Paiement::where('service', 'COMMANDE')->where('service_id', $commande->id)->first();
        $this->assertNotNull($reglement);
        $this->assertSame(1, (int) $reglement->statut, 'Le règlement issu d\'une avance est déjà validé.');
        $this->assertStringStartsWith('AV-' . date('Y') . '-', $reglement->numero_recu);
        $this->assertSame((int) $validateur->id, (int) $reglement->user_valide2_id);
        $ligne = LignePaiement::where('paiement_id', $reglement->id)->first();
        $this->assertStringContainsString('Avance client', $ligne->moyen_paiement);
        $this->assertSame(1, MouvementAvance::where('avance_client_id', $avance->id)->where('type', 'DEDUCTION')->count());

        // Le reçu du règlement dit d'où vient l'argent, et le reçu de dépôt dit
        // que l'avance ne se rembourse pas (Q4).
        $this->get('/recu/' . $reglement->id)->assertOk()->assertSee('Avance client');
        $this->get('/avances/' . $avance->id . '/recu')->assertOk()
            ->assertSee('pas remboursable')
            ->assertSee($avance->numero_recu);

        // Une seconde imputation ne fait rien : rien n'est disponible.
        $encore = Avances::imputerSurCommande($commande, $validateur->id);
        $this->assertSame(0.0, $encore['impute']);
    }

    public function test_le_surplus_d_un_encaissement_devient_une_avance_si_le_caissier_le_dit(): void
    {
        [$caissier] = $this->admins();
        $client = $this->clientSansDette();
        $commande = $this->commandeEnAgence($client, 1500, 2);
        $mode = ModePaiement::listePourAgent()->first() ?: ModePaiement::first();
        $reste = \Help::arrondiFranc($commande->montantRestantDu());

        Auth::guard('web')->login($caissier);

        // Sans la case : refusé, rien n'est écrit.
        $paiementsAvant = Paiement::count();
        $this->post('/comptant/encaissements', [
            'numeros_commande' => [$commande->numero],
            'mode_paiement_id' => $mode->id,
            'notes' => 'Recette automatique',
            'montant'          => $reste + 700,
        ])->assertRedirect();
        $this->assertSame($paiementsAvant, Paiement::count(), "Sans la case, le dépassement est refusé : rien n'est écrit.");
        $this->assertSame(0, AvanceClient::where('client_id', $client->id)->where('origine', 'SURPLUS')->count());

        // Avec la case : le reste est encaissé, les 700 deviennent une avance.
        $this->post('/comptant/encaissements', [
            'numeros_commande'  => [$commande->numero],
            'mode_paiement_id'  => $mode->id,
            'notes' => 'Recette automatique',
            'montant'           => $reste + 700,
            'surplus_en_avance' => 1,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame($paiementsAvant + 1, Paiement::count());
        $reglement = Paiement::where('service', 'COMMANDE')->where('service_id', $commande->id)->orderByDesc('id')->first();
        $this->assertEqualsWithDelta($reste, (float) $reglement->montant_total, 0.01, 'Le règlement ne dépasse jamais le reste dû.');

        $avance = AvanceClient::where('client_id', $client->id)->where('origine', 'SURPLUS')->first();
        $this->assertNotNull($avance);
        $this->assertEqualsWithDelta(700, (float) $avance->montant, 0.01);
        $this->assertSame(AvanceClient::EN_ATTENTE, (int) $avance->statut, 'Le surplus suit la double validation.');
        $this->assertSame($reglement->numero_recu, $avance->origine_recu);
    }

    public function test_la_page_des_avances_et_les_guichets_proposent_le_depot(): void
    {
        [$caissier] = $this->admins();
        Auth::guard('web')->login($caissier);

        $this->get('/avances')->assertOk()->assertSee('Avances clients')->assertSee('Nouvelle avance');
        $this->get('/comptant/encaissements')->assertOk()->assertSee("Dépôt d'avance", false);
        $this->get('/clients-terme/paiements')->assertOk()->assertSee("Dépôt d'avance", false);
        $this->get('/gestionnaire/home')->assertOk()->assertSee('Avances clients');
    }
}
