<?php

namespace App\Services\Comptabilite;

use App\Models\EcritureComptable;
use App\Models\LigneEcritureComptable;
use Illuminate\Support\Collection;

/**
 * LA MISE EN FORME POUR LE LOGICIEL COMPTABLE — SAGE.
 *
 * Les colonnes, leur ordre et leurs intitulés sont ceux des deux écrans
 * « Description des champs » du Sage de DALAKOUN, fournis le 26/09/2026 :
 * celui des ÉCRITURES et celui du PLAN COMPTABLE. Une ligne du fichier
 * d'écritures = une ligne d'écriture.
 *
 * TOUT SE RÈGLE ICI : rien d'autre dans le module ne connaît la forme du
 * fichier. Dans Sage, « * » marque un champ obligatoire et le triangle un
 * champ à valeur imposée — ces deux valeurs-là sont les constantes
 * PLAN_ANALYTIQUE et TYPE_DE_COMPTE ci-dessous.
 */
class FormatSage
{
    /**
     * Le numéro du plan analytique, imposé par Sage (triangle, valeur 1).
     * Un seul plan analytique est employé.
     */
    public const PLAN_ANALYTIQUE = '1';

    /** Le type de compte du plan comptable : G comme général (triangle, valeur G). */
    public const TYPE_DE_COMPTE = 'G';

    /** intitulé de la colonne => comment la remplir pour une ligne d'écriture */
    public const COLONNES = [
        "Type d'écriture"         => 'type_ecriture',
        'Code journal'            => 'journal',
        'Date de pièce'           => 'date',
        'N° pièce'                => 'piece',
        'Référence'               => 'reference',
        'N° compte général'       => 'compte',
        'Intitulé compte général' => 'intitule_compte',
        'N° section 1'            => 'analytique',
        'N° compte tiers'         => 'tiers',
        'Libellé écriture'        => 'libelle',
        'Montant débit'           => 'debit',
        'Montant crédit'          => 'credit',
        'N° plan analytique'      => 'plan_analytique',
    ];

    /** intitulé de la colonne => comment la remplir pour un compte du plan */
    public const COLONNES_PLAN = [
        'Numéro compte'  => 'numero',
        'Intitulé'       => 'libelle',
        'Type'           => 'type',
        'Type de compte' => 'type_de_compte',
    ];

    public static function entetes(): array
    {
        return array_keys(self::COLONNES);
    }

    public static function entetesDuPlan(): array
    {
        return array_keys(self::COLONNES_PLAN);
    }

    /**
     * Le plan comptable au format d'import de Sage. Les comptes GÉNÉRAUX
     * seulement : dans Sage, les comptes tiers et les sections analytiques
     * s'importent par d'autres fichiers.
     */
    public static function plan(Collection $comptes): array
    {
        $fichier = [];
        foreach ($comptes as $compte) {
            $valeurs = [
                'numero'         => (string) $compte->numero,
                'libelle'        => (string) $compte->libelle,
                'type'           => '',
                'type_de_compte' => self::TYPE_DE_COMPTE,
            ];
            $ligne = [];
            foreach (self::COLONNES_PLAN as $champ) {
                $ligne[] = $valeurs[$champ] ?? '';
            }
            $fichier[] = $ligne;
        }

        return $fichier;
    }

    /** Toutes les lignes du fichier, écriture après écriture, dans l'ordre. */
    public static function lignes(Collection $ecritures): array
    {
        // Les intitulés des comptes en UNE requête : aller les chercher ligne
        // par ligne en ferait une par ligne du fichier.
        $intitules = \App\Models\CompteComptable::withTrashed()->pluck('libelle', 'numero');

        $fichier = [];
        foreach ($ecritures as $ecriture) {
            foreach ($ecriture->lignes as $ligne) {
                $fichier[] = self::ligne($ecriture, $ligne, $intitules);
            }
        }

        return $fichier;
    }

    private static function ligne(EcritureComptable $ecriture, LigneEcritureComptable $ligne, $intitules = null): array
    {
        $valeurs = [
            // Sage laisse ce champ libre : il n'est pas obligatoire, et aucune
            // valeur ne nous a été imposée.
            'type_ecriture'   => '',
            'journal'         => (string) $ecriture->journal_code,
            'date'            => $ecriture->date_ecriture?->format('d/m/Y') ?? '',
            'piece'           => (string) $ecriture->piece,
            'reference'       => (string) ($ecriture->reference_fne ?: $ecriture->numero_affaire),
            'compte'          => (string) $ligne->numero_compte,
            'intitule_compte' => (string) ($intitules[$ligne->numero_compte] ?? ''),
            'analytique'      => (string) $ligne->numero_analytique,
            'tiers'           => (string) $ligne->compte_tiers,
            'libelle'         => (string) $ligne->libelle,
            // Les montants partent en nombres, avec deux décimales : un montant
            // devenu texte est refusé à l'import, ou pire, importé à zéro.
            'debit'           => round((float) $ligne->debit, 2),
            'credit'          => round((float) $ligne->credit, 2),
            'plan_analytique' => self::PLAN_ANALYTIQUE,
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
