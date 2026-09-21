<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuditTest extends TestCase
{
    use DatabaseTransactions;

    private function compte(int $type): ?User
    {
        return User::where('type_user_id', $type)
            ->where('statut', \Help::$STATUT_ACTIF)->orderBy('id')->first();
    }

    // ------------------------------------------------------ la trace elle-même

    public function test_une_ecriture_du_back_office_est_tracee(): void
    {
        $admin = $this->compte(\Help::$USER_ADMIN);
        if (!$admin) { $this->markTestSkipped('Aucun administrateur actif.'); }

        $avant = Audit::count();

        // Une route d'écriture quelconque du back-office.
        $this->actingAs($admin)->post(route('show.parametreUpdate'), ['tva' => 18]);

        $this->assertGreaterThan($avant, Audit::count(),
            "Une écriture du back-office doit laisser une trace.");
    }

    public function test_une_consultation_ne_laisse_aucune_trace(): void
    {
        $admin = $this->compte(\Help::$USER_ADMIN);
        if (!$admin) { $this->markTestSkipped('Aucun administrateur actif.'); }

        $avant = Audit::count();
        $this->actingAs($admin)->get('/orders-list');

        $this->assertSame($avant, Audit::count(), 'Une lecture ne doit rien journaliser.');
    }

    public function test_le_mot_de_passe_n_est_jamais_enregistre(): void
    {
        $propre = Audit::nettoyer([
            'login'                 => 'admin',
            'password'              => 'secret',
            'password_confirmation' => 'secret',
            'nouveau_mot_de_passe'  => 'secret',
            'imbrique'              => ['current_password' => 'secret', 'nom' => 'Kouassi'],
        ]);

        $json = json_encode($propre);

        $this->assertStringNotContainsString('secret', $json);
        $this->assertArrayHasKey('login', $propre);
        $this->assertSame('Kouassi', $propre['imbrique']['nom']);
    }

    public function test_l_audit_ne_fait_jamais_echouer_l_operation(): void
    {
        // Un libellé démesuré : la colonne fait 191 caractères. L'appel doit
        // rester silencieux quoi qu'il arrive.
        Audit::log(str_repeat('x', 5000), ['a' => 1]);
        $this->assertTrue(true);
    }

    // ------------------------------------------------------------ les accès

    public function test_l_ecran_est_ouvert_a_l_administrateur(): void
    {
        $admin = $this->compte(\Help::$USER_ADMIN);
        if (!$admin) { $this->markTestSkipped('Aucun administrateur actif.'); }

        URL::forceRootUrl('');

        // Depuis le 29/08/2026, le journal est l'onglet « Audit » de
        // « Paramètre » et « /audit » y renvoie. Ce qui compte reste que
        // l'administrateur l'ATTEIGNE — et qu'il y lise bien le journal.
        $reponse = $this->actingAs($admin)->followingRedirects()->get(route('show.audit.index'));

        $reponse->assertOk();
        $reponse->assertSee('id="journalAudit"', false);
    }

    public function test_l_ecran_est_refuse_au_gestionnaire(): void
    {
        $gestionnaire = $this->compte(\Help::$USER_GESTIONNAIRE);
        if (!$gestionnaire) { $this->markTestSkipped('Aucun gestionnaire actif.'); }

        URL::forceRootUrl('');
        $this->actingAs($gestionnaire)->get(route('show.audit.index'))->assertForbidden();
    }

    public function test_le_menu_est_invisible_pour_le_gestionnaire(): void
    {
        $gestionnaire = $this->compte(\Help::$USER_GESTIONNAIRE);
        if (!$gestionnaire) { $this->markTestSkipped('Aucun gestionnaire actif.'); }

        URL::forceRootUrl('');
        $reponse = $this->actingAs($gestionnaire)->get('/home');
        $reponse->assertDontSee(route('show.audit.index'), false);
    }
}
