<?php

namespace Tests\Feature;

use App\Models\Agence;
use App\Models\Client;
use App\Models\Commande;
use App\Models\Paiement;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * LE GUICHET DES VENTES NE MONTRE QUE DES VENTES.
 *
 * Constaté le 01/09/2026 : sur /comptant/encaissements, la colonne
 * « N° Commande » était vide sur certaines lignes.
 *
 * La cause : cet écran ne filtrait PAS sur le service, à la différence des deux
 * autres guichets — locations et demandes de livraison — qui le font depuis
 * toujours. Un encaissement de LOCATION ou de LIVRAISON fait au guichet pour un
 * client ordinaire atterrissait donc dans la liste des ventes. Sans numéro de
 * commande, puisqu'il n'y en a pas.
 *
 * Et la conséquence dépassait la case vide : « Total encaissé » additionne
 * toutes les lignes de l'écran. Le total des ventes était donc gonflé de
 * montants qui n'en sont pas.
 */
class GuichetVentesNumeroCommandeTest extends TestCase
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

    private function unClientOrdinaire(): Client
    {
        $client = Client::where(function ($q) {
                $q->where('client_a_terme', 0)->orWhereNull('client_a_terme');
            })->where('statut', 1)->first();

        if (!$client) {
            $this->markTestSkipped('Aucun client ordinaire actif.');
        }

        return $client;
    }

    /** Un encaissement de guichet : agence et caissier renseignés. */
    private function unEncaissement(Client $client, string $service, ?int $serviceId, float $montant): Paiement
    {
        $agence = Agence::first();

        if (!$agence) {
            $this->markTestSkipped('Aucune agence.');
        }

        return Paiement::create([
            'code'          => 'TEST-' . uniqid(),
            // `libelle` est obligatoire et sans valeur par défaut en base.
            'libelle'       => 'Encaissement d’essai',
            'client_id'     => $client->id,
            'service'       => $service,
            'service_id'    => $serviceId,
            'montant_total' => $montant,
            'montant_restant' => 0,
            'statut'        => 1,
            'agence_id'     => $agence->id,
            'caissier_id'   => $this->unAdmin()->id,
        ]);
    }

    private function uneCommande(Client $client): Commande
    {
        return Commande::create([
            'numero'        => 'TSTV' . substr(uniqid(), -8),
            'client_id'     => $client->id,
            'montant_total' => 50000,
            'etat_commande' => \Help::$COMMANDE_EN_ATTENTE,
            'statut'        => 3,
        ]);
    }

    /** @return array<int,object> les lignes affichées par l'écran */
    private function lignesDeLEcran(): array
    {
        $reponse = $this->actingAs($this->unAdmin())->get('/comptant/encaissements');
        $reponse->assertOk();

        return collect($reponse->viewData('lignes'))->all();
    }

    /**
     * UNE LOCATION ENCAISSÉE AU GUICHET N'ENTRE PAS DANS LA LISTE DES VENTES.
     */
    public function test_une_location_n_apparait_pas_au_guichet_des_ventes(): void
    {
        $client = $this->unClientOrdinaire();

        $avant = count($this->lignesDeLEcran());

        $this->unEncaissement($client, \Help::$LOCATION, 999999, 81880);
        $this->unEncaissement($client, \Help::$LIVRAISON, 999998, 12000);

        $this->assertCount($avant, $this->lignesDeLEcran(),
            'Un encaissement de location ou de livraison apparaît dans la liste '
            . 'des ventes : il y figure sans numéro de commande, et son montant '
            . 'gonfle le total encaissé des ventes.');
    }

    /** UNE VENTE, ELLE, Y FIGURE AVEC SON NUMÉRO. */
    public function test_une_vente_y_figure_avec_son_numero(): void
    {
        $client = $this->unClientOrdinaire();
        $commande = $this->uneCommande($client);

        $this->unEncaissement($client, \Help::$COMMANDE, $commande->id, 50000);

        $numeros = array_map(fn ($l) => $l->numero_commande, $this->lignesDeLEcran());

        $this->assertContains($commande->numero, $numeros,
            'L’encaissement d’une vente doit figurer au guichet avec le numéro '
            . 'de sa commande.');
    }

    /** LE TOTAL ENCAISSÉ NE COMPTE QUE LES VENTES. */
    public function test_le_total_encaisse_ignore_les_autres_services(): void
    {
        $client = $this->unClientOrdinaire();

        $reponse = $this->actingAs($this->unAdmin())->get('/comptant/encaissements');
        $avant = (float) $reponse->viewData('totalEncaisse');

        $this->unEncaissement($client, \Help::$LOCATION, 999999, 81880);

        $apres = (float) $this->actingAs($this->unAdmin())
            ->get('/comptant/encaissements')->viewData('totalEncaisse');

        $this->assertEqualsWithDelta($avant, $apres, 0.01,
            'Le total encaissé des ventes a bougé de ' . ($apres - $avant)
            . ' F après un encaissement de LOCATION : il compte des montants '
            . 'qui ne sont pas des ventes.');
    }

    /**
     * UNE CASE VIDE NE DIT RIEN — ON DIT POURQUOI.
     *
     * Il reste un cas où la commande est introuvable : elle a été mise à la
     * corbeille APRÈS son encaissement. Le règlement, lui, a bien eu lieu et son
     * reçu porte ce numéro : le caissier doit pouvoir le retrouver.
     */
    public function test_une_commande_supprimee_garde_son_numero_a_l_ecran(): void
    {
        $client = $this->unClientOrdinaire();
        $commande = $this->uneCommande($client);

        $this->unEncaissement($client, \Help::$COMMANDE, $commande->id, 50000);

        $commande->delete();   // mise à la corbeille

        $ligne = collect($this->lignesDeLEcran())
            ->first(fn ($l) => $l->numero_commande === $commande->numero);

        $this->assertNotNull($ligne,
            'Une commande mise à la corbeille après son encaissement laisse la '
            . 'ligne sans numéro : le règlement devient introuvable.');

        $this->assertTrue((bool) $ligne->commande_supprimee,
            'L’écran doit signaler que la commande est à la corbeille, sans quoi '
            . 'on la cherche en vain dans les listes.');
    }

    /** AUCUNE LIGNE NE RESTE MUETTE. */
    public function test_aucune_ligne_n_a_de_numero_vide(): void
    {
        $client = $this->unClientOrdinaire();

        // Un encaissement de vente dont le service_id ne pointe sur rien.
        $this->unEncaissement($client, \Help::$COMMANDE, 999997, 30000);

        foreach ($this->lignesDeLEcran() as $ligne) {
            $this->assertNotSame('', trim((string) $ligne->numero_commande),
                'Une ligne du guichet n’affiche aucun numéro : le caissier ne '
                . 'peut pas savoir à quoi se rapporte ce règlement.');
        }
    }

    /** LES TROIS GUICHETS FILTRENT DE LA MÊME FAÇON. */
    public function test_les_trois_guichets_filtrent_sur_le_service(): void
    {
        $ventes = file_get_contents(app_path('Http/Controllers/CommandeComptantController.php'));
        $locations = file_get_contents(app_path('Http/Controllers/LocationComptantController.php'));
        $livraisons = file_get_contents(app_path('Http/Controllers/DemandeLivraisonComptantController.php'));

        $this->assertStringContainsString("->where('service', Help::\$COMMANDE)", $ventes,
            'Le guichet des ventes ne filtre pas sur le service : les autres '
            . 'affaires y entrent.');
        $this->assertStringContainsString("->where('service', Help::\$LOCATION)", $locations,
            'Le guichet des locations doit filtrer sur son service.');
        $this->assertStringContainsString("->where('service', Help::\$LIVRAISON)", $livraisons,
            'Le guichet des livraisons doit filtrer sur son service.');
    }
}
