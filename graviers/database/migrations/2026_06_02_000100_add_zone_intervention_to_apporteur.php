<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apporteur', function (Blueprint $table) {
            if (!Schema::hasColumn('apporteur', 'zone_intervention')) {
                $table->string('zone_intervention', 150)->nullable()->after('coordonnees_paiement');
            }
        });
    }

    public function down(): void
    {
        Schema::table('apporteur', function (Blueprint $table) {
            if (Schema::hasColumn('apporteur', 'zone_intervention')) {
                $table->dropColumn('zone_intervention');
            }
        });
    }
};
