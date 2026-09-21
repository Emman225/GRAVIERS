<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LA DÉROGATION : un taux propre à un produit.
 *
 * Le taux général vaut pour tout le catalogue, mais certaines familles ne
 * supportent pas la même marge — un produit très cher à l'achat, un article
 * d'appel, une gamme où la concurrence est plus dure.
 *
 * Une dérogation passe par la MÊME double validation que le taux général :
 * c'est une décision de prix, elle ne se prend pas seul. On réutilise donc la
 * table existante plutôt que d'en créer une seconde aux mêmes règles ; une
 * ligne sans produit est le taux général, une ligne avec produit est sa
 * dérogation.
 *
 * `taux` devient nullable : une ligne à taux vide, sur un produit, demande le
 * RETOUR au taux général — décision qui mérite le même contrôle que la
 * dérogation elle-même.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('pourcentage_dalakoun', 'produit_id')) {
            Schema::table('pourcentage_dalakoun', function (Blueprint $table) {
                $table->unsignedBigInteger('produit_id')->nullable()->after('id');
                $table->index(['produit_id', 'statut']);
            });
        }

        // `->change()` réclamerait doctrine/dbal, absent de ce projet.
        DB::statement('ALTER TABLE pourcentage_dalakoun MODIFY taux DOUBLE NULL');
    }

    public function down(): void
    {
        if (Schema::hasColumn('pourcentage_dalakoun', 'produit_id')) {
            Schema::table('pourcentage_dalakoun', function (Blueprint $table) {
                $table->dropIndex(['produit_id', 'statut']);
                $table->dropColumn('produit_id');
            });
        }
    }
};
