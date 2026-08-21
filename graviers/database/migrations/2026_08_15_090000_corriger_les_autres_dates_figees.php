<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Suite de la correction de commande.date_commande.
     *
     * Le schéma comporte plusieurs colonnes de date dont la valeur par défaut
     * est un horodatage FIGÉ d'avril 2026 au lieu de l'instant courant. Tant
     * qu'un point de création oublie de renseigner la colonne, la ligne prend
     * cette date-là. L'inventaire complet du schéma en a révélé trois autres
     * effectivement touchées par des données réelles :
     *
     *   - location.date_location      : les deux points de création du SITE
     *                                   laissaient la ligne en commentaire ;
     *                                   les locations de l'application, elles,
     *                                   étaient correctement datées ;
     *   - ligne_paiement.date_paiement: plusieurs points de création ne la
     *                                   renseignaient pas ; comme la liste des
     *                                   règlements est TRIÉE sur cette date,
     *                                   elle sortait dans le désordre ;
     *   - retour_produit.date_retour  : la colonne était absente de $fillable,
     *                                   donc ignorée en silence à la création.
     *
     * Comme pour date_commande : on répare les lignes portant très exactement
     * l'horodatage figé — les seules dont on puisse affirmer qu'elles n'ont
     * jamais été renseignées — en les réalignant sur created_at, puis on
     * remplace la valeur par défaut par l'instant courant.
     *
     * commande.date_fin_livraison porte le même défaut figé mais n'est écrite
     * NULLE PART et affichée sur AUCUN écran : la corriger reviendrait à dater
     * une fin de livraison qui n'a pas eu lieu. Elle est laissée telle quelle.
     */
    private array $colonnes = [
        ['location',       'date_location', '2026-04-13 12:32:30'],
        ['ligne_paiement', 'date_paiement', '2026-04-13 12:32:23'],
        ['retour_produit', 'date_retour',   '2026-04-13 12:32:26'],
    ];

    public function up(): void
    {
        foreach ($this->colonnes as [$table, $colonne, $dateFigee]) {
            $reparees = DB::table($table)
                ->where($colonne, $dateFigee)
                ->whereNotNull('created_at')
                ->update([$colonne => DB::raw('created_at')]);

            DB::statement(
                "ALTER TABLE `{$table}` MODIFY `{$colonne}` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP"
            );

            if ($reparees > 0) {
                echo "  {$table}.{$colonne} : {$reparees} ligne(s) redatée(s) sur leur date de création réelle." . PHP_EOL;
            }
        }
    }

    public function down(): void
    {
        // On ne restaure PAS les horodatages figés : c'étaient eux, le défaut.
        foreach ($this->colonnes as [$table, $colonne, $dateFigee]) {
            DB::statement("ALTER TABLE `{$table}` MODIFY `{$colonne}` DATETIME NOT NULL");
        }
    }
};
