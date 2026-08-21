<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DemandeLivraison;
use App\Models\DetailLivraison;
use App\Models\Livraison;
use App\Models\UniteProduit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Une course refusée par le livreur bloquait la demande pour toujours.
 *
 * Le refus se contente d'écrire `livraison.accepte = 3`. Or la quantité déjà
 * affectée à une ligne se calculait en sommant TOUTES ses courses, refus
 * compris. Une seule ligne refusée suffisait donc à faire croire la ligne
 * entièrement servie :
 *
 *   - l'écran de traitement l'annonçait « Déjà traité » ;
 *   - le gestionnaire ne pouvait plus la confier à un autre livreur ;
 *   - la quantité livrée n'atteignant jamais celle demandée, la demande
 *     n'était jamais marquée TERMINEE et restait « en attente » indéfiniment.
 *
 * Rien, nulle part, ne signalait le refus.
 */
class RefusCourseLivraisonTest extends TestCase
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

    /** Une demande d'une seule ligne de 10 tonnes, prête à être affectée. */
    private function uneDemande(float $qte = 10): array
    {
        $unite = UniteProduit::first();

        if (!$unite) {
            $this->markTestSkipped('Aucune unité de produit.');
        }

        $client = Client::create([
            'user_id'     => $this->unAdmin()->id,
            'nom'         => 'Transport',
            'prenom'      => 'Recette',
            'email'       => 'transport-' . uniqid() . '@example.test',
            'contact1'    => '0744444444',
            'type_client' => 'PARTICULIER',
            'statut'      => \Help::$STATUT_ACTIF,
        ]);

        $demande = DemandeLivraison::create([
            'numero'        => 'DL' . substr((string) uniqid(), -8),
            'client_id'     => $client->id,
            'etat_commande' => \Help::$COMMANDE_EN_TRAITEMENT,
            'statut'        => \Help::$STATUT_ACTIF,
        ]);

        $detail = DetailLivraison::create([
            'nom_produit'          => 'Sable de recette',
            'qte'                  => $qte,
            'unite'                => $unite->libelle ?? 'T',
            'unite_produit_id'     => $unite->id,
            'description'          => 'Ligne créée pour la recette.',
            'demande_livraison_id' => $demande->id,
            'etat_livraison'       => \Help::$LIVRAISON_EN_TRAITEMENT,
            'statut'               => \Help::$STATUT_ACTIF,
        ]);

        return [$client, $demande, $detail];
    }

    private function uneCourse(Client $client, DetailLivraison $detail, float $qte, int $accepte): Livraison
    {
        return Livraison::create([
            'numero'              => 'LV' . substr((string) uniqid(), -8),
            'client_id'           => $client->id,
            'date_livraison'      => now()->toDateString(),
            'qte'                 => $qte,
            'etat_livraison'      => \Help::$LIVRAISON_EN_TRAITEMENT,
            'detail_livraison_id' => $detail->id,
            'accepte'             => $accepte,
            'statut'              => \Help::$STATUT_ACTIF,
        ]);
    }

    // ------------------------------------------------------- LE CALCUL

    public function test_une_course_refusee_ne_consomme_pas_la_quantite(): void
    {
        [$client, $demande, $detail] = $this->uneDemande(10);

        $this->uneCourse($client, $detail, 10, Livraison::REFUSEE);

        $detail = $detail->fresh()->load('livraisons');

        // Elle n'a rien transporté : elle ne consomme rien.
        $this->assertSame(0.0, round($detail->qteAffectee(), 2));
        $this->assertSame(10.0, round($detail->qteRestanteAAffecter(), 2));
        $this->assertFalse($detail->estEntierementAffectee(),
            'La ligne s\'annonçait entièrement affectée alors que rien n\'avait été transporté.');
    }

    public function test_une_course_acceptee_consomme_bien_la_quantite(): void
    {
        [$client, $demande, $detail] = $this->uneDemande(10);

        $this->uneCourse($client, $detail, 4, Livraison::ACCEPTEE);

        $detail = $detail->fresh()->load('livraisons');

        $this->assertSame(4.0, round($detail->qteAffectee(), 2));
        $this->assertSame(6.0, round($detail->qteRestanteAAffecter(), 2));
    }

    public function test_le_reste_a_affecter_ignore_le_seul_refus(): void
    {
        [$client, $demande, $detail] = $this->uneDemande(10);

        $this->uneCourse($client, $detail, 6, Livraison::ACCEPTEE);
        $this->uneCourse($client, $detail, 4, Livraison::REFUSEE);

        $detail = $detail->fresh()->load('livraisons');

        // 6 confiées, 4 refusées : il reste bien 4 à reconfier, et non 0.
        $this->assertSame(4.0, round($detail->qteRestanteAAffecter(), 2));
        $this->assertSame(4.0, round(\Help::qteDetaillivraisonRestante($detail), 2));
    }

    // ------------------------------------------------------ LE SIGNAL

    public function test_la_demande_signale_qu_une_reaffectation_est_attendue(): void
    {
        [$client, $demande, $detail] = $this->uneDemande(10);

        $this->uneCourse($client, $detail, 10, Livraison::REFUSEE);

        $this->assertTrue($demande->fresh()->attendUneReaffectation());
    }

    public function test_une_demande_entierement_confiee_ne_signale_rien(): void
    {
        [$client, $demande, $detail] = $this->uneDemande(10);

        $this->uneCourse($client, $detail, 10, Livraison::ACCEPTEE);

        $this->assertFalse($demande->fresh()->attendUneReaffectation());
    }

    public function test_un_refus_deja_reconfie_ne_signale_plus_rien(): void
    {
        [$client, $demande, $detail] = $this->uneDemande(10);

        $this->uneCourse($client, $detail, 10, Livraison::REFUSEE);
        // Le gestionnaire l'a reconfiée à un autre livreur.
        $this->uneCourse($client, $detail, 10, Livraison::ACCEPTEE);

        $this->assertFalse($demande->fresh()->attendUneReaffectation());
    }

    // ------------------------------------------------------- LES ÉCRANS

    public function test_l_ecran_de_traitement_propose_de_reaffecter(): void
    {
        [$client, $demande, $detail] = $this->uneDemande(10);

        $this->uneCourse($client, $detail, 10, Livraison::REFUSEE);

        URL::forceRootUrl('');
        $reponse = $this->actingAs($this->unAdmin())
            ->get('/traite-livraison-page-' . $demande->id);

        $reponse->assertOk();

        // Il annonçait « Déjà traité » et ne proposait plus rien.
        $reponse->assertDontSee('Déjà traité', false);
        $reponse->assertSee('Sable de recette', false);
    }

    public function test_la_liste_signale_le_refus(): void
    {
        [$client, $demande, $detail] = $this->uneDemande(10);

        $this->uneCourse($client, $detail, 10, Livraison::REFUSEE);

        URL::forceRootUrl('');
        $reponse = $this->actingAs($this->unAdmin())->get('/liste-demande-de-livraison');

        $reponse->assertOk();
        $reponse->assertSee('Refus livreur', false);
    }
}
