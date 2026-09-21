<?php

namespace Tests\Feature;

use App\Models\Configuration;
use App\Services\FneService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * LES MENTIONS LÉGALES DE L'ENTREPRISE DOIVENT ÊTRE SAISISSABLES.
 *
 * Les onze colonnes existaient en base, et les factures comme les reçus les
 * LISAIENT depuis toujours. Mais aucun écran ne permettait de les renseigner :
 * les lignes s'imprimaient vides chez le client, et les valeurs enregistrées
 * s'étaient décalées d'une case — le RCCM dans « adresse du siège », les
 * références bancaires dans « téléphone » — sans que personne ne puisse les
 * corriger autrement qu'en base.
 *
 * Le NCC est le plus sensible : il n'est pas seulement imprimé, il préfixe le
 * numéro de chaque facture normalisée et il est encodé dans le QR code que
 * l'administration scanne.
 */
class MentionsLegalesEntrepriseTest extends TestCase
{
    use DatabaseTransactions;

    /** Les onze champs figurent dans le formulaire, avec leur nom exact. */
    public function test_l_onglet_entreprise_porte_les_onze_champs(): void
    {
        $vue = file_get_contents(resource_path('views/layout/parametre.blade.php'));

        foreach ([
            'raison_sociale', 'ncc', 'regime_imposition', 'centre_impots', 'rccm',
            'ref_bancaires', 'adresse_siege', 'telephone', 'email_entreprise',
            'capital_social', 'cnps',
        ] as $champ) {
            $this->assertStringContainsString(
                'name="' . $champ . '"',
                $vue,
                "Le champ « $champ » n'a pas de saisie : sa ligne continuera de "
                . "s'imprimer vide sur les factures."
            );
        }
    }

    /** Un champ affiché mais non enregistré serait pire que pas de champ. */
    public function test_les_onze_champs_sont_enregistres(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/UserController.php'));
        $code = preg_replace('!/\*.*?\*/!s', '', $source);
        $code = preg_replace('!^\s*//.*$!m', '', $code);

        $debut = strpos($code, 'function parametreUpdate(');
        $fin   = strpos($code, 'function ', $debut + 20);
        $bloc  = substr($code, $debut, ($fin === false ? strlen($code) : $fin) - $debut);

        foreach ([
            'raison_sociale', 'ncc', 'regime_imposition', 'centre_impots', 'rccm',
            'ref_bancaires', 'adresse_siege', 'telephone', 'email_entreprise',
            'capital_social', 'cnps',
        ] as $champ) {
            $this->assertStringContainsString(
                "'" . $champ . "'",
                $bloc,
                "« $champ » est saisissable mais n'est pas enregistré : "
                . "le gestionnaire croirait l'avoir corrigé."
            );
        }
    }

    /**
     * LE NUMÉRO DE FACTURE NE DOIT PAS CONTENIR D'ESPACE.
     *
     * Le NCC de DALAKOUN s'écrit « 2507546 J ». Concaténé tel quel, il
     * produisait « 2507546 JU26… » — un identifiant avec une espace, que
     * l'administration a toutes les chances de refuser.
     */
    public function test_le_numero_de_facture_ne_porte_pas_l_espace_du_ncc(): void
    {
        Configuration::first()->update(['ncc' => '2507546 J']);

        $numero = FneService::genererNumeroFne();

        $this->assertStringNotContainsString(' ', $numero,
            "Le numéro de facture normalisée contient une espace : « $numero ».");

        $this->assertStringStartsWith('2507546J' . date('y'), str_replace('U', '', $numero),
            'Le NCC nettoyé doit rester reconnaissable dans le numéro.');
    }

    /** La valeur SAISIE reste intacte : c'est elle qui s'imprime et part au QR. */
    public function test_le_ncc_saisi_n_est_pas_modifie_en_base(): void
    {
        Configuration::first()->update(['ncc' => '2507546 J']);

        FneService::genererNumeroFne();

        $this->assertSame('2507546 J', Configuration::first()->ncc,
            "Le nettoyage ne doit toucher QUE le numéro : le NCC s'imprime sur "
            . "la facture sous la forme exacte où l'entreprise le déclare.");
    }
}
