<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Une confirmation avant toute décision sur une demande de paiement.
 *
 * Les deux boutons de VALIDATION n'en demandaient aucune : un clic suffisait
 * à engager le versement. Seul « Rejeter » en avait une.
 *
 * Le back-office remplace partout les fenêtres du navigateur par SweetAlert2 :
 * `delete-confirm.js` intercepte les boutons dont l'attribut onclick appelle
 * confirm(). Il suffit d'en poser un — le style vient tout seul.
 */
class ConfirmationDemandePaiementTest extends TestCase
{
    /** @dataProvider ecrans */
    public function test_les_trois_decisions_demandent_confirmation(string $vue, string $tiers): void
    {
        $source = file_get_contents(resource_path('views/admin/' . $vue));

        // Les trois actions : 1re validation, 2e validation, refus.
        $this->assertSame(3, substr_count($source, 'return confirm('));

        $this->assertStringContainsString('Donner la 1re validation', $source);
        $this->assertStringContainsString('Accepter et PAYER', $source);
        $this->assertStringContainsString('Refuser cette demande', $source);

        // Le tiers est nommé, pour qu'on sache qui l'on paie.
        $this->assertStringContainsString($tiers, $source);
    }

    /** @dataProvider ecrans */
    public function test_aucun_message_ne_contient_d_apostrophe(string $vue): void
    {
        $source = file_get_contents(resource_path('views/admin/' . $vue));

        // L'extracteur de delete-confirm.js coupe le message à la première
        // apostrophe, MÊME ÉCHAPPÉE : la fenêtre affichait « …devra ensuite
        // l\ ». Les messages sont donc écrits sans apostrophe.
        preg_match_all("/return confirm\('([^']*)'/", $source, $trouves);

        $this->assertCount(3, $trouves[1], 'Un message a été tronqué à une apostrophe.');

        foreach ($trouves[1] as $message) {
            $this->assertStringEndsWith('.', $message, "Message incomplet : {$message}");
        }
    }

    public static function ecrans(): array
    {
        return [
            'apporteurs'   => ['listeDeDemandeApporteur.blade.php', 'cet apporteur'],
            'livreurs'     => ['listeDeDemande.blade.php', 'ce livreur'],
            'fournisseurs' => ['listeDeDemandeFournisseur.blade.php', 'ce fournisseur'],
        ];
    }
}
