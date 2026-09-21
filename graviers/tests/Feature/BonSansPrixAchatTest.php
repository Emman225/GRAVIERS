<?php

namespace Tests\Feature;

use App\Models\Commande;
use App\Models\Enlevement;
use App\Models\Fournisseur;
use App\Models\StockProduit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Un bon d'enlèvement sans prix d'achat vaudrait un dû de zéro.
 *
 * Depuis que le prix d'achat vient de la ligne de stock du fournisseur retenu
 * — et non plus d'un champ retapé de mémoire à chaque bon — une ligne à zéro
 * produit un bon à zéro. Le fournisseur livre, et rien ne lui est dû : la
 * comptabilité est juste, l'engagement ne l'est pas.
 *
 * Le cas n'a rien de théorique : quatre fournisseurs sont rattachés à chaque
 * produit du catalogue, dont deux à zéro sur toute la ligne.
 */
class BonSansPrixAchatTest extends TestCase
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

    /**
     * Une commande en cours, sa ligne produit, et un fournisseur tarifé comme
     * demandé pour ce produit.
     */
    private function contexte(float $prixAchat): array
    {
        $commande = Commande::whereHas('detailCommande')->latest('id')->first();

        if (!$commande) {
            $this->markTestSkipped('Aucune commande exploitable.');
        }

        $detail  = $commande->detailCommande->first();
        $produit = $detail->produit;

        $fournisseur = Fournisseur::where('statut', \Help::$STATUT_ACTIF)->first();

        if (!$produit || !$fournisseur) {
            $this->markTestSkipped('Jeu de donnees insuffisant.');
        }

        // LA VENTE DOIT COUVRIR L'ACHAT.
        //
        // Cet essai porte sur le tarif du fournisseur, pas sur la marge :
        // la ligne reprise portait un prix client de quelques centaines de
        // francs, qu'un fournisseur a 4 800 F fait vendre a perte. Le
        // garde-fou des ventes a perte le refuse — a juste titre. On donne
        // donc a la ligne un prix qui couvre l'achat.
        $detail->update(['prix' => $prixAchat * 1.5]);

        // On repart d'une ligne propre pour ce couple produit x fournisseur.
        StockProduit::where('produit_id', $produit->id)
            ->where('fournisseur_id', $fournisseur->id)->delete();

        $stock = StockProduit::create([
            'fournisseur_id' => $fournisseur->id,
            'produit_id'     => $produit->id,
            'qte'            => 10000,
            'prix'           => $prixAchat,
            'seuil_alert'    => 0,
            'statut'         => \Help::$STATUT_ACTIF,
        ]);

        return [$commande, $produit, $fournisseur, $stock];
    }

    private function traiter(array $contexte, array $donnees = []): \Illuminate\Testing\TestResponse
    {
        [$commande, $produit, $fournisseur] = $contexte;

        URL::forceRootUrl('');

        return $this->actingAs($this->unAdmin())->post(
            '/traitement/sans/livraison/' . $commande->id . '-' . $produit->id,
            array_merge([
                'fournisseur' => $fournisseur->id,
                'produit'     => $produit->id,
                'qte'         => 1,
                'date'        => today()->format('Y-m-d'),
            ], $donnees)
        );
    }

    /** Le bon éventuellement émis pour ce couple produit × fournisseur. */
    private function bonEmis(array $contexte): ?Enlevement
    {
        [, $produit, $fournisseur] = $contexte;

        return Enlevement::where('produit_id', $produit->id)
            ->where('fournisseur_id', $fournisseur->id)
            ->latest('id')
            ->first();
    }

    public function test_un_fournisseur_sans_prix_ne_recoit_pas_de_bon(): void
    {
        // On observe le bon, pas le message : Flasher consomme la cle `error`
        // pour la rejouer en toast, et la session ne la porte plus ensuite.
        $contexte = $this->contexte(0);

        $avant = $this->bonEmis($contexte)?->id;

        $this->traiter($contexte)->assertRedirect();

        $this->assertSame($avant, $this->bonEmis($contexte)?->id,
            'Aucun bon ne doit avoir ete emis pour un fournisseur sans prix.');
    }

    public function test_un_fournisseur_tarife_recoit_son_bon(): void
    {
        // Le garde-fou ne doit bloquer que le cas qu il vise.
        $contexte = $this->contexte(4800);

        $avant = $this->bonEmis($contexte)?->id;

        $this->traiter($contexte);

        $bon = $this->bonEmis($contexte);

        $this->assertNotSame($avant, $bon?->id, 'Le bon doit avoir ete emis.');

        $this->assertSame(4800.0, round((float) $bon->prix_fournisseur, 2),
            'Le bon doit porter le tarif du fournisseur retenu.');
    }

    public function test_un_prix_negocie_sur_le_bon_reste_possible(): void
    {
        // Une negociation ponctuelle garde sa place, meme si la ligne est a zero.
        $contexte = $this->contexte(0);

        $avant = $this->bonEmis($contexte)?->id;

        $this->traiter($contexte, ['prix_fournisseur' => 5200]);

        $bon = $this->bonEmis($contexte);

        $this->assertNotSame($avant, $bon?->id,
            'Un prix transmis avec le bon doit suffire a le laisser passer.');

        $this->assertSame(5200.0, round((float) $bon->prix_fournisseur, 2));
    }
}
