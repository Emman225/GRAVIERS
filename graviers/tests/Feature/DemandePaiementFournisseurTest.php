<?php

namespace Tests\Feature;

use App\Models\DemandePaiement;
use App\Models\Fournisseur;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * La demande de paiement du fournisseur.
 *
 * Le fournisseur avait son propre écran — trois champs, sans historique — et
 * son propre traitement, alors que le livreur et l'apporteur disposent déjà
 * d'un écran complet dont le contrôleur traite le profil fournisseur. Et sa
 * demande, une fois envoyée, n'apparaissait pas sur l'écran où
 * l'administrateur suit ce qu'il doit aux fournisseurs.
 */
class DemandePaiementFournisseurTest extends TestCase
{
    use DatabaseTransactions;

    private function unFournisseur(): Fournisseur
    {
        $fournisseur = Fournisseur::whereNotNull('user_id')->whereHas('user')->first();

        if (!$fournisseur) {
            $this->markTestSkipped('Aucun fournisseur rattaché à un compte.');
        }

        return $fournisseur;
    }

    private function unAdmin(): User
    {
        $admin = User::where('type_user_id', \Help::$USER_ADMIN)
            ->where('statut', \Help::$STATUT_ACTIF)->first();

        if (!$admin) {
            $this->markTestSkipped('Aucun administrateur actif.');
        }

        return $admin;
    }

    public function test_l_ancienne_adresse_mene_a_l_ecran_complet(): void
    {
        $fournisseur = $this->unFournisseur();

        URL::forceRootUrl('');

        $this->actingAs($fournisseur->user)
            ->get('/demande-de-paiement-fournisseur')
            ->assertRedirect(route('show.demandeDepaiePage'));
    }

    public function test_l_ecran_complet_accueille_le_fournisseur(): void
    {
        $fournisseur = $this->unFournisseur();

        URL::forceRootUrl('');

        $reponse = $this->actingAs($fournisseur->user)->get('/demande-de-paie');
        $reponse->assertOk();

        // Le profil est nommé, et le solde annoncé : c'est ce que l'ancien
        // écran ne montrait qu'à moitié, sans aucun historique.
        $reponse->assertSee('Fournisseur', false);
        $reponse->assertSee('Solde disponible', false);
    }

    public function test_la_demande_du_fournisseur_apparait_chez_l_admin(): void
    {
        $fournisseur = $this->unFournisseur();

        $demande = DemandePaiement::create([
            'montant'          => 4321,
            'user_id'          => $fournisseur->user_id,
            'numero_compte'    => '0102030405',
            'mode_paiement_id' => 6,
            'solde_debite_initiation' => 1,
        ]);

        URL::forceRootUrl('');

        $reponse = $this->actingAs($this->unAdmin())->get('/fournisseurs/paiements');
        $reponse->assertOk();

        $html = $reponse->getContent();

        $this->assertStringContainsString('Demandes de paiement initiées par les fournisseurs', $html);
        // Le montant demandé, et le fait que l'initiateur soit le fournisseur.
        $this->assertStringContainsString('4 321', $html);
        $this->assertStringContainsString('Le fournisseur', $html);
        $this->assertStringContainsString('0102030405', $html);

        $demande->delete();
    }

    public function test_l_envoi_d_une_demande_retient_le_montant_sur_le_solde(): void
    {
        $fournisseur = $this->unFournisseur();
        $fournisseur->update(['solde' => 50000]);

        URL::forceRootUrl('');

        $this->actingAs($fournisseur->user)->post('/demande-de-paiements', [
            'montant'  => 12000,
            'numero'   => '0102030405',
            'modePaie' => 6,
        ]);

        // Le montant est réservé dès l'envoi : le fournisseur ne peut pas
        // demander deux fois la même somme.
        $this->assertSame(38000.0, (float) $fournisseur->fresh()->solde);
    }

    public function test_une_demande_superieure_au_solde_est_refusee(): void
    {
        $fournisseur = $this->unFournisseur();
        $fournisseur->update(['solde' => 1000]);

        URL::forceRootUrl('');

        $this->actingAs($fournisseur->user)->post('/demande-de-paiements', [
            'montant'  => 9999,
            'numero'   => '0102030405',
            'modePaie' => 6,
        ]);

        $this->assertSame(1000.0, (float) $fournisseur->fresh()->solde);
    }
}
