<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * tva_commande est POLYMORPHE : commande_id porte l'id d'une COMMANDE
     * (type_affaire = VENTE) ou d'une LOCATION (type_affaire = LOCATION).
     * La contrainte FK stricte vers commande(id) est donc sémantiquement fausse :
     * elle plantait l'enregistrement de la TVA d'une location dès que l'id de la
     * location n'existait pas aussi dans commande (bug dormant révélé par le
     * vidage de la base : avant, un id de commande identique existait par accident).
     */
    public function up(): void
    {
        $fk = DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'tva_commande'
               AND CONSTRAINT_NAME = 'tva_commande_commande_id_foreign'
               AND CONSTRAINT_TYPE = 'FOREIGN KEY'"
        );

        if (!empty($fk)) {
            DB::statement('ALTER TABLE tva_commande DROP FOREIGN KEY tva_commande_commande_id_foreign');
        }
    }

    public function down(): void
    {
        // On ne recrée PAS la FK : elle est incompatible avec les lignes LOCATION
        // légitimes déjà présentes (commande_id = id de location).
    }
};
