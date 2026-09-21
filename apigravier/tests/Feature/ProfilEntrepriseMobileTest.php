<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/** Le client entreprise complet depuis l'application (lot 100, 16/09/2026). */
class ProfilEntrepriseMobileTest extends TestCase
{
    use DatabaseTransactions;

    public function test_une_entreprise_ne_s_inscrit_pas_sans_ncc(): void
    {
        $r = $this->postJson('/mon_gravier/inscription', [
            'nom_prenoms' => 'SOCIETE TEST', 'email' => 'test-' . uniqid() . '@recette.ci', 'contact' => '0700000000',
            'password' => 'Test1234!', 'type_client' => 2, 'pays_id' => 1, 'ville_id' => 1,
        ]);
        $r->assertOk()->assertJson(['code' => 501]);
        $this->assertStringContainsString('NCC', $r->json('message'));
        $this->assertStringContainsString('RCCM', $r->json('message'));
    }

    public function test_mes_informations_renvoie_et_enregistre_le_ncc_de_l_entreprise(): void
    {
        $client = Client::whereNotNull('user_id')->get()->first(fn ($c) => User::find($c->user_id));
        if (!$client) {
            $this->markTestSkipped('Aucun client avec compte.');
        }
        Client::where('id', $client->id)->update(['type_client' => \Help::$ENTREPRISE, 'ncc_clt' => '1111111A', 'rccm_clt' => 'CI-ABJ-1', 'regime_imposition' => 'RNI']);
        $user = User::find($client->user_id);
        $acces = Crypt::encryptString((string) $user->id);

        $r = $this->postJson('/mon_gravier/infos-utilisateur', ['access' => $acces, 'type' => 4])->assertOk();
        $this->assertSame('1111111A', $r->json('data.ncc_clt'));
        $this->assertSame('RNI', $r->json('data.regime_imposition'));
        $this->assertSame(\Help::$ENTREPRISE, $r->json('data.type_client'));

        $base = ['access' => $acces, 'type' => 4, 'nom_prenoms' => $user->nom_prenoms ?: 'SOCIETE', 'contact' => $user->contact ?: '0700000000',
            'pays_id' => $user->pays_id ?: 1, 'ville_id' => $user->ville_id ?: 1, 'adresse' => $user->adresse ?: 'Abidjan'];
        $this->postJson('/mon_gravier/edit-profil', $base + ['ncc' => '2222222B', 'rccm' => 'CI-ABJ-2', 'regime_imposition' => 'RSI', 'nature_fne' => 'B2G'])->assertOk()->assertJson(['code' => 200]);
        $fiche = Client::find($client->id);
        $this->assertSame('2222222B', $fiche->ncc_clt);
        $this->assertSame('B2G', $fiche->nature_fne, 'La nature de l\'organisation est enregistrée.');
        $this->assertSame('B2G', $this->postJson('/mon_gravier/infos-utilisateur', ['access' => $acces, 'type' => 4])->json('data.nature_fne'));
        $this->assertSame('CI-ABJ-2', $fiche->rccm_clt);
        $this->assertSame('RSI', $fiche->regime_imposition);

        $r2 = $this->postJson('/mon_gravier/edit-profil', $base + ['ncc' => '   '])->assertOk()->assertJson(['code' => 501]);
        $this->assertStringContainsString('NCC', $r2->json('message'));
        $this->assertSame('2222222B', Client::find($client->id)->ncc_clt, 'Le NCC ne s\'efface pas.');
    }
}
