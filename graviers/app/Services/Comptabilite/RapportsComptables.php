<?php

namespace App\Services\Comptabilite;

use App\Models\AnomalieComptable;
use App\Models\Categorie;
use App\Models\CompteComptable;
use App\Models\EcritureComptable;
use App\Models\Facture;
use App\Models\JournalComptable;
use App\Models\LigneEcritureComptable;
use App\Models\RubriqueComptable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * LES RAPPORTS COMPTABLES (module « Écritures comptables », phase 3b, lot 120).
 *
 * Tous lisent les écritures et n'écrivent rien : aucun rapport ne peut modifier
 * ni supprimer une écriture.
 *
 * Deux règles valent pour tous :
 *  - les écritures EN ANOMALIE sont hors des rapports de comptabilité : elles
 *    ne sont pas équilibrées, et les additionner fausserait les totaux. Seuls
 *    le rapport d'anomalies et le contrôle de complétude les regardent ;
 *  - une écriture annulée et son annulation restent, l'une et l'autre : leur
 *    somme est nulle, et c'est ainsi que la comptabilité se lit.
 */
class RapportsComptables
{
    /** La base commune : les lignes des écritures retenues, avec leur écriture. */
    private static function lignes(?Carbon $du, ?Carbon $au)
    {
        return LigneEcritureComptable::query()
            ->join('ecriture_comptable as e', 'e.id', '=', 'ligne_ecriture_comptable.ecriture_comptable_id')
            ->where('e.etat', '!=', EcritureComptable::ETAT_ANOMALIE)
            ->when($du, fn ($q) => $q->where('e.date_ecriture', '>=', $du->toDateString()))
            ->when($au, fn ($q) => $q->where('e.date_ecriture', '<=', $au->toDateString()));
    }

    // ================================================================== rapports demandés

    /**
     * 1. SUIVI DES DÉVERSEMENTS — ce qui a été transmis, quand, et ce qui reste.
     * Le journal des transmissions vit dans l'écran de transmission ; ici, le
     * compte de ce qui est parti et de ce qui attend, mois par mois.
     */
    public static function deversementsParMois(Carbon $du, Carbon $au): array
    {
        $lignes = EcritureComptable::whereBetween('date_ecriture', [$du->toDateString(), $au->toDateString()])
            ->select(
                DB::raw("DATE_FORMAT(date_ecriture, '%Y-%m') AS mois"),
                'etat',
                DB::raw('COUNT(*) AS nombre'),
                DB::raw('SUM(total_debit) AS montant')
            )->groupBy('mois', 'etat')->orderBy('mois')->get();

        $parMois = [];
        foreach ($lignes as $ligne) {
            $mois = $ligne->mois;
            $parMois[$mois] ??= ['mois' => $mois, 'libelle' => self::libelleMois($mois),
                'deverse' => 0, 'montant_deverse' => 0.0, 'non_deverse' => 0, 'montant_non_deverse' => 0.0,
                'anomalie' => 0, 'montant_anomalie' => 0.0];
            if ($ligne->etat === EcritureComptable::ETAT_EXPORTEE) {
                $parMois[$mois]['deverse'] += (int) $ligne->nombre;
                $parMois[$mois]['montant_deverse'] += (float) $ligne->montant;
            } elseif ($ligne->etat === EcritureComptable::ETAT_ANOMALIE) {
                $parMois[$mois]['anomalie'] += (int) $ligne->nombre;
                $parMois[$mois]['montant_anomalie'] += (float) $ligne->montant;
            } else {
                $parMois[$mois]['non_deverse'] += (int) $ligne->nombre;
                $parMois[$mois]['montant_non_deverse'] += (float) $ligne->montant;
            }
        }

        return array_values($parMois);
    }

    /**
     * 2. ÉTAT DÉVERSÉ / NON DÉVERSÉ PAR GRANDE FAMILLE ET PAR MOIS.
     * Les ventes seules : ce sont elles qui portent une grande famille.
     */
    public static function parFamilleEtMois(Carbon $du, Carbon $au): array
    {
        $lignes = self::lignes($du, $au)
            ->where('ligne_ecriture_comptable.rubrique', LigneEcritureComptable::PRODUIT)
            ->select(
                DB::raw("DATE_FORMAT(e.date_ecriture, '%Y-%m') AS mois"),
                'ligne_ecriture_comptable.categorie_id',
                'e.etat',
                DB::raw('COUNT(DISTINCT e.id) AS nombre'),
                DB::raw('SUM(ligne_ecriture_comptable.credit - ligne_ecriture_comptable.debit) AS montant')
            )->groupBy('mois', 'ligne_ecriture_comptable.categorie_id', 'e.etat')->get();

        $familles = Categorie::withTrashed()->pluck('nom', 'id');
        $resultat = [];
        foreach ($lignes as $ligne) {
            $cle = $ligne->mois . '|' . (int) $ligne->categorie_id;
            $resultat[$cle] ??= [
                'mois' => $ligne->mois, 'libelle_mois' => self::libelleMois($ligne->mois),
                'famille' => $familles[$ligne->categorie_id] ?? 'Sans grande famille',
                'deverse' => 0.0, 'nombre_deverse' => 0, 'non_deverse' => 0.0, 'nombre_non_deverse' => 0,
            ];
            if ($ligne->etat === EcritureComptable::ETAT_EXPORTEE) {
                $resultat[$cle]['deverse'] += (float) $ligne->montant;
                $resultat[$cle]['nombre_deverse'] += (int) $ligne->nombre;
            } else {
                $resultat[$cle]['non_deverse'] += (float) $ligne->montant;
                $resultat[$cle]['nombre_non_deverse'] += (int) $ligne->nombre;
            }
        }
        foreach ($resultat as &$ligne) {
            $ligne['total'] = $ligne['deverse'] + $ligne['non_deverse'];
        }

        return array_values($resultat);
    }

    /**
     * 3. SOLDES DES COMPTES — sur la période : ouverture, mouvements, clôture,
     * et la part qui n'est pas encore partie au logiciel comptable.
     */
    public static function soldesDesComptes(Carbon $du, Carbon $au): array
    {
        $ouvertures = self::lignes(null, $du->copy()->subDay())
            ->select('ligne_ecriture_comptable.numero_compte', DB::raw('SUM(debit - credit) AS solde'))
            ->groupBy('ligne_ecriture_comptable.numero_compte')->pluck('solde', 'numero_compte');

        $mouvements = self::lignes($du, $au)
            ->select(
                'ligne_ecriture_comptable.numero_compte',
                DB::raw('SUM(debit) AS debit'),
                DB::raw('SUM(credit) AS credit'),
                DB::raw("SUM(CASE WHEN e.etat = 'EXPORTEE' THEN debit - credit ELSE 0 END) AS deverse"),
                DB::raw("SUM(CASE WHEN e.etat <> 'EXPORTEE' THEN debit - credit ELSE 0 END) AS attente")
            )->groupBy('ligne_ecriture_comptable.numero_compte')->get();

        $libelles = CompteComptable::withTrashed()->pluck('libelle', 'numero');
        $soldes = [];
        foreach ($mouvements as $mouvement) {
            $numero = (string) $mouvement->numero_compte;
            $ouverture = (float) ($ouvertures[$numero] ?? 0);
            $soldes[] = [
                'compte'    => $numero ?: '(sans compte)',
                'libelle'   => $libelles[$numero] ?? '—',
                'ouverture' => $ouverture,
                'debit'     => (float) $mouvement->debit,
                'credit'    => (float) $mouvement->credit,
                'cloture'   => $ouverture + (float) $mouvement->debit - (float) $mouvement->credit,
                'deverse'   => (float) $mouvement->deverse,
                'attente'   => (float) $mouvement->attente,
            ];
        }
        usort($soldes, fn ($a, $b) => strcmp($a['compte'], $b['compte']));

        return $soldes;
    }

    /**
     * 4. GRAND LIVRE D'UN COMPTE — toutes ses écritures de la période, dans
     * l'ordre, avec le solde qui avance ligne après ligne.
     */
    public static function grandLivre(string $compte, Carbon $du, Carbon $au): array
    {
        $ouverture = (float) self::lignes(null, $du->copy()->subDay())
            ->where('ligne_ecriture_comptable.numero_compte', $compte)
            ->sum(DB::raw('debit - credit'));

        $lignes = self::lignes($du, $au)
            ->where('ligne_ecriture_comptable.numero_compte', $compte)
            ->orderBy('e.date_ecriture')->orderBy('e.id')->orderBy('ligne_ecriture_comptable.rang')
            ->select('ligne_ecriture_comptable.*', 'e.date_ecriture', 'e.piece', 'e.reference_fne', 'e.journal_code',
                     'e.identifiant', 'e.etat', 'e.id AS ecriture_id')
            ->get();

        $solde = $ouverture;
        $mouvements = [];
        foreach ($lignes as $ligne) {
            $solde += (float) $ligne->debit - (float) $ligne->credit;
            $mouvements[] = [
                'ecriture_id' => (int) $ligne->ecriture_id,
                'identifiant' => $ligne->identifiant,
                'date'        => Carbon::parse($ligne->date_ecriture),
                'journal'     => $ligne->journal_code,
                'piece'       => $ligne->piece,
                'reference'   => $ligne->reference_fne,
                'libelle'     => $ligne->libelle,
                'tiers'       => $ligne->compte_tiers,
                'lettre'      => $ligne->lettre,
                'debit'       => (float) $ligne->debit,
                'credit'      => (float) $ligne->credit,
                'solde'       => $solde,
                'deverse'     => $ligne->etat === EcritureComptable::ETAT_EXPORTEE,
            ];
        }

        return [
            'compte'     => $compte,
            'libelle'    => CompteComptable::withTrashed()->where('numero', $compte)->value('libelle') ?: '—',
            'ouverture'  => $ouverture,
            'mouvements' => $mouvements,
            'debit'      => array_sum(array_column($mouvements, 'debit')),
            'credit'     => array_sum(array_column($mouvements, 'credit')),
            'cloture'    => $solde,
        ];
    }

    /**
     * 5. BALANCE — un compte par ligne. Trois déclinaisons : générale (les
     * comptes), des tiers (les comptes tiers), analytique (les codes produits).
     */
    public static function balance(Carbon $du, Carbon $au, string $sorte = 'generale'): array
    {
        $colonne = ['generale' => 'numero_compte', 'tiers' => 'compte_tiers', 'analytique' => 'numero_analytique'][$sorte] ?? 'numero_compte';

        $ouvertures = self::lignes(null, $du->copy()->subDay())
            ->whereNotNull('ligne_ecriture_comptable.' . $colonne)->where('ligne_ecriture_comptable.' . $colonne, '!=', '')
            ->select('ligne_ecriture_comptable.' . $colonne . ' AS cle', DB::raw('SUM(debit - credit) AS solde'))
            ->groupBy('cle')->pluck('solde', 'cle');

        $mouvements = self::lignes($du, $au)
            ->whereNotNull('ligne_ecriture_comptable.' . $colonne)->where('ligne_ecriture_comptable.' . $colonne, '!=', '')
            ->select('ligne_ecriture_comptable.' . $colonne . ' AS cle',
                     DB::raw('SUM(debit) AS debit'), DB::raw('SUM(credit) AS credit'))
            ->groupBy('cle')->orderBy('cle')->get();

        $libelles = $sorte === 'generale' || $sorte === 'analytique'
            ? CompteComptable::withTrashed()->pluck('libelle', 'numero')
            : collect();

        $balance = [];
        foreach ($mouvements as $mouvement) {
            $ouverture = (float) ($ouvertures[$mouvement->cle] ?? 0);
            $cloture = $ouverture + (float) $mouvement->debit - (float) $mouvement->credit;
            $balance[] = [
                'cle'          => (string) $mouvement->cle,
                'libelle'      => $libelles[$mouvement->cle] ?? self::libelleDuTiers($sorte, (string) $mouvement->cle),
                'ouverture'    => $ouverture,
                'debit'        => (float) $mouvement->debit,
                'credit'       => (float) $mouvement->credit,
                'solde_debit'  => $cloture > 0 ? $cloture : 0.0,
                'solde_credit' => $cloture < 0 ? -$cloture : 0.0,
            ];
        }

        return $balance;
    }

    /**
     * 6. BALANCE CONSOLIDÉE — les mêmes totaux, regroupés par classe de
     * comptes (le premier chiffre) et, pour les ventes, par grande famille.
     */
    public static function balanceConsolidee(Carbon $du, Carbon $au): array
    {
        $classes = [];
        foreach (self::balance($du, $au, 'generale') as $ligne) {
            $classe = substr($ligne['cle'], 0, 1) ?: '?';
            $classes[$classe] ??= ['classe' => $classe, 'libelle' => self::libelleClasse($classe),
                'comptes' => 0, 'debit' => 0.0, 'credit' => 0.0, 'solde_debit' => 0.0, 'solde_credit' => 0.0];
            $classes[$classe]['comptes']++;
            foreach (['debit', 'credit', 'solde_debit', 'solde_credit'] as $champ) {
                $classes[$classe][$champ] += $ligne[$champ];
            }
        }
        ksort($classes);

        $familles = self::lignes($du, $au)
            ->where('ligne_ecriture_comptable.rubrique', LigneEcritureComptable::PRODUIT)
            ->select('ligne_ecriture_comptable.categorie_id', DB::raw('SUM(credit - debit) AS montant'), DB::raw('COUNT(DISTINCT e.id) AS nombre'))
            ->groupBy('ligne_ecriture_comptable.categorie_id')->get();
        $noms = Categorie::withTrashed()->pluck('nom', 'id');

        return [
            'classes'  => array_values($classes),
            'familles' => $familles->map(fn ($f) => [
                'famille' => $noms[$f->categorie_id] ?? 'Sans grande famille',
                'nombre'  => (int) $f->nombre,
                'montant' => (float) $f->montant,
            ])->sortByDesc('montant')->values()->all(),
        ];
    }

    // ================================================================== rapports complémentaires

    /**
     * 7. RAPPROCHEMENT FNE / ÉCRITURES — le contrôle de complétude : chaque
     * facture certifiée de la période a-t-elle son écriture ?
     */
    public static function rapprochementFne(Carbon $du, Carbon $au): array
    {
        $factures = Facture::withTrashed()->where('fne_status', 'certified')
            ->whereBetween('fne_certified_at', [$du->copy()->startOfDay(), $au->copy()->endOfDay()])
            ->orderBy('fne_certified_at')->get();

        $ecritures = EcritureComptable::where('source_type', 'facture')
            ->whereIn('source_id', $factures->pluck('id'))
            ->whereNull('annulee_par_id')->where('origine', '!=', EcritureComptable::ORIGINE_ANNULATION)
            ->get()->keyBy('source_id');

        $lignes = [];
        foreach ($factures as $facture) {
            $ecriture = $ecritures[$facture->id] ?? null;
            $montant = abs((float) $facture->montant);
            $ecart = $ecriture ? round($montant - (float) $ecriture->total_debit, 2) : null;
            $lignes[] = [
                'facture'     => $facture->numero,
                'reference'   => $facture->fne_reference ?: $facture->numero_fne,
                'date'        => $facture->fne_certified_at,
                'type'        => $facture->estUnAvoir() ? 'Avoir' : 'Facture',
                'montant'     => $montant,
                'ecriture'    => $ecriture,
                'etat'        => $ecriture === null ? 'ABSENTE' : ($ecriture->etat === EcritureComptable::ETAT_ANOMALIE ? 'ANOMALIE' : (abs($ecart) > 1 ? 'ECART' : 'CONFORME')),
                'ecart'       => $ecart,
            ];
        }

        return $lignes;
    }

    /** 8. HISTORIQUE DES ANOMALIES — ouvertes, corrigées, et le délai de correction. */
    public static function historiqueAnomalies(Carbon $du, Carbon $au): array
    {
        $anomalies = AnomalieComptable::with('ecriture')
            ->whereIn('ecriture_comptable_id', EcritureComptable::whereBetween('date_ecriture', [$du->toDateString(), $au->toDateString()])->pluck('id'))
            ->get();

        $parCode = [];
        foreach ($anomalies as $anomalie) {
            $code = $anomalie->code;
            $parCode[$code] ??= ['code' => $code, 'colonne' => $anomalie->colonne, 'onglet' => $anomalie->onglet,
                'exemple' => $anomalie->cause, 'ouvertes' => 0, 'corrigees' => 0, 'delais' => []];
            if ($anomalie->resolue_le) {
                $parCode[$code]['corrigees']++;
                $parCode[$code]['delais'][] = $anomalie->created_at?->diffInHours($anomalie->resolue_le) ?? 0;
            } else {
                $parCode[$code]['ouvertes']++;
            }
        }
        foreach ($parCode as &$ligne) {
            $ligne['delai_moyen'] = $ligne['delais'] ? round(array_sum($ligne['delais']) / count($ligne['delais']), 1) : null;
            unset($ligne['delais']);
        }

        return array_values($parCode);
    }

    /** 9. CHIFFRE D'AFFAIRES par grande famille et par compte analytique. */
    public static function chiffreDAffaires(Carbon $du, Carbon $au): array
    {
        $lire = fn (Carbon $debut, Carbon $fin) => self::lignes($debut, $fin)
            ->where('ligne_ecriture_comptable.rubrique', LigneEcritureComptable::PRODUIT)
            ->select('ligne_ecriture_comptable.categorie_id', 'ligne_ecriture_comptable.numero_analytique',
                     DB::raw('SUM(credit - debit) AS montant'))
            ->groupBy('ligne_ecriture_comptable.categorie_id', 'ligne_ecriture_comptable.numero_analytique')->get();

        $periode    = $lire($du, $au);
        $precedente = $lire($du->copy()->subMonthNoOverflow()->startOfMonth(), $du->copy()->subMonthNoOverflow()->endOfMonth());
        $anPasse    = $lire($du->copy()->subYear(), $au->copy()->subYear());

        $cle = fn ($l) => (int) $l->categorie_id . '|' . (string) $l->numero_analytique;
        $avant = $precedente->keyBy($cle);
        $anDernier = $anPasse->keyBy($cle);
        $familles = Categorie::withTrashed()->pluck('nom', 'id');
        $libelles = CompteComptable::withTrashed()->pluck('libelle', 'numero');

        return $periode->map(fn ($ligne) => [
            'famille'          => $familles[$ligne->categorie_id] ?? 'Sans grande famille',
            'analytique'       => $ligne->numero_analytique ?: '—',
            'produit'          => $libelles[$ligne->numero_analytique] ?? '—',
            'montant'          => (float) $ligne->montant,
            'mois_precedent'   => (float) ($avant[$cle($ligne)]->montant ?? 0),
            'annee_precedente' => (float) ($anDernier[$cle($ligne)]->montant ?? 0),
        ])->sortByDesc('montant')->values()->all();
    }

    /** 10. ÉTAT DES TAXES COLLECTÉES — TVA et AIRSI, mois par mois. */
    public static function taxesCollectees(Carbon $du, Carbon $au): array
    {
        $lignes = self::lignes($du, $au)
            ->whereIn('ligne_ecriture_comptable.rubrique', [LigneEcritureComptable::TVA, LigneEcritureComptable::AIRSI])
            ->select(DB::raw("DATE_FORMAT(e.date_ecriture, '%Y-%m') AS mois"), 'ligne_ecriture_comptable.rubrique',
                     DB::raw('SUM(credit - debit) AS montant'))
            ->groupBy('mois', 'ligne_ecriture_comptable.rubrique')->orderBy('mois')->get();

        $parMois = [];
        foreach ($lignes as $ligne) {
            $parMois[$ligne->mois] ??= ['mois' => $ligne->mois, 'libelle' => self::libelleMois($ligne->mois), 'tva' => 0.0, 'airsi' => 0.0];
            $parMois[$ligne->mois][$ligne->rubrique === LigneEcritureComptable::TVA ? 'tva' : 'airsi'] += (float) $ligne->montant;
        }
        foreach ($parMois as &$ligne) {
            $ligne['total'] = $ligne['tva'] + $ligne['airsi'];
        }

        return array_values($parMois);
    }

    /**
     * 11. SITUATION DES CLIENTS — par compte tiers : facturé, encaissé, solde
     * dû et ancienneté de ce qui reste.
     */
    public static function situationDesClients(Carbon $au): array
    {
        $lignes = self::lignes(null, $au)
            ->where('ligne_ecriture_comptable.rubrique', LigneEcritureComptable::CLIENT)
            ->whereNotNull('ligne_ecriture_comptable.compte_tiers')->where('ligne_ecriture_comptable.compte_tiers', '!=', '')
            ->select('ligne_ecriture_comptable.compte_tiers', 'ligne_ecriture_comptable.lettre', 'e.date_ecriture',
                     'e.client_id', 'ligne_ecriture_comptable.debit', 'ligne_ecriture_comptable.credit')
            ->get();

        $clients = \App\Models\Client::withTrashed()->get()->keyBy('id');
        $tranches = [30, 60, 90];
        $parTiers = [];
        foreach ($lignes as $ligne) {
            $tiers = (string) $ligne->compte_tiers;
            $parTiers[$tiers] ??= [
                'compte' => $tiers, 'client' => '—', 'facture' => 0.0, 'encaisse' => 0.0,
                'solde' => 0.0, 'moins_30' => 0.0, 'de_30_60' => 0.0, 'de_60_90' => 0.0, 'plus_90' => 0.0, 'non_lettre' => 0.0,
            ];
            if ($ligne->client_id && isset($clients[$ligne->client_id]) && $parTiers[$tiers]['client'] === '—') {
                $parTiers[$tiers]['client'] = $clients[$ligne->client_id]->display_name ?: ('Client n° ' . $ligne->client_id);
            }
            $parTiers[$tiers]['facture']  += (float) $ligne->debit;
            $parTiers[$tiers]['encaisse'] += (float) $ligne->credit;

            // L'ancienneté ne se calcule que sur ce qui n'est pas soldé : une
            // ligne lettrée est réglée, elle ne doit plus rien.
            $reste = (float) $ligne->debit - (float) $ligne->credit;
            if (!$ligne->lettre && $reste > 0) {
                $jours = Carbon::parse($ligne->date_ecriture)->diffInDays($au);
                $champ = $jours < $tranches[0] ? 'moins_30' : ($jours < $tranches[1] ? 'de_30_60' : ($jours < $tranches[2] ? 'de_60_90' : 'plus_90'));
                $parTiers[$tiers][$champ] += $reste;
                $parTiers[$tiers]['non_lettre'] += $reste;
            }
        }
        foreach ($parTiers as &$ligne) {
            $ligne['solde'] = $ligne['facture'] - $ligne['encaisse'];
        }

        return collect($parTiers)->sortByDesc('solde')->values()->all();
    }

    /** 12. TRÉSORERIE PAR MODE DE RÈGLEMENT — entrées et sorties, par journal et par mois. */
    public static function tresorerie(Carbon $du, Carbon $au): array
    {
        $lignes = self::lignes($du, $au)
            ->where('ligne_ecriture_comptable.rubrique', 'TRESORERIE')
            ->select(DB::raw("DATE_FORMAT(e.date_ecriture, '%Y-%m') AS mois"), 'e.journal_code',
                     DB::raw('SUM(debit) AS entrees'), DB::raw('SUM(credit) AS sorties'))
            ->groupBy('mois', 'e.journal_code')->orderBy('mois')->orderBy('e.journal_code')->get();

        $journaux = JournalComptable::withTrashed()->pluck('libelle', 'code');

        return $lignes->map(fn ($ligne) => [
            'mois'    => $ligne->mois,
            'libelle_mois' => self::libelleMois($ligne->mois),
            'journal' => $ligne->journal_code ?: '—',
            'nom'     => $journaux[$ligne->journal_code] ?? '—',
            'entrees' => (float) $ligne->entrees,
            'sorties' => (float) $ligne->sorties,
            'solde'   => (float) $ligne->entrees - (float) $ligne->sorties,
        ])->all();
    }

    /** 13. JOURNAL DES VENTES — la présentation classique, prête à imprimer. */
    public static function journalDesVentes(Carbon $du, Carbon $au)
    {
        $codes = JournalComptable::withTrashed()->where('type', JournalComptable::TYPE_VENTES)->pluck('code');

        return EcritureComptable::with('lignes')
            ->whereBetween('date_ecriture', [$du->toDateString(), $au->toDateString()])
            ->where('etat', '!=', EcritureComptable::ETAT_ANOMALIE)
            ->whereIn('journal_code', $codes)
            ->orderBy('date_ecriture')->orderBy('id')->get();
    }

    // ================================================================== outils

    /** Les comptes qui ont bougé : la liste du choix « grand livre ». */
    public static function comptesMouvementes(Carbon $du, Carbon $au): Collection
    {
        $numeros = self::lignes($du, $au)
            ->whereNotNull('ligne_ecriture_comptable.numero_compte')->where('ligne_ecriture_comptable.numero_compte', '!=', '')
            ->distinct()->orderBy('ligne_ecriture_comptable.numero_compte')
            ->pluck('ligne_ecriture_comptable.numero_compte');

        $libelles = CompteComptable::withTrashed()->pluck('libelle', 'numero');

        return $numeros->map(fn ($numero) => ['numero' => $numero, 'libelle' => $libelles[$numero] ?? '—']);
    }

    /** Le total d'un tableau de rapport, colonne par colonne. */
    public static function totaux(array $lignes, array $colonnes): array
    {
        $totaux = array_fill_keys($colonnes, 0.0);
        foreach ($lignes as $ligne) {
            foreach ($colonnes as $colonne) {
                $totaux[$colonne] += (float) ($ligne[$colonne] ?? 0);
            }
        }

        return $totaux;
    }

    private static function libelleMois(string $mois): string
    {
        return \Help::phrase(Carbon::createFromFormat('Y-m-d', $mois . '-01')->locale('fr')->isoFormat('MMMM YYYY'));
    }

    private static function libelleDuTiers(string $sorte, string $cle): string
    {
        if ($sorte !== 'tiers') {
            return '—';
        }
        foreach ([\App\Models\Client::class, \App\Models\Fournisseur::class, \App\Models\Livreur::class, \App\Models\Apporteur::class] as $classe) {
            $tiers = $classe::withTrashed()->where('compte_tiers', $cle)->first();
            if ($tiers) {
                return $classe === \App\Models\Client::class
                    ? ($tiers->display_name ?: 'Client n° ' . $tiers->id)
                    : MoteurTresorerie::nomDuPartenaire(strtolower(class_basename($classe)), $tiers);
            }
        }

        return '—';
    }

    /** Les classes du plan comptable SYSCOHADA, pour la balance consolidée. */
    private static function libelleClasse(string $classe): string
    {
        return [
            '1' => 'Ressources durables', '2' => 'Actif immobilisé', '3' => 'Stocks',
            '4' => 'Tiers', '5' => 'Trésorerie', '6' => 'Charges', '7' => 'Produits',
            '8' => 'Autres charges et produits', '9' => 'Comptabilité analytique',
        ][$classe] ?? 'Autres';
    }

    /** Les rubriques qui n'ont pas de compte : un rapport vide s'explique souvent ainsi. */
    public static function rubriquesSansCompte(): Collection
    {
        return RubriqueComptable::toutes()->filter(fn ($r) => !$r->compte_comptable_id && !$r->estFacultative());
    }
}
