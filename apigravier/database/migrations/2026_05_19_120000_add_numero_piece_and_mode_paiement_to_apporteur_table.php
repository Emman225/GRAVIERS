<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apporteur', function (Blueprint $table) {
            if (!Schema::hasColumn('apporteur', 'numero_piece')) {
                $table->string('numero_piece', 50)->nullable();
            }
            if (!Schema::hasColumn('apporteur', 'mode_paiement_id')) {
                $table->unsignedBigInteger('mode_paiement_id')->nullable();
                $table->foreign('mode_paiement_id')->references('id')->on('mode_paiement')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('apporteur', function (Blueprint $table) {
            if (Schema::hasColumn('apporteur', 'mode_paiement_id')) {
                $table->dropForeign(['mode_paiement_id']);
                $table->dropColumn('mode_paiement_id');
            }
            if (Schema::hasColumn('apporteur', 'numero_piece')) {
                $table->dropColumn('numero_piece');
            }
        });
    }
};
