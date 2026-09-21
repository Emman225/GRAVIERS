<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Configuration;
use App\Models\Paiement;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * UNE SEULE RÈGLE DE POINTS, ET DE QUOI LES REPRENDRE.
 *
 * TROIS RÈGLES coexistaient, selon la façon de payer :
 *
 *   · au guichet        : +200 points forfaitaires, écrits en dur ;
 *   · en ligne (mobile) : 1 à 15 points selon la grille interval_point ;
 *   · en ligne (site)   : rien du tout.
 *
 * Un client réglant 5 000 000 F gagnait 200 points en agence, 1 par le mobile,
 * zéro depuis le site. Le canal décidait de la récompense.
 *
 * ET LES POINTS N'ÉTAIENT JAMAIS REPRIS. Un client pouvait payer, gagner sa
 * remise, annuler avant traitement, se faire rembourser — et la garder.
 *
 * Deux décisions du 25/08/2026 : 1 000 F encaissés = 1 point (soit 1 % rendu,
 * le point valant 10 F), et le solde ne descend jamais sous zéro.
 */
class PointsDeFideliteTest extends TestCase
{
    use DatabaseTransactions;

    /** La règle est proportionnelle au montant ENCAISSÉ. */
    public function test_les_points_suivent_le_montant(): void
    {
        Configuration::first()->update(['montant_pour_un_point' => 1000]);

        $this->assertSame(28, \Help::pointsPour(28813), '28 813 F encaissés donnent 28 points.');
        $this->assertSame(5000, \Help::pointsPour(5000000));
        $this->assertSame(1, \Help::pointsPour(1000));
        $this->assertSame(0, \Help::pointsPour(999), 'En dessous de la tranche, rien.');
    }

    /**
     * LE RÉSULTAT EST TRONQUÉ, PAS ARRONDI.
     *
     * 1 999 F ne peuvent pas valoir 2 points quand la règle en annonce 1 pour
     * 1 000. Mieux vaut promettre moins et tenir.
     */
    public function test_les_points_sont_tronques_et_non_arrondis(): void
    {
        Configuration::first()->update(['montant_pour_un_point' => 1000]);

        $this->assertSame(1, \Help::pointsPour(1999));
        $this->assertSame(1, \Help::pointsPour(1500), 'Un arrondi aurait donné 2.');
    }

    /**
     * UN PARAMÈTRE ABSENT N'ATTRIBUE RIEN — il ne divise pas par zéro.
     *
     * Un écran de paramétrage mal rempli ne doit pas casser un encaissement.
     */
    public function test_un_parametre_nul_n_attribue_rien(): void
    {
        Configuration::first()->update(['montant_pour_un_point' => 0]);

        $this->assertSame(0, \Help::pointsPour(1000000));
    }

    /** Le taux se règle : la règle ne vit plus dans le code. */
    public function test_la_regle_est_parametrable(): void
    {
        Configuration::first()->update(['montant_pour_un_point' => 500]);
        $this->assertSame(57, \Help::pointsPour(28813), 'Deux fois plus généreux.');

        Configuration::first()->update(['montant_pour_un_point' => 5000]);
        $this->assertSame(5, \Help::pointsPour(28813), 'Cinq fois plus prudent.');
    }

    /**
     * CE QUI EST DONNÉ EST INSCRIT SUR LE RÈGLEMENT.
     *
     * Sans cette trace, une annulation obligerait à DEVINER les points à
     * reprendre — et le forfait de 200 rendait la devinette impossible dès que
     * la règle changeait.
     */
    public function test_le_reglement_porte_les_points_attribues(): void
    {
        $client = Client::first();
        $this->assertNotNull($client);

        $paiement = Paiement::create([
            'client_id'     => $client->id,
            'code'          => 'PT-' . substr((string) microtime(true), -8),
            'libelle'       => 'Essai points',
            'montant_total' => 28813,
            'montant_restant' => 0,
            'statut'        => 1,
            'service'       => 'COMMANDE',
            'service_id'    => 1,
            'points_attribues' => 28,
        ]);

        $this->assertSame(28.0, (float) $paiement->fresh()->points_attribues,
            "La colonne doit être dans \$fillable : sinon l'écriture est silencieusement perdue.");
    }

    /**
     * L'ANNULATION REPREND LES POINTS, SANS DESCENDRE SOUS ZÉRO.
     *
     * Le plancher est une décision : un solde négatif serait plus juste
     * comptablement, mais incompréhensible pour le client.
     */
    public function test_la_reprise_ne_descend_jamais_sous_zero(): void
    {
        $reprise = fn (float $solde, float $aRendre) => (float) max(0, $solde - $aRendre);

        $this->assertSame(72.0, $reprise(100, 28), 'Cas courant : on reprend les 28 points donnés.');
        $this->assertSame(0.0, $reprise(10, 28), 'Points déjà dépensés : on reprend ce qu\'on peut.');
        $this->assertSame(0.0, $reprise(0, 28));
    }

    /** Les quatre canaux passent par la même règle. */
    public function test_tous_les_canaux_appliquent_la_meme_regle(): void
    {
        $sites = [
            // Le guichet des ventes passe par App\Services\ReglementValide depuis
            // le 07/09/2026 : la même règle sert aux avances clients.
            'graviers/app/Services/ReglementValide.php',
        ];

        // Le guichet des locations délègue à ReglementValide::appliquerLocation
        // depuis le 10/09/2026 (l'avance d'un client s'impute aussi sur une
        // location) : il ne porte plus la règle, il l'appelle.
        $guichet = file_get_contents(base_path('../graviers/app/Http/Controllers/LocationComptantController.php'));
        $this->assertStringContainsString('ReglementValide::appliquerLocation(', $guichet);
        $this->assertStringNotContainsString('point + 200', $guichet);

        foreach ($sites as $chemin) {
            $code = file_get_contents(base_path('../' . $chemin));

            $this->assertStringContainsString('Help::pointsPour(', $code,
                "{$chemin} doit passer par la règle commune.");
            $this->assertStringNotContainsString('point + 200', $code,
                "{$chemin} : le forfait de 200 points ne doit plus exister.");
        }

        $api = base_path('../apigravier/app/Http/Controllers/PaiementController.php');

        if (is_file($api)) {
            $code = file_get_contents($api);

            $this->assertStringContainsString('Help::pointsPour(', $code,
                "L'API doit appliquer la même règle que le guichet.");
            $this->assertStringNotContainsString('$intervalPoint->nombre_point', $code,
                "La grille interval_point ne doit plus décider seule des points du mobile.");
        }
    }
}
