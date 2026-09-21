<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\PourcentageDalakoun;
use App\Models\Produit;
use App\Models\StockProduit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * La dérogation : un taux propre à un produit.
 *
 * Le taux général vaut pour tout le catalogue, mais certains produits ne
 * supportent pas la même marge — un matériau très cher à l'achat, un article
 * d'appel, une gamme plus disputée.
 *
 * Une dérogation change un prix : elle passe donc par la même double validation
 * que le taux général. Celui qui la saisit ne la valide pas.
 */
class DerogationPourcentageTest extends TestCase
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

    /** Le taux general, deja en vigueur. */
    private function tauxGeneral(float $taux): PourcentageDalakoun
    {
        [$a, $b] = $this->deuxAdmins();

        return PourcentageDalakoun::create([
            'taux'              => $taux,
            'motif'             => 'General recette',
            'user_valide_id'    => $a->id,
            'user_valide2_id'   => $b->id,
            'date_validation_1' => now()->subMinute(),
            'date_validation_2' => now(),
            'statut'            => PourcentageDalakoun::APPLIQUE,
        ]);
    }

    /** Un produit tarife, sans derogation. */
    private function unProduit(float $prixAchat): Produit
    {
        $modele = Produit::first();
        $ligne  = StockProduit::whereNotNull('fournisseur_id')->first();

        if (!$modele || !$ligne) {
            $this->markTestSkipped('Jeu de donnees insuffisant.');
        }

        $produit = Produit::create([
            'nom'              => 'Derogation recette ' . uniqid(),
            'description'      => 'Cree pour la recette.',
            'unite_produit_id' => $modele->unite_produit_id,
            'type_affaire'     => \Help::$VENTE,
            'prix_moyen'       => 1,
            'statut'           => \Help::$STATUT_ACTIF,
        ]);

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

    private function saisir(User $auteur, array $donnees): ?PourcentageDalakoun
    {
        $motif = 'Recette ' . uniqid();

        URL::forceRootUrl('');

        $this->actingAs($auteur)->post('/pourcentage-dalakoun', array_merge(['motif' => $motif], $donnees));

        return PourcentageDalakoun::where('motif', $motif)->latest('id')->first();
    }

    private function valider(User $validateur, PourcentageDalakoun $decision): void
    {
        URL::forceRootUrl('');

        $this->actingAs($validateur)->post('/pourcentage-dalakoun-' . $decision->id . '/valider');
    }

    // ------------------------------------------------------------- SAISIE

    public function test_une_derogation_en_attente_ne_change_rien(): void
    {
        [$auteur] = $this->deuxAdmins();
        $this->tauxGeneral(10);

        $produit = $this->unProduit(10000);

        $decision = $this->saisir($auteur, ['produit_id' => $produit->id, 'taux' => 30]);

        $this->assertNotNull($decision);
        $this->assertTrue($decision->attendUneSecondeValidation());

        $this->assertNull($produit->fresh()->pourcentage_dalakoun,
            'Tant que la derogation attend, le produit suit le taux general.');
    }

    public function test_une_derogation_ne_devient_pas_le_taux_general(): void
    {
        // Le piege : une derogation validee ne doit jamais etre lue comme le
        // taux de tout le catalogue.
        [$auteur, $valideur] = $this->deuxAdmins();
        $general = $this->tauxGeneral(10);

        $produit  = $this->unProduit(10000);
        $decision = $this->saisir($auteur, ['produit_id' => $produit->id, 'taux' => 30]);

        $this->valider($valideur, $decision);

        $this->assertSame((int) $general->id, (int) PourcentageDalakoun::enVigueur()?->id,
            'Le taux general doit rester celui qui ne vise aucun produit.');

        $this->assertSame(10.0, PourcentageDalakoun::tauxEnVigueur());
    }

    // --------------------------------------------------------- VALIDATION

    public function test_une_derogation_validee_refait_le_prix_du_produit(): void
    {
        [$auteur, $valideur] = $this->deuxAdmins();
        $this->tauxGeneral(10);

        $produit  = $this->unProduit(10000);
        $decision = $this->saisir($auteur, ['produit_id' => $produit->id, 'taux' => 30]);

        $this->valider($valideur, $decision);

        $produit = $produit->fresh();

        $this->assertSame(30.0, (float) $produit->pourcentage_dalakoun);
        $this->assertSame(30.0, $produit->tauxDalakoun());
        $this->assertSame(13000.0, round((float) $produit->prix_moyen, 2),
            'Le prix doit suivre la derogation, pas le taux general.');
    }

    public function test_la_derogation_ne_touche_pas_les_autres_produits(): void
    {
        [$auteur, $valideur] = $this->deuxAdmins();
        $this->tauxGeneral(10);

        $vise   = $this->unProduit(10000);
        $voisin = $this->unProduit(10000);

        $decision = $this->saisir($auteur, ['produit_id' => $vise->id, 'taux' => 30]);
        $this->valider($valideur, $decision);

        $this->assertNull($voisin->fresh()->pourcentage_dalakoun);
        $this->assertSame(10.0, $voisin->fresh()->tauxDalakoun());
    }

    public function test_une_derogation_a_zero_est_une_decision(): void
    {
        // Zero n est pas "pas de derogation" : c est le choix de vendre au prix
        // d achat, et il doit survivre a la validation.
        [$auteur, $valideur] = $this->deuxAdmins();
        $this->tauxGeneral(10);

        $produit  = $this->unProduit(10000);
        $decision = $this->saisir($auteur, ['produit_id' => $produit->id, 'taux' => 0]);

        $this->valider($valideur, $decision);

        $produit = $produit->fresh();

        $this->assertNotNull($produit->pourcentage_dalakoun);
        $this->assertSame(0.0, $produit->tauxDalakoun());
        $this->assertSame(10000.0, round((float) $produit->prix_moyen, 2));
    }

    public function test_l_auteur_ne_valide_pas_sa_propre_derogation(): void
    {
        [$auteur] = $this->deuxAdmins();
        $this->tauxGeneral(10);

        $produit  = $this->unProduit(10000);
        $decision = $this->saisir($auteur, ['produit_id' => $produit->id, 'taux' => 30]);

        $this->valider($auteur, $decision);

        $this->assertTrue($decision->fresh()->attendUneSecondeValidation());
        $this->assertNull($produit->fresh()->pourcentage_dalakoun);
    }

    // ------------------------------------------------------------ RETRAIT

    public function test_le_retrait_ramene_le_produit_au_taux_general(): void
    {
        [$auteur, $valideur] = $this->deuxAdmins();
        $this->tauxGeneral(10);

        $produit = $this->unProduit(10000);

        $pose = $this->saisir($auteur, ['produit_id' => $produit->id, 'taux' => 30]);
        $this->valider($valideur, $pose);

        $this->assertSame(13000.0, round((float) $produit->fresh()->prix_moyen, 2));

        $retrait = $this->saisir($auteur, ['produit_id' => $produit->id, 'retrait' => 1]);

        $this->assertNotNull($retrait);
        $this->assertTrue($retrait->estUnRetraitDeDerogation());

        $this->valider($valideur, $retrait);

        $produit = $produit->fresh();

        $this->assertNull($produit->pourcentage_dalakoun);
        $this->assertSame(10.0, $produit->tauxDalakoun());
        $this->assertSame(11000.0, round((float) $produit->prix_moyen, 2),
            'Le produit doit revenir au prix que dicte le taux general.');
    }

    public function test_un_retrait_attend_aussi_sa_seconde_validation(): void
    {
        [$auteur, $valideur] = $this->deuxAdmins();
        $this->tauxGeneral(10);

        $produit = $this->unProduit(10000);

        $pose = $this->saisir($auteur, ['produit_id' => $produit->id, 'taux' => 30]);
        $this->valider($valideur, $pose);

        $this->saisir($auteur, ['produit_id' => $produit->id, 'retrait' => 1]);

        $this->assertSame(30.0, (float) $produit->fresh()->pourcentage_dalakoun,
            'Tant que le retrait attend, la derogation tient.');
    }

    // ----------------------------------------------------- LES PERIMETRES

    public function test_une_derogation_en_attente_n_en_bloque_pas_une_autre(): void
    {
        // La regle du "un seul en attente" vaut par perimetre : sinon une
        // derogation oubliee sur un produit gelerait tout le catalogue.
        [$auteur] = $this->deuxAdmins();
        $this->tauxGeneral(10);

        $premier = $this->unProduit(10000);
        $second  = $this->unProduit(10000);

        $this->saisir($auteur, ['produit_id' => $premier->id, 'taux' => 30]);
        $surLeSecond = $this->saisir($auteur, ['produit_id' => $second->id, 'taux' => 25]);

        $this->assertNotNull($surLeSecond,
            'Une derogation sur un produit ne doit pas bloquer les autres.');
    }

    public function test_deux_derogations_sur_le_meme_produit_ne_s_empilent_pas(): void
    {
        [$auteur] = $this->deuxAdmins();
        $this->tauxGeneral(10);

        $produit = $this->unProduit(10000);

        $this->saisir($auteur, ['produit_id' => $produit->id, 'taux' => 30]);
        $seconde = $this->saisir($auteur, ['produit_id' => $produit->id, 'taux' => 25]);

        $this->assertNull($seconde,
            'Deux decisions concurrentes sur le meme produit doivent etre refusees.');
    }

    public function test_l_ecran_liste_les_derogations_en_vigueur(): void
    {
        [$auteur, $valideur] = $this->deuxAdmins();
        $this->tauxGeneral(10);

        $produit  = $this->unProduit(10000);
        $decision = $this->saisir($auteur, ['produit_id' => $produit->id, 'taux' => 30]);
        $this->valider($valideur, $decision);

        URL::forceRootUrl('');

        $reponse = $this->actingAs($auteur)->get('/pourcentage-dalakoun');

        $reponse->assertOk();
        $reponse->assertSee($produit->nom, false);
    }

    public function test_la_fiche_produit_annonce_le_taux_qui_lui_est_applique(): void
    {
        // La fiche affichait le taux GENERAL, meme sur un produit sous
        // derogation : elle annoncait donc un prix que la boutique ne
        // pratiquait pas, et le champ calcule se trompait a la frappe.
        [$auteur, $valideur] = $this->deuxAdmins();
        $this->tauxGeneral(10);

        $produit  = $this->unProduit(10000);
        $decision = $this->saisir($auteur, ['produit_id' => $produit->id, 'taux' => 30]);
        $this->valider($valideur, $decision);

        \App\Models\ImageProduit::firstOrCreate(
            ['produit_id' => $produit->id],
            ['image' => 'recette.png']
        );

        URL::forceRootUrl('');

        $reponse = $this->actingAs($auteur)->get('/products-edit/' . $produit->id);

        $reponse->assertOk();
        $reponse->assertSee('data-taux="30"', false);
        $reponse->assertSee('Derogation', false);
    }

    public function test_une_fiche_sans_derogation_garde_le_taux_general(): void
    {
        [$auteur] = $this->deuxAdmins();
        $this->tauxGeneral(10);

        $produit = $this->unProduit(10000);

        \App\Models\ImageProduit::firstOrCreate(
            ['produit_id' => $produit->id],
            ['image' => 'recette.png']
        );

        URL::forceRootUrl('');

        $reponse = $this->actingAs($auteur)->get('/products-edit/' . $produit->id);

        $reponse->assertOk();
        $reponse->assertSee('data-taux="10"', false);
    }
}
