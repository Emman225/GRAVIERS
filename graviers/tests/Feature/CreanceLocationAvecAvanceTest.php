<?php

namespace Tests\Feature;

use App\Models\Agence;
use App\Models\AvanceClient;
use App\Models\Client;
use App\Models\DetailLocation;
use App\Models\Facture;
use App\Models\Location;
use App\Models\Produit;
use App\Models\TvaCommande;
use App\Models\User;
use App\Services\Avances;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * LE GUICHET DES CRÉANCES ET LES FACTURES DE LOCATION (10/09/2026) : le client
 * de la facture est nommé (il se lisait via la commande, donc « - »), et la
 * mention de l'avance imputée apparaît comme pour une commande. Une location
 * n'a jamais qu'une facture, émise à la demande, jamais au règlement.
 */
class CreanceLocationAvecAvanceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_une_facture_de_location_porte_son_client_et_l_avance_imputee(): void
    {
        $admin = User::whereIn('type_user_id', [1, 2])->where('statut', 1)->orderBy('id')->first();
        $client = Client::where('statut', 1)->where('client_a_terme', 1)->whereHas('user')->first()
            ?: Client::where('statut', 1)->whereHas('user')->first();
        if (!$admin || !$client) {
            $this->markTestSkipped('Il manque un administrateur ou un client.');
        }
        $client->update(['client_a_terme' => 1]);
        if (!$admin->agence_id) {
            $admin->agence_id = Agence::value('id');
            $admin->save();
        }

        $produit  = Produit::where('statut', 1)->first() ?: Produit::first();
        $location = Location::create([
            'numero' => 'T' . random_int(100000, 999999), 'client_id' => $client->id, 'montant_total' => 200000,
            'etat_location' => \Help::$LOCATION_EN_ATTENTE, 'statut' => 1, 'remise' => 0, 'cout_livraison_client' => 0,
        ]);
        DetailLocation::create([
            'produit_id' => $produit->id, 'location_id' => $location->id, 'qte' => 1, 'debut' => now()->toDateString(),
            'fin' => now()->toDateString(), 'prix' => 200000, 'nombre_jour' => 1, 'etat_location' => \Help::$LOCATION_EN_ATTENTE,
        ]);
        TvaCommande::create(['client_id' => $client->id, 'commande_id' => $location->id, 'montant' => 36000, 'type_affaire' => \Help::$LOCATION]);
        $location = Location::find($location->id);

        // L'avance s'impute : un règlement AV, aucune facture.
        AvanceClient::create([
            'client_id' => $client->id, 'montant' => 50000, 'montant_consomme' => 0, 'statut' => AvanceClient::DISPONIBLE,
            'numero_recu' => 'RA-TEST-' . random_int(100, 999), 'date_depot' => now(),
        ]);
        Avances::imputerSurLocation($location, $admin->id);
        $this->assertSame(0, Facture::where('service', 'LOCATION')->where('service_id', $location->id)->count(), 'Un règlement de location n\'émet pas de facture.');

        // La facture de location, émise à la demande, porte toute la location ; son reste est le reliquat.
        Auth::guard('web')->login($admin);
        $this->post(route('orders.genererFactureLocation', $location->id))->assertRedirect();
        $factures = Facture::where('service', 'LOCATION')->where('service_id', $location->id)->get();
        $this->assertCount(1, $factures, 'Une location, une facture.');
        $facture = $factures->first();
        $this->assertEqualsWithDelta(236000, (float) $facture->montant, 1, 'HT 200 000 + TVA 18 %.');
        $this->assertEqualsWithDelta(186000, $facture->resteAEncaisser(), 1, 'Le reste de la facture est le reliquat après avance.');

        // Un second appel ne crée pas de doublon.
        $this->post(route('orders.genererFactureLocation', $location->id))->assertRedirect();
        $this->assertSame(1, Facture::where('service', 'LOCATION')->where('service_id', $location->id)->count());

        $html = $this->get('/clients-terme/paiements')->assertOk()->getContent();
        $debut = strpos($html, 'value="' . $facture->numero . '"');
        $this->assertNotFalse($debut, 'La facture de location doit être proposée au guichet des créances.');
        $ligne = substr($html, $debut, 4000);
        $this->assertStringContainsString('data-client="' . e($client->display_name) . '"', $ligne, 'Le client de la facture de location doit être nommé.');
        $this->assertStringContainsString('data-reste="186000"', $ligne);
        $this->assertStringContainsString('Location ' . $location->numero, $ligne, 'La mention de l\'avance nomme la location.');
        $this->assertStringContainsString('50 000', $ligne);
    }
}
