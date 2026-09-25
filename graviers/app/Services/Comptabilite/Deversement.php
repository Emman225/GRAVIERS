<?php

namespace App\Services\Comptabilite;

use App\Models\DeversementComptable;
use App\Models\EcritureComptable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * LA TRANSMISSION DES ÉCRITURES au logiciel comptable.
 *
 * Elle est toujours lancée par l'utilisateur (jamais à la clôture d'un mois :
 * réponse du responsable, question 8). Trois règles :
 *  - une période qui porte une anomalie ne part pas ;
 *  - une écriture transmise passe à « exportée » et ne changera plus jamais —
 *    toute correction ultérieure passe par une écriture d'annulation ;
 *  - un déversement rejeté rend ses écritures à l'état « à exporter », pour
 *    qu'elles puissent repartir.
 */
class Deversement
{
    /** Ce que la période enverrait, et ce qui l'en empêche. */
    public static function apercu(Carbon $du, Carbon $au): array
    {
        $ecritures = JournalDesEcritures::aExporter($du, $au);
        $anomalies = JournalDesEcritures::anomalies($du, $au);

        return [
            'ecritures'  => $ecritures,
            'anomalies'  => $anomalies,
            'nombre'     => $ecritures->count(),
            'lignes'     => $ecritures->sum(fn (EcritureComptable $e) => $e->lignes->count()),
            'debit'      => round((float) $ecritures->sum('total_debit'), 2),
            'credit'     => round((float) $ecritures->sum('total_credit'), 2),
            'possible'   => $ecritures->isNotEmpty() && $anomalies->isEmpty(),
        ];
    }

    /**
     * Enregistre la transmission et marque les écritures comme exportées.
     *
     * @return array{success:bool,message:string,deversement:?DeversementComptable,ecritures:?Collection}
     */
    public static function transmettre(Carbon $du, Carbon $au, string $mode, string $format): array
    {
        $apercu = self::apercu($du, $au);

        if ($apercu['anomalies']->isNotEmpty()) {
            return ['success' => false, 'deversement' => null, 'ecritures' => null, 'message' =>
                $apercu['anomalies']->count() . ' anomalie(s) sur la période : corrigez-les avant de transmettre. '
                . 'Un logiciel comptable refuse un lot incomplet, et une écriture fausse déjà partie ne se rattrape qu\'en l\'annulant.'];
        }
        if ($apercu['nombre'] === 0) {
            return ['success' => false, 'deversement' => null, 'ecritures' => null, 'message' =>
                'Aucune écriture à transmettre sur cette période : elles ont déjà été transmises, ou il n\'y en a pas.'];
        }
        if (abs($apercu['debit'] - $apercu['credit']) > 0.004) {
            return ['success' => false, 'deversement' => null, 'ecritures' => null, 'message' =>
                'Le lot ne s\'équilibre pas (débit ' . number_format($apercu['debit'], 0, ',', ' ')
                . ' F, crédit ' . number_format($apercu['credit'], 0, ',', ' ') . ' F) : rien n\'a été transmis.'];
        }

        $ecritures = $apercu['ecritures'];
        $deversement = DB::transaction(function () use ($du, $au, $mode, $format, $apercu, $ecritures) {
            $deversement = DeversementComptable::create([
                'numero'           => self::prochainNumero(),
                'user_id'          => Auth::id(),
                'mode_periode'     => $mode,
                'du'               => $du->toDateString(),
                'au'               => $au->toDateString(),
                'format'           => $format,
                'nombre_ecritures' => $apercu['nombre'],
                'nombre_lignes'    => $apercu['lignes'],
                'total_debit'      => $apercu['debit'],
                'total_credit'     => $apercu['credit'],
                'fichier'          => self::nomDuFichier($du, $au, $mode, $format),
                'etat'             => DeversementComptable::TRANSMIS,
            ]);

            EcritureComptable::whereIn('id', $ecritures->pluck('id'))->update([
                'etat'           => EcritureComptable::ETAT_EXPORTEE,
                'exportee_le'    => now(),
                'deversement_id' => $deversement->id,
            ]);

            return $deversement;
        });

        JournalDesEcritures::memoriserLeMode($mode);

        return ['success' => true, 'deversement' => $deversement, 'ecritures' => $ecritures, 'message' =>
            $apercu['nombre'] . ' écriture(s) transmise(s) — déversement n° ' . $deversement->numero . '.'];
    }

    /** L'accusé de réception du logiciel comptable : le lot est bien arrivé. */
    public static function accuserReception(DeversementComptable $deversement): void
    {
        if ($deversement->etat === DeversementComptable::TRANSMIS) {
            $deversement->update(['etat' => DeversementComptable::ACCUSE_RECU, 'accuse_le' => now()]);
        }
    }

    /**
     * Le logiciel comptable a refusé le lot : les écritures repartent à
     * « à exporter ». Sans cela, elles resteraient marquées exportées alors
     * qu'aucune comptabilité ne les porte — et personne ne les reverrait.
     */
    public static function rejeter(DeversementComptable $deversement, string $motif): void
    {
        if ($deversement->estRejete()) {
            return;
        }

        DB::transaction(function () use ($deversement, $motif) {
            EcritureComptable::where('deversement_id', $deversement->id)->update([
                'etat'           => EcritureComptable::ETAT_A_EXPORTER,
                'exportee_le'    => null,
                'deversement_id' => null,
            ]);
            $deversement->update(['etat' => DeversementComptable::REJETE, 'motif_rejet' => mb_substr(trim($motif), 0, 255)]);
        });
    }

    public static function nomDuFichier(Carbon $du, Carbon $au, string $mode, string $format): string
    {
        $periode = match ($mode) {
            JournalDesEcritures::PAR_MOIS  => $du->format('Y-m'),
            JournalDesEcritures::PAR_ANNEE => $du->format('Y'),
            default => $du->format('Y-m-d') . '_' . $au->format('Y-m-d'),
        };
        $extension = ['SAGE' => 'xlsx', 'CSV' => 'csv', 'JSON' => 'json'][$format] ?? 'txt';

        return 'ecritures-comptables-' . $periode . '.' . $extension;
    }

    private static function prochainNumero(): string
    {
        $annee = now()->year;
        $dernier = DeversementComptable::where('numero', 'like', 'DEV-' . $annee . '-%')->orderByDesc('id')->value('numero');
        $rang = $dernier ? ((int) substr($dernier, -4)) + 1 : 1;

        return 'DEV-' . $annee . '-' . str_pad((string) $rang, 4, '0', STR_PAD_LEFT);
    }
}
