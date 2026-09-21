<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * POINT 1 (validé le 07/09/2026) : l'ordre du menu latéral du back-office et
 * de la barre du compte client. Le test lit la page et vérifie que les
 * entrées apparaissent dans l'ordre convenu, et qu'aucune n'a disparu.
 */
class MenuReorganiseTest extends TestCase
{
    use DatabaseTransactions;

    /** Vérifie que chaque libellé apparaît après le précédent dans le HTML. */
    private function assertDansLOrdre(string $html, array $libelles): void
    {
        $position = -1;
        foreach ($libelles as $libelle) {
            $p = strpos($html, $libelle, $position + 1);
            $this->assertNotFalse($p, "« {$libelle} » est absent du menu (ou avant le précédent).");
            $this->assertGreaterThan($position, $p, "« {$libelle} » n'est pas à sa place.");
            $position = $p;
        }
    }

    public function test_l_ordre_du_menu_de_l_administrateur(): void
    {
        $admin = User::where('type_user_id', 2)->where('statut', 1)->first();
        if (!$admin) {
            $this->markTestSkipped('Aucun administrateur.');
        }
        Auth::guard('web')->login($admin);
        $html = $this->get('/gestionnaire/home')->assertOk()->getContent();
        $menu = substr($html, strpos($html, 'menu-aside'));

        $this->assertDansLOrdre($menu, [
            'Tableau de bord', 'Commandes', 'Livraisons', 'Locations', 'Demandes de livraison',
            '>Client<', 'Avances clients', 'Partenaires', 'Fournisseurs', 'Livreurs', 'Grille tarifaire livraison',
            "Apporteurs d'affaires", "Factures & Bons d'enlèvement", 'Demandes de paiement',
            'Etat des paiements reçus', 'Comptabilité', '>Etat<', 'Catalogue', 'Produits', 'Code promo',
            'Modération des commentaires', 'Configuration', 'Divers', 'Les grands livres', 'Paramètre',
        ]);
        // « Dettes » de l'administrateur vit sous « Etat », pas à la racine.
        $this->assertSame(1, substr_count($menu, '<span class="text">Dettes</span>'));
    }

    public function test_le_gestionnaire_garde_ses_ecrans_sans_ceux_de_l_administrateur(): void
    {
        $gestionnaire = User::where('type_user_id', 3)->where('statut', 1)->first();
        if (!$gestionnaire) {
            $this->markTestSkipped('Aucun gestionnaire.');
        }
        Auth::guard('web')->login($gestionnaire);
        $html = $this->get('/gestionnaire/home')->assertOk()->getContent();
        $menu = substr($html, strpos($html, 'menu-aside'));

        $this->assertDansLOrdre($menu, [
            'Tableau de bord', 'Commandes', 'Partenaires', 'Demandes de paiement', 'Dettes',
            'Etat des paiements reçus', 'Catalogue', 'Divers', 'Agences', 'Paramètre',
        ]);
        $this->assertStringNotContainsString('<span class="text">Configuration</span>', $menu);
        $this->assertStringNotContainsString('<span class="text">Comptabilité</span>', $menu);
        $this->assertStringNotContainsString('Avances clients', $menu);
    }

    public function test_l_ordre_de_la_barre_du_compte_client(): void
    {
        $client = Client::where('statut', 1)->whereHas('user', fn ($q) => $q->where('statut', 1))->first();
        if (!$client) {
            $this->markTestSkipped('Aucun client actif.');
        }
        Auth::guard('web')->login($client->user);
        $html = $this->get('/mon-compte')->assertOk()->getContent();
        $barre = substr($html, strpos($html, 'dashboard-menu'));

        $this->assertDansLOrdre($barre, [
            'Votre tableau de bord', 'Mes commandes', 'Mes devis', 'Demande de livraisons',
            'Demande de location', 'Mes paiements', 'Retour de produits', 'Service après-vente',
            'Mes tickets SAV', 'Détail du compte',
        ]);
        // Pour une entreprise, « Devenir un client à terme » précède « Détail du compte » (08/09/2026).
        if ($client->type_client === 'ENTREPRISE') {
            $this->assertDansLOrdre($barre, ['Mes tickets SAV', 'Devenir un client à terme', 'Détail du compte']);
        }
    }
}
