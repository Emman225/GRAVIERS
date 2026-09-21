<?php

namespace Tests\Feature;

use App\Models\Enlevement;
use App\Models\PourcentageDalakoun;
use App\Models\Produit;
use App\Models\StockProduit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * LE PRIX DE VENTE EST CALCULÉ : prix d'achat × (1 + pourcentage DALAKOUN).
 *
 * Il valait jusqu'ici le prix d'achat lui-même. L'écran d'ajout de produit
 * recopie « Prix fournisseur » dans la ligne de stock, et c'est cette ligne qui
 * faisait le prix affiché : tout produit créé par cet écran était vendu au prix
 * auquel il avait été acheté.
 *
 * Deux règles arrêtées avec la direction :
 *
 *   - la base est le prix du fournisseur LE PLUS CHER. Sur les graviers et
 *     sables, l'écart entre fournisseurs est systématique — 7 %. En partant du
 *     moins-disant, un bon parti chez l'autre ramenait une marge de 20 % à
 *     12 %, sans que rien ne le signale ;
 *   - un produit peut DÉROGER au taux général par un champ qui lui est propre.
 */
class PrixCalculeAvecPourcentageTest extends TestCase
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

    /** Met un taux général en vigueur, doublement validé. */
    private function tauxEnVigueur(float $taux): PourcentageDalakoun
    {
        $admins = User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN])
            ->where('statut', \Help::$STATUT_ACTIF)->limit(2)->get();

        if ($admins->count() < 2) {
            $this->markTestSkipped('Deux administrateurs actifs sont nécessaires.');
        }

        return PourcentageDalakoun::create([
            'taux'              => $taux,
            'motif'             => 'Recette ' . uniqid(),
            'user_valide_id'    => $admins[0]->id,
            'user_valide2_id'   => $admins[1]->id,
            'date_validation_1' => now(),
            'date_validation_2' => now(),
            'statut'            => PourcentageDalakoun::APPLIQUE,
        ]);
    }

    /** Un produit et ses prix d'achat, un par fournisseur. */
    private function unProduit(array $prixFournisseurs, ?float $derogation = null): Produit
    {
        $modele = Produit::first();
        $bons   = Enlevement::whereNotNull('fournisseur_id')->get()
            ->pluck('fournisseur_id')->unique()->values();

        if (!$modele || $bons->count() < count($prixFournisseurs)) {
            $this->markTestSkipped('Pas assez de fournisseurs distincts.');
        }

        $produit = Produit::create([
            'nom'                  => 'Produit calcul ' . uniqid(),
            'description'          => 'Produit créé pour la recette.',
            'unite_produit_id'     => $modele->unite_produit_id,
            'type_affaire'         => 'VENTE',
            'prix_moyen'           => 1,
            'pourcentage_dalakoun' => $derogation,
            'statut'               => \Help::$STATUT_ACTIF,
        ]);

        foreach ($prixFournisseurs as $i => $prix) {
            StockProduit::create([
                'fournisseur_id' => $bons[$i],
                'produit_id'     => $produit->id,
                'qte'            => 50,
                'prix'           => $prix,
                'seuil_alert'    => 0,
                'statut'         => \Help::$STATUT_ACTIF,
            ]);
        }

        return $produit->fresh();
    }

    // ------------------------------------------------------- LA MARGE

    public function test_le_prix_de_vente_porte_la_marge(): void
    {
        $this->tauxEnVigueur(20);

        $produit = $this->unProduit([10000]);

        // Sans le pourcentage, l'entreprise vendait 10 000 ce qu'elle achetait
        // 10 000 : aucune marge.
        $this->assertSame(12000.0, round($produit->prixPour(null), 2));
    }

    public function test_sans_taux_en_vigueur_le_prix_reste_le_prix_d_achat(): void
    {
        PourcentageDalakoun::query()->forceDelete();

        $produit = $this->unProduit([10000]);

        // Aucune marge inventée tant que rien n'est décidé.
        $this->assertSame(10000.0, round($produit->prixPour(null), 2));
    }

    // -------------------------------------------- LE FOURNISSEUR LE PLUS CHER

    public function test_la_base_est_le_fournisseur_le_plus_cher(): void
    {
        $this->tauxEnVigueur(20);

        // 13 500 et 14 445 : l'écart de 7 % relevé sur vos graviers.
        $produit = $this->unProduit([13500, 14445]);

        $this->assertSame(14445.0, Produit::prixAchatDe($produit->id));

        // Base sur le plus cher : 14 445 × 1,20 = 17 334. La marge tient les
        // 20 % quel que soit le fournisseur qui servira.
        $this->assertSame(17334.0, round($produit->prixPour(null), 2));

        // Sur le moins-disant, le prix aurait été 16 200 et la marge serait
        // tombée à 12 % si le bon partait chez le fournisseur à 14 445.
        $this->assertGreaterThan(16200.0, $produit->prixPour(null));
    }

    public function test_un_stock_desactive_ne_fait_pas_le_prix(): void
    {
        $this->tauxEnVigueur(20);

        $produit = $this->unProduit([13500, 14445]);

        // Le fournisseur le plus cher sort du circuit : le prix redescend.
        StockProduit::where('produit_id', $produit->id)
            ->where('prix', 14445)->update(['statut' => 0]);

        $this->assertSame(13500.0, Produit::prixAchatDe($produit->id));
        $this->assertSame(16200.0, round($produit->fresh()->prixPour(null), 2));
    }

    // ----------------------------------------------------- LA DÉROGATION

    public function test_une_derogation_prime_sur_le_taux_general(): void
    {
        $this->tauxEnVigueur(20);

        $produit = $this->unProduit([10000], derogation: 5);

        // Produit d'appel : 5 % au lieu de 20.
        $this->assertSame(5.0, $produit->tauxDalakoun());
        $this->assertSame(10500.0, round($produit->prixPour(null), 2));
    }

    public function test_une_derogation_a_zero_est_une_decision(): void
    {
        $this->tauxEnVigueur(20);

        $produit = $this->unProduit([10000], derogation: 0);

        // Zéro n'est pas « pas de dérogation » : c'est « vendu au prix d'achat ».
        $this->assertSame(0.0, $produit->tauxDalakoun());
        $this->assertSame(10000.0, round($produit->prixPour(null), 2));
    }

    public function test_sans_derogation_le_taux_general_s_applique(): void
    {
        $this->tauxEnVigueur(20);

        $produit = $this->unProduit([10000]);

        $this->assertNull($produit->pourcentage_dalakoun);
        $this->assertSame(20.0, $produit->tauxDalakoun());
    }

    // ------------------------------------------------------ LE CATALOGUE

    public function test_le_prix_affiche_est_celui_du_panier(): void
    {
        $this->tauxEnVigueur(20);

        $produit = $this->unProduit([13500, 14445]);

        $liste = collect([$produit]);
        Produit::alignerPrixAffiche($liste);

        // C'est tout l'objet : ce que le client voit est ce qu'il paiera.
        $this->assertSame(
            round($produit->prixPour(null), 2),
            round((float) $liste->first()->prix_moyen, 2)
        );
    }

    public function test_le_catalogue_respecte_aussi_les_derogations(): void
    {
        $this->tauxEnVigueur(20);

        $general   = $this->unProduit([10000]);
        $derogeant = $this->unProduit([10000], derogation: 5);

        $liste = collect([$general, $derogeant]);
        Produit::alignerPrixAffiche($liste);

        $this->assertSame(12000.0, round((float) $liste[0]->prix_moyen, 2));
        $this->assertSame(10500.0, round((float) $liste[1]->prix_moyen, 2));
    }

    // ----------------------------------------------- LE PRIX PERSONNALISÉ

    public function test_le_prix_negocie_d_un_client_prime_sur_tout(): void
    {
        $this->tauxEnVigueur(20);

        $produit = $this->unProduit([10000]);

        $client = \App\Models\Client::create([
            'user_id'     => $this->unAdmin()->id,
            'nom'         => 'Negocie',
            'prenom'      => 'Recette',
            'email'       => 'negocie-' . uniqid() . '@example.test',
            'contact1'    => '0799999999',
            'type_client' => 'ENTREPRISE',
            'statut'      => \Help::$STATUT_ACTIF,
        ]);

        \App\Models\PrixPersonnalise::create([
            'client_id'  => $client->id,
            'produit_id' => $produit->id,
            'prix'       => 9000,
        ]);

        // Un tarif négocié reste un engagement : il passe avant le calcul.
        $this->assertSame(9000.0, round($produit->prixPour($client), 2));
        $this->assertSame(12000.0, round($produit->prixPour(null), 2));
    }

    // ------------------------------------------------- CE QUE LES ÉCRANS FONT

    /**
     * Le vrai piège : la règle peut être juste et un écran ne pas s'en servir.
     *
     * L'accueil de la boutique gardait sa propre copie du calcul — fournisseur
     * le moins cher, sans marge — qui écrasait le prix calculé. Après la mise en
     * service du pourcentage, il affichait encore 100 F un gravier vendu 14 850.
     * Tester alignerPrixAffiche() ne suffisait pas : il fallait vérifier que
     * l'écran l'appelle.
     */
    public function test_l_accueil_affiche_le_prix_calcule(): void
    {
        $this->tauxEnVigueur(20);
        $this->unProduit([13500, 14445]);

        \Illuminate\Support\Facades\URL::forceRootUrl('');
        $reponse = $this->get('/');
        $reponse->assertOk();

        $verifies = 0;

        // Un paginateur ne se parcourt pas comme une collection : collect() en
        // rendrait la representation (data, total...), pas ses elements.
        $listeAffichee = $reponse->viewData('produits');
        $listeAffichee = $listeAffichee instanceof \Illuminate\Pagination\AbstractPaginator
            ? $listeAffichee->getCollection()
            : collect($listeAffichee);

        foreach ($listeAffichee as $affiche) {
            $attendu = Produit::prixAchatDe($affiche->id);

            if ($attendu === null) {
                continue;
            }

            $produit = Produit::find($affiche->id);

            $this->assertSame(
                round($produit->prixPour(null), 2),
                round((float) $affiche->prix_moyen, 2),
                "Le prix affiche de « {$affiche->nom} » n'est pas celui du panier."
            );

            $verifies++;
        }

        $this->assertGreaterThan(0, $verifies, 'Aucun produit tarife sur la page : le test ne prouverait rien.');
    }

    public function test_le_devis_reprend_le_prix_calcule(): void
    {
        $this->tauxEnVigueur(20);

        $produit = $this->unProduit([13500, 14445]);

        // Le devis chargeait le fournisseur le moins cher et sans marge : il
        // proposait donc un prix que la commande ne confirmait pas.
        $prix = Produit::prixVenteParProduit([$produit->id]);

        $this->assertSame(
            round($produit->prixPour(null), 2),
            round((float) $prix[$produit->id], 2)
        );
    }

    public function test_la_table_des_prix_respecte_les_derogations(): void
    {
        $this->tauxEnVigueur(20);

        $general   = $this->unProduit([10000]);
        $derogeant = $this->unProduit([10000], derogation: 5);

        $prix = Produit::prixVenteParProduit([$general->id, $derogeant->id]);

        $this->assertSame(12000.0, round((float) $prix[$general->id], 2));
        $this->assertSame(10500.0, round((float) $prix[$derogeant->id], 2));
    }
}
