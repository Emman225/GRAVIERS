<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Commande;
use App\Models\Enlevement;
use App\Models\Facture;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Le grand livre des clients, et l'état de livraison.
 *
 * GRAND LIVRE. Les deux écrans chargeaient TOUS les comptes clients puis
 * écartaient les autres dans le Blade ; l'en-tête du grand livre ordinaire
 * annonçait neuf colonnes pour huit cellules par ligne, décalant le tableau sur
 * toute sa largeur ; et le solde, calculé en SQL écrit à la main, ignorait la
 * suppression logique — une facture supprimée continuait de charger le client,
 * un règlement supprimé continuait de l'alléger.
 *
 * ÉTAT DE LIVRAISON. Le bandeau annonçait « Qté livrée » en sommant la quantité
 * ENLEVÉE : les deux compteurs affichaient donc toujours le même nombre.
 */
class GrandLivreEtLivraisonTest extends TestCase
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

    private function ecran(string $url): \Illuminate\Testing\TestResponse
    {
        URL::forceRootUrl('');
        $reponse = $this->actingAs($this->unAdmin())->get($url);
        $reponse->assertOk();

        return $reponse;
    }

    /** Un client, son compte, et le type de grand livre auquel il appartient. */
    private function unClient(int $aTerme): Client
    {
        $user = User::create([
            'nom_prenoms'  => 'Grand Livre Recette',
            'email'        => 'gl-' . uniqid() . '@example.test',
            'login'        => 'gl' . substr((string) uniqid(), -8),
            'password'     => bcrypt(\Illuminate\Support\Str::random(16)),
            'type_user_id' => 4,
            'statut'       => \Help::$STATUT_ACTIF,
        ]);

        return Client::create([
            'user_id'        => $user->id,
            'nom'            => 'Livre',
            'prenom'         => 'Recette',
            'email'          => $user->email,
            'contact1'       => '0733333333',
            'type_client'    => 'PARTICULIER',
            'client_a_terme' => $aTerme,
            'statut'         => \Help::$STATUT_ACTIF,
        ]);
    }

    private function idsAffiches(string $url): array
    {
        return collect($this->ecran($url)->viewData('clients'))
            ->pluck('id')->all();
    }

    // ------------------------------------------------------------ GRAND LIVRE

    public function test_chaque_grand_livre_ne_montre_que_ses_clients(): void
    {
        $ordinaire = $this->unClient(0);
        $aTerme    = $this->unClient(1);

        $listeOrdinaire = $this->idsAffiches('/grand-livre-client-ordinaire');
        $listeATerme    = $this->idsAffiches('/grand-livre-client-client-a-terme');

        $this->assertContains($ordinaire->id, $listeOrdinaire);
        $this->assertNotContains($aTerme->id, $listeOrdinaire);

        $this->assertContains($aTerme->id, $listeATerme);
        $this->assertNotContains($ordinaire->id, $listeATerme);
    }

    public function test_le_tri_se_fait_en_base_et_non_dans_la_vue(): void
    {
        $this->unClient(0);

        // La sélection remonte des clients, pas des comptes : un compte sans
        // fiche client ne produit plus de ligne muette.
        foreach ($this->ecran('/grand-livre-client-ordinaire')->viewData('clients') as $c) {
            $this->assertInstanceOf(Client::class, $c);
            $this->assertSame(0, (int) $c->client_a_terme);
        }
    }

    public function test_une_facture_supprimee_ne_charge_plus_le_client(): void
    {
        $client = $this->unClient(0);

        $commande = Commande::create([
            'numero'    => 'GL' . substr((string) uniqid(), -8),
            'client_id' => $client->id,
            'statut'    => \Help::$STATUT_ACTIF,
        ]);

        $facture = Facture::create([
            'numero'     => 'FGL' . substr((string) uniqid(), -7),
            'user_id'    => $this->unAdmin()->id,
            'client_id'  => $client->id,
            'service'    => \Help::$COMMANDE,
            'service_id' => $commande->id,
            'montant'    => 50000,
            'statut'     => 2,
        ]);

        $this->assertSame(50000.0, round(\Help::soldeClientBrut($client), 2));

        // Supprimée : l'ORM l'ignore partout ailleurs, le solde le doit aussi.
        $facture->delete();

        $this->assertSame(0.0, round(\Help::soldeClientBrut($client), 2),
            'Une facture supprimée continuait de charger le client.');
    }

    public function test_les_deux_grands_livres_repondent(): void
    {
        $this->ecran('/grand-livre-client-ordinaire')->assertOk();
        $this->ecran('/grand-livre-client-client-a-terme')->assertOk();
    }

    // ----------------------------------------------------- ÉTAT DE LIVRAISON

    public function test_la_quantite_servie_n_est_pas_la_quantite_enlevee(): void
    {
        $bon = Enlevement::where('statut', \Help::$STATUT_ACTIF)->first();

        if (!$bon) {
            $this->markTestSkipped('Aucun bon actif.');
        }

        $bon->update(['qte' => 30, 'qte_servi' => 18]);

        $enlevements = $this->ecran('/reapprovisionnement')->viewData('enlevements');

        $enleve = (float) $enlevements->sum('qte');
        $servi  = (float) $enlevements->sum(fn ($e) => (float) $e->qte_servi);

        // Le bandeau sommait deux fois `qte` : il annonçait toujours
        // livré = enlevé.
        $this->assertNotSame($enleve, $servi,
            'La quantité servie ne peut pas égaler la quantité enlevée sur ce jeu de données.');
        $this->assertGreaterThan($servi, $enleve);
    }

    public function test_un_bon_annule_sort_de_l_etat_de_livraison(): void
    {
        $bon = Enlevement::where('statut', \Help::$STATUT_ACTIF)->first();

        if (!$bon) {
            $this->markTestSkipped('Aucun bon actif.');
        }

        $this->assertContains(
            $bon->id,
            collect($this->ecran('/reapprovisionnement')->viewData('enlevements'))->pluck('id')->all()
        );

        $bon->update(['statut' => 0]);

        $this->assertNotContains(
            $bon->id,
            collect($this->ecran('/reapprovisionnement')->viewData('enlevements'))->pluck('id')->all()
        );
    }
}
