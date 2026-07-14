<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apporteur', function (Blueprint $table) {
            if (!Schema::hasColumn('apporteur', 'cni')) {
                $table->string('cni', 50)->nullable();
            }
            if (!Schema::hasColumn('apporteur', 'mode_paiement_prefere')) {
                $table->string('mode_paiement_prefere', 80)->nullable();
            }
            if (!Schema::hasColumn('apporteur', 'coordonnees_paiement')) {
                $table->string('coordonnees_paiement', 200)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('apporteur', function (Blueprint $table) {
            foreach (['cni', 'mode_paiement_prefere', 'coordonnees_paiement'] as $col) {
                if (Schema::hasColumn('apporteur', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
