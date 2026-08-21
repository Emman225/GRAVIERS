<?php

namespace Tests\Feature;

use App\Models\Apporteur;
use App\Models\Fournisseur;
use App\Models\Livreur;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Le bouton « Régler » des écrans de dettes mène au guichet de paiement.
 *
 * Il ouvrait un popup local qui écrivait le règlement par un autre chemin que
 * les écrans de paiement — sans imputation sur les bons, les courses ou les
 * commissions, et sans les reçus. Il renvoie désormais vers le guichet, avec
 * le tiers déjà choisi.
 */
class ReglerDepuisLesDettesTest extends TestCase
{
    use DatabaseTransactions;

    private function unAdmin(): User
    {
        $admin = User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN])
            ->where('statut', \Help::$STATUT_ACTIF)->first();

        if (!$admin) {
            $this->markTestSkipped('Aucun administrateur actif.');
        }

        if (!$admin->agence_id && ($agence = \App\Models\Agence::first())) {
            $admin->update(['agence_id' => $agence->id]);
        }

        return $admin->fresh();
    }

    public function test_l_ecran_des_dettes_apporteurs_mene_au_guichet(): void
    {
        $apporteur = Apporteur::first();

        if (!$apporteur) {
            $this->markTestSkipped('Aucun apporteur.');
        }

        $apporteur->update(['solde' => 5000]);

        URL::forceRootUrl('');
        $html = $this->actingAs($this->unAdmin())->get('/dettes/apporteurs')->getContent();

        $this->assertStringContainsString(
            'apporteurs/paiements?regler=' . $apporteur->id,
            $html
        );
        // Le popup local n'a plus lieu d'être.
        $this->assertStringNotContainsString('btn-regler-dette', $html);
    }

    public function test_l_ecran_des_dettes_fournisseurs_mene_au_guichet(): void
    {
        $fournisseur = Fournisseur::first();

        if (!$fournisseur) {
            $this->markTestSkipped('Aucun fournisseur.');
        }

        $fournisseur->update(['solde' => 5000]);

        URL::forceRootUrl('');
        $html = $this->actingAs($this->unAdmin())->get('/dettes/fournisseurs')->getContent();

        $this->assertStringContainsString(
            'fournisseurs/paiements?regler=' . $fournisseur->id,
            $html
        );
    }

    public function test_l_ecran_des_dettes_livreurs_mene_au_guichet(): void
    {
        $livreur = Livreur::first();

        if (!$livreur) {
            $this->markTestSkipped('Aucun livreur.');
        }

        $livreur->update(['solde' => 5000]);

        URL::forceRootUrl('');
        $html = $this->actingAs($this->unAdmin())->get('/dettes/livreurs')->getContent();

        $this->assertStringContainsString(
            'livreurs/paiements?regler=' . $livreur->id,
            $html
        );
    }

    /** @dataProvider guichets */
    public function test_le_guichet_sait_ouvrir_sur_le_tiers_recu(string $url, string $select, string $modal): void
    {
        URL::forceRootUrl('');
        $html = $this->actingAs($this->unAdmin())->get($url)->getContent();

        // Le paramètre est lu, le tiers sélectionné, et le formulaire ouvert
        // par un clic sur le bouton existant — cette version de Bootstrap
        // n'expose pas Modal.getOrCreateInstance.
        $this->assertStringContainsString("get('regler')", $html);
        $this->assertStringContainsString("$('#" . $select . "')", $html);
        $this->assertStringContainsString('data-bs-target="#' . $modal . '"', $html);
    }

    public static function guichets(): array
    {
        return [
            'apporteurs'   => ['/apporteurs/paiements', 'filtreApporteur', 'modalPaiementApp'],
            'fournisseurs' => ['/fournisseurs/paiements', 'filtreFournisseur', 'modalPaiementFourn'],
            'livreurs'     => ['/livreurs/paiements', 'filtreLivreur', 'modalPaiementLivreur'],
        ];
    }
}
