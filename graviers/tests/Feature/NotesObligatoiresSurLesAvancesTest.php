<?php

namespace Tests\Feature;

use App\Models\AvanceClient;
use App\Models\Client;
use App\Models\ModePaiement;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * /avances (09/09/2026) : la note saisie dans « Dépôt d'une avance » est
 * obligatoire, et la liste des avances l'affiche dans une colonne « Notes ».
 */
class NotesObligatoiresSurLesAvancesTest extends TestCase
{
    use DatabaseTransactions;

    /** Un administrateur rattaché à une agence : sans agence, aucun dépôt n'est possible. */
    private function admin(): User
    {
        $u = User::whereIn('type_user_id', [1, 2])->where('statut', 1)->orderBy('id')->first();
        if (!$u) {
            $this->markTestSkipped('Aucun administrateur.');
        }
        if (!$u->agence_id) {
            $u->agence_id = \App\Models\Agence::value('id');
            $u->save();
        }

        return $u;
    }

    /** Un client actif sans affaire non soldée (règle du dépôt). */
    private function client(): Client
    {
        $client = Client::where('statut', 1)->whereHas('user')->get()
            ->first(fn (Client $c) => \App\Services\Avances::affairesNonSoldees($c)->isEmpty());
        if (!$client) {
            $this->markTestSkipped('Aucun client actif sans affaire non soldée.');
        }

        return $client;
    }

    public function test_le_depot_exige_une_note_et_la_liste_l_affiche(): void
    {
        Auth::guard('web')->login($this->admin());
        $client = $this->client();
        $mode = ModePaiement::listePourAgent()->first() ?: ModePaiement::first();
        if (!$mode) {
            $this->markTestSkipped('Aucun mode de paiement.');
        }

        // Sans note : refus de validation.
        $this->post('/avances', ['client_id' => $client->id, 'montant' => 5000, 'mode_paiement_id' => $mode->id, 'notes' => ''])
            ->assertSessionHasErrors('notes');
        $this->assertSame(0, AvanceClient::where('client_id', $client->id)->where('montant', 5000)->where('libelle', null)->count());

        // Avec une note : dépôt enregistré, note dans la liste.
        $this->post('/avances', ['client_id' => $client->id, 'montant' => 5000, 'mode_paiement_id' => $mode->id, 'notes' => 'Avance sur chantier Riviera'])
            ->assertRedirect();
        $avance = AvanceClient::where('client_id', $client->id)->orderByDesc('id')->first();
        $this->assertNotNull($avance);
        $this->assertSame('Avance sur chantier Riviera', $avance->libelle);

        $html = $this->get('/avances')->assertOk()->getContent();
        $this->assertStringContainsString('<th>Observations / Notes</th>', $html, 'La colonne « Observations / Notes » manque.');
        $this->assertStringContainsString('Avance sur chantier Riviera', $html, 'La note du dépôt ne figure pas dans la liste.');
        $this->assertMatchesRegularExpression('/<textarea[^>]*name="notes"[^>]*required/', $html, 'Le champ Notes du formulaire doit être obligatoire.');
    }
}
