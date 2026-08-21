<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal des opérations du back-office.
 *
 * Le nom de l'utilisateur est RECOPIÉ dans la ligne, en plus de la clé
 * étrangère : une trace d'audit doit survivre à la suppression du compte qui
 * l'a produite. La clé passe alors à null, le nom reste lisible.
 *
 * `donnees` porte le JSON des changements. La table n'a pas de `updated_at` :
 * une trace ne se modifie pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('audits')) {
            return;
        }

        Schema::create('audits', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('nom_utilisateur', 191)->nullable();
            $table->unsignedBigInteger('type_user_id')->nullable();

            $table->string('action', 191);
            $table->string('methode', 10)->nullable();
            $table->string('url', 500)->nullable();
            $table->string('route_name', 191)->nullable();
            $table->json('donnees')->nullable();

            $table->string('adresse_ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();

            $table->timestamp('created_at')->nullable();

            // Les écrans filtrent par utilisateur, par action et par période,
            // et trient toujours du plus récent au plus ancien.
            $table->index('user_id');
            $table->index('action');
            $table->index('created_at');

            // onDelete('set null') : supprimer un compte ne doit pas effacer
            // son journal, ni bloquer la suppression.
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audits');
    }
};
