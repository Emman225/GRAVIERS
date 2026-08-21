<?php

namespace Tests\Feature;

use Help;
use App\Models\Livreur;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Le refus d'une course, côté application livreur.
 *
 * Il se contentait d'écrire `livraison.accepte = 3`. Deux réservations faites
 * au moment de l'affectation n'étaient jamais rendues :
 *
 *   - le CAMION, mis indisponible au traitement, le restait pour toujours — il
 *     sortait du parc sans avoir rien transporté ;
 *   - la LIGNE de la demande restait « EN TRAITEMENT », alors qu'aucune course
 *     active ne la couvrait plus.
 *
 * Le back-office ne pouvait donc ni réaffecter la course, ni réutiliser le
 * camion, et la demande restait « en attente » indéfiniment.
 */
class RefusCourseTest extends TestCase
{
    use DatabaseTransactions;

    private function unLivreurConnecte(): array
    {
        $user = User::create([
            'nom_prenoms'  => 'Livreur Refus',
            'email'        => 'refus_' . uniqid() . '@example.com',
            'contact'      => '0700000009',
            'login'        => 'refus_' . uniqid(),
            'password'     => Help::HashPassword('password123'),
            'type_user_id' => Help::$USER_LIVREUR,
            'statut'       => Help::$STATUT_ACTIF,
        ]);

        $livreurId = DB::table('livreur')->insertGetId([
            'user_id'            => $user->id,
            'num_piece_identite' => 'CI' . substr((string) uniqid(), -8),
            'statut'             => Help::$STATUT_ACTIF,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        return [$user, Livreur::find($livreurId)];
    }

    /** Une course affectée : camion réservé, ligne en traitement. */
    private function uneCourseAffectee(Livreur $livreur): array
    {
        $client = DB::table('client')->first();
        $unite  = DB::table('unite_produit')->first();

        if (!$client || !$unite) {
            $this->markTestSkipped('Jeu de données insuffisant (client ou unité).');
        }

        $typeVehicule = DB::table('type_vehicule')->first();

        if (!$typeVehicule) {
            $this->markTestSkipped('Aucun type de véhicule.');
        }

        $vehiculeId = DB::table('vehicule')->insertGetId([
            'immatriculation'  => 'RF-' . substr((string) uniqid(), -6),
            'nom'              => 'Camion de recette',
            'type_vehicule_id' => $typeVehicule->id,
            'livreur_id'       => $livreur->id,
            'capacite'         => 20,
            'marque'           => 'Recette',
            'modele'           => 'Test',
            'disponible'       => 0,   // réservé par l'affectation
            'statut'           => Help::$STATUT_ACTIF,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $demandeId = DB::table('demande_livraison')->insertGetId([
            'numero'        => 'DR' . substr((string) uniqid(), -8),
            'client_id'     => $client->id,
            'etat_commande' => Help::$COMMANDE_EN_TRAITEMENT,
            'statut'        => Help::$STATUT_ACTIF,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $detailId = DB::table('detail_livraison')->insertGetId([
            'nom_produit'          => 'Gravier de recette',
            'qte'                  => 10,
            'unite'                => 'T',
            'unite_produit_id'     => $unite->id,
            'description'          => 'Ligne de recette.',
            'demande_livraison_id' => $demandeId,
            'etat_livraison'       => Help::$LIVRAISON_EN_TRAITEMENT,
            'statut'               => Help::$STATUT_ACTIF,
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        $livraisonId = DB::table('livraison')->insertGetId([
            'numero'              => 'LR' . substr((string) uniqid(), -8),
            'client_id'           => $client->id,
            'livreur_id'          => $livreur->id,
            'vehicule_id'         => $vehiculeId,
            'detail_livraison_id' => $detailId,
            'date_livraison'      => now()->toDateString(),
            'qte'                 => 10,
            'etat_livraison'      => Help::$LIVRAISON_EN_TRAITEMENT,
            'accepte'             => 1,
            'statut'              => Help::$STATUT_ACTIF,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        return [$vehiculeId, $detailId, $livraisonId];
    }

    private function refuser(User $user, int $livraisonId): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/mon_gravier_livreur/refuser-livraison', [
            'access'      => Crypt::encryptString((string) $user->id),
            'type'        => (string) $user->type_user_id,
            'idLivraison' => $livraisonId,
        ]);
    }

    public function test_le_refus_libere_le_camion(): void
    {
        [$user, $livreur] = $this->unLivreurConnecte();
        [$vehiculeId, $detailId, $livraisonId] = $this->uneCourseAffectee($livreur);

        $this->assertSame(0, (int) DB::table('vehicule')->where('id', $vehiculeId)->value('disponible'));

        $this->refuser($user, $livraisonId)->assertOk();

        // Il ne transportera rien : il doit revenir au parc.
        $this->assertSame(1, (int) DB::table('vehicule')->where('id', $vehiculeId)->value('disponible'),
            'Le camion restait indisponible après un refus.');
    }

    public function test_le_refus_remet_la_ligne_en_attente(): void
    {
        [$user, $livreur] = $this->unLivreurConnecte();
        [$vehiculeId, $detailId, $livraisonId] = $this->uneCourseAffectee($livreur);

        $this->refuser($user, $livraisonId)->assertOk();

        $this->assertSame(
            Help::$LIVRAISON_EN_ATTENTE,
            DB::table('detail_livraison')->where('id', $detailId)->value('etat_livraison'),
            'La ligne restait en traitement alors que plus aucune course ne la couvrait.'
        );

        $this->assertSame(3, (int) DB::table('livraison')->where('id', $livraisonId)->value('accepte'));
    }

    public function test_une_ligne_encore_couverte_reste_en_traitement(): void
    {
        [$user, $livreur] = $this->unLivreurConnecte();
        [$vehiculeId, $detailId, $livraisonId] = $this->uneCourseAffectee($livreur);

        // Une seconde course couvre déjà toute la ligne.
        DB::table('livraison')->insert([
            'numero'              => 'LR' . substr((string) uniqid(), -8),
            'client_id'           => DB::table('livraison')->where('id', $livraisonId)->value('client_id'),
            'livreur_id'          => $livreur->id,
            'detail_livraison_id' => $detailId,
            'date_livraison'      => now()->toDateString(),
            'qte'                 => 10,
            'etat_livraison'      => Help::$LIVRAISON_EN_TRAITEMENT,
            'accepte'             => 1,
            'statut'              => Help::$STATUT_ACTIF,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        $this->refuser($user, $livraisonId)->assertOk();

        $this->assertSame(
            Help::$LIVRAISON_EN_TRAITEMENT,
            DB::table('detail_livraison')->where('id', $detailId)->value('etat_livraison'),
            'La ligne est encore entièrement confiée : rien ne doit repasser en attente.'
        );
    }

    public function test_un_autre_livreur_ne_peut_pas_refuser(): void
    {
        [$user, $livreur] = $this->unLivreurConnecte();
        [$vehiculeId, $detailId, $livraisonId] = $this->uneCourseAffectee($livreur);

        [$intrus] = $this->unLivreurConnecte();

        $this->refuser($intrus, $livraisonId)->assertOk()->assertJsonPath('code', 404);

        // Rien n'a bougé.
        $this->assertSame(0, (int) DB::table('vehicule')->where('id', $vehiculeId)->value('disponible'));
        $this->assertSame(1, (int) DB::table('livraison')->where('id', $livraisonId)->value('accepte'));
    }
}
