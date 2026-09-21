<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LE CODE D'EXONÉRATION FNE DU CLIENT (lot 82, 15/09/2026).
 *
 * Au retrait de la TVA d'un client (liste des clients), l'administrateur dit
 * si l'exonération est légale (TVAD) ou conventionnelle (TVAC) : c'est le
 * code de taxe que la DGI attend sur chaque ligne de ses factures. Vide tant
 * que la TVA est appliquée ; un client dispensé avant cette évolution retombe
 * sur FNE_EXEMPT_TAX (config/fne.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('client') && !Schema::hasColumn('client', 'code_exoneration_fne')) {
            Schema::table('client', function (Blueprint $t) {
                $t->string('code_exoneration_fne', 4)->nullable()->after('applique_tva_transport');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('client') && Schema::hasColumn('client', 'code_exoneration_fne')) {
            Schema::table('client', fn (Blueprint $t) => $t->dropColumn('code_exoneration_fne'));
        }
    }
};
