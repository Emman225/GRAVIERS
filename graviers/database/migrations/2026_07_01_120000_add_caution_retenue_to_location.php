<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retenue partielle de caution (phase c, suite) :
 *  - caution_retenue : montant de la caution conservé par l'entreprise (ex. dégâts).
 *                      Montant restitué = caution - caution_retenue.
 *  - motif_retenue   : justification de la retenue.
 * Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('location', function (Blueprint $table) {
            if (!Schema::hasColumn('location', 'caution_retenue')) {
                $table->double('caution_retenue')->default(0);
            }
            if (!Schema::hasColumn('location', 'motif_retenue')) {
                $table->string('motif_retenue', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('location', function (Blueprint $table) {
            foreach (['caution_retenue', 'motif_retenue'] as $col) {
                if (Schema::hasColumn('location', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
