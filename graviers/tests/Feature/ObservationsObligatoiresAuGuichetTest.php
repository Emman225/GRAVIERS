<?php

namespace Tests\Feature;

use App\Models\Agence;
use App\Models\Commande;
use App\Models\Facture;
use App\Models\ModePaiement;
use App\Models\Paiement;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * « NOTES / OBSERVATIONS » EST OBLIGATOIRE AUX DEUX GUICHETS CLIENTS (08/09/2026),
 * et le journal la reprend dans une colonne « Observations ».
 */
class ObservationsObligatoiresAuGuichetTest extends TestCase
{
    use DatabaseTransactions;

    private function caissier(): User
    {
        $user = User::whereIn('type_user_id', [1, 2])->where('statut', 1)->orderBy('id')->first();
        if (!$user) {
            $this->markTestSkipped('Aucun administrateur.');
        }
        if (!$user->agence_id) {
            $user->agence_id = Agence::first()?->id;
            $user->save();
        }
        return $user;
    }

    public function test_le_guichet_des_ventes_refuse_un_encaissement_sans_observation(): void
    {
        $commande = Commande::where('statut', '!=', 0)->get()
            ->first(fn (Commande $c) => $c->affaireVivante() && $c->montantRestantDu() >= 1);
        $mode = ModePaiement::listePourAgent()->first();
        if (!$commande || !$mode) {
            $this->markTestSkipped('Aucune commande due.');
        }
        Auth::guard('web')->login($this->caissier());
        $avant = Paiement::count();

        $this->post('/comptant/encaissements', [
            'numeros_commande' => [$commande->numero],
            'mode_paiement_id' => $mode->id,
            'montant'          => 1,
        ])->assertSessionHasErrors('notes');
        $this->assertSame($avant, Paiement::count());

        $this->post('/comptant/encaissements', [
            'numeros_commande' => [$commande->numero],
            'mode_paiement_id' => $mode->id,
            'montant'          => 1,
            'notes'            => 'Acompte remis par le chauffeur',
        ])->assertSessionHasNoErrors();
        $this->assertSame($avant + 1, Paiement::count());
        $this->assertSame('Acompte remis par le chauffeur', Paiement::orderByDesc('id')->first()->libelle);

        $html = $this->get('/comptant/encaissements')->assertOk()->getContent();
        $this->assertStringContainsString('<th class="text-center">Observations</th>', $html);
        $this->assertStringContainsString('Acompte remis par le chauffeur', $html);
    }

    public function test_le_guichet_des_creances_refuse_un_paiement_sans_observation(): void
    {
        // La validation refuse AVANT toute lecture de facture : l'obligation
        // se prouve sans facture en base.
        $mode = ModePaiement::listePourAgent()->first();
        if (!$mode) {
            $this->markTestSkipped('Aucun mode de paiement.');
        }
        Auth::guard('web')->login($this->caissier());
        $avant = Paiement::count();

        $this->post('/clients-terme/paiements', [
            'mode_paiement_id' => $mode->id,
            'montant'          => 1,
        ])->assertSessionHasErrors('notes');
        $this->assertSame($avant, Paiement::count());

        $html = $this->get('/clients-terme/paiements')->assertOk()->getContent();
        $this->assertStringContainsString('<th class="text-center">Observations</th>', $html);
        $this->assertStringContainsString('name="notes" rows="2" required', $html);
    }
}
