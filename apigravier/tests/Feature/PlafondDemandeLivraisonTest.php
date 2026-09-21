<?php

namespace Tests\Feature;

use App\Http\Controllers\LivraisonController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionMethod;
use Tests\TestCase;

/**
 * LE PLAFOND DE CRÉDIT DOIT VALOIR AUSSI POUR LES DEMANDES DE LIVRAISON.
 *
 * Une demande de livraison engage le crédit au même titre qu'une vente ou une
 * location : le camion part, le transport est rendu, et le client à terme
 * paiera plus tard. `Client::encoursCredit()` la compte d'ailleurs déjà dans ce
 * qui est dû — elle amputait donc le plafond des achats suivants sans jamais
 * être bornée elle-même.
 *
 * Le contrôle existait dans `CommandeController` et `LocationController`, et
 * pas dans `LivraisonController`. C'est la troisième fois que ce garde-fou
 * manque sur une voie et une seule : les essais ci-dessous vérifient donc les
 * TROIS contrôleurs ensemble, pour qu'une prochaine voie ajoutée sans lui se
 * signale d'elle-même.
 */
class PlafondDemandeLivraisonTest extends TestCase
{
    use DatabaseTransactions;

    private function corpsDe(string $classe, string $methode): string
    {
        $chemin = app_path('Http/Controllers/' . $classe . '.php');
        $code = file_get_contents($chemin);

        // Commentaires retirés : sinon l'essai trouve les mots qu'il cherche
        // dans l'explication du correctif et passe au vert à tort.
        $code = preg_replace('!/\*.*?\*/!s', '', $code);
        $code = preg_replace('!^\s*//.*$!m', '', $code);

        $debut = strpos($code, 'function ' . $methode . '(');
        $this->assertNotFalse($debut, "$classe::$methode introuvable.");

        $fin = strpos($code, ' function ', $debut + 20);

        return substr($code, $debut, ($fin === false ? strlen($code) : $fin) - $debut);
    }

    /** LA DEMANDE DE LIVRAISON EST DÉSORMAIS BORNÉE. */
    public function test_la_demande_de_livraison_controle_le_plafond(): void
    {
        $corps = $this->corpsDe('LivraisonController', 'enregistrerDemandeLivraison');

        $this->assertStringContainsString('refusPlafondCredit', $corps,
            "La demande de livraison est enregistrée sans vérifier le plafond : "
            . "un client à terme peut engager sans limite.");

        $this->assertLessThan(
            strpos($corps, 'new DemandeLivraison'),
            strpos($corps, 'refusPlafondCredit'),
            "Le contrôle doit précéder la création : refuser après coup "
            . "laisserait la demande enregistrée."
        );
    }

    /** LE MONTANT CONTRÔLÉ EST CELUI QUI ENGAGE, TAXE COMPRISE. */
    public function test_le_montant_controle_porte_la_taxe(): void
    {
        $corps = $this->corpsDe('LivraisonController', 'enregistrerDemandeLivraison');

        // Depuis le 10/09/2026, l'avance disponible du client est déduite du
        // montant contrôlé quand la demande se règle en agence : le montant
        // engagé se calcule à part (montantACredit), toujours sur montantAPayer.
        $this->assertStringContainsString('$montantACredit = self::montantACredit($request, $client, (float) $montantAPayer)', $corps,
            'Le montant engagé se calcule sur montantAPayer, taxe comprise.');
        $this->assertStringContainsString('refusPlafondCredit($client, $montantACredit)', $corps,
            "Le contrôle doit porter sur `montantAPayer` — recalculé côté "
            . "serveur, taxe comprise — et non sur le seul montant hors taxe : "
            . "ce que l'application a affiché ne fait pas foi.");
    }

    /** LES TROIS VOIES SONT GARDÉES : c'est l'oubli d'UNE seule qui a fait défaut. */
    public function test_les_trois_voies_sont_gardees(): void
    {
        $voies = [
            ['CommandeController', 'enregistrerCommande'],
            ['LocationController', 'enregistrerLocation'],
            ['LivraisonController', 'enregistrerDemandeLivraison'],
        ];

        foreach ($voies as [$classe, $methode]) {
            $this->assertStringContainsString(
                'refusPlafondCredit',
                $this->corpsDe($classe, $methode),
                "$classe::$methode engage le crédit sans le borner."
            );
        }
    }

    /** LA RÈGLE EST LA MÊME PARTOUT : mêmes conditions, même formulation. */
    public function test_la_regle_de_refus_est_identique_partout(): void
    {
        foreach (['CommandeController', 'LocationController', 'LivraisonController'] as $classe) {
            $corps = $this->corpsDe($classe, 'refusPlafondCredit');

            $this->assertStringContainsString('!$client->client_a_terme', $corps,
                "$classe : un client ordinaire ne doit pas être bridé, il paie comptant.");

            $this->assertStringContainsString('$disponible === null', $corps,
                "$classe : sans plafond accordé, aucune limite ne doit être "
                . "imposée — un 0 trompeur bloquerait tout.");

            $this->assertStringContainsString('plafondDisponible()', $corps,
                "$classe : le crédit disponible doit venir du modèle, non d'un "
                . 'calcul recopié qui pourrait diverger.');
        }
    }

    /** Le garde-fou existe bien comme méthode, et prend un client nullable. */
    public function test_la_signature_tolere_un_client_absent(): void
    {
        $methode = new ReflectionMethod(LivraisonController::class, 'refusPlafondCredit');
        $parametre = $methode->getParameters()[0];

        $this->assertTrue(
            $parametre->getType()->allowsNull(),
            "Un client introuvable doit laisser passer plutôt que de casser "
            . "l'enregistrement : le plafond ne concerne que les clients à terme."
        );
    }
}
