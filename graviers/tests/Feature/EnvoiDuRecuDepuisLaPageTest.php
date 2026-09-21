<?php

namespace Tests\Feature;

use App\Mail\DocumentPdfMail;
use App\Models\Paiement;
use App\Models\User;
use App\Services\RecuPaiement;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * « ENVOYER PAR COURRIEL » SUR LA PAGE DU REÇU (11/09/2026) : envoi immédiat,
 * même si un envoi est déjà daté, et l'erreur réelle affichée si le courriel ne
 * part pas — pour renvoyer un reçu et comprendre un envoi automatique manqué.
 */
class EnvoiDuRecuDepuisLaPageTest extends TestCase
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

    private function unPaiement(): Paiement
    {
        $p = Paiement::where('statut', 1)->whereNotNull('service_id')
            ->whereHas('client.user', fn ($q) => $q->whereNotNull('email')->where('email', '<>', ''))
            ->orderByDesc('id')->first();
        if (!$p) {
            $this->markTestSkipped('Aucun règlement d\'un client joignable.');
        }

        return $p;
    }

    public function test_la_page_du_recu_propose_l_envoi_par_courriel(): void
    {
        $p = $this->unPaiement();

        $this->actingAs($this->admin())->get(route('show.recu', $p->id))
            ->assertOk()
            ->assertSee('Envoyer par courriel')
            ->assertSee(route('show.recu.envoyer', $p->id), false);
    }

    public function test_le_bouton_envoie_le_recu_meme_deja_envoye(): void
    {
        Mail::fake();
        $p = $this->unPaiement();
        Paiement::where('id', $p->id)->update(['recu_envoye_le' => now()->subDay()]);
        $email = $p->client->user->email;

        $this->actingAs($this->admin())->post(route('show.recu.envoyer', $p->id))
            ->assertRedirect()->assertSessionMissing('error');

        Mail::assertSent(DocumentPdfMail::class, fn (DocumentPdfMail $m) => $m->emailClient === $email
            && $m->typeDocument === 'Reçu de paiement' && str_starts_with($m->pdfContent, '%PDF'));
        $this->assertTrue(\Illuminate\Support\Carbon::parse($p->fresh()->recu_envoye_le)->greaterThan(now()->subMinute()), 'La date d envoi est rafraichie.');
    }

    public function test_l_erreur_d_envoi_est_dite_au_guichet(): void
    {
        Mail::shouldReceive('send')->andThrow(new \RuntimeException('SMTP injoignable (recette)'));
        $p = $this->unPaiement();

        $reponse = $this->actingAs($this->admin())->post(route('show.recu.envoyer', $p->id));

        $reponse->assertRedirect();
        $this->assertSame('SMTP injoignable (recette)', RecuPaiement::$derniereErreur);
        $this->assertNull($p->fresh()->recu_envoye_le, 'Un envoi manqué n\'est pas daté.');
    }
}
