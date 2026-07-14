<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paiement', function (Blueprint $table) {
            if (!Schema::hasColumn('paiement', 'agence_id')) {
                $table->unsignedBigInteger('agence_id')->nullable();
                $table->index('agence_id');
            }
            if (!Schema::hasColumn('paiement', 'caissier_id')) {
                $table->unsignedBigInteger('caissier_id')->nullable();
                $table->index('caissier_id');
            }
            if (!Schema::hasColumn('paiement', 'numero_recu')) {
                $table->string('numero_recu', 50)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('paiement', function (Blueprint $table) {
            foreach (['agence_id', 'caissier_id', 'numero_recu'] as $col) {
                if (Schema::hasColumn('paiement', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
