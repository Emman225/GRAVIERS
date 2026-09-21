<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DetailLocation;
use App\Models\Fournisseur;
use App\Models\Livraison;
use App\Models\Livreur;
use App\Models\Location;
use App\Models\Produit;
use App\Models\User;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * UNE COURSE DE LIVREUR SANS ADRESSE N'EXISTE PAS.
 *
 * Constaté le 29/08/2026 : une location venait d'être traitée, et sur le mobile
 * du livreur le lieu affichait « null ». La course avait bien été créée, mais
 * `livraison.adresse_livraison_id` était vide — le livreur savait quoi porter,
 * et pas où.
 *
 * L'origine est en amont. Le parcours de LOCATION du site lisait le choix
 * « Me faire livrer / Retrait sur place » pour calculer le coût, puis le
 * jetait : contrairement au parcours de VENTE (infoLivraison), il ne le
 * rangeait pas en session. La création de la location retombait donc sur son
 * repli « livrable », et une location de retrait se présentait au gestionnaire
 * comme une livraison. Il affectait un livreur, et la course partait sans
 * adresse — avec, au passage, une distance de 0 km pour sa rémunération.
 */
class LivraisonLocationSansAdresseTest extends TestCase
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
     * Une location SOLDÉE, EN ATTENTE, sans adresse de livraison,
     * avec une ligne prête à être validée.
     */
    private function uneLocationSansAdresse(int $estLivrable = 1): array
    {
        $client  = Client::whereNotNull('user_id')->first();
        $produit = Produit::where('statut', 1)->first();

        if (!$client || !$produit) {
            $this->markTestSkipped('Fixtures absentes (client ou produit).');
        }

        $location = Location::create([
            'numero'               => 'TEST-' . uniqid(),
            'client_id'            => $client->id,
            'adresse_livraison_id' => null,
            'montant_total'        => 10000,
            'etat_location'        => \Help::$LOCATION_EN_ATTENTE,
            // LE CHOIX DU CLIENT, et non plus celui du gestionnaire : depuis le
            // 01/09/2026 l'écran de validation ne propose plus de cases à cocher,
            // il constate `est_livrable`. Une location de retrait se monte donc
            // avec 0, pas en postant « retrait » sur une location à livrer.
            'est_livrable'         => $estLivrable,
            // Soldée : sans quoi la validation s'arrête avant le garde-fou.
            'statut'               => 3,
        ]);

        $detail = DetailLocation::create([
            'produit_id'    => $produit->id,
            'location_id'   => $location->id,
            'qte'           => 2,
            'debut'         => now()->toDateString(),
            'fin'           => now()->addDay()->toDateString(),
            'prix'          => 10000,
            'nombre_jour'   => 1,
            'etat_location' => \Help::$LOCATION_EN_ATTENTE,
        ]);

        return [$location, $detail];
    }

    private function corps(DetailLocation $detail): array
    {
        $livreur     = Livreur::first();
        $vehicule    = Vehicule::first();
        $fournisseur = Fournisseur::first();

        if (!$livreur || !$vehicule || !$fournisseur) {
            $this->markTestSkipped('Fixtures absentes (livreur, véhicule ou fournisseur).');
        }

        return [
            'livreur'        => $livreur->id,
            'vehicule'       => $vehicule->id,
            'fournisseur'    => [$detail->id => $fournisseur->id],
            'qte'            => [$detail->id => 2],
        ];
    }

    /** AUCUNE COURSE N'EST CRÉÉE, ET LA LOCATION RESTE À TRAITER. */
    public function test_une_livraison_sans_adresse_est_refusee(): void
    {
        [$location, $detail] = $this->uneLocationSansAdresse();

        $avant = Livraison::count();

        $reponse = $this->actingAs($this->unAdmin())
            ->post('/valider-location-' . $location->id, $this->corps($detail));

        // On observe l'EFFET, pas le message : Flasher capte la clé « error »
        // pour la rejouer en toast et la retire de la session. Le gestionnaire
        // voit bien l'avertissement ; un essai qui interrogerait la clé ne
        // trouverait rien.
        $reponse->assertStatus(302);

        $this->assertSame($avant, Livraison::count(),
            'Aucune course ne doit être créée : le livreur verrait « Lieu : null » '
            . 'et sa distance serait comptée à 0 km.');

        $this->assertSame(
            \Help::$LOCATION_EN_ATTENTE,
            $location->fresh()->etat_location,
            'La location doit rester EN ATTENTE : elle n’a pas été traitée, et le '
            . 'gestionnaire doit pouvoir la reprendre.'
        );
    }

    /** LE RETRAIT SUR PLACE, LUI, N'A BESOIN D'AUCUNE ADRESSE. */
    public function test_le_retrait_sur_place_reste_possible_sans_adresse(): void
    {
        // Un retrait, c'est `est_livrable = 0` SANS adresse : le client vient
        // chercher son matériel, il n'y a rien à adresser.
        [$location, $detail] = $this->uneLocationSansAdresse(0);

        $this->actingAs($this->unAdmin())
            ->post('/valider-location-' . $location->id, $this->corps($detail));

        // Le garde-fou ne doit pas déborder sur le retrait : sinon plus aucune
        // location de retrait ne pourrait être validée — le client vient
        // chercher le matériel, il n'y a rien à adresser.
        $this->assertDatabaseHas('livraison', [
            'detail_commande_id' => $detail->id,
            'provenance'         => \Help::$LOCATION,
            'livre_par'          => 2,
        ]);
    }

    /** L'ÉCRAN LE DIT AVANT, PAS APRÈS. */
    public function test_l_ecran_de_validation_annonce_l_absence_d_adresse(): void
    {
        [$location, ] = $this->uneLocationSansAdresse();

        $reponse = $this->actingAs($this->unAdmin())
            ->get('/valider-location-page-' . $location->id);

        $reponse->assertOk();

        // Le libellé a changé le 01/09/2026 : le gestionnaire ne pouvant plus
        // basculer en retrait, l'alerte lui dit quoi faire au lieu de lui
        // proposer un choix disparu. L'intention, elle, ne change pas :
        // l'absence d'adresse est annoncée AVANT la validation.
        $reponse->assertSee('ne porte aucune adresse', false);
    }

    /**
     * LE CHOIX DU CLIENT EST ENREGISTRÉ PAR LE PARCOURS DE LOCATION.
     *
     * C'est l'origine du défaut : sans lui, `est_livrable` retombait sur son
     * repli « livrable » et une location de retrait était proposée au
     * gestionnaire comme une livraison.
     */
    public function test_le_parcours_de_location_enregistre_le_choix_de_livraison(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/ClientController.php'));
        $source = preg_replace('!/\*.*?\*/!s', '', $source);
        $source = preg_replace('!^\s*//.*$!m', '', $source);

        $debut = strpos($source, 'function choixDateProduitLocation(');
        $this->assertNotFalse($debut, 'choixDateProduitLocation introuvable.');

        $corps = substr($source, $debut,
            strpos($source, ' function ', $debut + 20) - $debut);

        $this->assertSame(2, substr_count($corps, "'estLivrable'"),
            'Le choix « Me faire livrer / Retrait sur place » doit être rangé en '
            . 'session DANS LES DEUX BRANCHES : la création de la location le lit '
            . 'sous cette clé, et sans lui elle retombe sur « livrable ».');

        $this->assertStringContainsString("'estLivrable' => 'non',", $corps,
            'La branche « Retrait sur place » ne range pas le choix : la location '
            . 'sera créée livrable, un livreur lui sera affecté, et sa course '
            . 'partira sans adresse.');
    }
}
