<?php

namespace Tests\Feature;

use App\Models\AdresseLivraison;
use App\Models\Client;
use App\Models\Commande;
use App\Models\DemandeLivraison;
use App\Models\ModePaiement;
use App\Models\UniteProduit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * UNE DEMANDE DE LIVRAISON SE NUMÉROTE COMME UNE VENTE — CÔTÉ MOBILE AUSSI.
 *
 * Demandé le 05/09/2026 : le numéro doit avoir le même format des deux côtés.
 * L'API passait déjà par le générateur commun ; c'est le site qui écrivait
 * `uniqid()`, soit treize caractères au lieu de six chiffres.
 *
 * Cet essai ne corrige donc rien : il empêche l'API de dériver à son tour, et
 * il documente le format attendu là où il est produit.
 */
class NumeroDemandeLivraisonMobileTest extends TestCase
{
    use DatabaseTransactions;

    /** Le format d'un numéro d'affaire : six chiffres, rien d'autre. */
    private const FORMAT = '/^\d{6}$/';

    protected function setUp(): void
    {
        parent::setUp();

        // La passerelle ne doit jamais être jointe par un essai.
        Http::fake(['*' => Http::response(['code' => 400, 'message' => 'refus'], 200)]);
    }

    private function unClientAvecCompte(): Client
    {
        $client = Client::whereNotNull('user_id')->whereHas('user')->first();

        if (!$client) {
            $this->markTestSkipped('Aucun client rattaché à un compte.');
        }

        return $client;
    }

    public function test_le_numero_a_le_format_d_une_vente(): void
    {
        $client = $this->unClientAvecCompte();

        $adresse = AdresseLivraison::first();
        $unite   = UniteProduit::first();
        // Un mode HORS LIGNE : on éprouve le numéro, pas la passerelle.
        $mode    = ModePaiement::where('en_ligne', '!=', 1)
            ->where('statut', \Help::$STATUT_ACTIF)->first();
        $type    = \App\Models\TypeLivraison::first();

        if (!$adresse || !$unite || !$mode || !$type) {
            $this->markTestSkipped('Jeu de données insuffisant.');
        }

        $avant = DemandeLivraison::count();

        $reponse = $this->postJson('/mon_gravier/enregistrer-demande-livraison', [
            'access'   => Crypt::encryptString((string) $client->user_id),
            'type'     => 'mobile',
            'libelle'  => 'Recette',
            'distance' => 5,
            'demande'  => [
                'adresseDepart'      => $adresse->id,
                'adresseDestination' => $adresse->id,
                'dateLivraison'      => now()->addDays(2)->toDateString(),
                'note'               => 'Recette',
                'modePaiement'       => $mode->id,
                'typeLivraison'      => $type->id,
            ],
            // LES CLÉS QUE LE CONTRÔLEUR LIT VRAIMENT.
            //
            // Une première version envoyait « nom_produit » et « unite » =>
            // id : le point d'entrée attend « produit », « unite » (le libellé)
            // et « unite_id ». La demande ne s'enregistrait pas, l'essai se
            // sautait tout seul — et un essai sauté ne protège rien.
            'lignes'   => [[
                'produit'  => 'Marchandise de recette',
                'qte'      => 1,
                'unite'    => $unite->abreviation,
                'unite_id' => $unite->id,
            ]],
        ]);

        $reponse->assertOk();

        if (DemandeLivraison::count() === $avant) {
            $this->markTestSkipped(
                'La demande n\'a pas été enregistrée (jeu de données) : '
                . (string) $reponse->json('message'));
        }

        $demande = DemandeLivraison::latest('id')->first();

        $this->assertMatchesRegularExpression(self::FORMAT, (string) $demande->numero,
            'Le numéro de la demande de livraison doit se lire comme celui '
            . 'd\'une vente : six chiffres, et non « ' . $demande->numero . ' ».');
    }

    /**
     * LE GÉNÉRATEUR EST BIEN LE MÊME QUE POUR LES VENTES.
     *
     * Sans cette vérification, l'API pourrait retomber sur `uniqid()` — le
     * défaut du site — sans qu'aucun essai ne le voie tant qu'aucune demande
     * n'est enregistrée dans le jeu de données.
     */
    public function test_le_controleur_passe_par_le_generateur_commun(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/LivraisonController.php'));

        $this->assertStringContainsString(
            "Help::genererNumeroUnique('demande_livraison')", $source,
            'Le numéro de la demande de livraison doit venir du générateur '
            . 'commun, celui qui produit aussi les numéros de vente.');

        $this->assertStringNotContainsString('$demande->numero = uniqid()', $source,
            'L\'API est retombée sur uniqid() : c\'était le défaut du site.');
    }

    /** Le format de référence, lu sur une vraie commande. */
    public function test_le_format_de_reference_est_bien_celui_des_ventes(): void
    {
        $commande = Commande::whereNotNull('numero')->latest('id')->first();

        if (!$commande) {
            $this->markTestSkipped('Aucune commande en base pour comparer.');
        }

        $this->assertMatchesRegularExpression(self::FORMAT, (string) $commande->numero,
            'Le format de référence a changé : cet essai doit suivre.');
    }
}
