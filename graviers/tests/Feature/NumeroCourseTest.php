<?php

namespace Tests\Feature;

use App\Models\Livraison;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * UNE COURSE SE NUMÉROTE COMME UNE VENTE.
 *
 * Le numéro d'une livraison était un `uniqid()` : treize caractères
 * hexadécimaux, du genre « 6a99c1826bdb0 ». Deux personnes le lisent :
 *
 *   - le CLIENT, dans « Mes livraisons » ;
 *   - le LIVREUR, qui doit le SAISIR à l'identique pour clôturer sa course —
 *     alors que sa propre liste le tronque à « 6a99c1826b… ».
 *
 * Dicter treize caractères hexadécimaux sur un chantier, avec les confusions
 * entre 0 et O, 1 et l, n'est pas tenable. Six chiffres le sont.
 *
 * NOTE SUR L'ÉCRITURE DE CET ESSAI
 *
 * Un premier garde-fou cherchait, en amont de chaque `'numero' => uniqid()`,
 * le `::create([` le plus proche pour décider s'il s'agissait d'une livraison.
 * Il a laissé passer le sixième cas, où le tableau est construit à part puis
 * passé à `Livraison::create($livraison)` — et il annonçait « 0 restant ».
 * Un contrôle qui partage le défaut qu'il traque ne prouve rien. Celui-ci
 * n'interprète donc AUCUN contexte : il refuse `uniqid()` comme numéro,
 * partout, sans exception à deviner.
 */
class NumeroCourseTest extends TestCase
{
    use DatabaseTransactions;

    /** Le format d'un numéro d'affaire : six chiffres, rien d'autre. */
    private const FORMAT = '/^\d{6}$/';

    /** Les fichiers qui créent des courses. */
    private const CONTROLEURS = [
        'Http/Controllers/OrdersController.php',
        'Http/Controllers/UserController.php',
    ];

    public function test_aucun_numero_de_course_ne_vient_de_uniqid(): void
    {
        foreach (self::CONTROLEURS as $rel) {
            $source = file_get_contents(app_path($rel));

            // Les parenthèses peuvent porter un argument : un `uniqid(5)`
            // se cachait derrière une expression qui exigeait « () » vides.
            $this->assertDoesNotMatchRegularExpression(
                "/'numero'\s*=>\s*uniqid\(/", $source,
                $rel . " numérote encore une affaire avec uniqid(). "
                . "Le client et le livreur lisent ce numéro : il doit avoir "
                . "six chiffres, comme une vente.");
        }
    }

    public function test_les_controleurs_passent_par_le_generateur_commun(): void
    {
        foreach (self::CONTROLEURS as $rel) {
            $source = file_get_contents(app_path($rel));

            // Autant d'appels au générateur que de créations de livraison.
            $creations = preg_match_all('/Livraison::create\(/', $source);
            $generations = preg_match_all(
                "/genererNumeroUnique\('livraison'\)/", $source);

            $this->assertGreaterThanOrEqual($creations, $generations,
                $rel . " crée {$creations} livraison(s) mais n'appelle le "
                . "générateur que {$generations} fois : au moins une course "
                . "reçoit encore un numéro d'une autre provenance.");
        }
    }

    /** Le générateur rend bien six chiffres, et un numéro libre. */
    public function test_le_generateur_rend_six_chiffres_inedits(): void
    {
        $numeros = [];

        for ($i = 0; $i < 25; $i++) {
            $numero = \Help::genererNumeroUnique('livraison');

            $this->assertMatchesRegularExpression(self::FORMAT, $numero,
                'Le générateur doit rendre six chiffres, pas « ' . $numero . ' ».');

            $this->assertFalse(
                Livraison::where('numero', $numero)->exists(),
                'Le générateur a rendu un numéro déjà pris : ' . $numero);

            $numeros[] = $numero;
        }

        // Un tirage aléatoire peut se répéter ; vingt-cinq fois de suite, non.
        $this->assertGreaterThan(20, count(array_unique($numeros)),
            'Le générateur se répète beaucoup trop.');
    }

    /**
     * LE MÊME FORMAT QUE LES AUTRES AFFAIRES, pas seulement « six chiffres ».
     *
     * On compare à une commande réellement en base : si le format des ventes
     * change un jour, cet essai le dira au lieu de figer une règle en double.
     */
    public function test_le_format_est_celui_des_ventes(): void
    {
        $commande = \App\Models\Commande::whereNotNull('numero')
            ->latest('id')->first();

        if (!$commande) {
            $this->markTestSkipped('Aucune commande en base pour comparer.');
        }

        $this->assertSame(
            strlen((string) $commande->numero),
            strlen(\Help::genererNumeroUnique('livraison')),
            'Une course et une vente doivent porter des numéros de même '
            . 'longueur : le client les lit côte à côte.');
    }
}
