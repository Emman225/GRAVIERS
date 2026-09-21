<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Commande;
use App\Models\Facture;
use App\Models\Paiement;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * UN RÈGLEMENT NE SE COMPTE QU'UNE FOIS.
 *
 * La facturation émet une facture par groupe d'enlèvements : plusieurs
 * factures partagent donc le MÊME `service_id`. Le calcul de l'encaissé
 * retenait tout règlement portant « ou bien le numéro de la facture, ou bien
 * celui de l'affaire » — les factures sœurs se volaient mutuellement leurs
 * encaissements.
 *
 * Constaté le 04/09/2026 en cherchant pourquoi la balance âgée était vide.
 * Deux conséquences, la seconde bien pire que la première :
 *
 *   · un chiffre d'encaissement doublé, visible ;
 *   · une DETTE INVISIBLE : deux factures affichées soldées par un seul
 *     règlement qui n'en couvre qu'une. La créance disparaissait de la balance
 *     âgée, donc des relances.
 */
class ReglementEntreFacturesSoeursTest extends TestCase
{
    use DatabaseTransactions;

    private function client(): Client
    {
        return Client::whereNotNull('user_id')->firstOrFail();
    }

    /** Deux factures de la MÊME commande, comme la facturation en produit. */
    private function deuxFacturesSoeurs(float $montant): array
    {
        $client = $this->client();
        $admin = User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN])
            ->where('statut', 1)->firstOrFail();
        $commande = Commande::firstOrFail();

        $faites = [];

        foreach ([0, 1] as $i) {
            $faites[] = Facture::create([
                'numero'     => 'T' . $i . uniqid(),
                'client_id'  => $client->id,
                'user_id'    => $client->user_id ?? $admin->id,
                'montant'    => $montant,
                'service'    => 'COMMANDE',
                'service_id' => $commande->id,
                'statut'     => 1,
            ]);
        }

        return $faites;
    }

    private function reglement(Facture $facture, float $montant, bool $nominatif): Paiement
    {
        return Paiement::create([
            'client_id'     => $facture->client_id,
            'code'          => 'P' . substr((string) uniqid(), -9),
            'libelle'       => 'Règlement',
            'montant_total' => $montant,
            // Nominatif : l'écran des créances désigne la facture.
            // Sinon : le guichet ne rattache qu'à l'affaire.
            'facture_id'    => $nominatif ? $facture->id : null,
            'service'       => 'COMMANDE',
            'service_id'    => $facture->service_id,
            'statut'        => \Help::$STATUT_ACTIF,
        ]);
    }

    /**
     * Chacune son règlement : chacune son montant, et rien de plus.
     */
    public function test_une_facture_ne_compte_pas_le_reglement_de_sa_soeur(): void
    {
        [$a, $b] = $this->deuxFacturesSoeurs(100000);

        $this->reglement($a, 100000, true);
        $this->reglement($b, 100000, true);

        $this->assertSame(100000.0, round($a->fresh()->montantPaye(), 2),
            'La première facture compte aussi le règlement de sa sœur.');
        $this->assertSame(100000.0, round($b->fresh()->montantPaye(), 2),
            'La seconde facture compte aussi le règlement de sa sœur.');
    }

    /**
     * LE CAS QUI CACHE UNE DETTE.
     *
     * Un seul règlement de comptoir, rattaché à l'affaire, pour deux factures.
     * Il en solde UNE. L'autre reste due — c'est tout l'enjeu : une dette que
     * personne ne voit n'est jamais relancée.
     */
    public function test_un_reglement_de_guichet_ne_solde_pas_deux_factures(): void
    {
        [$a, $b] = $this->deuxFacturesSoeurs(100000);

        $this->reglement($a, 100000, false);

        $paye = round($a->fresh()->montantPaye(), 2) + round($b->fresh()->montantPaye(), 2);

        $this->assertSame(100000.0, $paye,
            'Un seul règlement de 100 000 est compté deux fois : '
            . 'il solde deux factures qu\'il ne couvre pas.');

        $restes = [round($a->fresh()->resteAPayer(), 2), round($b->fresh()->resteAPayer(), 2)];
        sort($restes);

        $this->assertSame([0.0, 100000.0], $restes,
            'Une facture doit rester due : sans cela, la créance disparaît '
            . 'de la balance âgée et n\'est jamais relancée.');
    }

    /**
     * LA RÉPARTITION S'ARRÊTE AU DÛ.
     *
     * Un versement supérieur au total des factures ne doit pas gonfler
     * l'encaissé de chacune : le surplus est un versement en trop, traité
     * ailleurs, pas un paiement.
     */
    public function test_le_surplus_n_est_attribue_a_personne(): void
    {
        [$a, $b] = $this->deuxFacturesSoeurs(50000);

        $this->reglement($a, 500000, false);

        $paye = round($a->fresh()->montantPaye(), 2) + round($b->fresh()->montantPaye(), 2);

        $this->assertSame(100000.0, $paye,
            'Le versement en trop est compté comme un encaissement de facture.');
    }

    /**
     * UNE FACTURE SEULE N'EST PAS PÉNALISÉE.
     *
     * La correction ne doit pas casser le cas courant, qui est le seul en
     * production aujourd'hui : une affaire, une facture, un règlement de
     * guichet qui ne nomme aucune facture.
     */
    public function test_une_affaire_a_facture_unique_encaisse_normalement(): void
    {
        [$a, $b] = $this->deuxFacturesSoeurs(100000);

        // On isole la seconde sur sa propre affaire.
        $b->update(['service_id' => $b->service_id + 100000]);

        $this->reglement($a, 100000, false);

        $this->assertSame(100000.0, round($a->fresh()->montantPaye(), 2),
            'Un règlement de guichet doit toujours solder la facture de son affaire.');
        $this->assertSame(0.0, round($b->fresh()->montantPaye(), 2),
            'Une facture d\'une autre affaire ne doit rien encaisser.');
    }
}
