<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Facture;
use App\Models\LignePaiement;
use App\Models\ModePaiement;
use App\Models\Paiement;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * UN RÈGLEMENT DÉSIGNE LE SERVICE RÉELLEMENT FACTURÉ.
 *
 * Constaté le 02/09/2026 : l'espace client de DA1 TECHNOLOGIE annonçait
 * « Versé en trop : 33 960 FCFA ». Décomposition :
 *
 *     29 960  règlement de la facture de LOCATION 340101
 *      4 000  règlement de la facture de TRANSPORT 800860
 *
 * Les deux avaient été encaissés depuis /clients-terme/paiements, qui
 * estampillait TOUT règlement `service = 'COMMANDE'` — en dur — avec le
 * `service_id` de la facture. Un règlement désignait donc une commande n° 60
 * qui n'existe pas.
 *
 * LA CONSÉQUENCE DÉPASSE L'AFFICHAGE : plus rien n'absorbait ces règlements.
 * Le client les voyait à son crédit pendant que sa facture restait due — la
 * même somme comptée deux fois en sa faveur.
 *
 * Cet écran encaisse les factures de TOUS les services : vente, location,
 * transport. Le service se lit donc sur la facture.
 */
class ReglementClientTermeServiceTest extends TestCase
{
    use DatabaseTransactions;

    private function unAdmin(): User
    {
        $admin = User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN])
            ->where('statut', \Help::$STATUT_ACTIF)->first();

        if (!$admin) {
            $this->markTestSkipped('Aucun administrateur actif.');
        }

        // L'AGENCE EST INDISPENSABLE : `storePaiement` refuse tout encaissement
        // d'un caissier non rattaché à un guichet — sinon la recette d'une
        // agence serait créditée à une autre.
        //
        // On la POSE plutôt que de sauter l'essai : un essai qui se saute ne
        // prouve rien, et c'est justement le chemin qu'il faut éprouver.
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

    private function encaisser(Facture $f, float $montant)
    {
        $mode = ModePaiement::first();

        if (!$mode) {
            $this->markTestSkipped('Aucun mode de paiement.');
        }

        return $this->actingAs($this->unAdmin())->post('/clients-terme/paiements', [
            'numero_facture'   => $f->numero,
            'mode_paiement_id' => $mode->id,
            'notes' => 'Recette automatique',
            'montant'          => $montant,
            'date_paiement'    => now()->toDateString(),
        ]);
    }

    /** UNE FACTURE DE LOCATION PRODUIT UN RÈGLEMENT DE LOCATION. */
    public function test_le_reglement_d_une_location_designe_la_location(): void
    {
        $facture = $this->uneFacture(\Help::$LOCATION, 999160, 29960);

        $this->encaisser($facture, 29960);

        $paiement = Paiement::where('facture_id', $facture->id)->latest('id')->first();

        $this->assertNotNull($paiement, 'Aucun règlement enregistré.');

        $this->assertSame(\Help::$LOCATION, $paiement->service,
            'Le règlement d’une facture de LOCATION est rangé en COMMANDE : il '
            . 'désigne une commande qui n’existe pas, plus rien ne l’absorbe, et '
            . 'le client le voit en « versé en trop » pendant que sa facture '
            . 'reste due.');

        $this->assertSame(\Help::$LOCATION,
            LignePaiement::where('paiement_id', $paiement->id)->value('service'),
            'La ligne de règlement doit désigner le même service que son '
            . 'règlement, sans quoi les états comptables divergent.');
    }

    /** UNE FACTURE DE TRANSPORT AUSSI. */
    public function test_le_reglement_d_un_transport_designe_le_transport(): void
    {
        $facture = $this->uneFacture(\Help::$LIVRAISON, 999119, 4000);

        $this->encaisser($facture, 4000);

        $this->assertSame(\Help::$LIVRAISON,
            Paiement::where('facture_id', $facture->id)->latest('id')->value('service'),
            'Le règlement d’une facture de TRANSPORT est rangé en COMMANDE.');
    }

    /** LE CAS COURANT N'EST PAS CASSÉ. */
    public function test_le_reglement_d_une_vente_reste_une_vente(): void
    {
        $facture = $this->uneFacture(\Help::$COMMANDE, 999151, 4260);

        $this->encaisser($facture, 4260);

        $this->assertSame(\Help::$COMMANDE,
            Paiement::where('facture_id', $facture->id)->latest('id')->value('service'),
            'Une facture de vente doit produire un règlement de vente.');
    }

    /** LE SERVICE N'EST PLUS ÉCRIT EN DUR. */
    public function test_le_service_se_lit_sur_la_facture(): void
    {
        $source = file_get_contents(
            app_path('Http/Controllers/CreanceClientTermeController.php'));

        $this->assertSame(2, substr_count($source, '$f->service ?:'),
            'Le règlement ou sa ligne décide encore du service sans regarder la '
            . 'facture.');
    }
}
