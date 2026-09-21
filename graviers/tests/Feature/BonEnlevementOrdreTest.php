<?php

namespace Tests\Feature;

use App\Models\Commande;
use App\Models\Livraison;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Le bon d'enlèvement se lit du plus ancien au plus récent.
 *
 * `Livraison::liste()` trie du plus récent au plus ancien — l'ordre qui convient
 * à une liste de travail, où l'on traite d'abord ce qui vient d'arriver. Mais la
 * page d'une commande raconte son HISTOIRE : le premier enlèvement doit venir
 * en premier, sinon on lit la chronologie à l'envers.
 *
 * Le tri est fait dans le contrôleur, pas dans le modèle : `liste()` sert aussi
 * les écrans de suivi des livreurs, où l'ordre inverse reste le bon. Ce test
 * garde donc les deux comportements.
 */
class BonEnlevementOrdreTest extends TestCase
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

    /**
     * Une commande dont une ligne porte trois enlèvements successifs.
     *
     * On duplique une livraison existante : elle porte déjà des liens valides
     * vers un livreur, une adresse et un type de livraison, que les jointures
     * de `Livraison::liste()` exigent toutes.
     */
    private function uneCommandeATroisEnlevements(): ?array
    {
        $modele = Livraison::whereNotNull('detail_commande_id')
            ->whereNotNull('livreur_id')
            ->whereNotNull('adresse_livraison_id')
            ->whereNotNull('type_livraison_id')
            ->where('statut', \Help::$STATUT_ACTIF)
            ->first();

        if (!$modele) {
            return null;
        }

        $detail = \App\Models\DetailCommande::find($modele->detail_commande_id);

        if (!$detail || !$detail->commande_id) {
            return null;
        }

        $commande = Commande::find($detail->commande_id);

        if (!$commande) {
            return null;
        }

        $creees = [];

        foreach ([1, 2, 3] as $rang) {
            $copie = $modele->replicate();
            $copie->numero              = 'ORD-' . $rang . '-' . uniqid();
            $copie->detail_commande_id  = $detail->id;
            $copie->client_id           = $commande->client_id;
            $copie->statut              = \Help::$STATUT_ACTIF;
            $copie->accepte             = 1;
            $copie->save();

            $creees[] = $copie->id;
        }

        return [$commande, $detail, $creees];
    }

    public function test_les_enlevements_vont_du_plus_ancien_au_plus_recent(): void
    {
        $contexte = $this->uneCommandeATroisEnlevements();

        if (!$contexte) {
            $this->markTestSkipped('Aucune livraison exploitable comme modele.');
        }

        [$commande, $detail, $creees] = $contexte;

        URL::forceRootUrl('');

        $reponse = $this->actingAs($this->unAdmin())->get('/orders-be/' . $commande->numero);
        $reponse->assertOk();

        $ligne = collect($reponse->viewData('details'))->firstWhere('id', $detail->id);

        $this->assertNotNull($ligne, 'La ligne de commande doit figurer sur le bon.');

        $ids = collect($ligne->livs)->pluck('id')->all();

        $this->assertNotEmpty($ids);

        $trie = $ids;
        sort($trie);

        $this->assertSame($trie, $ids, 'Les enlevements doivent se suivre du plus ancien au plus recent.');

        // Et nos trois copies s y trouvent bien dans l ordre de creation.
        $lesNotres = array_values(array_intersect($ids, $creees));

        $this->assertSame($creees, $lesNotres);
    }

    public function test_la_liste_de_travail_garde_le_plus_recent_en_premier(): void
    {
        // L ordre inverse reste celui des ecrans de suivi : si le modele
        // changeait, ce test tomberait et signalerait la regression ailleurs.
        $contexte = $this->uneCommandeATroisEnlevements();

        if (!$contexte) {
            $this->markTestSkipped('Aucune livraison exploitable comme modele.');
        }

        [$commande, $detail] = $contexte;

        $ids = collect(Livraison::liste(null, $commande->client_id, $detail->id))
            ->pluck('id')->all();

        $decroissant = $ids;
        rsort($decroissant);

        $this->assertSame($decroissant, $ids,
            'Livraison::liste() doit continuer a trier du plus recent au plus ancien.');
    }
}
