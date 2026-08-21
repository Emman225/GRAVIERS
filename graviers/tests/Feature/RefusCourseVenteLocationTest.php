<?php

namespace Tests\Feature;

use App\Models\Commande;
use App\Models\DetailCommande;
use App\Models\Enlevement;
use App\Models\Livraison;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Le refus d'une course sur une VENTE.
 *
 * Le circuit des ventes ne passe pas par `detail_livraison` : il a donc ses
 * propres versions du même défaut.
 *
 *  - L'écran de traitement mesure ce qui reste à confier en sommant les
 *    ENLÈVEMENTS rattachés aux courses. Ceux d'une course refusée étaient
 *    comptés : la ligne s'annonçait entièrement traitée et plus rien n'était
 *    proposé à la réaffectation.
 *
 *  - La clôture de la commande, côté site, comptait les courses et exigeait
 *    qu'elles soient TOUTES livrées. Une course refusée entrait dans ce compte
 *    sans jamais pouvoir l'être : la commande restait EN TRAITEMENT même après
 *    que la marchandise eut été reconfiée et remise au client. L'application
 *    mobile, elle, raisonnait déjà en quantités — les deux se contredisaient.
 */
class RefusCourseVenteLocationTest extends TestCase
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

    /** Un livreur existant, et le compte qui va avec. */
    private function unLivreur(): \App\Models\Livreur
    {
        $livreur = \App\Models\Livreur::whereHas('user')->first();

        if (!$livreur) {
            $this->markTestSkipped('Aucun livreur rattaché à un compte.');
        }

        return $livreur;
    }

    /**
     * Valide une course comme le ferait le livreur depuis le site : par la
     * route reelle, avec le code que le client lui communique.
     */
    private function validerParLeLivreur(\App\Models\Livreur $livreur, Livraison $course): void
    {
        \Illuminate\Support\Facades\URL::forceRootUrl('');

        $this->actingAs($livreur->user)
            ->post('/validation-livraison', ['code' => $course->numero]);
    }

    /** Une commande d'une ligne de 10 t, sa course et son bon d'enlèvement. */
    private function uneVente(int $accepte, float $qte = 10): array
    {
        $modele = Enlevement::whereNotNull('fournisseur_id')->first();
        $produit = Produit::first();

        if (!$modele || !$produit) {
            $this->markTestSkipped('Jeu de données insuffisant.');
        }

        $client = \App\Models\Client::create([
            'user_id'     => $this->unAdmin()->id,
            'nom'         => 'Vente',
            'prenom'      => 'Recette',
            'email'       => 'vente-' . uniqid() . '@example.test',
            'contact1'    => '0766666666',
            'type_client' => 'PARTICULIER',
            'statut'      => \Help::$STATUT_ACTIF,
        ]);

        $commande = Commande::create([
            'numero'        => 'CV' . substr((string) uniqid(), -8),
            'client_id'     => $client->id,
            'etat_commande' => \Help::$COMMANDE_EN_TRAITEMENT,
            'statut'        => \Help::$STATUT_ACTIF,
        ]);

        $ligne = DetailCommande::create([
            'produit_id'     => $produit->id,
            'commande_id'    => $commande->id,
            'qte'            => $qte,
            'prix'           => 1000,
            'qte_livree'     => 0,
            'statut'         => \Help::$STATUT_ACTIF,
            'etat_livraison' => \Help::$LIVRAISON_EN_TRAITEMENT,
        ]);

        $course = Livraison::create([
            'numero'             => 'LV' . substr((string) uniqid(), -8),
            'client_id'          => $client->id,
            'livreur_id'         => $this->unLivreur()->id,
            'date_livraison'     => now()->toDateString(),
            'qte'                => $qte,
            'etat_livraison'     => \Help::$LIVRAISON_EN_ATTENTE,
            'detail_commande_id' => $ligne->id,
            'provenance'         => \Help::$COMMANDE,
            'accepte'            => $accepte,
            'statut'             => \Help::$STATUT_ACTIF,
        ]);

        Enlevement::create([
            'fournisseur_id' => $modele->fournisseur_id,
            'livraison_id'   => $course->id,
            'produit_id'     => $produit->id,
            'qte'            => $qte,
            'statut'         => \Help::$STATUT_ACTIF,
        ]);

        return [$commande, $ligne, $course, $produit];
    }

    // ------------------------------------------------ RÉAFFECTATION POSSIBLE

    public function test_une_course_refusee_ne_consomme_pas_la_ligne_de_commande(): void
    {
        [$commande, $ligne, $course, $produit] = $this->uneVente(Livraison::REFUSEE);

        // L'écran de traitement mesure ce qui a déjà été confié par les
        // enlèvements : ceux d'une course refusée n'ont rien enlevé.
        $this->assertSame(0.0,
            round((float) \Help::totatEnlevementUnProduit($commande->id, $produit->id), 2),
            'Les enlèvements d\'une course refusée bloquaient la réaffectation.');
    }

    public function test_une_course_acceptee_consomme_bien_la_ligne(): void
    {
        [$commande, $ligne, $course, $produit] = $this->uneVente(Livraison::ACCEPTEE);

        $this->assertSame(10.0,
            round((float) \Help::totatEnlevementUnProduit($commande->id, $produit->id), 2));
    }

    // -------------------------------------------------- CLÔTURE DE COMMANDE

    public function test_un_refus_n_empeche_plus_la_commande_de_se_clore(): void
    {
        [$commande, $ligne, $course, $produit] = $this->uneVente(Livraison::REFUSEE);

        // La marchandise a été reconfiée à un autre livreur, qui l'a remise.
        $livreur = $this->unLivreur();

        $remplacante = Livraison::create([
            'numero'             => 'LV' . substr((string) uniqid(), -8),
            'client_id'          => $commande->client_id,
            'livreur_id'         => $livreur->id,
            'date_livraison'     => now()->toDateString(),
            'qte'                => 10,
            'etat_livraison'     => \Help::$LIVRAISON_EN_ATTENTE,
            'detail_commande_id' => $ligne->id,
            'provenance'         => \Help::$COMMANDE,
            'accepte'            => Livraison::ACCEPTEE,
            'statut'             => \Help::$STATUT_ACTIF,
        ]);

        $this->validerParLeLivreur($livreur, $remplacante);

        // Le compte des courses incluait la refusée : la commande restait
        // EN TRAITEMENT quoi qu'il arrive.
        $this->assertSame(\Help::$COMMANDE_TERMINE, $commande->fresh()->etat_commande);
        $this->assertSame(10.0, round((float) $ligne->fresh()->qte_livree, 2));
    }

    public function test_une_ligne_incomplete_laisse_la_commande_ouverte(): void
    {
        [$commande, $ligne, $course, $produit] = $this->uneVente(Livraison::ACCEPTEE, 10);

        // Une seule course de 4 t sur 10 commandées.
        $course->update(['qte' => 4]);

        $this->validerParLeLivreur($this->unLivreur(), $course);

        // La quantité n'y est pas : rien ne doit se clore.
        $this->assertNotSame(\Help::$COMMANDE_TERMINE, $commande->fresh()->etat_commande);
    }

    // ------------------------------------------------------------ LOCATION

    public function test_une_location_refusee_peut_etre_reaffectee(): void
    {
        $location = \App\Models\Location::with('detailLocation')
            ->whereHas('detailLocation')->first();

        if (!$location) {
            $this->markTestSkipped('Aucune location avec detail.');
        }

        $detail = $location->detailLocation->first();

        // Toutes les courses de cette ligne ont été refusées.
        Livraison::where('provenance', \Help::$LOCATION)
            ->where('detail_commande_id', $detail->id)
            ->delete();

        Livraison::create([
            'numero'             => 'LL' . substr((string) uniqid(), -8),
            'client_id'          => $location->client_id,
            'date_livraison'     => now()->toDateString(),
            'qte'                => $detail->qte ?: 1,
            'etat_livraison'     => \Help::$LIVRAISON_EN_ATTENTE,
            'detail_commande_id' => $detail->id,
            'provenance'         => \Help::$LOCATION,
            'accepte'            => Livraison::REFUSEE,
            'statut'             => \Help::$STATUT_ACTIF,
        ]);

        $location = $location->fresh()->load('detailLocation');

        // La validation était verrouillée sur l'état EN ATTENTE, que
        // l'affectation quitte aussitôt : un refus rendait toute
        // réaffectation impossible.
        $this->assertTrue($location->attendUneReaffectation());
        $this->assertTrue($location->lignesALivrerDeNouveau()->contains('id', $detail->id));
    }

    public function test_une_location_normalement_confiee_ne_demande_rien(): void
    {
        $location = \App\Models\Location::with('detailLocation')
            ->whereHas('detailLocation')->first();

        if (!$location) {
            $this->markTestSkipped('Aucune location avec detail.');
        }

        $detail = $location->detailLocation->first();

        Livraison::where('provenance', \Help::$LOCATION)
            ->where('detail_commande_id', $detail->id)
            ->delete();

        Livraison::create([
            'numero'             => 'LL' . substr((string) uniqid(), -8),
            'client_id'          => $location->client_id,
            'date_livraison'     => now()->toDateString(),
            'qte'                => $detail->qte ?: 1,
            'etat_livraison'     => \Help::$LIVRAISON_EN_ATTENTE,
            'detail_commande_id' => $detail->id,
            'provenance'         => \Help::$LOCATION,
            'accepte'            => Livraison::ACCEPTEE,
            'statut'             => \Help::$STATUT_ACTIF,
        ]);

        $this->assertFalse($location->fresh()->load('detailLocation')->attendUneReaffectation());
    }
}
