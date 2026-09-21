<?php

namespace Tests\Feature;

use App\Models\Commande;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Le filtre par dates des commandes traitées.
 *
 * L'écran montrait tout l'historique, sans moyen de s'y repérer. Deux principes
 * tiennent le comportement :
 *
 *   - PAS DE BORNE PAR DÉFAUT. Restreindre l'affichage sans qu'on l'ait demandé
 *     ferait croire à des commandes disparues.
 *   - UNE SEULE BORNE SUFFIT. « depuis le 1er août » est une question légitime,
 *     et exiger les deux dates la rendrait impossible à poser.
 */
class CommandesTraiteesFiltreDatesTest extends TestCase
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

    private function ouvrir(string $requete = ''): \Illuminate\Testing\TestResponse
    {
        URL::forceRootUrl('');

        return $this->actingAs($this->unAdmin())->get('/commande-traitees' . $requete);
    }

    /** Les dates des commandes affichees. */
    private function jours(\Illuminate\Testing\TestResponse $reponse): array
    {
        return collect($reponse->viewData('commandes'))
            ->map(fn ($c) => \Carbon\Carbon::parse($c->date_commande ?? $c->created_at)->format('Y-m-d'))
            ->values()->all();
    }

    public function test_sans_filtre_tout_l_historique_reste_affiche(): void
    {
        $reponse = $this->ouvrir();

        $reponse->assertOk();

        $attendu = count(Commande::liste(null, [\Help::$COMMANDE_TERMINE]));

        $this->assertSame($attendu, count($reponse->viewData('commandes')),
            'Sans borne, rien ne doit disparaitre.');

        $this->assertNull($reponse->viewData('du'));
        $this->assertNull($reponse->viewData('au'));
    }

    public function test_le_filtre_ne_garde_que_la_periode(): void
    {
        $toutes = $this->jours($this->ouvrir());

        if (empty($toutes)) {
            $this->markTestSkipped('Aucune commande traitee en base.');
        }

        sort($toutes);
        $milieu = $toutes[intdiv(count($toutes), 2)];

        $filtrees = $this->jours($this->ouvrir('?du=' . $milieu . '&au=' . $milieu));

        foreach ($filtrees as $jour) {
            $this->assertSame($milieu, $jour, 'Une commande hors periode ne doit pas figurer.');
        }
    }

    public function test_une_seule_borne_suffit(): void
    {
        $toutes = $this->jours($this->ouvrir());

        if (empty($toutes)) {
            $this->markTestSkipped('Aucune commande traitee en base.');
        }

        sort($toutes);
        $depuis = $toutes[intdiv(count($toutes), 2)];

        $reponse = $this->ouvrir('?du=' . $depuis);
        $reponse->assertOk();

        foreach ($this->jours($reponse) as $jour) {
            $this->assertGreaterThanOrEqual($depuis, $jour);
        }

        // Et la borne haute seule fonctionne aussi.
        foreach ($this->jours($this->ouvrir('?au=' . $depuis)) as $jour) {
            $this->assertLessThanOrEqual($depuis, $jour);
        }
    }

    public function test_une_periode_vide_ne_casse_pas_l_ecran(): void
    {
        // Le tableau vide fait tomber DataTables si l ecran ne s en garde pas.
        $reponse = $this->ouvrir('?du=1990-01-01&au=1990-01-02');

        $reponse->assertOk();
        $this->assertSame([], $this->jours($reponse));
    }

    public function test_le_lien_tout_l_historique_apparait_seulement_filtre(): void
    {
        $this->ouvrir()->assertDontSee("Tout l'historique</a>", false);
        $this->ouvrir('?du=2020-01-01')->assertSee("Tout l'historique</a>", false);
    }
}
