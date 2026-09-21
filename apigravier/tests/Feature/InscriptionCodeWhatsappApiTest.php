<?php

namespace Tests\Feature;

use App\Models\CodeReset;
use App\Models\User;
use Help;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * L'APPLICATION MOBILE CLIENTE ENVOIE AUSSI LE CODE PAR WHATSAPP.
 *
 * Même besoin que sur le site : un code de confirmation qui n'arrive pas, c'est
 * un compte qui reste inactif. Le courriel continue de partir ; WhatsApp
 * s'ajoute.
 *
 * Le RENVOI de code est traité avec un soin particulier : c'est le geste que
 * fait précisément le client dont le courriel n'est jamais arrivé. Il partait
 * en erreur 500 si le serveur de messagerie était en panne, sans jamais tenter
 * le second canal.
 */
class InscriptionCodeWhatsappApiTest extends TestCase
{
    use DatabaseTransactions;

    private function seedTypeUsers(): void
    {
        DB::table('type_user')->insertOrIgnore([
            ['id' => Help::$USER_CLIENT, 'nom' => 'Client', 'statut' => Help::$STATUT_ACTIF,
             'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function activerLeFournisseur(): void
    {
        config([
            'whatsapp.actif'     => true,
            'whatsapp.jeton'     => 'jeton-de-test',
            'whatsapp.numero_id' => '999',
            'whatsapp.url_base'  => 'https://exemple.test/v21.0',
        ]);
    }

    private function donneesInscription(array $remplace = []): array
    {
        return array_merge([
            'nom_prenoms' => 'Awa Konan',
            'email'       => 'essai_' . uniqid() . '@example.com',
            'contact'     => '0700112233',
            'password'    => 'MotDePasse1!',
            'type_client' => 1,
            'pays_id'     => DB::table('pays')->value('id'),
            'ville_id'    => DB::table('ville')->value('id'),
        ], $remplace);
    }

    public function test_l_inscription_mobile_envoie_le_code_au_numero_saisi(): void
    {
        Mail::fake();
        $this->seedTypeUsers();
        $this->activerLeFournisseur();
        Http::fake(['exemple.test/*' => Http::response(['messages' => [['id' => 'wamid.1']]], 200)]);

        $donnees = $this->donneesInscription();

        $this->postJson('/mon_gravier/inscription', $donnees);

        $client = User::where('email', $donnees['email'])->first();
        $this->assertNotNull($client, "L'inscription mobile doit créer le compte.");

        $code = CodeReset::lireSurUser($client->id, Help::$CODE_INSCRIPTION, false);
        $this->assertGreaterThan(0, $code->id, "Un code de confirmation doit être enregistré.");

        Http::assertSent(function ($requete) use ($code) {
            $corps = $requete->data();

            // Le code envoyé doit être CELUI enregistré : un code différent
            // serait rejeté à la saisie et le client resterait bloqué. Il part
            // sous sa forme affichable — complétée à quatre chiffres, comme
            // dans le courriel — sans quoi « 482 » et « 0482 » se liraient
            // comme deux codes différents.
            return $corps['to'] === '2250700112233'
                && $corps['template']['components'][0]['parameters'][0]['text']
                    === str_pad((string) $code->code, 4, '0', STR_PAD_LEFT);
        });
    }

    /** WhatsApp s'ajoute au courriel, il ne le remplace pas. */
    public function test_le_courriel_part_toujours(): void
    {
        Mail::fake();
        $this->seedTypeUsers();
        $this->activerLeFournisseur();
        Http::fake(['exemple.test/*' => Http::response([], 200)]);

        $this->postJson('/mon_gravier/inscription', $this->donneesInscription());

        Mail::assertSent(\App\Mail\CodeInscriptionMail::class);
    }

    /**
     * NON-RÉGRESSION : sans compte fournisseur — l'état de la production au
     * moment du déploiement — rien ne part et l'inscription est inchangée.
     */
    public function test_sans_fournisseur_l_inscription_est_inchangee(): void
    {
        Mail::fake();
        $this->seedTypeUsers();
        config(['whatsapp.actif' => false]);
        Http::fake();

        $donnees = $this->donneesInscription();

        $reponse = $this->postJson('/mon_gravier/inscription', $donnees);

        $this->assertNotNull(User::where('email', $donnees['email'])->first());
        $this->assertNotSame(500, $reponse->json('code'));
        Mail::assertSent(\App\Mail\CodeInscriptionMail::class);
        Http::assertNothingSent();
    }

    /** Une panne WhatsApp ne doit pas perdre une inscription déjà enregistrée. */
    public function test_une_panne_whatsapp_ne_fait_pas_echouer_l_inscription(): void
    {
        Mail::fake();
        $this->seedTypeUsers();
        $this->activerLeFournisseur();
        Http::fake(function () {
            throw new \RuntimeException('fournisseur injoignable');
        });

        $donnees = $this->donneesInscription();

        $reponse = $this->postJson('/mon_gravier/inscription', $donnees);

        $this->assertNotSame(500, $reponse->json('code'),
            "Le compte est déjà créé : une panne du second canal ne doit pas rendre une erreur.");
        $this->assertNotNull(User::where('email', $donnees['email'])->first());
    }

    // ---------------------------------------------------------------- renvoi

    /** Prépare un compte en attente de confirmation, avec son code du jour. */
    private function unCompteEnAttente(): User
    {
        $this->seedTypeUsers();

        $user = User::create([
            'nom_prenoms'  => 'Awa Konan',
            'email'        => 'attente_' . uniqid() . '@example.com',
            'contact'      => '0700112233',
            'login'        => 'attente_' . uniqid(),
            'password'     => Help::HashPassword('MotDePasse1!'),
            'type_user_id' => Help::$USER_CLIENT,
            'statut'       => Help::$STATUT_INACTIF,
        ]);

        $code = new CodeReset();
        $code->code = '4821';
        $code->email = $user->email;
        $code->user_id = $user->id;
        $code->type_code = Help::$CODE_INSCRIPTION;
        $code->expiration_date = date('Y-m-d H:i:s', strtotime('+30 minutes'));
        $code->utilise = false;
        $code->save();

        return $user;
    }

    public function test_le_renvoi_passe_aussi_par_whatsapp(): void
    {
        Mail::fake();
        $this->activerLeFournisseur();
        Http::fake(['exemple.test/*' => Http::response([], 200)]);

        $user = $this->unCompteEnAttente();

        $reponse = $this->postJson('/mon_gravier/renvoyerOtp', [
            'access' => Crypt::encryptString($user->id),
            'type'   => (string) $user->type_user_id,
            'niveau' => 1,
        ]);

        $this->assertSame(200, $reponse->json('code'));

        Http::assertSent(fn ($requete) => $requete->data()['to'] === '2250700112233'
            && $requete->data()['template']['components'][0]['parameters'][0]['text'] === '4821');
    }

    /**
     * LE CAS QUI MOTIVE TOUT LE RESTE.
     *
     * Le client redemande un code parce que le courriel n'arrive pas. Si le
     * serveur de messagerie est en panne, le renvoi rendait 500 sans jamais
     * tenter WhatsApp : le client restait définitivement bloqué.
     */
    public function test_un_serveur_de_messagerie_en_panne_n_empeche_plus_le_renvoi(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP injoignable'));
        $this->activerLeFournisseur();
        Http::fake(['exemple.test/*' => Http::response([], 200)]);

        $user = $this->unCompteEnAttente();

        $reponse = $this->postJson('/mon_gravier/renvoyerOtp', [
            'access' => Crypt::encryptString($user->id),
            'type'   => (string) $user->type_user_id,
            'niveau' => 1,
        ]);

        $this->assertSame(200, $reponse->json('code'),
            "Le code est parti par WhatsApp : le renvoi a réussi.");
        $this->assertStringContainsString('WhatsApp', $reponse->json('message'));
    }

    /** Non-régression : sans fournisseur, le renvoi se comporte comme avant. */
    public function test_sans_fournisseur_le_renvoi_est_inchange(): void
    {
        Mail::fake();
        config(['whatsapp.actif' => false]);
        Http::fake();

        $user = $this->unCompteEnAttente();

        $reponse = $this->postJson('/mon_gravier/renvoyerOtp', [
            'access' => Crypt::encryptString($user->id),
            'type'   => (string) $user->type_user_id,
            'niveau' => 1,
        ]);

        $this->assertSame(200, $reponse->json('code'));
        $this->assertSame('Le code a bien été renvoyé sur votre mail', $reponse->json('message'));
        Mail::assertSent(\App\Mail\CodeInscriptionMail::class);
        Http::assertNothingSent();
    }
}
