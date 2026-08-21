<?php

namespace Tests\Feature;

use App\Models\Apporteur;
use App\Models\CommissionApporteur;
use App\Models\DemandePaiement;
use App\Models\Livraison;
use App\Models\Livreur;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Les applications mobiles voient TOUT ce que le tiers a touché.
 *
 * L'entreprise paie par deux chemins : la demande que le tiers envoie depuis
 * l'application, et le règlement qu'un administrateur saisit sur une de ses
 * pièces. Le livreur ne recevait que le premier. L'apporteur recevait les
 * deux, mais SANS exclure les règlements qui découlent d'une demande : depuis
 * que la validation d'une demande crée sa ligne de règlement, le même
 * versement lui serait apparu deux fois.
 */
class HistoriquePaiementMobileTest extends TestCase
{
    use DatabaseTransactions;

    private function appeler(string $url, $user): array
    {
        $reponse = $this->postJson($url, [
            'access' => Crypt::encryptString((string) $user->id),
            'type'   => 'mobile',
        ]);

        $reponse->assertOk();

        return $reponse->json();
    }

    public function test_le_livreur_recoit_les_reglements_du_back_office(): void
    {
        $course = Livraison::whereNotNull('livreur_id')->first();

        if (!$course) {
            $this->markTestSkipped('Aucune course rattachée à un livreur.');
        }

        $livreur = Livreur::find($course->livreur_id);

        if (!$livreur || !$livreur->user_id || !$livreur->user) {
            $this->markTestSkipped('Le livreur n\'a pas de compte.');
        }

        DB::table('paiement_livreur')->where('livreur_id', $livreur->id)->delete();

        DB::table('paiement_livreur')->insert([
            'date_paiement'    => now()->toDateString(),
            'livraison_id'     => $course->id,
            'livreur_id'       => $livreur->id,
            'montant'          => 3210,
            'mode_paiement_id' => 6,
            'reference'        => 'MOBILE-LVR-1',
            'statut'           => 1,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $data = $this->appeler('/mon_gravier_livreur/lister-demande-paiement', $livreur->user);

        $references = array_column($data['data'] ?? [], 'numero_compte');

        $this->assertContains('MOBILE-LVR-1', $references,
            "Le règlement du back-office n'est pas remonté à l'application.");
    }

    public function test_le_livreur_ne_voit_pas_deux_fois_un_reglement_issu_d_une_demande(): void
    {
        $course = Livraison::whereNotNull('livreur_id')->first();
        $livreur = $course ? Livreur::find($course->livreur_id) : null;

        if (!$livreur || !$livreur->user) {
            $this->markTestSkipped('Aucun livreur exploitable.');
        }

        DB::table('paiement_livreur')->where('livreur_id', $livreur->id)->delete();
        DemandePaiement::where('user_id', $livreur->user_id)->delete();

        $demande = DemandePaiement::create([
            'numero' => 'MOB-DEM-LVR', 'montant' => 900,
            'user_id' => $livreur->user_id, 'mode_paiement_id' => 6, 'paye' => 1,
        ]);

        DB::table('paiement_livreur')->insert([
            'date_paiement'       => now()->toDateString(),
            'livraison_id'        => $course->id,
            'demande_paiement_id' => $demande->id,
            'livreur_id'          => $livreur->id,
            'montant'             => 900,
            'mode_paiement_id'    => 6,
            'reference'           => 'MOB-DEM-LVR',
            'statut'              => 1,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        $data = $this->appeler('/mon_gravier_livreur/lister-demande-paiement', $livreur->user);

        // `numero` n'est pas fillable dans le modele de l'API : il est
        // ignore silencieusement. On compte donc par MONTANT, qui lui remonte.
        $lignes = collect($data['data'] ?? [])
            ->filter(fn ($l) => (float) ($l['montant'] ?? 0) === 900.0);

        $this->assertCount(1, $lignes, 'Le versement remonte deux fois.');
    }

    public function test_l_apporteur_ne_voit_pas_deux_fois_un_reglement_issu_d_une_demande(): void
    {
        $apporteur = Apporteur::whereNotNull('user_id')->whereHas('user')->first();

        if (!$apporteur) {
            $this->markTestSkipped('Aucun apporteur rattaché à un compte.');
        }

        DB::table('paiement_apporteur')->where('apporteur_id', $apporteur->id)->delete();
        DB::table('commission_apporteur')->where('apporteur_id', $apporteur->id)->delete();
        DemandePaiement::where('user_id', $apporteur->user_id)->delete();

        $commissionId = DB::table('commission_apporteur')->insertGetId([
            'apporteur_id' => $apporteur->id, 'montant' => 1500,
            'type_affaire' => 'VENTE', 'statut' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $demande = DemandePaiement::create([
            'numero' => 'MOB-DEM-APP', 'montant' => 1500,
            'user_id' => $apporteur->user_id, 'mode_paiement_id' => 6, 'paye' => 1,
        ]);

        DB::table('paiement_apporteur')->insert([
            'date_paiement'       => now()->toDateString(),
            'commission_id'       => $commissionId,
            'demande_paiement_id' => $demande->id,
            'apporteur_id'        => $apporteur->id,
            'montant'             => 1500,
            'mode_paiement_id'    => 6,
            'reference'           => 'MOB-DEM-APP',
            'statut'              => 1,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        $data = $this->appeler('/mon_gravier_apporteur/lister-demande-paiement', $apporteur->user);

        // `numero` n'est pas fillable dans le modele de l'API : on compte
        // donc par MONTANT, qui lui remonte.
        $lignes = collect($data['data'] ?? [])
            ->filter(fn ($l) => (float) ($l['montant'] ?? 0) === 1500.0);

        $this->assertCount(1, $lignes, 'Le versement remonte deux fois.');
    }
}
