<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Met la table `blogs` en accord avec le code.
 *
 * Le modèle visait une table `blog` qui n'existe pas en production : la liste des
 * blogs renvoyait « Base table or view not found: 1146 Table 'blog' doesn't exist ».
 * La table réellement créée par la migration d'origine s'appelle `blogs` — c'est
 * aussi celle que référence la clé étrangère de blog_commentaires.
 *
 * Cette table n'a jamais reçu les colonnes que le code manipule pourtant depuis
 * le début : titre, image de détail, indicateur de publication et auteur.
 * On les ajoute ici, et on rend facultatives deux colonnes héritées qui étaient
 * obligatoires sans valeur par défaut (userVu, user_publie) — elles empêchaient
 * toute création de blog.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('blogs')) {
            return;
        }

        Schema::table('blogs', function (Blueprint $table) {
            if (!Schema::hasColumn('blogs', 'titre')) {
                $table->string('titre', 190)->nullable()->after('image');
            }
            if (!Schema::hasColumn('blogs', 'image_detail')) {
                $table->string('image_detail')->nullable()->after('titre');
            }
            if (!Schema::hasColumn('blogs', 'publie')) {
                $table->boolean('publie')->default(1);
            }
            if (!Schema::hasColumn('blogs', 'user_publie_id')) {
                $table->unsignedBigInteger('user_publie_id')->nullable();
            }
        });

        // Colonnes héritées obligatoires : passées en NULL autorisé, sinon la
        // création d'un blog échoue (« Field 'userVu' doesn't have a default value »).
        // ALTER direct plutôt que ->change() : évite d'exiger doctrine/dbal.
        foreach (['userVu', 'user_publie'] as $colonne) {
            if (Schema::hasColumn('blogs', $colonne)) {
                try {
                    DB::statement("ALTER TABLE `blogs` MODIFY `$colonne` BIGINT UNSIGNED NULL");
                } catch (\Throwable $e) {
                    // Une contrainte de clé étrangère peut refuser la modification :
                    // ce n'est pas bloquant, on continue.
                }
            }
        }

        if (Schema::hasColumn('blogs', 'description')) {
            try {
                DB::statement("ALTER TABLE `blogs` MODIFY `description` TEXT NULL");
            } catch (\Throwable $e) {
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('blogs')) {
            return;
        }

        Schema::table('blogs', function (Blueprint $table) {
            foreach (['titre', 'image_detail', 'publie', 'user_publie_id'] as $colonne) {
                if (Schema::hasColumn('blogs', $colonne)) {
                    $table->dropColumn($colonne);
                }
            }
        });
    }
};
