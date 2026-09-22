<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module « Écritures comptables », phase 4 : l'API (lot 121, 22/09/2026).
 *
 * LA TABLE `personal_access_tokens` DÉJÀ PRÉSENTE N'EST PAS CELLE DE SANCTUM.
 *
 * Le paquet `laravel/sanctum` est dans composer.json, mais la migration
 * `2019_12_14_000001_create_personal_access_tokens_table.php` de ce dépôt ne
 * reprend pas le gabarit du paquet (`vendor/laravel/sanctum/database/…`) :
 * elle pose `token` en clé primaire, avec `uuid` et `status`, sans les
 * colonnes polymorphes `tokenable_type` / `tokenable_id` qu'attend le modèle
 * `Laravel\Sanctum\PersonalAccessToken`. Un reliquat jamais exploité — aucun
 * modèle ni contrôleur du dépôt ne la lit — mais qui ferait échouer tout
 * `createToken()` tel quel.
 *
 * Reconstruite ici au gabarit exact de Sanctum, PLUS deux colonnes utiles à
 * l'écran des jetons (qui l'a créé, une note lisible). Aucune perte : la
 * table est vide partout où elle existe (vérifié avant d'écrire cette
 * migration) ; par prudence, si une base en production portait malgré tout
 * des lignes, la migration s'arrête au lieu de les écraser en silence.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('personal_access_tokens')) {
            $this->creerLaTableSanctum();

            return;
        }

        if (Schema::hasColumn('personal_access_tokens', 'tokenable_type')) {
            $this->ajouterLesColonnesDuJeton();

            return;
        }

        $nombre = (int) DB::table('personal_access_tokens')->count();
        if ($nombre > 0) {
            throw new \RuntimeException(
                "La table personal_access_tokens existe, au format non-Sanctum, et porte {$nombre} ligne(s) : "
                . "cette migration s'arrête plutôt que de les perdre. Examinez-les avant de continuer."
            );
        }

        Schema::drop('personal_access_tokens');
        $this->creerLaTableSanctum();
    }

    private function creerLaTableSanctum(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('cree_par_id')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();
        });
    }

    private function ajouterLesColonnesDuJeton(): void
    {
        if (!Schema::hasColumn('personal_access_tokens', 'cree_par_id')) {
            Schema::table('personal_access_tokens', function (Blueprint $table) {
                $table->unsignedBigInteger('cree_par_id')->nullable()->after('tokenable_id');
                $table->string('note', 255)->nullable()->after('cree_par_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('personal_access_tokens') && Schema::hasColumn('personal_access_tokens', 'cree_par_id')) {
            Schema::table('personal_access_tokens', function (Blueprint $table) {
                $table->dropColumn(['cree_par_id', 'note']);
            });
        }
    }
};
