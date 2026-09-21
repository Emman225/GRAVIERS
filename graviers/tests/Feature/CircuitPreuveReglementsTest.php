<?php

namespace Tests\Feature;

use App\Models\Apporteur;
use App\Models\Client;
use App\Models\DemandePaiement;
use App\Models\Fournisseur;
use App\Models\Livreur;
use App\Models\Paiement;
use App\Models\PaiementApporteur;
use App\Models\PaiementFournisseur;
use App\Models\PaiementLivreur;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * POINT 20 SUR TOUS LES RÈGLEMENTS ENREGISTRÉS AU BACK-OFFICE (09/09/2026) :
 * dettes des fournisseurs, livreurs et apporteurs, créances des clients à
 * terme, encaissements en agence des clients comptant.
 *
 * Quand le 2e validateur finit, le règlement est « À payer » ; une icône
 * téléverse la preuve ; une fois jointe, une icône finalise : « Effectuée ».
 * Sans preuve, la finalisation est refusée. Le partenaire et le client voient
 * l'état dans leur espace.
 */
class CircuitPreuveReglementsTest extends TestCase
{
    use DatabaseTransactions;

    /** La cellule Action de la ligne du règlement : entre ses formulaires (…/{id}/…) et la fin de la ligne. */
    private function celluleAction(string $html, int $id): string
    {
        // La ligne se repère par son marqueur data-reglement (cellule État), présent
        // quel que soit l'administrateur connecté ; la cellule Action la suit.
        $p = strpos($html, 'data-reglement="' . $id . '"');
        $this->assertNotFalse($p, 'La ligne du règlement est introuvable sur la page.');
        $fin = strpos($html, '</tr>', $p);

        return substr($html, $p, ($fin === false ? $p + 3000 : $fin) - $p);
    }

    /**
     * Trois administrateurs distincts : le 1er validateur, le 2e, et le
     * TROISIÈME qui téléverse la preuve et finalise (sécurité, 09/09/2026).
     */
    private function deuxAdmins(): array
    {
        $admins = User::whereIn('type_user_id', [1, 2])->where('statut', 1)->orderBy('id')->limit(3)->get();
        if ($admins->count() < 3) {
            $this->markTestSkipped('Il faut trois administrateurs.');
        }

        return [$admins[0], $admins[1], $admins[2]];
    }

    /**
     * Joue le circuit complet sur un règlement créé « en attente de la 2e
     * validation » : validation → À payer ; finaliser sans preuve → refus ;
     * preuve → preuve jointe ; finaliser → Effectuée.
     */
    private function jouerLeCircuit($paiement, string $base, string $pageAdmin, string $libelle): void
    {
        [$premier, $second, $troisieme] = $this->deuxAdmins();
        Storage::fake('public');
        Auth::guard('web')->login($second);

        // 2e validation : « À payer ».
        $this->post(route($base . '.valider', $paiement->id))->assertRedirect();
        $paiement->refresh();
        $this->assertSame(1, (int) $paiement->statut, "{$libelle} : la 2e validation doit activer le règlement.");
        $this->assertSame(DemandePaiement::A_PAYER, $paiement->etat_reglement, "{$libelle} : après la 2e validation, le règlement doit être « À payer ».");

        // La page du guichet montre l'état et l'icône de téléversement.
        $html = $this->get($pageAdmin)->assertOk()->getContent();
        $this->assertStringContainsString('>À payer<', $html, "{$libelle} : la colonne État doit afficher « À payer ».");
        $this->assertStringContainsString('js-joindre-preuve-reglement', $html, "{$libelle} : l'icône de téléversement manque.");
        $this->assertStringContainsString('id="modalPreuveReglement"', $html, "{$libelle} : la fenêtre de téléversement manque.");
        // Avant la preuve, ni « Voir reçu » ni « PDF » sur cette ligne (09/09/2026).
        $cellule = $this->celluleAction($html, $paiement->id);
        $this->assertStringNotContainsString('md-receipt', $cellule, "{$libelle} : le reçu ne doit pas être visible avant la preuve.");
        $this->assertStringNotContainsString('md-picture_as_pdf', $cellule, "{$libelle} : le PDF ne doit pas être visible avant la preuve.");

        // SÉCURITÉ : le 2e validateur ne peut ni téléverser ni finaliser — c'est à
        // un troisième administrateur. La page le lui dit, sans icône.
        $cellule = $this->celluleAction($html, $paiement->id);
        $this->assertStringNotContainsString('js-joindre-preuve-reglement', $cellule, "{$libelle} : le 2e validateur ne doit pas voir l'icône de téléversement.");
        $this->assertStringContainsString('un troisième administrateur', $cellule, "{$libelle} : le validateur doit savoir qu'un troisième administrateur est attendu.");
        $this->post(route($base . '.preuve', $paiement->id), ['preuve' => UploadedFile::fake()->create('virement.pdf', 20, 'application/pdf')])
            ->assertRedirect();
        $this->assertSame(DemandePaiement::A_PAYER, $paiement->fresh()->etat_reglement, "{$libelle} : le 2e validateur ne doit pas pouvoir téléverser la preuve.");
        $this->assertEmpty($paiement->fresh()->preuve_paiement);

        // Le troisième administrateur prend la suite.
        Auth::guard('web')->login($troisieme);
        $html = $this->get($pageAdmin)->assertOk()->getContent();
        $this->assertStringContainsString('js-joindre-preuve-reglement', $this->celluleAction($html, $paiement->id), "{$libelle} : le troisième administrateur doit voir l'icône de téléversement.");

        // Finaliser sans preuve : refusé, l'état ne bouge pas.
        $this->post(route($base . '.effectuer', $paiement->id))->assertRedirect();
        $this->assertSame(DemandePaiement::A_PAYER, $paiement->fresh()->etat_reglement, "{$libelle} : finaliser sans preuve doit être refusé.");

        // La preuve : téléversée, rangée, état « preuve jointe ».
        $this->post(route($base . '.preuve', $paiement->id), ['preuve' => UploadedFile::fake()->create('virement.pdf', 20, 'application/pdf')])
            ->assertRedirect();
        $paiement->refresh();
        $this->assertSame(DemandePaiement::PREUVE_JOINTE, $paiement->etat_reglement, "{$libelle} : la preuve jointe doit changer l'état.");
        $this->assertNotEmpty($paiement->preuve_paiement);
        Storage::disk('public')->assertExists($paiement->preuve_paiement);
        $this->assertSame($troisieme->id, (int) $paiement->user_preuve_id);
        $this->get(route($base . '.voirPreuve', $paiement->id))->assertOk();

        $html = $this->get($pageAdmin)->assertOk()->getContent();
        $this->assertStringContainsString('md-task_alt', $html, "{$libelle} : l'icône de finalisation manque une fois la preuve jointe.");
        // La confirmation de « Finaliser » passe par SweetAlert2 (js-delete-form + data-confirm-*), pas par confirm() (09/09/2026).
        $cellule = $this->celluleAction($html, $paiement->id);
        $this->assertStringContainsString('js-delete-form', substr($cellule, strpos($cellule, '/effectuer') - 400, 800), "{$libelle} : la finalisation doit se confirmer en SweetAlert2.");
        $this->assertStringNotContainsString('onsubmit="return confirm(', $cellule, "{$libelle} : plus de confirm() natif.");
        // Preuve jointe mais pas finalisé : toujours ni reçu ni PDF (09/09/2026).
        $cellule = $this->celluleAction($html, $paiement->id);
        $this->assertStringNotContainsString('md-receipt', $cellule, "{$libelle} : le reçu ne doit apparaître qu'après la finalisation.");
        $this->assertStringNotContainsString('md-picture_as_pdf', $cellule, "{$libelle} : le PDF ne doit apparaître qu'après la finalisation.");

        // Finalisation : « Effectuée ».
        $this->post(route($base . '.effectuer', $paiement->id))->assertRedirect();
        $paiement->refresh();
        $this->assertSame(DemandePaiement::EFFECTUEE, $paiement->etat_reglement, "{$libelle} : la finalisation doit rendre le règlement « Effectuée ».");
        $this->assertNotNull($paiement->date_effectuee);
        $this->assertSame($troisieme->id, (int) $paiement->user_effectuee_id);
        $html = $this->get($pageAdmin)->assertOk()->getContent();
        $this->assertStringContainsString('>Effectuée<', $html);
        // Finalisé : le reçu et le PDF apparaissent enfin. La ligne n'a plus de
        // formulaire de preuve : on la repère par son reçu.
        $this->assertStringContainsString('/' . $paiement->id . '/pdf', $html, "{$libelle} : le PDF doit apparaître une fois le règlement effectué.");
        $this->assertStringContainsString('md-receipt', $html, "{$libelle} : le reçu doit apparaître une fois le règlement effectué.");
        // La colonne « 3e validateur » porte le nom de l'administrateur qui a finalisé.
        $ligne = substr($html, strpos($html, 'data-reglement="' . $paiement->id . '"') - 400, 900);
        $this->assertStringContainsString($troisieme->nom_prenoms, $ligne, "{$libelle} : le nom du troisième administrateur doit figurer sur la ligne.");
        $this->assertStringContainsString('3e validateur', $html, "{$libelle} : l'en-tête « 3e validateur » manque.");
    }

    public function test_le_reglement_d_un_fournisseur_suit_le_circuit_et_le_fournisseur_le_voit(): void
    {
        [$premier] = $this->deuxAdmins();
        $fournisseur = Fournisseur::whereHas('user', fn ($q) => $q->where('statut', 1))->first();
        if (!$fournisseur) {
            $this->markTestSkipped('Aucun fournisseur avec un compte.');
        }
        $p = PaiementFournisseur::create([
            'date_paiement' => now(), 'fournisseur_id' => $fournisseur->id, 'montant' => 12500, 'mode_paiement_id' => 1,
            'enlevement_id' => (\App\Models\Enlevement::where('fournisseur_id', $fournisseur->id)->value('id') ?? \App\Models\Enlevement::value('id') ?? 0),
            'reference' => 'REC-F-20', 'notes' => 'Recette point 20', 'user_id' => $premier->id, 'statut' => 2,
            'user_valide_id' => $premier->id, 'date_validation_1' => now(),
        ]);
        $this->jouerLeCircuit($p, 'show.fournisseurs.paiements', '/fournisseurs/paiements', 'Fournisseur');

        Auth::guard('web')->login($fournisseur->user);
        $this->assertStringContainsString('Effectuée', $this->get('/liste-des-demande-de-paiement')->assertOk()->getContent(),
            "L'espace du fournisseur doit marquer l'opération effectuée.");
    }

    public function test_le_reglement_d_un_livreur_suit_le_circuit(): void
    {
        [$premier] = $this->deuxAdmins();
        $livreur = Livreur::whereHas('user')->first();
        if (!$livreur) {
            $this->markTestSkipped('Aucun livreur.');
        }
        $p = PaiementLivreur::create([
            'date_paiement' => now(), 'livreur_id' => $livreur->id, 'montant' => 8000, 'mode_paiement_id' => 1,
            'livraison_id' => (\App\Models\Livraison::where('livreur_id', $livreur->id)->value('id') ?? \App\Models\Livraison::value('id') ?? 0),
            'reference' => 'REC-L-20', 'user_id' => $premier->id, 'statut' => 2, 'user_valide_id' => $premier->id, 'date_validation_1' => now(),
        ]);
        $this->jouerLeCircuit($p, 'show.livreurs.paiements', '/livreurs/paiements', 'Livreur');
    }

    public function test_le_reglement_d_un_apporteur_suit_le_circuit(): void
    {
        [$premier] = $this->deuxAdmins();
        $apporteur = Apporteur::whereHas('user')->first();
        if (!$apporteur) {
            $this->markTestSkipped('Aucun apporteur.');
        }
        $p = PaiementApporteur::create([
            'date_paiement' => now(), 'apporteur_id' => $apporteur->id, 'montant' => 5000, 'mode_paiement_id' => 1,
            'commission_id' => (\App\Models\CommissionApporteur::where('apporteur_id', $apporteur->id)->value('id') ?? \App\Models\CommissionApporteur::value('id') ?? 0),
            'reference' => 'REC-A-20', 'user_id' => $premier->id, 'statut' => 2, 'user_valide_id' => $premier->id, 'date_validation_1' => now(),
        ]);
        $this->jouerLeCircuit($p, 'show.apporteurs.paiements', '/apporteurs/paiements', 'Apporteur');
    }

    public function test_le_reglement_d_une_creance_client_suit_le_circuit(): void
    {
        [$premier] = $this->deuxAdmins();
        $client = Client::where('client_a_terme', 1)->where('statut', 1)->first();
        if (!$client) {
            $this->markTestSkipped('Aucun client à terme.');
        }
        $p = Paiement::create([
            'client_id' => $client->id, 'code' => 'CR-' . substr((string) time(), -6), 'libelle' => 'Recette point 20',
            'montant_total' => 30000, 'montant_restant' => 0, 'statut' => 2, 'service' => 'COMMANDE',
            'caissier_id' => $premier->id, 'numero_recu' => 'RC-' . substr((string) time(), -6),
            'user_valide_id' => $premier->id, 'date_validation_1' => now(),
        ]);
        $this->jouerLeCircuit($p, 'show.creancesTerme.paiements', '/clients-terme/paiements', 'Créance client');
    }

    public function test_l_encaissement_en_agence_d_un_client_suit_le_circuit(): void
    {
        [$premier] = $this->deuxAdmins();
        // Un client COMPTANT : le guichet des encaissements ne liste que ceux-là,
        // encaissés en agence par un caissier.
        $client = Client::where('statut', 1)->where(fn ($q) => $q->where('client_a_terme', 0)->orWhereNull('client_a_terme'))->whereHas('user')->first();
        if (!$client) {
            $this->markTestSkipped('Aucun client comptant.');
        }
        $p = Paiement::create([
            'client_id' => $client->id, 'code' => 'EA-' . substr((string) time(), -6), 'libelle' => 'Recette point 20',
            'montant_total' => 15000, 'montant_restant' => 0, 'statut' => 2, 'service' => 'COMMANDE',
            'caissier_id' => $premier->id, 'agence_id' => \App\Models\Agence::value('id'), 'numero_recu' => 'EA-' . substr((string) time(), -6),
            'user_valide_id' => $premier->id, 'date_validation_1' => now(),
        ]);
        $this->jouerLeCircuit($p, 'show.comptant.encaissements', '/comptant/encaissements', 'Encaissement en agence');
    }

    /** Le guichet des LOCATIONS suit le même circuit (constaté manquant le 10/09/2026). */
    public function test_l_encaissement_d_une_location_suit_le_circuit(): void
    {
        [$premier] = $this->deuxAdmins();
        $location = \App\Models\Location::whereHas('client')->first();
        if (!$location) {
            $this->markTestSkipped('Aucune location.');
        }
        $p = Paiement::create([
            'client_id' => $location->client_id, 'code' => 'EL-' . substr((string) time(), -6), 'libelle' => 'Recette point 20 location',
            'montant_total' => 15000, 'montant_restant' => 0, 'statut' => 2, 'service' => 'LOCATION', 'service_id' => $location->id,
            'caissier_id' => $premier->id, 'agence_id' => \App\Models\Agence::value('id'), 'numero_recu' => 'EL-' . substr((string) time(), -6),
            'user_valide_id' => $premier->id, 'date_validation_1' => now(),
        ]);
        $this->jouerLeCircuit($p, 'show.encaissements.locations', '/encaissements/locations', 'Encaissement de location');
    }

    /** Le guichet des DEMANDES DE LIVRAISON suit le même circuit (constaté manquant le 10/09/2026). */
    public function test_l_encaissement_d_une_demande_de_livraison_suit_le_circuit(): void
    {
        [$premier] = $this->deuxAdmins();
        $demande = \App\Models\DemandeLivraison::whereHas('client')->first();
        if (!$demande) {
            $client = Client::where('statut', 1)->whereHas('user')->first();
            if (!$client) {
                $this->markTestSkipped('Aucun client.');
            }
            $demande = \App\Models\DemandeLivraison::create([
                'numero' => 'T' . random_int(100000, 999999), 'client_id' => $client->id, 'montantTotal' => 20000,
                'etat_commande' => \Help::$COMMANDE_EN_ATTENTE, 'statut' => \Help::$STATUT_ACTIF,
            ]);
        }
        $p = Paiement::create([
            'client_id' => $demande->client_id, 'code' => 'ED-' . substr((string) time(), -6), 'libelle' => 'Recette point 20 livraison',
            'montant_total' => 15000, 'montant_restant' => 0, 'statut' => 2, 'service' => 'LIVRAISON', 'service_id' => $demande->id,
            'caissier_id' => $premier->id, 'agence_id' => \App\Models\Agence::value('id'), 'numero_recu' => 'ED-' . substr((string) time(), -6),
            'user_valide_id' => $premier->id, 'date_validation_1' => now(),
        ]);
        $this->jouerLeCircuit($p, 'show.comptant.livraisons.encaissements', '/comptant/livraisons/encaissements', 'Encaissement de livraison');
    }

    public function test_le_libelle_du_circuit(): void
    {
        $p = new PaiementFournisseur(['statut' => 2]);
        $this->assertSame('En attente de validation', $p->libelleReglement());
        $p = new PaiementFournisseur(['statut' => 1]);
        $this->assertSame('Payé', $p->libelleReglement(), 'Un règlement d\'avant le circuit reste « Payé ».');
        $p = new PaiementFournisseur(['statut' => 1, 'etat_reglement' => DemandePaiement::A_PAYER]);
        $this->assertSame('À payer', $p->libelleReglement());
        $p = new PaiementFournisseur(['statut' => 1, 'etat_reglement' => DemandePaiement::EFFECTUEE]);
        $this->assertSame('Effectuée', $p->libelleReglement());
    }
}
