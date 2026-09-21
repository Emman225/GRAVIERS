<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Commande;
use App\Models\DetailCommande;
use App\Models\Enlevement;
use App\Models\Fournisseur;
use App\Models\Livraison;
use App\Models\Livreur;
use App\Models\Produit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * UN ENLÈVEMENT PARTIEL NE CLÔT PAS LA COMMANDE.
 *
 * Constaté en production le 01/09/2026 sur la commande 627042 : le gestionnaire
 * traite 15 tonnes, le fournisseur n'en sert que 5. Il en reste 10 à servir.
 * Pourtant, à la clôture de la course, la ligne était créditée de 15 — la
 * quantité DEMANDÉE — la ligne passait LIVREE et la commande TERMINEE. Elle
 * quittait la liste des commandes à traiter, et le reliquat de 10 tonnes
 * n'était plus réclamé nulle part.
 *
 * L'écran de détail, lui, disait juste : « QTÉ 15, QTÉ LIVRÉE 5, RESTE À
 * TRAITER 10, Livraison partielle ». Deux comptes coexistaient donc, et c'est
 * le faux qui décidait de l'état de la commande.
 *
 * RÉGRESSION : le crédit de `qte_livree` depuis le site a été introduit par le
 * commit « Recette : états comptables, circuit de paiement, refus de course ».
 * L'application du livreur portait la même faute depuis l'origine. Les deux
 * lisent désormais la même règle, posée sur le modèle Livraison.
 */
class EnlevementPartielTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Monte le décor : une commande d'une ligne, sa course, son bon.
     *
     * @return array{0:Commande,1:DetailCommande,2:Livraison,3:Enlevement,4:Livreur}
     */
    private function uneCommandeTraitee(float $qteCommandee, ?float $qteServie): array
    {
        $client = Client::whereNotNull('user_id')->first();
        $produit = Produit::where('statut', 1)->first();
        $fournisseur = Fournisseur::first();
        $livreur = Livreur::whereNotNull('user_id')->first();

        if (!$client || !$produit || !$fournisseur || !$livreur) {
            $this->markTestSkipped('Fixtures absentes (client, produit, fournisseur ou livreur).');
        }

        $commande = Commande::create([
            'numero'        => 'TEST-' . uniqid(),
            'client_id'     => $client->id,
            'montant_total' => 181500,
            'etat_commande' => \Help::$COMMANDE_EN_TRAITEMENT,
            'statut'        => 3,
        ]);

        $detail = DetailCommande::create([
            'commande_id'    => $commande->id,
            'produit_id'     => $produit->id,
            'qte'            => $qteCommandee,
            'prix'           => 12100,
            'statut'         => \Help::$STATUT_ACTIF,
            'etat_livraison' => \Help::$LIVRAISON_EN_ATTENTE,
            'qte_livree'     => 0,
        ]);

        $livraison = Livraison::create([
            'numero'             => uniqid(),
            'client_id'          => $client->id,
            'livreur_id'         => $livreur->id,
            'detail_commande_id' => $detail->id,
            'qte'                => $qteCommandee,
            'livre_par'          => 1,
            'accepte'            => 2,
            'etat_livraison'     => \Help::$LIVRAISON_EN_COURS,
            'statut'             => \Help::$STATUT_ACTIF,
            'provenance'         => 'COMMANDE',
            'date_livraison'     => now()->toDateString(),
        ]);

        $bon = Enlevement::create([
            'code_enleve'            => strtoupper(substr(uniqid(), -8)),
            'livraison_id'           => $livraison->id,
            'fournisseur_id'         => $fournisseur->id,
            'produit_id'             => $produit->id,
            'livreur_id'             => $livreur->id,
            'qte'                    => $qteCommandee,
            'qte_servi'              => $qteServie,
            'fournisseur_validation' => now(),
            'prix_fournisseur'       => 8000,
            'statut'                 => \Help::$STATUT_ACTIF,
        ]);

        return [$commande, $detail, $livraison, $bon, $livreur];
    }

    private function leLivreurCloture(Livraison $livraison, Livreur $livreur): void
    {
        Auth::loginUsingId($livreur->user_id);

        (new \App\Http\Controllers\LivreurController())->validationLivraison(
            \Illuminate\Http\Request::create('/x', 'POST', ['code' => $livraison->numero])
        );
    }

    /** 5 SERVIES SUR 15 : IL EN RESTE 10, ET LA COMMANDE RESTE À TRAITER. */
    public function test_un_enlevement_partiel_laisse_la_commande_a_traiter(): void
    {
        [$commande, $detail, $livraison, , $livreur] = $this->uneCommandeTraitee(15, 5);

        $this->leLivreurCloture($livraison, $livreur);

        $this->assertEquals(5, $detail->fresh()->qte_livree,
            'Le client est crédité de la quantité DEMANDÉE et non de celle que le '
            . 'fournisseur a servie : le reliquat disparaît des comptes.');

        $this->assertSame(\Help::$COMMANDE_EN_TRAITEMENT, $commande->fresh()->etat_commande,
            'La commande a quitté la liste des commandes à traiter alors qu’il '
            . 'reste de la marchandise à servir.');

        $this->assertNotSame(\Help::$LIVRAISON_LIVREE, $detail->fresh()->etat_livraison,
            'La ligne est annoncée LIVREE alors qu’elle est servie au tiers.');
    }

    /** LE CAS NOMINAL N'EST PAS CASSÉ : TOUT SERVI, TOUT CLÔTURÉ. */
    public function test_un_enlevement_complet_cloture_bien_la_commande(): void
    {
        [$commande, $detail, $livraison, , $livreur] = $this->uneCommandeTraitee(15, 15);

        $this->leLivreurCloture($livraison, $livreur);

        $this->assertEquals(15, $detail->fresh()->qte_livree,
            'Une livraison complète doit créditer la totalité.');

        $this->assertSame(\Help::$COMMANDE_TERMINE, $commande->fresh()->etat_commande,
            'Une commande entièrement servie doit passer TERMINEE : la corriger '
            . 'ne doit pas bloquer le cas courant.');

        $this->assertSame(\Help::$LIVRAISON_LIVREE, $detail->fresh()->etat_livraison,
            'La ligne entièrement servie doit être marquée LIVREE.');
    }

    /**
     * UN BON SANS QUANTITÉ SERVIE VAUT LA QUANTITÉ DEMANDÉE.
     *
     * Les bons émis avant que la saisie n'existe ont `qte_servi` à NULL. Les
     * traiter comme « zéro servi » rouvrirait des commandes closes depuis des
     * mois.
     */
    public function test_un_bon_sans_quantite_servie_vaut_la_quantite_demandee(): void
    {
        [$commande, $detail, $livraison, , $livreur] = $this->uneCommandeTraitee(15, null);

        $this->leLivreurCloture($livraison, $livreur);

        $this->assertEquals(15, $detail->fresh()->qte_livree,
            'Un bon sans quantité servie doit valoir la quantité demandée : '
            . 'sinon les anciennes commandes se rouvrent toutes.');

        $this->assertSame(\Help::$COMMANDE_TERMINE, $commande->fresh()->etat_commande,
            'La commande doit se clore comme avant sur les bons anciens.');
    }

    /** SANS BON — LOCATION, DEMANDE DE LIVRAISON — LA COURSE FAIT FOI. */
    public function test_sans_bon_la_quantite_de_la_course_fait_foi(): void
    {
        [, , $livraison, $bon, ] = $this->uneCommandeTraitee(15, 5);

        $bon->forceDelete();

        $this->assertEquals(15, $livraison->fresh()->quantiteRemise(),
            'Une course sans bon — location, demande de livraison — n’a pas de '
            . 'fournisseur : sa propre quantité fait foi.');
    }

    /** LA RÈGLE EST LA MÊME DES DEUX CÔTÉS. */
    public function test_le_site_et_l_application_lisent_la_meme_regle(): void
    {
        $site = file_get_contents(app_path('Http/Controllers/LivreurController.php'));

        $this->assertStringContainsString('$livraison->quantiteRemise()', $site,
            'Le site recalcule la quantité livrée de son côté : les deux comptes '
            . 'finiraient par diverger.');

        $this->assertStringNotContainsString(
            "(float) (\$ligne->qte_livree ?? 0) + (float) \$livraison->qte", $site,
            'Le site crédite encore la quantité demandée.');

        $api = base_path('../apigravier/app/Http/Controllers/LivreurController.php');

        if (!is_file($api)) {
            $this->markTestSkipped('Le dépôt de l’API n’est pas à côté.');
        }

        $this->assertStringContainsString('$livraison->quantiteRemise()',
            file_get_contents($api),
            'L’application du livreur crédite encore la quantité demandée : le '
            . 'même défaut réapparaîtrait par elle.');
    }
}
