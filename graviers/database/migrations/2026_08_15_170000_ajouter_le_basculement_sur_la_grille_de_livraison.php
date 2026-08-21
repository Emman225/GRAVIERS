<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Interrupteur du mode de tarification du transport pour les VENTES et les
     * LOCATIONS.
     *
     *   0 (défaut) — formule kilométrique : distance × prixKm, avec plancher.
     *                C'est le comportement actuel, inchangé.
     *   1          — grille tarifaire : forfait par (unité, quantité, distance),
     *                le même barème que les demandes de livraison.
     *
     * Le basculement est un interrupteur et non un déploiement, pour deux
     * raisons : la grille doit d'abord être complète — « php artisan
     * grille:auditer » le dit — et le passage multiplie les prix de transport
     * par un facteur important. L'entreprise choisit donc son moment, et peut
     * revenir en arrière en un clic si la facturation ne convient pas.
     */
    public function up(): void
    {
        if (Schema::hasColumn('configuration', 'livraison_sur_grille')) {
            return;
        }

        Schema::table('configuration', function (Blueprint $table) {
            $table->boolean('livraison_sur_grille')->default(false)->after('cout_livraison_min');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('configuration', 'livraison_sur_grille')) {
            return;
        }

        Schema::table('configuration', function (Blueprint $table) {
            $table->dropColumn('livraison_sur_grille');
        });
    }
};
