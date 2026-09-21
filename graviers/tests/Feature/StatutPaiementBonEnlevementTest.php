<?php

namespace Tests\Feature;

use App\Models\Commande;
use App\Models\LignePaiement;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Un règlement saisi n'est pas « aucun paiement ».
 *
 * Le règlement d'un client à terme est enregistré au statut 2 : il attend la
 * seconde validation, et ne compte donc pas encore dans le montant payé. Le bon
 * d'enlèvement affichait alors « Aucun paiement effectué » — ce qui est faux
 * pour le client, qui a versé, et pour le caissier, qui a encaissé.
 *
 * Trois états distincts désormais : rien reçu, reçu mais pas encore contrôlé,
 * ou soldé.
 */
class StatutPaiementBonEnlevementTest extends TestCase
{
    use DatabaseTransactions;

    private function unAdmin(): User
    {
        $admin = User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN])
            ->where('statut', \Help::$STATUT_ACTIF)->first();

        if (!$admin) {
            $this->markTestSkipped('Aucun administrateur actif.');
        }

        return $admin;
    }

    private function uneCommande(): Commande
    {
        $commande = Commande::whereHas('detailCommande')->latest('id')->first();

        if (!$commande) {
            $this->markTestSkipped('Aucune commande exploitable.');
        }

        // On repart d une commande sans reglement enregistre.
        LignePaiement::where('service_id', $commande->id)
            ->where('service', \Help::$COMMANDE)->delete();

        return $commande;
    }

    private function unReglement(Commande $commande, float $montant, int $statut): LignePaiement
    {
        // Une ligne appartient toujours a un paiement : la colonne est
        // obligatoire en base.
        $paiement = \App\Models\Paiement::create([
            'client_id'     => $commande->client_id,
            'code'          => 'TST-' . strtoupper(substr(md5(uniqid()), 0, 8)),
            'libelle'       => 'Reglement de recette',
            'montant_total' => $montant,
            'montant_restant' => 0,
            'statut'        => $statut,
            'service'       => \Help::$COMMANDE,
            'service_id'    => $commande->id,
        ]);

        return LignePaiement::create([
            'paiement_id'      => $paiement->id,
            'mode_paiement_id' => \App\Models\ModePaiement::first()?->id,
            'montant'     => $montant,
            'statut'      => $statut,
            'service'     => \Help::$COMMANDE,
            'service_id'  => $commande->id,
        ]);
    }

    private function ouvrir(Commande $commande): \Illuminate\Testing\TestResponse
    {
        URL::forceRootUrl('');

        return $this->actingAs($this->unAdmin())->get('/orders-be/' . $commande->numero);
    }

    public function test_un_reglement_en_attente_n_est_pas_aucun_paiement(): void
    {
        $commande = $this->uneCommande();

        $this->unReglement($commande, $commande->montantAPayer(), 2);

        $reponse = $this->ouvrir($commande);

        $reponse->assertOk();
        $reponse->assertSee('en attente de validation', false);
        $reponse->assertDontSee('Aucun paiement effectué', false);
    }

    public function test_sans_aucun_reglement_le_message_reste(): void
    {
        // Le garde-fou ne doit pas masquer le vrai cas « rien recu ».
        $commande = $this->uneCommande();

        $reponse = $this->ouvrir($commande);

        $reponse->assertOk();
        $reponse->assertSee('Aucun paiement effectué', false);
    }

    public function test_le_montant_en_attente_est_annonce(): void
    {
        $commande = $this->uneCommande();

        $this->unReglement($commande, $commande->montantAPayer(), 2);

        $reponse = $this->ouvrir($commande);

        $this->assertGreaterThan(0, (float) $reponse->viewData('montantEnAttente'));
        $reponse->assertSee('second administrateur', false);
    }

    public function test_un_reglement_valide_compte_normalement(): void
    {
        $commande = $this->uneCommande();

        $this->unReglement($commande, $commande->montantAPayer(), \Help::$STATUT_ACTIF);

        $reponse = $this->ouvrir($commande);

        $reponse->assertOk();
        $reponse->assertDontSee('Aucun paiement effectué', false);
        $reponse->assertDontSee('en attente de validation', false);
    }

    public function test_le_bon_imprime_dit_la_meme_chose(): void
    {
        // Un bon papier qui contredit l ecran est pire que pas de bon du tout.
        $commande = $this->uneCommande();

        $this->unReglement($commande, $commande->montantAPayer(), 2);

        $donnees = $this->ouvrir($commande)->viewData('montantEnAttente');

        $this->assertGreaterThan(0, (float) $donnees);

        $html = view('orders.BECommande-pdf', [
            'commande'         => $commande,
            'details'          => [],
            'restant'          => $commande->montantAPayer(),
            'montantAPayer'    => $commande->montantAPayer(),
            'montantEnAttente' => $donnees,
            'config'           => \App\Models\Configuration::first(),
            'nbFacturables'    => 0,
        ])->render();

        $this->assertStringContainsString('en attente de validation', $html);
    }
}
