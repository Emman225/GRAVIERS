<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rattachement d'un utilisateur à une agence.
 *
 * L'agence d'un encaissement était CHOISIE dans une liste au moment de la
 * saisie : un caissier pouvait donc, par erreur ou non, imputer sa recette à
 * un autre guichet que le sien, et la caisse d'une agence se retrouvait
 * créditée d'un versement qu'elle n'avait jamais reçu.
 *
 * L'agence devient une propriété de la PERSONNE : elle est décidée par
 * l'administrateur, une fois, et reprise automatiquement à chaque opération.
 *
 * Nullable : un administrateur n'est pas nécessairement rattaché à un guichet,
 * et les comptes existants n'en ont pas encore. Les contrôleurs refusent
 * l'encaissement tant que le rattachement n'est pas fait — plutôt que
 * d'inventer une agence par défaut, qui fausserait la caisse en silence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('agence_id')->nullable()->after('type_user_id');
            $table->index('agence_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['agence_id']);
            $table->dropColumn('agence_id');
        });
    }
};
