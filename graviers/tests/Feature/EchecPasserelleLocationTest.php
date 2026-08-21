<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Location;
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
 * Quand la passerelle de paiement refuse d'ouvrir, le client doit l'apprendre.
 *
 * Choisir « Wave » sur une location menait à une location ENREGISTRÉE, sans
 * passerelle et sans un mot d'explication : le message d'échec renvoyé par la
 * plateforme de paiement était purement et simplement jeté, et rien n'était
 * journalisé. Le client repartait avec une location qui paraissait confirmée
 * alors qu'aucun franc n'avait été encaissé, et personne ne pouvait dire
 * pourquoi la passerelle ne s'était pas ouverte.
 *
 * Le circuit des COMMANDES avertit le client depuis toujours dans ce cas ;
 * celui des locations se taisait.
 */
class EchecPasserelleLocationTest extends TestCase
{
    use DatabaseTransactions;

    private function unClientConnecte(int $aTerme = 0): Client
    {
        $user = User::create([
            'nom_prenoms'  => 'Locataire Recette',
            'email'        => 'loc_' . uniqid() . '@example.test',
            'login'        => 'loc' . substr((string) uniqid(), -8),
            'password'     => bcrypt(\Illuminate\Support\Str::random(16)),
            'type_user_id' => 4,
            'contact'      => '0788888888',
            'statut'       => \Help::$STATUT_ACTIF,
        ]);

        $client = Client::create([
            'user_id'        => $user->id,
            'nom'            => 'Locataire',
            'prenom'         => 'Recette',
            'email'          => $user->email,
            'contact1'       => '0788888888',
            'type_client'    => 'PARTICULIER',
            'client_a_terme' => $aTerme,
            'statut'         => \Help::$STATUT_ACTIF,
        ]);

        $this->actingAs($user);

        return $client;
    }

    /** L'état de session que laisse le parcours de location avant validation. */
    private function amorcerLeParcours(int $modePaiementId): Produit
    {
        $produit = Produit::where('type_affaire', 'LOCATION')->first() ?? Produit::first();
        $ville   = Ville::first();

        if (!$produit || !$ville) {
            $this->markTestSkipped('Jeu de données insuffisant.');
        }

        Cart::destroy();
        Cart::add($produit->id, $produit->nom, 1, 100)->associate(Produit::class);

        session()->put([
            '0' => [
                'ville'        => $ville->id,
                'long'         => 0,
                'lat'          => 0,
                'infoSup'      => 'Adresse de recette',
                'montantTTC'   => 236,
                'tva'          => 36,
                'cout_livraison' => 0,
                'estLivrable'  => 'oui',
            ],
            'totalLocation' => 200,
            'remise'        => 0,
            'mode_paiement' => $modePaiementId,
            'debuts'        => [now()->addDay()->toDateString()],
            'fins'          => [now()->addDays(3)->toDateString()],
            'nbre_jour'     => [2],
        ]);

        return $produit;
    }

    private function unModeEnLigne(): ModePaiement
    {
        $mode = ModePaiement::where('en_ligne', 1)->where('statut', 1)->first();

        if (!$mode) {
            $this->markTestSkipped('Aucun mode de paiement en ligne.');
        }

        return $mode;
    }

    public function test_un_echec_de_la_passerelle_est_annonce_au_client(): void
    {
        $client = $this->unClientConnecte();
        $mode   = $this->unModeEnLigne();
        $this->amorcerLeParcours($mode->id);

        // La plateforme répond, mais refuse : clé invalide, plafond, etc.
        Http::fake([
            '*' => Http::response(['code' => 400, 'message' => 'Cle marchand invalide'], 200),
        ]);

        URL::forceRootUrl('');
        $reponse = $this->get('/enregistrement-location-client');

        $reponse->assertOk();

        // Le message était jeté : le client ne voyait rien.
        $reponse->assertSee("Le paiement en ligne n'a pas pu démarrer.", false);
        $reponse->assertSee('Cle marchand invalide', false);
    }

    public function test_la_location_creee_apres_un_echec_n_est_pas_payee(): void
    {
        $client = $this->unClientConnecte();
        $mode   = $this->unModeEnLigne();
        $this->amorcerLeParcours($mode->id);

        Http::fake([
            '*' => Http::response(['code' => 400, 'message' => 'Cle marchand invalide'], 200),
        ]);

        URL::forceRootUrl('');
        $this->get('/enregistrement-location-client')->assertOk();

        $location = Location::where('client_id', $client->id)->latest('id')->first();

        $this->assertNotNull($location, 'La location doit être conservée : les dates ne sont pas perdues.');

        // 3 = soldée (cf. le contrôle de validation en back-office). Une location
        // dont le paiement a échoué ne peut pas être réputée payée.
        $this->assertNotSame(3, (int) $location->statut);
    }

    public function test_la_passerelle_reste_prioritaire_quand_elle_repond(): void
    {
        $client = $this->unClientConnecte();
        $mode   = $this->unModeEnLigne();
        $this->amorcerLeParcours($mode->id);

        // Cas nominal : la plateforme ouvre bien la page de paiement.
        Http::fake([
            '*' => Http::response(['code' => 200, 'url' => 'https://paiement.test/aller'], 200),
        ]);

        URL::forceRootUrl('');
        $reponse = $this->get('/enregistrement-location-client');

        // On part vers la passerelle, et AUCUNE location n'est créée avant
        // confirmation du paiement.
        $reponse->assertRedirect('https://paiement.test/aller');
        $this->assertNull(Location::where('client_id', $client->id)->first());
    }

    public function test_un_mode_hors_ligne_ne_passe_pas_par_la_passerelle(): void
    {
        $client = $this->unClientConnecte();

        $mode = ModePaiement::where('en_ligne', 0)->where('statut', 1)->first();

        if (!$mode) {
            $this->markTestSkipped('Aucun mode de paiement hors ligne.');
        }

        $this->amorcerLeParcours($mode->id);

        Http::fake();

        URL::forceRootUrl('');
        $reponse = $this->get('/enregistrement-location-client');

        $reponse->assertOk();
        // Aucun avertissement : rien n'a échoué, le règlement se fait en agence.
        $reponse->assertDontSee("Le paiement en ligne n'a pas pu démarrer.", false);

    }

    public function test_un_client_a_terme_qui_choisit_wave_va_a_la_passerelle(): void
    {
        // L'ecran propose le selecteur de mode a TOUT LE MONDE. Son choix etait
        // accepte puis oublie : aucun mode n'etait retrouve plus loin, la
        // passerelle n'etait meme pas tentee, et la location partait sans un mot.
        //
        // Le circuit des commandes a tranche : « Le client a terme n'est plus
        // exclu : s'il a choisi un mode en ligne, il regle en ligne. »
        $client = $this->unClientConnecte(aTerme: 1);
        $mode   = $this->unModeEnLigne();
        $this->amorcerLeParcours($mode->id);

        Http::fake([
            '*' => Http::response(['code' => 200, 'url' => 'https://paiement.test/aller'], 200),
        ]);

        URL::forceRootUrl('');
        $reponse = $this->get('/enregistrement-location-client');

        $reponse->assertRedirect('https://paiement.test/aller');
        $this->assertNull(Location::where('client_id', $client->id)->first());
    }

    public function test_un_client_a_terme_en_mode_hors_ligne_ne_paie_pas_en_ligne(): void
    {
        $client = $this->unClientConnecte(aTerme: 1);

        $mode = ModePaiement::where('en_ligne', 0)->where('statut', 1)->first();

        if (!$mode) {
            $this->markTestSkipped('Aucun mode de paiement hors ligne.');
        }

        $this->amorcerLeParcours($mode->id);

        Http::fake();

        URL::forceRootUrl('');
        $reponse = $this->get('/enregistrement-location-client');

        // Le credit reste le credit : la location est enregistree, sans passerelle.
        $reponse->assertOk();
        $this->assertNotNull(Location::where('client_id', $client->id)->first());
    }

    public function test_une_passerelle_non_configuree_est_annoncee(): void
    {
        $client = $this->unClientConnecte();
        $mode   = $this->unModeEnLigne();
        $this->amorcerLeParcours($mode->id);

        // Rien n'etait verifie : on partait appeler une adresse vide.
        config(['paysecure.url' => null]);

        Http::fake();

        URL::forceRootUrl('');
        $reponse = $this->get('/enregistrement-location-client');

        $reponse->assertOk();
        $reponse->assertSee("Le paiement en ligne n'a pas pu démarrer.", false);
        $reponse->assertSee("pas configuré sur ce serveur", false);
    }

}
