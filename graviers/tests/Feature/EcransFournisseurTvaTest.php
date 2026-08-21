<?php

namespace Tests\Feature;

use App\Models\Configuration;
use App\Models\Enlevement;
use App\Models\Fournisseur;
use App\Models\TypeUser;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Le fournisseur ne reçoit que le coût du produit : la TVA revient à l'État et
 * le transport à l'entreprise. Seul un fournisseur DÉCLARÉ facture la TVA.
 *
 * On vérifie la règle de calcul, puis on fume les écrans qui l'affichent — un
 * appel resté sur l'ancien nom montantTtc() s'y traduirait par une 500.
 */
class EcransFournisseurTvaTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            [\Help::$USER_SA, 'Super Admin'],
            [\Help::$USER_ADMIN, 'Admin'],
            [\Help::$USER_FOURNISSEUR, 'Fournisseur'],
        ] as [$id, $nom]) {
            TypeUser::firstOrCreate(['id' => $id], ['nom' => $nom, 'statut' => 1]);
        }

        Configuration::firstOrCreate(['id' => 1], [
            'tva' => 18,
            'tonne_moyenne' => 25,
            'cout_liv_fixe' => 100,
            'cout_livraison_min' => 5000,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'type_user_id' => \Help::$USER_ADMIN,
            'statut'       => \Help::$STATUT_ACTIF,
        ]);
    }

    /** Un bon de 10 unités à 4 000 F, servi à 8. */
    private function bon(bool $assujetti): Enlevement
    {
        $compte = User::factory()->create([
            'type_user_id' => \Help::$USER_FOURNISSEUR,
            'statut'       => \Help::$STATUT_ACTIF,
        ]);

        $fournisseur = Fournisseur::factory()->create([
            'user_id'       => $compte->id,
            'assujetti_tva' => $assujetti,
        ]);

        return Enlevement::factory()->make([
            'fournisseur_id'   => $fournisseur->id,
            'qte'              => 10,
            'qte_servi'        => 8,
            'prix_fournisseur' => 4000,
        ])->setRelation('fournisseur', $fournisseur);
    }

    public function test_le_fournisseur_non_assujetti_ne_recoit_que_le_cout_du_produit(): void
    {
        $bon = $this->bon(false);

        $this->assertSame(32000.0, $bon->montantHt());
        $this->assertSame(32000.0, $bon->montantDu());
        $this->assertSame(0.0, $bon->tvaFournisseur());
    }

    public function test_le_fournisseur_assujetti_facture_la_tva_en_plus(): void
    {
        $bon = $this->bon(true);

        $this->assertSame(37760.0, $bon->montantDu());
        $this->assertSame(5760.0, $bon->tvaFournisseur());
    }

    public function test_le_du_porte_sur_la_quantite_servie_et_non_commandee(): void
    {
        $bon = $this->bon(false);

        // 8 servies sur 10 commandées : deux unités jamais livrées, jamais payées.
        $this->assertSame(8.0, $bon->quantiteAPayer());

        $bon->qte_servi = null; // Bon non encore servi : la dette prévisionnelle.
        $this->assertSame(10.0, $bon->quantiteAPayer());
    }

    /** @dataProvider ecrans */
    public function test_l_ecran_repond(string $url): void
    {
        $this->actingAs($this->admin())->get($url)->assertOk();
    }

    public static function ecrans(): array
    {
        return [
            'enlèvements fournisseurs'   => ['/fournisseurs/enlevements'],
            'paiements fournisseurs'     => ['/fournisseurs/paiements'],
            'liste fournisseurs'         => ['/sellers-list'],
            'tableau de bord dettes'     => ['/recap-dettes/tableau-bord'],
            'détail dettes fournisseurs' => ['/recap-dettes/detail-fournisseurs'],
        ];
    }
}
