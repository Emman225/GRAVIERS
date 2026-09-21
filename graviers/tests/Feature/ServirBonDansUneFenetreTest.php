<?php

namespace Tests\Feature;

use App\Models\Enlevement;
use App\Models\Fournisseur;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * LA QUANTITÉ SERVIE D'UN BON SE SAISIT DANS UNE FENÊTRE (10/09/2026) : sur le
 * détail d'un bon non encore traité, un bouton « Servir ce bon » ouvre une
 * fenêtre qui porte le champ et le bouton « Valider la quantité servie ».
 */
class ServirBonDansUneFenetreTest extends TestCase
{
    public function test_le_champ_et_le_bouton_sont_dans_la_fenetre(): void
    {
        $source = file_get_contents(resource_path('views/fournisseur/detailBon.blade.php'));

        $fenetre = strpos($source, 'id="modalServirBon"');
        $this->assertNotFalse($fenetre, 'La fenêtre est absente.');
        $this->assertGreaterThan($fenetre, strpos($source, 'id="qteServiInput"'), 'Le champ doit être dans la fenêtre.');
        $this->assertGreaterThan($fenetre, strpos($source, 'id="open-confirmation-bon"'), 'Le bouton doit être dans la fenêtre.');
        $this->assertStringContainsString('data-bs-target="#modalServirBon"', $source);
        $this->assertStringContainsString('Valider la quantité servie', $source);
        $this->assertStringContainsString('modal-dialog-centered', $source);
        $this->assertStringContainsString('shown.bs.modal', $source);
    }

    public function test_la_page_servie_porte_la_fenetre_pour_un_bon_non_traite(): void
    {
        $fournisseur = Fournisseur::whereHas('user', fn ($q) => $q->where('statut', 1))->first();
        $bon = $fournisseur
            ? Enlevement::where('fournisseur_id', $fournisseur->id)->whereNotNull('code_enleve')->whereNull('fournisseur_validation')->first()
            : null;
        if (!$fournisseur || !$bon) {
            $this->markTestSkipped('Aucun bon non traité pour un fournisseur avec un compte.');
        }
        Auth::guard('web')->login($fournisseur->user);

        $this->get('/seller/bon/details/' . $bon->code_enleve)->assertOk()
            ->assertSee('id="modalServirBon"', false)
            ->assertSee('Servir ce bon')
            ->assertSee('Valider la quantité servie')
            ->assertSee('max="' . $bon->qte . '"', false);
    }
}
