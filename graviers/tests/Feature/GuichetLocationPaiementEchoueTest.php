<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DetailLocation;
use App\Models\LignePaiement;
use App\Models\Location;
use App\Models\ModePaiement;
use App\Models\Paiement;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * UNE AFFAIRE QUE LA PASSERELLE N'A JAMAIS ENCAISSÉE SE RÈGLE AU GUICHET.
 *
 * Constaté le 01/09/2026 : deux locations « EN ATTENTE / aucun paiement
 * effectué » figuraient dans la liste des locations en attente, mais le
 * sélecteur « Location à encaisser » du guichet était VIDE. Le caissier avait
 * un client devant lui, une location due, et rien à sélectionner.
 *
 * La règle était : « mode de paiement en ligne, donc encaissée par la
 * passerelle, donc pas au guichet ». Elle vaut pour un paiement abouti. Mais
 * une initiation ÉCHOUÉE — le site crée alors la location tout de même — ou un
 * client qui abandonne la page de paiement laissent une affaire due portant un
 * mode en ligne. Elle n'était encaissable NULLE PART : ni par la passerelle,
 * qui n'a rien pris, ni au guichet, qui l'ignorait.
 *
 * Les demandes de livraison portaient exactement la même règle, et donc le même
 * piège.
 */
class GuichetLocationPaiementEchoueTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        URL::forceRootUrl('');
    }

    private function unAdmin(): User
    {
        $admin = User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN])
            ->where('statut', \Help::$STATUT_ACTIF)->first();

        if (!$admin) {
            $this->markTestSkipped('Aucun administrateur actif.');
        }

        return $admin;
    }

    /** Une location due de 81 880 F, réglée par le mode donné. */
    private function uneLocationDue(?int $modeId, float $dejaEncaisse = 0): Location
    {
        $client = Client::whereNotNull('user_id')->first();
        $produit = Produit::where('statut', 1)->first();

        if (!$client || !$produit) {
            $this->markTestSkipped('Fixtures absentes (client ou produit).');
        }

        $location = Location::create([
            'numero'           => 'TEST-' . uniqid(),
            'client_id'        => $client->id,
            'mode_paiement_id' => $modeId,
            'montant_total'    => 81880,
            'etat_location'    => \Help::$LOCATION_EN_ATTENTE,
            'est_livrable'     => 1,
            'statut'           => 1,
        ]);

        DetailLocation::create([
            'produit_id'    => $produit->id,
            'location_id'   => $location->id,
            'qte'           => 1,
            'prix'          => 81880,
            'nombre_jour'   => 1,
            'debut'         => now()->toDateString(),
            'fin'           => now()->addDay()->toDateString(),
            'etat_location' => \Help::$LOCATION_EN_ATTENTE,
        ]);

        if ($dejaEncaisse > 0) {
            $paiement = Paiement::create([
                'code'            => uniqid(),
                // `libelle` n'a pas de valeur par défaut en base : l'omettre
                // fait échouer l'insertion, et l'essai avec elle.
                'libelle'         => 'Location ' . $location->numero,
                'client_id'       => $client->id,
                'montant_total'   => $dejaEncaisse,
                'montant_restant' => 0,
                'statut'          => \Help::$STATUT_ACTIF,
                'service'         => \Help::$LOCATION,
                'service_id'      => $location->id,
            ]);

            LignePaiement::create([
                'paiement_id'      => $paiement->id,
                'mode_paiement_id' => $modeId,
                'montant'          => $dejaEncaisse,
                'statut'           => \Help::$STATUT_ACTIF,
                'service'          => \Help::$LOCATION,
                'service_id'       => $location->id,
                'date_paiement'    => now(),
            ]);
        }

        return $location->fresh()->load('detailLocation', 'modeDePaiement');
    }

    private function unModeEnLigne(): ModePaiement
    {
        $mode = ModePaiement::where('en_ligne', 1)->first();

        if (!$mode) {
            $this->markTestSkipped('Aucun mode de paiement en ligne.');
        }

        return $mode;
    }

    /** LE CAS SIGNALÉ : PAIEMENT EN LIGNE JAMAIS ABOUTI. */
    public function test_une_location_en_ligne_jamais_payee_se_regle_au_guichet(): void
    {
        $location = $this->uneLocationDue($this->unModeEnLigne()->id);

        $this->assertTrue($location->encaissableAuGuichet(),
            'Une location dont le paiement en ligne n’a jamais abouti est '
            . 'écartée du guichet : elle n’est encaissable NULLE PART.');

        $this->assertGreaterThan(0, $location->montantEncaissable(),
            'Elle doit rester encaissable : rien n’a été perçu.');
    }

    /** ELLE APPARAÎT DANS LE SÉLECTEUR DU GUICHET. */
    public function test_elle_apparait_dans_le_selecteur_du_guichet(): void
    {
        $location = $this->uneLocationDue($this->unModeEnLigne()->id);

        $html = $this->actingAs($this->unAdmin())->get('/encaissements/locations')->getContent();

        $this->assertStringContainsString($location->numero, $html,
            'La location n’est pas proposée au caissier : le sélecteur « Location '
            . 'à encaisser » reste vide devant un client venu payer.');
    }

    /** MAIS UNE LOCATION RÉELLEMENT PAYÉE EN LIGNE RESTE HORS DU GUICHET. */
    public function test_une_location_payee_en_ligne_reste_hors_du_guichet(): void
    {
        $location = $this->uneLocationDue($this->unModeEnLigne()->id, 81880);

        $this->assertFalse($location->encaissableAuGuichet(),
            'La passerelle a encaissé : la proposer au guichet ouvrirait la porte '
            . 'à un second encaissement.');

        $this->assertSame(0.0, $location->montantEncaissable(),
            'Rien ne reste à encaisser sur une location soldée.');
    }

    /** UN MODE HORS LIGNE RESTE ÉVIDEMMENT AU GUICHET. */
    public function test_un_mode_hors_ligne_reste_au_guichet(): void
    {
        $mode = ModePaiement::where('en_ligne', 0)->first();

        if (!$mode) {
            $this->markTestSkipped('Aucun mode hors ligne.');
        }

        $this->assertTrue($this->uneLocationDue($mode->id)->encaissableAuGuichet(),
            'Un règlement en agence doit rester encaissable au guichet.');
    }

    /** SANS MODE — LE MOBILE N'EN ENREGISTRE PAS POUR UN PAIEMENT EN AGENCE. */
    public function test_une_location_sans_mode_reste_au_guichet(): void
    {
        $this->assertTrue($this->uneLocationDue(null)->encaissableAuGuichet(),
            'L’application mobile enregistre NULL pour un paiement en agence : '
            . 'écarter ces locations les rendrait inencaissables.');
    }

    /**
     * LE GARDE-FOU AVANT TRAITEMENT N'A PAS BOUGÉ.
     *
     * `reglementEnAgence()` sert AUSSI à interdire le départ d'un camion sur
     * une affaire réglée en agence et non payée. Confondre les deux questions
     * bloquait des traitements que rien n'avait à bloquer — quatorze essais
     * l'ont dit.
     */
    public function test_le_garde_fou_avant_traitement_est_inchange(): void
    {
        $location = $this->uneLocationDue($this->unModeEnLigne()->id);

        $this->assertFalse($location->reglementEnAgence(),
            'Le mode reste un mode EN LIGNE : la question du guichet est autre, '
            . 'et elle a sa propre méthode.');

        $this->assertTrue($location->encaissableAuGuichet(),
            'Elle doit malgré tout pouvoir être encaissée au guichet.');
    }

    /** LES DEMANDES DE LIVRAISON PORTAIENT LE MÊME PIÈGE. */
    public function test_les_demandes_de_livraison_suivent_la_meme_regle(): void
    {
        $source = file_get_contents(app_path('Models/DemandeLivraison.php'));

        $this->assertStringContainsString('function encaissableAuGuichet', $source,
            'Les demandes de livraison écartent encore du guichet celles dont le '
            . 'paiement en ligne n’a jamais abouti : le même défaut y subsiste.');
    }
}
