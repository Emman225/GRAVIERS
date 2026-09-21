<?php

namespace Tests\Feature;

use App\Models\Commande;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * LE DÉTAIL D'UNE COMMANDE PORTE LES CODES DE CHAQUE LIGNE (point 17, 08/09/2026).
 *
 * Pour chaque course acceptée : le code de livraison (remis au livreur) et
 * le bon d'enlèvement (remis au fournisseur). L'application les affiche
 * avec « copier » et « WhatsApp », y compris quand le client retire lui-même.
 */
class CodesSurDetailCommandeMobileTest extends TestCase
{
    use DatabaseTransactions;

    public function test_chaque_ligne_annonce_ses_codes(): void
    {
        $livraison = DB::table('livraison')
            ->join('detail_commande', 'detail_commande.id', '=', 'livraison.detail_commande_id')
            ->join('commande', 'commande.id', '=', 'detail_commande.commande_id')
            ->join('client', 'client.id', '=', 'commande.client_id')
            ->leftJoin('enlevement', 'enlevement.livraison_id', '=', 'livraison.id')
            ->where('livraison.accepte', 1)
            ->whereNull('livraison.deleted_at')
            ->whereNotNull('client.user_id')
            ->orderByDesc('livraison.id')
            ->first(['livraison.numero', 'enlevement.code_enleve', 'commande.id as commande_id',
                     'detail_commande.id as ligne_id', 'client.user_id']);
        if (!$livraison) {
            $this->markTestSkipped('Aucune course acceptée en base.');
        }

        $reponse = $this->postJson('/mon_gravier/details-commande/' . $livraison->commande_id, [
            'access' => Crypt::encryptString((string) $livraison->user_id),
            'type'   => 'mobile',
        ]);
        $reponse->assertOk();
        $this->assertSame(200, $reponse->json('code'), $reponse->json('message'));

        $ligne = collect($reponse->json('data.lignes'))->firstWhere('id', $livraison->ligne_id);
        $this->assertNotNull($ligne, 'La ligne de la course doit être dans le détail.');
        $this->assertArrayHasKey('codes', $ligne);
        $codes = collect($ligne['codes']);
        $this->assertTrue($codes->contains('code_livraison', $livraison->numero),
            'Le code de livraison de la course acceptée doit figurer sur sa ligne.');
        if ($livraison->code_enleve) {
            $this->assertTrue($codes->contains('code_enlevement', $livraison->code_enleve));
        }
    }

    public function test_une_ligne_sans_course_acceptee_a_une_liste_vide(): void
    {
        $ligne = DB::table('detail_commande')
            ->join('commande', 'commande.id', '=', 'detail_commande.commande_id')
            ->join('client', 'client.id', '=', 'commande.client_id')
            ->whereNotNull('client.user_id')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('livraison')
                  ->whereColumn('livraison.detail_commande_id', 'detail_commande.id')
                  ->where('livraison.accepte', 1)->whereNull('livraison.deleted_at');
            })
            ->orderByDesc('detail_commande.id')
            ->first(['detail_commande.id as ligne_id', 'commande.id as commande_id', 'client.user_id']);
        if (!$ligne) {
            $this->markTestSkipped('Toutes les lignes ont une course acceptée.');
        }

        $reponse = $this->postJson('/mon_gravier/details-commande/' . $ligne->commande_id, [
            'access' => Crypt::encryptString((string) $ligne->user_id),
            'type'   => 'mobile',
        ]);
        $l = collect($reponse->json('data.lignes'))->firstWhere('id', $ligne->ligne_id);
        $this->assertNotNull($l);
        $this->assertSame([], $l['codes']);
    }
}
