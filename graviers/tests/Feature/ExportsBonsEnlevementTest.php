<?php

namespace Tests\Feature;

use App\Models\Commande;
use App\Models\Configuration;
use App\Models\Fournisseur;
use App\Models\TypeUser;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Les cinq écrans autour du bon d'enlèvement doivent proposer Excel, Word et PDF.
 */
class ExportsBonsEnlevementTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            [\Help::$USER_ADMIN, 'Admin'],
            [\Help::$USER_FOURNISSEUR, 'Fournisseur'],
        ] as [$id, $nom]) {
            TypeUser::firstOrCreate(['id' => $id], ['nom' => $nom, 'statut' => 1]);
        }

        Configuration::firstOrCreate(['id' => 1], [
            'tva' => 18, 'tonne_moyenne' => 25,
            'cout_liv_fixe' => 100, 'cout_livraison_min' => 5000,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'type_user_id' => \Help::$USER_ADMIN,
            'statut'       => \Help::$STATUT_ACTIF,
        ]);
    }

    /** Les trois boutons sont-ils présents ? */
    private function assertTroisExports(string $url): void
    {
        $reponse = $this->actingAs($this->admin())->get($url)->assertOk();

        foreach (['Excel', 'Word', 'PDF'] as $format) {
            $reponse->assertSee($format, false);
        }
    }

    public function test_liste_des_fournisseurs_pour_les_bons(): void
    {
        $this->assertTroisExports('/sellers-list-pour-les-bon');
    }

    public function test_bons_par_fournisseur(): void
    {
        $fournisseur = Fournisseur::first();
        if (!$fournisseur) {
            $this->markTestSkipped('Aucun fournisseur en base.');
        }

        $this->assertTroisExports('/les-bons-par-fournisseur-' . $fournisseur->id);
    }

    public function test_bons_en_attente(): void
    {
        $this->assertTroisExports('/bonAttente');
    }

    public function test_bons_valides(): void
    {
        $this->assertTroisExports('/bonValides');
    }

    public function test_page_du_bon_d_une_commande(): void
    {
        $commande = Commande::whereNotNull('numero')->first();
        if (!$commande) {
            $this->markTestSkipped('Aucune commande en base.');
        }

        $this->assertTroisExports('/orders-be/' . $commande->numero);
    }

    public function test_le_word_du_bon_est_bien_un_document_word(): void
    {
        $commande = Commande::whereNotNull('numero')->first();
        if (!$commande) {
            $this->markTestSkipped('Aucune commande en base.');
        }

        $reponse = $this->actingAs($this->admin())
            ->get('/orders-be/' . $commande->numero . '/word')
            ->assertOk();

        $this->assertStringContainsString('application/msword', $reponse->headers->get('Content-Type'));
        $this->assertStringContainsString(
            'bon-commande-' . $commande->numero . '.doc',
            $reponse->headers->get('Content-Disposition')
        );
        // Le BOM : sans lui, Word retombe sur son encodage régional.
        $this->assertStringStartsWith("\u{FEFF}", $reponse->getContent());
    }

    public function test_le_pdf_du_bon_repond_toujours(): void
    {
        $commande = Commande::whereNotNull('numero')->first();
        if (!$commande) {
            $this->markTestSkipped('Aucune commande en base.');
        }

        $this->actingAs($this->admin())
            ->get('/orders-be/' . $commande->numero . '/pdf')
            ->assertOk();
    }
}
