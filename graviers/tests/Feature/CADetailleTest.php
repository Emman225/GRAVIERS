<?php

namespace Tests\Feature;

use App\Models\Enlevement;
use App\Models\StockProduit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Le chiffre d'affaires détaillé, et son accord avec le chiffre d'affaires
 * par famille.
 *
 * L'écran comptait les COMMANDES, à leur quantité DEMANDÉE, sans regarder si
 * la marchandise était sortie : 910 255 F pour 56 unités, quand « CA par
 * famille » en annonçait 16 750 pour 8. Deux écrans de chiffre d'affaires, un
 * facteur 54 entre eux.
 *
 * Sa marge était une fiction : le coût fournisseur venait de
 * `detail_commande.prix_fournisseur`, renseignée sur 2 lignes sur 38 — et
 * l'une des deux porte 258 020 F l'unité pour un produit vendu 50 F.
 */
class CADetailleTest extends TestCase
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

    private function ecran(string $url, array $filtres = []): \Illuminate\Testing\TestResponse
    {
        URL::forceRootUrl('');
        $reponse = $this->actingAs($this->unAdmin())
            ->get($url . ($filtres ? '?' . http_build_query($filtres) : ''));
        $reponse->assertOk();

        return $reponse;
    }

    private function detaille(array $filtres = []): \Illuminate\Testing\TestResponse
    {
        return $this->ecran('/CA-detaille', $filtres);
    }

    private function unBonServi(): Enlevement
    {
        $bon = Enlevement::whereNotNull('fournisseur_validation')
            ->where('statut', \Help::$STATUT_ACTIF)
            ->whereNotNull('produit_id')
            ->whereNull('deleted_at')
            ->first();

        if (!$bon) {
            $this->markTestSkipped('Aucun bon servi rattaché à un produit.');
        }

        return $bon;
    }

    // ------------------------------------------------------------ COHÉRENCE

    public function test_les_deux_ecrans_de_chiffre_d_affaires_disent_la_meme_chose(): void
    {
        $detaille  = $this->detaille();
        $parFamille = $this->ecran('/CA-par-famille');

        // C'est le défaut central : deux écrans de chiffre d'affaires qui ne
        // comptaient pas la même chose.
        $this->assertSame(
            round((float) $parFamille->viewData('totalHt'), 2),
            round((float) $detaille->viewData('totalVente'), 2),
            'Le CA détaillé et le CA par famille doivent annoncer le même montant.'
        );
        $this->assertSame(
            round((float) $parFamille->viewData('qteVendue'), 2),
            round((float) $detaille->viewData('totalQteServie'), 2),
            'Les deux écrans doivent annoncer la même quantité vendue.'
        );
    }

    public function test_les_lignes_s_additionnent_pour_donner_les_totaux(): void
    {
        $r     = $this->detaille();
        $stats = $r->viewData('stats');

        foreach (['vente' => 'totalVente', 'cout' => 'totalCout', 'qteServie' => 'totalQteServie'] as $champ => $total) {
            $this->assertSame(
                round(array_sum(array_map(fn ($s) => $s->$champ, $stats)), 2),
                round((float) $r->viewData($total), 2),
                "La colonne « $champ » ne s'additionne pas au total."
            );
        }
    }

    // ---------------------------------------------------------------- MARGE

    public function test_la_marge_est_la_difference_entre_vente_et_cout(): void
    {
        $r = $this->detaille();

        foreach ($r->viewData('stats') as $s) {
            $this->assertSame(round($s->vente - $s->cout, 2), round($s->marge, 2),
                "La marge de « {$s->nom} » ne découle pas de ses deux montants.");
        }

        $this->assertSame(
            round((float) $r->viewData('totalVente') - (float) $r->viewData('totalCout'), 2),
            round((float) $r->viewData('totalMarge'), 2)
        );
    }

    public function test_le_cout_fournisseur_vient_du_bon_et_non_de_la_ligne_de_commande(): void
    {
        $bon = $this->unBonServi();
        $bon->update(['qte_servi' => 2, 'prix_fournisseur' => 1000]);

        // Une ligne de commande au coût fournisseur aberrant — le cas réel qui
        // écrasait le total — ne doit plus rien changer.
        $ligne = DB::table('detail_commande')
            ->where('produit_id', $bon->produit_id)->first();

        if ($ligne) {
            DB::table('detail_commande')->where('id', $ligne->id)
                ->update(['prix_fournisseur' => 258020]);
        }

        $stats = $this->detaille()->viewData('stats');

        $ligneProduit = collect($stats)->firstWhere('id', $bon->produit_id);
        $this->assertNotNull($ligneProduit, 'Le produit servi doit être listé.');

        // 2 servies x 1 000 = 2 000, quelle que soit la ligne de commande.
        $this->assertGreaterThanOrEqual(2000.0, round($ligneProduit->cout, 2));
        $this->assertLessThan(258020.0, round((float) $this->detaille()->viewData('totalCout'), 2),
            'Le coût fournisseur ne doit plus dépendre de detail_commande.prix_fournisseur.');
    }

    // ------------------------------------------------------------- PÉRIMÈTRE

    public function test_un_bon_non_servi_ne_compte_pas(): void
    {
        $bon = $this->unBonServi();
        $bon->update(['qte_servi' => 3]);

        $avant = (float) $this->detaille()->viewData('totalQteServie');

        $memo = $bon->fournisseur_validation;
        $bon->update(['fournisseur_validation' => null]);

        $apres = (float) $this->detaille()->viewData('totalQteServie');

        $this->assertSame(round($avant - 3, 2), round($apres, 2),
            'Un bon jamais servi alimentait le chiffre d\'affaires.');

        $bon->update(['fournisseur_validation' => $memo]);
    }

    public function test_un_bon_annule_ne_compte_pas(): void
    {
        $bon = $this->unBonServi();
        $bon->update(['qte_servi' => 3]);

        $avant = (float) $this->detaille()->viewData('totalQteServie');

        $bon->update(['statut' => 0]);

        $this->assertSame(
            round($avant - 3, 2),
            round((float) $this->detaille()->viewData('totalQteServie'), 2)
        );
    }

    public function test_l_ecart_entre_demande_et_servi_reste_visible(): void
    {
        $bon = $this->unBonServi();
        $bon->update(['qte' => 10, 'qte_servi' => 4]);

        $ligne = collect($this->detaille()->viewData('stats'))->firstWhere('id', $bon->produit_id);

        $this->assertNotNull($ligne);
        // La quantité demandée disparaissait de l'écran une fois le calcul
        // ramené au servi : c'est pourtant l'écart qui intéresse.
        $this->assertGreaterThanOrEqual(10.0, $ligne->qteDemandee);
        $this->assertGreaterThan($ligne->qteServie, $ligne->qteDemandee);
    }

    public function test_le_stock_desactive_ne_compte_pas_dans_le_dispo(): void
    {
        $bon = $this->unBonServi();

        if (!$bon->fournisseur_id) {
            $this->markTestSkipped('Bon sans fournisseur.');
        }

        $stock = StockProduit::create([
            'fournisseur_id' => $bon->fournisseur_id,
            'produit_id'     => $bon->produit_id,
            'qte'            => 400,
            'prix'           => 1000,
            'seuil_alert'    => 0,
            'statut'         => \Help::$STATUT_ACTIF,
        ]);

        $avec = collect($this->detaille()->viewData('stats'))->firstWhere('id', $bon->produit_id);

        $stock->update(['statut' => 0]);

        $sans = collect($this->detaille()->viewData('stats'))->firstWhere('id', $bon->produit_id);

        $this->assertSame(round($avec->dispo - 400, 2), round($sans->dispo, 2));
    }

    // ---------------------------------------------------------------- DATES

    public function test_le_filtre_de_dates_porte_sur_la_date_de_service(): void
    {
        $bon = $this->unBonServi();
        $bon->update(['fournisseur_validation' => '2018-03-10 09:00:00', 'qte_servi' => 6]);

        $dedans = (float) $this->detaille(['du' => '2018-03-01', 'au' => '2018-03-31'])
            ->viewData('totalQteServie');
        $dehors = (float) $this->detaille(['du' => '2018-04-01', 'au' => '2018-04-30'])
            ->viewData('totalQteServie');

        $this->assertSame(6.0, round($dedans - $dehors, 2));
    }

    public function test_sans_filtre_la_periode_couvre_tout_l_historique(): void
    {
        $r = $this->detaille();

        // Les champs affichaient « du 1er janvier à aujourd'hui » pendant que le
        // tableau montrait tout l'historique.
        $this->assertNull($r->viewData('du'));
        $this->assertNull($r->viewData('au'));
    }
}
