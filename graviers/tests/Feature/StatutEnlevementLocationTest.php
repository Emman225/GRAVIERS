<?php

namespace Tests\Feature;

use App\Models\Enlevement;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * LE CODE D'ENLÈVEMENT SEUL NE DIT PAS SI LE MATÉRIEL EST SORTI.
 *
 * Sur /locations-traitees, la colonne « Codes à communiquer » donnait le code
 * du bon et le nom du fournisseur — mais pas l'essentiel : ce fournisseur
 * a-t-il validé ? Tant qu'il ne l'a pas fait, RIEN n'a été remis, et
 * communiquer le code au client ne sert à rien. Le gestionnaire devait ouvrir
 * une autre page pour le savoir.
 *
 * Et une fois validé, ce qui compte n'est plus le fait mais la QUANTITÉ
 * SERVIE : un enlèvement partiel laisse du matériel à retirer — c'est le
 * défaut constaté sur la commande 627042, où 5 tonnes servies sur 15 avaient
 * clos la commande.
 */
class StatutEnlevementLocationTest extends TestCase
{
    use DatabaseTransactions;

    private function unBon(array $attributs = []): Enlevement
    {
        return new Enlevement(array_merge([
            'qte'                    => 15,
            'qte_servi'              => null,
            'fournisseur_validation' => null,
        ], $attributs));
    }

    /** TANT QUE LE FOURNISSEUR N'A PAS VALIDÉ, ON LE DIT. */
    public function test_un_bon_non_valide_est_annonce_en_attente(): void
    {
        $bon = $this->unBon();

        $this->assertFalse($bon->estValideParFournisseur());
        $this->assertSame('En attente du fournisseur',
            $bon->libelleValidationFournisseur(),
            'Le gestionnaire communique le code sans savoir que rien n’est '
            . 'sorti de chez le fournisseur.');
    }

    /** VALIDÉ ET SERVI EN ENTIER : LA DATE SUFFIT. */
    public function test_un_bon_servi_en_entier_annonce_sa_date(): void
    {
        $bon = $this->unBon([
            'qte_servi'              => 15,
            'fournisseur_validation' => '2026-09-01 10:30:00',
        ]);

        $this->assertTrue($bon->estValideParFournisseur());
        $this->assertSame('Validé le 01/09/2026', $bon->libelleValidationFournisseur());
    }

    /** SERVI EN PARTIE : LE RELIQUAT SE VOIT. */
    public function test_un_enlevement_partiel_annonce_ce_qui_manque(): void
    {
        $libelle = $this->unBon([
            'qte_servi'              => 5,
            'fournisseur_validation' => '2026-09-01 10:30:00',
        ])->libelleValidationFournisseur();

        $this->assertStringContainsString('partiellement', $libelle);
        $this->assertStringContainsString('5 sur 15', $libelle,
            'Le gestionnaire doit lire le reliquat : sans lui, il croit '
            . 'l’enlèvement soldé et ne réclame jamais les 10 restants.');
    }

    /**
     * UN BON ANCIEN N'EST PAS UN ENLÈVEMENT PARTIEL.
     *
     * `qte_servi` à NULL vaut la quantité demandée : ce sont les bons émis
     * avant que la saisie n'existe. Les annoncer « partiels » ferait courir
     * après un reliquat qui n'existe pas.
     */
    public function test_un_bon_sans_quantite_servie_n_est_pas_partiel(): void
    {
        $libelle = $this->unBon(['fournisseur_validation' => '2026-09-01 10:30:00'])
            ->libelleValidationFournisseur();

        $this->assertSame('Validé le 01/09/2026', $libelle);
    }

    /** L'ÉCRAN AFFICHE BIEN CE STATUT. */
    public function test_l_ecran_affiche_le_statut_du_fournisseur(): void
    {
        $vue = file_get_contents(
            resource_path('views/gestionnaire/locationsTraitees.blade.php'));

        $this->assertStringContainsString('libelleValidationFournisseur()', $vue,
            'La colonne « Codes à communiquer » ne dit pas où en est '
            . 'l’enlèvement.');
    }
}
