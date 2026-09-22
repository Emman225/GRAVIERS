<?php

namespace App\Services\Comptabilite;

use App\Models\AnomalieComptable;
use App\Models\Configuration;
use App\Models\EcritureComptable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * La lecture des écritures : la période demandée, les écritures qu'elle
 * couvre, et ce qui empêche encore de les exporter.
 *
 * La période se choisit de deux façons — par dates, ou par mois et année — et
 * le dernier choix est mémorisé (configuration.periode_transmission_comptable).
 */
class JournalDesEcritures
{
    public const PAR_DATES = 'DATES';
    public const PAR_MOIS  = 'MOIS';

    /**
     * Lit la période demandée. Rend le mode, les deux bornes et les valeurs à
     * réafficher dans le formulaire.
     *
     * @return array{mode:string,du:Carbon,au:Carbon,mois:int,annee:int}
     */
    public static function periode(array $entrees): array
    {
        $memorise = Configuration::first()?->periode_transmission_comptable;
        $mode = $entrees['mode_periode'] ?? $memorise ?? self::PAR_MOIS;
        $mode = in_array($mode, [self::PAR_DATES, self::PAR_MOIS], true) ? $mode : self::PAR_MOIS;

        $aujourdhui = Carbon::today();
        if ($mode === self::PAR_MOIS) {
            // Le formulaire envoie « AAAA-MM » en un seul champ : deux champs séparés
            // se désaccordent dès qu'on change de mois sans changer d'année.
            if (preg_match('/^(\d{4})-(\d{2})$/', (string) ($entrees['periode'] ?? ''), $trouve)) {
                $entrees['annee'] = $trouve[1];
                $entrees['mois']  = $trouve[2];
            }
            $mois  = (int) ($entrees['mois'] ?? $aujourdhui->month);
            $annee = (int) ($entrees['annee'] ?? $aujourdhui->year);
            $mois  = ($mois >= 1 && $mois <= 12) ? $mois : $aujourdhui->month;
            $annee = ($annee >= 2000 && $annee <= 2100) ? $annee : $aujourdhui->year;
            $du = Carbon::create($annee, $mois, 1)->startOfDay();
            $au = $du->copy()->endOfMonth();
        } else {
            $du = self::date($entrees['du'] ?? null) ?: $aujourdhui->copy()->startOfMonth();
            $au = self::date($entrees['au'] ?? null) ?: $aujourdhui->copy()->endOfMonth();
            if ($au->lt($du)) {
                [$du, $au] = [$au, $du];
            }
            $mois  = $du->month;
            $annee = $du->year;
        }

        return ['mode' => $mode, 'du' => $du, 'au' => $au, 'mois' => $mois, 'annee' => $annee];
    }

    private static function date($valeur): ?Carbon
    {
        try {
            return $valeur ? Carbon::parse($valeur)->startOfDay() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Mémorise le mode de période choisi, pour la prochaine fois. */
    public static function memoriserLeMode(string $mode): void
    {
        $configuration = Configuration::first();
        if ($configuration && $configuration->periode_transmission_comptable !== $mode) {
            DB::table('configuration')->where('id', $configuration->id)->update(['periode_transmission_comptable' => $mode]);
        }
    }

    /**
     * Les écritures de la période. Les annulations y figurent comme les
     * autres : elles ont leur propre date, et le logiciel comptable les attend.
     *
     * @param array{etat?:?string,journal?:?int,origine?:?string,deversement?:?int} $filtres
     */
    public static function ecritures(Carbon $du, Carbon $au, array $filtres = [])
    {
        return EcritureComptable::with(['journal', 'client', 'lignes'])
            ->whereBetween('date_ecriture', [$du->toDateString(), $au->toDateString()])
            ->when(!empty($filtres['etat']), fn ($q) => $q->where('etat', $filtres['etat']))
            ->when(!empty($filtres['journal']), fn ($q) => $q->where('journal_comptable_id', $filtres['journal']))
            ->when(!empty($filtres['origine']), fn ($q) => $q->where('origine', $filtres['origine']))
            ->when(!empty($filtres['deversement']), fn ($q) => $q->where('deversement_id', $filtres['deversement']))
            ->orderBy('date_ecriture')->orderBy('id')
            ->get();
    }

    /** Les écritures prêtes à partir : dans la période, équilibrées, jamais exportées. */
    public static function aExporter(Carbon $du, Carbon $au)
    {
        return self::ecritures($du, $au, ['etat' => EcritureComptable::ETAT_A_EXPORTER]);
    }

    /** Ce qui empêche d'exporter la période : les anomalies ouvertes des écritures qu'elle couvre. */
    public static function anomalies(Carbon $du, Carbon $au)
    {
        return AnomalieComptable::with(['ecriture', 'facture'])
            ->whereNull('resolue_le')
            ->whereIn('ecriture_comptable_id', EcritureComptable::whereBetween('date_ecriture', [$du->toDateString(), $au->toDateString()])->pluck('id'))
            ->orderBy('code')->orderBy('id')
            ->get();
    }

    /** Le compte de ce que porte la période, par état. */
    public static function resume(Carbon $du, Carbon $au): array
    {
        $lignes = EcritureComptable::whereBetween('date_ecriture', [$du->toDateString(), $au->toDateString()])
            ->select('etat', DB::raw('COUNT(*) AS nombre'), DB::raw('SUM(total_debit) AS debit'), DB::raw('SUM(total_credit) AS credit'))
            ->groupBy('etat')->get()->keyBy('etat');

        $resume = [];
        foreach (array_keys(EcritureComptable::ETATS) as $etat) {
            $resume[$etat] = [
                'nombre' => (int) ($lignes[$etat]->nombre ?? 0),
                'debit'  => (float) ($lignes[$etat]->debit ?? 0),
                'credit' => (float) ($lignes[$etat]->credit ?? 0),
            ];
        }
        $resume['total'] = [
            'nombre' => array_sum(array_column($resume, 'nombre')),
            'debit'  => array_sum(array_column($resume, 'debit')),
            'credit' => array_sum(array_column($resume, 'credit')),
        ];

        return $resume;
    }

    /** Les douze derniers mois, pour le choix « par mois ». */
    public static function moisProposes(): array
    {
        $mois = [];
        for ($i = 0; $i < 12; $i++) {
            $date = Carbon::today()->startOfMonth()->subMonths($i);
            $mois[] = ['mois' => $date->month, 'annee' => $date->year, 'libelle' => \Help::phrase($date->locale('fr')->isoFormat('MMMM YYYY'))];
        }

        return $mois;
    }
}
