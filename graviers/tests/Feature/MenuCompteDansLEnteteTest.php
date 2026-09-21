<?php

namespace Tests\Feature;

use App\Models\Client;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/** Menu au survol de l'icône « Mon compte » de l'en-tête du site (lot 107, 17/09/2026). */
class MenuCompteDansLEnteteTest extends TestCase
{
    use DatabaseTransactions;

    public function test_le_client_connecte_a_profil_client_a_terme_et_deconnexion_au_survol(): void
    {
        $client = Client::whereNotNull('user_id')->get()->first(fn ($c) => $c->user);
        if (!$client) {
            $this->markTestSkipped('Aucun client avec compte.');
        }
        Auth::guard('web')->login($client->user);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('account-dropdown menu-compte-entete', $html, "Le menu de l'icône Mon compte est dans l'en-tête.");
        $this->assertStringContainsString(route('client.monCompte') . '?onglet=account-detail', $html, 'Profil mène aux Détails du compte.');
        $this->assertStringContainsString('>Profil<', $html);
        $this->assertStringContainsString('>Mon compte</a>', $html, "Entrée « Mon compte » au-dessus de Profil.");
        $this->assertTrue(strpos($html, '>Mon compte</a>') < strpos($html, '>Profil<'), "« Mon compte » précède « Profil »");
        $this->assertStringContainsString(route('client.demandeClientATermePage'), $html, 'Devenir un client à terme.');
        $this->assertStringContainsString('action="' . route('show.logout') . '"', $html, 'Déconnexion par le formulaire de sortie.');
        $this->assertStringContainsString('name="_method" value="delete"', $html);
        $this->assertStringContainsString('>Déconnexion</button>', $html);

        // Mon compte ouvre l'onglet visé par l'ancre (script) et par ?onglet= (rendu serveur).
        $compte = $this->get('/mon-compte')->assertOk()->getContent();
        $this->assertStringContainsString("getElementById(cible + '-tab')", $compte);
        $this->assertStringContainsString('class="nav-link active" id="dashboard-tab"', $compte, 'Par défaut : tableau de bord.');
        $this->assertStringContainsString('class="tab-pane fade active show" id="dashboard"', $compte);
        $profil = $this->get('/mon-compte?onglet=account-detail')->assertOk()->getContent();
        $this->assertStringContainsString('class="nav-link active" id="account-detail-tab"', $profil, 'Profil : Détails du compte actif.');
        $this->assertStringContainsString('class="tab-pane fade active show" id="account-detail"', $profil);
        $this->assertStringContainsString('class="nav-link " id="dashboard-tab"', $profil, 'Le tableau de bord ne l\'est plus.');
        $this->assertStringContainsString('class="tab-pane fade " id="dashboard"', $profil);
        $this->assertStringNotContainsString("getElementById('info-tab')", $compte, "L'avatar ne vise plus un onglet inexistant.");
    }

    public function test_un_visiteur_n_a_pas_ce_menu(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        // Le nom de classe figure dans la feuille de style en ligne ; c'est le balisage qui doit manquer.
        $this->assertStringNotContainsString('account-dropdown menu-compte-entete', $html);
        $this->assertStringNotContainsString('>Profil<', $html);
    }
}
