<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * La confirmation du bouton « Facture DGI » (bon d'enlèvement seul).
 *
 * Le back-office remplace partout les fenêtres du navigateur par SweetAlert2 :
 * `delete-confirm.js` intercepte les boutons dont l'attribut onclick appelle
 * confirm(). Ce bouton-là y échappait — il avait justement été écrit en
 * fonction nommée pour ne pas être capté — et gardait donc l'alerte native.
 */
class ConfirmationFactureDgiTest extends TestCase
{
    private function source(): string
    {
        return file_get_contents(
            resource_path('views/orders/BECommande.blade.php')
        );
    }

    public function test_la_confirmation_passe_par_l_assistant_du_back_office(): void
    {
        $source = $this->source();

        $this->assertStringContainsString('window.confirmDelete', $source);
        $this->assertStringContainsString("mode: 'confirm'", $source);
        $this->assertStringContainsString('Oui, générer la facture', $source);
    }

    public function test_la_fenetre_du_navigateur_ne_reste_qu_en_repli(): void
    {
        $source = $this->source();

        // SweetAlert2 vient d'un CDN : le repli natif doit subsister, mais
        // seulement APRÈS le test de présence de l'assistant, jamais avant.
        $positionAssistant = strpos($source, 'typeof window.confirmDelete');
        $positionRepli     = strpos($source, 'window.confirm(');

        $this->assertNotFalse($positionAssistant);
        $this->assertNotFalse($positionRepli);
        $this->assertLessThan(
            $positionRepli,
            $positionAssistant,
            "Le repli natif doit venir après l'essai de SweetAlert2."
        );
    }
}
