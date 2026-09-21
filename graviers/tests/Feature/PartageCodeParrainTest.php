<?php

namespace Tests\Feature;

use App\Models\Apporteur;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * PARTAGER SON CODE PARRAIN PAR WHATSAPP.
 *
 * Le code s'affichait, et c'est tout : l'apporteur devait le recopier a la main
 * dans WhatsApp, puis expliquer ou le saisir. Chaque etape recopiee est une
 * occasion de se tromper — et un filleul perdu est une commission perdue.
 *
 * Le lien partage PORTE le code, et la page d'inscription le pre-remplit : le
 * filleul n'a rien a retaper. C'est ce qui distingue un partage utile d'un
 * simple copier-coller.
 */
class PartageCodeParrainTest extends TestCase
{
    use DatabaseTransactions;

    private function unApporteur(): Apporteur
    {
        $apporteur = Apporteur::whereNotNull('user_id')->whereNotNull('code')->first();

        if (!$apporteur || !$apporteur->user) {
            $this->markTestSkipped('Aucun apporteur rattache a un compte.');
        }

        return $apporteur;
    }

    public function test_le_tableau_de_bord_propose_le_partage_whatsapp(): void
    {
        $apporteur = $this->unApporteur();

        $html = $this->actingAs($apporteur->user)
            ->get(route('apporteur.home'))
            ->assertOk()->getContent();

        $this->assertStringContainsString('wa.me/?text=', $html,
            "Le partage doit passer par le lien officiel de WhatsApp.");

        $this->assertStringContainsString('Partager sur WhatsApp', $html);
    }

    public function test_le_lien_partage_porte_le_code(): void
    {
        // Un partage qui se contente d'annoncer le code oblige le filleul a le
        // retaper : autant de chances de se tromper.
        $apporteur = $this->unApporteur();

        $html = $this->actingAs($apporteur->user)
            ->get(route('apporteur.home'))
            ->assertOk()->getContent();

        $attendu = rawurlencode(route('client.register', ['code_promo' => $apporteur->code]));

        $this->assertStringContainsString($attendu, $html,
            "Le message doit contenir le lien d'inscription portant le code.");
    }

    public function test_le_message_nomme_le_code(): void
    {
        $apporteur = $this->unApporteur();

        $html = $this->actingAs($apporteur->user)
            ->get(route('apporteur.home'))->getContent();

        $this->assertStringContainsString(rawurlencode($apporteur->code), $html);
    }

    public function test_l_inscription_pre_remplit_le_code_recu(): void
    {
        // Le coeur de la chaine : sans cette reprise, le lien ne sert a rien.
        $apporteur = $this->unApporteur();

        $this->get(route('client.register', ['code_promo' => $apporteur->code]))
            ->assertOk()
            ->assertSee('value="' . $apporteur->code . '"', false);
    }

    public function test_sans_code_dans_l_adresse_le_champ_reste_vide(): void
    {
        $html = $this->get(route('client.register'))->assertOk()->getContent();

        $this->assertStringContainsString('name="code_promo"', $html);
        $this->assertStringNotContainsString('name="code_promo"' . "\n" . '                                       value="APP-', $html);
    }

    public function test_un_client_venu_par_le_lien_est_rattache_a_son_parrain(): void
    {
        // La chaine complete : le lien porte le code, le formulaire le reprend,
        // et l'inscription rattache le filleul.
        \Illuminate\Support\Facades\Mail::fake();

        $apporteur = $this->unApporteur();

        $adresse = 'filleul.' . uniqid() . '@gmail.com';

        $this->post(route('client.registerClient'), [
            'type'      => 1,
            'prenom'    => 'Awa',
            'nom'       => 'Konan',
            'email'     => $adresse,
            'pays'      => \App\Models\Pays::value('id'),
            'ville'     => \App\Models\Ville::value('id'),
            'contact1'  => '07' . random_int(10000000, 99999999),
            'adresse'   => 'Yopougon',
            'password'  => 'MotDePasse1!',
            'password_confirmation' => 'MotDePasse1!',
            'code_promo' => $apporteur->code,
            'condition' => 'on',
        ])->assertSessionHasNoErrors();

        $user = User::where('email', $adresse)->first();
        $this->assertNotNull($user, "Le compte doit etre cree.");

        $client = \App\Models\Client::where('user_id', $user->id)->first();

        $this->assertNotNull($client);
        $this->assertEquals($apporteur->id, $client->parrain_id,
            "Le filleul doit etre rattache a l'apporteur dont il a suivi le lien.");
        $this->assertEquals($apporteur->code, $client->code_parrain);
    }
}
