<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DemandeLivraison;
use App\Models\DetailLivraison;
use App\Models\Livraison;
use App\Models\ModePaiement;
use App\Models\User;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * LA QUANTITÉ CONFIÉE À CHAQUE CAMION SE DÉCIDE.
 *
 * Elle était imposée : toujours la capacité du véhicule. Une ligne de 25 sacs
 * et un camion de 20 demandaient donc DEUX affectations, deux codes de
 * validation et deux clôtures — pour un seul travail. Et le calcul de rotations,
 * pourtant écrit, ne servait à rien : une quantité plafonnée à la capacité fait
 * toujours exactement un voyage.
 *
 * Ce que la quantité saisie change, selon l'unité :
 *   · en TONNES, elle compte les voyages, et la rémunération du livreur suit ;
 *   · dans les autres unités — sacs, barres, unités — elle ne se divise pas par
 *     une capacité en tonnes : un seul voyage, et c'est la GRILLE du livreur,
 *     indexée sur l'unité et la quantité, qui fait le tarif.
 *
 * Une VENTE, elle, laisse depuis toujours le gestionnaire saisir la quantité et
 * en déduit le nombre de voyages (cf. OrdersController). Le même camion pouvait
 * donc emporter 25 sacs pour une vente et pas pour un transport.
 *
 * Le champ est pré-rempli avec l'ancienne valeur : ne rien toucher donne
 * exactement le comportement d'avant.
 */
class QuantiteParCamionTest extends TestCase
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

    private function unCamion(float $capacite): Vehicule
    {
        $modele = Vehicule::whereNotNull('livreur_id')->whereNotNull('type_vehicule_id')->first();

        if (!$modele) {
            $this->markTestSkipped('Aucun véhicule rattaché à un livreur.');
        }

        return Vehicule::create([
            'immatriculation'  => 'TEST-' . substr(uniqid(), -6),
            'nom'              => 'Camion de test',
            'description'      => '',
            'type_vehicule_id' => $modele->type_vehicule_id,
            'livreur_id'       => $modele->livreur_id,
            'statut'           => \Help::$STATUT_ACTIF,
            'disponible'       => 1,
            'capacite'         => $capacite,
            'marque'           => 'Test',
            'modele'           => 'Test',
        ]);
    }

    /** Une demande de 25 sacs, comme celle signalée. */
    private function uneDemande(float $qte = 25): array
    {
        $client = Client::whereNotNull('user_id')->first();
        $adresse = \App\Models\AdresseLivraison::first();

        if (!$client || !$adresse) {
            $this->markTestSkipped('Client ou adresse manquant.');
        }

        $demande = DemandeLivraison::create([
            'numero'                    => 'DL-' . substr(uniqid(), -8),
            'client_id'                 => $client->id,
            'montantTotal'              => 20000,
            'remise'                    => 0,
            'etat_commande'             => \Help::$COMMANDE_EN_ATTENTE,
            'statut'                    => \Help::$STATUT_ACTIF,
            'adresse_livraison_pec_id'  => $adresse->id,
            'adresse_livraison_dest_id' => $adresse->id,
            'date_livraison'            => date('Y-m-d'),
            'mode_paiement_id'          => ModePaiement::where('en_ligne', 1)->value('id'),
        ]);

        $detail = DetailLivraison::create([
            'nom_produit'          => 'Riz',
            'qte'                  => $qte,
            'unite'                => 'Sac',
            'unite_produit_id'     => \App\Models\UniteProduit::value('id'),
            'description'          => '',
            'demande_livraison_id' => $demande->id,
            'etat_livraison'       => \Help::$LIVRAISON_EN_ATTENTE,
            'statut'               => \Help::$STATUT_ACTIF,
        ]);

        return [$demande, $detail];
    }

    private function affecter(DemandeLivraison $demande, DetailLivraison $detail, array $donnees)
    {
        return $this->actingAs($this->unGestionnaire())->post(
            route('show.traitementLivraison', [
                'demandeLivraison' => $demande->id,
                'detail'           => $detail->id,
            ]),
            $donnees + ['date' => date('Y-m-d')]
        );
    }

    public function test_sans_quantite_saisie_le_comportement_ne_change_pas(): void
    {
        // Le cas de non-régression : un formulaire qui n'envoie pas de quantité
        // — une version d'écran restée en cache, par exemple — doit produire
        // exactement l'affectation d'avant.
        [$demande, $detail] = $this->uneDemande(25);
        $camion = $this->unCamion(20);

        $this->affecter($demande, $detail, ['id' => [$camion->id]]);

        $course = Livraison::where('detail_livraison_id', $detail->id)->latest('id')->first();

        $this->assertNotNull($course);
        $this->assertEquals(20, (float) $course->qte, 'Sans saisie, le camion emporte sa capacité.');
        $this->assertEquals(5, $detail->fresh()->qteRestanteAAffecter());
    }

    public function test_le_gestionnaire_peut_confier_plus_que_la_capacite(): void
    {
        // LE CAS SIGNALÉ : 25 sacs, camion de 20, un seul travail.
        [$demande, $detail] = $this->uneDemande(25);
        $camion = $this->unCamion(20);

        $this->affecter($demande, $detail, ['id' => [$camion->id], 'qte' => [25]]);

        $course = Livraison::where('detail_livraison_id', $detail->id)->latest('id')->first();

        $this->assertEquals(25, (float) $course->qte,
            'Le camion doit pouvoir se voir confier les 25 sacs en une affectation.');

        $this->assertEquals(0, $detail->fresh()->qteRestanteAAffecter(),
            'La ligne est alors entièrement confiée : plus rien à affecter.');

        $this->assertTrue($detail->fresh()->estEntierementAffectee());
    }

    public function test_les_voyages_ne_se_comptent_que_sur_ce_qui_se_divise(): void
    {
        // CE QUE LE GESTIONNAIRE DOIT SAVOIR EN SAISISSANT 25 POUR UN CAMION DE 20.
        //
        // La capacite d'un camion est en TONNES. Une quantite en TONNES au-dela
        // de la capacite compte donc plusieurs voyages, et la remuneration du
        // livreur est multipliee d'autant.
        $tonne = \App\Models\UniteProduit::where('libelle', 'Tonne')->value('id');
        $sac   = \App\Models\UniteProduit::where('libelle', 'Sac')->value('id');

        if (!$tonne || !$sac) {
            $this->markTestSkipped('Unites Tonne et Sac absentes.');
        }

        $this->assertEquals(2, \App\Models\Livreur::nombreDeVoyages(25, 20, 0, $tonne),
            '25 tonnes dans un camion de 20 font deux voyages.');

        // Mais des SACS ne se divisent pas par des tonnes : ce calcul-la n'a
        // aucun sens, et il a deja coute cher — une commande de 11 400 barres
        // devenait 285 voyages, et 32 tranches tarifaires partaient a perte.
        //
        // Pour ces unites, on compte UN voyage, et c'est la GRILLE du livreur —
        // indexee sur l'unite et la quantite — qui fait le tarif : 25 sacs n'y
        // tombent pas dans la meme tranche que 20.
        $this->assertEquals(1, \App\Models\Livreur::nombreDeVoyages(25, 20, 0, $sac),
            'Des sacs ne se divisent pas par une capacite en tonnes.');
    }

    public function test_on_ne_peut_pas_confier_plus_que_ce_qui_reste(): void
    {
        // Une saisie au-delà du restant sur-affecterait la ligne : elle ne se
        // clôturerait jamais, la quantité livrée ne pouvant pas dépasser la
        // quantité demandée.
        [$demande, $detail] = $this->uneDemande(25);
        $camion = $this->unCamion(20);

        $this->affecter($demande, $detail, ['id' => [$camion->id], 'qte' => [999]]);

        $course = Livraison::where('detail_livraison_id', $detail->id)->latest('id')->first();

        $this->assertEquals(25, (float) $course->qte,
            'La quantité est ramenée à ce qui reste à confier.');
    }

    public function test_une_quantite_nulle_ne_cree_aucune_course(): void
    {
        [$demande, $detail] = $this->uneDemande(25);
        $camion = $this->unCamion(20);

        $this->affecter($demande, $detail, ['id' => [$camion->id], 'qte' => [0]]);

        $this->assertEquals(0, Livraison::where('detail_livraison_id', $detail->id)->count(),
            "Confier zéro n'a pas de sens : aucune course ne doit être créée.");
    }

    public function test_l_ecran_propose_le_champ_quantite(): void
    {
        [$demande, $detail] = $this->uneDemande(25);

        $ecran = $this->actingAs($this->unGestionnaire())
            ->get(route('show.traitelivraisonPage', $demande->id));

        $ecran->assertOk();
        $ecran->assertSee('Quantité');
        $ecran->assertSee('data-demande="25"', false);
    }

    public function test_l_ecran_montre_ce_qui_est_livre_et_pas_seulement_le_reste(): void
    {
        // LE DEFAUT SIGNALE : 20 sacs sur 25 livres, et le back-office
        // ressemblait a un article dont on n'avait rien fait. Il n'affichait
        // qu'un chiffre — le reste a confier — qui ne dit rien de ce que le
        // client a recu.
        [$demande, $detail] = $this->uneDemande(25);
        $camion = $this->unCamion(20);

        $this->affecter($demande, $detail, ['id' => [$camion->id], 'qte' => [20]]);

        $course = Livraison::where('detail_livraison_id', $detail->id)->latest('id')->first();
        $course->update(['etat_livraison' => \Help::$LIVRAISON_LIVREE]);

        $detail = $detail->fresh();

        $this->assertEquals(20, $detail->qteLivree());
        $this->assertFalse($detail->estEntierementLivree(), 'Il reste 5 sacs a livrer.');

        $ecran = $this->actingAs($this->unGestionnaire())
            ->get(route('show.traitelivraisonPage', $demande->id));

        $ecran->assertOk();
        $ecran->assertSee('Livré');
        $ecran->assertSee('Livraison partielle');
    }

    public function test_une_ligne_entierement_livree_est_annoncee_comme_telle(): void
    {
        [$demande, $detail] = $this->uneDemande(25);
        $camion = $this->unCamion(20);

        $this->affecter($demande, $detail, ['id' => [$camion->id], 'qte' => [25]]);

        $course = Livraison::where('detail_livraison_id', $detail->id)->latest('id')->first();
        $course->update(['etat_livraison' => \Help::$LIVRAISON_LIVREE]);

        $detail = $detail->fresh();

        $this->assertTrue($detail->estEntierementLivree());

        $this->actingAs($this->unGestionnaire())
            ->get(route('show.traitelivraisonPage', $demande->id))
            ->assertOk()
            ->assertSee('Livré au client');
    }

    public function test_une_course_affectee_mais_pas_roulee_ne_compte_pas_comme_livree(): void
    {
        // Un camion charge n'a pas encore livre : confondre les deux ferait
        // croire le client servi alors que le camion n'a pas bouge.
        [$demande, $detail] = $this->uneDemande(25);
        $camion = $this->unCamion(20);

        $this->affecter($demande, $detail, ['id' => [$camion->id], 'qte' => [20]]);

        $detail = $detail->fresh();

        $this->assertEquals(20, $detail->qteAffectee(), 'La quantite est bien confiee.');
        $this->assertEquals(0, $detail->qteLivree(), "Mais rien n'est encore livre.");
    }

    public function test_l_ecran_explique_qu_aucun_camion_n_est_libre(): void
    {
        // Le champ s'affichait VIDE, sans un mot : impossible de savoir si
        // c'etait une panne, un droit manquant, ou simplement des camions
        // occupes. Le gestionnaire restait devant un formulaire muet.
        [$demande, $detail] = $this->uneDemande(25);

        Vehicule::query()->update(['disponible' => 0]);

        $this->actingAs($this->unGestionnaire())
            ->get(route('show.traitelivraisonPage', $demande->id))
            ->assertOk()
            ->assertSee('Aucun véhicule disponible')
            ->assertSee('clôturée par le livreur');
    }

    public function test_un_camion_libre_est_bien_propose(): void
    {
        [$demande, $detail] = $this->uneDemande(25);

        Vehicule::query()->update(['disponible' => 0]);
        $camion = $this->unCamion(20);

        $this->actingAs($this->unGestionnaire())
            ->get(route('show.traitelivraisonPage', $demande->id))
            ->assertOk()
            ->assertSee($camion->marque)
            ->assertDontSee('Aucun véhicule disponible');
    }
}
