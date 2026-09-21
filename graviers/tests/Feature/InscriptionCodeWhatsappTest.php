<?php

namespace Tests\Feature;

use App\Models\Pays;
use App\Models\User;
use App\Models\Ville;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * L'INSCRIPTION SUR LE SITE ENVOIE LE CODE PAR LES DEUX CANAUX.
 *
 * Le code ne partait que par courriel. Beaucoup de clients n'ont pas d'adresse
 * consultée régulièrement, et le message part parfois en indésirables : un code
 * jamais reçu, c'est un compte jamais activé.
 *
 * Ce que ces tests tiennent :
 *
 *   · le code part au NUMÉRO SAISI À L'INSCRIPTION, pas à un autre ;
 *   · le courriel continue de partir — WhatsApp s'ajoute, il ne remplace pas ;
 *   · une panne WhatsApp ne fait pas échouer l'inscription.
 */
class InscriptionCodeWhatsappTest extends TestCase
{
    use DatabaseTransactions;

    private function unParticulier(array $remplace = []): array
    {
        return array_merge([
            'type'      => 1,
            'prenom'    => 'Awa',
            'nom'       => 'Konan',
            'email'     => 'essai-' . uniqid() . '@example.com',
            'pays'      => Pays::value('id'),
            'ville'     => Ville::value('id'),
            'contact1'  => '0700112233',
            'adresse'   => 'Yopougon',
            'password'  => 'MotDePasse1!',
            'password_confirmation' => 'MotDePasse1!',
            'condition' => 'on',
        ], $remplace);
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

    public function test_l_inscription_envoie_le_code_au_numero_saisi(): void
    {
        Mail::fake();
        $this->activerLeFournisseur();
        Http::fake(['exemple.test/*' => Http::response(['messages' => [['id' => 'wamid.1']]], 200)]);

        $donnees = $this->unParticulier();

        $this->post(route('client.registerClient'), $donnees)->assertSessionHasNoErrors();

        $client = User::where('email', $donnees['email'])->first();
        $this->assertNotNull($client, "L'inscription doit aboutir.");

        Http::assertSent(function ($requete) use ($client) {
            $corps = $requete->data();

            // Le numéro saisi « 0700112233 » doit arriver au format WhatsApp,
            // et le code envoyé doit être CELUI enregistré sur le compte —
            // sinon le client saisirait un code que le site ne reconnaît pas.
            return $corps['to'] === '2250700112233'
                && $corps['template']['components'][0]['parameters'][0]['text'] === (string) $client->token;
        });
    }

    /** WhatsApp S'AJOUTE au courriel, il ne le remplace pas. */
    public function test_le_courriel_part_toujours(): void
    {
        Mail::fake();
        $this->activerLeFournisseur();
        Http::fake(['exemple.test/*' => Http::response([], 200)]);

        $this->post(route('client.registerClient'), $this->unParticulier())
            ->assertSessionHasNoErrors();

        Mail::assertSent(\App\Mail\confirmClient::class);
    }

    /**
     * UNE PANNE WHATSAPP NE DOIT PAS PERDRE UNE INSCRIPTION.
     *
     * Le compte est déjà enregistré et le courriel déjà parti quand l'envoi est
     * tenté : une exception ici renverrait une erreur 500 à un client dont le
     * compte existe pourtant.
     */
    public function test_une_panne_whatsapp_ne_fait_pas_echouer_l_inscription(): void
    {
        Mail::fake();
        $this->activerLeFournisseur();
        Http::fake(function () {
            throw new \RuntimeException('fournisseur injoignable');
        });

        $donnees = $this->unParticulier();
        $reponse = $this->post(route('client.registerClient'), $donnees);

        $reponse->assertSessionHasNoErrors();
        $this->assertNotSame(500, $reponse->status());
        $this->assertNotNull(User::where('email', $donnees['email'])->first(),
            "Le compte doit exister même si WhatsApp est en panne.");
    }

    /**
     * NON-RÉGRESSION : sans compte fournisseur configuré — c'est-à-dire dans
     * l'état où la production sera au moment du déploiement — l'inscription se
     * déroule exactement comme avant et aucune requête n'est émise.
     */
    public function test_sans_fournisseur_l_inscription_est_inchangee(): void
    {
        Mail::fake();
        config(['whatsapp.actif' => false]);
        Http::fake();

        $donnees = $this->unParticulier();

        $this->post(route('client.registerClient'), $donnees)->assertSessionHasNoErrors();

        $this->assertNotNull(User::where('email', $donnees['email'])->first());
        Mail::assertSent(\App\Mail\confirmClient::class);
        Http::assertNothingSent();
    }
}
