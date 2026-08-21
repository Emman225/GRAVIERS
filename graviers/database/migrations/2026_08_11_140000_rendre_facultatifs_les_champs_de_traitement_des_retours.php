<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Une demande de retour ne peut pas connaître, au moment où le client la
 * dépose, qui la traitera ni ce qui sera constaté à la réception. Ces deux
 * colonnes étaient pourtant obligatoires :
 *
 *   user_paie_id           — l'agent qui prononce le remboursement
 *   observation_reception  — le constat fait à la réception du produit
 *
 * Elles ne sont renseignées qu'au traitement, depuis le back-office
 * (UserController). Tant qu'elles étaient NOT NULL, l'enregistrement d'une
 * demande dépendait du mode SQL du serveur : toléré en mode permissif, refusé
 * en mode strict — où la demande échouait sur une page d'erreur.
 *
 * Les rendre facultatives fait dépendre le résultat du code, et non de la
 * configuration du serveur de base de données.
 *
 * La vue client teste déjà la présence de l'observation avant de l'afficher :
 * une valeur nulle n'y change rien.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE retour_produit MODIFY user_paie_id BIGINT UNSIGNED NULL DEFAULT NULL');
        DB::statement('ALTER TABLE retour_produit MODIFY observation_reception MEDIUMTEXT NULL DEFAULT NULL');
    }

    public function down(): void
    {
        // Restaurer la contrainte suppose de ne plus avoir de valeur nulle.
        DB::statement('UPDATE retour_produit SET user_paie_id = user_id WHERE user_paie_id IS NULL');
        DB::statement("UPDATE retour_produit SET observation_reception = '' WHERE observation_reception IS NULL");
        DB::statement('ALTER TABLE retour_produit MODIFY user_paie_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE retour_produit MODIFY observation_reception MEDIUMTEXT NOT NULL');
    }
};
