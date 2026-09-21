<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DemandeLivraison;
use App\Models\DetailLivraison;
use App\Models\Livraison;
use App\Models\User;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * LE CODE DE VALIDATION APRÈS UN REFUS.
 *
 * Scénario signalé : le livreur refuse une course de transport, le gestionnaire
 * la lui réaffecte, il accepte — et le client ne reçoit plus le courriel portant
 * son code de validation. Sans ce code, il ne peut pas clore la course : le
 * livreur reste bloqué au moment de la valider.
 *
 * Le courriel part à l'AFFECTATION, pas à l'acceptation. Une réaffectation doit
 * donc en produire un nouveau — le code est le numéro de la NOUVELLE course, et
 * l'ancien, attaché à une course refusée, ne vaut plus rien.
 */
class ReaffectationApresRefusTest extends TestCase
{
    use DatabaseTransactions;

    private function unGestionnaire(): User
    {
        $user = User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN, \Help::$USER_GESTIONNAIRE])
            ->where('statut', \Help::$STATUT_ACTIF)->first();

        if (!$user) {
            $this->markTestSkipped('Aucun gestionnaire actif.');
        }

        return $user;
    }

    private function unCamion(): Vehicule
    {
        $camion = Vehicule::whereNotNull('livreur_id')->where('capacite', '>', 0)->first();

        if (!$camion) {
            $this->markTestSkipped('Aucun véhicule rattaché à un livreur.');
        }

        $camion->update(['disponible' => 1]);

        return $camion->fresh();
    }

    private function uneDemande(): array
    {
        $client = Client::whereNotNull('user_id')->whereNotNull('email')->first()
            ?? Client::whereNotNull('user_id')->first();

        if (!$client) {
            $this->markTestSkipped('Aucun client rattaché à un compte.');
        }

        $adresse = \App\Models\AdresseLivraison::where('client_id', $client->id)->first()
            ?? \App\Models\AdresseLivraison::first();

        if (!$adresse) {
            $this->markTestSkipped('Aucune adresse de livraison en base.');
        }

        $demande = DemandeLivraison::create([
            'numero'                  => 'DL-' . substr(uniqid(), -8),
            'client_id'               => $client->id,
            'montantTotal'            => 20000,
            'remise'                  => 0,
            'etat_commande'           => \Help::$COMMANDE_EN_ATTENTE,
            'statut'                  => \Help::$STATUT_ACTIF,
            'adresse_livraison_pec_id'  => $adresse->id,
            'adresse_livraison_dest_id' => $adresse->id,
            'date_livraison'          => date('Y-m-d'),
            // Mode EN LIGNE : le blocage « réglée en agence, reste à encaisser »
            // n'a rien à voir avec ce qu'on teste ici, et masquerait le sujet.
            'mode_paiement_id'        => \App\Models\ModePaiement::where('en_ligne', 1)->value('id'),
        ]);

        $unite = \App\Models\UniteProduit::first();

        $detail = DetailLivraison::create([
            'nom_produit'          => 'Sable de test',
            'qte'                  => 1,
            'unite'                => $unite?->libelle ?? 'Tonne',
            'unite_produit_id'     => $unite?->id,
            'description'          => '',
            'demande_livraison_id' => $demande->id,
            'etat_livraison'       => \Help::$LIVRAISON_EN_ATTENTE,
            'statut'               => \Help::$STATUT_ACTIF,
        ]);

        return [$demande, $detail];
    }

    private function affecter(DemandeLivraison $demande, DetailLivraison $detail, Vehicule $camion)
    {
        return $this->actingAs($this->unGestionnaire())->post(
            route('show.traitementLivraison', [
                'demandeLivraison' => $demande->id,
                'detail'           => $detail->id,
            ]),
            ['id' => [$camion->id], 'date' => date('Y-m-d')]
        );
    }

    /** Le refus, exactement comme l'application livreur l'écrit. */
    private function refuser(Livraison $livraison, Vehicule $camion): void
    {
        $livraison->update(['accepte' => 3]);

        DB::table('vehicule')->where('id', $camion->id)->update(['disponible' => 1]);

        DB::table('detail_livraison')
            ->where('id', $livraison->detail_livraison_id)
            ->update(['etat_livraison' => \Help::$LIVRAISON_EN_ATTENTE]);
    }

    public function test_la_premiere_affectation_envoie_le_code(): void
    {
        Mail::fake();

        [$demande, $detail] = $this->uneDemande();
        $camion = $this->unCamion();

        $this->affecter($demande, $detail, $camion);

        Mail::assertSent(\App\Mail\receptionCodeDemandeLivraison::class);
    }

    public function test_la_reaffectation_apres_refus_renvoie_un_code(): void
    {
        Mail::fake();

        [$demande, $detail] = $this->uneDemande();
        $camion = $this->unCamion();

        // 1. Affectation.
        $this->affecter($demande, $detail, $camion);

        $premiere = Livraison::where('detail_livraison_id', $detail->id)->latest('id')->first();
        $this->assertNotNull($premiere, "La première affectation doit créer une course.");

        // 2. Le livreur refuse.
        $this->refuser($premiere, $camion);

        // 3. Le gestionnaire réaffecte au MÊME livreur.
        $this->affecter($demande->fresh(), $detail->fresh(), $camion->fresh());

        $courses = Livraison::where('detail_livraison_id', $detail->id)->get();

        $this->assertCount(2, $courses,
            "La réaffectation doit créer une NOUVELLE course, pas réutiliser la refusée.");

        // Le client doit avoir reçu DEUX codes : celui de la course refusée ne
        // vaut plus rien, seul le nouveau permet de clore la livraison.
        Mail::assertSent(\App\Mail\receptionCodeDemandeLivraison::class, 2);
    }

    public function test_le_code_est_renvoyable_depuis_l_ecran_sans_y_etre_lisible(): void
    {
        Mail::fake();

        [$demande, $detail] = $this->uneDemande();
        $camion = $this->unCamion();

        $this->affecter($demande, $detail, $camion);

        $course = Livraison::where('detail_livraison_id', $detail->id)->latest('id')->first();

        // Règle du 10/09/2026 : le code ne s'affiche plus au back-office — le
        // client le lit sur Mon compte et dans l'application. Un courriel perdu
        // se rattrape par « Renvoyer le code », sans que le gestionnaire le voie.
        $ecran = $this->actingAs($this->unGestionnaire())
            ->get(route('show.traitelivraisonPage', $demande->id));

        $ecran->assertOk();
        $ecran->assertDontSee($course->numero);
        $ecran->assertSee('Renvoyer le code');
    }

    public function test_le_renvoi_envoie_a_nouveau_le_code(): void
    {
        Mail::fake();

        [$demande, $detail] = $this->uneDemande();
        $camion = $this->unCamion();

        $this->affecter($demande, $detail, $camion);

        $course = Livraison::where('detail_livraison_id', $detail->id)->latest('id')->first();

        $this->actingAs($this->unGestionnaire())
            ->post(route('show.renvoyerCodeDemandeLivraison', $course->id));

        // Un à l'affectation, un au renvoi.
        Mail::assertSent(\App\Mail\receptionCodeDemandeLivraison::class, 2);
    }

    public function test_le_code_d_une_course_refusee_ne_se_renvoie_pas(): void
    {
        Mail::fake();

        [$demande, $detail] = $this->uneDemande();
        $camion = $this->unCamion();

        $this->affecter($demande, $detail, $camion);

        $course = Livraison::where('detail_livraison_id', $detail->id)->latest('id')->first();
        $this->refuser($course, $camion);

        $this->actingAs($this->unGestionnaire())
            ->post(route('show.renvoyerCodeDemandeLivraison', $course->id))
            ->assertSessionHas('code_non_envoye');

        // Le code d'une course refusée est caduc : l'envoyer entretiendrait la
        // confusion. Seul le courriel de l'affectation est parti.
        Mail::assertSent(\App\Mail\receptionCodeDemandeLivraison::class, 1);
    }
}
