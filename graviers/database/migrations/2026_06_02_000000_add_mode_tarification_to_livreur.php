<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('livreur', function (Blueprint $table) {
            if (!Schema::hasColumn('livreur', 'mode_tarification')) {
                // 'base' = tarif forfaitaire (cout_livraison) ; 'km' = tarif par kilomètre (tarif_km)
                $table->string('mode_tarification', 10)->default('base')->after('cout_livraison');
            }
        });
    }

    public function down(): void
    {
        Schema::table('livreur', function (Blueprint $table) {
            if (Schema::hasColumn('livreur', 'mode_tarification')) {
                $table->dropColumn('mode_tarification');
            }
        });
    }
};
