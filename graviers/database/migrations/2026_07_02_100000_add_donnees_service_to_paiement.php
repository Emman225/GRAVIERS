<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stocke le « brouillon » d'une location (produits, dates, montants, remise) sur le
 * paiement, afin de ne créer la Location qu'APRÈS confirmation du paiement en ligne
 * (le callback PaySecure serveur-à-serveur n'a pas accès à la session du navigateur).
 * Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('paiement', 'donnees_service')) {
            Schema::table('paiement', function (Blueprint $table) {
                $table->json('donnees_service')->nullable()->after('service');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('paiement', 'donnees_service')) {
            Schema::table('paiement', function (Blueprint $table) {
                $table->dropColumn('donnees_service');
            });
        }
    }
};
