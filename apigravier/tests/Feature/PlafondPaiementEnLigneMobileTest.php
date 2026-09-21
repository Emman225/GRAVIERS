<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Commande;
use App\Models\Devis;
use App\Models\Produit;
use App\Support\PlafondPaiementEnLigne;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * AU-DESSUS DE DEUX MILLIONS, ON NE PAIE PAS EN LIGNE — CÔTÉ MOBILE AUSSI.
 *
 * Le seuil était recopié dans TROIS points d'entrée de l'API, avec deux
 * comparaisons différentes : la commande et la location testaient
 * « <= 2 000 000 », la demande de livraison « < 2 000 000 ». Le montant exact
 * de deux millions était donc refusé d'un côté et accepté de l'autre.
 *
 * Et dans les trois cas, dépasser le plafond ne REFUSAIT rien : la passerelle
 * était simplement sautée. L'affaire s'enregistrait comme si elle devait être
 * réglée au comptoir, entrait dans la file du gestionnaire sans qu'un franc
 * soit encaissé, et l'application n'avait aucun moyen de le dire au client.
 *
 * L'application filtre déjà son menu au-dessus du plafond
 * (choix_adresse_screen.dart) : ce refus protège les versions plus anciennes
 * et tout appel qui ne passerait pas par elle. Elle sait l'afficher — elle
 * montre `message` dès que `code` n'est pas 200 — donc aucune nouvelle version
 * n'est nécessaire.
 */
class PlafondPaiementEnLigneMobileTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * AUCUN APPEL À LA VRAIE PASSERELLE.
     *
     * Sans cette barrière, ces essais joignent réellement PaySecure et y créent
     * des liens de paiement. Constaté en écrivant cet essai : la plateforme a
     * répondu avec une véritable URL de règlement.
     */
    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\Http::fake([
            '*' => \Illuminate\Support\Facades\Http::response(
                ['code' => 200, 'url' => 'https://passerelle.test/regler'], 200),
        ]);
    }

    private function unClientAvecCompte(): Client
    {
        $client = Client::whereNotNull('user_id')->whereHas('user')->first();

        if (!$client) {
            $this->markTestSkipped('Aucun client rattaché à un compte.');
        }

        return $client;
    }

    /**
     * Un panier dont le total dépasse le plafond.
     *
     * Le montant qui compte est celui que le SERVEUR recalcule : on force donc
     * la quantité, jamais le prix.
     */
    private function unPanierAuDessusDuPlafond(Client $client): array
    {
        // LE PRIX QUE LE SERVEUR RETIENDRA POUR CE CLIENT-LÀ.
        //
        // Deux fausses pistes traversées avant d'arriver ici, et chacune faisait
        // passer l'essai à côté de la règle qu'il prétend éprouver :
        //
        //   · `prix_moyen` n'est pas le prix retenu — le serveur passe par
        //     Produit::prixCatalogue() ;
        //   · et ce catalogue est lui-même écrasé par le PRIX PERSONNALISÉ du
        //     client quand il en a un. Le produit à 8 000 F du catalogue était
        //     facturé 100 F à ce client : 263 unités faisaient 26 300 F, très
        //     loin du plafond, et la commande passait sans rien prouver.
        //
        // On reproduit donc la règle du serveur : personnalisé, sinon catalogue.
        $ids = Produit::where('type_affaire', \Help::$VENTE)
            ->where('statut', \Help::$STATUT_ACTIF)
            ->pluck('id')->all();

        if (!$ids) {
            $this->markTestSkipped('Aucun produit de vente actif au catalogue.');
        }

        $catalogue = Produit::prixCatalogue($ids);
        $perso     = \App\Models\PrixPersonnalise::listeSurClient($client->id);

        $meilleur = null;

        foreach ($ids as $id) {
            $prix = (float) ($perso[$id] ?? ($catalogue[$id] ?? 0));

            if ($prix > 0 && (!$meilleur || $prix > $meilleur['prix'])) {
                $meilleur = ['id' => $id, 'prix' => $prix];
            }
        }

        if (!$meilleur) {
            $this->markTestSkipped('Aucun prix exploitable pour ce client.');
        }

        $qte = (int) ceil((PlafondPaiementEnLigne::PLAFOND + 100000) / $meilleur['prix']);

        return [[
            'produit_id' => $meilleur['id'],
            'qte'        => $qte,
            'prix'       => $meilleur['prix'],
            'livraison'  => 0,
        ]];
    }

    private function commander(Client $client, array $lignes, int $modePaiement)
    {
        return $this->postJson('/mon_gravier/enregistrer-commande', [
            'access'         => Crypt::encryptString((string) $client->user_id),
            'type'           => 'mobile',
            // 1 = en ligne, 3 = paiement en agence.
            'mode_paiement'  => $modePaiement,
            'moyen_paiement' => 0,
            'lignes'         => $lignes,
            'total'          => collect($lignes)->sum(fn ($l) => $l['prix'] * $l['qte']),
            'meFaireLivre'   => 0,
            'date_livraison' => now()->addDay()->toDateString(),
        ]);
    }

    // ---------------------------------------------------------------- LA RÈGLE

    public function test_le_plafond_porte_sur_un_montant_strictement_superieur(): void
    {
        // « SUPÉRIEUR à », comme le dit le message montré au client.
        $this->assertFalse(PlafondPaiementEnLigne::depasse(1999999));
        $this->assertFalse(PlafondPaiementEnLigne::depasse(2000000));
        $this->assertTrue(PlafondPaiementEnLigne::depasse(2000001));
    }

    public function test_le_plafond_est_le_meme_que_celui_de_l_application(): void
    {
        // L'application porte la même valeur en dur pour construire son menu.
        // Si les deux divergent, elle propose un règlement que le serveur
        // refuse — ou l'inverse.
        $this->assertSame(2000000, PlafondPaiementEnLigne::PLAFOND);
    }

    // ------------------------------------------------------------ LA COMMANDE

    public function test_une_commande_en_ligne_au_dessus_du_plafond_est_refusee(): void
    {
        $client = $this->unClientAvecCompte();
        $avant  = Commande::count();

        $reponse = $this->commander($client, $this->unPanierAuDessusDuPlafond($client), 1);

        $reponse->assertOk();

        $this->assertSame(400, $reponse->json('code'),
            'Une commande de plus de deux millions réglée « en ligne » doit être '
            . 'refusée, pas enregistrée en silence sans passerelle.');

        $this->assertStringContainsString('Paiement en agence',
            (string) $reponse->json('message'),
            'Le refus doit dire au client comment poursuivre.');

        $this->assertSame($avant, Commande::count(),
            'Une commande a été créée alors qu\'elle était refusée.');
    }

    /**
     * ET LE PAIEMENT EN AGENCE PASSE, LUI.
     *
     * C'est le garde-fou du garde-fou : refuser trop large empêcherait toute
     * grosse commande, ce qui est exactement ce que le message invite à faire.
     */
    public function test_la_meme_commande_en_agence_est_acceptee(): void
    {
        $client = $this->unClientAvecCompte();
        $avant  = Commande::count();

        $reponse = $this->commander($client, $this->unPanierAuDessusDuPlafond($client), 3);

        $reponse->assertOk();

        $this->assertContains($reponse->json('code'), [200, 201],
            'Une commande de plus de deux millions réglée EN AGENCE doit passer : '
            . 'c\'est précisément ce que le message demande au client de faire. '
            . 'Message reçu : ' . (string) $reponse->json('message'));

        $this->assertSame($avant + 1, Commande::count());
    }

    // ------------------------------------------------------------- LE DEVIS

    /**
     * UN PAIEMENT NON CONFIRMÉ NE CLÔT PAS LE DEVIS.
     *
     * Le devis d'origine était clos dès l'enregistrement de la commande, y
     * compris quand celle-ci partait « EN ATTENTE DE PAIEMENT ». Un client qui
     * annulait son règlement laissait derrière lui un devis annoncé
     * « Commandé » alors que rien n'avait été encaissé.
     */
    public function test_le_devis_reste_ouvert_tant_que_le_paiement_n_est_pas_confirme(): void
    {
        $client = $this->unClientAvecCompte();

        $produit = Produit::where('type_affaire', \Help::$VENTE)
            ->where('statut', \Help::$STATUT_ACTIF)
            ->where('prix_moyen', '>', 0)
            ->first();

        if (!$produit) {
            $this->markTestSkipped('Aucun produit de vente actif au catalogue.');
        }

        $devis = Devis::create([
            'numero'      => 'T' . substr((string) uniqid(), -8),
            'client_id'   => $client->id,
            'montant'     => (float) $produit->prix_moyen,
            'montant_ht'  => (float) $produit->prix_moyen,
            'tva'         => 0,
            'statut'      => \Help::$STATUT_ACTIF,
        ]);

        $reponse = $this->postJson('/mon_gravier/enregistrer-commande', [
            'access'         => Crypt::encryptString((string) $client->user_id),
            'type'           => 'mobile',
            'mode_paiement'  => 1,          // en ligne : la commande attend son règlement
            'moyen_paiement' => 0,
            'devis_id'       => $devis->id,
            'lignes'         => [[
                'produit_id' => $produit->id,
                'qte'        => 1,
                'prix'       => (float) $produit->prix_moyen,
                'livraison'  => 0,
            ]],
            'total'          => (float) $produit->prix_moyen,
            'meFaireLivre'   => 0,
            'date_livraison' => now()->addDay()->toDateString(),
        ]);

        $reponse->assertOk();

        $this->assertContains($reponse->json('code'), [200, 201],
            'La commande devait être acceptée. Message : ' . (string) $reponse->json('message'));

        $commande = Commande::where('devis_id', $devis->id)->latest('id')->first();

        $this->assertNotNull($commande, 'La commande doit être rattachée à son devis.');

        $this->assertSame(\Help::$COMMANDE_EN_ATTENTE_PAIEMENT, $commande->etat_commande,
            'La commande réglée en ligne doit attendre son paiement.');

        $this->assertSame(\Help::$STATUT_ACTIF, (int) $devis->fresh()->statut,
            'Le devis est annoncé « Commandé » alors qu\'aucun franc n\'a été '
            . 'encaissé : c\'est le défaut constaté le 04/09/2026 sur le site, '
            . 'et l\'API le reproduisait à l\'identique.');
    }
}
