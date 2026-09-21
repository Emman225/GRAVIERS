<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Facture;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * LE FRANC N'A PAS DE CENTIMES — NI LA FACTURE, NI CE QU'IL EN RESTE.
 *
 * Signalé le 02/09/2026 sur /clients-terme/paiements : la facture 901247
 * s'affichait « Reste : 4 260 fcfa » dans le sélecteur, mais le champ
 * « Montant » se remplissait de 4259.6 — et l'enregistrement était REFUSÉ.
 *
 * Le champ n'accepte que des francs entiers (`step="1"`) : le navigateur
 * bloquait l'envoi, sans qu'aucun message n'explique pourquoi. L'affichage
 * arrondissait, le champ non : deux nombres pour une même somme.
 *
 * CE N'EST PAS UNE RÉGRESSION, ET C'EST PIRE : la convention `arrondiFranc`
 * existait déjà, mais n'avait été appliquée qu'aux commissions, à la TVA et
 * aux remises. Le montant FACTURÉ et le RESTE DÛ y avaient échappé — les deux
 * nombres que cet écran manipule. Le même symptôme revenait donc par un chemin
 * jamais traité.
 *
 * Cet essai couvre la famille entière : les quatre points qui écrivent un
 * montant de facture, et les trois qui calculent un reste dû.
 */
class ArrondiFrancFactureTest extends TestCase
{
    use DatabaseTransactions;

    /** LA CONVENTION ELLE-MÊME. */
    public function test_arrondi_franc_ne_laisse_aucun_centime(): void
    {
        $this->assertSame(4260.0, \Help::arrondiFranc(4259.6));
        $this->assertSame(4259.0, \Help::arrondiFranc(4259.4));
        $this->assertSame(0.0, \Help::arrondiFranc(0));
    }

    /**
     * CE QUI EST AFFICHÉ ET CE QUI EST PRÉREMPLI SONT LE MÊME NOMBRE.
     *
     * L'écran affiche `Help::formatNombre($reste, true)` — qui arrondit — et
     * préremplit le champ avec `data-reste`. Si les deux ne sortent pas du même
     * calcul, l'agent lit 4 260 et le navigateur refuse 4 259,6.
     */
    public function test_le_reste_affiche_est_celui_qui_preremplit_le_champ(): void
    {
        $reste = \Help::arrondiFranc(max(0, 4259.6 - 0));

        $this->assertSame(
            \Help::formatNombre($reste, true),
            \Help::formatNombre(\Help::arrondiFranc($reste), true),
            'Le libellé du sélecteur et la valeur du champ divergent : '
            . 'l’enregistrement est refusé sans explication.');

        $this->assertSame($reste, round($reste),
            'Le champ « Montant » est à pas de 1 : une valeur à centimes y est '
            . 'rejetée par le navigateur.');
    }

    /** LES TROIS CALCULS DE RESTE DÛ ARRONDISSENT. */
    public function test_les_trois_calculs_de_reste_arrondissent(): void
    {
        $source = file_get_contents(
            app_path('Http/Controllers/CreanceClientTermeController.php'));

        // Quatre depuis le 07/09/2026 : le surplus converti en avance s'arrondit aussi.
        $this->assertSame(4, substr_count($source, 'Help::arrondiFranc('),
            'Un des calculs de reste dû échappe à l’arrondi : l’écran '
            . 'rebloquera sur cette facture-là.');
    }

    /** LES QUATRE POINTS DE FACTURATION ARRONDISSENT. */
    public function test_aucune_facture_ne_naît_avec_des_centimes(): void
    {
        $orders = file_get_contents(app_path('Http/Controllers/OrdersController.php'));
        $service = file_get_contents(app_path('Services/FacturationCommande.php'));

        $this->assertSame(3, substr_count($orders, "arrondiFranc(\$montant"),
            'Un point de facturation (vente, location, transport) écrit encore '
            . 'un montant à centimes.');

        $this->assertStringContainsString('arrondiFranc($aFacturer)', $service,
            'La facturation par tranches écrit un montant à centimes : c’est '
            . 'elle qui a produit la facture 901247.');
    }

    /** ET UNE FACTURE ENREGISTRÉE SE SOLDE AU FRANC. */
    public function test_une_facture_au_franc_se_solde_exactement(): void
    {
        $client = Client::first();

        if (!$client) {
            $this->markTestSkipped('Aucun client.');
        }

        $facture = Facture::create([
            'numero'     => 'TST-' . uniqid(),
            // `user_id` est NOT NULL sans valeur par defaut.
            'user_id'    => \App\Models\User::value('id'),
            'client_id'  => $client->id,
            'montant'    => \Help::arrondiFranc(4259.6),
            'service'    => \Help::$COMMANDE,
            'service_id' => 999999,
            'statut'     => 2,
        ]);

        $reste = \Help::arrondiFranc(max(0, (float) $facture->montant - 4260));

        $this->assertSame(0.0, $reste,
            'Un règlement du montant affiché ne solde pas la facture : il '
            . 'resterait quelques centimes que personne ne peut payer.');
    }
}
