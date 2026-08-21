<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute la colonne `statut` aux commentaires de blog.
 *
 * Le code s'appuie sur cette colonne depuis toujours — la relation
 * blog::clients() la sélectionne via withPivot('statut'), la page publique
 * n'affiche que les commentaires de statut 2, et les deux actions de modération
 * du back-office écrivent 2 ou 3. Mais la migration d'origine ne l'a jamais
 * créée : la page « détail blog » et le bouton « Publier » tombaient en erreur
 * « Unknown column 'statut' ».
 *
 * Convention retenue, identique à celle des avis produits (NoteProduit) :
 *   1 = en attente de modération   2 = publié   3 = refusé
 *
 * Les commentaires déjà saisis passent en « en attente » : ils n'étaient de
 * toute façon visibles nulle part, et un administrateur doit les valider.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('blog_commentaires')) {
            return;
        }

        if (!Schema::hasColumn('blog_commentaires', 'statut')) {
            Schema::table('blog_commentaires', function (Blueprint $table) {
                $table->unsignedTinyInteger('statut')->default(1)->after('commentaire');
            });

            // Sécurité : une ligne antérieure pourrait porter 0 si la colonne a
            // été créée à la main entre-temps.
            DB::table('blog_commentaires')->whereNull('statut')->orWhere('statut', 0)->update(['statut' => 1]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('blog_commentaires') && Schema::hasColumn('blog_commentaires', 'statut')) {
            Schema::table('blog_commentaires', function (Blueprint $table) {
                $table->dropColumn('statut');
            });
        }
    }
};
