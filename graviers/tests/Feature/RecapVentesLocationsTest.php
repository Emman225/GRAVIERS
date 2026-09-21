<?php

namespace Tests\Feature;

use App\Models\Enlevement;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Les deux récapitulatifs comptables.
 *
 * « CA détaillé » répond par produit, ceux-ci répondent par affaire : quelle
 * vente, à quel client, encaissée ou non. Côté locations, il n'existait rien.
 *
 * Deux exigences tiennent tout l'écran :
 *   - le chiffre d'affaires doit être le MÊME que celui des écrans existants,
 *     sinon le back-office annonce deux vérités ;
 *   - la caution ne doit jamais compter comme un produit : elle appartient au
 *     client tant qu'elle n'est pas retenue.
 */
class RecapVentesLocationsTest extends TestCase
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

    private function ouvrir(string $url): \Illuminate\Testing\TestResponse
    {
        URL::forceRootUrl('');

        return $this->actingAs($this->unAdmin())->get($url);
    }

    /** La periode qui couvre tout l historique connu. */
    private function toutLHistorique(): string
    {
        return '?du=2000-01-01&au=' . now()->addYear()->format('Y-m-d');
    }

    // -------------------------------------------------------------- VENTES

    public function test_le_recap_des_ventes_repond(): void
    {
        $this->ouvrir('/comptabilite/recap-ventes')->assertOk();
    }

    public function test_la_periode_par_defaut_est_le_mois_en_cours(): void
    {
        // Sans borne, l ecran chargerait tout l historique a chaque ouverture.
        $reponse = $this->ouvrir('/comptabilite/recap-ventes');

        $reponse->assertOk();
        $this->assertSame(now()->startOfMonth()->format('Y-m-d'), $reponse->viewData('du'));
        $this->assertSame(now()->endOfMonth()->format('Y-m-d'), $reponse->viewData('au'));
    }

    public function test_le_chiffre_d_affaires_est_celui_du_CA_detaille(): void
    {
        // Deux écrans qui comptent la même chose doivent trouver le même total.
        // Ils partagent l axe de temps — la date du bon servi — et la meme
        // quantite : celle reellement servie.
        $periode = $this->toutLHistorique();

        $recap = $this->ouvrir('/comptabilite/recap-ventes' . $periode);
        $recap->assertOk();

        $detaille = $this->ouvrir('/CA-detaille?du=2000-01-01&au=' . now()->addYear()->format('Y-m-d'));
        $detaille->assertOk();

        $this->assertSame(
            round((float) $detaille->viewData('totalVente')),
            round((float) $recap->viewData('totaux')->vente),
            'Le recap des ventes et le CA detaille doivent annoncer le meme chiffre.'
        );

        $this->assertSame(
            round((float) $detaille->viewData('totalCout')),
            round((float) $recap->viewData('totaux')->cout),
            'Le cout fournisseur doit lui aussi concorder.'
        );
    }

    public function test_une_vente_sans_bon_servi_n_apparait_pas(): void
    {
        // L axe de temps est la date du bon SERVI : une commande dont aucun
        // fournisseur n a encore livre ne peut pas figurer au chiffre.
        $recap = $this->ouvrir('/comptabilite/recap-ventes' . $this->toutLHistorique());

        $servies = Enlevement::whereNotNull('fournisseur_validation')
            ->where('statut', \Help::$STATUT_ACTIF)
            ->pluck('id');

        if ($servies->isEmpty()) {
            $this->markTestSkipped('Aucun bon servi en base.');
        }

        foreach ($recap->viewData('lignes') as $ligne) {
            $this->assertGreaterThan(0, $ligne->vente + $ligne->cout,
                'Une ligne sans montant ni cout ne devrait pas figurer.');
        }
    }

    public function test_la_marge_est_la_difference_annoncee(): void
    {
        $recap = $this->ouvrir('/comptabilite/recap-ventes' . $this->toutLHistorique());

        $totaux = $recap->viewData('totaux');

        $this->assertSame(round($totaux->vente - $totaux->cout, 2), round($totaux->marge, 2));

        foreach ($recap->viewData('lignes') as $ligne) {
            $this->assertSame(round($ligne->vente - $ligne->cout, 2), round($ligne->marge, 2));
        }
    }

    public function test_le_reste_du_ne_depasse_jamais_le_facture(): void
    {
        // Un trop-perçu produirait un reste négatif, qui se soustrairait au
        // total et masquerait de vraies créances.
        $recap = $this->ouvrir('/comptabilite/recap-ventes' . $this->toutLHistorique());

        foreach ($recap->viewData('lignes') as $ligne) {
            $this->assertGreaterThanOrEqual(0, $ligne->reste);
            $this->assertLessThanOrEqual(round($ligne->facture, 2) + 0.01, round($ligne->reste, 2));
        }
    }

    // ----------------------------------------------------------- LOCATIONS

    /**
     * Une location de recette : deux materiels, cinq jours, une caution.
     *
     * On la fabrique plutot que de compter sur les donnees presentes : sans
     * elle, les deux verifications les plus utiles de cet ecran se sautaient
     * faute de jeu d essai, et ne prouvaient donc rien.
     */
    private function uneLocation(float $montant, float $caution, bool $rendue = false): Location
    {
        $client = \App\Models\Client::first();

        // Un produit REELLEMENT tarife : sans prix d achat, le cout du materiel
        // vaut zero et la verification du benefice ne prouve plus rien.
        $ligneStock = \App\Models\StockProduit::where('statut', \Help::$STATUT_ACTIF)
            ->where('prix', '>', 0)
            ->whereNull('deleted_at')
            ->first();

        $produit = $ligneStock ? \App\Models\Produit::find($ligneStock->produit_id) : null;

        if (!$client || !$produit) {
            $this->markTestSkipped('Jeu de donnees insuffisant.');
        }

        $location = Location::create([
            'numero'         => 'LOC-' . uniqid(),
            'client_id'      => $client->id,
            'date_location'  => now()->format('Y-m-d'),
            'montant_total'  => $montant,
            'etat_location'  => \Help::$LOCATION_EN_COURS,
            'caution'        => $caution,
            'caution_restituee' => $rendue,
            'statut'         => \Help::$STATUT_ACTIF,
        ]);

        \App\Models\DetailLocation::create([
            'produit_id'    => $produit->id,
            'location_id'   => $location->id,
            'qte'           => 2,
            'debut'         => now()->format('Y-m-d'),
            'fin'           => now()->addDays(5)->format('Y-m-d'),
            'prix'          => $montant,
            'nombre_jour'   => 5,
            'etat_location' => \Help::$LOCATION_EN_COURS,
            'statut'        => \Help::$STATUT_ACTIF,
        ]);

        return $location->fresh();
    }

    public function test_la_caution_ne_gonfle_pas_le_montant_loue(): void
    {
        // Une caution encaissee n est pas un produit : la compter gonflerait le
        // chiffre d affaires d un argent qui doit etre rendu.
        $location = $this->uneLocation(100000, 50000);

        $recap = $this->ouvrir('/comptabilite/recap-locations' . $this->toutLHistorique());

        $ligne = collect($recap->viewData('lignes'))->firstWhere('id', $location->id);

        $this->assertNotNull($ligne, 'La location doit figurer au recapitulatif.');
        $this->assertSame(100000.0, round($ligne->montant, 2));
        $this->assertSame(50000.0, round($ligne->caution, 2));
    }

    public function test_une_caution_restituee_sort_du_total_detenu(): void
    {
        $detenue  = $this->uneLocation(100000, 50000, false);
        $rendue   = $this->uneLocation(100000, 30000, true);

        $recap = $this->ouvrir('/comptabilite/recap-locations' . $this->toutLHistorique());

        $lignes = collect($recap->viewData('lignes'));

        $this->assertFalse($lignes->firstWhere('id', $detenue->id)->rendue);
        $this->assertTrue($lignes->firstWhere('id', $rendue->id)->rendue);

        // Les 30 000 restitues ne doivent pas figurer au total detenu.
        $attendu = $lignes->reject(fn ($l) => $l->rendue)->sum('caution');

        $this->assertSame(round($attendu, 2), round($recap->viewData('totaux')->caution, 2));
        $this->assertGreaterThanOrEqual(50000.0, $recap->viewData('totaux')->caution);
    }

    public function test_deux_materiels_cinq_jours_font_dix_jours_materiel(): void
    {
        $location = $this->uneLocation(100000, 0);

        $recap = $this->ouvrir('/comptabilite/recap-locations' . $this->toutLHistorique());

        $ligne = collect($recap->viewData('lignes'))->firstWhere('id', $location->id);

        $this->assertNotNull($ligne);
        $this->assertSame(10.0, round($ligne->jours, 2),
            'Deux materiels sur cinq jours font dix jours-materiel.');
    }

    public function test_le_recap_des_locations_repond(): void
    {
        $this->ouvrir('/comptabilite/recap-locations')->assertOk();
    }

    public function test_toutes_les_locations_de_la_periode_sont_la(): void
    {
        $recap = $this->ouvrir('/comptabilite/recap-locations' . $this->toutLHistorique());
        $recap->assertOk();

        // CET ESSAI RECOPIAIT LE DÉFAUT QU'IL AURAIT DÛ PRENDRE.
        //
        // Il calculait l'attendu avec le MÊME filtre que l'écran —
        // `where('statut', STATUT_ACTIF)` — si bien que les deux se trompaient
        // ensemble et tombaient d'accord. L'écran est resté vide en production
        // du 25 au 28/08/2026 sans que rien ne le signale.
        //
        // `location.statut` porte l'état du RÈGLEMENT (1 = aucun paiement,
        // 2 = acompte, 3 = soldé), pas l'activité de la ligne. Le critère juste
        // est l'annulation, et l'attendu se calcule désormais ainsi.
        $attendu = Location::where('etat_location', '<>', 'ANNULEE')
            ->whereDate('date_location', '>=', '2000-01-01')
            ->count();

        $this->assertSame($attendu, count($recap->viewData('lignes')));
    }


    public function test_seules_les_cautions_detenues_sont_totalisees(): void
    {
        // Une caution restituée n'est plus détenue : la garder au total ferait
        // croire à une trésorerie qu'on n'a plus.
        $recap = $this->ouvrir('/comptabilite/recap-locations' . $this->toutLHistorique());

        $lignes  = $recap->viewData('lignes');
        $attendu = 0.0;

        foreach ($lignes as $ligne) {
            if (!$ligne->rendue) {
                $attendu += $ligne->caution;
            }
        }

        $this->assertSame(round($attendu, 2), round($recap->viewData('totaux')->caution, 2));
    }


    public function test_les_totaux_sont_la_somme_des_lignes(): void
    {
        $recap = $this->ouvrir('/comptabilite/recap-locations' . $this->toutLHistorique());

        $lignes = $recap->viewData('lignes');
        $totaux = $recap->viewData('totaux');

        $this->assertSame(
            round(array_sum(array_map(fn ($l) => $l->montant, $lignes)), 2),
            round($totaux->montant, 2)
        );

        $this->assertSame(
            round(array_sum(array_map(fn ($l) => $l->encaisse, $lignes)), 2),
            round($totaux->encaisse, 2)
        );
    }

    // ------------------------------------------ LE BENEFICE, ANNONCE EN CLAIR

    public function test_les_trois_ecrans_nomment_le_benefice_de_dalakoun(): void
    {
        // Le chiffre existait sur les trois ecrans, mais aucun ne le NOMMAIT :
        // on lisait "Marge" sans savoir de quoi.
        $ecrans = [
            '/comptabilite/recap-ventes'    => 'ventes',
            '/comptabilite/recap-locations' => 'locations',
            '/comptabilite/benefices-livraisons' => 'livraisons',
        ];

        foreach ($ecrans as $url => $quoi) {
            $reponse = $this->ouvrir($url);

            $reponse->assertOk();
            // « Bénéfices » : on cherche la partie sans accent, l encodage du
            // fichier de test et celui de la page n ont pas a concorder.
            $reponse->assertSee('fices de DALAKOUN sur les ' . $quoi, false);
        }
    }

    public function test_le_benefice_des_locations_est_le_loue_moins_le_cout(): void
    {
        $location = $this->uneLocation(100000, 0);

        $recap  = $this->ouvrir('/comptabilite/recap-locations' . $this->toutLHistorique());
        $totaux = $recap->viewData('totaux');

        $this->assertSame(round($totaux->loue - $totaux->cout, 2), round($totaux->marge, 2));

        $ligne = collect($recap->viewData('lignes'))->firstWhere('id', $location->id);

        $this->assertSame(100000.0, round($ligne->loue, 2),
            'La base du benefice est le montant loue, remise deduite.');
        $this->assertSame(round($ligne->loue - $ligne->cout, 2), round($ligne->marge, 2));
    }

    public function test_le_cout_du_materiel_suit_la_quantite_et_la_duree(): void
    {
        // Deux materiels cinq jours : le cout est multiplie par dix, sinon le
        // benefice serait surevalue d autant.
        $location = $this->uneLocation(100000, 0);

        $detail = $location->detailLocation->first();
        $achat  = \App\Models\Produit::prixAchatDe($detail->produit_id);

        $this->assertNotNull($achat, 'La fixture doit porter un produit tarife.');

        $recap = $this->ouvrir('/comptabilite/recap-locations' . $this->toutLHistorique());
        $ligne = collect($recap->viewData('lignes'))->firstWhere('id', $location->id);

        $this->assertSame(round($achat * 2 * 5, 2), round($ligne->cout, 2),
            'Le cout doit valoir prix dachat x quantite x duree.');
    }

    public function test_la_caution_reste_hors_du_benefice(): void
    {
        // Une caution comptee au benefice ferait croire a un gain sur un argent
        // qui doit etre rendu.
        $sans = $this->uneLocation(100000, 0);

        $recapSans = $this->ouvrir('/comptabilite/recap-locations' . $this->toutLHistorique());
        $margeSans = $recapSans->viewData('totaux')->marge;

        $avec = $this->uneLocation(100000, 500000);

        $recapAvec = $this->ouvrir('/comptabilite/recap-locations' . $this->toutLHistorique());
        $ligneAvec = collect($recapAvec->viewData('lignes'))->firstWhere('id', $avec->id);
        $ligneSans = collect($recapAvec->viewData('lignes'))->firstWhere('id', $sans->id);

        $this->assertSame(round($ligneSans->marge, 2), round($ligneAvec->marge, 2),
            'Une caution de 500 000 ne doit rien changer au benefice.');
    }
}
