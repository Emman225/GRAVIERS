<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LA TVA SUR LE TRANSPORT, FIGÉE SUR CHAQUE AFFAIRE.
 *
 * Point 5 du 07/09/2026 : la case « TVA sur le transport » vaut désormais
 * pour les ventes et les locations, pas seulement pour les demandes de
 * livraison. Le montant est inscrit sur l'affaire au moment où elle est
 * chiffrée (commande, location, devis), et sur la facture qui le porte : une
 * décision prise plus tard ne réécrit pas ce qui a déjà été vendu.
 *
 * Valeur par défaut 0 : poser cette migration ne change aucun prix.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['commande', 'location', 'devis'] as $table) {
            if (!Schema::hasColumn($table, 'tva_transport')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->decimal('tva_transport', 15, 2)->default(0);
                });
            }
        }

        if (!Schema::hasColumn('facture', 'tva_transport_applique')) {
            Schema::table('facture', function (Blueprint $t) {
                $t->decimal('tva_transport_applique', 15, 2)->default(0);
            });
        }
    }

    public function down(): void
    {
        foreach (['commande', 'location', 'devis'] as $table) {
            if (Schema::hasColumn($table, 'tva_transport')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('tva_transport'));
            }
        }
        if (Schema::hasColumn('facture', 'tva_transport_applique')) {
            Schema::table('facture', fn (Blueprint $t) => $t->dropColumn('tva_transport_applique'));
        }
    }
};
