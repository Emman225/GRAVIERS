<?php

namespace Tests\Feature;

use App\Models\AvanceClient;
use App\Models\Client;
use App\Models\Commande;
use App\Models\Paiement;
use App\Models\User;
use App\Services\Avances;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * L'AVANCE IMPUTÉE DEPUIS L'APPLICATION ENVOIE LE REÇU AU CLIENT (11/09/2026),
 * comme après un paiement en ligne : le site envoie le PDF du guichet (jeton
 * interne) ; sans site joignable, l'API envoie le reçu en texte. Et jamais
 * un envoi qui échoue n'empêche l'imputation.
 */
class RecuDeLAvanceImputeeMobileTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array{Commande, Client, User} */
    private function jeu(): array
    {
        $admin = User::whereIn('type_user_id', [1, 2])->where('statut', 1)->first();
        $commande = null;
        $client = null;
        foreach (Commande::where('statut', '<>', 0)->orderByDesc('id')->limit(300)->get() as $c) {
            $cl = Client::find($c->client_id);
            $u  = $cl && $cl->user_id ? User::find($cl->user_id) : null;
            if ($u && trim((string) $u->email) !== '' && $c->montantRestantDu() >= 1000) {
                $commande = $c;
                $client = $cl;
                break;
            }
        }
        if (!$admin || !$commande || !$client) {
            $this->markTestSkipped('Jeu de données insuffisant (administrateur, commande d\'un client joignable avec un reste dû).');
        }
        AvanceClient::create([
            'client_id' => $client->id, 'montant' => 1000, 'montant_consomme' => 0,
            'statut' => AvanceClient::DISPONIBLE, 'numero_recu' => 'RA-T-' . random_int(100, 999),
            'agence_id' => $admin->agence_id, 'caissier_id' => $admin->id,
            'user_valide_id' => $admin->id, 'user_valide2_id' => $admin->id,
            'date_depot' => now(), 'date_validation_1' => now(), 'date_validation_2' => now(),
        ]);

        return [$commande, $client, $admin];
    }

    public function test_le_site_est_sollicite_pour_le_recu_pdf(): void
    {
        Mail::fake();
        Http::fake(['*/api/interne/recu-paiement/*' => Http::response(['code' => 200, 'envoye' => true], 200)]);
        config(['constantes.jeton_interne' => 'jeton-de-recette', 'constantes.url_site' => 'http://site.recette']);
        [$commande, $client] = $this->jeu();

        $resultat = Avances::imputerSurCommande($commande, $client);

        $this->assertGreaterThanOrEqual(1, $resultat['impute']);
        $p = Paiement::where('numero_recu', $resultat['recus'][0])->first();
        $this->assertNotNull($p);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/api/interne/recu-paiement/' . $p->id)
            && $r['jeton'] === 'jeton-de-recette');
    }

    public function test_sans_site_le_recu_part_en_texte(): void
    {
        Mail::fake();
        config(['constantes.jeton_interne' => '']);
        [$commande, $client] = $this->jeu();
        $email = User::find($client->user_id)->email;

        $resultat = Avances::imputerSurCommande($commande, $client);

        $this->assertGreaterThanOrEqual(1, $resultat['impute']);
        Mail::assertSent(\App\Mail\RecuPaiementMail::class, fn ($m) => $m->hasTo($email)
            && str_starts_with((string) $m->recu['numeroRecu'], 'AV-'));
        $p = Paiement::where('numero_recu', $resultat['recus'][0])->first();
        $this->assertNotNull($p->recu_envoye_le);
    }

    public function test_un_envoi_qui_echoue_n_empeche_pas_l_imputation(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP injoignable (recette)'));
        config(['constantes.jeton_interne' => '']);
        [$commande, $client] = $this->jeu();

        $resultat = Avances::imputerSurCommande($commande, $client);

        $this->assertGreaterThanOrEqual(1, $resultat['impute'], 'L\'imputation est acquise malgré l\'échec du courriel.');
        $this->assertNotNull(Paiement::where('numero_recu', $resultat['recus'][0])->first());
    }
}
