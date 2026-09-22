<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module « Écritures comptables », phase 3 : les déversements (lot 119, 22/09/2026).
 *
 * Un déversement = une transmission d'écritures vers le logiciel comptable.
 * Il garde ce qui a été envoyé, quand, par qui et sous quelle forme : c'est la
 * preuve de l'envoi, et c'est lui qui empêche d'envoyer deux fois les mêmes
 * écritures.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('deversement_comptable')) {
            Schema::create('deversement_comptable', function (Blueprint $table) {
                $table->id();
                $table->string('numero', 30)->unique();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('mode_periode', 10);              // DATES | MOIS
                $table->date('du');
                $table->date('au');
                $table->string('format', 15);                    // SAGE | CSV | JSON
                $table->unsignedInteger('nombre_ecritures')->default(0);
                $table->unsignedInteger('nombre_lignes')->default(0);
                $table->decimal('total_debit', 15, 2)->default(0);
                $table->decimal('total_credit', 15, 2)->default(0);
                $table->string('fichier', 190)->nullable();
                $table->string('etat', 15)->default('TRANSMIS'); // TRANSMIS | ACCUSE_RECU | REJETE
                $table->dateTime('accuse_le')->nullable();
                $table->string('motif_rejet', 255)->nullable();
                $table->timestamps();
                $table->index(['du', 'au']);
                $table->index('etat');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('deversement_comptable');
    }
};
