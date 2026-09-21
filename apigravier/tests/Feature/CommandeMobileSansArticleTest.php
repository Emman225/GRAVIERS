<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Commande;
use App\Models\DetailCommande;
use App\Models\Produit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * UNE COMMANDE MOBILE PORTE TOUS SES ARTICLES, OU AUCUNE COMMANDE N'EST CRÉÉE.
 *
 * Constaté le 01/09/2026 : la commande 286453 (588 100 F) s'affichait dans
 * l'application avec « 0 article(s) » et un écran de détail entièrement blanc.
 * Elle ne portait AUCUNE ligne en base.
 *
 * Le site en était l'origine (un devis vide, voir CommandeSansArticleTest côté
 * graviers), mais rien n'empêchait l'application de produire la même chose : ni
 * à l'entrée — un panier illisible —, ni à la sortie — une ligne qui ne
 * s'enregistre pas. Une commande à moitié écrite est pire qu'une commande
 * refusée : le client la croit passée.
 *
 * Le contrôle de sortie est DANS la transaction : il annule tout, il ne
 * rattrape pas.
 */
class CommandeMobileSansArticleTest extends TestCase
{
    use DatabaseTransactions;

    private function unClientAvecCompte(): Client
    {
        $client = Client::whereNotNull('user_id')->whereHas('user')->first();

        if (!$client) {
            $this->markTestSkipped('Aucun client rattaché à un compte.');
        }

        return $client;
    }

    /** @return array{0:array,1:array} les deux produits et les lignes du panier */
    private function unPanierDeDeuxArticles(): array
    {
        $produits = Produit::where('type_affaire', \Help::$VENTE)
            ->where('statut', \Help::$STATUT_ACTIF)
            ->take(2)->get();

        if ($produits->count() < 2) {
            $this->markTestSkipped('Moins de deux produits de vente actifs.');
        }

        $lignes = $produits->map(fn ($p) => [
            'produit_id' => $p->id,
            'qte'        => 2,
            'prix'       => (float) $p->prix_moyen,
            'livraison'  => 0,
        ])->all();

        return [$produits->all(), $lignes];
    }

    private function commander(Client $client, array $lignes)
    {
        return $this->postJson('/mon_gravier/enregistrer-commande', [
            'access'        => Crypt::encryptString((string) $client->user_id),
            'type'          => 'mobile',
            // 1 = en ligne, 2 = virement (exige une preuve) : 3 = paiement en
            // agence, le mode du cas signalé.
            'mode_paiement' => 3,
            'moyen_paiement' => 0,
            'lignes'        => $lignes,
            'total'         => collect($lignes)->sum(fn ($l) => $l['prix'] * $l['qte']),
            'meFaireLivre'  => 0,
            'date_livraison' => now()->addDay()->toDateString(),
        ]);
    }

    /**
     * LE PANIER ARRIVE ENTIER DANS LA COMMANDE.
     *
     * C'est l'essai que le contrôle de sortie protège : si une seule ligne
     * manquait, la commande ne devrait pas exister du tout.
     */
    public function test_la_commande_porte_toutes_les_lignes_du_panier(): void
    {
        $client = $this->unClientAvecCompte();
        [, $lignes] = $this->unPanierDeDeuxArticles();

        $avant = Commande::where('client_id', $client->id)->count();

        $reponse = $this->commander($client, $lignes);
        $reponse->assertOk();

        $corps = $reponse->json();

        $this->assertSame(200, $corps['code'],
            'La commande a été refusée : ' . ($corps['message'] ?? ''));

        $commande = Commande::where('client_id', $client->id)->latest('id')->first();

        $this->assertSame($avant + 1, Commande::where('client_id', $client->id)->count(),
            'La commande n’a pas été enregistrée.');

        $this->assertSame(count($lignes),
            DetailCommande::where('commande_id', $commande->id)->count(),
            'La commande a été enregistrée avec moins d’articles que le panier : '
            . 'le client la croit passée alors qu’elle est incomplète.');
    }

    /** AUCUNE COMMANDE CREUSE N'EST LAISSÉE EN BASE. */
    public function test_aucune_commande_du_client_ne_reste_sans_article(): void
    {
        $client = $this->unClientAvecCompte();
        [, $lignes] = $this->unPanierDeDeuxArticles();

        $this->commander($client, $lignes);

        $creuses = Commande::where('client_id', $client->id)
            ->whereDoesntHave('detailCommande')
            ->count();

        $this->assertSame(0, $creuses,
            "$creuses commande(s) de ce client ne portent aucun article : leur "
            . 'écran de détail s’ouvre blanc et leur bon s’imprime vide.');
    }

    /** LES DEUX VERROUS SONT EN PLACE, ET ILS ANNULENT. */
    public function test_les_deux_verrous_annulent_la_transaction(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/CommandeController.php'));

        $this->assertStringContainsString('if (empty($lignes)) {', $source,
            'Rien n’empêche un panier illisible de devenir une commande sans '
            . 'article.');

        $this->assertStringContainsString('if ($posees !== count($lignes)) {', $source,
            'Rien ne vérifie que toutes les lignes ont bien été enregistrées.');

        // Le contrôle doit ANNULER, pas seulement journaliser : une commande
        // incomplète laissée en base est pire qu'une commande refusée.
        $apres = substr($source, strpos($source, 'if ($posees !== count($lignes)) {'));
        $this->assertStringContainsString('DB::rollBack();', substr($apres, 0, 200),
            'Le contrôle de sortie signale l’anomalie sans annuler la commande.');
    }
}
