<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ModePaiement;
use App\Models\Produit;
use App\Models\TypeLivraison;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * LE RÉCAPITULATIF DE LOCATION NE DOIT PLUS TOMBER EN PAGE BLANCHE.
 *
 * Signalé le 03/09/2026 : POST /recapitulatif-de-la-location → 500.
 *
 * DEUX CAUSES DISTINCTES, toutes deux couvertes ici :
 *
 *  1. UNE CLASSE NEUVE NON DÉPLOYÉE. Le contrôleur réclame
 *     `App\Support\BonDeCommandeJoint`, créée le 03/09/2026. Si ce fichier
 *     n'arrive pas sur le serveur, la page tombe en 500 — et seulement pour
 *     les clients ENTREPRISE, ce qui la rend d'autant plus difficile à voir.
 *
 *  2. UNE CLÉ DE SESSION SUPPOSÉE PRÉSENTE. `session('0')['ville']` était lu
 *     sans garde. Or l'étape du mode de paiement RÉÉCRIT ce bloc
 *     (`$tab = session('0') ?: []`) : si la session a expiré entre les deux
 *     étapes, ou si l'on reprend le tunnel en cours de route, il ne reste que
 *     le montant. La suite du code prévoyait déjà une ville absente
 *     (« $ville == null ? null : $ville->nom ») — c'est la LECTURE qui
 *     refusait de l'admettre.
 */
class RecapitulatifLocationTest extends TestCase
{
    use DatabaseTransactions;

    private function unClient(string $type): ?Client
    {
        return Client::where('type_client', $type)->whereNotNull('user_id')->first();
    }

    private function remplirLePanier(): void
    {
        $produit = Produit::where('type_affaire', 'LOCATION')->first() ?: Produit::first();

        Cart::destroy();
        // `associate` est indispensable : la vue lit `$produit->model->nom`.
        Cart::add($produit->id, $produit->nom, 1, $produit->prix_moyen ?: 1000,
            ['type' => 2, 'cout_livraison' => 0])->associate(Produit::class);
    }

    /**
     * LE CAS EXACT DU 03/09/2026 : une session qui a perdu son adresse.
     */
    public function test_une_session_sans_adresse_ne_fait_plus_page_blanche(): void
    {
        $client = $this->unClient('ENTREPRISE') ?: $this->unClient('PARTICULIER');

        if (!$client) {
            $this->markTestSkipped('Aucun client en base.');
        }

        $this->remplirLePanier();

        // Ce que laisse l'étape du mode de paiement quand l'adresse a disparu :
        // le montant, et rien d'autre.
        $this->withSession([
            '0' => ['tva' => 100, 'montantTTC' => 1100],
            'totalLocation' => 1000,
            'debuts' => [date('Y-m-d')],
            'fins' => [date('Y-m-d')],
            'nbre_jour' => [1],
        ]);

        $reponse = $this->actingAs($client->user)->post('/recapitulatif-de-la-location', [
            'mode' => ModePaiement::first()?->id,
            'type_livraison' => TypeLivraison::first()?->id,
            'numero_bon' => 'BC-CONTROLE',
        ]);

        $this->assertLessThan(500, $reponse->getStatusCode(),
            'Le récapitulatif tombe en 500 quand la session a perdu son '
            . 'adresse : écran blanc, et le client ne peut plus louer.');
    }

    /**
     * LA CLASSE RÉCLAMÉE PAR LE CONTRÔLEUR DOIT EXISTER.
     *
     * Une classe créée le jour même et absente du serveur produit exactement
     * ce défaut : 500 sur cette seule route, sans que rien d'autre ne bouge.
     * Cet essai le dit AVANT la mise en ligne.
     */
    public function test_les_classes_maison_reclamees_existent_toutes(): void
    {
        $sources = [];

        foreach ([app_path(), resource_path('views')] as $racine) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($racine));

            foreach ($it as $f) {
                if (!$f->isFile() || !str_ends_with($f->getFilename(), '.php')) {
                    continue;
                }
                $sources[$f->getPathname()] = file_get_contents($f->getPathname());
            }
        }

        $introuvables = [];

        foreach ($sources as $chemin => $code) {
            preg_match_all('/\\\\App\\\\(Support|Services)\\\\([A-Za-z0-9_]+)/', $code, $m);

            foreach ($m[0] as $i => $tout) {
                $classe = 'App\\' . $m[1][$i] . '\\' . $m[2][$i];

                if (!class_exists($classe)) {
                    $introuvables[] = basename($chemin) . ' réclame ' . $classe;
                }
            }
        }

        $this->assertSame([], array_values(array_unique($introuvables)),
            'Ces classes sont réclamées par du code livré mais introuvables : '
            . 'la page qui les appelle tombera en 500 sur le serveur.');
    }
}
