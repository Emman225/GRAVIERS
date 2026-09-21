<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Commande;
use App\Models\Devis;
use App\Models\Produit;
use App\Models\User;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * UNE COMMANDE DE VENTE NE CRÉE PAS DE DEVIS.
 *
 * Demandé le 04/09/2026 : « c'est moi-même qui dois décider de créer un devis,
 * et il y a une possibilité qui existe déjà pour ça ».
 *
 * Le devis servait de PORTEUR des données du panier jusqu'à la commande, mais
 * il était ENREGISTRÉ. Conséquence : chaque vente ajoutait une ligne dans
 * « Liste des devis » du back-office — 11 des 21 devis de la base d'essai
 * venaient de là — et, depuis la correction de son état, dans « Mes devis »
 * côté client.
 *
 * Il reste un porteur, en mémoire, jamais écrit. Ces essais tiennent les deux
 * bouts : plus aucun devis pour une vente, ET le parcours « Demander un devis »
 * en crée toujours un.
 */
class CommandeNeCreePasDeDevisTest extends TestCase
{
    use DatabaseTransactions;

    private function clientConnecte(): Client
    {
        $user = User::create([
            'nom_prenoms'  => 'Devis Zero',
            'email'        => 'dz_' . uniqid() . '@example.test',
            'login'        => 'dz' . substr((string) uniqid(), -8),
            'password'     => bcrypt(\Illuminate\Support\Str::random(16)),
            'type_user_id' => 4,
            'contact'      => '0700000000',
            'statut'       => \Help::$STATUT_ACTIF,
        ]);

        $client = Client::create([
            'user_id'        => $user->id,
            'nom'            => 'Devis',
            'prenom'         => 'Zero',
            'email'          => $user->email,
            'contact1'       => '0700000000',
            'type_client'    => 'PARTICULIER',
            'client_a_terme' => 0,
            'statut'         => \Help::$STATUT_ACTIF,
        ]);

        $this->actingAs($user);
        URL::forceRootUrl('');
        Http::fake(['*' => Http::response(['code' => 400, 'message' => 'refus'], 200)]);

        return $client;
    }

    private function unPanier(): void
    {
        $produit = Produit::where('type_affaire', 'VENTE')
            ->where('statut', \Help::$STATUT_ACTIF)->first() ?? Produit::first();

        if (!$produit) {
            $this->markTestSkipped('Aucun produit au catalogue.');
        }

        Cart::destroy();
        Cart::add($produit->id, $produit->nom, 2, 50000)->associate(Produit::class);

        session()->put([
            '0' => [
                'ville'          => null,
                'long'           => 0,
                'lat'            => 0,
                'infoSup'        => 'Adresse de recette',
                'montantTTC'     => 118000,
                'montantHT'      => 100000,
                'tva'            => 18000,
                'cout_livraison' => 0,
                'estLivrable'    => 'non',
            ],
            'remise'         => 0,
            'type_livraison' => 1,
            'date_livraison' => now()->addDays(3)->toDateString(),
            'type'           => 'commande',
        ]);
    }

    /** LE PARCOURS PRINCIPAL : panier → commande. */
    public function test_une_commande_directe_ne_laisse_aucun_devis(): void
    {
        $client = $this->clientConnecte();
        $this->unPanier();

        $devisAvant = Devis::count();

        $this->get('/panier-en-commande');

        $commande = Commande::where('client_id', $client->id)->latest('id')->first();

        $this->assertNotNull($commande, 'La commande doit être enregistrée.');

        $this->assertSame($devisAvant, Devis::count(),
            'La commande a créé un devis que le client n\'a pas demandé : '
            . 'la liste des devis se remplit toute seule à chaque vente.');

        $this->assertNull($commande->devis_id,
            'La commande directe ne doit se rattacher à aucun devis.');
    }

    /**
     * LA COMMANDE RESTE COMPLÈTE.
     *
     * C'est le risque de ce changement : le devis portait le numéro, le montant
     * et les articles. Les perdre ferait des commandes creuses — précisément ce
     * que CommandeSansArticleTest interdit.
     */
    public function test_la_commande_garde_son_numero_son_montant_et_ses_articles(): void
    {
        $client = $this->clientConnecte();
        $this->unPanier();

        $this->get('/panier-en-commande');

        $commande = Commande::where('client_id', $client->id)->latest('id')->first();

        $this->assertNotNull($commande);

        $this->assertNotEmpty($commande->numero,
            'La commande doit porter un numéro : il venait du devis.');

        $this->assertGreaterThan(0, (float) $commande->montant_total,
            'Le montant venait du devis : il ne doit pas être perdu.');

        $this->assertSame(1, $commande->detailCommande()->count(),
            'Les articles venaient des lignes du devis : ils doivent maintenant '
            . 'venir du panier.');

        $this->assertSame(2.0, (float) $commande->detailCommande()->first()->qte,
            'La quantité du panier doit être reprise telle quelle.');
    }

    /** DEUX COMMANDES DE SUITE N'ENTRENT PAS EN COLLISION DE NUMÉRO. */
    public function test_deux_commandes_successives_ont_des_numeros_differents(): void
    {
        $client = $this->clientConnecte();

        $numeros = [];

        foreach ([1, 2] as $i) {
            $this->unPanier();
            $this->get('/panier-en-commande');
            $numeros[] = Commande::where('client_id', $client->id)
                ->latest('id')->first()?->numero;
        }

        $this->assertCount(2, array_filter($numeros));
        $this->assertNotSame($numeros[0], $numeros[1],
            'Le numéro était tiré à la création du devis, avec reprise en cas '
            . 'de doublon. La commande doit reprendre ce mécanisme, sinon deux '
            . 'ventes peuvent se disputer le même numéro — la colonne est UNIQUE.');
    }

    /**
     * ET LE PARCOURS « DEMANDER UN DEVIS » EN CRÉE TOUJOURS UN.
     *
     * C'est l'autre bout de la règle : le client garde le moyen d'obtenir un
     * devis quand il en veut un.
     */
    public function test_demander_un_devis_cree_bien_un_devis(): void
    {
        $client = $this->clientConnecte();
        $this->unPanier();

        $avant = Devis::count();

        $this->get('/panier-en-devis');

        $this->assertSame($avant + 1, Devis::count(),
            'Le parcours « Demander un devis » ne crée plus de devis : '
            . 'le client n\'a plus aucun moyen d\'en obtenir un.');

        $devis = Devis::where('client_id', $client->id)->latest('id')->first();

        $this->assertNotNull($devis);
        $this->assertGreaterThan(0, $devis->detailDevis()->count(),
            'Le devis demandé doit porter ses articles.');
    }
}
