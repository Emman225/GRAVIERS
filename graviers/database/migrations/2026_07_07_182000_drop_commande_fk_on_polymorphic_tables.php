<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Même correctif que pour tva_commande : ces tables sont POLYMORPHES
     * (commande_id porte l'id d'une COMMANDE ou d'une LOCATION selon le
     * discriminant service / type_affaire). La FK stricte vers commande(id)
     * casse tous les flux LOCATION dès que l'id de la location n'existe pas
     * aussi dans commande (bug dormant révélé par le vidage de la base) :
     *  - preuve_operation_banque : preuve de virement d'une location (500 mobile)
     *  - commission_apporteur    : commission sur une location
     *  - demande_annulation_commande : demande d'annulation d'une location
     */
    public function up(): void
    {
        $fks = [
            'preuve_operation_banque'     => 'preuve_operation_banque_commande_id_foreign',
            'commission_apporteur'        => 'commission_apporteur_commande_id_foreign',
            'demande_annulation_commande' => 'demande_annulation_commande_commande_id_foreign',
        ];

        foreach ($fks as $table => $fk) {
            $existe = DB::select(
                "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                   AND CONSTRAINT_NAME = ?
                   AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
                [$table, $fk]
            );
            if (!empty($existe)) {
                DB::statement("ALTER TABLE `$table` DROP FOREIGN KEY `$fk`");
            }
        }
    }

    public function down(): void
    {
        // On ne recrée PAS ces FK : incompatibles avec les lignes LOCATION
        // légitimes (commande_id = id de location).
    }
};
