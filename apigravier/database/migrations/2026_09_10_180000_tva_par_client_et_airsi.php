<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TVA PAR CLIENT ET AIRSI (10/09/2026).
 *
 *  - client.applique_tva_transport : la TVA sur le transport peut être retirée
 *    pour un client donné, comme celle de la marchandise (applique_tva) ;
 *    appliquée par défaut. Les applique_tva laissés vides passent à 1.
 *  - configuration.taux_airsi : 5 % par défaut.
 *  - commande / location / demande_livraison / devis.airsi : l'AIRSI figé sur
 *    l'affaire, comme la TVA du transport ; facture.airsi_applique : sa part
 *    sur la facture.
 *
 * Base partagée site / API : à passer UNE fois, depuis la racine du site.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('client', 'applique_tva_transport')) {
            Schema::table('client', function (Blueprint $table) {
                $table->boolean('applique_tva_transport')->default(1)->after('applique_tva');
            });
        }
        // La TVA est appliquée par défaut : un client jamais renseigné n'en est pas exempté.
        \Illuminate\Support\Facades\DB::table('client')->whereNull('applique_tva')->update(['applique_tva' => 1]);

        if (!Schema::hasColumn('configuration', 'taux_airsi')) {
            Schema::table('configuration', function (Blueprint $table) {
                $table->decimal('taux_airsi', 5, 2)->default(5)->after('tva_transport');
            });
        }

        foreach (['commande', 'location', 'demande_livraison', 'devis'] as $table) {
            if (!Schema::hasColumn($table, 'airsi')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->double('airsi')->default(0);
                });
            }
        }
        if (!Schema::hasColumn('facture', 'airsi_applique')) {
            Schema::table('facture', function (Blueprint $t) {
                $t->double('airsi_applique')->default(0);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('client', 'applique_tva_transport')) {
            Schema::table('client', fn (Blueprint $t) => $t->dropColumn('applique_tva_transport'));
        }
        if (Schema::hasColumn('configuration', 'taux_airsi')) {
            Schema::table('configuration', fn (Blueprint $t) => $t->dropColumn('taux_airsi'));
        }
        foreach (['commande', 'location', 'demande_livraison', 'devis'] as $table) {
            if (Schema::hasColumn($table, 'airsi')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('airsi'));
            }
        }
        if (Schema::hasColumn('facture', 'airsi_applique')) {
            Schema::table('facture', fn (Blueprint $t) => $t->dropColumn('airsi_applique'));
        }
    }
};
