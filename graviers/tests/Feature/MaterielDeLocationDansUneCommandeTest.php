<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Commande;
use App\Models\DetailCommande;
use App\Models\DetailDevis;
use App\Models\Devis;
use App\Models\Produit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * UN MATÉRIEL DE LOCATION N'ENTRE PAS DANS UNE COMMANDE DE VENTE.
 *
 * Constaté en production le 01/09/2026 sur la commande 286453 (495 000 F) :
 * l'écran « Détails commande à imprimer ou retourner » de l'application client
 * s'affichait entièrement BLANC, « 0 article(s) ».
 *
 * La commande portait pourtant bien sa ligne : 5 « Mini-pelle de chantier » à
 * 99 000 F — soit exactement les 495 000 F annoncés. Un matériel de LOCATION,
 * dans une commande de VENTE.
 *
 * DEUX FAUTES SE SUPERPOSAIENT :
 *
 *  1. La lecture écartait la ligne. `DetailCommande::liste()` filtrait sur
 *     `produit.type_affaire = 'VENTE'` : toute ligne d'un autre type
 *     disparaissait SANS UN MOT. L'écran était blanc, le bon s'imprimait vide,
 *     et rien n'indiquait qu'une ligne avait été retirée de la vue.
 *
 *  2. Le matériel n'aurait jamais dû arriver là. Le raccourci « Commander » du
 *     menu du panier mène au parcours de VENTE quel que soit son contenu. La
 *     page « Mon panier », elle, aiguille correctement — mais le raccourci
 *     court-circuitait cet aiguillage. Le client se retrouvait engagé sur un
 *     ACHAT de mini-pelle : sans dates, sans caution, sans retour de matériel.
 */
class MaterielDeLocationDansUneCommandeTest extends TestCase
{
    use DatabaseTransactions;

    private function unClient(): Client
    {
        $client = Client::whereNotNull('user_id')->whereHas('user')->first();

        if (!$client) {
            $this->markTestSkipped('Aucun client rattaché à un compte.');
        }

        return $client;
    }

    private function unProduit(string $type): Produit
    {
        $produit = Produit::where('type_affaire', $type)->first();

        if (!$produit) {
            $this->markTestSkipped("Aucun produit $type.");
        }

        return $produit;
    }

    /** Reconstitue la commande 286453 : une ligne, un produit de LOCATION. */
    private function laCommandeDeLaMiniPelle(Client $client): Commande
    {
        $commande = Commande::create([
            'numero'        => 'TST' . substr(uniqid(), -7),
            'client_id'     => $client->id,
            'montant_total' => 495000,
            'etat_commande' => \Help::$COMMANDE_EN_ATTENTE,
            'statut'        => \Help::$STATUT_ACTIF,
        ]);

        DetailCommande::create([
            'commande_id'    => $commande->id,
            'produit_id'     => $this->unProduit(\Help::$LOCATION)->id,
            'qte'            => 5,
            'prix'           => 99000,
            'etat_livraison' => \Help::$LIVRAISON_EN_ATTENTE,
            'statut'         => \Help::$STATUT_ACTIF,
        ]);

        return $commande;
    }

    /** FAUTE 1 — LA LIGNE NE DISPARAÎT PLUS DE LA LECTURE. */
    public function test_une_ligne_de_location_reste_visible_dans_la_commande(): void
    {
        $commande = $this->laCommandeDeLaMiniPelle($client = $this->unClient());

        $this->assertCount(1, DetailCommande::liste(null, $commande->id, $client->id),
            'La ligne est écartée de la lecture parce que son produit n’est pas '
            . 'typé VENTE : l’écran de détail s’ouvre blanc et le bon s’imprime '
            . 'vide, sans qu’un mot ne signale la ligne manquante.');
    }

    /** ET LE MONTANT LU CORRESPOND À CELUI DE LA COMMANDE. */
    public function test_le_montant_lu_correspond_a_celui_annonce(): void
    {
        $commande = $this->laCommandeDeLaMiniPelle($client = $this->unClient());

        $lu = collect(DetailCommande::liste(null, $commande->id, $client->id))
            ->sum(fn ($l) => (float) $l->prix * (float) $l->qte);

        $this->assertEqualsWithDelta(495000, $lu, 0.01,
            'Le total des lignes lues ne fait pas le montant de la commande : '
            . 'le client voit une somme que rien à l’écran ne justifie.');
    }

    /** FAUTE 2 — UN DEVIS DE MATÉRIEL DE LOCATION NE DEVIENT PAS UNE COMMANDE. */
    public function test_un_devis_de_location_ne_devient_pas_une_commande(): void
    {
        $client = $this->unClient();

        $devis = Devis::create([
            'numero'         => 'TST' . substr(uniqid(), -7),
            'client_id'      => $client->id,
            'montant'        => 495000,
            'date_livraison' => now()->addDay()->toDateString(),
            'statut'         => \Help::$STATUT_ACTIF,
        ]);

        DetailDevis::create([
            'devis_id'   => $devis->id,
            'produit_id' => $this->unProduit(\Help::$LOCATION)->id,
            'qte'        => 5,
            'prix'       => 99000,
            'statut'     => \Help::$STATUT_ACTIF,
        ]);

        $this->actingAs($client->user)
            ->get(route('client.panierCommande', $devis))
            ->assertRedirect(route('client.panierLocation'));

        $this->assertNull(Commande::where('devis_id', $devis->id)->first(),
            'Un matériel de location a été transformé en ACHAT : le client est '
            . 'engagé sans dates, sans caution et sans retour de matériel.');
    }

    /** LE CAS NOMINAL N'EST PAS CASSÉ : UNE VENTE RESTE UNE VENTE. */
    public function test_un_devis_de_vente_devient_bien_une_commande(): void
    {
        $client = $this->unClient();

        $devis = Devis::create([
            'numero'         => 'TST' . substr(uniqid(), -7),
            'client_id'      => $client->id,
            'montant'        => 50000,
            'date_livraison' => now()->addDay()->toDateString(),
            'statut'         => \Help::$STATUT_ACTIF,
        ]);

        DetailDevis::create([
            'devis_id'   => $devis->id,
            'produit_id' => $this->unProduit(\Help::$VENTE)->id,
            'qte'        => 2,
            'prix'       => 25000,
            'statut'     => \Help::$STATUT_ACTIF,
        ]);

        $this->actingAs($client->user)->get(route('client.panierCommande', $devis));

        $this->assertNotNull(Commande::where('devis_id', $devis->id)->first(),
            'Un devis de vente ne devient plus une commande : l’aiguillage bloque '
            . 'le parcours normal.');
    }

    /** L'AIGUILLAGE PRÉCÈDE TOUTE ÉCRITURE. */
    public function test_l_aiguillage_precede_la_creation_du_devis(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/ClientController.php'));

        $aiguillage = strpos($source, '$this->porteDuMaterielDeLocation($devis)');
        $creationDevis = strpos($source, 'return Devis::create($dataDevis);');

        $this->assertLessThan($creationDevis, $aiguillage,
            'L’aiguillage se fait APRÈS la création du devis depuis le panier : '
            . 'un refus laisserait derrière lui un devis orphelin et un code '
            . 'promo déjà consommé.');
    }
}
