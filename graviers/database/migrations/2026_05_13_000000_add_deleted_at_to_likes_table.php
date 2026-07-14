<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajoute la colonne deleted_at à la table likes.
     * Utilisée par ClientController@like et @likePlus comme flag de soft-delete
     * (toggle favoris). La colonne manquait dans la migration d'origine.
     */
    public function up(): void
    {
        Schema::table('likes', function (Blueprint $table) {
            if (!Schema::hasColumn('likes', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('likes', function (Blueprint $table) {
            if (Schema::hasColumn('likes', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
