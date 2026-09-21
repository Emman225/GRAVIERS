<?php

namespace Tests\Feature;

use App\Models\Commande;
use App\Models\ModePaiement;
use App\Models\Paiement;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * ON N'ENCAISSE PAS UNE AFFAIRE QUI N'EXISTE PLUS.
 *
 * Signalé le 06/09/2026 : un client passe une commande depuis l'application,
 * arrive à la passerelle de paiement et abandonne. La commande reste alors en
 * « EN ATTENTE DE PAIEMENT » — volontairement HORS de la file du gestionnaire,
 * donc absente de « Commandes en attente ».
 *
 * Le guichet d'encaissement, lui, la proposait quand même : son menu ne
 * regardait que le reste dû, jamais l'état. Les deux écrans se contredisaient,
 * et c'était le mauvais côté qui encaissait — un caissier pouvait prendre de
 * l'argent pour une commande que personne ne verrait jamais à traiter.
 *
 * La reproduction locale a montré un second trou, plus grave encore : une
 * commande ANNULÉE portant 9 220 fcfa de reste était elle aussi proposée.
 *
 * DEUX VERROUS SONT NÉCESSAIRES. Retirer la commande du menu ne suffit pas :
 * le formulaire poste un NUMÉRO, et un onglet resté ouvert suffit à en poster
 * un autre. Le refus doit donc aussi vivre là où l'argent s'enregistre.
 */
class GuichetRefuseAffaireMorteTest extends TestCase
{
    use DatabaseTransactions;

    private function caissier(): User
    {
        // Un encaissement exige une agence : sans elle, l'opération est
        // refusée pour une tout autre raison, et l'essai ne prouverait rien.
        //
        // Aucun administrateur du jeu local n'en a — c'est une opération faite
        // à la main en production. Plutôt que de SAUTER l'essai (un essai sauté
        // ne protège rien), on rattache l'agence nous-mêmes : la transaction
        // du test défait ce rattachement à la fin.
        $user = User::whereNotNull('agence_id')
            ->whereIn('type_user_id', [1, 2])
            ->first();

        if ($user) {
            return $user;
        }

        $agence = \App\Models\Agence::first();
        $user = User::whereIn('type_user_id', [1, 2])->first();

        if (!$agence || !$user) {
            $this->markTestSkipped('Ni agence ni administrateur en base.');
        }

        $user->agence_id = $agence->id;
        $user->save();

        return $user;
    }

    /** Une commande d'un client ordinaire, encore due, qu'on rendra morte. */
    private function uneCommandeDue(): Commande
    {
        $commande = Commande::whereHas('client', function ($q) {
                $q->where(function ($c) {
                    $c->where('client_a_terme', 0)->orWhereNull('client_a_terme');
                })->where('statut', 1);
            })
            ->where('statut', '!=', 0)
            ->get()
            ->first(fn (Commande $c) => $c->montantRestantDu() > 0);

        if (!$commande) {
            $this->markTestSkipped('Aucune commande comptant avec un reste dû.');
        }

        return $commande;
    }

    public function test_une_commande_annulee_n_est_plus_proposee_au_guichet(): void
    {
        $commande = $this->uneCommandeDue();
        Auth::guard('web')->login($this->caissier());

        // TÉMOIN : elle est bien proposée AVANT qu'on l'annule. Sans ce
        // contrôle, une absence pourrait venir de tout autre chose.
        $avant = $this->get('/comptant/encaissements');
        $avant->assertOk();
        $avant->assertSee($commande->numero);

        $commande->etat_commande = \Help::$AFFAIRE_ANNULEE;
        $commande->save();

        $apres = $this->get('/comptant/encaissements');
        $apres->assertOk();
        $apres->assertDontSee($commande->numero);
    }

    public function test_une_commande_dont_le_paiement_a_ete_abandonne_n_est_pas_proposee(): void
    {
        $commande = $this->uneCommandeDue();
        Auth::guard('web')->login($this->caissier());

        $this->get('/comptant/encaissements')->assertSee($commande->numero);

        // L'état que reçoit une commande mobile dont la passerelle est quittée.
        $commande->etat_commande = \Help::$COMMANDE_EN_ATTENTE_PAIEMENT;
        $commande->save();

        $this->get('/comptant/encaissements')->assertDontSee($commande->numero);
    }

    /**
     * LE SECOND VERROU : l'enregistrement refuse, même si le numéro est posté.
     *
     * On n'affirme pas sur le message affiché — Flasher intercepte les clés
     * `error` de la session et les rejoue en notification, si bien qu'une
     * vérification sur la session passerait à côté. On affirme sur ce qui
     * compte : AUCUN paiement n'a été créé.
     */
    public function test_l_encaissement_est_refuse_meme_si_le_numero_est_poste(): void
    {
        $commande = $this->uneCommandeDue();
        $mode = ModePaiement::where('statut', \Help::$STATUT_ACTIF)->first();

        if (!$mode) {
            $this->markTestSkipped('Aucun mode de paiement actif.');
        }

        Auth::guard('web')->login($this->caissier());

        $commande->etat_commande = \Help::$AFFAIRE_ANNULEE;
        $commande->save();

        $avant = Paiement::count();

        $this->post('/comptant/encaissements', [
            'numero_commande'  => $commande->numero,
            'mode_paiement_id' => $mode->id,
            'notes' => 'Recette automatique',
            'montant'          => 1,
        ]);

        $this->assertSame($avant, Paiement::count(),
            'Un encaissement a été enregistré pour la commande '
            . $commande->numero . ', qui est annulée. Retirer la commande du '
            . 'menu ne suffit pas : le formulaire poste un numéro.');
    }

    /** Une commande bien vivante, elle, reste encaissable. */
    public function test_une_commande_en_attente_reste_encaissable(): void
    {
        $commande = $this->uneCommandeDue();
        $commande->etat_commande = \Help::$COMMANDE_EN_ATTENTE;
        $commande->save();

        $this->assertTrue($commande->affaireVivante());

        Auth::guard('web')->login($this->caissier());
        $this->get('/comptant/encaissements')->assertSee($commande->numero);
    }

    /** La règle vaut pour les trois guichets, pas seulement pour les ventes. */
    public function test_les_trois_affaires_connaissent_la_regle(): void
    {
        foreach ([
            \App\Models\Commande::class,
            \App\Models\Location::class,
            \App\Models\DemandeLivraison::class,
        ] as $classe) {
            $this->assertTrue(method_exists($classe, 'affaireVivante'),
                $classe . ' ne sait pas dire si son affaire existe encore : '
                . 'son guichet peut donc encaisser une affaire annulée.');
        }
    }
}
