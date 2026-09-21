<?php

namespace Tests\Feature;

use App\Models\Agence;
use App\Models\Client;
use App\Models\Commande;
use App\Models\DetailCommande;
use App\Models\ModePaiement;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * ON N'AFFICHE JAMAIS UN MONTANT QU'ON REFUSE D'ENCAISSER.
 *
 * Le franc CFA n'a pas de subdivision : personne ne remet 28 812,50 au guichet.
 * Or les totaux en portent — un taux appliqué à un prix catalogue tombe rarement
 * rond. L'écran affichait le montant arrondi (« Reste à payer : 28 813 FCFA »),
 * mais le plafond de saisie et le contrôle serveur comparaient à la valeur
 * brute, 28 812,5.
 *
 * Le caissier lisait donc 28 813, saisissait 28 813, et se voyait répondre que
 * la valeur devait être inférieure ou égale à 28 812,5. La commande ne pouvait
 * tout simplement pas être soldée : ni au franc supérieur, refusé, ni au franc
 * inférieur, qui l'aurait laissée éternellement débitrice d'un demi-franc.
 */
class EncaissementMontantEntierTest extends TestCase
{
    use DatabaseTransactions;

    /** L'arrondi doit être CELUI DE L'AFFICHAGE, sinon les deux redivergeront. */
    public function test_l_arrondi_suit_exactement_celui_de_l_affichage(): void
    {
        foreach ([28812.5, 100.4, 100.6, 0.5, 1234.0] as $brut) {
            $affiche = (float) str_replace(' ', '', str_replace(' fcfa', '', \Help::formatNombre($brut, true)));

            $this->assertSame($affiche, \Help::arrondiFranc($brut),
                "Le montant encaissable doit valoir exactement ce que l'écran affiche pour $brut.");
        }
    }

    /** Le cas signalé : 28 812,5 doit s'encaisser à 28 813. */
    public function test_un_demi_franc_est_encaissable_au_franc_superieur(): void
    {
        $this->assertSame(28813.0, \Help::arrondiFranc(28812.5));
    }

    /**
     * LE PLAFOND SERVEUR ACCEPTE LE MONTANT AFFICHÉ.
     *
     * C'est ce contrôle qui refusait l'encaissement : il comparait la saisie au
     * reste BRUT. Le test rejoue la commande du 24/08/2026 — 28 812,5 dû,
     * 28 813 saisis.
     */
    public function test_le_caissier_peut_encaisser_le_montant_affiche(): void
    {
        $caissier = $this->unCaissierRattache();
        $commande = $this->uneCommandeDe(28812.5);

        $this->assertSame(28812.5, round($commande->montantRestantDu(), 2),
            'La commande doit bien porter un demi-franc, sinon le test ne prouve rien.');

        $reponse = $this->actingAs($caissier)->post(route('show.comptant.encaissements.store'), [
            'numero_commande'   => $commande->numero,
            'mode_paiement_id'  => $this->unModePaiement()->id,
            'notes' => 'Recette automatique',
            'montant'           => 28813,
            'date_encaissement' => now()->format('Y-m-d'),
        ]);

        // ON VÉRIFIE L'EFFET, PAS LE MESSAGE.
        //
        // Flasher capte les clés « success » et « error » pour les rejouer en
        // notification et vide leur valeur de la session : les chercher là
        // ferait échouer le test alors que l'encaissement a bien eu lieu.
        // La preuve qui compte est la ligne de paiement.

        // Et il doit avoir laissé une trace du montant EXACT saisi. On ne
        // vérifie pas ici que la commande est soldée : l'encaissement naît en
        // attente d'une seconde validation (statut 2), et n'est compté comme
        // payé qu'une fois validé par un autre administrateur. Attendre un
        // solde nul aurait fait échouer le test pour une raison étrangère au
        // défaut corrigé.
        $encaissement = \App\Models\Paiement::where('service', 'COMMANDE')
            ->where('service_id', $commande->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($encaissement, "L'encaissement doit être enregistré.");
        $this->assertSame(28813.0, (float) $encaissement->montant_total,
            "Le montant encaissé doit être celui que le caissier a lu à l'écran.");
    }

    /**
     * NON-RÉGRESSION : le plafond protège toujours contre un vrai dépassement.
     *
     * L'arrondi tolère le demi-franc, pas la générosité : encaisser nettement
     * plus que le dû doit rester refusé, sans quoi une erreur de frappe
     * passerait en caisse.
     */
    public function test_un_depassement_reel_reste_refuse(): void
    {
        $caissier = $this->unCaissierRattache();
        $commande = $this->uneCommandeDe(28812.5);

        $reponse = $this->actingAs($caissier)->post(route('show.comptant.encaissements.store'), [
            'numero_commande'   => $commande->numero,
            'mode_paiement_id'  => $this->unModePaiement()->id,
            'notes' => 'Recette automatique',
            'montant'           => 30000,
            'date_encaissement' => now()->format('Y-m-d'),
        ]);

        // Là encore, c'est l'absence d'effet qui fait foi : la saisie est
        // renvoyée au formulaire, et RIEN n'est enregistré.
        $reponse->assertSessionHas('_old_input');

        $this->assertNull(
            \App\Models\Paiement::where('service', 'COMMANDE')
                ->where('service_id', $commande->id)
                ->first(),
            "Rien ne doit être encaissé quand le montant dépasse réellement le dû."
        );
    }

    // ------------------------------------------------------------- fixtures

    private function unCaissierRattache(): User
    {
        $agence = Agence::where('statut', \Help::$STATUT_ACTIF)->first();
        $this->assertNotNull($agence, 'La base de test doit comporter une agence active.');

        $caissier = User::where('type_user_id', \Help::$USER_ADMIN)->first();
        $this->assertNotNull($caissier, 'La base de test doit comporter un administrateur.');

        // Sans rattachement, l'encaissement est refusé AVANT le contrôle du
        // plafond : le test passerait pour la mauvaise raison.
        $caissier->agence_id = $agence->id;
        $caissier->save();

        return $caissier;
    }

    private function unModePaiement(): ModePaiement
    {
        $mode = ModePaiement::where('statut', \Help::$STATUT_ACTIF)->first();
        $this->assertNotNull($mode);

        return $mode;
    }

    /** Une commande comptant dont le net dû vaut exactement $montant. */
    private function uneCommandeDe(float $montant): Commande
    {
        $client = Client::first();
        $this->assertNotNull($client, 'La base de test doit comporter un client.');

        $produit = Produit::first();
        $this->assertNotNull($produit, 'La base de test doit comporter un produit.');

        $commande = Commande::create([
            'numero'         => 'T' . substr((string) microtime(true), -8),
            'client_id'      => $client->id,
            'date_commande'  => now(),
            'montant_total'  => $montant,
            'etat_commande'  => 1,
            'statut'         => \Help::$STATUT_ACTIF,
        ]);

        // Le net dû se calcule depuis les LIGNES, jamais depuis montant_total :
        // c'est la règle du modèle, et s'en écarter donnerait une fixture
        // complaisante qui ne prouverait rien.
        DetailCommande::create([
            'commande_id' => $commande->id,
            'produit_id'  => $produit->id,
            'quantite'    => 1,
            'prix'        => $montant,
            'montant'     => $montant,
        ]);

        return $commande->fresh();
    }
}
