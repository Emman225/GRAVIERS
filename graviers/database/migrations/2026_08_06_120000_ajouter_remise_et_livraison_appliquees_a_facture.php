<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mémorise, sur chaque facture, la part de remise et de coût de livraison qui
 * lui a été imputée.
 *
 * Une commande se facture enlèvement par enlèvement : elle peut donner
 * plusieurs factures. Jusqu'ici, remise et livraison étaient portées EN ENTIER
 * par la première d'entre elles, et le message envoyé à la DGI déclarait la
 * remise complète de la commande sur CHAQUE facture — une commande remisée de
 * 400 et livrée en deux fois en déclarait donc 800.
 *
 * Ces deux colonnes rendent l'imputation explicite et vérifiable : elles sont
 * calculées au prorata du HT facturé (OrdersController::genererFacture) et
 * relues telles quelles au moment de la certification (FneService).
 *
 * NULL par défaut, et non 0 : les factures antérieures à cette migration
 * conservent ainsi l'ancien comportement de déclaration, sans réécriture de
 * données.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('facture')) {
            return;
        }

        Schema::table('facture', function (Blueprint $table) {
            if (!Schema::hasColumn('facture', 'remise_appliquee')) {
                $table->decimal('remise_appliquee', 18, 2)->nullable()->after('montant');
            }
            if (!Schema::hasColumn('facture', 'cout_livraison_applique')) {
                $table->decimal('cout_livraison_applique', 18, 2)->nullable()->after('remise_appliquee');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('facture')) {
            return;
        }

        Schema::table('facture', function (Blueprint $table) {
            foreach (['remise_appliquee', 'cout_livraison_applique'] as $colonne) {
                if (Schema::hasColumn('facture', $colonne)) {
                    $table->dropColumn($colonne);
                }
            }
        });
    }
};
