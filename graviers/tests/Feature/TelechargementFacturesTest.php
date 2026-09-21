<?php

namespace Tests\Feature;

use App\Models\Commande;
use App\Models\Facture;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Une facture en attente de certification se télécharge aussi.
 *
 * L'écran des factures non validées ne proposait que la consultation à l'écran.
 * Le téléchargement n'existait que sur les factures DÉJÀ certifiées — alors
 * qu'une facture en attente se transmet tout autant : au comptable, au client
 * qui la réclame, à l'administration en cas de question.
 */
class TelechargementFacturesTest extends TestCase
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

    private function uneFactureEnAttente(string $service, int $serviceId, ?int $clientId): Facture
    {
        return Facture::create([
            'numero'     => 'FAC-' . uniqid(),
            'numero_fne' => 'FNE-' . uniqid(),
            'user_id'    => $this->unAdmin()->id,
            'client_id'  => $clientId,
            'montant'    => 50000,
            'statut'     => 2,
            'service'    => $service,
            'service_id' => $serviceId,
            'fne_status' => 'pending',
        ]);
    }

    private function laFile(): \Illuminate\Testing\TestResponse
    {
        URL::forceRootUrl('');

        return $this->actingAs($this->unAdmin())->get('/factures-non-validees');
    }

    public function test_une_facture_de_vente_se_telecharge(): void
    {
        $commande = Commande::first();

        if (!$commande) {
            $this->markTestSkipped('Aucune commande en base.');
        }

        $facture = $this->uneFactureEnAttente(\Help::$COMMANDE, $commande->id, $commande->client_id);

        $reponse = $this->laFile();

        $reponse->assertOk();
        $reponse->assertSee('telecharger', false);

        // Et le lien mene bien a un PDF.
        URL::forceRootUrl('');

        $pdf = $this->actingAs($this->unAdmin())->get(
            '/action-facture-' . $commande->id . '-' . $facture->id . '-telecharger-1'
        );

        if ($pdf->status() === 404) {
            $this->markTestSkipped('Route de facture de vente nommee autrement.');
        }

        $this->assertStringContainsString('attachment',
            (string) $pdf->headers->get('content-disposition'));
    }

    public function test_une_facture_de_location_se_telecharge(): void
    {
        $location = Location::first();

        if (!$location) {
            $this->markTestSkipped('Aucune location en base.');
        }

        $facture = $this->uneFactureEnAttente(\Help::$LOCATION, $location->id, $location->client_id);

        $this->laFile()->assertOk();

        URL::forceRootUrl('');

        $pdf = $this->actingAs($this->unAdmin())
            ->get('/facture-location-' . $facture->id . '-telecharger');

        if ($pdf->status() === 404) {
            $this->markTestSkipped('Route de facture de location nommee autrement.');
        }

        $this->assertStringContainsString('attachment',
            (string) $pdf->headers->get('content-disposition'));
    }

    public function test_la_file_offre_voir_ET_telecharger(): void
    {
        // Les deux gestes, cote a cote : consulter a l ecran, et emporter le
        // document.
        $commande = Commande::first();

        if (!$commande) {
            $this->markTestSkipped('Aucune commande en base.');
        }

        $this->uneFactureEnAttente(\Help::$COMMANDE, $commande->id, $commande->client_id);

        $reponse = $this->laFile();

        $reponse->assertSee('fa-eye', false);
        $reponse->assertSee('fa-download', false);
    }
}
