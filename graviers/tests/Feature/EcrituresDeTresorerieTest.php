<?php

namespace Tests\Feature;

use App\Models\AnomalieComptable;
use App\Models\Apporteur;
use App\Models\AvanceClient;
use App\Models\Commande;
use App\Models\CompteComptable;
use App\Models\DemandePaiement;
use App\Models\EcritureComptable;
use App\Models\Enlevement;
use App\Models\Fournisseur;
use App\Models\JournalComptable;
use App\Models\LignePaiement;
use App\Models\Livraison;
use App\Models\Livreur;
use App\Models\Location;
use App\Models\ModePaiement;
use App\Models\Paiement;
use App\Models\PaiementFournisseur;
use App\Models\PaiementLivreur;
use App\Models\RubriqueComptable;
use App\Models\User;
use App\Services\Comptabilite\Ecrivain;
use App\Services\Comptabilite\MoteurTresorerie;
use App\Services\Comptabilite\ParametrageComptable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Module « Écritures comptables », phase 2b : la trésorerie (lot 118, 21/09/2026).
 *
 * Réponses du responsable : chaque moyen de paiement a son journal ; livreurs
 * et apporteurs ont des comptes fournisseurs dédiés dont la contrepartie est
 * une charge ; les cautions se suivent dans un compte de dépôts.
 */
class EcrituresDeTresorerieTest extends TestCase
{
    use DatabaseTransactions;

    private Commande $commande;
    private ModePaiement $especes;
    private ModePaiement $mobile;

    protected function setUp(): void
    {
        parent::setUp();
        URL::forceRootUrl('');
        DB::table('configuration')->update(['longueur_compte_comptable' => 6]);
        DB::table('compte_comptable')->delete();
        DB::table('rubrique_comptable')->delete();
        DB::table('mode_paiement')->update(['journal_comptable_id' => null]);

        $commande = Commande::whereHas('client')->first();
        $especes = ModePaiement::where('en_ligne', 0)->where('id', '!=', 1)->where('statut', \Help::$STATUT_ACTIF)->first();
        $mobile = ModePaiement::where('en_ligne', 1)->where('statut', \Help::$STATUT_ACTIF)->first();
        if (!$commande || !$especes || !$mobile) {
            $this->markTestSkipped('Il faut une commande, un mode de règlement au guichet et un mode en ligne.');
        }
        [$this->commande, $this->especes, $this->mobile] = [$commande, $especes, $mobile];
        DB::table('client')->where('id', $commande->client_id)->update(['compte_tiers' => '411RECETTE']);

        // Le paramétrage de la trésorerie : caisse, Mobile Money, banque, et les rubriques.
        $this->journal('CAISSE', '571000', $especes);
        $this->journal('MOBILE_MONEY', '552000', $mobile);
        $this->journal('BANQUE', '521000');
        foreach ([
            RubriqueComptable::CLIENTS => '411000', RubriqueComptable::AVANCES_CLIENTS => '419100', RubriqueComptable::FOURNISSEURS => '401100',
            RubriqueComptable::LIVREURS => '401200', RubriqueComptable::CHARGES_LIVREURS => '613000', RubriqueComptable::APPORTEURS => '401300',
            RubriqueComptable::CHARGES_APPORTEURS => '632000', RubriqueComptable::CAUTIONS => '165000', RubriqueComptable::CAUTIONS_RETENUES => '758000',
        ] as $code => $numero) {
            RubriqueComptable::pour($code)->update(['compte_comptable_id' => $this->compte($numero)->id]);
        }
    }

    // ------------------------------------------------------------------ montage

    private function compte(string $numero, string $nature = 'GENERAL'): CompteComptable
    {
        return CompteComptable::create(['nature' => $nature, 'numero' => $numero, 'libelle' => 'Recette ' . $numero, 'statut' => 1]);
    }

    private function journal(string $type, string $numeroCompte, ?ModePaiement $mode = null): JournalComptable
    {
        $journal = JournalComptable::where('type', $type)->first()
            ?: JournalComptable::create(['code' => substr($type, 0, 2), 'libelle' => $type, 'type' => $type, 'statut' => 1]);
        $journal->update(['statut' => 1, 'compte_comptable_id' => $this->compte($numeroCompte)->id]);
        if ($mode) {
            DB::table('mode_paiement')->where('id', $mode->id)->update(['journal_comptable_id' => $journal->id]);
        }

        return $journal;
    }

    /** Un règlement de guichet, en attente de la seconde validation, avec ses lignes. */
    private function unReglement(array $lignes, array $entete = []): Paiement
    {
        return Model::unguarded(function () use ($lignes, $entete) {
            $paiement = Paiement::create(array_merge([
                'client_id' => $this->commande->client_id, 'code' => 'PAY-' . substr(uniqid(), -8), 'libelle' => 'Règlement de recette',
                'montant_total' => array_sum(array_column($lignes, 'montant')), 'montant_restant' => 0, 'statut' => 2,
                'service' => \Help::$COMMANDE, 'service_id' => $this->commande->id, 'numero_recu' => 'RC-2026-' . random_int(100, 999),
            ], $entete));
            foreach ($lignes as $ligne) {
                LignePaiement::create(array_merge([
                    'paiement_id' => $paiement->id, 'statut' => 2, 'date_paiement' => '2026-09-15 11:00:00',
                    'service' => \Help::$COMMANDE, 'service_id' => $this->commande->id, 'user_id' => User::value('id'),
                ], $ligne));
            }

            return $paiement;
        });
    }

    /** La seconde validation, telle que les quatre guichets la font : lignes en masse, puis le règlement. */
    private function validerAuGuichet(Paiement $paiement): void
    {
        LignePaiement::where('paiement_id', $paiement->id)->update(['statut' => 1]);
        $paiement->update(['statut' => 1, 'etat_reglement' => DemandePaiement::A_PAYER]);
    }

    private function ecrituresDe(string $sourceType, $ids)
    {
        return EcritureComptable::where('source_type', $sourceType)->whereIn('source_id', (array) $ids)->with('lignes')->orderBy('id')->get();
    }

    // ------------------------------------------------------------------ encaissements

    public function test_un_reglement_valide_au_guichet_ecrit_dans_le_journal_de_son_moyen_de_paiement(): void
    {
        $paiement = $this->unReglement([
            ['mode_paiement_id' => $this->especes->id, 'moyen_paiement' => $this->especes->libelle, 'montant' => 6000],
            ['mode_paiement_id' => $this->mobile->id, 'moyen_paiement' => $this->mobile->libelle, 'montant' => 4000, 'reference' => 'TX-778'],
        ]);
        $ids = LignePaiement::where('paiement_id', $paiement->id)->pluck('id')->all();
        $this->assertCount(0, $this->ecrituresDe('ligne_paiement', $ids), 'En attente de la seconde validation : rien.');

        $this->validerAuGuichet($paiement);
        $ecritures = $this->ecrituresDe('ligne_paiement', $ids);
        $this->assertCount(2, $ecritures, 'Une écriture par ligne de règlement : chaque moyen de paiement a son journal.');

        [$caisse, $mobile] = [$ecritures[0], $ecritures[1]];
        $this->assertSame('ENC-' . $ids[0], $caisse->identifiant);
        $this->assertSame(MoteurTresorerie::ENCAISSEMENT, $caisse->origine);
        $this->assertSame(EcritureComptable::ETAT_A_EXPORTER, $caisse->etat, $caisse->anomalies->pluck('cause')->implode(' | '));
        $this->assertSame('CAISSE', $caisse->journal->type);
        $this->assertSame('MOBILE_MONEY', $mobile->journal->type);
        $this->assertSame('2026-09-15', $caisse->date_ecriture->toDateString(), 'Date de l\'écriture = date du règlement.');
        $this->assertSame($paiement->numero_recu, $caisse->piece);
        $this->assertSame((string) $this->commande->numero, $caisse->numero_affaire);

        $this->assertSame(['TRESORERIE', 'CLIENT'], $caisse->lignes->pluck('rubrique')->all());
        $this->assertSame('571000', $caisse->lignes[0]->numero_compte);
        $this->assertEqualsWithDelta(6000, $caisse->lignes[0]->debit, 0.001, 'La trésorerie est débitée.');
        $this->assertSame('411000', $caisse->lignes[1]->numero_compte);
        $this->assertSame('411RECETTE', $caisse->lignes[1]->compte_tiers);
        $this->assertEqualsWithDelta(6000, $caisse->lignes[1]->credit, 0.001, 'Le compte tiers du client est crédité.');
        $this->assertSame('552000', $mobile->lignes[0]->numero_compte);
        $this->assertStringContainsString('TX-778', $mobile->lignes[0]->libelle);
        $this->assertTrue($caisse->estEquilibree() && $mobile->estEquilibree());

        // Rejoué (reprise), rien ne double.
        MoteurTresorerie::toutProduire('2026-09-15', '2026-09-15');
        $this->assertCount(2, $this->ecrituresDe('ligne_paiement', $ids));
    }

    public function test_un_paiement_en_ligne_confirme_ecrit_sans_double_validation(): void
    {
        $paiement = $this->unReglement([['mode_paiement_id' => $this->mobile->id, 'montant' => 9000]], ['numero_recu' => null]);
        $ligne = LignePaiement::where('paiement_id', $paiement->id)->first();

        // Le retour de la passerelle : la ligne passe à 1, enregistrée par le modèle.
        $ligne->reference = 'PAYSECURE-1';
        $ligne->moyen_paiement = 'Wave';
        $ligne->statut = 1;
        $ligne->save();

        $ecriture = $this->ecrituresDe('ligne_paiement', $ligne->id)->first();
        $this->assertNotNull($ecriture);
        $this->assertSame('MOBILE_MONEY', $ecriture->journal->type);
        $this->assertSame($paiement->code, $ecriture->piece, 'Sans reçu de guichet, la pièce est le code du paiement.');
        $this->assertEqualsWithDelta(9000, $ecriture->total_debit, 0.001);
    }

    public function test_un_mode_sans_journal_met_l_ecriture_en_anomalie_et_la_reprise_la_regle(): void
    {
        DB::table('mode_paiement')->where('id', $this->especes->id)->update(['journal_comptable_id' => null]);
        $paiement = $this->unReglement([['mode_paiement_id' => $this->especes->id, 'montant' => 5000]]);
        $this->validerAuGuichet($paiement);
        $ligne = LignePaiement::where('paiement_id', $paiement->id)->first();

        $ecriture = $this->ecrituresDe('ligne_paiement', $ligne->id)->first();
        $this->assertSame(EcritureComptable::ETAT_ANOMALIE, $ecriture->etat);
        $anomalie = $ecriture->anomaliesOuvertes()->where('code', AnomalieComptable::MODE_SANS_JOURNAL)->first();
        $this->assertNotNull($anomalie);
        $this->assertSame('journaux', $anomalie->onglet);
        $this->assertStringContainsString($this->especes->libelle, $anomalie->objet);

        $caisse = JournalComptable::where('type', 'CAISSE')->first();
        DB::table('mode_paiement')->where('id', $this->especes->id)->update(['journal_comptable_id' => $caisse->id]);
        $bilan = MoteurTresorerie::reprendreLesAnomalies();

        $this->assertGreaterThanOrEqual(1, $bilan['reglees']);
        $this->assertSame(EcritureComptable::ETAT_A_EXPORTER, $ecriture->fresh()->etat);
        $this->assertSame($ecriture->id, $this->ecrituresDe('ligne_paiement', $ligne->id)->first()->id);
    }

    public function test_un_reglement_qui_n_est_plus_valable_perd_son_ecriture_non_exportee(): void
    {
        $paiement = $this->unReglement([['mode_paiement_id' => $this->especes->id, 'montant' => 5000]]);
        $this->validerAuGuichet($paiement);
        $ligne = LignePaiement::where('paiement_id', $paiement->id)->first();
        $this->assertCount(1, $this->ecrituresDe('ligne_paiement', $ligne->id));

        $ligne->statut = 3;
        $ligne->save();
        $this->assertCount(0, $this->ecrituresDe('ligne_paiement', $ligne->id));
    }

    // ------------------------------------------------------------------ avances

    public function test_le_depot_d_une_avance_est_l_encaissement_son_imputation_ne_l_est_pas(): void
    {
        $avance = Model::unguarded(fn () => AvanceClient::create([
            'client_id' => $this->commande->client_id, 'montant' => 20000, 'montant_consomme' => 0, 'statut' => AvanceClient::EN_ATTENTE,
            'numero_recu' => 'RA-2026-901', 'mode_paiement_id' => $this->especes->id, 'moyen_paiement' => $this->especes->libelle,
            'origine' => 'DEPOT', 'date_depot' => '2026-09-12 09:00:00',
        ]));
        $this->assertCount(0, $this->ecrituresDe('avance_client', $avance->id), 'Un dépôt en attente de validation n\'est pas de l\'argent.');

        $avance->update(['statut' => AvanceClient::DISPONIBLE]);
        $depot = $this->ecrituresDe('avance_client', $avance->id)->first();
        $this->assertSame(MoteurTresorerie::AVANCE_DEPOT, $depot->origine);
        $this->assertSame('CAISSE', $depot->journal->type);
        $this->assertSame('2026-09-12', $depot->date_ecriture->toDateString());
        $this->assertSame('571000', $depot->lignes[0]->numero_compte);
        $this->assertEqualsWithDelta(20000, $depot->lignes[0]->debit, 0.001);
        $this->assertSame('419100', $depot->lignes[1]->numero_compte, 'Le dépôt crédite le compte d\'avances, pas le compte client.');
        $this->assertSame('411RECETTE', $depot->lignes[1]->compte_tiers);

        // L'imputation, telle que le service Avances l'enregistre : déjà validée, au mode du dépôt d'origine.
        $imputation = $this->unReglement(
            [['mode_paiement_id' => $this->especes->id, 'moyen_paiement' => 'Avance client (reçu RA-2026-901)', 'montant' => 8000, 'statut' => 1, 'reference' => 'RA-2026-901']],
            ['code' => 'PAV-' . substr(uniqid(), -8), 'numero_recu' => 'AV-2026-901', 'statut' => 1]
        );
        $ligne = LignePaiement::where('paiement_id', $imputation->id)->first();
        $ecriture = $this->ecrituresDe('ligne_paiement', $ligne->id)->first();

        $this->assertNotNull($ecriture);
        $this->assertSame('AVI-' . $ligne->id, $ecriture->identifiant);
        $this->assertSame(MoteurTresorerie::AVANCE_IMPUTATION, $ecriture->origine);
        $this->assertSame('DIVERS', $ecriture->journal->type, 'Aucune trésorerie ne bouge : journal des opérations diverses.');
        $this->assertSame(['AVANCE', 'CLIENT'], $ecriture->lignes->pluck('rubrique')->all());
        $this->assertEqualsWithDelta(8000, $ecriture->lignes[0]->debit, 0.001, 'Le compte d\'avances est débité.');
        $this->assertEqualsWithDelta(8000, $ecriture->lignes[1]->credit, 0.001, 'Le compte du client est crédité.');
        $this->assertNull($ecriture->lignes->firstWhere('rubrique', 'TRESORERIE'), 'L\'argent est entré au dépôt : pas deux fois en caisse.');

        $enCaisse = EcritureComptable::whereIn('id', [$depot->id, $ecriture->id])->with('lignes')->get()
            ->flatMap->lignes->where('rubrique', 'TRESORERIE')->sum('debit');
        $this->assertEqualsWithDelta(20000, $enCaisse, 0.001);
    }

    // ------------------------------------------------------------------ lettrage

    public function test_le_lettrage_relie_la_facture_a_ses_reglements_quand_l_affaire_est_soldee(): void
    {
        // L'écriture d'une facture de 10 000 F sur l'affaire (le moteur des factures a ses propres essais).
        $facture = Ecrivain::enregistrer(null, 'FAC-RECETTE-' . uniqid(), [
            'origine' => 'FACTURE', 'source_type' => 'facture', 'source_id' => 0, 'version' => 1, 'date_ecriture' => '2026-09-14',
            'piece' => 'F-RECETTE', 'libelle' => 'Facture de recette', 'service' => \Help::$COMMANDE, 'service_id' => $this->commande->id,
        ], [
            ['rubrique' => 'CLIENT', 'compte' => CompteComptable::where('numero', '411000')->first(), 'tiers' => '411RECETTE', 'libelle' => 'Client', 'montant' => 10000, 'sens' => 'D'],
            ['rubrique' => 'PRODUIT', 'compte' => $this->compte('701100'), 'libelle' => 'Vente', 'montant' => 10000, 'sens' => 'C'],
        ], []);
        $lettres = fn () => DB::table('ligne_ecriture_comptable as l')->join('ecriture_comptable as e', 'e.id', '=', 'l.ecriture_comptable_id')
            ->where('e.service', \Help::$COMMANDE)->where('e.service_id', $this->commande->id)->where('l.rubrique', 'CLIENT')->pluck('l.lettre')->all();

        $this->validerAuGuichet($this->unReglement([['mode_paiement_id' => $this->especes->id, 'montant' => 6000]]));
        $this->assertSame([null, null], $lettres(), 'Affaire non soldée : pas de lettre.');

        $this->validerAuGuichet($this->unReglement([['mode_paiement_id' => $this->mobile->id, 'montant' => 4000]]));
        $apres = $lettres();
        $this->assertCount(3, $apres);
        $this->assertNotNull($apres[0]);
        $this->assertCount(1, array_unique($apres), 'Facture et règlements portent la même lettre.');
        $this->assertNull(DB::table('ligne_ecriture_comptable')->where('ecriture_comptable_id', $facture->id)->where('rubrique', 'PRODUIT')->value('lettre'),
            'Seules les lignes du compte client se lettrent.');

        // Un règlement de trop rompt l'égalité : la lettre se retire.
        $this->validerAuGuichet($this->unReglement([['mode_paiement_id' => $this->especes->id, 'montant' => 1000]]));
        $this->assertSame([null, null, null, null], $lettres());
    }

    // ------------------------------------------------------------------ décaissements

    public function test_le_reglement_d_un_livreur_constate_la_charge_puis_sort_de_la_tresorerie_une_fois_effectue(): void
    {
        $livreur = Livreur::first();
        $livraison = Livraison::first();
        $banque = JournalComptable::where('type', 'BANQUE')->first();
        $virement = ModePaiement::where('en_ligne', 0)->where('id', '!=', 1)->where('id', '!=', $this->especes->id)->first();
        if (!$livreur || !$livraison || !$virement) {
            $this->markTestSkipped('Il faut un livreur, une livraison et un second mode de règlement au guichet.');
        }
        DB::table('mode_paiement')->where('id', $virement->id)->update(['journal_comptable_id' => $banque->id]);
        DB::table('livreur')->where('id', $livreur->id)->update(['compte_tiers' => '401LIV01']);

        $reglement = Model::unguarded(fn () => PaiementLivreur::create([
            'date_paiement' => '2026-09-16', 'livraison_id' => $livraison->id, 'livreur_id' => $livreur->id, 'montant' => 12000,
            'mode_paiement_id' => $virement->id, 'reference' => 'VIR-5521', 'user_id' => User::value('id'), 'statut' => 2,
        ]));
        $this->assertCount(0, $this->ecrituresDe('paiement_livreur', $reglement->id));

        $reglement->update(['statut' => 1, 'etat_reglement' => DemandePaiement::A_PAYER]);
        $this->assertCount(0, $this->ecrituresDe('paiement_livreur', $reglement->id), 'Validé deux fois mais virement à faire : l\'argent n\'est pas sorti.');

        $reglement->update(['etat_reglement' => DemandePaiement::EFFECTUEE, 'date_effectuee' => '2026-09-18 15:00:00']);
        $ecriture = $this->ecrituresDe('paiement_livreur', $reglement->id)->first();

        $this->assertNotNull($ecriture);
        $this->assertSame('DEC-L-' . $reglement->id, $ecriture->identifiant);
        $this->assertSame(MoteurTresorerie::DECAISSEMENT, $ecriture->origine);
        $this->assertSame(EcritureComptable::ETAT_A_EXPORTER, $ecriture->etat, $ecriture->anomalies->pluck('cause')->implode(' | '));
        $this->assertSame('BANQUE', $ecriture->journal->type);
        $this->assertSame('2026-09-18', $ecriture->date_ecriture->toDateString(), 'Date = jour où l\'opération a été effectuée.');
        $this->assertSame('livreur', $ecriture->tiers_type);

        $this->assertSame(['CHARGE', 'FOURNISSEUR', 'FOURNISSEUR', 'TRESORERIE'], $ecriture->lignes->pluck('rubrique')->all());
        [$charge, $dette, $solde, $banqueLigne] = $ecriture->lignes->all();
        $this->assertSame('613000', $charge->numero_compte);
        $this->assertEqualsWithDelta(12000, $charge->debit, 0.001, 'La contrepartie du compte du livreur est une charge.');
        $this->assertSame('401200', $dette->numero_compte, 'Compte fournisseurs dédié aux livreurs.');
        $this->assertSame('401LIV01', $dette->compte_tiers);
        $this->assertEqualsWithDelta(12000, $dette->credit, 0.001);
        $this->assertEqualsWithDelta(12000, $solde->debit, 0.001);
        $this->assertSame('521000', $banqueLigne->numero_compte);
        $this->assertEqualsWithDelta(12000, $banqueLigne->credit, 0.001, 'La trésorerie est créditée.');
        $this->assertTrue($ecriture->estEquilibree());
    }

    public function test_le_reglement_d_un_fournisseur_ne_constate_l_achat_que_si_le_compte_est_renseigne(): void
    {
        $fournisseur = Fournisseur::first();
        $enlevement = Enlevement::first();
        if (!$fournisseur || !$enlevement) {
            $this->markTestSkipped('Il faut un fournisseur et un enlèvement.');
        }
        DB::table('fournisseur')->where('id', $fournisseur->id)->update(['compte_tiers' => '401FOU01']);
        $creer = fn () => Model::unguarded(fn () => PaiementFournisseur::create([
            'date_paiement' => '2026-09-16', 'enlevement_id' => $enlevement->id, 'fournisseur_id' => $fournisseur->id, 'montant' => 30000,
            'mode_paiement_id' => $this->especes->id, 'user_id' => User::value('id'), 'statut' => 1,   // règlement d'avant le circuit de preuve
        ]));

        $sansAchat = $this->ecrituresDe('paiement_fournisseur', $creer()->id)->first();
        $this->assertSame(['FOURNISSEUR', 'TRESORERIE'], $sansAchat->lignes->pluck('rubrique')->all());
        $this->assertSame('401100', $sansAchat->lignes[0]->numero_compte);
        $this->assertSame('401FOU01', $sansAchat->lignes[0]->compte_tiers);
        $this->assertEqualsWithDelta(30000, $sansAchat->lignes[0]->debit, 0.001, 'Le compte du fournisseur est débité.');
        $this->assertEqualsWithDelta(30000, $sansAchat->lignes[1]->credit, 0.001);
        $this->assertSame('2026-09-16', $sansAchat->date_ecriture->toDateString());
        $this->assertSame(EcritureComptable::ETAT_A_EXPORTER, $sansAchat->etat, $sansAchat->anomalies->pluck('cause')->implode(' | '));

        RubriqueComptable::pour(RubriqueComptable::ACHATS_FOURNISSEURS)->update(['compte_comptable_id' => $this->compte('601100')->id]);
        $avecAchat = $this->ecrituresDe('paiement_fournisseur', $creer()->id)->first();
        $this->assertSame(['CHARGE', 'FOURNISSEUR', 'FOURNISSEUR', 'TRESORERIE'], $avecAchat->lignes->pluck('rubrique')->all());
        $this->assertSame('601100', $avecAchat->lignes[0]->numero_compte);
    }

    // ------------------------------------------------------------------ cautions

    public function test_la_caution_d_une_location_entre_dans_un_compte_de_depots_puis_en_sort_au_retour(): void
    {
        $location = Location::whereHas('client')->first();
        if (!$location) {
            $this->markTestSkipped('Aucune location en base.');
        }
        $caisse = JournalComptable::where('type', 'CAISSE')->first();
        DB::table('configuration')->update(['journal_cautions_id' => $caisse->id]);
        DB::table('location')->where('id', $location->id)->update(['caution' => 0, 'date_retour' => null, 'caution_retenue' => 0, 'etat_location' => 'EN ATTENTE']);
        $location = $location->fresh();

        $location->update(['caution' => 50000, 'etat_location' => \Help::$LOCATION_EN_COURS]);
        $recue = $this->ecrituresDe('location_caution_recue', $location->id)->first();
        $this->assertNotNull($recue);
        $this->assertSame('CAU-R-' . $location->id, $recue->identifiant);
        $this->assertSame('571000', $recue->lignes[0]->numero_compte);
        $this->assertEqualsWithDelta(50000, $recue->lignes[0]->debit, 0.001);
        $this->assertSame('165000', $recue->lignes[1]->numero_compte, 'Compte de dépôts et cautionnements reçus.');
        $this->assertEqualsWithDelta(50000, $recue->lignes[1]->credit, 0.001);
        $this->assertCount(0, $this->ecrituresDe('location_caution_rendue', $location->id));

        $location->update(['etat_location' => \Help::$LOCATION_TERMINE, 'date_retour' => '2026-09-20', 'caution_retenue' => 10000, 'motif_retenue' => 'Godet abîmé']);
        $rendue = $this->ecrituresDe('location_caution_rendue', $location->id)->first();
        $this->assertNotNull($rendue);
        $this->assertSame('2026-09-20', $rendue->date_ecriture->toDateString());
        $this->assertSame(['CAUTION', 'TRESORERIE', 'CAUTION_RETENUE'], $rendue->lignes->pluck('rubrique')->all());
        $this->assertEqualsWithDelta(50000, $rendue->lignes[0]->debit, 0.001, 'Le dépôt est soldé en entier.');
        $this->assertEqualsWithDelta(40000, $rendue->lignes[1]->credit, 0.001, 'Rendu au client : caution moins retenue.');
        $this->assertSame('758000', $rendue->lignes[2]->numero_compte);
        $this->assertEqualsWithDelta(10000, $rendue->lignes[2]->credit, 0.001);
        $this->assertStringContainsString('Godet', $rendue->lignes[2]->libelle);
        $this->assertTrue($rendue->estEquilibree());
    }

    // ------------------------------------------------------------------ paramétrage et garde-fous

    public function test_le_parametrage_couvre_livreurs_apporteurs_cautions_et_rubriques_facultatives(): void
    {
        $admin = User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN])->where('statut', \Help::$STATUT_ACTIF)->first();
        $livreur = Livreur::first();
        $apporteur = Apporteur::first();
        if (!$admin || !$livreur || !$apporteur) {
            $this->markTestSkipped('Il faut un administrateur, un livreur et un apporteur.');
        }
        DB::table('livreur')->update(['compte_tiers' => null]);
        DB::table('apporteur')->update(['compte_tiers' => null]);

        $this->actingAs($admin)->get('/comptabilite/parametrage?onglet=tiers')->assertOk()
            ->assertSee('name="livreur[' . $livreur->id . ']"', false)->assertSee('name="apporteur[' . $apporteur->id . ']"', false);

        $this->actingAs($admin)->post('/comptabilite/parametrage/tiers', ['livreur' => [$livreur->id => '401liv77']])->assertSessionHasNoErrors();
        $this->assertSame('401LIV77', $livreur->fresh()->compte_tiers);
        $this->actingAs($admin)->post('/comptabilite/parametrage/tiers/generer', ['cible' => 'apporteur', 'prefixe' => '4013'])->assertSessionHasNoErrors();
        // Le rang occupe ce qui reste après le préfixe, au minimum trois chiffres.
        $this->assertSame('4013' . str_pad((string) $apporteur->id, 3, '0', STR_PAD_LEFT), $apporteur->fresh()->compte_tiers);

        $caisse = JournalComptable::where('type', 'CAISSE')->first();
        $ventes = JournalComptable::where('type', 'VENTES')->first();
        $this->actingAs($admin)->post('/comptabilite/parametrage/reglages', ['longueur_compte_comptable' => 6, 'journal_cautions_id' => $ventes->id])
            ->assertSessionHasErrors('journal_cautions_id');
        $this->actingAs($admin)->post('/comptabilite/parametrage/reglages', ['longueur_compte_comptable' => 6, 'journal_cautions_id' => $caisse->id])
            ->assertSessionHasNoErrors();
        $this->assertSame($caisse->id, (int) DB::table('configuration')->value('journal_cautions_id'));

        $objets = collect(ParametrageComptable::anomalies())->pluck('objet');
        $this->assertNotContains('Achats de marchandises (charge)', $objets, 'Une rubrique facultative sans compte n\'est pas une anomalie.');
        $this->assertNotContains('Journal des cautions', $objets);
        $this->assertTrue(JournalComptable::where('type', 'DIVERS')->exists(), 'La migration pose le journal des opérations diverses.');
    }

    public function test_une_ecriture_impossible_ne_fait_jamais_echouer_un_encaissement(): void
    {
        $paiement = $this->unReglement([['mode_paiement_id' => $this->especes->id, 'montant' => 5000]]);
        $ligne = LignePaiement::where('paiement_id', $paiement->id)->first();
        // Identifiant déjà pris : le moteur lèvera une exception (contrainte d'unicité).
        EcritureComptable::create([
            'identifiant' => 'ENC-' . $ligne->id, 'origine' => 'ENCAISSEMENT', 'source_type' => 'autre', 'source_id' => 0,
            'date_ecriture' => '2026-09-01', 'piece' => 'X', 'libelle' => 'Occupant', 'etat' => 'A_EXPORTER',
        ]);

        $this->validerAuGuichet($paiement);

        $this->assertSame(1, (int) $paiement->fresh()->statut, 'L\'encaissement est validé quoi qu\'il arrive à l\'écriture.');
        $this->assertCount(0, $this->ecrituresDe('ligne_paiement', $ligne->id));
    }
}
