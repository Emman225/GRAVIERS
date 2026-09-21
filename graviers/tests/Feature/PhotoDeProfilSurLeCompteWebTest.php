<?php

namespace Tests\Feature;

use App\Models\Client;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** La photo de profil du client sur son compte web, partagée avec le mobile (lot 105, 17/09/2026). */
class PhotoDeProfilSurLeCompteWebTest extends TestCase
{
    use DatabaseTransactions;

    public function test_le_client_change_sa_photo_et_la_voit_sur_mon_compte(): void
    {
        $client = Client::whereNotNull('user_id')->get()->first(fn ($c) => $c->user && $c->user->ville_id);
        if (!$client) {
            $this->markTestSkipped('Aucun client avec compte et ville.');
        }
        $user = $client->user;
        Auth::guard('web')->login($user);
        $chemin = 'imageUser/' . $user->id . '.png';
        $disque = Storage::disk('public');
        $existait = $disque->exists($chemin) ? $disque->get($chemin) : null;

        try {
            $reponse = $this->post('/modification', [
                'contact1' => '0700000000', 'contact2' => '0700000001', 'ville' => $user->ville_id,
                'adresse' => $user->adresse ?: 'Abidjan',
                'nom' => $client->nom ?: 'Client', 'prenom' => $client->prenom ?: '', 'raisonSociale' => $client->nom ?: 'Client',
                'rccm' => $client->rccm_clt ?: 'x', 'ncc' => $client->ncc_clt ?: 'x',
                'photo' => UploadedFile::fake()->image('moi.jpg', 200, 200),
            ]);
            $reponse->assertRedirect();
            $this->assertTrue($disque->exists($chemin), 'La photo est écrite au chemin du mobile.');
            $this->assertSame($chemin, $user->fresh()->photo);

            $adresse = \Help::photoDeProfil($user->fresh());
            $this->assertStringContainsString('storage/' . $chemin, (string) $adresse);
            $html = $this->get('/mon-compte')->assertOk()->getContent();
            $this->assertStringContainsString('storage/' . $chemin, $html, 'Mon compte affiche la photo.');
            $this->assertStringContainsString('name="photo"', $html, 'Mes informations permet de la changer.');

            // Une pièce qui n'est pas une image est refusée.
            $this->post('/modification', [
                'contact1' => '0700000000', 'contact2' => '0700000001', 'ville' => $user->ville_id, 'adresse' => 'Abidjan',
                'photo' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
            ])->assertSessionHasErrors('photo');
        } finally {
            if ($existait !== null) {
                $disque->put($chemin, $existait);
            } else {
                $disque->delete($chemin);
            }
        }
    }

    /** Lot 106 : la photo écrite par l'API dans storage/app/public (hors du dossier servi) est retrouvée, rapatriée, et remplace l'icône de l'en-tête. */
    public function test_la_photo_deposee_hors_du_dossier_servi_est_retrouvee_et_montree_dans_l_entete(): void
    {
        $client = Client::whereNotNull('user_id')->get()->first(fn ($c) => $c->user && $c->user->ville_id);
        if (!$client) {
            $this->markTestSkipped('Aucun client avec compte et ville.');
        }
        $user = $client->user;
        Auth::guard('web')->login($user);
        $chemin = 'imageUser/' . $user->id . '.png';
        $disque = Storage::disk('public');
        $existait = $disque->exists($chemin) ? $disque->get($chemin) : null;
        $ancienneRacine = storage_path('app/public/' . $chemin);
        $ancienExistait = is_file($ancienneRacine);
        $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');

        try {
            $disque->delete($chemin);
            if (!is_dir(dirname($ancienneRacine))) {
                mkdir(dirname($ancienneRacine), 0777, true);
            }
            file_put_contents($ancienneRacine, $pixel);
            $user->photo = $chemin;
            $user->save();

            $adresse = \Help::photoDeProfil($user->fresh());
            $this->assertStringContainsString('storage/' . $chemin, (string) $adresse, "La photo écrite par l'API est retrouvée.");
            $this->assertTrue($disque->exists($chemin), 'Elle est rapatriée dans le dossier servi.');

            $html = $this->get('/mon-compte')->assertOk()->getContent();
            $this->assertStringContainsString('photo-profil-entete', $html, "L'en-tête montre la photo à la place de l'icône « Mon compte ».");
            $this->assertStringContainsString('storage/' . $chemin, $html);
        } finally {
            if ($existait !== null) {
                $disque->put($chemin, $existait);
            } else {
                $disque->delete($chemin);
            }
            if (!$ancienExistait) {
                @unlink($ancienneRacine);
            }
        }
    }
}
