<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Commande;
use App\Models\DemandeLivraison;
use App\Models\ModePaiement;
use App\Models\Produit;
use App\Models\TypeLivraison;
use App\Models\UniteProduit;
use App\Models\User;
use App\Models\Ville;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * UNE DEMANDE DE LIVRAISON SE NUMÉROTE COMME UNE VENTE.
 *
 * Demandé le 05/09/2026 : « le numéro de la demande de livraison doit être
 * identique, même format, à celui d'une vente — sur le web et sur le mobile ».
 *
 * Le site écrivait `uniqid()` : treize caractères comme « 6a999a12546cb »,
 * quand une commande porte six chiffres. Deux affaires du même client, côte à
 * côte dans les listes, ne se lisaient pas de la même façon.
 *
 * L'application, elle, passait déjà par le générateur commun
 * (apigravier/LivraisonController) : c'est le site qui divergeait.
 */
class NumeroDemandeLivraisonTest extends TestCase
{
    use DatabaseTransactions;

    /** Le format d'un numéro d'affaire : six chiffres, rien d'autre. */
    private const FORMAT = '/^\d{6}$/';

    private function clientConnecte(): Client
    {
        $user = User::create([
            'nom_prenoms'  => 'Numero Recette',
            'email'        => 'num_' . uniqid() . '@example.test',
            'login'        => 'num' . substr((string) uniqid(), -8),
            'password'     => bcrypt(\Illuminate\Support\Str::random(16)),
            'type_user_id' => 4,
            'contact'      => '0700000000',
            'statut'       => \Help::$STATUT_ACTIF,
        ]);

        $client = Client::create([
            'user_id'        => $user->id,
            'nom'            => 'Numero',
            'prenom'         => 'Recette',
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

    /** Tout ce que le formulaire de demande de livraison dépose en session. */
    private function sessionDemande(): array
    {
        $ville = Ville::first();
        $unite = UniteProduit::first();
        $type  = TypeLivraison::first();
        $mode  = ModePaiement::where('en_ligne', '!=', 1)
            ->where('statut', \Help::$STATUT_ACTIF)->first();

        if (!$ville || !$unite || !$type || !$mode) {
            $this->markTestSkipped('Jeu de données insuffisant.');
        }

        return [
            'villePec'       => $ville->id,
            'villeDest'      => $ville->id,
            'affichagePec'   => 'Départ de recette',
            'affichageDest'  => 'Arrivée de recette',
            'longPec'        => 0,
            'latPec'         => 0,
            'longDest'       => 0,
            'latDest'        => 0,
            'paiement'       => $mode->id,
            'type_livraison' => $type->libelle,
            'date'           => now()->addDays(3)->toDateString(),
            'libelle'        => 'Recette',
            'description'    => 'Recette',
            'montant_total'  => 50000,
            'montantTva'     => 9000,
            'unite'          => $unite->abreviation,
            'produits'       => [[
                'nom_produit' => 'Marchandise de recette',
                'qte'         => 1,
                'unite'       => $unite->id,
                'desc'        => 'Recette',
            ]],
        ];
    }

    public function test_le_numero_d_une_demande_de_livraison_a_le_format_d_une_vente(): void
    {
        $this->clientConnecte();

        $avant = DemandeLivraison::count();

        $this->withSession($this->sessionDemande())
            ->get('/client-Validation-de-la-demande-de-livraison');

        $this->assertSame($avant + 1, DemandeLivraison::count(),
            'La demande de livraison doit être enregistrée.');

        $demande = DemandeLivraison::latest('id')->first();

        $this->assertMatchesRegularExpression(self::FORMAT, (string) $demande->numero,
            'Le numéro de la demande de livraison n\'a pas le format d\'une '
            . 'vente : le site écrivait `uniqid()`, soit « '
            . $demande->numero . ' » au lieu de six chiffres.');
    }

    /**
     * LE MÊME FORMAT QUE CELUI D'UNE VENTE, pas seulement « six chiffres ».
     *
     * On compare à un numéro de commande réellement présent en base : si le
     * format des ventes change un jour, cet essai le dira au lieu de figer
     * une règle en double.
     */
    public function test_les_deux_numeros_se_lisent_de_la_meme_facon(): void
    {
        $commande = Commande::whereNotNull('numero')->latest('id')->first();

        if (!$commande) {
            $this->markTestSkipped('Aucune commande en base pour comparer.');
        }

        $this->assertMatchesRegularExpression(self::FORMAT, (string) $commande->numero,
            'Le format de référence a changé : cet essai doit suivre.');

        $this->clientConnecte();

        $this->withSession($this->sessionDemande())
            ->get('/client-Validation-de-la-demande-de-livraison');

        $demande = DemandeLivraison::latest('id')->first();

        $this->assertSame(
            strlen((string) $commande->numero),
            strlen((string) $demande->numero),
            'Une demande de livraison et une vente doivent porter des numéros '
            . 'de même longueur : côte à côte dans une liste, ils doivent se '
            . 'lire de la même façon.');
    }

    /**
     * DEUX DEMANDES DE SUITE NE PORTENT PAS LE MÊME NUMÉRO.
     *
     * `uniqid()` ne pouvait pas se répéter ; un tirage à six chiffres, si.
     * Le générateur commun vérifie la table avant de rendre son numéro — et
     * c'est cette vérification qu'on éprouve ici.
     */
    public function test_deux_demandes_successives_ont_des_numeros_differents(): void
    {
        $this->clientConnecte();

        $numeros = [];

        foreach ([1, 2] as $i) {
            $this->withSession($this->sessionDemande())
                ->get('/client-Validation-de-la-demande-de-livraison');
            $numeros[] = DemandeLivraison::latest('id')->first()?->numero;
        }

        $this->assertCount(2, array_filter($numeros));
        $this->assertNotSame($numeros[0], $numeros[1],
            'Deux demandes de livraison portent le même numéro.');
    }
}
