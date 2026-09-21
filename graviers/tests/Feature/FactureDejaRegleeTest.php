<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Facture;
use App\Models\ModePaiement;
use App\Models\Paiement;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * UNE FACTURE DÉJÀ ENCAISSÉE AU GUICHET NE SE PAIE PAS UNE SECONDE FOIS.
 *
 * Constaté le 02/09/2026 : DA1 TECHNOLOGIE affichait « Versé en trop :
 * 33 960 FCFA ». Ce n'était pas un avoir — c'étaient DEUX factures payées
 * DEUX FOIS :
 *
 *     facture de location  340101 — 29 960 — réglée au guichet PUIS en créance
 *     facture de transport 800860 —  4 000 — idem
 *
 * L'argent arrive par deux routes qui ne se voyaient pas :
 *
 *   · LES GUICHETS rattachent le règlement à l'AFFAIRE (`service` +
 *     `service_id`) et laissent `facture_id` VIDE ;
 *   · l'écran client à terme le rattache à la FACTURE.
 *
 * Le contrôle « le montant dépasse-t-il le reste à payer ? » ne comptait que
 * la seconde route. Une facture soldée au comptoir lui apparaissait
 * entièrement due — et il laissait la régler de nouveau.
 *
 * La règle est désormais sur la facture, et tout le monde la lit.
 */
class FactureDejaRegleeTest extends TestCase
{
    use DatabaseTransactions;

    private function unAdmin(): User
    {
        $admin = User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN])
            ->where('statut', \Help::$STATUT_ACTIF)->first();

        if (!$admin) {
            $this->markTestSkipped('Aucun administrateur actif.');
        }

        if (!$admin->agence_id) {
            $agence = \App\Models\Agence::first();

            if (!$agence) {
                $this->markTestSkipped('Aucune agence.');
            }

            $admin->update(['agence_id' => $agence->id]);
        }

        return $admin;
    }

    private function uneFacture(string $service, int $serviceId, float $montant): Facture
    {
        $client = Client::first();

        if (!$client) {
            $this->markTestSkipped('Aucun client.');
        }

        return Facture::create([
            'numero'     => 'TST-' . uniqid(),
            'user_id'    => $this->unAdmin()->id,
            'client_id'  => $client->id,
            'montant'    => $montant,
            'service'    => $service,
            'service_id' => $serviceId,
            'statut'     => 2,
        ]);
    }

    /** Un encaissement de GUICHET : rattaché à l'affaire, sans `facture_id`. */
    private function unReglementDeGuichet(Facture $f): Paiement
    {
        return Paiement::create([
            'code'            => 'PAY-' . strtoupper(substr(md5(uniqid()), 0, 8)),
            'libelle'         => 'Encaissement guichet',
            'client_id'       => $f->client_id,
            'montant_total'   => $f->montant,
            'montant_restant' => 0,
            'statut'          => \Help::$STATUT_ACTIF,
            'service'         => $f->service,
            'service_id'      => $f->service_id,
        ]);
    }

    /** LE RÈGLEMENT DE GUICHET EST VU. */
    public function test_un_reglement_de_guichet_compte_dans_le_deja_regle(): void
    {
        $f = $this->uneFacture(\Help::$LOCATION, 999160, 29960);

        $this->assertSame(0.0, $f->montantDejaRegle());

        $this->unReglementDeGuichet($f);

        $this->assertSame(29960.0, $f->fresh()->montantDejaRegle(),
            'Le règlement encaissé au guichet n’est pas compté : la facture '
            . 'passe pour due alors qu’elle est payée.');

        $this->assertSame(0.0, $f->fresh()->resteAEncaisser(),
            'Il reste quelque chose à encaisser sur une facture soldée.');
    }

    /** ET L'ÉCRAN REFUSE DE LA FAIRE PAYER UNE SECONDE FOIS. */
    public function test_une_facture_soldee_au_guichet_ne_se_repaie_pas(): void
    {
        $f = $this->uneFacture(\Help::$LOCATION, 999161, 29960);
        $this->unReglementDeGuichet($f);

        $mode = ModePaiement::first();

        if (!$mode) {
            $this->markTestSkipped('Aucun mode de paiement.');
        }

        $avant = Paiement::where('facture_id', $f->id)->count();

        $this->actingAs($this->unAdmin())->post('/clients-terme/paiements', [
            'numero_facture'   => $f->numero,
            'mode_paiement_id' => $mode->id,
            'notes' => 'Recette automatique',
            'montant'          => 29960,
            'date_paiement'    => now()->toDateString(),
        ]);

        $this->assertSame($avant, Paiement::where('facture_id', $f->id)->count(),
            'La facture a été réglée une seconde fois : le client se retrouve '
            . 'avec un « versé en trop » qui n’est qu’un double encaissement, '
            . 'et l’entreprise lui doit une somme qu’elle a bel et bien reçue '
            . 'pour un service rendu.');
    }

    /** LE CAS COURANT RESTE POSSIBLE. */
    public function test_une_facture_non_reglee_s_encaisse_normalement(): void
    {
        $f = $this->uneFacture(\Help::$COMMANDE, 999151, 4260);

        $mode = ModePaiement::first();

        if (!$mode) {
            $this->markTestSkipped('Aucun mode de paiement.');
        }

        $this->actingAs($this->unAdmin())->post('/clients-terme/paiements', [
            'numero_facture'   => $f->numero,
            'mode_paiement_id' => $mode->id,
            'notes' => 'Recette automatique',
            'montant'          => 4260,
            'date_paiement'    => now()->toDateString(),
        ]);

        $this->assertSame(1, Paiement::where('facture_id', $f->id)->count(),
            'Une facture jamais réglée doit pouvoir être encaissée : le '
            . 'garde-fou ne doit pas bloquer le cas courant.');
    }

    /** UN RÈGLEMENT PORTANT LES DEUX RATTACHEMENTS N'EST COMPTÉ QU'UNE FOIS. */
    public function test_un_reglement_n_est_jamais_compte_deux_fois(): void
    {
        $f = $this->uneFacture(\Help::$COMMANDE, 999152, 10000);

        Paiement::create([
            'code'            => 'PAY-' . strtoupper(substr(md5(uniqid()), 0, 8)),
            'libelle'         => 'Règlement portant les deux liens',
            'client_id'       => $f->client_id,
            'montant_total'   => 10000,
            'montant_restant' => 0,
            'statut'          => \Help::$STATUT_ACTIF,
            'service'         => $f->service,
            'service_id'      => $f->service_id,
            'facture_id'      => $f->id,
        ]);

        $this->assertSame(10000.0, $f->fresh()->montantDejaRegle(),
            'Un règlement rattaché À LA FOIS à la facture et à l’affaire est '
            . 'compté deux fois : la facture passerait pour trop payée.');
    }
}
