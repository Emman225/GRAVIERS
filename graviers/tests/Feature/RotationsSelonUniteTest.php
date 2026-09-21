<?php

namespace Tests\Feature;

use App\Models\Livreur;
use App\Models\UniteProduit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * On ne compte des rotations que si les unités sont comparables.
 *
 * La capacité d'un camion est en TONNES. La quantité, elle, est dans l'unité du
 * produit : des sacs, des barres, des mètres cubes, des jours de location.
 * Diviser les unes par les autres ne donne pas un nombre de voyages, mais un
 * nombre sans signification — qui multipliait la paie du livreur.
 *
 * Une commande de 11 400 barres devenait 285 voyages : 715 000 F de
 * rémunération pour un transport facturé 100 000 F. Sur les 96 tranches
 * tarifaires, 32 partaient à perte pour cette seule raison.
 */
class RotationsSelonUniteTest extends TestCase
{
    use DatabaseTransactions;

    private function unite(string $abreviation): ?UniteProduit
    {
        return UniteProduit::whereRaw('UPPER(abreviation) = ?', [strtoupper($abreviation)])->first();
    }

    // --------------------------------------------------- CE QUI SE DIVISE

    public function test_les_tonnes_se_divisent_par_la_capacite(): void
    {
        $tonne = $this->unite('T');

        if (!$tonne) {
            $this->markTestSkipped('Unite Tonne absente.');
        }

        // 100 t dans un camion de 40 t : trois voyages.
        $this->assertSame(3, Livreur::nombreDeVoyages(100, 40, 40, $tonne->id));
    }

    public function test_les_kilogrammes_se_ramenent_aux_tonnes(): void
    {
        // 100 000 kg, c est 100 t : trois voyages, pas 2 500.
        $kg = $this->unite('KG');

        if (!$kg) {
            $this->markTestSkipped('Unite Kilogramme absente.');
        }

        $this->assertSame(3, Livreur::nombreDeVoyages(100000, 40, 40, $kg->id));
    }

    // ----------------------------------------------- CE QUI NE SE DIVISE PAS

    public function test_les_barres_ne_font_pas_des_centaines_de_voyages(): void
    {
        // Le cas reel : 11 400 barres donnaient 285 voyages.
        $barre = $this->unite('BAR');

        if (!$barre) {
            $this->markTestSkipped('Unite Barre absente.');
        }

        $this->assertSame(1, Livreur::nombreDeVoyages(11400, 40, 40, $barre->id));
    }

    public function test_les_sacs_non_plus(): void
    {
        $sac = $this->unite('SAC');

        if (!$sac) {
            $this->markTestSkipped('Unite Sac absente.');
        }

        $this->assertSame(1, Livreur::nombreDeVoyages(5700, 40, 40, $sac->id));
    }

    public function test_une_location_de_deux_cents_jours_reste_un_voyage(): void
    {
        // « Jour » est l unite des locations : la diviser par un tonnage
        // faisait payer cinq deplacements pour une seule prise en charge.
        $jour = $this->unite('J');

        if (!$jour) {
            $this->markTestSkipped('Unite Jour absente.');
        }

        $this->assertSame(1, Livreur::nombreDeVoyages(200, 40, 40, $jour->id));
    }

    public function test_le_metre_cube_et_l_unite_aussi(): void
    {
        foreach (['M3', 'U', 'L', 'P'] as $abreviation) {
            $unite = $this->unite($abreviation);

            if (!$unite) {
                continue;
            }

            $this->assertSame(1, Livreur::nombreDeVoyages(5000, 40, 40, $unite->id),
                'L unite ' . $abreviation . ' ne se ramene pas a des tonnes.');
        }
    }

    // ------------------------------------------------------- LES GARDE-FOUS

    public function test_sans_unite_le_comportement_d_origine_est_conserve(): void
    {
        // Les appelants qui ne connaissent pas l unite gardent l ancien calcul :
        // le correctif ne doit pas changer en silence ce qu il ne voit pas.
        $this->assertSame(3, Livreur::nombreDeVoyages(100, 40, 40));
    }

    public function test_une_unite_inconnue_ne_fait_pas_tomber_le_calcul(): void
    {
        $this->assertSame(1, Livreur::nombreDeVoyages(100, 40, 40, 999999));
    }

    public function test_une_quantite_nulle_vaut_un_voyage(): void
    {
        $tonne = $this->unite('T');

        if (!$tonne) {
            $this->markTestSkipped('Unite Tonne absente.');
        }

        $this->assertSame(1, Livreur::nombreDeVoyages(0, 40, 40, $tonne->id));
    }

    public function test_une_capacite_absente_vaut_un_voyage(): void
    {
        $tonne = $this->unite('T');

        if (!$tonne) {
            $this->markTestSkipped('Unite Tonne absente.');
        }

        $this->assertSame(1, Livreur::nombreDeVoyages(100, 0, 0, $tonne->id));
    }

    // ----------------------------------------------------- LA CONVERSION

    public function test_le_facteur_de_conversion(): void
    {
        $this->assertSame(1.0, UniteProduit::facteurPour('T'));
        $this->assertSame(0.001, UniteProduit::facteurPour('kg'));
        $this->assertNull(UniteProduit::facteurPour('SAC'));
        $this->assertNull(UniteProduit::facteurPour(null));
    }

    public function test_la_paie_du_livreur_ne_s_envole_plus(): void
    {
        // Le montant, et non plus seulement le compteur : c est lui qui partait
        // a 715 000 F pour un transport facture 100 000 F.
        $barre = $this->unite('BAR');
        $livreur = Livreur::first();

        if (!$barre || !$livreur) {
            $this->markTestSkipped('Jeu de donnees insuffisant.');
        }

        $livreur->update([
            'mode_tarification'  => 'mixte',
            'tarif_forfait_base' => 2000,
            'tarif_km'           => 50,
        ]);

        $voyages = Livreur::nombreDeVoyages(11400, 40, 40, $barre->id);
        $tarif   = $livreur->fresh()->tarificationLivraison(10, 0, $voyages);

        // 2 000 + 50 x 10 = 2 500, une seule fois.
        $this->assertSame(2500.0, round($tarif['total'], 2));
    }
}
