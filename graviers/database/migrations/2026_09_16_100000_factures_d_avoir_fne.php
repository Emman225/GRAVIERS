<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FACTURE D'AVOIR FNE (lot 92, 16/09/2026). L'avoir est une ligne de `facture`
 * de type AVOIR, rattachée à sa facture d'origine, au montant NÉGATIF : les
 * totaux facturés (créances, déjà facturé, comptabilité) se corrigent sans
 * qu'aucune somme existante n'ait à changer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facture', function (Blueprint $table) {
            if (!Schema::hasColumn('facture', 'type_document')) {
                $table->string('type_document', 10)->default('FACTURE')->after('numero_fne');
            }
            if (!Schema::hasColumn('facture', 'facture_origine_id')) {
                $table->unsignedBigInteger('facture_origine_id')->nullable()->after('type_document');
            }
            if (!Schema::hasColumn('facture', 'motif_avoir')) {
                $table->string('motif_avoir', 255)->nullable()->after('facture_origine_id');
            }
            if (!Schema::hasColumn('facture', 'lignes_avoir')) {
                $table->json('lignes_avoir')->nullable()->after('motif_avoir');
            }
        });
    }

    public function down(): void
    {
        Schema::table('facture', function (Blueprint $table) {
            foreach (['lignes_avoir', 'motif_avoir', 'facture_origine_id', 'type_document'] as $c) {
                if (Schema::hasColumn('facture', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
