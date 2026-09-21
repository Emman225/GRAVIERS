<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DemandeCompteClientATerme;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * PAS DE DEMANDE « CLIENT À TERME » SANS SES PIÈCES — DEPUIS LE MOBILE AUSSI.
 *
 * Point 15 du 07/09/2026 : mêmes pièces obligatoires que sur le site (RCCM,
 * attestation de revenus / bilan, pièce d'identité du dirigeant), et le
 * message nomme ce qui manque.
 */
class PiecesClientATermeMobileTest extends TestCase
{
    use DatabaseTransactions;

    public function test_une_demande_sans_pieces_est_refusee_en_nommant_ce_qui_manque(): void
    {
        $client = Client::where('type_client', \Help::$ENTREPRISE)
            ->whereNotNull('user_id')->whereHas('user')
            ->where(function ($q) { $q->where('client_a_terme', 0)->orWhereNull('client_a_terme'); })
            ->first();
        if (!$client) {
            $this->markTestSkipped('Aucun client entreprise ordinaire avec un compte.');
        }
        DemandeCompteClientATerme::where('client_id', $client->id)->delete();

        $avant = DemandeCompteClientATerme::count();

        $reponse = $this->postJson('/mon_gravier/demande-client-a-terme', [
            'access'      => Crypt::encryptString((string) $client->user_id),
            'type'        => 'mobile',
            'objet'       => 'Ouverture de compte',
            'description' => 'Nous commandons chaque semaine.',
            // Une seule pièce sur trois.
            'documents'   => ['rccm' => ['fichier' => base64_encode('x'), 'extension' => 'pdf']],
        ]);

        $reponse->assertOk();
        $this->assertSame(400, $reponse->json('code'), $reponse->json('message'));
        $message = (string) $reponse->json('message');
        $this->assertStringContainsString('bilan', $message);
        $this->assertStringContainsString("pièce d'identité", $message);
        $this->assertStringNotContainsString('RCCM', $message, 'Le RCCM était fourni : il ne doit pas être réclamé.');
        $this->assertSame($avant, DemandeCompteClientATerme::count());
    }
}
