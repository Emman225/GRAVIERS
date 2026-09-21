<?php

namespace Tests\Feature;

use App\Models\Livreur;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * PROFIL LIVREUR — la fenêtre « Historique du prix de livraison » (08/09/2026).
 *
 * Cause établie sur la page servie : la carte du thème et la section n'étaient
 * jamais refermées, la fenêtre Bootstrap écrite ensuite devenait un descendant
 * de la carte. Le thème transforme toute carte survolée (.card:hover
 * { transform: translateY(-2px) }) ; un ancêtre transformé devient le repère
 * des positions fixes, et la fenêtre — fond gris compris — restait confinée
 * dans la carte (overflow: hidden), « dansait » avec le curseur et ne se
 * peignait normalement qu'une fois le curseur hors de la page.
 *
 * L'invariant : dans la page rendue, toutes les <div> ouvertes entre le début
 * de la section et la fenêtre sont refermées, et la section l'est aussi.
 */
class FenetreHistoriquePrixHorsCarteTest extends TestCase
{
    use DatabaseTransactions;

    public function test_la_fenetre_historique_n_est_dans_aucune_carte(): void
    {
        $admin = User::where('type_user_id', 2)->where('statut', 1)->first();
        $livreur = Livreur::orderBy('id')->first();
        if (!$admin || !$livreur) {
            $this->markTestSkipped('Aucun administrateur ou aucun livreur.');
        }
        Auth::guard('web')->login($admin);
        $html = $this->get('/livreur/' . $livreur->id . '/profile')->assertOk()->getContent();

        // Le gabarit ouvre lui-même deux sections « content-main » autour du
        // contenu : la section de la page est la dernière ouverte avant la fenêtre.
        $fenetre = strpos($html, '<div class="modal" id="modalHistoriquePrix"');
        $debut = $fenetre === false ? false : strrpos(substr($html, 0, $fenetre), '<section class="content-main">');
        $this->assertNotFalse($debut, 'La section de contenu est absente.');
        $this->assertNotFalse($fenetre, 'La fenêtre « Historique » est absente.');
        $this->assertGreaterThan($debut, $fenetre);

        $entre = substr($html, $debut, $fenetre - $debut);
        $ouvertes = preg_match_all('/<div\b/i', $entre);
        $fermees = preg_match_all('/<\/div\s*>/i', $entre);
        $this->assertSame($ouvertes, $fermees,
            "Avant la fenêtre, {$ouvertes} <div> ouvertes pour {$fermees} refermées : la fenêtre serait un descendant d'une carte.");
        $this->assertStringContainsString('</section>', $entre, 'La section de contenu n\'est pas refermée avant la fenêtre.');

        // Et la fenêtre elle-même est refermée avant la fin de la section du gabarit.
        $profondeur = 0;
        $fin = null;
        preg_match_all('/<div\b|<\/div\s*>/i', $html, $balises, PREG_OFFSET_CAPTURE, $fenetre);
        foreach ($balises[0] as [$balise, $position]) {
            $profondeur += stripos($balise, '</') === 0 ? -1 : 1;
            if ($profondeur === 0) {
                $fin = $position;
                break;
            }
        }
        $this->assertNotNull($fin, "La fenêtre « Historique » n'est jamais refermée.");
        $this->assertLessThan(strpos($html, '</section>', $fenetre), $fin,
            'La fenêtre « Historique » se referme après la section du gabarit.');
    }
}
