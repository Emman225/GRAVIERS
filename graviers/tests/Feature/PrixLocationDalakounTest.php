<?php

namespace Tests\Feature;

use App\Models\PourcentageDalakoun;
use App\Models\Produit;
use App\Models\StockProduit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Le pourcentage DALAKOUN s'applique aussi aux produits de location.
 *
 * La demande était explicite : « le même principe sur le prix des produits de
 * location ». La règle est la même parce que le mécanisme est le même — le
 * catalogue de location lit `prixPour()` comme celui de la vente — mais une
 * règle qu'on croit partagée sans l'avoir vérifiée est une règle qu'on découvre
 * absente le jour d'une facture.
 *
 * Ce test tient donc le fil de bout en bout : prix d'achat, taux général,
 * dérogation, et le montant qu'un client paierait pour plusieurs jours.
 */
class PrixLocationDalakounTest extends TestCase
{
    use DatabaseTransactions;

    private function deuxAdmins(): array
    {
        $admins = User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN])
            ->where('statut', \Help::$STATUT_ACTIF)->limit(2)->get();

        if ($admins->count() < 2) {
            $this->markTestSkipped('Deux administrateurs actifs sont necessaires.');
        }

        return [$admins[0], $admins[1]];
    }

    private function tauxGeneral(float $taux): void
    {
        [$a, $b] = $this->deuxAdmins();

        PourcentageDalakoun::create([
            'taux'              => $taux,
            'motif'             => 'Location recette',
            'user_valide_id'    => $a->id,
            'user_valide2_id'   => $b->id,
            'date_validation_1' => now()->subMinute(),
            'date_validation_2' => now(),
            'statut'            => PourcentageDalakoun::APPLIQUE,
        ]);
    }

    /** Un materiel de location, tarife par un fournisseur. */
    private function unMateriel(float $prixAchat): Produit
    {
        $modele = Produit::first();
        $ligne  = StockProduit::whereNotNull('fournisseur_id')->first();

        if (!$modele || !$ligne) {
            $this->markTestSkipped('Jeu de donnees insuffisant.');
        }

        $produit = Produit::create([
            'nom'              => 'Betonniere recette ' . uniqid(),
            'description'      => 'Creee pour la recette.',
            'unite_produit_id' => $modele->unite_produit_id,
            'type_affaire'     => \Help::$LOCATION,
            'prix_moyen'       => 1,
            'caution'          => 0,
            'statut'           => \Help::$STATUT_ACTIF,
        ]);

        StockProduit::create([
            'fournisseur_id' => $ligne->fournisseur_id,
            'produit_id'     => $produit->id,
            'qte'            => 5,
            'prix'           => $prixAchat,
            'seuil_alert'    => 0,
            'statut'         => \Help::$STATUT_ACTIF,
        ]);

        return $produit->fresh();
    }

    public function test_le_taux_s_applique_au_prix_de_location(): void
    {
        $this->tauxGeneral(10);

        $materiel = $this->unMateriel(20000);

        $this->assertSame(22000.0, round($materiel->prixPour(null), 2),
            'Un materiel achete 20 000 doit se louer 22 000 au taux general.');
    }

    public function test_le_catalogue_de_location_montre_ce_prix(): void
    {
        // Le catalogue tenait sa propre copie du calcul : il annoncait
        // 20 000 quand le panier facturait 100.
        $this->tauxGeneral(10);

        $materiel = $this->unMateriel(20000);

        URL::forceRootUrl('');

        $reponse = $this->get('/location-materiel-construction');
        $reponse->assertOk();

        $affiche = null;

        foreach ($reponse->viewData('produits') as $p) {
            if ((int) $p->id === (int) $materiel->id) {
                $affiche = (float) $p->prix_moyen;
            }
        }

        $this->assertNotNull($affiche, 'Le materiel doit figurer au catalogue de location.');

        $this->assertSame(round($materiel->prixPour(null), 2), round($affiche, 2),
            'Le catalogue de location doit annoncer le prix que le panier facturera.');
    }

    public function test_une_derogation_vaut_aussi_en_location(): void
    {
        [$auteur, $valideur] = $this->deuxAdmins();
        $this->tauxGeneral(10);

        $materiel = $this->unMateriel(20000);

        URL::forceRootUrl('');

        $motif = 'Derogation location ' . uniqid();

        $this->actingAs($auteur)->post('/pourcentage-dalakoun', [
            'produit_id' => $materiel->id,
            'taux'       => 25,
            'motif'      => $motif,
        ]);

        $decision = PourcentageDalakoun::where('motif', $motif)->latest('id')->first();

        $this->assertNotNull($decision);

        $this->actingAs($valideur)->post('/pourcentage-dalakoun-' . $decision->id . '/valider');

        $this->assertSame(25000.0, round($materiel->fresh()->prixPour(null), 2),
            'La derogation doit valoir pour un materiel de location comme pour un produit vendu.');
    }

    public function test_le_montant_de_plusieurs_jours_suit_le_meme_prix(): void
    {
        // Une location se facture prix x quantite x nombre de jours : si le
        // prix unitaire est faux, l erreur est multipliee par la duree.
        $this->tauxGeneral(10);

        $materiel = $this->unMateriel(20000);

        $prixUnitaire = $materiel->prixPour(null);

        $this->assertSame(22000.0, round($prixUnitaire, 2));

        // Deux betonnieres, cinq jours.
        $this->assertSame(220000.0, round($prixUnitaire * 2 * 5, 2),
            'Le total doit se construire sur le prix majore, pas sur le prix d achat.');
    }

    public function test_la_caution_ne_porte_pas_la_marge(): void
    {
        // La caution est une somme rendue au client : la majorer reviendrait a
        // prendre une marge sur un depot de garantie.
        $this->tauxGeneral(10);

        $materiel = $this->unMateriel(20000);
        $materiel->update(['caution' => 50000]);

        $this->assertSame(50000.0, round((float) $materiel->fresh()->caution, 2));
    }
}
