<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * LE TRAITEMENT D'UNE DEMANDE DE LIVRAISON NE REDEMANDE PAS LA DATE (10/09/2026) :
 * le client l'a choisie en passant sa demande, la course la reprend. L'écran
 * la montre, il ne la ressaisit pas.
 */
class TraitementSansDateDeLivraisonTest extends TestCase
{
    public function test_le_formulaire_montre_la_date_du_client_sans_la_redemander(): void
    {
        $vue = file_get_contents(resource_path('views/gestionnaire/traiteLivraison.blade.php'));
        $this->assertStringNotContainsString('name="date"', $vue, 'Le champ de date ne doit plus exister.');
        $this->assertStringContainsString('Date de livraison demandée par le client', $vue);
        $this->assertStringContainsString('$livraisons->date_livraison', $vue);

        $controleur = file_get_contents(app_path('Http/Controllers/UserController.php'));
        $this->assertStringNotContainsString("'date' => 'required|date',", $controleur, 'La date ne doit plus être exigée au serveur.');
        $this->assertStringContainsString('\'date_livraison\' => $demandeLivraison->date_livraison,', $controleur, 'La course reprend la date de la demande.');
    }
}
