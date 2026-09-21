<?php

namespace Tests\Feature;

use App\Http\Controllers\PaiementController;
use App\Models\Commande;
use App\Models\Configuration;
use App\Models\LignePaiement;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionMethod;
use Tests\TestCase;

/**
 * UN REÇU NE DOIT PAS DÉCLARER UNE EXONÉRATION QUI N'EXISTE PAS.
 *
 * Le reçu téléchargé depuis /paiement/liste annonçait, pour TOUT règlement :
 *
 *     TOTAL HT   = le montant encaissé
 *     TVA        = 0            <- écrit en dur
 *     TOTAL TTC  = le même montant
 *     Résumé     : « TVA exo.lég - Pas de TVA sur HT 00,00% - D »
 *
 * Autrement dit, il présentait un TTC comme un HT et déclarait une exonération
 * sur des ventes qui avaient bien été taxées. Signalé le 28/08/2026.
 *
 * La part fiscale se lit désormais sur l'AFFAIRE réglée, au prorata de ce qui a
 * été versé, et distingue deux bases : la marchandise, taxée, et le transport,
 * qui ne l'est pas — la TVA sur le transport étant une option, désactivée par
 * défaut.
 */
class RecuPartFiscaleTest extends TestCase
{
    use DatabaseTransactions;

    private function part(LignePaiement $ligne): array
    {
        $m = new ReflectionMethod(PaiementController::class, 'partFiscaleDuReglement');
        $m->setAccessible(true);

        return $m->invoke(new PaiementController, $ligne);
    }

    private function unReglement(): ?LignePaiement
    {
        return LignePaiement::where('statut', \Help::$STATUT_ACTIF)
            ->whereHas('paiement', fn ($q) => $q->where('service', \Help::$COMMANDE))
            ->get()
            ->first(function ($l) {
                $c = Commande::find(optional($l->paiement)->service_id);

                return $c && (float) ($c->TvaCommande->montant ?? 0) > 0;
            });
    }

    /** LE DOCUMENT DOIT S'ADDITIONNER : base taxée + base exonérée + TVA = versé. */
    public function test_les_trois_montants_redonnent_le_verse(): void
    {
        $ligne = $this->unReglement();

        if (!$ligne) {
            $this->markTestSkipped('Base de travail sans règlement de vente taxée.');
        }

        $p = $this->part($ligne);

        $this->assertSame(
            (float) $p['total_ttc'],
            (float) ($p['base_taxee'] + $p['base_non_taxee'] + $p['total_tva']),
            "Le reçu ne s'additionne pas : le client ne peut pas refaire le "
            . 'calcul au stylo.'
        );

        $this->assertSame(
            (float) $p['total_ht'],
            (float) ($p['base_taxee'] + $p['base_non_taxee']),
            'Le TOTAL HT doit être la somme des deux bases.'
        );
    }

    /** LA TVA N'EST PLUS NULLE SUR UNE VENTE TAXÉE. */
    public function test_la_tva_n_est_plus_ecrite_a_zero(): void
    {
        $ligne = $this->unReglement();

        if (!$ligne) {
            $this->markTestSkipped('Base de travail sans règlement de vente taxée.');
        }

        $this->assertGreaterThan(
            0,
            $this->part($ligne)['total_tva'],
            "Le reçu déclare une TVA nulle sur un règlement qui en contient : "
            . "c'est une exonération annoncée à l'administration."
        );
    }

    /**
     * LE TAUX AFFICHÉ EST CELUI EN VIGUEUR, PAS UN RAPPORT ARBITRAIRE.
     *
     * Ramener le règlement à une seule base donnait des taux absurdes —
     * 17,14 %, 10,91 %, 16,53 % selon la part de livraison. Aucune de ces
     * valeurs n'est un taux de TVA.
     */
    public function test_le_taux_affiche_est_celui_de_la_configuration(): void
    {
        $ligne = $this->unReglement();

        if (!$ligne) {
            $this->markTestSkipped('Base de travail sans règlement de vente taxée.');
        }

        $p = $this->part($ligne);

        $this->assertSame(
            (float) Configuration::first()->tva,
            (float) $p['taux_tva'],
            'Le taux imprimé doit être celui en vigueur.'
        );

        $this->assertSame(
            round($p['base_taxee'] * $p['taux_tva'] / 100),
            (float) $p['total_tva'],
            'La TVA doit être exactement le taux appliqué à la base taxée.'
        );
    }

    /** LE GABARIT N'ÉCRIT PLUS DE VALEUR EN DUR. */
    public function test_le_gabarit_ne_porte_plus_de_tva_constante(): void
    {
        $vue = file_get_contents(resource_path('views/document/facture.blade.php'));
        $vue = preg_replace('!\{\{--.*?--\}\}!s', '', $vue);

        $this->assertStringNotContainsString(
            '<td class="label">TVA</td>' . "\n" . '            <td class="valeur">0</td>',
            $vue,
            'La TVA est encore écrite à zéro dans le gabarit.'
        );

        $this->assertStringContainsString('$base_taxee', $vue,
            'Le résumé fiscal ne distingue pas la base taxée de la base exonérée.');
    }
}
