<?php

namespace Tests\Feature;

use App\Models\Location;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * ÉTAT DES CAUTIONS DE LOCATION.
 *
 * Une caution n'est pas un produit : c'est un dépôt que DALAKOUN détient et doit
 * rendre. Trois sorts possibles, et la comptabilité ne les traite pas pareil :
 *
 *   · RETENUE   — acquise à l'entreprise (dommage, retard). C'est un produit ;
 *   · RESTITUÉE — rendue au client. C'est une dette éteinte ;
 *   · DÉTENUE   — le matériel n'est pas encore rendu, rien n'a bougé.
 *
 * L'INVARIANT QUI TIENT TOUT : caution = retenue + restitué + détenue. Une
 * caution ne peut ni se perdre ni se dédoubler ; si l'égalité se rompt, l'état
 * ment sur de l'argent qui appartient à quelqu'un.
 *
 * Le restitué se CALCULE (caution moins retenue) et ne se lit pas :
 * `caution_restituee` n'est qu'un drapeau, vrai dès qu'une PARTIE est rendue.
 * S'y fier donnerait un état faux à la première retenue partielle.
 */
class EtatCautionLocationTest extends TestCase
{
    use DatabaseTransactions;

    /** Crée une location dont la caution connaît le sort demandé. */
    private function uneCaution(float $caution, float $retenue, bool $rendue): Location
    {
        $base = Location::first();
        $this->assertNotNull($base, 'La base de test doit comporter une location.');

        $l = $base->replicate();
        $l->numero          = 'CT' . substr((string) microtime(true), -8);
        $l->caution         = $caution;
        $l->caution_retenue = $retenue;
        $l->date_retour     = $rendue ? now()->toDateString() : null;
        $l->date_location   = now()->toDateString();
        $l->etat_location   = $rendue ? 'TERMINE' : 'EN COURS';
        $l->statut          = \Help::$STATUT_ACTIF;
        $l->save();

        return $l;
    }

    private function etat(): array
    {
        return app(\App\Http\Controllers\RecapVentesLocationsController::class)
            ->cautions(new Request())
            ->getData();
    }

    private function ligneDe(array $donnees, string $numero): ?array
    {
        foreach ($donnees['lignes'] as $ligne) {
            if ($ligne['numero'] === $numero) {
                return $ligne;
            }
        }

        return null;
    }

    /** Une retenue partielle : le reste revient au client. */
    public function test_une_retenue_partielle_laisse_le_reste_au_client(): void
    {
        $l = $this->uneCaution(100000, 25000, true);
        $ligne = $this->ligneDe($this->etat(), $l->numero);

        $this->assertNotNull($ligne, "La location doit figurer dans l'état.");
        $this->assertSame(25000.0, $ligne['retenue']);
        $this->assertSame(75000.0, $ligne['restitue'], 'Caution moins retenue.');
        $this->assertSame(0.0, $ligne['detenue'], 'Le matériel est rendu : plus rien n\'est détenu.');
    }

    /** Une caution entièrement retenue ne restitue rien. */
    public function test_une_retenue_totale_ne_restitue_rien(): void
    {
        $l = $this->uneCaution(60000, 60000, true);
        $ligne = $this->ligneDe($this->etat(), $l->numero);

        $this->assertSame(60000.0, $ligne['retenue']);
        $this->assertSame(0.0, $ligne['restitue']);
    }

    /**
     * TANT QUE LE MATÉRIEL N'EST PAS RENDU, LA CAUTION EST SEULEMENT DÉTENUE.
     *
     * La compter comme restituée gonflerait les sorties d'une somme qui n'a pas
     * bougé — et l'entreprise croirait avoir rendu un argent qu'elle a encore.
     */
    public function test_une_location_en_cours_ne_restitue_rien(): void
    {
        $l = $this->uneCaution(80000, 0, false);
        $ligne = $this->ligneDe($this->etat(), $l->numero);

        $this->assertSame(0.0, $ligne['restitue']);
        $this->assertSame(0.0, $ligne['retenue']);
        $this->assertSame(80000.0, $ligne['detenue']);
    }

    /**
     * L'INVARIANT : une caution ne se perd ni ne se dédouble.
     */
    public function test_caution_egale_retenue_plus_restitue_plus_detenue(): void
    {
        $this->uneCaution(100000, 25000, true);
        $this->uneCaution(50000, 0, true);
        $this->uneCaution(80000, 0, false);
        $this->uneCaution(60000, 60000, true);

        $e = $this->etat();

        $this->assertSame(
            round($e['totalCaution'], 2),
            round($e['totalRetenue'] + $e['totalRestitue'] + $e['totalDetenue'], 2),
            "Toute caution est soit retenue, soit rendue, soit encore détenue — jamais autre chose."
        );
    }

    /** L'entrée de menu doit exister, sinon l'écran est inaccessible. */
    public function test_le_sous_menu_existe(): void
    {
        // « Comptabilité » vit dans navEtats depuis le découpage du 07/09/2026 (point 1).
        $menu = file_get_contents(resource_path('views/layout/navbar/navEtats.blade.php'));

        $this->assertStringContainsString('État caution location', $menu);
        $this->assertStringContainsString("route('show.comptabilite.etatCautions')", $menu);
    }
}
