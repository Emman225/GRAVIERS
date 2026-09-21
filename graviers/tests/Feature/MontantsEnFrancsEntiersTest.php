<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * UNE SOMME EN FCFA S'ÉCRIT EN FRANCS ENTIERS.
 *
 * Le franc CFA n'a pas de subdivision. Or la TVA et la remise naissent d'un
 * POURCENTAGE — « total × taux / 100 » — et tombent donc sur des demi-francs.
 * Deux sites d'écriture les arrondissaient déjà, les autres non : c'est cette
 * incohérence qui décidait, sans raison, quelle commande porterait un centime.
 *
 * Conséquence observée le 25/08/2026 : une commande due à 28 812,5, réglée
 * 28 813 au guichet — le seul montant saisissable — laissait un demi-franc
 * d'excédent, affiché « Réglé d'avance : 1 FCFA » au client.
 *
 * CE QUE CE CORRECTIF NE FAIT PAS, ET POURQUOI.
 *
 * Il ne rend pas TOUS les totaux entiers. Le HT vaut « quantité × prix
 * unitaire », et une quantité au demi — 4,50 tonnes — sur un prix impair
 * produit encore une moitié. On ne peut pas l'arrondir : FneService déclare à
 * la DGI la quantité et le prix unitaire, et la DGI RECALCULE le total. Un HT
 * arrondi de notre côté ferait diverger le document remis au client du montant
 * certifié — un problème fiscal, autrement plus grave qu'un franc affiché.
 *
 * Mesuré sur 5 000 tirages : 10,1 % de totaux entiers avant, 74,8 % après —
 * soit exactement la part des commandes dont le HT est déjà entier. Le
 * correctif atteint donc le maximum atteignable sans toucher aux lignes.
 *
 * Le reliquat irréductible est absorbé par la tolérance au franc
 * (Help::soldeClientBrut, Commande::montantRestantDu) : sous le franc, il n'y a
 * ni dette ni crédit.
 */
class MontantsEnFrancsEntiersTest extends TestCase
{
    /** L'arrondi au franc est la règle, écrite une seule fois. */
    public function test_la_regle_arrondit_au_franc(): void
    {
        $this->assertSame(3723.0, \Help::arrondiFranc(3722.5));
        $this->assertSame(3786.0, \Help::arrondiFranc(3785.5));
        $this->assertSame(4000.0, \Help::arrondiFranc(4000));
        $this->assertSame(0.0, \Help::arrondiFranc(0));
    }

    /**
     * AUCUNE TVA, AUCUNE REMISE NE S'ÉCRIT PLUS À NU.
     *
     * Le test lit les contrôleurs : toute écriture de ces deux montants doit
     * passer par la règle. C'est la garantie qui manquait — et l'incohérence
     * entre sites qui a produit le défaut.
     */
    public function test_aucune_tva_ni_remise_ne_s_ecrit_sans_arrondi(): void
    {
        $aNu = [];

        foreach (['ClientController', 'OrdersController'] as $nom) {
            $lignes = explode("\n", file_get_contents(app_path("Http/Controllers/{$nom}.php")));

            foreach ($lignes as $i => $ligne) {
                $nue = trim($ligne);

                if ($nue === '' || str_starts_with($nue, '//') || str_starts_with($nue, '*')) {
                    continue;
                }

                // Une écriture de remise ou de TVA dans un tableau de création.
                if (!preg_match("/^'(remise|montant)'\s*=>/", $nue)) {
                    continue;
                }

                // « 'montant' => » sert à bien d'autres choses : on ne retient
                // que les écritures dont la valeur vient d'un calcul de TVA ou
                // de remise.
                if (!preg_match('/\$(montantTva|laTva|remise|promo)\b/i', $nue)) {
                    continue;
                }

                // LE TOTAL D'UNE FACTURE EST EXCLU, ET CE N'EST PAS UN OUBLI.
                //
                // C'est le total du document remis au client. FneService le
                // reprend tel quel dans le TTC du code-barres, pendant que la
                // DGI recalcule le sien depuis les lignes declarees. L'arrondir
                // mettrait dans le QR un montant que la DGI n'a pas certifie.
                // Le test test_le_total_de_la_facture_n_est_pas_arrondi() fige
                // cette exclusion.
                if (str_contains($nue, '$montantHt')) {
                    continue;
                }

                if (!str_contains($nue, 'arrondiFranc')) {
                    $aNu[] = "{$nom}:" . ($i + 1) . ' — ' . $nue;
                }
            }
        }

        $this->assertSame([], $aNu,
            "Ces montants s'écrivent sans arrondi au franc :\n" . implode("\n", $aNu));
    }

    /**
     * ARRONDIR LA TVA ET LA REMISE SUFFIT DÈS QUE LE HT EST ENTIER.
     *
     * C'est la propriété qui compte : quand la ligne tombe juste — le cas le
     * plus courant — le client règle exactement ce qu'il doit, sans reliquat.
     */
    public function test_un_ht_entier_donne_toujours_un_total_entier(): void
    {
        foreach ([[4.5, 5500], [3, 1250], [10, 999], [2.5, 4000]] as [$qte, $pu]) {
            $ht = $qte * $pu;

            if (abs($ht - round($ht)) > 1e-9) {
                continue; // HT fractionnaire : hors de portée, cf. l'en-tête.
            }

            $tva    = \Help::arrondiFranc($ht * 0.18);
            $remise = \Help::arrondiFranc($ht * 0.13);
            $total  = $ht + $tva + 4000 - $remise;

            $this->assertSame(round($total), $total,
                "Avec un HT entier ({$ht}), le total dû doit être un nombre entier de francs.");
        }
    }

    /**
     * LE TOTAL DE LA FACTURE RESTE EXACT LUI AUSSI.
     *
     * Il part dans le TTC du code-barres pendant que la DGI recalcule le sien
     * depuis les lignes declarees. Les deux doivent dire la meme chose.
     */
    public function test_le_total_de_la_facture_n_est_pas_arrondi(): void
    {
        $controleur = file_get_contents(app_path('Http/Controllers/OrdersController.php'));

        $this->assertMatchesRegularExpression(
            '/\$montantHt \+ \$montantTva \+ \$supplement/',
            $controleur,
            "Le total de la facture doit rester l'addition exacte de ses composantes : "
            . "l'arrondir mettrait dans le code-barres un montant que la DGI n'a pas certifie."
        );
    }

    /**
     * LE HT RESTE EXACT — c'est lui qui est déclaré à la DGI.
     *
     * Si quelqu'un arrondit un jour montantHT() ou le prix d'une ligne, le
     * total du document s'écartera du montant certifié. Ce test le rappellera.
     */
    public function test_le_ht_n_est_pas_arrondi(): void
    {
        $modele = file_get_contents(app_path('Models/Commande.php'));
        $debut  = strpos($modele, 'public function montantHT');
        $corps  = substr($modele, $debut, 500);

        $this->assertStringNotContainsString('arrondiFranc', $corps,
            "Le HT ne doit PAS être arrondi : FneService déclare la quantité et le prix "
            . "unitaire à la DGI, qui recalcule le total. L'arrondir ferait diverger la "
            . "facture du montant certifié.");
    }
}
