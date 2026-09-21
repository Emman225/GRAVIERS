<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DemandeLivraison;
use App\Models\DetailLivraison;
use App\Models\Livraison;
use App\Models\TypeUser;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * LE CODE DE LIVRAISON D'UNE DEMANDE DE LIVRAISON POUR LE CLIENT (10/09/2026),
 * comme pour les ventes et les locations : sur Mon compte et sur le détail,
 * pour chaque course acceptée par un livreur.
 */
class CodeDeLivraisonDesDemandesPourLeClientTest extends TestCase
{
    use DatabaseTransactions;

    private function demandeAvecCourse(int $accepte): array
    {
        TypeUser::firstOrCreate(['id' => \Help::$USER_CLIENT], ['nom' => 'Client', 'statut' => 1]);
        $compte = User::factory()->create(['type_user_id' => \Help::$USER_CLIENT, 'statut' => \Help::$STATUT_ACTIF]);
        $client = Client::factory()->create(['user_id' => $compte->id]);

        $demande = DemandeLivraison::create([
            'numero' => 'DL' . random_int(100000, 999999), 'client_id' => $client->id, 'montantTotal' => 10000,
            'etat_commande' => \Help::$COMMANDE_EN_TRAITEMENT, 'statut' => \Help::$STATUT_ACTIF,
        ]);
        $detail = DetailLivraison::create([
            'nom_produit' => 'Sable', 'qte' => 5, 'unite' => 'Tonne',
            'unite_produit_id' => \App\Models\UniteProduit::value('id'), 'description' => '',
            'demande_livraison_id' => $demande->id, 'etat_livraison' => \Help::$LIVRAISON_EN_TRAITEMENT,
            'statut' => \Help::$STATUT_ACTIF,
        ]);
        $course = Livraison::create([
            'numero' => 'CD' . random_int(100000, 999999), 'client_id' => $client->id,
            'detail_livraison_id' => $detail->id, 'provenance' => \Help::$LIVRAISON,
            'date_livraison' => date('Y-m-d'), 'qte' => 5, 'accepte' => $accepte,
            'etat_livraison' => \Help::$LIVRAISON_EN_ATTENTE, 'statut' => \Help::$STATUT_ACTIF, 'cout_livraison' => 0,
        ]);

        return [DemandeLivraison::find($demande->id), $course, $compte];
    }

    public function test_une_course_acceptee_montre_son_code_sur_mon_compte_et_le_detail(): void
    {
        [$demande, $course, $compte] = $this->demandeAvecCourse(Livraison::ACCEPTEE);
        $this->assertCount(1, $demande->coursesAcceptees());

        foreach (['/mon-compte', '/detail-demandeDe-livraison-' . $demande->id] as $url) {
            $html = $this->actingAs($compte)->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('Code de livraison', $html, $url);
            $this->assertStringContainsString($course->numero, $html, $url);
            $this->assertStringNotContainsString('Bon d&#039;enl', $html, $url . ' : une demande de livraison n\'a pas de bon.');
        }
        $html = $this->actingAs($compte)->get('/mon-compte')->getContent();
        $this->assertStringContainsString('demande de livraison n° ' . $demande->numero, urldecode($html), 'Le message WhatsApp nomme la demande.');
    }

    public function test_une_course_non_acceptee_ne_montre_pas_de_code_sur_mon_compte(): void
    {
        [$demande, $course, $compte] = $this->demandeAvecCourse(2);
        $this->assertCount(0, $demande->coursesAcceptees());

        $html = $this->actingAs($compte)->get('/mon-compte')->assertOk()->getContent();
        $this->assertStringNotContainsString($course->numero, $html);
    }
}
