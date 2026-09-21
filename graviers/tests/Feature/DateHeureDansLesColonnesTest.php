<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * LES COLONNES DE DATES PORTENT L'HEURE, LA MINUTE ET LA SECONDE (10/09/2026).
 * Une valeur sans heure (colonne DATE) reste au jour seul.
 */
class DateHeureDansLesColonnesTest extends TestCase
{
    public function test_la_regle_est_en_un_seul_endroit(): void
    {
        $this->assertSame('10/09/2026 12:34:56', \Help::dateHeure('2026-09-10 12:34:56'));
        $this->assertSame('10/09/2026 12:34:56', \Help::dateHeure(\Carbon\Carbon::parse('2026-09-10 12:34:56')));
        $this->assertSame('10/09/2026', \Help::dateHeure('2026-09-10'), 'Une date sans heure reste au jour seul.');
        $this->assertSame('-', \Help::dateHeure(null));
        $this->assertSame('', \Help::dateHeure('', ''));
    }

    public function test_les_listes_passent_par_la_regle(): void
    {
        $vues = [
            'admin/comptabilite/tvaCollectee', 'admin/comptabilite/airsiCollectee',
            'admin/comptant/encaissements', 'admin/clientTerme/paiements',
            'admin/avances/index', 'client/listePaiement', 'client/listeFacture',
        ];
        foreach ($vues as $vue) {
            $source = file_get_contents(resource_path('views/' . $vue . '.blade.php'));
            $this->assertStringContainsString('Help::dateHeure(', $source, $vue);
            // Seuls les filtres « du / au » gardent une date sans heure.
            foreach (file(resource_path('views/' . $vue . '.blade.php')) as $ligne) {
                if (str_contains($ligne, "->format('d/m/Y')") && !str_contains($ligne, '$du') && !str_contains($ligne, '$au')) {
                    $this->fail($vue . ' garde une colonne sans heure : ' . trim($ligne));
                }
            }
        }
        // Les formats qui portaient déjà l'heure ont reçu la seconde.
        $monCompte = file_get_contents(resource_path('views/client/monCompte.blade.php'));
        $this->assertStringContainsString("format('H:i:s')", $monCompte);
        $this->assertStringNotContainsString("format('H:i')", $monCompte);
    }
}
