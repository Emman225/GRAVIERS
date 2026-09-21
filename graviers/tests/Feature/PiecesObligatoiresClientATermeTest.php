<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DemandeCompteClientATerme;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * PAS DE DEMANDE « CLIENT À TERME » SANS SES PIÈCES.
 *
 * Point 15 du 07/09/2026 : RCCM, attestation de revenus / bilan et pièce
 * d'identité du dirigeant sont obligatoires ; le message nomme ce qui manque.
 */
class PiecesObligatoiresClientATermeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_la_demande_est_refusee_sans_les_trois_pieces_et_dit_lesquelles(): void
    {
        $client = Client::where('type_client', 'ENTREPRISE')->whereHas('user')->first();
        if (!$client) {
            $this->markTestSkipped('Aucun client entreprise en base.');
        }
        DemandeCompteClientATerme::where('client_id', $client->id)->delete();

        Auth::guard('web')->login($client->user);
        $avant = DemandeCompteClientATerme::count();

        $reponse = $this->from(route('client.demandeClientATermePage'))
            ->post(route('client.demandeClientATerme'), [
                'objet'       => 'Ouverture de compte',
                'description' => 'Nous commandons régulièrement du gravier pour nos chantiers.',
            ]);

        $reponse->assertSessionHasErrors(['documents.rccm', 'documents.bilan', 'documents.piece_id']);
        $this->assertStringContainsString('RCCM', session('errors')->first('documents.rccm'));
        $this->assertSame($avant, DemandeCompteClientATerme::count(), 'Une demande sans pièce a été enregistrée.');
    }

    public function test_le_formulaire_exige_les_pieces(): void
    {
        $client = Client::where('type_client', 'ENTREPRISE')->whereHas('user')->first();
        if (!$client) {
            $this->markTestSkipped('Aucun client entreprise en base.');
        }
        DemandeCompteClientATerme::where('client_id', $client->id)->delete();
        Auth::guard('web')->login($client->user);

        $reponse = $this->get(route('client.demandeClientATermePage'));
        $reponse->assertOk();
        $reponse->assertSee('name="documents[rccm]" class="dct-input" required', false);
        $reponse->assertSee('name="documents[piece_id]" class="dct-input" required', false);
    }
}
