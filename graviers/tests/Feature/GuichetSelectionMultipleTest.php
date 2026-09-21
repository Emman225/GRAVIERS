<?php

namespace Tests\Feature;

use App\Models\Agence;
use App\Models\Commande;
use App\Models\ModePaiement;
use App\Models\Paiement;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * PLUSIEURS COMMANDES EN UN SEUL ENCAISSEMENT (point 22, 07/09/2026).
 *
 * Le montant saisi s'impute de la plus ancienne à la plus récente ; chaque
 * commande reçoit son règlement et son reçu. Les commandes cochées doivent
 * être du même client.
 */
class GuichetSelectionMultipleTest extends TestCase
{
    use DatabaseTransactions;

    private function caissier(): User
    {
        $user = User::whereNotNull('agence_id')->whereIn('type_user_id', [1, 2])->first();
        if ($user) {
            return $user;
        }
        $agence = Agence::first();
        $user = User::whereIn('type_user_id', [1, 2])->first();
        if (!$agence || !$user) {
            $this->markTestSkipped('Ni agence ni administrateur en base.');
        }
        $user->agence_id = $agence->id;
        $user->save();
        return $user;
    }

    /** Deux commandes dues, vivantes, d'un même client ordinaire. */
    private function deuxCommandesDuMemeClient(): array
    {
        $dues = Commande::whereHas('client', function ($q) {
                $q->where(function ($c) { $c->where('client_a_terme', 0)->orWhereNull('client_a_terme'); })
                  ->where('statut', 1);
            })
            ->where('statut', '!=', 0)
            ->get()
            ->filter(fn (Commande $c) => $c->affaireVivante() && $c->montantRestantDu() > 0)
            ->groupBy('client_id')
            ->first(fn ($groupe) => $groupe->count() >= 2);

        if (!$dues) {
            $this->markTestSkipped('Aucun client ordinaire avec deux commandes dues.');
        }

        return $dues->take(2)->values()->all();
    }

    public function test_deux_commandes_cochees_donnent_deux_reglements_imputes_chacun(): void
    {
        [$a, $b] = $this->deuxCommandesDuMemeClient();
        $mode = ModePaiement::where('statut', \Help::$STATUT_ACTIF)->first();
        if (!$mode) {
            $this->markTestSkipped('Aucun mode de paiement actif.');
        }
        Auth::guard('web')->login($this->caissier());

        $resteA = \Help::arrondiFranc($a->montantRestantDu());
        $resteB = \Help::arrondiFranc($b->montantRestantDu());
        $avant  = Paiement::count();

        $reponse = $this->post('/comptant/encaissements', [
            'numeros_commande' => [$a->numero, $b->numero],
            'mode_paiement_id' => $mode->id,
            'notes' => 'Recette automatique',
            'montant'          => $resteA + $resteB,
        ]);

        $reponse->assertSessionHasNoErrors();
        $this->assertSame($avant + 2, Paiement::count(), 'Chaque commande cochée reçoit son propre règlement.');

        $pA = Paiement::where('service', 'COMMANDE')->where('service_id', $a->id)->orderByDesc('id')->first();
        $pB = Paiement::where('service', 'COMMANDE')->where('service_id', $b->id)->orderByDesc('id')->first();
        $this->assertEqualsWithDelta($resteA, (float) $pA->montant_total, 0.01);
        $this->assertEqualsWithDelta($resteB, (float) $pB->montant_total, 0.01);
        $this->assertNotSame($pA->numero_recu, $pB->numero_recu, 'Deux reçus distincts.');
    }

    public function test_un_montant_partiel_s_impute_sur_la_plus_ancienne_d_abord(): void
    {
        [$a, $b] = $this->deuxCommandesDuMemeClient();
        $mode = ModePaiement::where('statut', \Help::$STATUT_ACTIF)->first();
        if (!$mode) {
            $this->markTestSkipped('Aucun mode de paiement actif.');
        }
        Auth::guard('web')->login($this->caissier());

        $ordre = collect([$a, $b])->sortBy(fn (Commande $c) => ($c->date_commande ?? $c->created_at) . '-' . $c->id)->values();
        $premiere = $ordre[0];
        $resteP   = \Help::arrondiFranc($premiere->montantRestantDu());
        if ($resteP < 2) {
            $this->markTestSkipped('Reste trop petit pour un partiel.');
        }

        $avant = Paiement::count();
        $this->post('/comptant/encaissements', [
            'numeros_commande' => [$a->numero, $b->numero],
            'mode_paiement_id' => $mode->id,
            'notes' => 'Recette automatique',
            'montant'          => $resteP - 1,
        ])->assertSessionHasNoErrors();

        $this->assertSame($avant + 1, Paiement::count(), 'Un montant qui ne couvre pas la première commande ne touche pas la seconde.');
        $p = Paiement::orderByDesc('id')->first();
        $this->assertSame((int) $premiere->id, (int) $p->service_id);
        $this->assertEqualsWithDelta($resteP - 1, (float) $p->montant_total, 0.01);
    }

    public function test_deux_clients_differents_sont_refuses(): void
    {
        $mode = ModePaiement::where('statut', \Help::$STATUT_ACTIF)->first();
        $dues = Commande::whereHas('client')->where('statut', '!=', 0)->get()
            ->filter(fn (Commande $c) => $c->affaireVivante() && $c->montantRestantDu() > 0);
        $a = $dues->first();
        $b = $a ? $dues->first(fn ($c) => $c->client_id !== $a->client_id) : null;
        if (!$mode || !$a || !$b) {
            $this->markTestSkipped('Il faut deux commandes dues de clients différents.');
        }
        Auth::guard('web')->login($this->caissier());

        $avant = Paiement::count();
        $this->post('/comptant/encaissements', [
            'numeros_commande' => [$a->numero, $b->numero],
            'mode_paiement_id' => $mode->id,
            'notes' => 'Recette automatique',
            'montant'          => 1,
        ]);

        $this->assertSame($avant, Paiement::count(), "Deux clients dans un même encaissement : rien ne doit s'enregistrer.");
    }
}
