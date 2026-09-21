<?php

namespace Tests\Feature;

use App\Models\Apporteur;
use App\Models\Livreur;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * POINT 20 SUR LES RÈGLEMENTS DE DETTES (09/09/2026) — côté applications.
 *
 * Un règlement saisi au back-office pour un livreur ou un apporteur remonte
 * dans sa liste de paiements avec l'état du circuit (etat_reglement) : l'écran
 * affiche « Validé — paiement en cours » tant qu'il n'est pas finalisé, puis
 * « Effectué ». Les règlements d'avant le circuit (etat vide) restent « Payé ».
 */
class EtatReglementPartenaireMobileTest extends TestCase
{
    use DatabaseTransactions;

    private function reglement(string $table, string $cle, int $tiersId, ?string $etat): int
    {
        $lien = $table === 'paiement_livreur' ? 'livraison_id' : 'commission_id';
        $lienId = $table === 'paiement_livreur'
            ? (DB::table('livraison')->value('id') ?? 0)
            : (DB::table('commission_apporteur')->value('id') ?? 0);

        return DB::table($table)->insertGetId([
            'date_paiement'   => now(), $cle => $tiersId, $lien => $lienId, 'montant' => 7000, 'mode_paiement_id' => 1,
            'reference' => 'REC-MOB-20', 'user_id' => 1, 'statut' => 1, 'user_valide_id' => 1, 'user_valide2_id' => 2,
            'date_validation_1' => now(), 'date_validation_2' => now(), 'etat_reglement' => $etat,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_le_livreur_lit_l_etat_du_reglement(): void
    {
        $livreur = Livreur::whereHas('user')->first();
        if (!$livreur) {
            $this->markTestSkipped('Aucun livreur.');
        }
        $effectue = $this->reglement('paiement_livreur', 'livreur_id', $livreur->id, 'EFFECTUEE');
        $enCours  = $this->reglement('paiement_livreur', 'livreur_id', $livreur->id, 'A_PAYER');

        $reponse = $this->postJson('/mon_gravier_livreur/lister-demande-paiement', [
            'access' => Crypt::encryptString((string) $livreur->user_id),
            'type'   => 'mobile',
        ]);
        $reponse->assertOk();
        $this->assertSame(200, $reponse->json('code'), $reponse->json('message'));
        $lignes = collect($reponse->json('data'));
        $this->assertSame('EFFECTUEE', $lignes->firstWhere('id', $effectue)['etat_reglement'] ?? null, 'Le règlement finalisé doit remonter « EFFECTUEE ».');
        $this->assertSame('A_PAYER', $lignes->firstWhere('id', $enCours)['etat_reglement'] ?? null, 'Le règlement en cours doit remonter « A_PAYER ».');
    }

    public function test_l_apporteur_lit_l_etat_du_reglement(): void
    {
        $apporteur = Apporteur::whereHas('user')->first();
        if (!$apporteur) {
            $this->markTestSkipped('Aucun apporteur.');
        }
        $effectue = $this->reglement('paiement_apporteur', 'apporteur_id', $apporteur->id, 'EFFECTUEE');

        $reponse = $this->postJson('/mon_gravier_apporteur/lister-demande-paiement', [
            'access' => Crypt::encryptString((string) $apporteur->user_id),
            'type'   => 'mobile',
        ]);
        $reponse->assertOk();
        $this->assertSame(200, $reponse->json('code'), $reponse->json('message'));
        $this->assertSame('EFFECTUEE', collect($reponse->json('data'))->firstWhere('id', $effectue)['etat_reglement'] ?? null);
    }
}
