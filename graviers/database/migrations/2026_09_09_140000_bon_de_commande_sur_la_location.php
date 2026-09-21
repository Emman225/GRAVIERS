<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LE BON DE COMMANDE INTERNE SUR LA LOCATION (09/09/2026).
 *
 * Ce qui existe pour la vente vaut pour la location : le numéro est figé sur
 * la location (location.numero_bon_commande) et la pièce jointe rejoint
 * bl_client, rattachée par bl_client.location_id — une colonne distincte,
 * comme commande_id et demande_livraison_id, jamais une colonne polymorphe.
 *
 * Colonnes facultatives : poser cette migration ne change rien à l'existant.
 * Le site et l'API partagent la base : chacun porte la même migration, gardée
 * par un contrôle d'existence, la seconde ne fait rien.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('location', 'numero_bon_commande')) {
            Schema::table('location', function (Blueprint $table) {
                $table->string('numero_bon_commande', 255)->nullable()->after('tva_transport');
            });
        }
        if (!Schema::hasColumn('bl_client', 'location_id')) {
            Schema::table('bl_client', function (Blueprint $table) {
                $table->unsignedBigInteger('location_id')->nullable()->after('demande_livraison_id');
                $table->index('location_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bl_client', 'location_id')) {
            Schema::table('bl_client', function (Blueprint $table) {
                $table->dropIndex(['location_id']);
                $table->dropColumn('location_id');
            });
        }
        if (Schema::hasColumn('location', 'numero_bon_commande')) {
            Schema::table('location', function (Blueprint $table) {
                $table->dropColumn('numero_bon_commande');
            });
        }
    }
};
