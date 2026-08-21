<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajoute la colonne « ville_id » à la grille tarifaire des livraisons.
     *
     * Le modèle CoutLivraison l'interroge depuis toujours — dans les DEUX
     * projets, web et API :
     *
     *   lireSurCle()          : ne retient que les lignes SANS ville, c'est-à-dire
     *                           les tarifs génériques valables partout ;
     *   lireSurCleAvecVille() : lit au contraire un tarif propre à une ville.
     *
     * Mais aucune migration ne l'a jamais créée. Toute recherche de tarif levait
     * donc « Unknown column 'ville_id' », erreur remontée en 500 par l'API — et
     * c'est justement cette recherche qui chiffre une DEMANDE DE LIVRAISON créée
     * depuis l'application mobile. Le défaut restait invisible tant que les
     * essais passaient par le site, qui calcule au kilomètre sans consulter la
     * grille.
     *
     * La colonne est nullable et sans valeur par défaut : les lignes existantes
     * restent donc des tarifs GÉNÉRIQUES, ce que lireSurCle() attend. Aucun
     * tarif n'est modifié.
     */
    public function up(): void
    {
        if (Schema::hasColumn('cout_livraison', 'ville_id')) {
            return;
        }

        Schema::table('cout_livraison', function ($table) {
            $table->unsignedBigInteger('ville_id')->nullable()->after('unite_produit_id');
            $table->foreign('ville_id')->references('id')->on('ville')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('cout_livraison', 'ville_id')) {
            return;
        }

        Schema::table('cout_livraison', function ($table) {
            $table->dropForeign(['ville_id']);
            $table->dropColumn('ville_id');
        });
    }
};
