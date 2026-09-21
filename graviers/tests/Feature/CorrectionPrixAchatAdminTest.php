<?php

namespace Tests\Feature;

use App\Models\ImageProduit;
use App\Models\Produit;
use App\Models\StockProduit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Un administrateur peut corriger un prix d'achat.
 *
 * Le garde-fou de cohérence signalait deux prix d'achat aberrants — une barre
 * de fer à 50 000 au lieu de 4 800 — et il fallait bien les corriger quelque
 * part. Or une seule vue de toute l'application écrivait `stock_produit.prix` :
 * l'espace du fournisseur, qui résout le fournisseur depuis l'utilisateur
 * CONNECTÉ. Un administrateur n'y trouvait aucune ligne à modifier.
 *
 * Le prix d'achat fait désormais le prix de vente : le laisser hors d'atteinte
 * revenait à laisser le catalogue faux sans recours.
 */
class CorrectionPrixAchatAdminTest extends TestCase
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

    /** Un produit tarifé par un fournisseur, prêt à être modifié par l'écran. */
    private function unProduitTarife(float $prixAchat): Produit
    {
        $modele = Produit::whereHas('categories')->first();
        $ligne  = StockProduit::whereNotNull('fournisseur_id')->first();

        if (!$modele || !$ligne) {
            $this->markTestSkipped('Jeu de donnees insuffisant.');
        }

        $produit = Produit::create([
            'nom'              => 'Produit recette ' . uniqid(),
            'description'      => 'Cree pour la recette.',
            'unite_produit_id' => $modele->unite_produit_id,
            'type_affaire'     => \Help::$VENTE,
            'prix_moyen'       => 1,
            'statut'           => \Help::$STATUT_ACTIF,
        ]);

        $produit->categories()->attach($modele->categories->first()->id);

        // L'ecran relit l'image du produit avant toute chose.
        ImageProduit::create(['produit_id' => $produit->id, 'image' => 'recette.png']);

        StockProduit::create([
            'fournisseur_id' => $ligne->fournisseur_id,
            'produit_id'     => $produit->id,
            'qte'            => 10,
            'prix'           => $prixAchat,
            'seuil_alert'    => 0,
            'statut'         => \Help::$STATUT_ACTIF,
        ]);

        return $produit->fresh();
    }

    /** Les champs que l'ecran envoie, hors prix d'achat. */
    private function champs(Produit $produit): array
    {
        return [
            'reference'     => $produit->reference,
            'nom'           => $produit->nom,
            'abreviation'   => $produit->abreviation,
            'unite'         => $produit->unite_produit_id,
            'description'   => $produit->description,
            'type_affaire'  => 2,
            'categories'    => $produit->categories->pluck('id')->all(),
            'reduction'     => $produit->prix_reduction ?? 0,
            'meilleur_note' => $produit->meilleur_note ?? 0,
            'caution'       => $produit->caution ?? 0,
        ];
    }

    private function enregistrer(Produit $produit, array $donnees): \Illuminate\Testing\TestResponse
    {
        URL::forceRootUrl('');

        return $this->actingAs($this->unAdmin())
            ->post('/products-edit/' . $produit->id, array_merge($this->champs($produit), $donnees));
    }

    public function test_l_administrateur_corrige_un_prix_d_achat(): void
    {
        $produit = $this->unProduitTarife(50000);
        $ligne   = StockProduit::where('produit_id', $produit->id)->first();

        $reponse = $this->enregistrer($produit, [
            'prix_achat' => [$ligne->id => 4800],
        ]);

        $reponse->assertRedirect();

        $this->assertSame(4800.0, round((float) $ligne->fresh()->prix, 2),
            'Le prix d\'achat corrige doit etre enregistre.');
    }

    public function test_le_prix_de_vente_suit_la_correction(): void
    {
        // Toute la raison d'etre de l'ecran : corriger l'achat corrige la vente.
        $produit = $this->unProduitTarife(50000);
        $ligne   = StockProduit::where('produit_id', $produit->id)->first();

        $this->enregistrer($produit, ['prix_achat' => [$ligne->id => 4800]]);

        $attendu = round(\App\Models\PourcentageDalakoun::appliquerA(4800.0, $produit->pourcentage_dalakoun));

        $this->assertSame($attendu, round((float) $produit->fresh()->prix_moyen),
            'Le prix catalogue doit etre recalcule sur le prix d\'achat corrige.');
    }

    public function test_le_plus_cher_des_fournisseurs_fait_le_prix(): void
    {
        $produit = $this->unProduitTarife(9000);
        $ligne   = StockProduit::where('produit_id', $produit->id)->first();

        $autre = \App\Models\Fournisseur::where('id', '!=', $ligne->fournisseur_id)->first();

        if (!$autre) {
            $this->markTestSkipped('Un seul fournisseur en base.');
        }

        $seconde = StockProduit::create([
            'fournisseur_id' => $autre->id,
            'produit_id'     => $produit->id,
            'qte'            => 5,
            'prix'           => 8000,
            'seuil_alert'    => 0,
            'statut'         => \Help::$STATUT_ACTIF,
        ]);

        $this->enregistrer($produit, [
            'prix_achat' => [$ligne->id => 9500, $seconde->id => 12000],
        ]);

        $attendu = round(\App\Models\PourcentageDalakoun::appliquerA(12000.0, $produit->pourcentage_dalakoun));

        $this->assertSame($attendu, round((float) $produit->fresh()->prix_moyen),
            'Le prix de vente suit le fournisseur le plus cher.');
    }

    public function test_un_identifiant_etranger_au_produit_est_ignore(): void
    {
        // Les identifiants de ligne voyagent dans le formulaire : un champ
        // renomme ne doit pas permettre d'ecrire le prix d'un autre produit.
        $produit = $this->unProduitTarife(9000);
        $voisin  = $this->unProduitTarife(7000);

        $ligneVoisine = StockProduit::where('produit_id', $voisin->id)->first();
        $avant        = (float) $ligneVoisine->prix;

        $this->enregistrer($produit, [
            'prix_achat' => [$ligneVoisine->id => 1],
        ]);

        $this->assertSame(round($avant, 2), round((float) $ligneVoisine->fresh()->prix, 2),
            'Une ligne d\'un autre produit ne doit pas etre modifiee.');
    }

    public function test_un_prix_negatif_est_refuse(): void
    {
        $produit = $this->unProduitTarife(9000);
        $ligne   = StockProduit::where('produit_id', $produit->id)->first();

        $reponse = $this->enregistrer($produit, ['prix_achat' => [$ligne->id => -5]]);

        $reponse->assertSessionHasErrors();

        $this->assertSame(9000.0, round((float) $ligne->fresh()->prix, 2),
            'Un prix negatif ne doit rien ecrire.');
    }

    public function test_un_enregistrement_sans_prix_d_achat_ne_casse_rien(): void
    {
        // L'ecran sert aussi a corriger un libelle : rien ne doit bouger cote prix.
        $produit = $this->unProduitTarife(9000);
        $ligne   = StockProduit::where('produit_id', $produit->id)->first();

        $this->enregistrer($produit, [])->assertRedirect();

        $this->assertSame(9000.0, round((float) $ligne->fresh()->prix, 2));
    }

    public function test_l_ecran_montre_une_ligne_par_fournisseur(): void
    {
        // Sans ce passage par la vue, une faute de Blade ne se verrait qu en
        // production : les tests precedents ne font que poster.
        $produit = $this->unProduitTarife(9000);
        $ligne   = StockProduit::where('produit_id', $produit->id)->first();

        URL::forceRootUrl('');

        $reponse = $this->actingAs($this->unAdmin())->get('/products-edit/' . $produit->id);

        $reponse->assertOk();
        $reponse->assertSee("achat par fournisseur", false);
        $reponse->assertSee('prix_achat[' . $ligne->id . ']', false);
        $reponse->assertSee($ligne->fournisseur->nom_prenoms, false);

        // Le champ calcule ne sert a rien sans le script qui le remplit.
        $reponse->assertSee('plusCher', false);

        // ... et le script doit venir APRES les champs qu il ecoute, sinon il
        // ne trouve rien et le prix calcule reste vide.
        $html = $reponse->getContent();

        $this->assertLessThan(
            strpos($html, 'plusCher'),
            strpos($html, 'prix-achat'),
            'Le script est place avant les champs : il ne les voit pas.'
        );

        $this->assertLessThan(
            strpos($html, 'plusCher'),
            strpos($html, 'prixDalakoun'),
            'Le script est place avant le champ calcule.'
        );
    }

    /** Un second fournisseur sur le meme produit, au prix voulu. */
    private function unSecondFournisseur(Produit $produit, float $prix): ?StockProduit
    {
        $premier = StockProduit::where('produit_id', $produit->id)->first();

        $autre = \App\Models\Fournisseur::where('id', '!=', $premier->fournisseur_id)->first();

        if (!$autre) {
            return null;
        }

        return StockProduit::create([
            'fournisseur_id' => $autre->id,
            'produit_id'     => $produit->id,
            'qte'            => 5,
            'prix'           => $prix,
            'seuil_alert'    => 0,
            'statut'         => \Help::$STATUT_ACTIF,
        ]);
    }

    public function test_un_fournisseur_retire_ne_fait_plus_le_prix(): void
    {
        // Le cas reel : un fournisseur rattache par erreur, au prix aberrant,
        // qui tirait tout le catalogue vers le haut.
        $produit = $this->unProduitTarife(25000);
        $juste   = $this->unSecondFournisseur($produit, 7500);

        if (!$juste) {
            $this->markTestSkipped('Un seul fournisseur en base.');
        }

        $aberrant = StockProduit::where('produit_id', $produit->id)
            ->where('id', '!=', $juste->id)->first();

        $this->enregistrer($produit, [
            'fournisseur_retire' => [$aberrant->id => 1],
        ])->assertRedirect();

        $this->assertSame((int) \Help::$STATUT_INACTIF, (int) $aberrant->fresh()->statut,
            'La ligne cochee doit etre desactivee.');

        $attendu = round(\App\Models\PourcentageDalakoun::appliquerA(7500.0, $produit->pourcentage_dalakoun));

        $this->assertSame($attendu, round((float) $produit->fresh()->prix_moyen),
            'Le prix de vente doit suivre le fournisseur restant.');
    }

    public function test_un_fournisseur_retire_peut_revenir(): void
    {
        // Une erreur de clic doit se defaire depuis le meme ecran : sans cela,
        // le detachement serait sans retour.
        $produit = $this->unProduitTarife(9000);
        $second  = $this->unSecondFournisseur($produit, 8000);

        if (!$second) {
            $this->markTestSkipped('Un seul fournisseur en base.');
        }

        $this->enregistrer($produit, ['fournisseur_retire' => [$second->id => 1]]);

        $this->assertSame((int) \Help::$STATUT_INACTIF, (int) $second->fresh()->statut);

        // Case decochee : rien dans la requete.
        $this->enregistrer($produit, [])->assertRedirect();

        $this->assertSame((int) \Help::$STATUT_ACTIF, (int) $second->fresh()->statut,
            'Decochee, la ligne doit redevenir active.');
    }

    public function test_l_ecran_montre_encore_un_fournisseur_retire(): void
    {
        $produit = $this->unProduitTarife(9000);
        $second  = $this->unSecondFournisseur($produit, 8000);

        if (!$second) {
            $this->markTestSkipped('Un seul fournisseur en base.');
        }

        $this->enregistrer($produit, ['fournisseur_retire' => [$second->id => 1]]);

        \Illuminate\Support\Facades\URL::forceRootUrl('');

        $reponse = $this->actingAs($this->unAdmin())->get('/products-edit/' . $produit->id);

        $reponse->assertOk();
        $reponse->assertSee('fournisseur_retire[' . $second->id . ']', false);
    }

    public function test_on_ne_retire_pas_tous_les_fournisseurs(): void
    {
        // Sans fournisseur, le prix de vente ne se recalcule plus : le catalogue
        // garderait un montant que plus rien ne justifie.
        $produit = $this->unProduitTarife(9000);
        $lignes  = StockProduit::where('produit_id', $produit->id)->pluck('id');

        $reponse = $this->enregistrer($produit, [
            'fournisseur_retire' => $lignes->mapWithKeys(fn ($id) => [$id => 1])->all(),
        ]);

        $reponse->assertSessionHas('produit_erreur');

        foreach ($lignes as $id) {
            $this->assertSame((int) \Help::$STATUT_ACTIF, (int) StockProduit::find($id)->statut,
                'Aucune ligne ne doit avoir ete desactivee.');
        }
    }
}
