<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LE TYPE « POPUP » POUR LA FENÊTRE PUBLICITAIRE DE L'ACCUEIL.
 *
 * `type_banniere` est une ÉNUMÉRATION MySQL : une valeur absente de la liste
 * n'y produit pas d'erreur, elle est TRONQUÉE — la bannière s'enregistrerait
 * avec un type vide, et n'apparaîtrait nulle part. Ajouter l'option au
 * formulaire ne suffit donc pas, il faut ouvrir la colonne.
 *
 * La nouvelle valeur est placée EN FIN de liste. L'ordre d'une énumération est
 * signifiant : MySQL interprète un entier comme la POSITION de la valeur, et ce
 * projet écrit des états entiers par endroits. Insérer POPUP ailleurs qu'à la
 * fin changerait silencieusement le sens des lignes existantes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('banniere')) {
            return;
        }

        DB::statement(
            "ALTER TABLE `banniere` MODIFY `type_banniere` "
            . "ENUM('TOP','FLASH','BOTTOM','POPUP') NOT NULL"
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('banniere')) {
            return;
        }

        // Les bannières de type POPUP perdraient leur type : on les désactive
        // d'abord, plutôt que de les laisser avec une valeur tronquée.
        DB::table('banniere')->where('type_banniere', 'POPUP')->update(['statut' => 0]);
        DB::table('banniere')->where('type_banniere', 'POPUP')->update(['type_banniere' => 'BOTTOM']);

        DB::statement(
            "ALTER TABLE `banniere` MODIFY `type_banniere` "
            . "ENUM('TOP','FLASH','BOTTOM') NOT NULL"
        );
    }
};
