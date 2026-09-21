<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Commande;
use App\Models\DetailDevis;
use App\Models\Devis;
use App\Models\Produit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * UNE COMMANDE SANS ARTICLE N'EST PAS UNE COMMANDE.
 *
 * Constaté le 01/09/2026 : sur l'application client, l'écran « Détails commande
 * à imprimer ou retourner » de la commande 286453 (588 100 F) s'affichait
 * entièrement BLANC — « 0 article(s) » en sous-titre.
 *
 * La commande ne portait AUCUNE ligne en base. Ce n'était donc pas un défaut
 * d'affichage : il n'y avait rien à afficher.
 *
 * D'où viennent ces commandes creuses ? Le site RECOPIE dans la commande les
 * lignes de son devis. Le devis, lui, est bâti sur le panier de SESSION. Quand
 * la session a expiré, le panier est vide mais ses TOTAUX survivent : on
 * obtient un devis avec un montant et zéro ligne, puis une commande avec un
 * montant et zéro ligne. Elle prend son numéro, sa place dans les listes, et
 * personne ne peut dire ce qui a été acheté.
 *
 * Témoin retrouvé en base locale : la commande 895007, 100 F, rattachée au
 * devis n° 5 — lui aussi sans la moindre ligne.
 *
 * Les QUATRE points de création du site passent désormais par la même règle.
 */
class CommandeSansArticleTest extends TestCase
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

    private function unDevis(Client $client, bool $avecLignes): Devis
    {
        $devis = Devis::create([
            'numero'         => 'TST' . substr(uniqid(), -7),
            'client_id'      => $client->id,
            'montant'        => 588100,
            'date_livraison' => now()->addDay()->toDateString(),
            'statut'         => \Help::$STATUT_ACTIF,
        ]);

        if ($avecLignes) {
            $produit = Produit::where('type_affaire', 'VENTE')->first();

            if (!$produit) {
                $this->markTestSkipped('Aucun produit de vente.');
            }

            DetailDevis::create([
                'devis_id'   => $devis->id,
                'produit_id' => $produit->id,
                'qte'        => 5,
                'prix'       => 117620,
                'statut'     => \Help::$STATUT_ACTIF,
            ]);
        }

        return $devis;
    }

    /** LE DEVIS VIDE NE DEVIENT PAS UNE COMMANDE. */
    public function test_un_devis_sans_ligne_ne_cree_pas_de_commande(): void
    {
        $client = $this->unClient();
        $devis  = $this->unDevis($client, false);

        $this->actingAs($client->user)
            ->get(route('client.panierCommande', $devis))
            ->assertRedirect(route('client.monPanier'));

        $this->assertNull(Commande::where('devis_id', $devis->id)->first(),
            'Une commande a été enregistrée sans le moindre article : elle '
            . 'affiche un montant que rien ne justifie, son écran de détail '
            . 's’ouvre blanc et son bon s’imprime vide.');
    }

    /**
     * LE CLIENT EST RENVOYÉ LÀ OÙ IL PEUT AGIR.
     *
     * Le refus habituel de cette page ramène à l'écran de paiement DU DEVIS
     * (`retourApresRefusCommande`). Ici ce serait un cul-de-sac : le devis est
     * vide, revalider depuis le paiement échouerait indéfiniment. Le seul
     * endroit où le client peut reprendre ses articles, c'est son panier.
     *
     * Le MESSAGE, lui, ne s'assère pas en session : Flasher capte les clés
     * success/error/warning/info et les rejoue en toast. On vérifie donc qu'il
     * est bien posé dans le code, et l'endroit où il mène par l'effet.
     */
    public function test_le_client_est_renvoye_la_ou_il_peut_agir(): void
    {
        $client = $this->unClient();
        $devis  = $this->unDevis($client, false);

        $reponse = $this->actingAs($client->user)
            ->get(route('client.panierCommande', $devis));

        $reponse->assertRedirect(route('client.monPanier'));

        $this->assertNotSame(route('devis.modePaiement', $devis),
            $reponse->headers->get('Location'),
            'Le client est renvoyé au paiement d’un devis vide : il ne peut '
            . 'qu’y échouer de nouveau.');

        $this->assertStringContainsString('ne contient plus aucun',
            file_get_contents(app_path('Http/Controllers/ClientController.php')),
            'Le refus doit dire ce qui manque : sans quoi le client réessaie '
            . 'sans comprendre.');
    }

    /**
     * LE CAS NOMINAL N'EST PAS CASSÉ.
     *
     * Un devis qui porte ses lignes doit toujours devenir une commande — et
     * cette commande doit porter les mêmes articles.
     */
    public function test_un_devis_avec_ses_lignes_devient_bien_une_commande(): void
    {
        $client = $this->unClient();
        $devis  = $this->unDevis($client, true);

        $this->actingAs($client->user)->get(route('client.panierCommande', $devis));

        $commande = Commande::where('devis_id', $devis->id)->first();

        $this->assertNotNull($commande,
            'Un devis complet ne devient plus une commande : le garde-fou '
            . 'bloque le parcours normal.');

        $this->assertSame(1, $commande->detailCommande()->count(),
            'La commande créée ne reprend pas les articles de son devis.');
    }

    /** LA MÊME RÈGLE AUX QUATRE POINTS DE CRÉATION. */
    public function test_les_quatre_points_de_creation_partagent_la_regle(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/ClientController.php'));

        $this->assertSame(4, substr_count($source, '$this->devisSansArticle($devis)'),
            'Les quatre points de création de commande du site doivent appliquer '
            . 'la même règle, sans quoi l’un d’eux recréera des commandes '
            . 'creuses.');

        // ON COMPTE LES POINTS DE CRÉATION, PAS UNE FORME D'ÉCRITURE.
        //
        // Cette assertion cherchait la chaîne exacte « $commande =
        // Commande::create([ ». Le jour où l'un de ces points a été réécrit —
        // la commande directe passe désormais par Help::creerAvecNumeroUnique,
        // puisqu'elle ne tire plus son numéro d'un devis — le compte est tombé
        // à 3 alors qu'aucun point n'avait disparu. On compte donc les appels
        // eux-mêmes, en excluant les tables voisines (DetailCommande,
        // TvaCommande, DemandeAnnulationCommande).
        $creations = preg_match_all('/(?<![A-Za-z])Commande::create\(/', $source);

        $this->assertSame(4, $creations,
            'Un cinquième point de création est apparu : il doit lui aussi '
            . 'passer par devisSansArticle().');
    }
}
