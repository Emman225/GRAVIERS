<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Commande;
use App\Models\DetailCommande;
use App\Models\Enlevement;
use App\Models\Fournisseur;
use App\Models\Livraison;
use App\Models\Produit;
use App\Models\TvaCommande;
use App\Models\TypeUser;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * « RESTE À ENLEVER » NE COMPTE QUE LA MARCHANDISE (10/09/2026).
 *
 * Un client qui avait tout réglé et tout enlevé lisait « 720 FCFA reste à
 * enlever » sur Mon compte : la TVA du transport (18 % de 4 000 F), que
 * montantAPayer() comptait et totalEnleveSurCommande() non.
 */
class ResteAEnleverSansLesTaxesTest extends TestCase
{
    use DatabaseTransactions;

    private function commandeLivree(?float $qteServie, bool $validee = true): array
    {
        foreach ([[\Help::$USER_CLIENT, 'Client']] as [$id, $nom]) {
            TypeUser::firstOrCreate(['id' => $id], ['nom' => $nom, 'statut' => 1]);
        }
        $compte  = User::factory()->create(['type_user_id' => \Help::$USER_CLIENT, 'statut' => \Help::$STATUT_ACTIF]);
        $client  = Client::factory()->create(['user_id' => $compte->id]);
        $produit = Produit::factory()->create();

        $commande = Commande::factory()->create([
            'client_id'             => $client->id,
            'devis_id'              => null,
            'adresse_livraison_id'  => null,
            'mode_paiement_id'      => null,
            'type_livraison_id'     => null,
            'etat_commande'         => \Help::$COMMANDE_TERMINE,
            'montant_total'         => 100000,
            'remise'                => 0,
            'cout_livraison_client' => 4000,
            'tva_transport'         => 720,
            'airsi'                 => 5900,
        ]);
        TvaCommande::create(['client_id' => $client->id, 'commande_id' => $commande->id, 'montant' => 18000, 'type_affaire' => \Help::$VENTE]);
        $detail = DetailCommande::factory()->create(['commande_id' => $commande->id, 'produit_id' => $produit->id, 'qte' => 1, 'prix' => 100000]);

        // Fixtures explicites : les fabriques tirent leurs clés étrangères au hasard.
        $livraison = new Livraison();
        $livraison->forceFill([
            'numero'             => 'LIV-' . uniqid(),
            'client_id'          => $client->id,
            'detail_commande_id' => $detail->id,
            'provenance'         => \Help::$COMMANDE,
            'date_livraison'     => now()->toDateString(),
            'qte'                => 1,
            'etat_livraison'     => \Help::$LIVRAISON_LIVREE,
            'statut'             => \Help::$STATUT_ACTIF,
        ])->save();

        $fournisseur = Fournisseur::first();
        if (!$fournisseur) {
            $this->markTestSkipped('Aucun fournisseur en base.');
        }
        $bon = new Enlevement();
        $bon->forceFill([
            'fournisseur_id'         => $fournisseur->id,
            'livraison_id'           => $livraison->id,
            'produit_id'             => $produit->id,
            'qte'                    => 1,
            'code_enleve'            => 'BON-' . uniqid(),
            'qte_servi'              => $qteServie,
            'fournisseur_validation' => $validee ? now() : null,
            'statut'                 => \Help::$STATUT_ACTIF,
        ])->save();

        return [$commande->fresh(), $compte];
    }

    public function test_une_commande_entierement_enlevee_n_a_plus_rien_a_enlever(): void
    {
        [$commande, $compte] = $this->commandeLivree(1.0);

        // Le net à payer porte bien les taxes : 100 000 + 18 000 + 4 000 + 720 + 5 900.
        $this->assertSame(128620.0, $commande->montantAPayer());
        $this->assertSame(0.0, $commande->resteAEnlever());
        // L'ancienne soustraction tombe aussi à zéro : les deux fonctions sont de nouveau miroirs.
        $this->assertEqualsWithDelta(0, $commande->montantAPayer() - \Help::totalEnleveSurCommande($commande), 0.001);

        $html = $this->actingAs($compte)->get('/mon-compte')->assertOk()->getContent();
        $this->assertMatchesRegularExpression(
            '/tb-chiffre__valeur">0<\/div>\s*<div class="tb-chiffre__libelle">FCFA reste à enlever/',
            $html,
            'La tuile de Mon compte doit annoncer 0 F à enlever.'
        );
    }

    public function test_une_commande_servie_en_partie_garde_la_marchandise_restante(): void
    {
        [$commande] = $this->commandeLivree(0.4);

        $this->assertSame(60000.0, $commande->resteAEnlever());
    }

    public function test_un_bon_valide_sans_quantite_saisie_vaut_la_quantite_demandee(): void
    {
        [$commande] = $this->commandeLivree(null, true);
        $this->assertSame(0.0, $commande->resteAEnlever());

        [$commande] = $this->commandeLivree(null, false);
        $this->assertSame(100000.0, $commande->resteAEnlever(), 'Un bon non validé n\'a rien servi.');
    }
}
