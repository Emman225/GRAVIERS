<?php

namespace Tests\Feature;

use App\Models\Apporteur;
use App\Models\Client;
use App\Models\CommissionApporteur;
use App\Models\Location;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * UNE COMMISSION DE LOCATION DÉSIGNE SA LOCATION — DANS LA COLONNE QU'ON LIT.
 *
 * Constaté sur l'application apporteur le 01/09/2026 à 19h33 : « Filleul non
 * identifié — Acquise le 1 septembre 2026 — Com. : 3 021 F ».
 *
 * LA CAUSE : deux conventions coexistaient pour désigner l'affaire.
 *
 *   · `commande_id` est la colonne POLYMORPHE : selon `type_affaire`, elle
 *     désigne une commande ou une location. C'est elle que lit l'application
 *     (CommissionApporteur::liste joint `commande` ET `location` dessus), et
 *     c'est elle qu'écrivent tous les autres points de création.
 *
 *   · le guichet des LOCATIONS écrivait `location_id` — une colonne que
 *     PERSONNE ne lit, et laissait `commande_id` vide.
 *
 * La commission naissait donc sans affaire lisible : ni la jointure sur
 * `commande`, ni celle sur `location` ne trouvaient quoi que ce soit. Le
 * montant, lui, était juste — il vit sur la ligne.
 *
 * L'encaissement d'une location en agence est le geste le plus courant du
 * guichet : chaque règlement produisait une commission illisible.
 */
class CommissionGuichetLocationTest extends TestCase
{
    use DatabaseTransactions;

    private function unApporteur(): Apporteur
    {
        $apporteur = Apporteur::first();

        if (!$apporteur) {
            $this->markTestSkipped('Aucun apporteur.');
        }

        return $apporteur;
    }

    /** Une location dont le client est filleul de l'apporteur. */
    private function uneLocationParrainee(Apporteur $apporteur): Location
    {
        $client = Client::first();

        if (!$client) {
            $this->markTestSkipped('Aucun client.');
        }

        $client->update(['code_parrain' => $apporteur->code, 'parrain_id' => $apporteur->id]);

        return Location::create([
            'numero'        => 'TST-' . uniqid(),
            'client_id'     => $client->id,
            'montant_total' => 120840,
            'etat_location' => \Help::$LOCATION_EN_ATTENTE,
            'statut'        => \Help::$STATUT_ACTIF,
        ]);
    }

    private function crediter(Location $location, float $montantTranche): void
    {
        $controleur = new \App\Http\Controllers\LocationComptantController();
        $methode = (new \ReflectionClass($controleur))->getMethod('crediterApporteur');
        $methode->setAccessible(true);
        $methode->invoke($controleur, $location, $montantTranche);
    }

    /** LA COMMISSION EST RATTACHÉE À SA LOCATION. */
    public function test_la_commission_designe_sa_location(): void
    {
        $apporteur = $this->unApporteur();
        $location  = $this->uneLocationParrainee($apporteur);

        $this->crediter($location, 120840);

        $commission = CommissionApporteur::where('apporteur_id', $apporteur->id)
            ->latest('id')->first();

        $this->assertNotNull($commission, 'Aucune commission n’a été créée.');

        $this->assertSame((int) $location->id, (int) $commission->commande_id,
            'La commission ne désigne pas sa location dans la colonne que '
            . 'l’application lit : elle s’affiche « Filleul non identifié », '
            . 'sans client ni montant d’affaire.');

        $this->assertSame('LOCATION', $commission->type_affaire);
    }

    /** ET L'APPLICATION LA RETROUVE AVEC SON CLIENT. */
    public function test_l_application_retrouve_le_client_de_la_commission(): void
    {
        $apporteur = $this->unApporteur();
        $location  = $this->uneLocationParrainee($apporteur);

        $this->crediter($location, 120840);

        $lignes = \App\Models\CommissionApporteur::where('apporteur_id', $apporteur->id)
            ->latest('id')->limit(1)->get();

        $this->assertCount(1, $lignes);

        // La lecture de l'application, reproduite : la jointure polymorphe.
        $vue = \DB::table('commission_apporteur as ca')
            ->leftJoin('location as l', function ($j) {
                $j->on('l.id', '=', 'ca.commande_id')
                  ->where('ca.type_affaire', '=', 'LOCATION');
            })
            ->where('ca.id', $lignes->first()->id)
            ->selectRaw('ca.id, l.client_id, l.montant_total')
            ->first();

        $this->assertNotNull($vue->client_id,
            'L’application ne retrouve aucun client pour cette commission : '
            . 'c’est exactement la ligne « Filleul non identifié ».');

        $this->assertEqualsWithDelta(120840, (float) $vue->montant_total, 0.01,
            'Le montant de l’affaire reste introuvable.');
    }

    /** UNE SEULE CONVENTION POUR DÉSIGNER L'AFFAIRE. */
    public function test_le_guichet_des_locations_ecrit_la_colonne_lue(): void
    {
        // La règle vit dans App\Services\ReglementValide depuis que l'avance
        // d'un client s'impute aussi sur une location (10/09/2026) : le
        // guichet et l'imputation partagent crediterApporteurLocation().
        $source = file_get_contents(app_path('Services/ReglementValide.php'));
        $source = substr($source, strpos($source, 'function crediterApporteurLocation('));

        // Commentaires retirés d'abord : sinon la fenêtre de lecture se
        // remplit de l'explication du correctif et le code cherché en sort.
        $code  = preg_replace('!^\s*//.*$!m', '', $source);
        $debut = strpos($code, 'CommissionApporteur::create([');
        $bloc  = substr($code, $debut, 400);

        $this->assertStringContainsString("'commande_id'", $bloc,
            'Le guichet des locations n’écrit pas la colonne polymorphe que '
            . 'l’application lit : la commission naîtra sans affaire.');
    }
}
