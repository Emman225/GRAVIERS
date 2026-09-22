<?php

namespace App\Services\Comptabilite;

use App\Models\EcritureComptable;
use App\Models\LigneEcritureComptable;
use Illuminate\Support\Collection;

/**
 * LA MISE EN FORME DES ÉCRITURES POUR LE LOGICIEL COMPTABLE.
 *
 * Une ligne du fichier = une ligne d'écriture. Les colonnes ci-dessous sont
 * celles d'un import Sage courant ; le gabarit exact du logiciel de DALAKOUN
 * n'a pas encore été fourni (question 4 du 21/09/2026).
 *
 * QUAND LE GABARIT ARRIVERA, TOUT SE RÈGLE ICI : l'ordre, les intitulés et le
 * contenu des colonnes se lisent et se modifient dans le seul tableau
 * COLONNES. Rien d'autre dans le module ne connaît la forme du fichier.
 */
class FormatSage
{
    /** intitulé de la colonne => comment la remplir pour une ligne d'écriture */
    public const COLONNES = [
        'Journal'           => 'journal',
        'Date'              => 'date',
        'N° pièce'          => 'piece',
        'Compte général'    => 'compte',
        'Compte tiers'      => 'tiers',
        'Libellé'           => 'libelle',
        'Débit'             => 'debit',
        'Crédit'            => 'credit',
        'Code analytique'   => 'analytique',
        'Référence'         => 'reference',
        'Lettrage'          => 'lettre',
        'Pièce interne'     => 'identifiant',
    ];

    public static function entetes(): array
    {
        return array_keys(self::COLONNES);
    }

    /** Toutes les lignes du fichier, écriture après écriture, dans l'ordre. */
    public static function lignes(Collection $ecritures): array
    {
        $fichier = [];
        foreach ($ecritures as $ecriture) {
            foreach ($ecriture->lignes as $ligne) {
                $fichier[] = self::ligne($ecriture, $ligne);
            }
        }

        return $fichier;
    }

    private static function ligne(EcritureComptable $ecriture, LigneEcritureComptable $ligne): array
    {
        $valeurs = [
            'journal'     => (string) $ecriture->journal_code,
            'date'        => $ecriture->date_ecriture?->format('d/m/Y') ?? '',
            'piece'       => (string) $ecriture->piece,
            'compte'      => (string) $ligne->numero_compte,
            'tiers'       => (string) $ligne->compte_tiers,
            'libelle'     => (string) $ligne->libelle,
            // Les montants partent en nombres, avec deux décimales : un montant
            // devenu texte est refusé à l'import, ou pire, importé à zéro.
            'debit'       => round((float) $ligne->debit, 2),
            'credit'      => round((float) $ligne->credit, 2),
            'analytique'  => (string) $ligne->numero_analytique,
            'reference'   => (string) ($ecriture->reference_fne ?: $ecriture->numero_affaire),
            'lettre'      => (string) $ligne->lettre,
            'identifiant' => (string) $ecriture->identifiant,
        ];

        $sortie = [];
        foreach (self::COLONNES as $champ) {
            $sortie[] = $valeurs[$champ] ?? '';
        }

        return $sortie;
    }

    /** Le même contenu en CSV (point-virgule, BOM UTF-8 : Excel l'ouvre sans rien demander). */
    public static function csv(Collection $ecritures): string
    {
        $flux = fopen('php://temp', 'r+');
        fwrite($flux, "\xEF\xBB\xBF");
        fputcsv($flux, self::entetes(), ';');
        foreach (self::lignes($ecritures) as $ligne) {
            // La virgule décimale, comme l'attend un tableur français.
            $ligne = array_map(fn ($v) => is_float($v) ? number_format($v, 2, ',', '') : $v, $ligne);
            fputcsv($flux, $ligne, ';');
        }
        rewind($flux);
        $contenu = stream_get_contents($flux);
        fclose($flux);

        return $contenu;
    }

    /** Le même contenu en JSON, une écriture par entrée — c'est ce que rend l'API (phase 4). */
    public static function json(Collection $ecritures): array
    {
        return $ecritures->map(fn (EcritureComptable $ecriture) => [
            'identifiant'    => $ecriture->identifiant,
            'journal'        => $ecriture->journal_code,
            'date'           => $ecriture->date_ecriture?->toDateString(),
            'piece'          => $ecriture->piece,
            'libelle'        => $ecriture->libelle,
            'origine'        => $ecriture->origine,
            'reference_fne'  => $ecriture->reference_fne,
            'numero_affaire' => $ecriture->numero_affaire,
            'total_debit'    => round((float) $ecriture->total_debit, 2),
            'total_credit'   => round((float) $ecriture->total_credit, 2),
            'lignes'         => $ecriture->lignes->map(fn (LigneEcritureComptable $ligne) => [
                'compte'     => $ligne->numero_compte,
                'tiers'      => $ligne->compte_tiers,
                'analytique' => $ligne->numero_analytique,
                'libelle'    => $ligne->libelle,
                'debit'      => round((float) $ligne->debit, 2),
                'credit'     => round((float) $ligne->credit, 2),
                'lettre'     => $ligne->lettre,
            ])->all(),
        ])->all();
    }
}
