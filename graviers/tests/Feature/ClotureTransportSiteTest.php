<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DemandeLivraison;
use App\Models\DetailLivraison;
use App\Models\Livraison;
use App\Models\Livreur;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * CLÔTURER UN ARTICLE DE TRANSPORT DEPUIS LE SITE.
 *
 * Deux défauts se cumulaient, et ensemble ils donnent le symptôme signalé :
 * « j'ai traité un article, ce n'est pas marqué au back-office, et le véhicule
 * n'est pas libéré ».
 *
 *   · LA LIGNE N'ÉTAIT PAS MARQUÉE. À la clôture, le site met à jour la ligne
 *     d'une COMMANDE (qte_livree, etat_livraison) mais laisse celle d'une
 *     DEMANDE DE LIVRAISON intacte : elle restait « EN TRAITEMENT » pour
 *     toujours. L'application mobile, elle, la met à jour — le résultat
 *     dépendait donc du canal par lequel le livreur clôturait.
 *
 *   · LE VÉHICULE RESTAIT RÉSERVÉ. Il n'est libéré que si plus aucune course
 *     ne lui est rattachée, et le compte incluait les courses REFUSÉES : une
 *     seule course refusée dans son histoire, et le camion ne pouvait plus
 *     jamais redevenir disponible.
 */
class ClotureTransportSiteTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * UN CAMION NEUF, rien que pour ce test.
     *
     * Réutiliser un véhicule de la base le ferait juger sur SES courses en
     * cours — un camion déjà engagé ailleurs ne doit pas être libéré, et le test
     * conclurait à un défaut là où le refus est correct. La libération se mesure
     * sur un camion dont on connaît TOUTES les courses.
     */
    private function unLivreurAvecCamion(): array
    {
        $modele = Vehicule::whereNotNull('livreur_id')->whereNotNull('type_vehicule_id')->first();

        if (!$modele || !$modele->livreur || !$modele->livreur->user) {
            $this->markTestSkipped('Aucun véhicule rattaché à un livreur ayant un compte.');
        }

        $camion = Vehicule::create([
            'immatriculation'  => 'TEST-' . substr(uniqid(), -6),
            'nom'              => 'Camion de test',
            'description'      => '',
            'type_vehicule_id' => $modele->type_vehicule_id,
            'livreur_id'       => $modele->livreur_id,
            'statut'           => \Help::$STATUT_ACTIF,
            'disponible'       => 1,
            'capacite'         => 30,
            'marque'           => 'Test',
            'modele'           => 'Test',
        ]);

        return [$modele->livreur, $camion];
    }

    private function uneCourse(Livreur $livreur, Vehicule $camion, float $qte = 1): array
    {
        $client = Client::first();

        if (!$client) {
            $this->markTestSkipped('Aucun client en base.');
        }

        $demande = DemandeLivraison::create([
            'numero'        => 'DL-' . substr(uniqid(), -8),
            'client_id'     => $client->id,
            'montantTotal'  => 10000,
            'etat_commande' => \Help::$COMMANDE_EN_TRAITEMENT,
            'statut'        => \Help::$STATUT_ACTIF,
        ]);

        $detail = DetailLivraison::create([
            'nom_produit'          => 'Sable',
            'qte'                  => $qte,
            'unite'                => 'Tonne',
            'unite_produit_id'     => \App\Models\UniteProduit::value('id'),
            'description'          => '',
            'demande_livraison_id' => $demande->id,
            'etat_livraison'       => \Help::$LIVRAISON_EN_TRAITEMENT,
            'statut'               => \Help::$STATUT_ACTIF,
        ]);

        $course = Livraison::create([
            'numero'              => 'C-' . substr(uniqid(), -8),
            'client_id'           => $client->id,
            'livreur_id'          => $livreur->id,
            'vehicule_id'         => $camion->id,
            'detail_livraison_id' => $detail->id,
            'provenance'          => \Help::$LIVRAISON,
            'date_livraison'      => date('Y-m-d'),
            'qte'                 => $qte,
            'accepte'             => 2,
            'etat_livraison'      => \Help::$LIVRAISON_EN_TRAITEMENT,
            'statut'              => \Help::$STATUT_ACTIF,
            'cout_livraison'      => 0,
        ]);

        $camion->update(['disponible' => 0]);

        return [$demande, $detail, $course];
    }

    private function cloturer(Livreur $livreur, Livraison $course)
    {
        return $this->actingAs($livreur->user)
            ->post(route('livreur.validationLivraison'), ['code' => $course->numero]);
    }

    public function test_la_ligne_de_transport_est_marquee_livree(): void
    {
        [$livreur, $camion] = $this->unLivreurAvecCamion();
        [$demande, $detail, $course] = $this->uneCourse($livreur, $camion);

        $this->cloturer($livreur, $course);

        $this->assertEquals(\Help::$LIVRAISON_LIVREE, $course->fresh()->etat_livraison,
            'La course doit être marquée livrée.');

        $this->assertEquals(\Help::$LIVRAISON_LIVREE, $detail->fresh()->etat_livraison,
            "L'article transporté doit être marqué livré au back-office : "
            . "clôturé depuis le site, il restait « EN TRAITEMENT » pour toujours.");
    }

    public function test_le_vehicule_est_libere_apres_la_course(): void
    {
        [$livreur, $camion] = $this->unLivreurAvecCamion();
        [$demande, $detail, $course] = $this->uneCourse($livreur, $camion);

        $this->cloturer($livreur, $course);

        $this->assertEquals(1, (int) $camion->fresh()->disponible,
            'Le véhicule doit redevenir disponible une fois sa course terminée.');
    }

    public function test_une_course_refusee_ne_bloque_pas_le_vehicule(): void
    {
        [$livreur, $camion] = $this->unLivreurAvecCamion();

        // Une course refusée dans l'histoire du camion — le cas signalé : la
        // demande avait été refusée puis réaffectée au même livreur.
        [$demandeA, $detailA, $refusee] = $this->uneCourse($livreur, $camion);
        $refusee->update(['accepte' => 3]);

        // Puis la course réellement effectuée.
        [$demandeB, $detailB, $course] = $this->uneCourse($livreur, $camion);

        $this->cloturer($livreur, $course);

        $this->assertEquals(1, (int) $camion->fresh()->disponible,
            "Une course REFUSÉE n'a rien transporté : elle ne doit pas retenir "
            . "le camion, sans quoi il ne redevient jamais disponible.");
    }

    public function test_le_depannage_libere_un_camion_bloque_par_un_refus(): void
    {
        // La commande vehicule:liberer est l'outil de reparation des camions
        // deja bloques en production. Elle comptait elle aussi les courses
        // refusees : elle etait donc inoperante precisement sur les camions
        // qu'elle devait debloquer.
        [$livreur, $camion] = $this->unLivreurAvecCamion();

        [$demande, $detail, $refusee] = $this->uneCourse($livreur, $camion);
        $refusee->update(['accepte' => 3]);

        $camion->update(['disponible' => 0]);

        $this->artisan('vehicule:liberer --apply')->assertSuccessful();

        $this->assertEquals(1, (int) $camion->fresh()->disponible,
            'Un camion dont la seule course est refusee doit etre libere.');
    }

    public function test_le_depannage_ne_touche_pas_a_un_camion_reellement_occupe(): void
    {
        [$livreur, $camion] = $this->unLivreurAvecCamion();

        // Course acceptee, non livree : le camion roule.
        [$demande, $detail, $course] = $this->uneCourse($livreur, $camion);

        $this->artisan('vehicule:liberer --apply')->assertSuccessful();

        $this->assertEquals(0, (int) $camion->fresh()->disponible,
            "Un camion en course ne doit pas etre rendu disponible : on le "
            . "proposerait pour une autre livraison alors qu'il roule.");
    }

    public function test_le_badge_de_refus_s_eteint_apres_la_reaffectation(): void
    {
        // LE CAS SIGNALE, chiffres reels : 25 sacs de riz, camion de 20.
        //
        // Le livreur refuse, le gestionnaire reaffecte, la course est livree.
        // Il reste 5 sacs a confier — ce qui n'a rien a voir avec le refus, deja
        // traite. Le badge « Refus livreur - a reaffecter » restait pourtant
        // allume, reclamant un travail deja fait.
        [$livreur, $camion] = $this->unLivreurAvecCamion();
        [$demande, $detail, $refusee] = $this->uneCourse($livreur, $camion, 20);

        // La ligne demande 25, le camion en a pris 20.
        $detail->update(['qte' => 25]);
        $refusee->update(['accepte' => 3]);

        $this->assertTrue($detail->fresh()->attendUneReaffectation(),
            'Tant que rien n a ete reconfie, le refus doit etre signale.');

        // Reaffectation au meme camion, puis livraison.
        $reconfiee = Livraison::create([
            'numero'              => 'C-' . substr(uniqid(), -8),
            'client_id'           => $refusee->client_id,
            'livreur_id'          => $livreur->id,
            'vehicule_id'         => $camion->id,
            'detail_livraison_id' => $detail->id,
            'provenance'          => \Help::$LIVRAISON,
            'date_livraison'      => date('Y-m-d'),
            'qte'                 => 20,
            'accepte'             => 2,
            'etat_livraison'      => \Help::$LIVRAISON_EN_TRAITEMENT,
            'statut'              => \Help::$STATUT_ACTIF,
            'cout_livraison'      => 0,
        ]);

        $this->cloturer($livreur, $reconfiee);

        $detail = $detail->fresh();

        // Il reste bien 5 sacs a confier : l ecran a raison de l afficher.
        $this->assertEquals(5, $detail->qteRestanteAAffecter());

        // Mais le refus, lui, a ete traite : le badge doit s eteindre.
        $this->assertFalse($detail->attendUneReaffectation(),
            'Un refus deja reconfie ne doit plus etre signale, meme s il reste '
            . 'de la quantite a confier : ce sont deux choses differentes.');
    }

    public function test_le_depannage_nomme_la_course_qui_retient_le_camion(): void
    {
        // « Occupe » sans dire PAR QUOI laisse le gestionnaire sans recours : il
        // ne peut ni cloturer la course qui bloque, ni juger si elle est
        // legitime. La commande doit la nommer.
        [$livreur, $camion] = $this->unLivreurAvecCamion();
        [$demande, $detail, $course] = $this->uneCourse($livreur, $camion);

        $this->artisan('vehicule:liberer')
            ->expectsOutputToContain($camion->immatriculation)
            ->expectsOutputToContain($course->numero)
            ->assertSuccessful();

        // Le camion n'a pas ete libere : il roule.
        $this->assertEquals(0, (int) $camion->fresh()->disponible);
    }
}
