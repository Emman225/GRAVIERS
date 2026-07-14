<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * L'enum `livraison.provenance` valait enum('COMMANDE','LIVRAISON') — il manquait
 * 'LOCATION', alors que le code (validation de location, API mobile detailsLivraison)
 * crée/lit des livraisons de provenance LOCATION. Sans cette valeur, l'insertion était
 * rejetée ("Data truncated for column 'provenance'"). On étend l'enum.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `livraison` MODIFY `provenance` ENUM('COMMANDE','LIVRAISON','LOCATION') NOT NULL DEFAULT 'COMMANDE'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `livraison` MODIFY `provenance` ENUM('COMMANDE','LIVRAISON') NOT NULL DEFAULT 'COMMANDE'");
    }
};
