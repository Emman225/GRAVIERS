<?php

namespace Tests\Feature;

use App\Models\AdresseLivraison;
use App\Models\Client;
use App\Models\Location;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * LE MODE DE RÉCUPÉRATION SE CONSTATE, IL NE SE CHOISIT PLUS.
 *
 * L'écran de validation d'une location proposait deux cases à cocher —
 * « Livraison » / « Retrait sur place » —, pré-remplies depuis le choix du
 * client mais modifiables. Le gestionnaire pouvait donc contredire son client
 * sans s'en rendre compte, et le formulaire acceptait « livraison » sur une
 * location sans adresse comme « retrait » sur une location qui en portait une.
 *
 * Il affiche désormais le choix du client, et rien d'autre.
 *
 * LE PIÈGE DE `est_livrable` : la colonne est NOT NULL avec 0 pour défaut. Une
 * location créée avant son ajout se lit donc « retrait sur place » sans que le
 * client l'ait jamais demandé. L'ADRESSE tranche : on n'en choisit une que pour
 * se faire livrer. Sa présence l'emporte sur un 0 qui n'est qu'un défaut.
 */
class ModeRecuperationLocationTest extends TestCase
{
    use DatabaseTransactions;

    private function uneLocation(int $estLivrable, bool $avecAdresse): Location
    {
        $client = Client::first();

        if (!$client) {
            $this->markTestSkipped('Aucun client.');
        }

        $adresse = null;

        if ($avecAdresse) {
            $adresse = AdresseLivraison::first();

            if (!$adresse) {
                $this->markTestSkipped('Aucune adresse de livraison.');
            }
        }

        return Location::create([
            'numero'               => 'TST-' . uniqid(),
            'client_id'            => $client->id,
            'montant_total'        => 50000,
            'etat_location'        => \Help::$LOCATION_EN_ATTENTE,
            'statut'               => \Help::$STATUT_ACTIF,
            'est_livrable'         => $estLivrable,
            'adresse_livraison_id' => $adresse?->id,
        ]);
    }

    /** LE CLIENT A DEMANDÉ LA LIVRAISON. */
    public function test_une_location_a_livrer_n_est_pas_un_retrait(): void
    {
        $this->assertFalse($this->uneLocation(1, true)->estRetraitSurPlace());
        $this->assertSame('Livraison', $this->uneLocation(1, true)->libelleModeRecuperation());
    }

    /** LE CLIENT A DEMANDÉ LE RETRAIT. */
    public function test_une_location_sans_livraison_ni_adresse_est_un_retrait(): void
    {
        $this->assertTrue($this->uneLocation(0, false)->estRetraitSurPlace());
        $this->assertSame('Retrait sur place',
            $this->uneLocation(0, false)->libelleModeRecuperation());
    }

    /**
     * L'ADRESSE TRANCHE CONTRE LE DÉFAUT DE LA COLONNE.
     *
     * `est_livrable` à 0 SANS choix du client — la valeur par défaut — sur une
     * location qui porte une adresse : le client s'est bien fait livrer. La
     * traiter en retrait enverrait le matériel nulle part.
     */
    public function test_une_adresse_l_emporte_sur_le_defaut_de_la_colonne(): void
    {
        $location = $this->uneLocation(0, true);

        $this->assertFalse($location->estRetraitSurPlace(),
            'Une location qui porte une adresse de livraison est traitée en '
            . 'retrait sur place : le client a pourtant donné une adresse, et '
            . 'personne ne lui apportera son matériel.');
    }

    /** L'ÉCRAN NE PROPOSE PLUS DE CHOISIR. */
    public function test_l_ecran_n_offre_plus_de_case_a_cocher(): void
    {
        $vue = file_get_contents(
            resource_path('views/gestionnaire/validerLocation.blade.php'));

        $this->assertStringNotContainsString('name="mode_livraison"', $vue,
            'L’écran propose encore de choisir le mode : le gestionnaire peut '
            . 'contredire son client.');

        $this->assertStringContainsString('libelleModeRecuperation()', $vue,
            'L’écran doit AFFICHER le mode choisi par le client.');
    }

    /** ET LE CONTRÔLEUR LIT LA MÊME RÈGLE. */
    public function test_le_controleur_lit_la_regle_du_modele(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/UserController.php'));

        $this->assertStringContainsString('$location->estRetraitSurPlace()', $source,
            'Le contrôleur décide du mode de son côté : il finira par diverger '
            . 'de ce que l’écran affiche.');

        $this->assertStringNotContainsString("\$request->mode_livraison", $source,
            'Le contrôleur lit encore un champ que le formulaire n’envoie plus : '
            . 'toute location serait traitée comme une livraison.');
    }
}
