<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ajoute la valeur « EN ATTENTE DE PAIEMENT » à l'ENUM commande.etat_commande.
     *
     * Une commande à régler par paiement EN LIGNE reste dans cet état (donc HORS
     * de la file de traitement du gestionnaire, qui ne liste que EN ATTENTE /
     * EN TRAITEMENT) tant que le paiement n'est pas confirmé. Le callback / la
     * vérification pull la passe ensuite à « EN ATTENTE ».
     *
     * Sans cette valeur dans l'ENUM, Commande::create() échoue (Data truncated)
     * -> 500 sur /panier-en-commande.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE commande MODIFY etat_commande ENUM('EN ATTENTE','EN TRAITEMENT','TERMINEE','ANNULEE','EN ATTENTE DE PAIEMENT') NOT NULL DEFAULT 'EN ATTENTE'");
    }

    public function down(): void
    {
        // Ramener les commandes concernées à EN ATTENTE avant de retirer la valeur
        // de l'ENUM (sinon l'ALTER échouerait sur les lignes existantes).
        DB::statement("UPDATE commande SET etat_commande = 'EN ATTENTE' WHERE etat_commande = 'EN ATTENTE DE PAIEMENT'");
        DB::statement("ALTER TABLE commande MODIFY etat_commande ENUM('EN ATTENTE','EN TRAITEMENT','TERMINEE','ANNULEE') NOT NULL DEFAULT 'EN ATTENTE'");
    }
};
