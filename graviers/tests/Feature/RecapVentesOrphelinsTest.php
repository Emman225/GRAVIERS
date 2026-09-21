<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * LE BANDEAU « BÉNÉFICES SUR LES VENTES » MESURE LES VENTES.
 *
 * Constaté en production le 03/09/2026 : « −732 020 fcfa ». Sur 19 bons
 * servis, 11 ne retrouvaient plus leur ligne de commande. Ces orphelins
 * portaient −735 500 à eux seuls ; les ventes réellement rattachées
 * dégageaient +3 480.
 *
 * Un bon orphelin n'a PAS de prix de vente connu — sa ligne facturée a
 * disparu. On lui prêtait celui du CATALOGUE : une recette jamais facturée à
 * personne, qui, moins un coût bien réel, écrasait la marge des vraies ventes.
 */
class RecapVentesOrphelinsTest extends TestCase
{
    // UN ESSAI QUI ÉCRIT DOIT TOUT RENDRE.
    //
    // Celui du bas déclare une course comme location : sans transaction, il a
    // laissé cette marque dans la base après son passage.
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN])
            ->where('statut', 1)->firstOrFail();
    }

    /** La période qui couvre tous les bons servis de la base. */
    private function periode(): ?array
    {
        $b = DB::selectOne('SELECT MIN(DATE(fournisseur_validation)) d,
                                   MAX(DATE(fournisseur_validation)) f
                            FROM enlevement
                            WHERE fournisseur_validation IS NOT NULL AND statut = 1');

        return $b && $b->d ? [$b->d, $b->f] : null;
    }

    public function test_le_bandeau_ne_compte_que_les_ventes_rattachees(): void
    {
        $periode = $this->periode();

        if (!$periode) {
            $this->markTestSkipped('Aucun bon servi en base.');
        }

        [$du, $au] = $periode;

        $reponse = $this->actingAs($this->admin())
            ->get('/comptabilite/recap-ventes?du=' . $du . '&au=' . $au);

        $reponse->assertOk();

        // ON LIT LE MONTANT AFFICHÉ, pas celui calculé.
        //
        // Une première version interrogeait les données du contrôleur :
        // changer la valeur PASSÉE au bandeau lui échappait entièrement.
        $html = preg_replace('/\s+/u', ' ', strip_tags($reponse->getContent()));

        // Les capitales du bandeau viennent du STYLE : dans le document, le
        // titre est écrit normalement. D'où le drapeau « i ».
        $motif = '/Bénéfices de DALAKOUN sur les ventes ([-0-9\x{00A0} ]+) fcfa/ui';

        $this->assertMatchesRegularExpression($motif, $html,
            'Le bandeau des ventes est introuvable.');

        preg_match($motif, $html, $m);
        $affiche = (float) preg_replace('/[^0-9-]/', '', $m[1]);

        // Ce que dit la base, pour les bons RATTACHÉS uniquement.
        $attendu = DB::selectOne(
            // Le controleur ecrit `$detail->prix ?? produit->prix_moyen` :
            // une ligne sans prix retombe sur le catalogue. L'attendu doit
            // suivre, sinon il mesure autre chose que la page.
            'SELECT ROUND(SUM(COALESCE(e.qte_servi, e.qte)
                        * (COALESCE(dc.prix, p.prix_moyen, 0)
                           - COALESCE(e.prix_fournisseur, 0)))) AS marge
             FROM enlevement e
             JOIN livraison l ON l.id = e.livraison_id
             JOIN detail_commande dc ON dc.id = l.detail_commande_id
             LEFT JOIN produit p ON p.id = e.produit_id
             WHERE e.statut = 1 AND e.fournisseur_validation IS NOT NULL
               AND e.fournisseur_validation >= ? AND e.fournisseur_validation <= ?',
            [$du . ' 00:00:00', $au . ' 23:59:59']);

        $this->assertEqualsWithDelta(
            (float) ($attendu->marge ?? 0),
            $affiche,
            1.0,
            'Le bandeau doit mesurer les ventes rattachées à leur commande, '
            . 'et rien d\'autre : un bon orphelin n\'a pas de prix de vente '
            . 'connu, et lui prêter le prix catalogue invente une recette.');
    }

    public function test_les_orphelins_sont_annonces_a_part_et_jamais_caches(): void
    {
        $periode = $this->periode();

        if (!$periode) {
            $this->markTestSkipped('Aucun bon servi en base.');
        }

        [$du, $au] = $periode;

        $reponse = $this->actingAs($this->admin())
            ->get('/comptabilite/recap-ventes?du=' . $du . '&au=' . $au);

        $totaux = $reponse->original->getData()['totaux'];

        $orphelins = DB::selectOne(
            'SELECT COUNT(*) AS n,
                    ROUND(SUM(COALESCE(e.qte_servi, e.qte)
                              * COALESCE(e.prix_fournisseur, 0))) AS cout
             FROM enlevement e
             LEFT JOIN livraison l ON l.id = e.livraison_id
             LEFT JOIN detail_commande dc ON dc.id = l.detail_commande_id
             WHERE e.statut = 1 AND e.fournisseur_validation IS NOT NULL
               AND e.fournisseur_validation >= ? AND e.fournisseur_validation <= ?
               AND dc.id IS NULL',
            [$du . ' 00:00:00', $au . ' 23:59:59']);

        $this->assertSame((int) $orphelins->n, (int) $totaux->bonsOrphelins,
            'Le nombre de bons orphelins doit être annoncé, pas masqué.');

        if ((int) $orphelins->n > 0) {
            $reponse->assertSee('ne retrouvent plus leur ligne de commande', false);

            // ET ILS SORTENT DES TOTAUX.
            //
            // L'assertion précédente cherchait le libellé « Bons sans commande »
            // dans la page. Elle a continué de passer après la suppression de
            // cette ligne du tableau : la phrase subsistait dans le texte
            // explicatif. Un essai qui passe pour la mauvaise raison ne protège
            // rien — on mesure donc l'ARGENT, pas un libellé.
            $this->assertSame(
                array_sum(array_map(fn ($l) => $l->cout, $reponse->original->getData()['lignes'])),
                (float) $totaux->cout,
                'Le total du tableau doit être la somme de ses lignes : '
                . 'un bon écarté ne doit peser sur aucune des deux.');

            $this->assertGreaterThan(0, (float) $totaux->coutOrphelins,
                'Le coût des bons écartés doit être annoncé, jamais tu.');
        }
    }

    /**
     * LES LOCATIONS NE SONT PAS DES VENTES.
     *
     * Une course de location range l'identifiant de sa ligne dans la MEME
     * colonne que les commandes : `livraison.detail_commande_id` porte alors
     * un id de `detail_location`. Cet ecran ne trouvait donc aucune ligne de
     * commande en face, et prenait ces bons pour des ventes orphelines — en
     * leur pretant le prix CATALOGUE du materiel, qui est un prix JOURNALIER,
     * face au cout d'une location de plusieurs jours.
     *
     * C'est ce qui produisait « -732 020 » en production.
     */
    public function test_un_bon_de_location_ne_compte_pas_dans_les_ventes(): void
    {
        $periode = $this->periode();

        if (!$periode) {
            $this->markTestSkipped('Aucun bon servi en base.');
        }

        [$du, $au] = $periode;
        $url = '/comptabilite/recap-ventes?du=' . $du . '&au=' . $au;

        $avant = $this->actingAs($this->admin())->get($url)
            ->original->getData()['totaux'];

        // ON PREND UN BON QUI COMPTE VRAIMENT DANS LES VENTES.
        //
        // La première version prenait le premier bon venu. En base d'essai
        // c'était un bon DÉJÀ orphelin, donc déjà hors des totaux : le déclarer
        // location ne changeait rien, et l'essai ne mesurait plus rien.
        $bon = \App\Models\Enlevement::whereNotNull('fournisseur_validation')
            ->where('statut', \Help::$STATUT_ACTIF)
            ->whereNotNull('livraison_id')
            ->get()
            ->first(function ($b) {
                $d = optional($b->livraison)->detailCommande;

                return $d && $d->commande_id;
            });

        if (!$bon) {
            $this->markTestSkipped('Aucun bon servi rattache a une ligne de commande.');
        }

        \App\Models\Livraison::where('id', $bon->livraison_id)
            ->update(['provenance' => \Help::$LOCATION]);

        $apres = $this->actingAs($this->admin())->get($url)
            ->original->getData()['totaux'];

        $this->assertNotEquals($avant->cout, $apres->cout,
            "Le bon de location compte toujours dans les ventes : son cout — "
            . "un tarif journalier multiplie par le nombre de jours — s'ajoute "
            . "aux achats des ventes, face a un prix catalogue journalier.");

        $this->assertLessThan($avant->cout, $apres->cout,
            'Le cout des ventes doit DIMINUER quand un bon de location en sort.');
    }
}
