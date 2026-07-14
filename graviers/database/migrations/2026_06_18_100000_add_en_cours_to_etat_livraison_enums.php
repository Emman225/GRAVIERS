<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute le 4e état "EN COURS LIVRAISON" aux colonnes ENUM `etat_livraison`.
 *
 * Contexte : du code (ex. SellerController::bonValidation) écrit l'entier 4 sur
 * ces colonnes ENUM (MySQL interprète un entier comme l'index 1-based de la valeur).
 * L'ENUM n'ayant que 3 valeurs, l'écriture de 4 provoquait
 * "SQLSTATE[01000]: 1265 Data truncated". On ajoute la 4e valeur en 4e position
 * pour préserver la convention int->index existante (1,2,3 inchangés).
 */
return new class extends Migration
{
    private array $tables = ['livraison', 'detail_commande', 'detail_livraison'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            DB::statement("ALTER TABLE `{$table}` MODIFY `etat_livraison` ENUM('EN ATTENTE','EN TRAITEMENT','LIVREE','EN COURS LIVRAISON') NOT NULL");
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            // Retour à 3 valeurs (échouera si des lignes utilisent encore le 4e état).
            DB::statement("ALTER TABLE `{$table}` MODIFY `etat_livraison` ENUM('EN ATTENTE','EN TRAITEMENT','LIVREE') NOT NULL");
        }
    }
};
