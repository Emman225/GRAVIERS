<?php

namespace Tests\Feature;

use App\Models\Enlevement;
use App\Models\Produit;
use App\Models\StockProduit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Le réapprovisionnement, sur l'écran qui porte son nom.
 *
 * L'écran s'appelle « Livraison et réapprovisionnement » et ne contenait rien
 * de la seconde moitié : ni stock, ni seuil d'alerte. Le seuil est pourtant
 * saisi sur chaque ligne de stock, et la quasi-totalité des lignes actives en
 * porte un.
 */
class ReapprovisionnementTest extends TestCase
{
    use DatabaseTransactions;

    private function unAdmin(): User
    {
        $admin = User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN])
            ->where('statut', \Help::$STATUT_ACTIF)->first();

        if (!$admin) {
            $this->markTestSkipped('Aucun administrateur actif.');
        }

        return $admin;
    }

    private function ecran(): \Illuminate\Testing\TestResponse
    {
        URL::forceRootUrl('');
        $reponse = $this->actingAs($this->unAdmin())->get('/reapprovisionnement');
        $reponse->assertOk();

        return $reponse;
    }

    /** Une ligne de stock sur un produit actif, avec le niveau voulu. */
    private function uneLigneDeStock(float $qte, float $seuil, bool $produitActif = true): StockProduit
    {
        $bon = Enlevement::whereNotNull('fournisseur_id')->first();

        if (!$bon) {
            $this->markTestSkipped('Aucun bon rattaché à un fournisseur.');
        }

        $modele = Produit::first();

        if (!$modele) {
            $this->markTestSkipped('Aucun produit pour servir de gabarit.');
        }

        $produit = Produit::create([
            'nom'              => 'Produit recette ' . uniqid(),
            'description'      => 'Produit créé pour la recette.',
            'unite_produit_id' => $modele->unite_produit_id,
            'type_affaire'     => $modele->type_affaire ?: 'VENTE',
            'statut'           => $produitActif ? \Help::$STATUT_ACTIF : 0,
        ]);

        return StockProduit::create([
            'fournisseur_id' => $bon->fournisseur_id,
            'produit_id'     => $produit->id,
            'qte'            => $qte,
            'prix'           => 1000,
            'seuil_alert'    => $seuil,
            'statut'         => \Help::$STATUT_ACTIF,
        ]);
    }

    private function idsAlertes(): array
    {
        return collect($this->ecran()->viewData('aReapprovisionner'))->pluck('id')->all();
    }

    public function test_un_produit_sous_son_seuil_est_signale(): void
    {
        $ligne = $this->uneLigneDeStock(qte: 5, seuil: 20);

        $this->assertContains($ligne->id, $this->idsAlertes());
        $this->assertSame(15.0, round($ligne->manquePourAtteindreLeSeuil(), 2));
        $this->assertFalse($ligne->estEnRupture());
    }

    public function test_une_rupture_est_distinguee_du_simple_seuil(): void
    {
        $rupture = $this->uneLigneDeStock(qte: 0, seuil: 20);
        $sousSeuil = $this->uneLigneDeStock(qte: 5, seuil: 20);

        $reponse = $this->ecran();

        $ids = collect($reponse->viewData('aReapprovisionner'))->pluck('id')->all();

        $this->assertContains($rupture->id, $ids);
        $this->assertContains($sousSeuil->id, $ids);

        $this->assertTrue($rupture->estEnRupture());
        $this->assertGreaterThanOrEqual(1, (int) $reponse->viewData('nbRuptures'));

        // Les ruptures passent devant : c'est l'ordre dans lequel on commande.
        $position = array_search($rupture->id, $ids, true);
        $this->assertLessThan(array_search($sousSeuil->id, $ids, true), $position);
    }

    public function test_un_produit_bien_approvisionne_n_est_pas_signale(): void
    {
        $ligne = $this->uneLigneDeStock(qte: 500, seuil: 20);

        $this->assertNotContains($ligne->id, $this->idsAlertes());
    }

    public function test_un_produit_retire_du_catalogue_ne_se_reapprovisionne_pas(): void
    {
        $ligne = $this->uneLigneDeStock(qte: 0, seuil: 20, produitActif: false);

        // Son stock n'est plus vendable : l'alerter n'apprendrait rien.
        $this->assertNotContains($ligne->id, $this->idsAlertes());
    }

    public function test_une_ligne_de_stock_desactivee_ne_declenche_rien(): void
    {
        $ligne = $this->uneLigneDeStock(qte: 0, seuil: 20);

        $this->assertContains($ligne->id, $this->idsAlertes());

        $ligne->update(['statut' => 0]);

        $this->assertNotContains($ligne->id, $this->idsAlertes());
    }

    public function test_l_ecran_repond_quand_tout_est_approvisionne(): void
    {
        $reponse = $this->ecran();

        if (count($reponse->viewData('aReapprovisionner')) === 0) {
            $reponse->assertSee("Aucun produit sous son seuil d'alerte.", false);
        }

        $this->assertIsInt((int) $reponse->viewData('nbRuptures'));
    }
}
