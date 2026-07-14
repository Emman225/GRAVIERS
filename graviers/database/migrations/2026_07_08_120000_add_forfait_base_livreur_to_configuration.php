<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Forfait de base PAR DÉFAUT appliqué à chaque nouveau livreur à sa création
     * (paramétrable dans /parametre onglet Livreurs). Reste modifiable ensuite
     * individuellement sur le profil du livreur (mode de tarification).
     */
    public function up(): void
    {
        Schema::table('configuration', function (Blueprint $table) {
            if (!Schema::hasColumn('configuration', 'forfait_base_livreur')) {
                $table->double('forfait_base_livreur')->default(0)->after('jour_paiement_livreur');
            }
        });
    }

    public function down(): void
    {
        Schema::table('configuration', function (Blueprint $table) {
            if (Schema::hasColumn('configuration', 'forfait_base_livreur')) {
                $table->dropColumn('forfait_base_livreur');
            }
        });
    }
};
