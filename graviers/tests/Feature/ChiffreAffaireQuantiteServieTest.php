<?php

namespace Tests\Feature;

use App\Models\Enlevement;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Le chiffre d'affaires par famille ne compte que ce qui a été servi.
 *
 * Il additionnait TOUS les enlèvements, à leur quantité DEMANDÉE — y compris
 * ceux que le fournisseur n'a jamais servis. Le chiffre d'affaires comptait
 * donc de la marchandise qui n'est jamais sortie.
 *
 * Second défaut : le total du bandeau se calculait au prix de la ligne de
 * commande pendant que chaque ligne du tableau se calculait au prix moyen du
 * produit — les lignes ne pouvaient pas s'additionner pour retomber sur le
 * total.
 */
class ChiffreAffaireQuantiteServieTest extends TestCase
{
    use DatabaseTransactions;

    private function unAdmin(): User
    {
        $admin = User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN])
            ->where('statut', \Help::$STATUT_ACTIF)->first();

        if (!$admin) {
            $this->markTestSkipped('Aucun administrateur actif.');
        }

        return $admin;
    }

    /** Ce que l'écran calcule, lu depuis la vue rendue. */
    private function donneesEcran(array $filtres = []): array
    {
        URL::forceRootUrl('');
        $reponse = $this->actingAs($this->unAdmin())
            ->get('/CA-par-famille' . ($filtres ? '?' . http_build_query($filtres) : ''));
        $reponse->assertOk();

        return [
            'qteVendue' => $reponse->viewData('qteVendue'),
            'totalHt'   => $reponse->viewData('totalHt'),
            'familles'  => $reponse->viewData('familles'),
        ];
    }

    /** Toutes les lignes produit, familles confondues. */
    private function lignes(array $familles): array
    {
        return array_merge(...array_values(array_map(fn ($f) => $f['lignes'], $familles))) ?: [];
    }

    public function test_un_bon_non_valide_ne_compte_pas(): void
    {
        $bon = Enlevement::whereNotNull('fournisseur_validation')->whereNull('deleted_at')->first();

        if (!$bon) {
            $this->markTestSkipped('Aucun bon validé.');
        }

        $avant = $this->donneesEcran();

        // Le fournisseur n'a jamais servi ce bon : il sort du chiffre d'affaires.
        $memo = $bon->fournisseur_validation;
        $bon->update(['fournisseur_validation' => null]);

        $apres = $this->donneesEcran();

        $this->assertLessThan($avant['qteVendue'], $apres['qteVendue']);

        $bon->update(['fournisseur_validation' => $memo]);
    }

    public function test_la_quantite_retenue_est_celle_servie(): void
    {
        $bon = Enlevement::whereNotNull('fournisseur_validation')
            ->where('statut', \Help::$STATUT_ACTIF)->whereNull('deleted_at')->first();

        if (!$bon) {
            $this->markTestSkipped('Aucun bon validé.');
        }

        $bon->update(['qte' => 10, 'qte_servi' => 3]);
        $avecTrois = $this->donneesEcran();

        $bon->update(['qte' => 10, 'qte_servi' => 4]);
        $avecQuatre = $this->donneesEcran();

        // Une unité servie de plus, une unité de plus au chiffre d'affaires —
        // et non les dix demandées.
        $this->assertSame(1.0, round($avecQuatre['qteVendue'] - $avecTrois['qteVendue'], 2));
    }

    public function test_les_lignes_s_additionnent_pour_donner_le_total(): void
    {
        $donnees = $this->donneesEcran();
        $lignes  = $this->lignes($donnees['familles']);

        // Les deux se calculaient sur des prix différents : ils ne pouvaient
        // pas se rejoindre.
        $this->assertSame(
            round(array_sum(array_map(fn ($l) => $l->ht, $lignes)), 2),
            round($donnees['totalHt'], 2)
        );
        $this->assertSame(
            round(array_sum(array_map(fn ($l) => $l->qteVendue, $lignes)), 2),
            round($donnees['qteVendue'], 2)
        );
    }
}
