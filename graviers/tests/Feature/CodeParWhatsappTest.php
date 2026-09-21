<?php

namespace Tests\Feature;

use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * LE CODE DE CONFIRMATION PART AUSSI PAR WHATSAPP.
 *
 * Ce que ces tests protègent :
 *
 *   · le numéro saisi « à l'ivoirienne » arrive au format attendu par WhatsApp ;
 *   · tant qu'aucun compte fournisseur n'est configuré, RIEN ne part et rien ne
 *     change — c'est ce qui permet de poser cette version en production sans
 *     attendre l'ouverture du compte ;
 *   · un fournisseur en panne ne fait jamais échouer une inscription.
 */
class CodeParWhatsappTest extends TestCase
{
    /** Configure un compte fournisseur fictif, pour les tests d'envoi. */
    private function activerLeFournisseur(): void
    {
        config([
            'whatsapp.actif'     => true,
            'whatsapp.jeton'     => 'jeton-de-test',
            'whatsapp.numero_id' => '999',
            'whatsapp.url_base'  => 'https://exemple.test/v21.0',
            'whatsapp.modele'    => 'code_confirmation',
            'whatsapp.langue'    => 'fr',
        ]);
    }

    /**
     * Le client saisit son numéro comme il le dit : « 07 00 11 22 33 ».
     * WhatsApp, lui, veut « 2250700112233 ».
     */
    public function test_le_numero_local_recoit_l_indicatif_ivoirien(): void
    {
        $this->assertSame('2250700112233', WhatsAppService::normaliserNumero('0700112233'));
        $this->assertSame('2250700112233', WhatsAppService::normaliserNumero('07 00 11 22 33'));
        $this->assertSame('2250700112233', WhatsAppService::normaliserNumero('07-00-11-22-33'));
    }

    /**
     * Le numéro déjà international ne doit pas être préfixé une seconde fois :
     * « 2252250700112233 » n'existe pas.
     */
    public function test_le_numero_deja_international_n_est_pas_prefixe_deux_fois(): void
    {
        $this->assertSame('2250700112233', WhatsAppService::normaliserNumero('+225 07 00 11 22 33'));
        $this->assertSame('2250700112233', WhatsAppService::normaliserNumero('00225 0700112233'));
        $this->assertSame('2250700112233', WhatsAppService::normaliserNumero('2250700112233'));
    }

    /**
     * Un numéro incomplet n'est pas « rattrapé » : mieux vaut ne rien envoyer
     * que d'écrire le code de quelqu'un à un inconnu.
     */
    public function test_un_numero_inexploitable_ne_donne_pas_de_destinataire(): void
    {
        $this->assertNull(WhatsAppService::normaliserNumero(null));
        $this->assertNull(WhatsAppService::normaliserNumero(''));
        $this->assertNull(WhatsAppService::normaliserNumero('néant'));
        $this->assertNull(WhatsAppService::normaliserNumero('07 00 11'));
    }

    /**
     * TANT QUE LE COMPTE FOURNISSEUR N'EST PAS OUVERT, RIEN NE PART.
     *
     * C'est le test qui autorise le déploiement immédiat : sans jeton, aucune
     * requête n'est émise et le comportement actuel est intact.
     */
    public function test_sans_compte_fournisseur_aucun_appel_n_est_emis(): void
    {
        config(['whatsapp.actif' => false]);
        Http::fake();

        $this->assertFalse(WhatsAppService::estActif());
        $this->assertFalse(WhatsAppService::envoyerCode('0700112233', '1234'));

        Http::assertNothingSent();
    }

    /** Le drapeau seul ne suffit pas : sans jeton ni numéro, on n'appelle personne. */
    public function test_le_drapeau_actif_sans_jeton_n_envoie_rien(): void
    {
        config(['whatsapp.actif' => true, 'whatsapp.jeton' => null, 'whatsapp.numero_id' => null]);
        Http::fake();

        $this->assertFalse(WhatsAppService::envoyerCode('0700112233', '1234'));
        Http::assertNothingSent();
    }

    /** Le code part au bon numéro, dans le modèle approuvé. */
    public function test_le_code_part_dans_le_modele_approuve(): void
    {
        $this->activerLeFournisseur();
        Http::fake(['exemple.test/*' => Http::response(['messages' => [['id' => 'wamid.1']]], 200)]);

        $this->assertTrue(WhatsAppService::envoyerCode('07 00 11 22 33', '4821'));

        Http::assertSent(function ($requete) {
            $corps = $requete->data();

            return $requete->url() === 'https://exemple.test/v21.0/999/messages'
                && $corps['to'] === '2250700112233'
                && $corps['type'] === 'template'
                && $corps['template']['name'] === 'code_confirmation'
                && $corps['template']['language']['code'] === 'fr'
                && $corps['template']['components'][0]['parameters'][0]['text'] === '4821';
        });
    }

    /**
     * UN FOURNISSEUR EN PANNE NE DOIT JAMAIS FAIRE ÉCHOUER UNE INSCRIPTION.
     *
     * Le compte est déjà enregistré et le courriel déjà parti quand l'envoi
     * WhatsApp est tenté : une exception ici perdrait le client pour rien.
     */
    public function test_une_panne_du_fournisseur_ne_leve_jamais(): void
    {
        $this->activerLeFournisseur();
        Http::fake(function () {
            throw new \RuntimeException('connexion refusée');
        });

        $this->assertFalse(WhatsAppService::envoyerCode('0700112233', '1234'));
    }

    /** Un refus du fournisseur se journalise, il ne se propage pas. */
    public function test_un_refus_du_fournisseur_rend_simplement_faux(): void
    {
        $this->activerLeFournisseur();
        Http::fake(['exemple.test/*' => Http::response(['error' => ['message' => 'modèle inconnu']], 400)]);

        $this->assertFalse(WhatsAppService::envoyerCode('0700112233', '1234'));
    }

    /** Un numéro inexploitable n'atteint jamais le fournisseur. */
    public function test_un_numero_inexploitable_n_appelle_pas_le_fournisseur(): void
    {
        $this->activerLeFournisseur();
        Http::fake();

        $this->assertFalse(WhatsAppService::envoyerCode('07 00', '1234'));
        Http::assertNothingSent();
    }
}
