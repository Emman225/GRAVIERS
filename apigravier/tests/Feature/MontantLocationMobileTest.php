<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DetailLocation;
use App\Models\Location;
use App\Models\Produit;
use App\Models\TvaCommande;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * LE DÛ D'UNE LOCATION SE LIT DANS SES LIGNES, JAMAIS DANS `montant_total`.
 *
 * Cette colonne n'a PAS le même sens selon le canal : le site y écrit le HT,
 * l'application le NET final, TVA et livraison comprises. L'API la lisait puis y
 * rajoutait TVA et livraison : sur une location passée depuis le téléphone, le
 * dû était gonflé de ces deux montants.
 *
 * Mesuré le 01/09/2026 sur la même base, pour la MÊME location :
 *     site   : 122 000
 *     mobile : 144 000
 *
 * Le site avait été corrigé ; l'API ne l'avait pas été. Le modèle Commande de
 * l'API portait déjà la correction — seules les locations manquaient.
 *
 * La conséquence dépasse l'affichage : `Client::encoursCredit()` additionne les
 * `montantRestantDu()` des locations. Un dû gonflé amputait le plafond de crédit
 * du client de la TVA et de la livraison, et lui faisait refuser des commandes
 * qu'il avait le droit de passer.
 */
class MontantLocationMobileTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Une location dont les deux conventions divergent :
     * lignes = 100 000 HT, TVA 18 000, livraison 4 000, net = 122 000.
     */
    private function uneLocationDuMobile(): Location
    {
        $client = Client::first();
        $produit = Produit::where('statut', 1)->first();

        if (!$client || !$produit) {
            $this->markTestSkipped('Fixtures absentes (client ou produit).');
        }

        $location = Location::create([
            'numero'                => 'TEST-' . uniqid(),
            'client_id'             => $client->id,
            // Convention de l'APPLICATION : le net final.
            'montant_total'         => 122000,
            'etat_location'         => \Help::$LOCATION_EN_ATTENTE,
            'statut'                => \Help::$STATUT_ACTIF,
            'cout_livraison_client' => 4000,
        ]);

        DetailLocation::create([
            'produit_id'    => $produit->id,
            'location_id'   => $location->id,
            'qte'           => 1,
            // `prix` porte le TOTAL de la ligne, quantité et jours compris.
            'prix'          => 100000,
            'debut'         => now()->toDateString(),
            'fin'           => now()->addDay()->toDateString(),
            'etat_location' => \Help::$LOCATION_EN_ATTENTE,
        ]);

        TvaCommande::create([
            'client_id'    => $client->id,
            'commande_id'  => $location->id,
            'montant'      => 18000,
            'type_affaire' => \Help::$LOCATION,
        ]);

        return $location->fresh()->load('detailLocation', 'tvaLocation');
    }

    /** LE HT VIENT DES LIGNES, PAS DE LA COLONNE AMBIGUË. */
    public function test_le_ht_est_lu_dans_les_lignes(): void
    {
        $this->assertSame(100000.0, $this->uneLocationDuMobile()->montantHT(),
            'Le HT est lu dans `montant_total`, dont le sens change selon le '
            . 'canal : le dû sera faux pour toute location passée du téléphone.');
    }

    /** LE DÛ NE COMPTE NI LA TVA NI LA LIVRAISON DEUX FOIS. */
    public function test_le_du_ne_compte_pas_la_tva_deux_fois(): void
    {
        $location = $this->uneLocationDuMobile();

        $this->assertSame(122000.0, $location->montantAPayer(),
            'Le dû est gonflé de la TVA et de la livraison : l’application '
            . 'réclame au client plus que le back-office et que sa facture.');

        $this->assertSame(122000.0, $location->montantRestantDu(),
            'Le reste dû suit le même calcul faux.');
    }

    /**
     * UNE LOCATION SANS LIGNE NE PASSE PAS POUR SOLDÉE.
     *
     * Elle ne devrait pas exister, mais rendre zéro l'effacerait des comptes.
     */
    public function test_une_location_sans_ligne_retombe_sur_le_montant_stocke(): void
    {
        $client = Client::first();

        if (!$client) {
            $this->markTestSkipped('Aucun client.');
        }

        $location = Location::create([
            'numero'        => 'TEST-' . uniqid(),
            'client_id'     => $client->id,
            'montant_total' => 50000,
            'etat_location' => \Help::$LOCATION_EN_ATTENTE,
            'statut'        => \Help::$STATUT_ACTIF,
        ]);

        $this->assertSame(50000.0, $location->fresh()->montantHT(),
            'Une location sans ligne doit retomber sur son montant stocké, sans '
            . 'quoi elle passerait pour soldée.');
    }

    /**
     * LE PLAFOND DE CRÉDIT SUIT LE DÛ RÉEL.
     *
     * `encoursCredit()` additionne les `montantRestantDu()` des locations. Un dû
     * gonflé amputait le crédit disponible du client, et lui faisait refuser des
     * commandes qu'il avait le droit de passer.
     */
    public function test_l_encours_du_client_suit_le_du_reel(): void
    {
        // ON MESURE L'ÉCART, PAS LE TOTAL.
        //
        // Première rédaction : « l'encours est inférieur à 144 000 + encours
        // - 122 000 ». Cela se simplifie en « encours < encours + 22 000 » —
        // toujours vrai. L'essai passait au vert sans rien éprouver.
        $client = Client::first();

        if (!$client) {
            $this->markTestSkipped('Aucun client.');
        }

        $avant = $client->encoursCredit();

        $this->uneLocationDuMobile();

        $apres = Client::find($client->id)->encoursCredit();

        $this->assertEqualsWithDelta(122000.0, $apres - $avant, 0.01,
            'La location pèse ' . ($apres - $avant) . ' F sur le crédit du client '
            . 'au lieu de 122 000 : la TVA et la livraison y sont comptées deux '
            . 'fois, et le client se voit refuser des commandes qu’il pouvait '
            . 'passer.');
    }

    /** LA MÊME RÈGLE QUE LE SITE, MOT POUR MOT. */
    public function test_la_regle_est_celle_du_site(): void
    {
        $api = file_get_contents(app_path('Models/Location.php'));

        $this->assertStringContainsString("\$this->detailLocation->sum('prix')", $api,
            'Le HT doit être lu dans les lignes, comme sur le site.');

        // `detail_location.prix` porte DÉJÀ la quantité et les jours : le
        // multiplier par la quantité doublerait le montant des locations de
        // plusieurs unités.
        $this->assertStringNotContainsString("sum('prix') * ", $api,
            'Le prix d’une ligne de location porte déjà la quantité et les '
            . 'jours : le multiplier gonflerait le dû.');
    }
}
