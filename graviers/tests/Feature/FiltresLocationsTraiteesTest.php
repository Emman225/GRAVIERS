<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Location;
use App\Models\ModePaiement;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * LES FILTRES DE LA LISTE DES LOCATIONS TRAITÉES.
 *
 * Demandés le 04/09/2026 : « il faut mettre un filtre par État et autres ».
 *
 * Chaque critère ne s'applique que s'il est rempli : l'écran a toujours montré
 * toutes les locations traitées, et en restreindre l'affichage sans qu'on
 * l'ait demandé ferait croire à des locations disparues.
 */
class FiltresLocationsTraiteesTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN])
            ->where('statut', 1)->firstOrFail();
    }

    private function creerLocation(string $etat, ?string $dateRetour = null): Location
    {
        return Location::create([
            'numero' => \Help::genererNumeroUnique('location'),
            'client_id' => Client::whereNotNull('user_id')->firstOrFail()->id,
            'mode_paiement_id' => ModePaiement::first()?->id,
            'montant_total' => 50000,
            'etat_location' => $etat,
            'est_livrable' => 1,
            'statut' => 1,
            'caution' => 0,
            'date_retour' => $dateRetour,
        ]);
    }

    /** @return array<int,string> les numéros affichés par la liste */
    private function numerosAffiches(array $filtres): array
    {
        $reponse = $this->actingAs($this->admin())
            ->get('/locations-traitees?' . http_build_query($filtres));

        $reponse->assertOk();

        return collect($reponse->original->getData()['locations'])
            ->pluck('numero')->all();
    }

    public function test_le_filtre_par_etat_ne_garde_que_cet_etat(): void
    {
        $enCours = $this->creerLocation(\Help::$LOCATION_EN_COURS);
        $terminee = $this->creerLocation(\Help::$LOCATION_TERMINE);

        $sansFiltre = $this->numerosAffiches([]);
        $this->assertContains($enCours->numero, $sansFiltre);
        $this->assertContains($terminee->numero, $sansFiltre);

        $filtrees = $this->numerosAffiches(['etat' => 'EN COURS']);

        $this->assertContains($enCours->numero, $filtrees);
        $this->assertNotContains($terminee->numero, $filtrees,
            'Une location terminée ressort du filtre « En cours ».');
    }

    public function test_le_filtre_par_paiement_lit_l_argent(): void
    {
        // Sans aucun encaissement : « Aucun ».
        $impayee = $this->creerLocation(\Help::$LOCATION_EN_COURS);

        $this->assertContains($impayee->numero,
            $this->numerosAffiches(['paiement' => 'AUCUN']));

        $this->assertNotContains($impayee->numero,
            $this->numerosAffiches(['paiement' => 'SOLDE']),
            'Une location sans le moindre encaissement ressort comme soldée.');
    }

    public function test_le_filtre_par_client_accepte_un_nom_partiel(): void
    {
        $location = $this->creerLocation(\Help::$LOCATION_EN_COURS);
        $client = $location->client;

        $nom = (string) ($client->display_name ?? $client->nom_prenoms ?? $client->nom ?? '');

        if (mb_strlen($nom) < 3) {
            $this->markTestSkipped('Le client de test n\'a pas de nom exploitable.');
        }

        $morceau = mb_substr($nom, 0, 3);

        $this->assertContains($location->numero,
            $this->numerosAffiches(['client' => $morceau]),
            'Un nom partiel doit suffire : on ne retape pas un nom entier.');

        $this->assertNotContains($location->numero,
            $this->numerosAffiches(['client' => 'zzzznexistepas']));
    }

    public function test_le_filtre_par_periode_porte_sur_la_date_de_retour(): void
    {
        $ancienne = $this->creerLocation(\Help::$LOCATION_TERMINE, '2020-01-15');
        $recente  = $this->creerLocation(\Help::$LOCATION_TERMINE, '2030-06-20');

        $filtrees = $this->numerosAffiches(['du' => '2030-01-01']);

        $this->assertContains($recente->numero, $filtrees);
        $this->assertNotContains($ancienne->numero, $filtrees,
            'Une location rendue en 2020 ressort d\'une recherche à partir de 2030.');
    }

    /**
     * SANS CRITÈRE, RIEN N'EST CACHÉ.
     *
     * C'est le risque de tout filtre : masquer par défaut, et faire croire à
     * des données disparues.
     */
    public function test_sans_critere_la_liste_montre_tout(): void
    {
        $avant = count($this->numerosAffiches([]));

        $this->creerLocation(\Help::$LOCATION_EN_COURS);
        $this->creerLocation(\Help::$LOCATION_TERMINE, '2029-03-03');

        $this->assertSame($avant + 2, count($this->numerosAffiches([])),
            'La liste sans filtre doit montrer toutes les locations traitées.');
    }
}
