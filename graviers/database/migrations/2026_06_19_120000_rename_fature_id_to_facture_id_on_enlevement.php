<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Corrige la faute de frappe historique : la colonne de liaison
 * enlevement -> facture avait été créée sous le nom « fature_id »
 * (migration 2025_02_07_101449), alors que TOUT le code applicatif
 * (OrdersController::genererFacture, FneService, UserController, etc.)
 * écrit/lit la colonne sous le nom correct « facture_id ».
 *
 * Conséquence avant ce correctif : la génération de facture provoquait
 * une erreur SQL #1054 (Unknown column 'facture_id') -> HTTP 500, et la
 * facture restait à montant 0.
 *
 * Idempotente : ne renomme que si « fature_id » existe encore et que
 * « facture_id » n'existe pas déjà (imports déjà corrigés -> no-op).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('enlevement', 'fature_id')
            && ! Schema::hasColumn('enlevement', 'facture_id')) {
            Schema::table('enlevement', function (Blueprint $table) {
                $table->renameColumn('fature_id', 'facture_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('enlevement', 'facture_id')
            && ! Schema::hasColumn('enlevement', 'fature_id')) {
            Schema::table('enlevement', function (Blueprint $table) {
                $table->renameColumn('facture_id', 'fature_id');
            });
        }
    }
};
