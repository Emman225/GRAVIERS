<?php

namespace Tests\Feature;

use App\Models\DemandePaiement;
use App\Models\ModePaiement;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * APRÈS LA 2e VALIDATION : « À PAYER », PREUVE, « EFFECTUÉE » (point 20).
 */
class PreuveDePaiementTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        $u = User::whereIn('type_user_id', [1, 2])->where('statut', 1)->orderBy('id')->first();
        if (!$u) {
            $this->markTestSkipped('Aucun administrateur.');
        }
        return $u;
    }

    /** Les deux validateurs : d'AUTRES administrateurs que celui qui téléverse et finalise (sécurité, 09/09/2026). */
    private function validateurs(): array
    {
        $autres = User::whereIn('type_user_id', [1, 2])->where('statut', 1)->orderBy('id')->skip(1)->take(2)->get();
        if ($autres->count() < 2) {
            $this->markTestSkipped('Il faut trois administrateurs.');
        }
        return [$autres[0], $autres[1]];
    }

    private function demandeValidee(): DemandePaiement
    {
        $livreur = User::where('type_user_id', 8)->first();
        $mode = ModePaiement::first();
        if (!$livreur || !$mode) {
            $this->markTestSkipped('Il faut un livreur et un mode de paiement.');
        }

        return DemandePaiement::create([
            'montant'          => 15000,
            'mode_paiement_id' => $mode->id,
            'user_id'          => $livreur->id,
            'user_valide_id'   => $this->validateurs()[0]->id,
            'user_valide2_id'  => $this->validateurs()[1]->id,
            'date_validation'  => now(),
            'paye'             => 1,
            'etat_reglement'   => DemandePaiement::A_PAYER,
            'statut'           => 1,
        ]);
    }

    public function test_la_finalisation_exige_une_preuve_puis_marque_effectuee(): void
    {
        Storage::fake('public');
        $demande = $this->demandeValidee();
        Auth::guard('web')->login($this->admin());

        $this->assertSame('À payer', $demande->libelleReglement());

        // Sans preuve : refus, rien ne bouge.
        $this->post(route('show.demandePaiement.effectuer', $demande));
        $this->assertSame(DemandePaiement::A_PAYER, $demande->fresh()->etat_reglement);

        // La preuve.
        $this->post(route('show.demandePaiement.preuve', $demande), [
            'preuve' => UploadedFile::fake()->create('virement.pdf', 120, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $demande->refresh();
        $this->assertSame(DemandePaiement::PREUVE_JOINTE, $demande->etat_reglement);
        $this->assertNotEmpty($demande->preuve_paiement);
        Storage::disk('public')->assertExists($demande->preuve_paiement);
        $this->assertSame($this->admin()->id, (int) $demande->user_preuve_id);

        // La finalisation.
        $this->post(route('show.demandePaiement.effectuer', $demande))->assertSessionHasNoErrors();
        $demande->refresh();
        $this->assertTrue($demande->estEffectuee());
        $this->assertNotNull($demande->date_effectuee);
        $this->assertSame('Effectuée', $demande->libelleReglement());
    }

    public function test_une_preuve_qui_n_est_pas_un_document_est_refusee(): void
    {
        Storage::fake('public');
        $demande = $this->demandeValidee();
        Auth::guard('web')->login($this->admin());

        $this->post(route('show.demandePaiement.preuve', $demande), [
            'preuve' => UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream'),
        ])->assertSessionHasErrors('preuve');

        $this->assertSame(DemandePaiement::A_PAYER, $demande->fresh()->etat_reglement);
    }

    public function test_l_espace_du_livreur_annonce_le_paiement_effectue(): void
    {
        $demande = $this->demandeValidee();
        $demande->update(['etat_reglement' => DemandePaiement::EFFECTUEE, 'date_effectuee' => now()]);

        Auth::guard('web')->login(User::find($demande->user_id));
        $reponse = $this->get(route('livreur.listeDesDemandesDePaiement'));
        $reponse->assertOk();
        $reponse->assertSee('Effectuée');
    }

    /** Sécurité (09/09/2026) : un validateur de la demande ne peut ni téléverser la preuve ni finaliser. */
    public function test_un_validateur_ne_peut_ni_joindre_la_preuve_ni_finaliser(): void
    {
        Storage::fake('public');
        $demande = $this->demandeValidee();
        Auth::guard('web')->login(User::find($demande->user_valide2_id));

        $this->post(route('show.demandePaiement.preuve', $demande), [
            'preuve' => UploadedFile::fake()->create('virement.pdf', 120, 'application/pdf'),
        ])->assertRedirect();
        $this->assertSame(DemandePaiement::A_PAYER, $demande->fresh()->etat_reglement, 'Le 2e validateur ne doit pas pouvoir joindre la preuve.');
        $this->assertEmpty($demande->fresh()->preuve_paiement);

        $demande->update(['etat_reglement' => DemandePaiement::PREUVE_JOINTE, 'preuve_paiement' => 'preuves_paiement/x.pdf']);
        $this->post(route('show.demandePaiement.effectuer', $demande))->assertRedirect();
        $this->assertSame(DemandePaiement::PREUVE_JOINTE, $demande->fresh()->etat_reglement, 'Le 2e validateur ne doit pas pouvoir finaliser.');
    }
}
