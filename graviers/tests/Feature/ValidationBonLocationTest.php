<?php

namespace Tests\Feature;

use App\Models\Enlevement;
use App\Models\Fournisseur;
use App\Models\Livraison;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * LE FOURNISSEUR DOIT POUVOIR VALIDER LE BON D'UNE LOCATION.
 *
 * Constaté le 28/08/2026 : « Ce bon n'est plus rattaché à une livraison
 * valide. Contactez le gestionnaire. »
 *
 * Toute la méthode `bonValidation` était écrite pour les VENTES : elle lit
 * `livraison->detailCommande`, puis joint `commande` et `detail_commande`. Or,
 * pour une course de LOCATION, `livraison.detail_commande_id` porte
 * l'identifiant d'un `detail_location` — la convention est posée à la création
 * de la course. La relation ne trouvait rien, et le contrôle refusait.
 *
 * ⚠ LE REFUS NOUS A PROTÉGÉS PAR CHANCE. Si un `detail_commande` avait porté
 *   le même identifiant, la relation aurait rendu la ligne d'UNE AUTRE
 *   COMMANDE : le contrôle serait passé, et la validation aurait écrit l'état
 *   de livraison et la quantité servie sur la commande d'un autre client.
 *   C'est ce que ces essais empêchent de revenir.
 */
class ValidationBonLocationTest extends TestCase
{
    use DatabaseTransactions;

    private function sourceValidation(): string
    {
        $source = file_get_contents(app_path('Http/Controllers/SellerController.php'));

        // Commentaires retirés : sans cela, un essai trouve les mots qu'il
        // cherche dans l'explication du correctif et passe au vert alors que le
        // code exécuté a disparu.
        $code = preg_replace('!/\*.*?\*/!s', '', $source);
        $code = preg_replace('!^\s*//.*$!m', '', $code);

        $debut = strpos($code, 'function bonValidation(');
        $fin   = strpos($code, 'public function', $debut + 10);

        return substr($code, $debut, ($fin === false ? strlen($code) : $fin) - $debut);
    }

    /** LA LOCATION EST AIGUILLÉE AVANT TOUTE LECTURE DE `detailCommande`. */
    public function test_la_location_ne_passe_jamais_par_le_chemin_des_ventes(): void
    {
        $code = $this->sourceValidation();

        $aiguillage = strpos($code, "provenance === Help::\$LOCATION");
        $lecture    = strpos($code, 'detailCommande');

        $this->assertNotFalse($aiguillage,
            "Aucun aiguillage sur la provenance : le bon d'une location repart "
            . 'sur le chemin des ventes et se fait refuser.');

        $this->assertLessThan($lecture, $aiguillage,
            'La provenance est examinée APRÈS la lecture de `detailCommande` : '
            . "pour une location, cette relation désigne un tout autre objet — au "
            . "mieux rien, au pire la ligne d'un autre client.");
    }

    /** LE STOCK N'EST PAS RESTITUÉ SUR UNE LOCATION. */
    public function test_la_branche_location_ne_touche_pas_au_stock(): void
    {
        $code = $this->sourceValidation();

        $debut = strpos($code, "provenance === Help::\$LOCATION");
        $fin   = strpos($code, "detailCommande == null", $debut);
        $branche = substr($code, $debut, $fin - $debut);

        $this->assertStringNotContainsString('$produit->update(', $branche,
            "La branche location restitue du stock. Or la validation d'une "
            . "location NE DÉCRÉMENTE PAS le stock : restituer gonflerait une "
            . 'quantité jamais retirée.');

        $this->assertStringContainsString('crediterFournisseur(', $branche,
            "Le fournisseur n'est pas crédité : son solde plafonne ses demandes "
            . 'de paiement, il ne pourrait rien réclamer.');
    }

    /** LES DEUX CHEMINS PARTAGENT LA MÊME RÈGLE DE CRÉDIT. */
    public function test_le_credit_est_calcule_au_meme_endroit_pour_les_deux(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/SellerController.php'));
        $code = preg_replace('!/\*.*?\*/!s', '', $source);
        $code = preg_replace('!^\s*//.*$!m', '', $code);

        $this->assertSame(1, substr_count($code, 'private function crediterFournisseur('),
            'La méthode de crédit doit exister une fois et une seule.');

        $this->assertSame(2, substr_count($code, '$this->crediterFournisseur('),
            'Les deux chemins — vente et location — doivent appeler la même '
            . 'méthode. Deux copies finiraient par diverger, et le solde du '
            . 'fournisseur avec elles.');
    }

    /**
     * LE CAS RÉEL : un bon de location validé par son fournisseur.
     *
     * On reconstitue une course de location portant un bon, puis on valide
     * comme le ferait le fournisseur depuis son espace.
     */
    public function test_un_bon_de_location_se_valide_vraiment(): void
    {
        $modele = Livraison::first();
        $fournisseur = Fournisseur::with('user')->whereHas('user')->first();

        if (!$modele || !$fournisseur) {
            $this->markTestSkipped('Base de travail sans livraison ou sans fournisseur.');
        }

        $ligne = \App\Models\DetailLocation::first();

        if (!$ligne) {
            $this->markTestSkipped('Base de travail sans ligne de location.');
        }

        $course = $modele->replicate();
        $course->numero = 'ESSAI-LOC-' . substr((string) uniqid(), -8);
        $course->provenance = \Help::$LOCATION;
        $course->detail_commande_id = $ligne->id;
        $course->livre_par = 1;
        $course->etat_livraison = \Help::$LIVRAISON_EN_ATTENTE;
        $course->save();

        $bon = Enlevement::create([
            'fournisseur_id'   => $fournisseur->id,
            'livraison_id'     => $course->id,
            'produit_id'       => $ligne->produit_id,
            'qte'              => 2,
            'prix_fournisseur' => 105,      // 15 F/jour × 7 jours
            'code_enleve'      => 'ENVESSAI' . substr((string) uniqid(), -4),
            'statut'           => \Help::$STATUT_ACTIF,
        ]);

        $soldeAvant = (float) $fournisseur->solde;

        URL::forceRootUrl('');
        $reponse = $this->actingAs($fournisseur->user)
            ->post('/seller/bon/validation/' . $bon->code_enleve, ['qteServi' => 2]);

        $reponse->assertRedirect();
        $this->assertNotEquals(
            "Ce bon n'est plus rattaché à une livraison valide. Contactez le gestionnaire.",
            session('error'),
            'Le bon de location est encore refusé.'
        );

        $bon->refresh();

        $this->assertNotNull($bon->fournisseur_validation,
            "Le bon n'a pas été marqué validé par le fournisseur : la course "
            . 'restera inclôturable pour le livreur.');

        $this->assertSame(2.0, (float) $bon->qte_servi);

        $this->assertGreaterThan($soldeAvant, (float) $fournisseur->fresh()->solde,
            "Le solde du fournisseur n'a pas bougé : il ne pourra rien réclamer "
            . "pour un matériel qu'il a pourtant remis.");
    }
}
