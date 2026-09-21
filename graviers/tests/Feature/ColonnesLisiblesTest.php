<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * COLONNES LISIBLES (10/09/2026) : dans les DataTables, une valeur ne se coupe
 * plus parce que le titre de sa colonne est court ; le texte long garde son
 * retour à la ligne dans une colonne large. Back-office et espace client.
 */
class ColonnesLisiblesTest extends TestCase
{
    public function test_les_feuilles_de_style_et_les_scripts_portent_la_regle(): void
    {
        foreach (['backend/assets/css/premium-admin.css', 'frontend/assets/css/premium-client-account.css'] as $css) {
            $source = file_get_contents(public_path($css));
            $this->assertStringContainsString('COLONNES LISIBLES', $source, $css);
            $this->assertMatchesRegularExpression('/\.table tbody td,\s*\.table tfoot td \{\s*white-space: nowrap;/', $source, $css);
            $this->assertStringContainsString('td.td-texte-long', $source, $css);
        }
        foreach (['backend/assets/js/delete-confirm.js', 'frontend/assets/js/delete-confirm.js'] as $js) {
            $source = file_get_contents(public_path($js));
            $this->assertStringContainsString('marquerCellulesLongues', $source, $js);
            $this->assertStringContainsString("on('draw.dt'", $source, $js);
            $this->assertStringContainsString('autoWidth: false', $source, $js);
        }
    }

    public function test_les_pages_chargent_les_versions_a_jour(): void
    {
        $bo = file_get_contents(resource_path('views/layout/head.blade.php'));
        $this->assertStringContainsString('premium-admin.css?v=1.8', $bo);
        $this->assertStringContainsString('delete-confirm.js?v=4.3', $bo);
        $client = file_get_contents(resource_path('views/client/head.blade.php'));
        $this->assertStringContainsString('premium-client-account.css?v=1.2', $client);
        $this->assertStringContainsString('delete-confirm.js?v=2.4', $client);
    }
}
