<?php

namespace Tests\Feature;

use App\Http\Middleware\MemoriserPagePrecedente;
use App\Models\Livreur;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * « RETOUR » RAMÈNE À UNE PAGE, JAMAIS À UN FICHIER NI À UNE ACTION.
 *
 * Signalé le 07/09/2026 : sur « Détail livreur », le bouton Retour ouvrait la
 * pièce d'identité du livreur. Le bouton pointe sur la « page précédente »
 * tenue en session par MemoriserPagePrecedente ; or l'adresse de l'image
 * (/livreur/{id}/piece/recto/inline), ouverte dans un autre onglet, était
 * mémorisée comme une page. Même chose pour toute route GET qui redirige :
 * revenir dessus aurait rejoué l'action.
 */
class PagePrecedenteIgnoreLesFichiersTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        $u = User::whereIn('type_user_id', [1, 2])->where('statut', 1)->orderBy('id')->first();
        if (!$u) {
            $this->markTestSkipped('Aucun administrateur en base.');
        }
        return $u;
    }

    public function test_une_image_ouverte_dans_un_autre_onglet_ne_devient_pas_la_page_precedente(): void
    {
        $livreur = Livreur::first();
        if (!$livreur) {
            $this->markTestSkipped('Aucun livreur en base.');
        }

        Auth::guard('web')->login($this->admin());

        $liste  = route('show.list');
        $profil = route('show.profile', $livreur->id);

        $this->get($liste)->assertOk();
        $this->get($profil)->assertOk();

        // TÉMOIN : après deux pages, la précédente est bien la liste.
        $this->assertSame($liste, session(MemoriserPagePrecedente::CLE_PRECEDENTE));
        $this->assertSame($profil, session(MemoriserPagePrecedente::CLE_COURANTE));

        // L'image de la pièce, ouverte dans un autre onglet : quel que soit
        // son résultat (fichier ou 404 en local), la navigation ne bouge pas.
        $this->get(route('show.livreurPiece', ['livreur' => $livreur->id, 'type' => 'recto', 'mode' => 'inline']));

        $this->assertSame($liste, session(MemoriserPagePrecedente::CLE_PRECEDENTE),
            "L'image de la pièce a pris la place de la page précédente : « Retour » l'ouvrirait.");
        $this->assertSame($profil, session(MemoriserPagePrecedente::CLE_COURANTE));
    }

    public function test_une_redirection_n_est_pas_memorisee_comme_une_page(): void
    {
        Auth::guard('web')->login($this->admin());

        $liste = route('show.list');
        $this->get($liste)->assertOk();

        // /audit redirige vers l'onglet du paramétrage : une route « de passage ».
        $reponse = $this->get('/audit');
        $this->assertTrue($reponse->isRedirection(), '/audit devrait rediriger.');

        $this->assertSame($liste, session(MemoriserPagePrecedente::CLE_COURANTE),
            'Une redirection a été retenue comme page courante.');
    }
}
