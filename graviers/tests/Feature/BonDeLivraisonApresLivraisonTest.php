<?php

namespace Tests\Feature;

use App\Mail\DocumentPdfMail;
use App\Models\Client;
use App\Models\Enlevement;
use App\Models\Livraison;
use App\Services\BonDeLivraisonClient;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * LE BON DE LIVRAISON DU CLIENT ENTREPRISE (lot 84, 15/09/2026) : bleu du logo,
 * colonne « Date enlèvement ou livraison », historique des enlèvements de la
 * commande, envoi par courriel à chaque livraison, une seule fois par bon.
 */
class BonDeLivraisonApresLivraisonTest extends TestCase
{
    use DatabaseTransactions;

    private function unBonServiDeCommande(): Enlevement
    {
        $bon = Enlevement::whereNotNull('qte_servi')
            ->whereHas('livraison', fn ($q) => $q->where('provenance', 'COMMANDE')->whereNotNull('client_id'))
            ->orderByDesc('id')->first();
        if (!$bon || !$bon->livraison?->client?->id) {
            $this->markTestSkipped('Aucun bon servi sur une commande avec client.');
        }

        return $bon;
    }

    public function test_la_base_porte_les_colonnes(): void
    {
        $this->assertTrue(Schema::hasColumn('livraison', 'date_livree'));
        $this->assertTrue(Schema::hasColumn('enlevement', 'bon_envoye_le'));
    }

    public function test_le_bon_porte_la_date_le_bleu_et_l_historique(): void
    {
        $bon = $this->unBonServiDeCommande();
        $html = view('livreur.bonImprime', BonDeLivraisonClient::donnees($bon))->render();

        $this->assertStringContainsString('Date enlèvement ou livraison', $html);
        $this->assertStringNotContainsString('yellow', $html);
        $this->assertStringContainsString('background-color: #1c57a3', $html);
        $this->assertStringContainsString('Historique des enlèvements de la commande N°', $html);
        $this->assertStringContainsString('(ce bon)', $html);
        $this->assertStringContainsString('Reste à livrer', $html);
        $date = BonDeLivraisonClient::dateDuBon($bon);
        $this->assertNotNull($date);
        $this->assertStringContainsString($date->format('d/m/Y'), $html);

        // Le cumul suit l'ordre des dates, jusqu'à ce bon.
        $historique = BonDeLivraisonClient::historique($bon);
        $this->assertNotEmpty($historique);
        $this->assertTrue(collect($historique)->contains(fn ($h) => $h['courant']));

        // L'exemplaire fournisseur n'est plus jaune non plus.
        $this->assertStringNotContainsString('yellow', file_get_contents(resource_path('views/fournisseur/bonImprime.blade.php')));
    }

    public function test_le_bon_part_une_fois_au_client_entreprise_et_pas_au_particulier(): void
    {
        $bon = $this->unBonServiDeCommande();
        $livraison = $bon->livraison;
        $client = Client::find($livraison->client_id);
        $client->update(['type_client' => 'ENTREPRISE']);
        if (!($client->user?->email) && !($client->email)) {
            $client->update(['email' => 'recette-bon@example.com']);
        }
        Livraison::where('id', $livraison->id)->update(['etat_livraison' => \Help::$LIVRAISON_LIVREE, 'date_livree' => null]);
        Enlevement::where('id', $bon->id)->update(['bon_envoye_le' => null]);
        Mail::fake();

        $this->assertTrue(BonDeLivraisonClient::envoyerApresLivraison(Livraison::find($livraison->id), true));
        Mail::assertSent(DocumentPdfMail::class, fn (DocumentPdfMail $m) =>
            $m->typeDocument === 'Bon de livraison'
            && str_starts_with($m->pdfContent, '%PDF')
            && str_starts_with($m->nomFichier, 'bon-de-livraison-'));
        $this->assertNotNull(Enlevement::find($bon->id)->bon_envoye_le);
        $this->assertNotNull(Livraison::find($livraison->id)->date_livree, 'La date de livraison effective est posée.');

        // Un second chemin se tait.
        $this->assertFalse(BonDeLivraisonClient::envoyerApresLivraison(Livraison::find($livraison->id), true));
        Mail::assertSent(DocumentPdfMail::class, 1);

        // Un particulier ne reçoit rien.
        Enlevement::where('id', $bon->id)->update(['bon_envoye_le' => null]);
        $client->update(['type_client' => 'PARTICULIER']);
        $this->assertFalse(BonDeLivraisonClient::envoyerApresLivraison(Livraison::find($livraison->id), true));
        Mail::assertSent(DocumentPdfMail::class, 1);
    }

    public function test_un_envoi_qui_echoue_ne_bloque_rien(): void
    {
        $bon = $this->unBonServiDeCommande();
        $livraison = $bon->livraison;
        $client = Client::find($livraison->client_id);
        $client->update(['type_client' => 'ENTREPRISE']);
        if (!($client->user?->email) && !($client->email)) {
            $client->update(['email' => 'recette-bon@example.com']);
        }
        Enlevement::where('id', $bon->id)->update(['bon_envoye_le' => null]);
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('SMTP injoignable'));

        $this->assertTrue(BonDeLivraisonClient::envoyerApresLivraison(Livraison::find($livraison->id), true));
        $this->assertNull(Enlevement::find($bon->id)->bon_envoye_le, 'La mémoire est libérée pour un prochain essai.');
    }

    public function test_la_route_interne_exige_le_jeton(): void
    {
        $bon = $this->unBonServiDeCommande();
        $this->postJson('/api/interne/livraison/' . $bon->livraison_id . '/bon-de-livraison', ['jeton' => 'faux'])->assertStatus(403);
    }
}
