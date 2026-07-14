<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commande', function (Blueprint $table) {
            if (!Schema::hasColumn('commande', 'agence_id')) {
                $table->unsignedBigInteger('agence_id')->nullable()->after('adresse_livraison_id');
                $table->index('agence_id');
            }
            if (!Schema::hasColumn('commande', 'date_limite_paiement')) {
                $table->date('date_limite_paiement')->nullable()->after('date_commande');
            }
            if (!Schema::hasColumn('commande', 'statut_comptant')) {
                $table->string('statut_comptant', 30)->nullable()->after('etat_commande');
            }
        });
    }

    public function down(): void
    {
        Schema::table('commande', function (Blueprint $table) {
            foreach (['agence_id', 'date_limite_paiement', 'statut_comptant'] as $col) {
                if (Schema::hasColumn('commande', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
