<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DetailDevis;
use App\Models\Devis;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Le total d'un devis se calcule sur ses LIGNES, pas sur la colonne montant_ht.
 *
 * Constaté en production sur le devis 687789 : montant_ht portait 10 000 pour
 * une ligne unique de 50 × 100 = 5 000. Le client lisait 14 900 F sur le devis
 * et 9 900 F sur la commande qui en découlait.
 */
class TotalDevisTest extends TestCase
{
    use DatabaseTransactions;

    private function devisAvecLigne(float $qte, float $prix, float $montantHtEnBase): Devis
    {
        $user = User::where('type_user_id', \Help::$USER_CLIENT)->first();
        $client = Client::where('user_id', $user?->id)->first()
            ?? Client::factory()->create(['user_id' => $user->id]);
        $produit = Produit::factory()->create(['type_affaire' => \Help::$VENTE]);

        $devis = Devis::factory()->create([
            'client_id'      => $client->id,
            'montant'        => $qte * $prix,
            'montant_ht'     => $montantHtEnBase,
            'tva'            => 900,
            'cout_livraison' => 4000,
            'cout_reduction' => 0,
        ]);

        DetailDevis::factory()->create([
            'devis_id'   => $devis->id,
            'produit_id' => $produit->id,
            'qte'        => $qte,
            'prix'       => $prix,
        ]);

        return $devis->fresh();
    }

    public function test_le_total_suit_les_lignes_et_non_la_colonne(): void
    {
        // La colonne annonce le double de ce que valent les lignes.
        $devis = $this->devisAvecLigne(qte: 50, prix: 100, montantHtEnBase: 10000);

        $this->assertEqualsWithDelta(5000, $devis->montantHT(), 0.01,
            'Le HT doit venir des lignes : 50 × 100.');

        // 5 000 + 900 de TVA + 4 000 de livraison = 9 900, comme la commande.
        $this->assertEqualsWithDelta(9900, $devis->montantAPayer(), 0.01);
    }

    public function test_un_devis_sans_ligne_retombe_sur_la_colonne(): void
    {
        $user = User::where('type_user_id', \Help::$USER_CLIENT)->first();
        $client = Client::where('user_id', $user?->id)->first()
            ?? Client::factory()->create(['user_id' => $user->id]);

        $devis = Devis::factory()->create([
            'client_id'      => $client->id,
            'montant'        => 0,
            'montant_ht'     => 7000,
            'tva'            => 0,
            'cout_livraison' => 0,
            'cout_reduction' => 0,
        ]);

        $this->assertEqualsWithDelta(7000, $devis->fresh()->montantAPayer(), 0.01);
    }
}
