<?php

namespace Tests\Feature;

use App\Mail\MailAccesUsers;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * /register-admin (09/09/2026) : l'identifiant de connexion est GÉNÉRÉ, comme le
 * mot de passe, et envoyé au nouvel administrateur par courriel. Le formulaire
 * ne le demande plus ; deux homonymes reçoivent deux identifiants différents.
 */
class IdentifiantAdminGenereTest extends TestCase
{
    use DatabaseTransactions;

    private function creer(array $champs = [])
    {
        return $this->post(route('show.registerAdminn'), array_merge([
            'nom'       => 'Kouassi',
            'prenom'    => 'Ange Émilie',
            'contact'   => '0700000000',
            'indicatif' => '+225',
            'email'     => 'ange.kouassi.' . uniqid() . '@example.test',
        ], $champs));
    }

    public function test_le_formulaire_ne_demande_plus_l_identifiant(): void
    {
        $admin = User::whereIn('type_user_id', [1, 2])->where('statut', 1)->first();
        if (!$admin) {
            $this->markTestSkipped('Aucun administrateur.');
        }
        Auth::guard('web')->login($admin);
        $html = $this->get('/register-admin')->assertOk()->getContent();
        $this->assertStringNotContainsString('name="login"', $html, 'Le champ identifiant ne doit plus être saisi.');
        $this->assertStringContainsString('généré automatiquement', $html);
    }

    public function test_l_identifiant_est_genere_et_envoye_par_courriel(): void
    {
        $admin = User::whereIn('type_user_id', [1, 2])->where('statut', 1)->first();
        if (!$admin) {
            $this->markTestSkipped('Aucun administrateur.');
        }
        Auth::guard('web')->login($admin);
        Mail::fake();

        $email = 'ange.kouassi.' . uniqid() . '@example.test';
        $this->creer(['email' => $email])->assertRedirect();

        $nouveau = User::where('email', $email)->first();
        $this->assertNotNull($nouveau, "L'administrateur n'a pas été créé sans identifiant saisi.");
        $this->assertNotEmpty($nouveau->login);
        $this->assertStringStartsWith('ange-emilie-kouassi', $nouveau->login, 'L\'identifiant se construit sur le prénom et le nom.');
        $this->assertSame('Ange Émilie Kouassi', $nouveau->nom_prenoms);

        $login = $nouveau->login;
        Mail::assertSent(MailAccesUsers::class, fn (MailAccesUsers $m) => $m->login === $login && $m->email === $email && $m->password !== '');

        // Un homonyme reçoit un identifiant différent.
        $email2 = 'ange.kouassi.' . uniqid() . '@example.test';
        $this->creer(['email' => $email2])->assertRedirect();
        $second = User::where('email', $email2)->first();
        $this->assertNotNull($second);
        $this->assertNotSame($nouveau->login, $second->login, 'Deux homonymes ne peuvent pas partager un identifiant.');
        $this->assertStringStartsWith('ange-emilie-kouassi', $second->login);
    }
}
