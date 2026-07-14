<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * L'API mobile (apigravier LocationController::enregistrerLocation) écrit
     * detail_location.cout_livraison (coût de livraison calculé par ligne,
     * comme detail_commande.cout_livraison pour les ventes) mais la colonne
     * n'existait pas -> "Unknown column 'cout_livraison'" -> 500 à la création
     * d'une location avec livraison depuis le mobile.
     */
    public function up(): void
    {
        Schema::table('detail_location', function (Blueprint $table) {
            if (!Schema::hasColumn('detail_location', 'cout_livraison')) {
                $table->double('cout_livraison')->default(0)->after('prix');
            }
        });
    }

    public function down(): void
    {
        Schema::table('detail_location', function (Blueprint $table) {
            if (Schema::hasColumn('detail_location', 'cout_livraison')) {
                $table->dropColumn('cout_livraison');
            }
        });
    }
};
