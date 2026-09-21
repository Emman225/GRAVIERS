<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * « GRILLE TARIFAIRE LIVRAISON » VIT SOUS « LIVREURS » (point 2, 07/09/2026).
 */
class MenuGrilleTarifaireSousLivreursTest extends TestCase
{
    use DatabaseTransactions;

    public function test_l_entree_est_sous_livreurs_et_le_menu_parent_s_ouvre_dessus(): void
    {
        $admin = User::whereIn('type_user_id', [1, 2])->where('statut', 1)->orderBy('id')->first();
        if (!$admin) {
            $this->markTestSkipped('Aucun administrateur en base.');
        }
        Auth::guard('web')->login($admin);

        $reponse = $this->get(route('show.grilleTarifaire'));
        $reponse->assertOk();
        $html = $reponse->getContent();

        // Le lien existe, une seule fois, et le bloc « Livreurs » est actif.
        $this->assertSame(1, substr_count($html, 'Grille tarifaire livraison'), 'Le lien doit exister une seule fois.');
        // Depuis le 07/09/2026 (point 1), « Livreurs » est un sous-menu de
        // « Partenaires » : les deux doivent être ouverts.
        $this->assertMatchesRegularExpression(
            '/<li class="menu-item has-submenu active">\s*<a class="menu-link" href="javascript:void\(0\)">\s*<i class="icon material-icons md-groups"><\/i>\s*<span class="text">Partenaires<\/span>/',
            $html,
            'Le menu « Partenaires » devrait être ouvert sur la grille tarifaire.'
        );
        $this->assertMatchesRegularExpression(
            '/<div class="menu-item has-submenu active">\s*<a class="menu-link" href="javascript:void\(0\)">\s*<span class="text">Livreurs<\/span>/',
            $html,
            'Le menu « Livreurs » devrait être ouvert sur la grille tarifaire.'
        );

        // Et il n'est plus dans « Demandes de livraison ».
        $bloc = substr($html, strpos($html, '<span class="text">Demandes de livraison</span>'));
        $bloc = substr($bloc, 0, strpos($bloc, '</li>'));
        $this->assertStringNotContainsString('grille', strtolower($bloc));
    }
}
