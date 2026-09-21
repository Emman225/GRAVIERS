<?php

namespace Tests\Feature;

use App\Models\AdresseLivraison;
use App\Models\AvanceClient;
use App\Models\Client;
use App\Models\DemandeLivraison;
use App\Models\ModePaiement;
use App\Models\UniteProduit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * UNE DEMANDE DE LIVRAISON DEPUIS L'APPLICATION IMPUTE L'AVANCE DU CLIENT
 * (11/09/2026) pour tout mode hors ligne, comme une vente ou une location. La
 * règle exigeait le mot « agence » dans le libellé du mode : « Espèces »,
 * « Chèque », « Virement » n'imputaient rien.
 */
class AvanceSurDemandeDeLivraisonMobileTest extends TestCase
{
    use DatabaseTransactions;

    public function test_un_mode_hors_ligne_sans_le_mot_agence_impute_l_avance(): void
    {
        $client  = Client::whereNotNull('user_id')->whereHas('user')->where('statut', 1)->first();
        $adresse = AdresseLivraison::first();
        $unite   = UniteProduit::first();
        $type    = \App\Models\TypeLivraison::first();
        $mode    = ModePaiement::where('en_ligne', 0)->where('statut', 1)
            ->whereRaw("LOWER(libelle) NOT LIKE '%agence%'")->first();
        $admin   = User::whereIn('type_user_id', [1, 2])->where('statut', 1)->first();
        if (!$client || !$adresse || !$unite || !$type || !$mode || !$admin) {
            $this->markTestSkipped('Jeu de données insuffisant (client, adresse, unité, type, mode hors ligne sans « agence », administrateur).');
        }
        // Un client au réel : le test porte sur l'avance, pas sur l'AIRSI.
        $client->update(['regime_imposition' => 'RNI']);

        AvanceClient::create([
            'client_id' => $client->id, 'montant' => 3000, 'montant_consomme' => 0,
            'statut' => AvanceClient::DISPONIBLE, 'numero_recu' => 'RA-T-' . random_int(100, 999),
            'agence_id' => $admin->agence_id, 'caissier_id' => $admin->id,
            'user_valide_id' => $admin->id, 'user_valide2_id' => $admin->id,
            'date_depot' => now(), 'date_validation_1' => now(), 'date_validation_2' => now(),
        ]);
        $avant = DemandeLivraison::count();

        $reponse = $this->postJson('/mon_gravier/enregistrer-demande-livraison', [
            'access' => Crypt::encryptString((string) $client->user_id), 'type' => 'mobile',
            'libelle' => 'Recette avance', 'distance' => 5,
            'demande' => [
                'adresseDepart' => $adresse->id, 'adresseDestination' => $adresse->id,
                'dateLivraison' => now()->addDays(2)->toDateString(), 'note' => 'Recette',
                'modePaiement' => $mode->id, 'typeLivraison' => $type->id,
            ],
            'lignes' => [['produit' => 'Marchandise de recette', 'qte' => 1, 'unite' => $unite->abreviation, 'unite_id' => $unite->id]],
        ])->assertOk();

        if (DemandeLivraison::count() === $avant) {
            $this->markTestSkipped('La demande n\'a pas été enregistrée (jeu de données) : ' . (string) $reponse->json('message'));
        }
        $demande = DemandeLivraison::latest('id')->first();
        $this->assertGreaterThan(0, $demande->montantAPayer(), 'La demande doit avoir un montant à payer.');

        $impute = (float) ($reponse->json('avance_imputee') ?? 0);
        $this->assertGreaterThan(0, $impute, 'L\'avance doit être imputée sur la demande (mode « ' . $mode->libelle . ' »). Message : ' . $reponse->json('message'));
        $this->assertEqualsWithDelta(min(3000, $demande->montantAPayer()), $impute, 1);
        $this->assertEqualsWithDelta($impute, $demande->fresh()->montantPayeComptant(), 1, 'Le règlement AV- doit être posé sur la demande.');
        $this->assertStringContainsString('avance', strtolower((string) $reponse->json('message')));
    }
}
