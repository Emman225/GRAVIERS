<?php

namespace Tests\Feature;

use App\Models\AnomalieComptable;
use App\Models\Categorie;
use App\Models\Commande;
use App\Models\CompteComptable;
use App\Models\Configuration;
use App\Models\EcritureComptable;
use App\Models\Enlevement;
use App\Models\Facture;
use App\Models\JournalComptable;
use App\Models\LigneEcritureComptable;
use App\Models\Location;
use App\Models\Produit;
use App\Models\RubriqueComptable;
use App\Models\TvaCommande;
use App\Services\Comptabilite\MoteurEcritures;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Module « Écritures comptables », phase 2 : le moteur (lot 117, 21/09/2026).
 *
 * Chaque facture normalisée produit, sans ressaisie, une écriture équilibrée
 * dont les comptes viennent du paramétrage. L'exemple du rapport sert d'étalon :
 * 8 000 F de gravier + 1 000 F de transport, TVA 18 %, AIRSI 5 % = 11 151 F.
 */
class MoteurEcrituresComptablesTest extends TestCase
{
    use DatabaseTransactions;

    private float $taux;

    protected function setUp(): void
    {
        parent::setUp();
        $this->taux = (float) (Configuration::first()->tva ?? 0);
        if ($this->taux <= 0) {
            $this->markTestSkipped('Le paramétrage ne taxe pas.');
        }
        DB::table('configuration')->update(['longueur_compte_comptable' => 6, 'format_libelle_ecriture' => null]);
        // Plan vierge et journal des ventes connu : l'essai ne dépend pas de la saisie locale.
        DB::table('compte_comptable')->delete();
        DB::table('rubrique_comptable')->delete();
        DB::table('journal_comptable')->where('type', 'VENTES')->update(['statut' => 1, 'deleted_at' => null]);
    }

    // ------------------------------------------------------------------ montage

    private function compte(string $nature, string $numero): CompteComptable
    {
        return CompteComptable::create(['nature' => $nature, 'numero' => $numero, 'libelle' => 'Recette ' . $numero, 'statut' => 1]);
    }

    /** Rubriques de facture paramétrées ; rend les comptes par code. */
    private function parametrerLesRubriques(): array
    {
        $comptes = [
            RubriqueComptable::CLIENTS => '411000', RubriqueComptable::TVA_COLLECTEE => '443100', RubriqueComptable::AIRSI => '447200',
            RubriqueComptable::TRANSPORT => '706100', RubriqueComptable::REMISES => '673000',
        ];
        $analytique = $this->compte('ANALYTIQUE', 'TRP-LIV');
        foreach ($comptes as $code => $numero) {
            $compte = $this->compte('GENERAL', $numero);
            RubriqueComptable::pour($code)->update([
                'compte_comptable_id'  => $compte->id,
                'compte_analytique_id' => $code === RubriqueComptable::TRANSPORT ? $analytique->id : null,
            ]);
        }

        return $comptes;
    }

    /** Donne au produit sa grande famille (compte général) et son compte analytique. */
    private function parametrerLeProduit(Produit $produit, string $general, string $analytique): Categorie
    {
        $famille = Categorie::create(['nom' => 'Famille recette ' . uniqid(), 'description' => 'Recette', 'parent_id' => 0, 'statut' => \Help::$STATUT_ACTIF]);
        $compteGeneral = CompteComptable::where('numero', $general)->first() ?: $this->compte('GENERAL', $general);
        DB::table('categorie')->where('id', $famille->id)->update(['compte_comptable_id' => $compteGeneral->id]);
        DB::table('produit')->where('id', $produit->id)->update([
            'categorie_comptable_id' => $famille->id,
            'compte_analytique_id'   => $this->compte('ANALYTIQUE', $analytique)->id,
        ]);

        return $famille;
    }

    /** Un enlèvement réel, ramené à « 1 unité à 8 000 F », sa commande taxée, son client doté d'un compte tiers. */
    private function unEnlevement(float $quantite = 1, float $prix = 8000): Enlevement
    {
        $enlevement = Enlevement::whereNull('facture_id')->get()->first(function (Enlevement $e) {
            $detail = $e->livraison?->detailCommande;

            return $detail && $e->produit && $detail->commande_id && Commande::find($detail->commande_id)?->client;
        });
        if (!$enlevement) {
            $this->markTestSkipped('Aucun enlèvement exploitable en base.');
        }

        $detail = $enlevement->livraison->detailCommande;
        DB::table('enlevement')->where('id', $enlevement->id)->update(['qte_servi' => $quantite]);
        DB::table('detail_commande')->where('id', $detail->id)->update(['prix' => $prix, 'qte' => max($quantite, (float) $detail->qte)]);
        $this->taxer($detail->commande_id, true);
        DB::table('client')->where('id', Commande::find($detail->commande_id)->client_id)->update(['compte_tiers' => '411RECETTE']);

        return $enlevement->fresh();
    }

    private function taxer(int $commandeId, bool $taxee): void
    {
        $commande = Commande::find($commandeId);
        TvaCommande::where('commande_id', $commandeId)->delete();
        TvaCommande::create([
            'client_id' => $commande->client_id, 'commande_id' => $commandeId,
            'montant' => $taxee ? 1000 : 0, 'type_affaire' => \Help::$VENTE,
        ]);
    }

    private function commandeDe(Enlevement $enlevement): Commande
    {
        return Commande::find($enlevement->livraison->detailCommande->commande_id);
    }

    /** Facture de vente rattachée à des enlèvements, en attente de certification. */
    private function uneFacture(Commande $commande, array $enlevements, array $montants): Facture
    {
        $facture = Facture::create(array_merge([
            'numero'                  => 'R' . substr((string) hrtime(true), -8),
            'numero_fne'              => 'FNE-RECETTE-' . uniqid(),
            'user_id'                 => \App\Models\User::value('id'),
            'client_id'               => $commande->client_id,
            'service'                 => \Help::$COMMANDE,
            'service_id'              => $commande->id,
            'remise_appliquee'        => 0,
            'cout_livraison_applique' => 0,
            'tva_transport_applique'  => 0,
            'airsi_applique'          => 0,
            'statut'                  => 2,
            'fne_status'              => 'pending',
        ], $montants));
        foreach ($enlevements as $enlevement) {
            DB::table('enlevement')->where('id', $enlevement->id)->update(['facture_id' => $facture->id]);
        }

        return $facture;
    }

    private function certifier(Facture $facture, array $reponse = []): Facture
    {
        $facture->update([
            'fne_status' => 'certified', 'fne_certified_at' => '2026-09-18 10:30:00',
            'fne_reference' => 'REF-' . $facture->id, 'fne_response_payload' => $reponse ?: null,
        ]);

        return $facture->fresh();
    }

    private function ecritureDe(Facture $facture): ?EcritureComptable
    {
        return EcritureComptable::vivantesDeLaFacture($facture->id)->with('lignes')->orderByDesc('id')->first();
    }

    /** La facture de l'exemple du rapport : 8 000 + 1 000 de transport, TVA sur les deux, AIRSI 531 → 11 151. */
    private function laFactureDuRapport(): array
    {
        $this->parametrerLesRubriques();
        $enlevement = $this->unEnlevement(1, 8000);
        $this->parametrerLeProduit($enlevement->produit, '701100', 'GRA-0515');
        $tva = round(8000 * $this->taux / 100);
        $tvaTransport = round(1000 * $this->taux / 100);
        $facture = $this->uneFacture($this->commandeDe($enlevement), [$enlevement], [
            'cout_livraison_applique' => 1000, 'tva_transport_applique' => $tvaTransport, 'airsi_applique' => 531,
            'montant' => 8000 + 1000 + $tva + $tvaTransport + 531,
        ]);

        return [$facture, $enlevement, $tva + $tvaTransport];
    }

    // ------------------------------------------------------------------ essais

    public function test_la_certification_produit_l_ecriture_de_l_exemple_du_rapport(): void
    {
        [$facture, $enlevement, $tva] = $this->laFactureDuRapport();
        $this->assertNull($this->ecritureDe($facture), 'Une facture non certifiée ne produit rien.');
        $this->assertNull(MoteurEcritures::produirePourFacture($facture));

        $facture = $this->certifier($facture);
        $ecriture = $this->ecritureDe($facture);

        $this->assertNotNull($ecriture, 'La certification doit produire l\'écriture, sans aucun geste.');
        $this->assertSame('FAC-' . $facture->id, $ecriture->identifiant);
        $this->assertSame(EcritureComptable::ORIGINE_FACTURE, $ecriture->origine);
        $this->assertSame(EcritureComptable::ETAT_A_EXPORTER, $ecriture->etat, $ecriture->anomalies->pluck('cause')->implode(' | '));
        $this->assertSame('2026-09-18', $ecriture->date_ecriture->toDateString(), 'Date de l\'écriture = date de certification.');
        $this->assertSame($facture->numero, $ecriture->piece);
        $this->assertSame('REF-' . $facture->id, $ecriture->reference_fne);
        $this->assertSame(JournalComptable::TYPE_VENTES, $ecriture->journal->type);
        $this->assertStringContainsString($facture->numero, $ecriture->libelle);
        $this->assertStringContainsString('REF-' . $facture->id, $ecriture->libelle);

        $total = (float) $facture->montant;
        $this->assertTrue($ecriture->estEquilibree());
        $this->assertEqualsWithDelta($total, $ecriture->total_debit, 0.001);
        $this->assertEqualsWithDelta($total, $ecriture->total_credit, 0.001);

        $lignes = $ecriture->lignes->keyBy('rubrique');
        $this->assertSame(['CLIENT', 'PRODUIT', 'TRANSPORT', 'TVA', 'AIRSI'], $ecriture->lignes->pluck('rubrique')->all());

        $this->assertSame('411000', $lignes['CLIENT']->numero_compte);
        $this->assertSame('411RECETTE', $lignes['CLIENT']->compte_tiers);
        $this->assertEqualsWithDelta($total, $lignes['CLIENT']->debit, 0.001);

        $this->assertSame('701100', $lignes['PRODUIT']->numero_compte, 'Le compte général vient de la grande famille.');
        $this->assertSame('GRA-0515', $lignes['PRODUIT']->numero_analytique, 'Le compte analytique vient du produit.');
        $this->assertSame($enlevement->produit_id, (int) $lignes['PRODUIT']->produit_id);
        $this->assertNotNull($lignes['PRODUIT']->categorie_id);
        $this->assertEqualsWithDelta(8000, $lignes['PRODUIT']->credit, 0.001);

        $this->assertSame('706100', $lignes['TRANSPORT']->numero_compte);
        $this->assertSame('TRP-LIV', $lignes['TRANSPORT']->numero_analytique);
        $this->assertEqualsWithDelta(1000, $lignes['TRANSPORT']->credit, 0.001);
        $this->assertEqualsWithDelta($tva, $lignes['TVA']->credit, 0.001);
        $this->assertSame('443100', $lignes['TVA']->numero_compte);
        $this->assertEqualsWithDelta(531, $lignes['AIRSI']->credit, 0.001);
        $this->assertSame('447200', $lignes['AIRSI']->numero_compte);

        // Rejouée, la production ne double rien.
        MoteurEcritures::produirePourFacture($facture);
        $this->assertSame(1, EcritureComptable::where('source_type', 'facture')->where('source_id', $facture->id)->count());
        $this->assertSame(5, LigneEcritureComptable::where('ecriture_comptable_id', $ecriture->id)->count());
    }

    public function test_une_commande_a_deux_factures_n_est_jamais_comptee_deux_fois(): void
    {
        $this->parametrerLesRubriques();
        $premier = $this->unEnlevement(2, 5000);
        $this->parametrerLeProduit($premier->produit, '701100', 'REC-A');
        $commande = $this->commandeDe($premier);

        // Un second enlèvement de la MÊME ligne de commande, servi plus tard.
        $second = $premier->replicate(['code_enleve']);
        $second->code_enleve = null;
        $second->qte_servi = 3;
        $second->save();

        $tva = fn (float $ht) => round($ht * $this->taux / 100);
        $factureA = $this->certifier($this->uneFacture($commande, [$premier], ['montant' => 10000 + $tva(10000)]));
        $factureB = $this->certifier($this->uneFacture($commande, [$second], ['montant' => 15000 + $tva(15000)]));

        $ventes = fn (Facture $f) => (float) $this->ecritureDe($f)->lignes->where('rubrique', 'PRODUIT')->sum('credit');
        $this->assertEqualsWithDelta(10000, $ventes($factureA), 0.001, 'La première facture ne porte que son enlèvement.');
        $this->assertEqualsWithDelta(15000, $ventes($factureB), 0.001, 'La seconde ne porte que le sien.');
        $this->assertTrue($this->ecritureDe($factureA)->estEquilibree());
        $this->assertTrue($this->ecritureDe($factureB)->estEquilibree());
        $this->assertSame($this->ecritureDe($factureA)->numero_affaire, $this->ecritureDe($factureB)->numero_affaire);

        $totalEcrit = EcritureComptable::whereIn('source_id', [$factureA->id, $factureB->id])->where('source_type', 'facture')->sum('total_debit');
        $this->assertEqualsWithDelta((float) $factureA->montant + (float) $factureB->montant, (float) $totalEcrit, 0.001,
            'Total des écritures = total des factures normalisées.');
    }

    public function test_deux_enlevements_du_meme_produit_font_une_seule_ligne(): void
    {
        $this->parametrerLesRubriques();
        $premier = $this->unEnlevement(2, 5000);
        $this->parametrerLeProduit($premier->produit, '701100', 'REC-A');
        $second = $premier->replicate(['code_enleve']);
        $second->code_enleve = null;
        $second->qte_servi = 1;
        $second->save();

        $ht = 15000;
        $facture = $this->certifier($this->uneFacture($this->commandeDe($premier), [$premier, $second], ['montant' => $ht + round($ht * $this->taux / 100)]));
        $produits = $this->ecritureDe($facture)->lignes->where('rubrique', 'PRODUIT');

        $this->assertCount(1, $produits, 'Une ligne par compte analytique, pas une par enlèvement.');
        $this->assertEqualsWithDelta(15000, $produits->first()->credit, 0.001);
    }

    public function test_un_parametrage_incomplet_met_l_ecriture_en_anomalie_puis_la_reprise_la_regle(): void
    {
        $enlevement = $this->unEnlevement(1, 8000);
        DB::table('produit')->where('id', $enlevement->produit_id)->update(['categorie_comptable_id' => null, 'compte_analytique_id' => null]);
        DB::table('client')->where('id', $this->commandeDe($enlevement)->client_id)->update(['compte_tiers' => null]);
        $facture = $this->certifier($this->uneFacture($this->commandeDe($enlevement), [$enlevement], ['montant' => 8000 + round(8000 * $this->taux / 100)]));

        $ecriture = $this->ecritureDe($facture);
        $this->assertSame(EcritureComptable::ETAT_ANOMALIE, $ecriture->etat);
        $codes = $ecriture->anomaliesOuvertes()->pluck('code')->all();
        foreach ([AnomalieComptable::PRODUIT_SANS_FAMILLE, AnomalieComptable::PRODUIT_SANS_ANALYTIQUE,
                  AnomalieComptable::CLIENT_SANS_COMPTE_TIERS, AnomalieComptable::RUBRIQUE_SANS_COMPTE] as $code) {
            $this->assertContains($code, $codes);
        }

        // Chaque anomalie dit la facture, la ligne, la colonne et l'onglet où corriger.
        $anomalie = $ecriture->anomaliesOuvertes()->where('code', AnomalieComptable::PRODUIT_SANS_FAMILLE)->first();
        $this->assertSame($facture->id, (int) $anomalie->facture_id);
        $this->assertSame(2, $anomalie->rang_ligne);
        $this->assertSame('Grande famille', $anomalie->colonne);
        $this->assertSame('produits', $anomalie->onglet);
        $this->assertStringContainsString($enlevement->produit->nom, $anomalie->objet);

        // On corrige le paramétrage, on reprend : même écriture, réglée, anomalies datées et non effacées.
        $this->parametrerLesRubriques();
        $this->parametrerLeProduit($enlevement->produit, '701100', 'REC-A');
        DB::table('client')->where('id', $this->commandeDe($enlevement)->client_id)->update(['compte_tiers' => '411RECETTE']);
        $bilan = MoteurEcritures::reprendreLesAnomalies();

        $this->assertGreaterThanOrEqual(1, $bilan['reglees']);
        $apres = $this->ecritureDe($facture);
        $this->assertSame($ecriture->id, $apres->id, 'La reprise réécrit la même écriture, elle n\'en crée pas une autre.');
        $this->assertSame(EcritureComptable::ETAT_A_EXPORTER, $apres->etat);
        $this->assertSame(0, $apres->anomaliesOuvertes()->count());
        $this->assertGreaterThanOrEqual(4, $apres->anomalies()->whereNotNull('resolue_le')->count(), 'L\'historique des anomalies est conservé.');
    }

    public function test_un_ecart_d_arrondi_va_sur_la_derniere_ligne_un_vrai_ecart_bloque(): void
    {
        $this->parametrerLesRubriques();
        $enlevement = $this->unEnlevement(1, 8000);
        $this->parametrerLeProduit($enlevement->produit, '701100', 'REC-A');
        $juste = 8000 + round(8000 * $this->taux / 100);

        $arrondie = $this->certifier($this->uneFacture($this->commandeDe($enlevement), [$enlevement], ['montant' => $juste + 3]));
        $ecriture = $this->ecritureDe($arrondie);
        $this->assertSame(EcritureComptable::ETAT_A_EXPORTER, $ecriture->etat);
        $this->assertTrue($ecriture->estEquilibree());
        $this->assertEqualsWithDelta(8003, $ecriture->lignes->firstWhere('rubrique', 'PRODUIT')->credit, 0.001, 'Trois francs d\'arrondi : sur la dernière ligne de vente.');

        DB::table('enlevement')->where('id', $enlevement->id)->update(['facture_id' => null]);
        $fausse = $this->certifier($this->uneFacture($this->commandeDe($enlevement), [$enlevement], ['montant' => $juste + 500]));
        $ecriture = $this->ecritureDe($fausse);
        $this->assertSame(EcritureComptable::ETAT_ANOMALIE, $ecriture->etat, 'Une écriture qui ne s\'équilibre pas n\'est pas exportable.');
        $this->assertFalse($ecriture->estEquilibree());
        $anomalie = $ecriture->anomaliesOuvertes()->where('code', AnomalieComptable::ECART_DE_TOTAL)->first();
        $this->assertNotNull($anomalie);
        $this->assertStringContainsString('500', $anomalie->cause);
    }

    public function test_remise_au_debit_et_client_dispense_sans_ligne_de_tva(): void
    {
        $this->parametrerLesRubriques();
        $enlevement = $this->unEnlevement(1, 8000);
        $this->parametrerLeProduit($enlevement->produit, '701100', 'REC-A');
        $commande = $this->commandeDe($enlevement);
        $this->taxer($commande->id, false);

        $facture = $this->certifier($this->uneFacture($commande, [$enlevement], ['remise_appliquee' => 500, 'montant' => 7500]));
        $ecriture = $this->ecritureDe($facture);

        $this->assertSame(EcritureComptable::ETAT_A_EXPORTER, $ecriture->etat, $ecriture->anomalies->pluck('cause')->implode(' | '));
        $this->assertSame(['CLIENT', 'PRODUIT', 'REMISE'], $ecriture->lignes->pluck('rubrique')->all(), 'Un client dispensé de TVA n\'a pas de ligne de TVA.');
        $remise = $ecriture->lignes->firstWhere('rubrique', 'REMISE');
        $this->assertSame('673000', $remise->numero_compte);
        $this->assertEqualsWithDelta(500, $remise->debit, 0.001);
        $this->assertEqualsWithDelta(8000, $ecriture->total_credit, 0.001);
        $this->assertTrue($ecriture->estEquilibree());
    }

    public function test_un_avoir_produit_l_ecriture_inverse_rattachee_a_sa_facture(): void
    {
        [$facture, $enlevement, $tva] = $this->laFactureDuRapport();
        DB::table('enlevement')->where('id', $enlevement->id)->update(['qte_servi' => 2]);
        DB::table('detail_commande')->where('id', $enlevement->livraison->detailCommande->id)->update(['prix' => 4000]);
        $facture = $this->certifier($facture, ['invoice' => ['items' => [
            ['id' => 'art-1', 'reference' => 'X', 'description' => $enlevement->produit->nom, 'quantity' => 2, 'amount' => 4000],
            ['id' => 'art-2', 'reference' => 'LIVRAISON', 'description' => 'Frais de livraison', 'quantity' => 1, 'amount' => 1000],
        ]]]);

        // On crédite une unité sur deux : 4 000 F sur 9 000 F de hors taxe certifié.
        $part = 4000 / 9000;
        $avoir = Facture::create([
            'numero' => 'A' . substr((string) hrtime(true), -8), 'numero_fne' => 'AV-' . uniqid(),
            'user_id' => \App\Models\User::value('id'), 'type_document' => Facture::TYPE_AVOIR, 'facture_origine_id' => $facture->id, 'motif_avoir' => 'Retour recette',
            'lignes_avoir' => [['id' => 'art-1', 'reference' => 'X', 'description' => $enlevement->produit->nom, 'quantity' => 1, 'amount' => 4000]],
            'client_id' => $facture->client_id, 'service' => $facture->service, 'service_id' => $facture->service_id,
            'statut' => 2, 'montant' => -round((float) $facture->montant * $part),
            'remise_appliquee' => 0, 'cout_livraison_applique' => 0, 'tva_transport_applique' => 0, 'airsi_applique' => 0,
            'fne_status' => 'certified', 'fne_certified_at' => '2026-09-19 09:00:00', 'fne_reference' => 'REF-AVOIR',
        ]);

        $ecriture = $this->ecritureDe($avoir);
        $this->assertNotNull($ecriture, 'Un avoir créé certifié produit son écriture.');
        $this->assertSame(EcritureComptable::ORIGINE_AVOIR, $ecriture->origine);
        $this->assertSame(EcritureComptable::ETAT_A_EXPORTER, $ecriture->etat, $ecriture->anomalies->pluck('cause')->implode(' | '));
        $this->assertTrue($ecriture->estEquilibree());
        $this->assertStringContainsString('Avoir', $ecriture->libelle);

        $client = $ecriture->lignes->firstWhere('rubrique', 'CLIENT');
        $this->assertEqualsWithDelta(abs((float) $avoir->montant), $client->credit, 0.001, 'Le client est crédité.');
        $this->assertEqualsWithDelta(0, $client->debit, 0.001);

        $produit = $ecriture->lignes->firstWhere('rubrique', 'PRODUIT');
        $this->assertSame('701100', $produit->numero_compte, 'Mêmes comptes que la facture d\'origine.');
        $this->assertSame('GRA-0515', $produit->numero_analytique);
        $this->assertGreaterThan(0, $produit->debit, 'La vente est contre-passée : au débit.');
        $this->assertEqualsWithDelta(4000, $produit->debit, MoteurEcritures::TOLERANCE);
        $this->assertNull($ecriture->lignes->firstWhere('rubrique', 'TRANSPORT'), 'Le transport n\'est pas crédité : il n\'est pas dans l\'avoir.');
        $this->assertGreaterThan(0, $ecriture->lignes->firstWhere('rubrique', 'TVA')->debit);
        $this->assertGreaterThan(0, $ecriture->lignes->firstWhere('rubrique', 'AIRSI')->debit);
    }

    public function test_une_ecriture_exportee_ne_change_plus_la_correction_passe_par_une_annulation(): void
    {
        [$facture] = $this->laFactureDuRapport();
        $facture = $this->certifier($facture);
        $ecriture = $this->ecritureDe($facture);
        $ecriture->update(['etat' => EcritureComptable::ETAT_EXPORTEE, 'exportee_le' => now()]);
        $avant = $ecriture->lignes->map->only(['rang', 'numero_compte', 'debit', 'credit'])->all();

        // La facture est corrigée après l'export : +118 F de transport TTC.
        $facture->update(['cout_livraison_applique' => 1100, 'tva_transport_applique' => round(1100 * $this->taux / 100),
            'montant' => (float) $facture->montant + 100 + round(100 * $this->taux / 100)]);

        MoteurEcritures::produirePourFacture($facture->fresh());
        $this->assertSame($avant, $ecriture->fresh('lignes')->lignes->map->only(['rang', 'numero_compte', 'debit', 'credit'])->all(),
            'Une écriture exportée n\'est jamais modifiée.');

        $nouvelle = MoteurEcritures::corriger($facture->fresh());
        $ancienne = $ecriture->fresh();
        $annulation = EcritureComptable::find($ancienne->annulee_par_id);

        $this->assertNotNull($annulation);
        $this->assertSame(EcritureComptable::ORIGINE_ANNULATION, $annulation->origine);
        $this->assertSame($ancienne->id, (int) $annulation->annulation_de_id);
        $this->assertSame($ancienne->identifiant . '-ANN', $annulation->identifiant);
        $this->assertEqualsWithDelta($ancienne->total_debit, $annulation->total_credit, 0.001);
        foreach ($ancienne->lignes as $i => $ligne) {
            $this->assertEqualsWithDelta($ligne->debit, $annulation->lignes[$i]->credit, 0.001);
            $this->assertEqualsWithDelta($ligne->credit, $annulation->lignes[$i]->debit, 0.001);
        }
        $this->assertSame(EcritureComptable::ETAT_EXPORTEE, $ancienne->etat, 'L\'ancienne reste telle qu\'exportée.');

        $this->assertSame('FAC-' . $facture->id . '-V2', $nouvelle->identifiant);
        $this->assertSame(EcritureComptable::ETAT_A_EXPORTER, $nouvelle->etat);
        $this->assertEqualsWithDelta(1100, $nouvelle->lignes->firstWhere('rubrique', 'TRANSPORT')->credit, 0.001);
        $this->assertSame($nouvelle->id, $this->ecritureDe($facture)->id, 'La facture n\'a qu\'une écriture vivante.');

        // Net des trois écritures = la facture corrigée.
        $net = EcritureComptable::where('source_type', 'facture')->where('source_id', $facture->id)->with('lignes')->get()
            ->flatMap->lignes->where('rubrique', 'CLIENT')->sum(fn ($l) => $l->debit - $l->credit);
        $this->assertEqualsWithDelta((float) $facture->fresh()->montant, $net, 0.001);
    }

    public function test_une_facture_de_location_ecrit_le_materiel_loue(): void
    {
        $location = Location::whereHas('detailLocation')->whereHas('client')->first();
        if (!$location || !$location->detailLocation->first()?->produit) {
            $this->markTestSkipped('Aucune location avec des lignes en base.');
        }
        $this->parametrerLesRubriques();
        foreach ($location->detailLocation as $i => $detail) {
            $this->parametrerLeProduit($detail->produit, '707300', 'LOC-' . $i);
        }
        DB::table('client')->where('id', $location->client_id)->update(['compte_tiers' => '411LOC']);
        DB::table('location')->where('id', $location->id)->update(['cout_livraison_client' => 0, 'remise' => 0, 'tva_transport' => 0]);
        Facture::where('service', \Help::$LOCATION)->where('service_id', $location->id)->delete();

        $ht = (float) $location->detailLocation->sum('prix');
        $taux = (float) \Help::tauxTvaAffaire($location->fresh());
        $facture = Facture::create([
            'numero' => 'L' . substr((string) hrtime(true), -8), 'numero_fne' => 'FNE-LOC-' . uniqid(), 'client_id' => $location->client_id,
            'user_id' => \App\Models\User::value('id'), 'service' => \Help::$LOCATION, 'service_id' => $location->id, 'montant' => $ht + round($ht * $taux / 100),
            'airsi_applique' => 0, 'statut' => 2, 'fne_status' => 'pending',
        ]);
        $ecriture = $this->ecritureDe($this->certifier($facture));

        $this->assertSame(EcritureComptable::ETAT_A_EXPORTER, $ecriture->etat, $ecriture->anomalies->pluck('cause')->implode(' | '));
        $this->assertTrue($ecriture->estEquilibree());
        $this->assertSame(\Help::$LOCATION, $ecriture->service);
        $this->assertSame((string) $location->numero, $ecriture->numero_affaire);
        $this->assertEqualsWithDelta($ht, $ecriture->lignes->where('rubrique', 'PRODUIT')->sum('credit'), 0.001);
        $this->assertSame('707300', $ecriture->lignes->firstWhere('rubrique', 'PRODUIT')->numero_compte);
    }

    public function test_la_commande_de_reprise_ecrit_ce_qui_manque_sans_rien_doubler(): void
    {
        [$facture] = $this->laFactureDuRapport();
        // Certifiée « en douce », comme une facture d'avant le module : aucune écriture.
        DB::table('facture')->where('id', $facture->id)->update(['fne_status' => 'certified', 'fne_certified_at' => '2026-09-10 08:00:00']);
        $this->assertNull($this->ecritureDe($facture));

        $this->artisan('comptabilite:produire-ecritures', ['--du' => '2026-09-10', '--au' => '2026-09-10'])->assertExitCode(0);
        $this->assertNotNull($this->ecritureDe($facture));

        $this->artisan('comptabilite:produire-ecritures', ['--du' => '2026-09-10', '--au' => '2026-09-10'])->assertExitCode(0);
        $this->assertSame(1, EcritureComptable::where('source_type', 'facture')->where('source_id', $facture->id)->count());
    }

    public function test_une_ecriture_impossible_ne_fait_jamais_echouer_la_certification(): void
    {
        [$facture] = $this->laFactureDuRapport();
        // Un identifiant déjà pris fera lever une exception au moteur (contrainte d'unicité) :
        // la certification, elle, doit passer quand même.
        EcritureComptable::create([
            'identifiant' => 'FAC-' . $facture->id, 'origine' => 'FACTURE', 'source_type' => 'autre', 'source_id' => 0,
            'date_ecriture' => '2026-09-01', 'piece' => 'X', 'libelle' => 'Occupant', 'etat' => 'A_EXPORTER',
        ]);

        $facture = $this->certifier($facture);

        $this->assertSame('certified', $facture->fne_status);
        $this->assertNull($this->ecritureDe($facture), 'Le moteur a bien échoué — sans emporter la certification.');
    }
}
