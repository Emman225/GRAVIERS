<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Commande;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * L'ÉTAT DES COMMANDES TÉLÉCHARGÉ PAR LE CLIENT (08/09/2026).
 *
 * Il sortait « Nom : Rapport » avec adresse, NCC et régime vides, et
 * « Aucun paiement effectué » sur chaque ligne — la colonne lisait le
 * drapeau actif/inactif de la commande, jamais ses règlements.
 */
class EtatDesCommandesDuClientTest extends TestCase
{
    use DatabaseTransactions;

    private function clientAvecCommandes(): Client
    {
        $client = Client::where('statut', 1)->whereHas('user')
            ->whereHas('Commande', fn ($q) => $q->where('statut', '!=', 0))
            ->orderBy('id')->first();
        if (!$client) {
            $this->markTestSkipped('Aucun client avec commandes.');
        }
        return $client;
    }

    public function test_l_etat_porte_le_client_et_le_paiement_reel(): void
    {
        $client = $this->clientAvecCommandes();
        $commandes = Commande::where('client_id', $client->id)->orderByDesc('created_at')->get();

        $html = view('document.etatCommande', [
            'commandes' => $commandes,
            'image'     => config('constantes.logo'),
            'client'    => $client,
        ])->render();

        $this->assertStringContainsString('Nom : ' . e($client->display_name), $html);
        $this->assertStringNotContainsString('Nom : Rapport', $html);

        foreach ($commandes as $c) {
            $du = $c->montantAPayer();
            $reste = $c->montantRestantDu();
            $paye = $c->montantPayeComptant();
            if ($c->etat_commande === \Help::$AFFAIRE_ANNULEE) {
                continue;
            }
            if ($du > 0 && $reste < 1) {
                $this->assertStringContainsString('Paiement soldé', $html, "La commande {$c->numero} est soldée.");
            } elseif ($paye >= 1) {
                $this->assertStringContainsString('Paiement en cours', $html, "La commande {$c->numero} est partiellement payée.");
            }
        }
        // Une commande soldée ou partielle ne doit plus être annoncée sans paiement.
        $soldees = $commandes->filter(fn ($c) => $c->montantPayeComptant() >= 1)->count();
        $sansPaiement = substr_count($html, 'Aucun paiement effectué');
        $this->assertSame($commandes->count() - $soldees - $commandes->where('etat_commande', \Help::$AFFAIRE_ANNULEE)
            ->filter(fn ($c) => $c->montantPayeComptant() < 1)->count(), $sansPaiement);
    }

    public function test_le_telechargement_repond_un_pdf(): void
    {
        $client = $this->clientAvecCommandes();
        Auth::guard('web')->login($client->user);

        $reponse = $this->get('/export-commande');
        $reponse->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $reponse->headers->get('content-type'));
    }
}
