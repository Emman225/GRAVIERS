<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DemandeLivraison;
use App\Models\Facture;
use App\Models\LignePaiement;
use App\Models\ModePaiement;
use App\Models\Paiement;
use App\Models\TvaCommande;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Un transport payé reçoit sa facture.
 *
 * Aucune facture n'était jamais émise pour une demande de livraison : le code
 * n'en créait que pour les commandes et les locations. Or le solde d'un client
 * compare ses règlements à ses FACTURES — un transport réglé restait donc
 * indéfiniment affiché « réglé d'avance », de l'argent que le client croyait
 * avoir à son crédit alors qu'il avait acheté un service rendu.
 *
 * La pièce était d'abord interne : la certification DGI ne savait construire que
 * la vente et la location. Le responsable est revenu sur ce point — le transport
 * doit être certifié comme le reste — et la facture a désormais son propre
 * message, qui déclare LA COURSE et non la marchandise transportée.
 */
class FactureTransportTest extends TestCase
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

    private function uneDemande(float $montant, float $remise = 0): DemandeLivraison
    {
        $client = Client::first();

        if (!$client) {
            $this->markTestSkipped('Aucun client en base.');
        }

        return DemandeLivraison::create([
            'numero'        => 'DL-' . substr(uniqid(), -8),
            'client_id'     => $client->id,
            'montantTotal'  => $montant,
            'remise'        => $remise,
            'etat_commande' => \Help::$COMMANDE_TERMINE,
            'statut'        => \Help::$STATUT_ACTIF,
        ]);
    }

    private function facturer(DemandeLivraison $demande): \Illuminate\Testing\TestResponse
    {
        URL::forceRootUrl('');

        return $this->actingAs($this->unAdmin())
            ->post('/generer-facture-livraison/' . $demande->id);
    }

    private function laFacture(DemandeLivraison $demande): ?Facture
    {
        return Facture::where('service', \Help::$LIVRAISON)
            ->where('service_id', $demande->id)->first();
    }

    public function test_une_facture_est_emise_pour_le_transport(): void
    {
        $demande = $this->uneDemande(8000);

        $this->facturer($demande)->assertRedirect();

        $facture = $this->laFacture($demande);

        $this->assertNotNull($facture, 'Le transport doit recevoir sa piece.');
        $this->assertSame(8000.0, round((float) $facture->montant, 2));
        $this->assertSame($demande->client_id, $facture->client_id);
    }

    public function test_le_montant_est_net_de_remise_et_porte_la_tva(): void
    {
        $demande = $this->uneDemande(10000, 1000);

        TvaCommande::create([
            'client_id'    => $demande->client_id,
            'commande_id'  => $demande->id,
            'montant'      => 1620,
            'type_affaire' => \Help::$LIVRAISON,
            'statut'       => \Help::$STATUT_ACTIF,
        ]);

        $this->facturer($demande);

        // 10 000 - 1 000 + 1 620
        $this->assertSame(10620.0, round((float) $this->laFacture($demande)->montant, 2));
    }

    public function test_la_tva_d_une_autre_affaire_n_est_pas_reprise(): void
    {
        // Une demande, une commande et une location peuvent porter le meme
        // identifiant : sans le filtre, la TVA d une vente gonflerait le transport.
        $demande = $this->uneDemande(8000);

        TvaCommande::create([
            'client_id'    => $demande->client_id,
            'commande_id'  => $demande->id,
            'montant'      => 99999,
            'type_affaire' => 'VENTE',
            'statut'       => \Help::$STATUT_ACTIF,
        ]);

        $this->facturer($demande);

        $this->assertSame(8000.0, round((float) $this->laFacture($demande)->montant, 2));
    }

    public function test_le_solde_du_client_retombe_a_zero(): void
    {
        // Le but de toute l operation : le reglement cesse d apparaitre comme
        // un credit du client.
        $demande = $this->uneDemande(8000);
        $client  = Client::find($demande->client_id);

        LignePaiement::whereIn('paiement_id',
            Paiement::where('client_id', $client->id)->pluck('id'))->forceDelete();
        Paiement::where('client_id', $client->id)->forceDelete();
        Facture::where('client_id', $client->id)->forceDelete();

        $paiement = Paiement::create([
            'client_id'       => $client->id,
            'code'            => 'TST-' . strtoupper(substr(md5(uniqid()), 0, 8)),
            'libelle'         => 'Transport',
            'montant_total'   => 8000,
            'montant_restant' => 0,
            'statut'          => \Help::$STATUT_ACTIF,
            'service'         => \Help::$LIVRAISON,
            'service_id'      => $demande->id,
        ]);

        LignePaiement::create([
            'paiement_id'      => $paiement->id,
            'mode_paiement_id' => ModePaiement::first()?->id,
            'montant'          => 8000,
            'statut'           => \Help::$STATUT_ACTIF,
            'service'          => \Help::$LIVRAISON,
            'service_id'       => $demande->id,
        ]);

        $this->assertSame(8000.0, round(\Help::soldeClientBrut($client, false), 2),
            'Avant facturation, le reglement apparait comme un credit.');

        $this->facturer($demande);

        $this->assertSame(0.0, round(\Help::soldeClientBrut($client->fresh(), false), 2),
            'Une fois facture, le credit disparait.');
    }

    public function test_une_demande_ne_se_facture_pas_deux_fois(): void
    {
        $demande = $this->uneDemande(8000);

        $this->facturer($demande);
        $this->facturer($demande);

        $this->assertSame(1, Facture::where('service', \Help::$LIVRAISON)
            ->where('service_id', $demande->id)->count());
    }

    public function test_une_demande_sans_montant_ne_se_facture_pas(): void
    {
        $demande = $this->uneDemande(0);

        $this->facturer($demande);

        $this->assertNull($this->laFacture($demande),
            'Une facture a zero n aurait aucun sens.');
    }

    public function test_la_piece_attend_sa_certification(): void
    {
        $demande = $this->uneDemande(8000);

        $this->facturer($demande);

        $facture = $this->laFacture($demande);

        $this->assertSame('pending', $facture->fne_status);
        $this->assertNotEmpty($facture->numero_fne,
            'Un numero FNE est reserve des la creation, comme pour la location.');

        URL::forceRootUrl('');

        $reponse = $this->actingAs($this->unAdmin())->get('/factures-non-validees');

        $reponse->assertOk();

        $this->assertContains($facture->id,
            collect($reponse->viewData('factures'))->pluck('id')->all(),
            'La facture doit attendre dans la file des certifications.');
    }

    public function test_le_message_DGI_declare_la_course_et_non_la_marchandise(): void
    {
        // Declarer les produits transportes comme des articles vendus
        // reviendrait a annoncer a l administration une vente qui n a pas eu lieu.
        $demande = $this->uneDemande(8000);

        $this->facturer($demande);

        $payload = \App\Services\FneService::buildLivraisonPayload($this->laFacture($demande));

        $this->assertCount(1, $payload['items'],
            'Une course, une ligne.');

        $this->assertStringContainsString('transport', mb_strtolower($payload['items'][0]['description']));
        $this->assertSame(8000.0, round((float) $payload['items'][0]['amount'], 2));
        $this->assertSame(1, (int) $payload['items'][0]['quantity']);
    }

    public function test_la_remise_n_est_comptee_qu_une_fois(): void
    {
        // La ligne garde son montant brut ; la remise part EN POUR CENT sur la
        // ligne (lot 99, 16/09/2026 : la DGI lit « discount » en %), jamais
        // deduite du montant en plus, ce qui la compterait deux fois.
        $demande = $this->uneDemande(10000, 1000);

        $this->facturer($demande);

        $payload = \App\Services\FneService::buildLivraisonPayload($this->laFacture($demande));

        $this->assertSame(10000.0, round((float) $payload['items'][0]['amount'], 2));
        $this->assertSame(10.0, round((float) $payload['items'][0]['discount'], 2), '1 000 sur 10 000 = 10 %.');
        $this->assertSame(0.0, round((float) $payload['discount'], 2), 'Plus de remise globale en francs.');
    }

    public function test_le_message_identifie_le_client(): void
    {
        $demande = $this->uneDemande(8000);

        $this->facturer($demande);

        $payload = \App\Services\FneService::buildLivraisonPayload($this->laFacture($demande));

        $this->assertNotEmpty($payload['clientCompanyName']);
        $this->assertContains($payload['template'], ['B2B', 'B2C']);
    }

    public function test_la_facture_se_consulte_en_pdf(): void
    {
        // Une piece qu on ne peut ni voir ni remettre au client ne sert a rien.
        $demande = $this->uneDemande(8000);

        $this->facturer($demande);

        URL::forceRootUrl('');

        $reponse = $this->actingAs($this->unAdmin())
            ->get('/facture-livraison/' . $this->laFacture($demande)->id . '/voir');

        $reponse->assertOk();
        $this->assertSame('application/pdf', $reponse->headers->get('content-type'));

        // Le numero doit figurer sur la piece : une facture sans numero
        // n identifie ni elle-meme ni son reglement.
        $donnees = \App\Services\FneService::getDonneesFne(
            $this->laFacture($demande), $this->laFacture($demande)->client
        );

        // Le numero FNE reserve a la creation, comme pour la location ; a
        // defaut, le numero interne. Dans tous les cas, la piece est numerotee.
        $facture = $this->laFacture($demande);

        $this->assertSame(
            $facture->fne_reference ?? $facture->numero_fne ?? $facture->numero,
            $donnees['fne_numero']
        );

        $this->assertNotEmpty($donnees['fne_numero'],
            'Une facture sans numero n identifie ni elle-meme ni son reglement.');

        $this->assertNotEmpty($donnees['fne_config']['raison_sociale'] ?? '',
            'L en-tete doit identifier l entreprise emettrice.');
    }

    public function test_la_facture_se_telecharge(): void
    {
        $demande = $this->uneDemande(8000);

        $this->facturer($demande);

        URL::forceRootUrl('');

        $reponse = $this->actingAs($this->unAdmin())
            ->get('/facture-livraison/' . $this->laFacture($demande)->id . '/telecharger');

        $reponse->assertOk();
        $this->assertStringContainsString('attachment',
            (string) $reponse->headers->get('content-disposition'));
    }

    public function test_l_ecran_propose_de_voir_la_facture(): void
    {
        $demande = $this->uneDemande(8000);

        $this->facturer($demande);

        URL::forceRootUrl('');

        $reponse = $this->actingAs($this->unAdmin())->get('/liste-demande-de-livraison-traitee');

        $reponse->assertOk();
        $reponse->assertSee('Voir facture', false);
        $reponse->assertSee('Télécharger', false);
    }

    public function test_la_file_des_certifications_propose_de_voir_la_facture(): void
    {
        $demande = $this->uneDemande(8000);

        $this->facturer($demande);

        URL::forceRootUrl('');

        $reponse = $this->actingAs($this->unAdmin())->get('/factures-non-validees');

        $reponse->assertOk();
        $reponse->assertSee('facture-livraison/' . $this->laFacture($demande)->id . '/voir', false);
        $reponse->assertSee('Transport', false);
    }

    public function test_la_file_ne_confond_pas_le_transport_avec_une_commande(): void
    {
        // `service_id` designe l affaire, quelle qu elle soit : sans traiter le
        // transport d abord, l ecran affichait le numero — et le lien — de la
        // commande qui porte par hasard le meme identifiant.
        $demande = $this->uneDemande(8000);

        $this->facturer($demande);

        $commandeHomonyme = \App\Models\Commande::find($demande->id);

        URL::forceRootUrl('');

        $reponse = $this->actingAs($this->unAdmin())->get('/factures-non-validees');

        if ($commandeHomonyme) {
            $reponse->assertDontSee(
                'orders-be/' . $commandeHomonyme->numero, false
            );
        }

        $reponse->assertSee($demande->numero, false);
    }
}
