<?php

namespace Tests\Feature;

use App\Models\Apporteur;
use App\Models\Client;
use App\Models\Commande;
use App\Models\CommissionApporteur;
use App\Models\LignePaiement;
use App\Models\Paiement;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * ON NE COMMISSIONNE QUE CE QUI EST COMMISSIONNABLE — ET ON SAIT SUR QUOI.
 *
 * Constaté sur l'application apporteur les 28/08 puis 01/09/2026 : une ligne
 * « null null (# null) — Total : 0 F — Com. : 4 968 F ». Corrigée deux fois à
 * l'affichage, elle est revenue — parce que la cause était en amont.
 *
 * LA CAUSE : `commission_apporteur.type_affaire` est un
 * enum('LOCATION','VENTE') — il n'a PAS de place pour une demande de
 * livraison. Or `addCommission()` commissionne TOUT règlement, sans regarder
 * le service, puis range le reste en 'VENTE' :
 *
 *     $commission->type_affaire = ($service === LOCATION) ? 'LOCATION' : 'VENTE';
 *
 * Le règlement d'une DEMANDE DE LIVRAISON produisait donc une commission
 * étiquetée VENTE dont `commande_id` porte l'identifiant d'une demande. La
 * lecture va la chercher dans `commande` : soit elle ne trouve rien — la ligne
 * « null null » —, soit elle tombe sur une commande QUI PORTE LE MÊME
 * IDENTIFIANT, et affiche alors le client d'un autre.
 *
 * LA RÈGLE EXISTAIT DÉJÀ, ailleurs : des trois guichets, seuls ceux des VENTES
 * et des LOCATIONS créent une commission. Celui des demandes de livraison n'en
 * crée aucune. Le chemin générique des règlements ne suivait pas cette règle.
 */
class CommissionSurServiceNonCommissionnableTest extends TestCase
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

    private function unClient(): Client
    {
        $client = Client::first();

        if (!$client) {
            $this->markTestSkipped('Aucun client.');
        }

        return $client;
    }

    /** Un règlement et sa ligne, portant le service indiqué. */
    private function unReglement(string $service, int $serviceId, float $montant): LignePaiement
    {
        $client = $this->unClient();

        $paiement = Paiement::create([
            'code'            => 'TST-' . uniqid(),
            'libelle'         => 'Règlement d’essai',
            'client_id'       => $client->id,
            'service'         => $service,
            'service_id'      => $serviceId,
            'montant_total'   => $montant,
            'montant_restant' => 0,
            'statut'          => 1,
        ]);

        return LignePaiement::create([
            'paiement_id'      => $paiement->id,
            'mode_paiement_id' => \DB::table('mode_paiement')->value('id'),
            'date_paiement'    => now()->toDateString(),
            'montant'          => $montant,
            'statut'           => 1,
            'service'          => $service,
            'service_id'       => $serviceId,
        ]);
    }

    /** Appelle la méthode privée du contrôleur, telle que les règlements le font. */
    private function commissionner(Apporteur $apporteur, LignePaiement $ligne): void
    {
        $controleur = new \App\Http\Controllers\PaiementController();
        $methode = (new \ReflectionClass($controleur))->getMethod('addCommission');
        $methode->setAccessible(true);
        $methode->invoke($controleur, $apporteur, $ligne);
    }

    /** UNE DEMANDE DE LIVRAISON NE PRODUIT AUCUNE COMMISSION. */
    public function test_une_demande_de_livraison_ne_cree_pas_de_commission(): void
    {
        $apporteur = $this->unApporteur();
        $avant = CommissionApporteur::where('apporteur_id', $apporteur->id)->count();
        $soldeAvant = (float) $apporteur->fresh()->solde;

        // 165 600 F de transport : l'identifiant de la demande n'a rien à faire
        // dans une colonne qui désigne une commande ou une location.
        $this->commissionner($apporteur, $this->unReglement(\Help::$LIVRAISON, 999123, 165600));

        $this->assertSame($avant,
            CommissionApporteur::where('apporteur_id', $apporteur->id)->count(),
            'Une commission a été créée sur une DEMANDE DE LIVRAISON : son '
            . 'type ne peut être qu’VENTE ou LOCATION, elle pointe donc sur '
            . 'une affaire qui n’existe pas — et le guichet des livraisons, '
            . 'lui, n’en crée aucune.');

        $this->assertEqualsWithDelta($soldeAvant, (float) $apporteur->fresh()->solde, 0.01,
            'Le solde de l’apporteur a été crédité d’une somme qu’aucune '
            . 'affaire ne justifie.');
    }

    /** UNE VENTE, ELLE, EST BIEN COMMISSIONNÉE — ET RETROUVE SON CLIENT. */
    public function test_une_vente_est_commissionnee_et_retrouve_son_client(): void
    {
        $apporteur = $this->unApporteur();

        $commande = Commande::whereNotNull('client_id')->first();

        if (!$commande) {
            $this->markTestSkipped('Aucune commande.');
        }

        $avant = CommissionApporteur::where('apporteur_id', $apporteur->id)->count();

        $this->commissionner($apporteur,
            $this->unReglement(\Help::$COMMANDE, $commande->id, 100000));

        $this->assertSame($avant + 1,
            CommissionApporteur::where('apporteur_id', $apporteur->id)->count(),
            'Le cas courant est cassé : une vente ne commissionne plus.');

        $creee = CommissionApporteur::where('apporteur_id', $apporteur->id)
            ->latest('id')->first();

        $this->assertSame('VENTE', $creee->type_affaire);
        $this->assertSame($commande->id, (int) $creee->commande_id,
            'La commission ne pointe pas sur la commande réglée.');
    }

    /** AUCUNE COMMISSION NE RESTE ORPHELINE. */
    public function test_aucune_commission_creee_ne_reste_sans_affaire(): void
    {
        $apporteur = $this->unApporteur();

        // Un service commissionnable, mais dont l'affaire est introuvable.
        $this->commissionner($apporteur,
            $this->unReglement(\Help::$COMMANDE, 999124, 80000));

        $orphelines = CommissionApporteur::where('apporteur_id', $apporteur->id)
            ->where('type_affaire', 'VENTE')
            ->whereNotExists(function ($q) {
                $q->selectRaw(1)->from('commande')
                  ->whereColumn('commande.id', 'commission_apporteur.commande_id');
            })->count();

        $this->assertSame(0, $orphelines,
            "$orphelines commission(s) pointent sur une commande inexistante : "
            . 'elles s’affichent sans client sur le téléphone de l’apporteur, '
            . 'et créditent son solde sans justification traçable.');
    }
    /**
     * LES TROIS CHEMINS DE RÈGLEMENT LISENT LA MÊME RÈGLE.
     *
     * Le règlement en agence, le règlement en ligne et le règlement mobile
     * créent chacun des commissions. Chacun rangeait de son côté « tout ce qui
     * n'est pas une location » en VENTE : la faute était donc à trois endroits,
     * et se serait recorrigée trois fois.
     */
    public function test_les_trois_chemins_partagent_la_regle(): void
    {
        $sources = [
            app_path('Http/Controllers/PaiementController.php'),
            app_path('Http/Controllers/PaiementEnLigne.php'),
            base_path('../apigravier/app/Http/Controllers/PaiementController.php'),
        ];

        foreach ($sources as $chemin) {
            if (!is_file($chemin)) {
                $this->markTestSkipped("Source absente : $chemin");
            }

            $code = file_get_contents($chemin);

            $this->assertStringContainsString(
                'AffaireCommissionnable::typeSiRattachable', $code,
                basename($chemin) . ' décide seul du type de commission : la '
                . 'règle finira par diverger d’un chemin à l’autre.');

            $this->assertStringNotContainsString(
                "? 'LOCATION' : 'VENTE'", $code,
                basename($chemin) . ' range encore en VENTE tout ce qui n’est '
                . 'pas une location — demande de livraison comprise.');
        }
    }
    /**
     * LE CAS DANGEREUX : UN IDENTIFIANT QUI EXISTE DES DEUX CÔTÉS.
     *
     * `demande_livraison` et `commande` ont chacune leur compteur : la demande
     * n° 12 et la commande n° 12 coexistent. Commissionner une demande en la
     * rangeant en VENTE ne donne alors PAS une ligne vide — elle affiche le
     * client de la COMMANDE n° 12, qui n'a rien à voir. L'apporteur croit
     * toucher sur une affaire qui n'est pas la sienne.
     *
     * Le contrôle d'existence ne peut rien contre ça : l'affaire existe. Seul
     * le refus du SERVICE protège.
     */
    public function test_une_livraison_ne_devient_pas_la_vente_de_meme_numero(): void
    {
        $apporteur = $this->unApporteur();

        // On vise l'identifiant d'une commande RÉELLE, comme le ferait une
        // demande de livraison portant ce même numéro.
        $commande = Commande::whereNotNull('client_id')->first();

        if (!$commande) {
            $this->markTestSkipped('Aucune commande.');
        }

        $avant = CommissionApporteur::where('apporteur_id', $apporteur->id)->count();

        $this->commissionner($apporteur,
            $this->unReglement(\Help::$LIVRAISON, $commande->id, 165600));

        $this->assertSame($avant,
            CommissionApporteur::where('apporteur_id', $apporteur->id)->count(),
            'Une demande de livraison a été commissionnée sous le numéro d’une '
            . 'VENTE : l’apporteur voit le client de cette vente, et le suivi '
            . 'des commissions désigne la mauvaise affaire.');
    }

    /** LA RÈGLE ELLE-MÊME : CE QUI SE COMMISSIONNE, ET CE QUI NE SE COMMISSIONNE PAS. */
    public function test_seuls_la_vente_et_la_location_se_commissionnent(): void
    {
        $regle = \App\Support\AffaireCommissionnable::class;

        $this->assertSame('VENTE', $regle::type(\Help::$COMMANDE));
        $this->assertSame('LOCATION', $regle::type(\Help::$LOCATION));

        $this->assertNull($regle::type(\Help::$LIVRAISON),
            'Une demande de livraison ne se commissionne pas : le guichet des '
            . 'livraisons n’en crée aucune, et l’enum n’a pas de place pour elle.');

        $this->assertNull($regle::type(null),
            'Un service inconnu ne doit pas retomber sur VENTE par défaut.');
    }
}
