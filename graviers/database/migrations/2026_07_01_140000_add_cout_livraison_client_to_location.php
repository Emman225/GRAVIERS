<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La table `location` n'avait pas de colonne `cout_livraison_client`, alors que
 * le modèle Location la déclare fillable et que enregistrementLocation() l'insère
 * (Location::create([... 'cout_livraison_client' => ...])) et que la vue
 * orders/recapLocation lit $location->cout_livraison_client.
 * Conséquence : "Column not found: cout_livraison_client" → 500 à la validation
 * de la location. On crée donc la colonne manquante (idempotent).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('location', 'cout_livraison_client')) {
            Schema::table('location', function (Blueprint $table) {
                $table->double('cout_livraison_client')->nullable()->default(0)->after('montant_total');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('location', 'cout_livraison_client')) {
            Schema::table('location', function (Blueprint $table) {
                $table->dropColumn('cout_livraison_client');
            });
        }
    }
};
