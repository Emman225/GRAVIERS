<?php

namespace Tests\Feature;

use App\Models\Configuration;
use App\Models\Enlevement;
use Tests\TestCase;

/**
 * L'en-tête des bons de livraison.
 *
 * Les deux exemplaires — celui du client et celui du fournisseur — portaient
 * un logo commenté et trois libellés vides (« Télécopie : », « Adresse
 * mail : », « Site internet : »), ainsi qu'une adresse écrite en dur qui
 * n'était plus celle de l'entreprise.
 */
class EnteteBonLivraisonTest extends TestCase
{
    private function unBon(): Enlevement
    {
        $bon = Enlevement::whereNull('deleted_at')->first();

        if (!$bon) {
            $this->markTestSkipped('Aucun enlèvement en base.');
        }

        return $bon;
    }

    public function test_les_deux_logos_sont_presents_sur_le_bon_du_client(): void
    {
        $html = view('livreur.bonImprime', ['enlevement' => $this->unBon()])->render();

        // Les images sont embarquées en base64 : dompdf ne va pas chercher une
        // URL, et c'est ce format qui garantit qu'elles s'impriment.
        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringContainsString('alt="GRAVIER.COM"', $html);
        $this->assertStringContainsString('DALAKOUN', $html);
    }

    public function test_l_entete_reprend_la_fiche_de_l_entreprise(): void
    {
        $fiche = Configuration::first();

        if (!$fiche || trim((string) $fiche->adresse_siege) === '') {
            $this->markTestSkipped("La fiche de l'entreprise n'est pas renseignée.");
        }

        $html = view('livreur.bonImprime', ['enlevement' => $this->unBon()])->render();

        $this->assertStringContainsString($fiche->adresse_siege, $html);
        // L'adresse écrite en dur a disparu.
        $this->assertStringNotContainsString('02 BP 578 Abidjan 02', $html);
    }

    public function test_aucun_libelle_ne_reste_sans_valeur(): void
    {
        foreach (['livreur.bonImprime', 'fournisseur.bonImprime'] as $vue) {
            $bon = $this->unBon();

            $html = $vue === 'livreur.bonImprime'
                ? view($vue, ['enlevement' => $bon])->render()
                : view($vue, ['bon' => $bon, 'produit' => $bon->produit])->render();

            // Un libellé suivi de rien laisse croire à une donnée oubliée : soit
            // la valeur est là, soit la ligne n'est pas imprimée.
            foreach (['Télécopie', 'Site internet :</', 'Adresse mail :</'] as $vide) {
                $this->assertStringNotContainsString($vide, $html, "Libellé vide dans {$vue}");
            }
        }
    }

    public function test_le_bon_du_fournisseur_annonce_la_date_et_le_vehicule(): void
    {
        $bon = $this->unBon();

        $html = view('fournisseur.bonImprime', ['bon' => $bon, 'produit' => $bon->produit])->render();

        $this->assertStringContainsString('En date du :', $html);
        $this->assertStringContainsString('Matricule du véhicule :', $html);
        $this->assertStringContainsString('Contact :', $html);
    }
}
