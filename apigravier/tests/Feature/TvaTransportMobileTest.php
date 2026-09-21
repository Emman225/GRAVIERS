<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Configuration;
use App\Models\CoutLivraison;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * LA MÊME COURSE, LE MÊME PRIX, QUEL QUE SOIT LE CANAL.
 *
 * Le site ajoutait 18 % au transport, l'application non : la même course coûtait
 * 23 600 F depuis le site et 20 000 F depuis le téléphone. L'arbitrage du
 * 13/08/2026 a supprimé la taxe ; le responsable demande aujourd'hui de pouvoir
 * la rétablir, sur décision.
 *
 * Le piège est de poser cette option sur le site seul : l'écart entre les deux
 * canaux renaîtrait aussitôt. Le réglage se pose donc une fois et vaut pour les
 * deux — c'est ce que ce fichier tient.
 */
class TvaTransportMobileTest extends TestCase
{
    use DatabaseTransactions;

    private function laConfig(): Configuration
    {
        $config = Configuration::first();

        if (!$config) {
            $this->markTestSkipped('Aucune configuration en base.');
        }

        return $config;
    }

    private function uneTranche(): CoutLivraison
    {
        $tranche = CoutLivraison::where('distance_min_km', '<=', 0)
            ->where('distance_max_km', '>=', 0)
            ->where('prix_km', '>', 0)
            ->whereNull('ville_id')
            ->first();

        if (!$tranche) {
            $this->markTestSkipped('Aucune tranche de grille couvrant 0 km.');
        }

        return $tranche;
    }

    private function unClient(): Client
    {
        $client = Client::whereNotNull('user_id')->first();

        if (!$client || !$client->user) {
            $this->markTestSkipped('Aucun client rattaché à un compte.');
        }

        return $client;
    }

    /** Le devis renvoyé à l'application avant confirmation. */
    private function chiffrer(CoutLivraison $tranche): array
    {
        $client = $this->unClient();

        $reponse = $this->postJson('/mon_gravier/resume-demande-livraison', [
            'access'   => Crypt::encryptString((string) $client->user_id),
            'type'     => (string) $client->user->type_user_id,
            'distance' => 0,
            'lignes'   => [[
                'produit'   => 'Sable',
                'qte'       => max(1, (float) $tranche->unite_min),
                'unite'     => 'Tonne',
                'unite_id'  => $tranche->unite_produit_id,
            ]],
            'demande' => [
                'adresseDepart'      => 0,
                'adresseDestination' => 0,
                'typeLivraison'      => 0,
                'modePaiement'       => 0,
                'dateLivraison'      => date('Y-m-d'),
                'note'               => 'Course de test',
            ],
        ]);

        $reponse->assertOk();

        return $reponse->json('data') ?? [];
    }

    public function test_sans_decision_l_application_annonce_le_tarif_de_la_grille(): void
    {
        $tranche = $this->uneTranche();

        $this->laConfig()->update(['tva_transport' => 0]);

        $data = $this->chiffrer($tranche);

        $this->assertEquals((float) $tranche->prix_km, (float) ($data['montant'] ?? -1),
            "Sans option, le prix annoncé est celui de la grille, sans taxe.");

        $this->assertEquals(0, (float) ($data['tva'] ?? -1));
    }

    public function test_option_activee_l_application_annonce_le_ttc(): void
    {
        $tranche = $this->uneTranche();
        $config  = $this->laConfig();

        $config->update(['tva_transport' => 1]);

        $data = $this->chiffrer($tranche);

        $tvaAttendue = round((float) $tranche->prix_km * ((float) $config->tva) / 100);

        $this->assertEquals($tvaAttendue, (float) ($data['tva'] ?? -1),
            'La taxe doit être calculée sur le tarif de la grille.');

        // Ce que le client paiera : c'est ce total-là qui doit égaler celui du
        // site pour la même course.
        $this->assertEquals(
            (float) $tranche->prix_km + $tvaAttendue,
            (float) ($data['montant'] ?? -1)
        );

        // Le hors taxe reste lisible à part : l'application peut détailler.
        $this->assertEquals((float) $tranche->prix_km, (float) ($data['montant_ht'] ?? -1));

        $config->update(['tva_transport' => 0]);
    }
}
