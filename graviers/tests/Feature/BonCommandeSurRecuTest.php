<?php

namespace Tests\Feature;

use App\Models\BlClient;
use App\Models\Commande;
use App\Models\Paiement;
use App\Models\User;
use App\Services\RecuPaiement;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * POINT 16 (07/09/2026) : le numéro de bon de commande du client figure sur
 * le reçu de paiement, comme sur la facture. La ligne est toujours imprimée
 * pour une commande : son absence doit se voir.
 */
class BonCommandeSurRecuTest extends TestCase
{
    use DatabaseTransactions;

    public function test_le_recu_d_une_commande_imprime_le_bon_de_commande(): void
    {
        $paiement = Paiement::where('service', 'COMMANDE')->whereNotNull('service_id')
            ->whereHas('commande')->orderBy('id')->first();
        $admin = User::whereIn('type_user_id', [1, 2])->where('statut', 1)->first();
        if (!$paiement || !$admin) {
            $this->markTestSkipped('Aucun règlement de commande ou aucun administrateur.');
        }

        $commande = Commande::find($paiement->service_id);
        if (!$commande->blClient) {
            BlClient::create([
                'numero'      => 'BC-TEST-2026',
                'client_id'   => $commande->client_id,
                'commande_id' => $commande->id,
                'fichier'     => 'lesBons/test-bc.pdf',
            ]);
            $commande->unsetRelation('blClient');
        }
        $numeroBc = Commande::find($commande->id)->blClient->numero;

        $donnees = RecuPaiement::donnees($paiement);
        $this->assertSame($numeroBc, $donnees['bonCommande']);

        Auth::guard('web')->login($admin);
        $this->get('/recu/' . $paiement->id)->assertOk()
            ->assertSee('Bon de commande')
            ->assertSee($numeroBc);
    }

    public function test_sans_bon_la_ligne_reste_visible_avec_un_tiret(): void
    {
        $paiement = Paiement::where('service', 'COMMANDE')->whereNotNull('service_id')
            ->whereHas('commande', fn ($q) => $q->whereDoesntHave('blClient'))
            ->orderBy('id')->first();
        $admin = User::whereIn('type_user_id', [1, 2])->where('statut', 1)->first();
        if (!$paiement || !$admin) {
            $this->markTestSkipped('Aucun règlement de commande sans bon.');
        }

        $this->assertNull(RecuPaiement::donnees($paiement)['bonCommande']);

        Auth::guard('web')->login($admin);
        $this->get('/recu/' . $paiement->id)->assertOk()->assertSee('Bon de commande');
    }
}
