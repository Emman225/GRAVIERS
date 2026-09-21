<?php

namespace Tests\Feature;

use App\Models\Enlevement;
use App\Models\Livraison;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * LES TROIS ÉCRANS DU CHIFFRE D'AFFAIRES DISENT LA MÊME CHOSE.
 *
 * Constaté le 04/09/2026 : « Marge brute HT −732 020 fcfa » sur « CA détaillé »
 * — le chiffre exact que « Récapitulatif des ventes » venait de cesser
 * d'afficher. La règle avait été corrigée sur un écran et recopiée sur aucun
 * des deux autres.
 *
 * Ces essais mettent la règle en défaut là où elle est le plus fragile : sur
 * une location, et sur un bon dont la ligne de commande a disparu.
 */
class ChiffreAffaireCoherentTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN])
            ->where('statut', 1)->firstOrFail();
    }

    /** La période qui couvre tous les bons servis de la base. */
    private function periode(): ?string
    {
        $b = DB::selectOne('SELECT MIN(DATE(fournisseur_validation)) d,
                                   MAX(DATE(fournisseur_validation)) f
                            FROM enlevement
                            WHERE fournisseur_validation IS NOT NULL AND statut = 1');

        return $b && $b->d ? ('?du=' . $b->d . '&au=' . $b->f) : null;
    }

    private function donnees(string $url, string $cle)
    {
        $reponse = $this->actingAs($this->admin())->get($url);
        $reponse->assertOk();

        return $reponse->original->getData()[$cle];
    }

    /**
     * UN BON QUI COMPTE VRAIMENT, pas un déjà mis de côté.
     *
     * Une première version prenait le premier bon venu : en base d'essai
     * c'était un bon déjà orphelin, donc déjà hors des totaux. Le déclarer
     * location ne changeait rien et l'essai ne mesurait plus rien.
     */
    private function bonRattache(): ?Enlevement
    {
        return Enlevement::whereNotNull('fournisseur_validation')
            ->where('statut', \Help::$STATUT_ACTIF)
            ->whereNotNull('livraison_id')
            ->get()
            ->first(function ($b) {
                $d = optional($b->livraison)->detailCommande;

                return $d && $d->commande_id && $b->produit_id;
            });
    }

    public function test_une_location_ne_compte_pas_dans_le_ca_detaille(): void
    {
        if (!$p = $this->periode()) {
            $this->markTestSkipped('Aucun bon servi en base.');
        }

        $bon = $this->bonRattache();

        if (!$bon) {
            $this->markTestSkipped('Aucun bon servi rattaché à une ligne de commande.');
        }

        $avant = $this->donnees('/CA-detaille' . $p, 'totalCout');

        // Exactement ce que fait la validation d'une location.
        Livraison::where('id', $bon->livraison_id)->update(['provenance' => \Help::$LOCATION]);

        $apres = $this->donnees('/CA-detaille' . $p, 'totalCout');

        $this->assertLessThan($avant, $apres,
            "Une location reste comptée comme une vente sur « CA détaillé » : "
            . "son coût est un tarif journalier multiplié par le nombre de jours, "
            . "face à un prix catalogue journalier. C'est ce qui affichait "
            . "« Marge brute HT −732 020 fcfa ».");

        // Et elle est ANNONCÉE : un chiffre qui disparaît sans un mot est un
        // chiffre qu'on croit perdu.
        $this->assertGreaterThan(0, $this->donnees('/CA-detaille' . $p, 'bonsDeLocation'),
            'Les bons de location écartés doivent être comptés et annoncés.');

        $this->actingAs($this->admin())->get('/CA-detaille' . $p)
            ->assertSee('ne sont pas comptés ici', false);
    }

    public function test_une_location_ne_compte_pas_dans_le_ca_par_famille(): void
    {
        if (!$p = $this->periode()) {
            $this->markTestSkipped('Aucun bon servi en base.');
        }

        $bon = $this->bonRattache();

        if (!$bon) {
            $this->markTestSkipped('Aucun bon servi rattaché à une ligne de commande.');
        }

        $avant = (float) $this->donnees('/CA-par-famille' . $p, 'totalHt');

        Livraison::where('id', $bon->livraison_id)->update(['provenance' => \Help::$LOCATION]);

        $apres = (float) $this->donnees('/CA-par-famille' . $p, 'totalHt');

        $this->assertLessThan($avant, $apres,
            'Une location gonfle encore le chiffre d\'affaires par famille.');
    }

    /**
     * UN BON SANS LIGNE DE COMMANDE N'A PAS DE PRIX DE VENTE.
     *
     * Le prix facturé vit sur la ligne de commande. Quand elle a disparu, ces
     * écrans retombaient sur le prix du CATALOGUE : une recette jamais
     * facturée à personne, qui gonflait le chiffre d'affaires.
     */
    public function test_aucun_prix_de_vente_n_est_invente(): void
    {
        if (!$p = $this->periode()) {
            $this->markTestSkipped('Aucun bon servi en base.');
        }

        $bon = $this->bonRattache();

        if (!$bon) {
            $this->markTestSkipped('Aucun bon servi rattaché à une ligne de commande.');
        }

        $avant = $this->donnees('/CA-detaille' . $p, 'totalVente');
        // La base d'essai porte déjà des bons orphelins : on mesure l'ÉCART,
        // pas un compte absolu, sinon l'essai dépend des données en place.
        $orphelinsAvant = (int) $this->donnees('/CA-detaille' . $p, 'sansPrix')['bons'];

        // La ligne de commande disparaît, comme en production.
        Livraison::where('id', $bon->livraison_id)->update(['detail_commande_id' => 99999999]);

        $apres = $this->donnees('/CA-detaille' . $p, 'totalVente');
        $sansPrix = $this->donnees('/CA-detaille' . $p, 'sansPrix');

        $this->assertLessThan($avant, $apres,
            'Le bon a gardé une vente alors que sa ligne de commande a disparu : '
            . 'le prix du catalogue a servi de repli, et invente une recette.');

        $this->assertSame($orphelinsAvant + 1, (int) $sansPrix['bons'],
            'Le bon sans ligne de commande doit être compté à part.');

        $this->assertGreaterThan(0, (float) $sansPrix['cout'],
            'Son coût est bien réel : il doit être annoncé, pas effacé.');

        $this->actingAs($this->admin())->get('/CA-detaille' . $p)
            ->assertSee('ne retrouvent plus leur ligne de commande', false);
    }

    /**
     * LE TOTAL EST LA SOMME DES LIGNES.
     *
     * C'est le piège de tout écart : écarter des montants du total en laissant
     * leur ligne dans le tableau, ou l'inverse. Une colonne qui ne
     * s'additionne pas est une colonne à laquelle personne ne peut se fier.
     */
    public function test_le_total_du_ca_detaille_est_la_somme_de_ses_lignes(): void
    {
        if (!$p = $this->periode()) {
            $this->markTestSkipped('Aucun bon servi en base.');
        }

        foreach (['vente' => 'totalVente', 'cout' => 'totalCout', 'marge' => 'totalMarge'] as $col => $total) {
            $this->assertEqualsWithDelta(
                collect($this->donnees('/CA-detaille' . $p, 'stats'))->sum($col),
                (float) $this->donnees('/CA-detaille' . $p, $total),
                0.01,
                "La colonne « $col » ne s'additionne pas jusqu'à son total.");
        }
    }

    /**
     * LES TROIS ÉCRANS S'ACCORDENT.
     *
     * C'est exactement ce qui manquait : « Récapitulatif des ventes » a été
     * corrigé seul, et « CA détaillé » a continué d'afficher le chiffre fautif.
     */
    public function test_les_trois_ecrans_annoncent_le_meme_chiffre_d_affaires(): void
    {
        if (!$p = $this->periode()) {
            $this->markTestSkipped('Aucun bon servi en base.');
        }

        $caDetaille = (float) $this->donnees('/CA-detaille' . $p, 'totalVente');
        $recap      = (float) $this->donnees('/comptabilite/recap-ventes' . $p, 'totaux')->vente;
        $parFamille = (float) $this->donnees('/CA-par-famille' . $p, 'totalHt');

        $this->assertEqualsWithDelta($caDetaille, $recap, 1.0,
            '« CA détaillé » et « Récapitulatif des ventes » annoncent deux '
            . 'chiffres d\'affaires différents pour la même période.');

        $this->assertEqualsWithDelta($caDetaille, $parFamille, 1.0,
            '« CA détaillé » et « CA par famille » annoncent deux chiffres '
            . 'd\'affaires différents pour la même période.');
    }
}
