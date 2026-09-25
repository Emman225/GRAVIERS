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
            $cloture = $ouverture + (float) $mouvement->debit - (float) $mouvement->credit;
            $soldes[] = [
                'compte'    => $numero ?: '(sans compte)',
                'libelle'   => $libelles[$numero] ?? '—',
                'ouverture' => $ouverture,
                // Un comptable ne lit pas un solde négatif : il lit un solde
                // créditeur. L'ouverture et la clôture sortent donc en deux
                // colonnes, comme les mouvements.
                'ouverture_debit'  => $ouverture > 0 ? $ouverture : 0.0,
                'ouverture_credit' => $ouverture < 0 ? -$ouverture : 0.0,
                'debit'     => (float) $mouvement->debit,
                'credit'    => (float) $mouvement->credit,
                'cloture'   => $cloture,
                'cloture_debit'  => $cloture > 0 ? $cloture : 0.0,
                'cloture_credit' => $cloture < 0 ? -$cloture : 0.0,
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
     *
     * Trois déclinaisons, exactement comme la balance : par compte général,
     * par compte tiers, par compte analytique. Seule la clé change.
     */
    public static function grandLivre(string $compte, Carbon $du, Carbon $au, string $sorte = 'generale'): array
    {
        $colonne = 'ligne_ecriture_comptable.' . self::colonneDe($sorte);

        $ouverture = (float) self::lignes(null, $du->copy()->subDay())
            ->where($colonne, $compte)
            ->sum(DB::raw('debit - credit'));

        $lignes = self::lignes($du, $au)
            ->where($colonne, $compte)
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
            'sorte'      => $sorte,
            'compte'     => $compte,
            'libelle'    => self::libelleDeLaCle($sorte, $compte),
            'ouverture'  => $ouverture,
            'ouverture_debit'  => $ouverture > 0 ? $ouverture : 0.0,
            'ouverture_credit' => $ouverture < 0 ? -$ouverture : 0.0,
            'mouvements' => $mouvements,
            'debit'      => array_sum(array_column($mouvements, 'debit')),
            'credit'     => array_sum(array_column($mouvements, 'credit')),
            'cloture'    => $solde,
            'cloture_debit'  => $solde > 0 ? $solde : 0.0,
            'cloture_credit' => $solde < 0 ? -$solde : 0.0,
        ];
    }

    /**
     * 5. BALANCE — un compte par ligne. Trois déclinaisons : générale (les
     * comptes), des tiers (les comptes tiers), analytique (les codes produits).
     */
    public static function balance(Carbon $du, Carbon $au, string $sorte = 'generale'): array
    {
        $colonne = self::colonneDe($sorte);

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
                'ouverture_debit'  => $ouverture > 0 ? $ouverture : 0.0,
                'ouverture_credit' => $ouverture < 0 ? -$ouverture : 0.0,
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
                'comptes' => 0, 'ouverture_debit' => 0.0, 'ouverture_credit' => 0.0,
                'debit' => 0.0, 'credit' => 0.0, 'solde_debit' => 0.0, 'solde_credit' => 0.0];
            $classes[$classe]['comptes']++;
            foreach (['ouverture_debit', 'ouverture_credit', 'debit', 'credit', 'solde_debit', 'solde_credit'] as $champ) {
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
        $parTiers = [];
        foreach ($lignes as $ligne) {
            $tiers = (string) $ligne->compte_tiers;
            $parTiers[$tiers] ??= [
                'compte' => $tiers, 'client' => '—', 'facture' => 0.0, 'encaisse' => 0.0,
                'solde' => 0.0, 'moins_30' => 0.0, 'de_30_60' => 0.0, 'de_60_90' => 0.0,
                'de_90_120' => 0.0, 'plus_120' => 0.0, 'non_lettre' => 0.0,
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
                $parTiers[$tiers][self::trancheDAge((int) $jours)] += $reste;
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

    /**
     * 11b. DÉTAIL DES FACTURES DES CLIENTS (demande du 25/09/2026) — une ligne
     * par facture : ce qu'elle porte, ce qui a été réglé dessus, ce qui reste,
     * son âge, et le ou les moyens de paiement employés.
     *
     * Une écriture de règlement ne désigne pas une facture : elle désigne une
     * AFFAIRE, qui peut en porter plusieurs (voir la règle « plusieurs factures
     * par commande »). L'imputation suit donc trois temps :
     *   1. la facture que le règlement NOMME (paiement.facture_id), si elle doit encore ;
     *   2. sinon la plus ANCIENNE facture encore due de la même affaire ;
     *   3. sinon la plus ancienne facture encore due du client.
     * Un avoir et une annulation sont des crédits comme les autres : ils
     * s'imputent de la même façon, et portent leur nom en guise de moyen.
     */
    public static function facturesDesClients(Carbon $au): array
    {
        // Le même filtre que la situation des clients, pour que le détail et le
        // récapitulatif ne puissent pas se contredire.
        $mouvements = self::lignes(null, $au)
            ->where('ligne_ecriture_comptable.rubrique', LigneEcritureComptable::CLIENT)
            ->whereNotNull('ligne_ecriture_comptable.compte_tiers')->where('ligne_ecriture_comptable.compte_tiers', '!=', '')
            ->orderBy('e.date_ecriture')->orderBy('e.id')
            ->select('ligne_ecriture_comptable.compte_tiers', 'ligne_ecriture_comptable.debit', 'ligne_ecriture_comptable.credit',
                     'e.id AS ecriture_id', 'e.origine', 'e.source_type', 'e.source_id', 'e.date_ecriture',
                     'e.piece', 'e.reference_fne', 'e.numero_affaire', 'e.client_id')
            ->get();

        $reglements = $mouvements->filter(fn ($m) => (float) $m->credit > 0 && $m->source_type === 'ligne_paiement');
        $moyens = self::moyensDesReglements($reglements->pluck('source_id')->filter()->unique()->all());
        $clients = \App\Models\Client::withTrashed()->get()->keyBy('id');

        $parTiers = [];
        foreach ($mouvements as $m) {
            $parTiers[(string) $m->compte_tiers][] = $m;
        }

        $resultat = [];
        foreach ($parTiers as $tiers => $lignes) {
            $factures = [];
            $credits = [];
            foreach ($lignes as $m) {
                if ((float) $m->debit > 0) {
                    $factures[] = [
                        'compte'      => $tiers,
                        'client'      => ($m->client_id && isset($clients[$m->client_id]))
                            ? ($clients[$m->client_id]->display_name ?: 'Client n° ' . $m->client_id) : '—',
                        'ecriture_id' => (int) $m->ecriture_id,
                        'facture_id'  => $m->source_type === 'facture' ? (int) $m->source_id : null,
                        'numero'      => (string) $m->piece,
                        'reference'   => (string) ($m->reference_fne ?: ''),
                        'affaire'     => (string) ($m->numero_affaire ?: ''),
                        'date'        => Carbon::parse($m->date_ecriture),
                        'montant'     => (float) $m->debit,
                        'regle'       => 0.0,
                        'moyens'      => [],
                    ];
                } elseif ((float) $m->credit > 0) {
                    $credits[] = $m;
                }
            }

            foreach ($credits as $credit) {
                $reste = (float) $credit->credit;
                $moyen = $m2 = null;
                if ($credit->source_type === 'ligne_paiement' && isset($moyens[(int) $credit->source_id])) {
                    $moyen = $moyens[(int) $credit->source_id]->mode;
                    $m2 = $moyens[(int) $credit->source_id]->facture_id;
                }
                $moyen = $moyen ?: (EcritureComptable::ORIGINES[$credit->origine] ?? 'Règlement');

                foreach (self::ordreDImputation($factures, $m2, (string) ($credit->numero_affaire ?: '')) as $i) {
                    if ($reste <= 0.004) {
                        break;
                    }
                    $du = round($factures[$i]['montant'] - $factures[$i]['regle'], 2);
                    if ($du <= 0.004) {
                        continue;
                    }
                    $impute = min($du, $reste);
                    $factures[$i]['regle'] += $impute;
                    $reste -= $impute;
                    if (!in_array($moyen, $factures[$i]['moyens'], true)) {
                        $factures[$i]['moyens'][] = $moyen;
                    }
                }
            }

            foreach ($factures as $facture) {
                $reste = round($facture['montant'] - $facture['regle'], 2);
                $jours = (int) Carbon::parse($facture['date'])->diffInDays($au);
                $resultat[] = $facture + [
                    'reste'  => $reste,
                    'jours'  => $jours,
                    'tranche' => $reste > 0.004 ? self::libelleTranche(self::trancheDAge($jours)) : '—',
                    'moyen'  => $facture['moyens'] ? implode(', ', $facture['moyens']) : '—',
                ];
            }
        }

        return collect($resultat)->sortByDesc('reste')->values()->all();
    }

    /** L'ordre dans lequel un règlement cherche la facture à solder. */
    private static function ordreDImputation(array $factures, $factureNommee, string $affaire): array
    {
        $nommee = [];
        $memeAffaire = [];
        $autres = [];
        foreach ($factures as $i => $facture) {
            if ($factureNommee && (int) $facture['facture_id'] === (int) $factureNommee) {
                $nommee[] = $i;
            } elseif ($affaire !== '' && $facture['affaire'] === $affaire) {
                $memeAffaire[] = $i;
            } else {
                $autres[] = $i;
            }
        }

        // $factures est déjà rangé par date : chaque groupe reste du plus
        // ancien au plus récent.
        return array_merge($nommee, $memeAffaire, $autres);
    }

    /** Le moyen de paiement et la facture nommée de chaque règlement, en une requête. */
    private static function moyensDesReglements(array $ids)
    {
        if (!$ids) {
            return collect();
        }

        return DB::table('ligne_paiement')
            ->leftJoin('mode_paiement', 'mode_paiement.id', '=', 'ligne_paiement.mode_paiement_id')
            ->leftJoin('paiement', 'paiement.id', '=', 'ligne_paiement.paiement_id')
            ->whereIn('ligne_paiement.id', $ids)
            ->select('ligne_paiement.id',
                     DB::raw('COALESCE(NULLIF(mode_paiement.libelle, \'\'), ligne_paiement.moyen_paiement) AS mode'),
                     'paiement.facture_id')
            ->get()->keyBy('id');
    }

    /** Le nom lisible d'une tranche d'ancienneté. */
    private static function libelleTranche(string $champ): string
    {
        return [
            'moins_30' => 'Moins de 30 j', 'de_30_60' => '30 à 60 j', 'de_60_90' => '60 à 90 j',
            'de_90_120' => '90 à 120 j', 'plus_120' => 'Plus de 120 j',
        ][$champ] ?? $champ;
    }

    /**
     * 10b. DÉTAIL DES TAXES, FACTURE PAR FACTURE (demande du 25/09/2026).
     *
     * Tout se calcule depuis l'écriture : le HT est la somme des produits, du
     * transport et de la remise (qui est au débit, donc se retranche) ; le TTC
     * est le débit du compte client, c'est-à-dire ce que le client doit
     * vraiment. Les totaux du détail retombent sur ceux du récapitulatif
     * mensuel — c'est ce qui en fait aussi un contrôle.
     */
    public static function detailDesTaxes(Carbon $du, Carbon $au): array
    {
        $lignes = self::lignes($du, $au)
            ->orderBy('e.date_ecriture')->orderBy('e.id')
            ->select('ligne_ecriture_comptable.rubrique', 'ligne_ecriture_comptable.debit', 'ligne_ecriture_comptable.credit',
                     'e.id AS ecriture_id', 'e.date_ecriture', 'e.piece', 'e.reference_fne', 'e.origine', 'e.client_id', 'e.numero_affaire')
            ->get();

        $clients = \App\Models\Client::withTrashed()->get()->keyBy('id');
        $factures = [];
        foreach ($lignes as $ligne) {
            $id = (int) $ligne->ecriture_id;
            $factures[$id] ??= [
                'ecriture_id' => $id,
                'date'        => Carbon::parse($ligne->date_ecriture),
                'numero'      => (string) $ligne->piece,
                'reference'   => (string) ($ligne->reference_fne ?: ''),
                'origine'     => EcritureComptable::ORIGINES[$ligne->origine] ?? $ligne->origine,
                'client'      => ($ligne->client_id && isset($clients[$ligne->client_id]))
                    ? ($clients[$ligne->client_id]->display_name ?: 'Client n° ' . $ligne->client_id) : '—',
                'affaire'     => (string) ($ligne->numero_affaire ?: ''),
                'ht' => 0.0, 'tva' => 0.0, 'airsi' => 0.0, 'ttc' => 0.0,
            ];
            $net = (float) $ligne->credit - (float) $ligne->debit;
            if (in_array($ligne->rubrique, [LigneEcritureComptable::PRODUIT, LigneEcritureComptable::TRANSPORT, LigneEcritureComptable::REMISE], true)) {
                $factures[$id]['ht'] += $net;
            } elseif ($ligne->rubrique === LigneEcritureComptable::TVA) {
                $factures[$id]['tva'] += $net;
            } elseif ($ligne->rubrique === LigneEcritureComptable::AIRSI) {
                $factures[$id]['airsi'] += $net;
            } elseif ($ligne->rubrique === LigneEcritureComptable::CLIENT) {
                $factures[$id]['ttc'] -= $net;   // le client est au débit
            }
        }

        // Seules les écritures qui portent une taxe : ce sont exactement celles
        // que compte le récapitulatif mensuel.
        return collect($factures)
            ->filter(fn ($f) => abs($f['tva']) > 0.004 || abs($f['airsi']) > 0.004)
            ->values()->all();
    }

    /**
     * 12b. DÉTAIL DES OPÉRATIONS DE TRÉSORERIE (demande du 25/09/2026) — une
     * ligne par mouvement, avec le bénéficiaire ou le client. Le récapitulatif
     * par journal reste au-dessus : c'est lui qui donne la vue d'ensemble.
     */
    public static function detailDeTresorerie(Carbon $du, Carbon $au): array
    {
        $lignes = self::lignes($du, $au)
            ->where('ligne_ecriture_comptable.rubrique', 'TRESORERIE')
            ->orderBy('e.date_ecriture')->orderBy('e.id')
            ->select('ligne_ecriture_comptable.debit', 'ligne_ecriture_comptable.credit', 'ligne_ecriture_comptable.libelle',
                     'e.id AS ecriture_id', 'e.date_ecriture', 'e.journal_code', 'e.origine', 'e.piece',
                     'e.tiers_type', 'e.tiers_id', 'e.client_id', 'e.numero_affaire')
            ->get();

        $journaux = JournalComptable::withTrashed()->pluck('libelle', 'code');
        $noms = self::nomsDesTiers($lignes);

        return $lignes->map(fn ($ligne) => [
            'ecriture_id'  => (int) $ligne->ecriture_id,
            'date'         => Carbon::parse($ligne->date_ecriture),
            'mois'         => Carbon::parse($ligne->date_ecriture)->format('Y-m'),
            'journal'      => $ligne->journal_code ?: '—',
            'nom_journal'  => $journaux[$ligne->journal_code] ?? '—',
            'beneficiaire' => $noms[$ligne->tiers_type . '|' . $ligne->tiers_id] ?? '—',
            'nature'       => EcritureComptable::ORIGINES[$ligne->origine] ?? $ligne->origine,
            'piece'        => (string) $ligne->piece,
            'affaire'      => (string) ($ligne->numero_affaire ?: ''),
            'libelle'      => (string) $ligne->libelle,
            'entree'       => (float) $ligne->debit,
            'sortie'       => (float) $ligne->credit,
        ])->all();
    }

    /**
     * Le nom de chaque tiers cité, chargé en une fois par famille : une requête
     * par famille, jamais une par ligne.
     */
    private static function nomsDesTiers(Collection $lignes): array
    {
        $classes = [
            'client'      => \App\Models\Client::class,
            'fournisseur' => \App\Models\Fournisseur::class,
            'livreur'     => \App\Models\Livreur::class,
            'apporteur'   => \App\Models\Apporteur::class,
        ];

        $noms = [];
        foreach ($classes as $type => $classe) {
            $ids = $lignes->where('tiers_type', $type)->pluck('tiers_id')->filter()->unique()->all();
            if (!$ids) {
                continue;
            }
            foreach ($classe::withTrashed()->whereIn('id', $ids)->get() as $tiers) {
                $noms[$type . '|' . $tiers->id] = $type === 'client'
                    ? ($tiers->display_name ?: 'Client n° ' . $tiers->id)
                    : MoteurTresorerie::nomDuPartenaire($type, $tiers);
            }
        }

        return $noms;
    }

    /**
     * 1b. LE RÉSUMÉ DE CHAQUE DÉVERSEMENT (demande du 25/09/2026) : les
     * journaux qu'il couvre et le nombre de factures qu'il emporte.
     *
     * Un déversement n'a pas UN numéro de facture : c'est un envoi qui couvre
     * une période, donc autant de factures qu'elle en portait. On donne donc
     * leur NOMBRE ici, et leur liste s'ouvre au clic.
     */
    public static function resumeDesDeversements(array $ids): array
    {
        if (!$ids) {
            return [];
        }

        $lignes = EcritureComptable::whereIn('deversement_id', $ids)
            ->groupBy('deversement_id')
            ->select('deversement_id',
                     DB::raw("GROUP_CONCAT(DISTINCT journal_code ORDER BY journal_code SEPARATOR ', ') AS journaux"),
                     DB::raw("COUNT(DISTINCT CASE WHEN source_type = 'facture' THEN source_id END) AS factures"))
            ->get();

        $resume = [];
        foreach ($lignes as $ligne) {
            $resume[(int) $ligne->deversement_id] = [
                'journaux' => (string) ($ligne->journaux ?: '—'),
                'factures' => (int) $ligne->factures,
            ];
        }

        return $resume;
    }

    /**
     * 2b. LE CYCLE D'EXPLOITATION PAR GRANDE FAMILLE (demande du 25/09/2026).
     *
     * CE RAPPORT N'EST PAS COMPTABLE, et c'est voulu. « Commandé non livré » et
     * « livré non facturé » ne sont pas des écritures : une écriture ne naît
     * que d'une facture certifiée. Ces chiffres-là se lisent dans les commandes
     * et les bons d'enlèvement.
     *
     * Les conventions, arrêtées le 25/09/2026 :
     *  - tout est en HT, ligne de commande par ligne de commande ;
     *  - la période se lit sur la DATE DE COMMANDE, pour que les quatre étapes
     *    suivent la même marchandise ;
     *  - les commandes annulées sont hors du compte ;
     *  - « livré » suit la règle du site, celle de Help::marchandiseEnleveeSurCommande :
     *    ce qui est SERVI sur le bon d'enlèvement, pas ce qui était demandé.
     */
    public static function cycleParFamille(Carbon $du, Carbon $au): array
    {
        $commandes = DB::table('detail_commande as dc')
            ->join('commande as c', 'c.id', '=', 'dc.commande_id')
            ->leftJoin('produit as p', 'p.id', '=', 'dc.produit_id')
            ->whereNull('dc.deleted_at')->whereNull('c.deleted_at')
            ->where('c.etat_commande', '!=', \Help::$AFFAIRE_ANNULEE)
            ->whereDate('c.date_commande', '>=', $du->toDateString())
            ->whereDate('c.date_commande', '<=', $au->toDateString())
            ->groupBy('p.categorie_comptable_id')
            ->select('p.categorie_comptable_id AS famille',
                     DB::raw('COUNT(*) AS lignes'),
                     DB::raw('SUM(dc.qte * dc.prix) AS montant'))
            ->get();

        // Les sorties se totalisent en PHP : une jointure de plus sur les
        // écritures pourrait compter deux fois un bon dont la facture a été
        // réécrite (version 2).
        $sorties = DB::table('enlevement as e')
            ->join('livraison as l', 'l.id', '=', 'e.livraison_id')
            ->join('detail_commande as dc', 'dc.id', '=', 'l.detail_commande_id')
            ->join('commande as c', 'c.id', '=', 'dc.commande_id')
            ->leftJoin('produit as p', 'p.id', '=', 'dc.produit_id')
            ->whereNull('e.deleted_at')->whereNull('l.deleted_at')
            ->whereNull('dc.deleted_at')->whereNull('c.deleted_at')
            ->where('c.etat_commande', '!=', \Help::$AFFAIRE_ANNULEE)
            ->whereDate('c.date_commande', '>=', $du->toDateString())
            ->whereDate('c.date_commande', '<=', $au->toDateString())
            ->select('p.categorie_comptable_id AS famille', 'dc.prix',
                     'e.qte', 'e.qte_servi', 'e.fournisseur_validation', 'e.facture_id')
            ->get();

        $deversees = EcritureComptable::where('source_type', 'facture')
            ->where('etat', EcritureComptable::ETAT_EXPORTEE)
            ->pluck('source_id')->map(fn ($id) => (int) $id)->flip();

        $noms = Categorie::withTrashed()->pluck('nom', 'id');
        $lignes = [];
        $vide = [
            'lignes' => 0, 'commande' => 0.0, 'livre' => 0.0, 'facture' => 0.0, 'deverse' => 0.0,
            'bons' => 0, 'factures' => 0,
        ];

        foreach ($commandes as $c) {
            $cle = (int) $c->famille;
            $lignes[$cle] ??= $vide + ['famille' => $noms[$c->famille] ?? 'Sans grande famille'];
            $lignes[$cle]['lignes'] = (int) $c->lignes;
            $lignes[$cle]['commande'] = (float) $c->montant;
        }

        $facturesVues = [];
        foreach ($sorties as $s) {
            $cle = (int) $s->famille;
            $lignes[$cle] ??= $vide + ['famille' => $noms[$s->famille] ?? 'Sans grande famille'];

            // La convention du site : qte_servi à NULL sur un bon validé par le
            // fournisseur vaut la quantité demandée ; sinon rien n'est sorti.
            $servi = $s->qte_servi !== null
                ? (float) $s->qte_servi
                : (trim((string) $s->fournisseur_validation) !== '' ? (float) $s->qte : 0.0);
            $montant = $servi * (float) $s->prix;

            $lignes[$cle]['bons']++;
            $lignes[$cle]['livre'] += $montant;
            if ($s->facture_id) {
                $lignes[$cle]['facture'] += $montant;
                if (!isset($facturesVues[$cle][(int) $s->facture_id])) {
                    $facturesVues[$cle][(int) $s->facture_id] = true;
                    $lignes[$cle]['factures']++;
                }
                if ($deversees->has((int) $s->facture_id)) {
                    $lignes[$cle]['deverse'] += $montant;
                }
            }
        }

        foreach ($lignes as &$ligne) {
            $ligne['commande_non_livre']  = round($ligne['commande'] - $ligne['livre'], 2);
            $ligne['livre_non_facture']   = round($ligne['livre'] - $ligne['facture'], 2);
            $ligne['facture_non_deverse'] = round($ligne['facture'] - $ligne['deverse'], 2);
        }

        return collect($lignes)->sortByDesc('commande')->values()->all();
    }

    /** Les clés qui ont bougé : la liste du choix « grand livre », déclinaison comprise. */
    public static function comptesMouvementes(Carbon $du, Carbon $au, string $sorte = 'generale'): Collection
    {
        $colonne = 'ligne_ecriture_comptable.' . self::colonneDe($sorte);

        $numeros = self::lignes($du, $au)
            ->whereNotNull($colonne)->where($colonne, '!=', '')
            ->distinct()->orderBy($colonne)
            ->pluck($colonne);

        // Les libellés des comptes se lisent en une fois ; ceux des tiers se
        // cherchent un par un, mais une liste de choix reste courte.
        $libelles = $sorte === 'tiers' ? collect() : CompteComptable::withTrashed()->pluck('libelle', 'numero');

        return $numeros->map(fn ($numero) => [
            'numero'  => (string) $numero,
            'libelle' => $sorte === 'tiers' ? self::libelleDuTiers('tiers', (string) $numero) : ($libelles[$numero] ?? '—'),
        ]);
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

    /**
     * La colonne qui porte la clé d'une déclinaison. La balance et le grand
     * livre lisent les trois mêmes : le compte général, le compte tiers, le
     * compte analytique.
     */
    private static function colonneDe(string $sorte): string
    {
        return ['generale' => 'numero_compte', 'tiers' => 'compte_tiers', 'analytique' => 'numero_analytique'][$sorte] ?? 'numero_compte';
    }

    /** Le libellé d'une clé, selon la déclinaison. Une seule clé : une requête suffit. */
    private static function libelleDeLaCle(string $sorte, string $cle): string
    {
        if ($sorte === 'tiers') {
            return self::libelleDuTiers('tiers', $cle);
        }

        return CompteComptable::withTrashed()->where('numero', $cle)->value('libelle') ?: '—';
    }

    /**
     * Les tranches d'ancienneté d'une créance (demande du 25/09/2026 : cinq
     * tranches au lieu de quatre). La borne est le nombre de jours SOUS lequel
     * la tranche s'applique ; au-delà de la dernière, c'est « plus de 120 ».
     */
    private const TRANCHES_AGE = [30 => 'moins_30', 60 => 'de_30_60', 90 => 'de_60_90', 120 => 'de_90_120'];

    private static function trancheDAge(int $jours): string
    {
        foreach (self::TRANCHES_AGE as $limite => $champ) {
            if ($jours < $limite) {
                return $champ;
            }
        }

        return 'plus_120';
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
