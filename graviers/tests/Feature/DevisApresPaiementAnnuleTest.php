<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Commande;
use App\Models\Devis;
use App\Models\ModePaiement;
use App\Models\Produit;
use App\Models\User;
use App\Models\Ville;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * UN PAIEMENT ANNULÉ NE FAIT PAS UNE COMMANDE.
 *
 * Constaté le 04/09/2026 : un client lance son règlement, l'annule sur la
 * passerelle — et le devis n° 429808 s'affiche « Commandé » sur l'écran des
 * devis, pour 6 502 000 fcfa dont pas un franc n'a été encaissé.
 *
 * Le devis basculait à « Commandé » AVANT le départ vers la passerelle. La
 * commande, elle, était correctement rangée « en attente de paiement » : les
 * deux écrans se contredisaient.
 *
 * Le panier, lui, reste plein — et c'est voulu : le vider ferait croire au
 * client que sa commande est réglée. Un essai le vérifie aussi, pour que
 * personne ne « corrige » cela un jour.
 */
class DevisApresPaiementAnnuleTest extends TestCase
{
    use DatabaseTransactions;

    private function clientConnecte(): Client
    {
        $user = User::create([
            'nom_prenoms'  => 'Devis Recette',
            'email'        => 'dev_' . uniqid() . '@example.test',
            'login'        => 'dev' . substr((string) uniqid(), -8),
            'password'     => bcrypt(\Illuminate\Support\Str::random(16)),
            'type_user_id' => 4,
            'contact'      => '0700000000',
            'statut'       => \Help::$STATUT_ACTIF,
        ]);

        $client = Client::create([
            'user_id'        => $user->id,
            'nom'            => 'Devis',
            'prenom'         => 'Recette',
            'email'          => $user->email,
            'contact1'       => '0700000000',
            'type_client'    => 'PARTICULIER',
            'client_a_terme' => 0,
            'statut'         => \Help::$STATUT_ACTIF,
        ]);

        $this->actingAs($user);
        URL::forceRootUrl('');

        return $client;
    }

    /** Un montant SOUS le plafond : c'est le paiement en ligne qu'on teste ici. */
    private function amorcerLePanier(int $modeId): void
    {
        $produit = Produit::first();
        $ville   = Ville::first();

        if (!$produit || !$ville) {
            $this->markTestSkipped('Jeu de données insuffisant.');
        }

        Cart::destroy();
        Cart::add($produit->id, $produit->nom, 1, 100000)->associate(Produit::class);

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
            'mode_paiement'  => $modeId,
            'type_livraison' => 1,
            'date_livraison' => now()->addDays(3)->toDateString(),
        ]);
    }

    private function modeEnLigne(): ModePaiement
    {
        return ModePaiement::where('en_ligne', 1)
            ->where('statut', \Help::$STATUT_ACTIF)
            ->firstOrFail();
    }

    /** Lance le parcours jusqu'au départ vers la passerelle. */
    private function partirVersLaPasserelle(): Commande
    {
        $client = $this->clientConnecte();
        $this->amorcerLePanier($this->modeEnLigne()->id);

        // La passerelle ouvre : le client est envoyé chez elle. Ce qu'il y fera
        // ensuite — payer ou annuler — ne regarde pas cette requête.
        Http::fake(['*' => Http::response(
            ['code' => 200, 'url' => 'https://passerelle.test/regler', 'message' => 'https://passerelle.test/regler'], 200)]);

        $this->get('/panier-en-commande');

        $commande = Commande::where('client_id', $client->id)->latest('id')->first();

        $this->assertNotNull($commande, 'La commande doit être enregistrée.');

        return $commande;
    }

    /**
     * UNE COMMANDE DIRECTE NE CRÉE AUCUN DEVIS.
     *
     * C'est la règle demandée le 04/09/2026 : « c'est moi-même qui dois décider
     * de créer un devis, et il y a une possibilité qui existe déjà pour ça ».
     *
     * Le devis n'était qu'un porteur de données vers la commande, mais il était
     * ENREGISTRÉ : chaque vente ajoutait une ligne dans « Liste des devis » —
     * 11 des 21 devis de la base d'essai venaient de là — et, depuis la
     * correction de l'état, dans « Mes devis » côté client.
     */
    public function test_une_commande_directe_ne_cree_aucun_devis(): void
    {
        $devisAvant = Devis::count();

        $commande = $this->partirVersLaPasserelle();

        $this->assertSame(\Help::$COMMANDE_EN_ATTENTE_PAIEMENT, $commande->etat_commande,
            'La commande doit rester en attente de paiement.');

        $this->assertSame($devisAvant, Devis::count(),
            'Une commande directe crée encore un devis : la liste des devis se '
            . 'remplit toute seule, et le client en voit un qu\'il n\'a pas demandé.');

        $this->assertNull($commande->devis_id,
            'La commande directe ne doit se rattacher à aucun devis.');

        $this->assertNotEmpty($commande->numero,
            'La commande doit porter son propre numéro, qui venait du devis.');

        $this->assertGreaterThan(0, $commande->detailCommande()->count(),
            'Les articles doivent venir du panier, puisqu\'il n\'y a plus de '
            . 'lignes de devis pour les porter.');
    }

    /**
     * LE PANIER RESTE PLEIN — ET C'EST VOULU.
     *
     * Il était vidé avant le départ vers la passerelle : un client qui fermait
     * la page de paiement retrouvait son panier vide, comme si la commande
     * avait été réglée.
     */
    public function test_le_panier_n_est_pas_vide_avant_le_paiement(): void
    {
        $this->partirVersLaPasserelle();

        $this->assertGreaterThan(0, Cart::count(),
            'Le panier a été vidé avant tout encaissement : le client croira '
            . 'sa commande réglée.');
    }

    /**
     * ET QUAND LE PAIEMENT EST CONFIRMÉ, LE DEVIS DEVIENT « COMMANDÉ ».
     *
     * Sans cela, la correction ci-dessus laisserait tous les devis en attente
     * pour toujours : on aurait échangé un défaut contre un autre.
     */
    public function test_le_paiement_confirme_fait_passer_le_devis_en_commande(): void
    {
        // CE QUI RESTE VRAI DU PARCOURS « DEMANDER UN DEVIS ».
        //
        // Une commande directe ne crée plus de devis, mais celui que le client
        // a demandé lui-même doit toujours passer « Commandé » — et seulement
        // quand le règlement est confirmé.
        $client = $this->clientConnecte();

        $produit = \App\Models\Produit::where('type_affaire', 'VENTE')->first();

        if (!$produit) {
            $this->markTestSkipped('Aucun produit de vente.');
        }

        $devis = Devis::create([
            'numero'         => 'TST' . substr((string) uniqid(), -7),
            'client_id'      => $client->id,
            'montant'        => 100000,
            'date_livraison' => now()->addDay()->toDateString(),
            'mode_paiement_id' => $this->modeEnLigne()->id,
            'statut'         => \Help::$STATUT_ACTIF,
        ]);

        \App\Models\DetailDevis::create([
            'devis_id'   => $devis->id,
            'produit_id' => $produit->id,
            'qte'        => 1,
            'prix'       => 100000,
            'statut'     => \Help::$STATUT_ACTIF,
        ]);

        session()->put(['mode_paiement' => $this->modeEnLigne()->id]);

        Http::fake(['*' => Http::response(
            ['code' => 200, 'url' => 'https://passerelle.test/regler',
             'message' => 'https://passerelle.test/regler'], 200)]);

        $this->get(route('client.panierCommande', $devis));

        $commande = Commande::where('devis_id', $devis->id)->latest('id')->first();

        $this->assertNotNull($commande, 'Le devis doit être devenu une commande.');

        $this->assertSame(\Help::$STATUT_ACTIF, (int) $devis->fresh()->statut,
            'Le devis passe « Commandé » avant que le paiement soit confirmé.');

        // `ligne_paiement` porte elle-même le service et le code : la colonne
        // s'appelle `code_paiement`, pas `code`. Chercher la mauvaise faisait
        // sauter cet essai — et un essai sauté ne protège rien.
        $ligne = \App\Models\LignePaiement::where('service', \Help::$COMMANDE)
            ->where('service_id', $commande->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($ligne,
            'Le parcours doit avoir créé la ligne de paiement de la commande.');

        (new \App\Http\Controllers\PaiementEnLigne())
            ->marquerPaiementEffectue($ligne->code_paiement, 'REF-RECETTE', 'CARTE');

        $this->assertSame(2, (int) Devis::find($commande->devis_id)->statut,
            'Le devis doit devenir « Commandé » dès que le paiement est confirmé.');

        $this->assertSame(\Help::$COMMANDE_EN_ATTENTE,
            Commande::find($commande->id)->etat_commande,
            'La commande payée doit entrer dans la file de traitement.');
    }
}
