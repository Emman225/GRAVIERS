<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * L'ÉTAT DES CAUTIONS ÉTAIT STRUCTURELLEMENT VIDE.
 *
 * Constaté en production le 28/08/2026 : « Aucune caution sur cette période »,
 * sur toutes les périodes.
 *
 * La cause tient en une ligne. Les deux écrans filtraient les locations sur
 * `where('statut', Help::$STATUT_ACTIF)` — c'est-à-dire `statut = 1`. Or, pour
 * une location, `statut` ne dit PAS si la ligne est active : il porte l'état du
 * RÈGLEMENT, et le code le documente lui-même dans `supprimerLocation` —
 * « 1 = aucun paiement, 2 = acompte, 3 = soldé ».
 *
 * Le filtre ne retenait donc que les locations JAMAIS PAYÉES. Or une location
 * ne se valide qu'une fois soldée, et c'est la validation qui enregistre la
 * caution : aucune location porteuse d'une caution ne pouvait passer ce
 * filtre. L'écran ne pouvait qu'être vide.
 *
 * Le même défaut frappait le « Récapitulatif des locations » : le chiffre
 * d'affaires et la marge de DALAKOUN s'y calculaient sur les seules affaires
 * qui n'ont rien rapporté.
 */
class EtatCautionNonVideTest extends TestCase
{
    use DatabaseTransactions;

    private function unAdmin(): User
    {
        $admin = User::whereIn('type_user_id', [1, 2])
            ->where('statut', \Help::$STATUT_ACTIF)
            ->first();

        if (!$admin) {
            $this->markTestSkipped('Aucun administrateur actif dans la base de travail.');
        }

        return $admin;
    }

    /**
     * Une location soldée, porteuse d'une caution — le cas RÉEL, celui qui
     * n'apparaissait jamais.
     */
    private function uneLocationAvecCaution(): Location
    {
        $location = Location::orderByDesc('id')->first();

        if (!$location) {
            $this->markTestSkipped('Aucune location dans la base de travail.');
        }

        $location->update([
            'statut'        => 3,               // soldée : le seul état qui permet la validation
            'etat_location' => 'EN COURS',
            'caution'       => 50000,
            'date_location' => now()->toDateString(),
            'date_retour'   => null,            // matériel non rendu : caution détenue
        ]);

        return $location->fresh();
    }

    /** LA CAUTION D'UNE LOCATION SOLDÉE DOIT APPARAÎTRE. */
    public function test_une_location_soldee_apparait_dans_l_etat_des_cautions(): void
    {
        $location = $this->uneLocationAvecCaution();

        URL::forceRootUrl('');
        $ecran = $this->actingAs($this->unAdmin())
            ->get('/comptabilite/etat-cautions?du=' . now()->startOfMonth()->toDateString()
                . '&au=' . now()->endOfMonth()->toDateString());

        $ecran->assertOk();

        $ecran->assertDontSee('Aucune caution sur cette période', false);

        $ecran->assertSee($location->numero, false);
    }

    /** LE RÉCAPITULATIF DES LOCATIONS SOUFFRAIT DU MÊME FILTRE. */
    public function test_une_location_soldee_apparait_dans_le_recapitulatif(): void
    {
        $location = $this->uneLocationAvecCaution();

        URL::forceRootUrl('');
        $ecran = $this->actingAs($this->unAdmin())
            ->get('/comptabilite/recap-locations?du=' . now()->startOfMonth()->toDateString()
                . '&au=' . now()->endOfMonth()->toDateString());

        $ecran->assertOk();
        $ecran->assertSee($location->numero, false);
    }

    /** UNE LOCATION ANNULÉE, ELLE, RESTE ÉCARTÉE. */
    public function test_une_location_annulee_est_ecartee(): void
    {
        $location = $this->uneLocationAvecCaution();
        $location->update(['etat_location' => 'ANNULEE']);

        URL::forceRootUrl('');
        $ecran = $this->actingAs($this->unAdmin())
            ->get('/comptabilite/etat-cautions?du=' . now()->startOfMonth()->toDateString()
                . '&au=' . now()->endOfMonth()->toDateString());

        $ecran->assertOk();
        $ecran->assertDontSee($location->numero, false);
    }

    /**
     * LE FILTRE FAUTIF NE DOIT PAS REVENIR.
     *
     * `statut` reste employé à bon droit sur les bons d'enlèvement, où il EST
     * un indicateur d'activité. L'essai ne vise donc que les requêtes portant
     * sur les locations.
     */
    public function test_le_filtre_sur_le_statut_de_la_location_ne_revient_pas(): void
    {
        $source = file_get_contents(
            app_path('Http/Controllers/RecapVentesLocationsController.php')
        );

        // Commentaires retirés : sans cela l'essai trouve les mots qu'il cherche
        // dans l'explication du correctif et passe au vert à tort.
        $code = preg_replace('!/\*.*?\*/!s', '', $source);
        $code = preg_replace('!^\s*//.*$!m', '', $code);

        foreach (['cautions', 'locations'] as $methode) {
            $debut = strpos($code, 'function ' . $methode . '(');
            $fin   = strpos($code, 'public function', $debut + 10);
            $bloc  = substr($code, $debut, ($fin === false ? strlen($code) : $fin) - $debut);

            $this->assertStringNotContainsString(
                "->where('statut', \\Help::\$STATUT_ACTIF)",
                $bloc,
                "La méthode $methode() filtre de nouveau les locations sur `statut` : "
                . "elle ne montrerait que les locations JAMAIS payées, et l'écran "
                . 'redeviendrait vide.'
            );

            $this->assertStringContainsString(
                "->where('etat_location', '<>', 'ANNULEE')",
                $bloc,
                "La méthode $methode() n'écarte plus les locations annulées."
            );
        }
    }
}
