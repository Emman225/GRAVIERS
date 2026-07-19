<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * qte_servi était unsignedInteger alors que les quantités sont décimales.
 *
 * Même famille de défaut que qte_livree (cf. 2026_07_18_100000) : enlevement.qte
 * est FLOAT, le formulaire fournisseur accepte les décimales (step="any"), mais
 * qte_servi (migration 2025_03_06) a été créée en unsignedInteger. Un fournisseur
 * servant 0.5 tonne voyait la valeur détruite (0 en base — cas réel :
 * enlèvement 13, qte 0.50, qte_servi 0).
 *
 * Conséquence en cascade : totatEnlevementUnProduit fait
 * COALESCE(qte_servi, qte) — 0 n'étant pas NULL, la quantité enlevée comptait
 * pour 0 -> la page de traitement affichait « Aucune Qté enlevée » et proposait
 * de RE-traiter la totalité (risque de sur-livraison).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('enlevement', 'qte_servi')) {
            DB::statement("ALTER TABLE enlevement MODIFY qte_servi DOUBLE NULL");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('enlevement', 'qte_servi')) {
            DB::statement("ALTER TABLE enlevement MODIFY qte_servi INT UNSIGNED NULL");
        }
    }
};
