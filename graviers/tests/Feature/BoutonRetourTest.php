<?php

namespace Tests\Feature;

use App\Http\Middleware\MemoriserPagePrecedente;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Le bouton « Retour » doit ramener EXACTEMENT à la page précédente.
 *
 * Il rejouait l'historique du navigateur : history.back(), et history.go(-2)
 * quand le referrer était égal à l'URL courante. Ce second cas se déclenchait
 * aussi sur un simple rechargement, et le retour sautait une page de trop.
 * La page précédente est désormais tenue côté serveur.
 */
class BoutonRetourTest extends TestCase
{
    use DatabaseTransactions;

    private function precedente(): ?string
    {
        return session(MemoriserPagePrecedente::CLE_PRECEDENTE);
    }

    public function test_la_page_precedente_suit_la_navigation(): void
    {
        $this->get('/');
        $this->get('/a-propos');

        $this->assertStringContainsString('/', (string) $this->precedente());
        $this->assertStringNotContainsString('a-propos', (string) $this->precedente(),
            'La page précédente ne doit pas être la page courante.');
    }

    public function test_un_rechargement_ne_decale_pas_la_page_precedente(): void
    {
        $this->get('/');
        $this->get('/a-propos');

        $avant = $this->precedente();

        // Rechargement de la même page : c'est le cas qui faisait sauter une
        // page de trop, le referrer étant alors identique à l'URL courante.
        $this->get('/a-propos');
        $this->get('/a-propos');

        $this->assertSame($avant, $this->precedente(),
            'Recharger la page ne doit pas modifier la page de retour.');
    }

    public function test_les_telechargements_ne_deviennent_pas_une_page_de_retour(): void
    {
        $this->get('/');
        $this->get('/a-propos');

        $avant = $this->precedente();

        // Un PDF n'est pas une destination de retour.
        $this->get('/recu/999999/pdf');

        $this->assertSame($avant, $this->precedente(),
            'Un téléchargement ne doit pas devenir la page de retour.');
    }

    /**
     * Le reçu est servi pour les TROIS caisses. Son bouton « Retour » pointait
     * en dur sur celle des ventes : depuis le reçu d'une location, on
     * atterrissait sur un écran sans rapport.
     */
    public function test_le_recu_renvoie_vers_la_caisse_dou_lon_vient(): void
    {
        $admin = \App\Models\User::whereIn('type_user_id', [1, 2])->first();
        $this->assertNotNull($admin, 'Aucun administrateur en base pour ce test.');

        $this->actingAs($admin);

        $this->get('/encaissements/locations');

        $paiement = \App\Models\Paiement::first();
        $this->assertNotNull($paiement, 'Aucun paiement en base pour ce test.');

        $reponse = $this->get('/recu/' . $paiement->id);
        $reponse->assertOk();

        // On vise le bouton de l'en-tête du reçu, et non n'importe quelle
        // occurrence de l'adresse : la barre latérale contient elle aussi un
        // lien vers la caisse des ventes, ce qui rendrait toute assertion
        // globale inexploitable.
        $boutonRetour = 'href="' . route('show.encaissements.locations') . '" class="btn btn-light"';

        $reponse->assertSee($boutonRetour, false);
        $reponse->assertDontSee(
            'href="' . route('show.comptant.encaissements') . '" class="btn btn-light"',
            false
        );
    }

    public function test_la_toute_premiere_page_na_pas_de_precedente(): void
    {
        session()->flush();

        $this->get('/');

        $this->assertNull($this->precedente(),
            "À la première page, il n'y a pas de page précédente : le bouton retombe sur l'historique.");
    }
}
