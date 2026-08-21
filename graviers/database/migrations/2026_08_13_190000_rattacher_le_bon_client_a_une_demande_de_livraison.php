<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permet de rattacher un bon client à une DEMANDE DE LIVRAISON.
     *
     * Le formulaire de demande de livraison propose depuis toujours de joindre
     * un bon de commande — obligatoire pour un compte à terme. Le fichier était
     * bien téléversé et déposé dans « temp_pdfs », mais rien ne le reliait
     * ensuite à la demande : le gestionnaire ne pouvait pas le consulter, et le
     * fichier restait orphelin sur le disque.
     *
     * Pire, les clés de session qui le portent n'étaient pas purgées : le bon
     * joint à une demande de livraison pouvait se retrouver attaché à la
     * COMMANDE suivante du même client.
     *
     * On ajoute donc une colonne dédiée plutôt que de réutiliser commande_id.
     * Ce choix est délibéré : une colonne qui porte tantôt l'id d'une commande,
     * tantôt celui d'autre chose, est exactement ce qui a produit cette semaine
     * un 500 en production sur tva_commande. Chaque lien a sa colonne et sa
     * contrainte.
     */
    public function up(): void
    {
        // 1. commande_id devient facultatif : un bon rattaché à une demande de
        //    livraison n'a pas de commande. Le type ne change pas, la clé
        //    étrangère existante reste donc valide.
        if ($this->colonneObligatoire('commande_id')) {
            DB::statement('ALTER TABLE `bl_client` MODIFY `commande_id` BIGINT UNSIGNED NULL');
        }

        // 2. La colonne du nouveau lien, avec sa propre contrainte.
        if (!Schema::hasColumn('bl_client', 'demande_livraison_id')) {
            Schema::table('bl_client', function ($table) {
                $table->unsignedBigInteger('demande_livraison_id')->nullable()->after('commande_id');
                $table->foreign('demande_livraison_id')
                    ->references('id')->on('demande_livraison')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bl_client', 'demande_livraison_id')) {
            Schema::table('bl_client', function ($table) {
                $table->dropForeign(['demande_livraison_id']);
                $table->dropColumn('demande_livraison_id');
            });
        }

        // commande_id n'est PAS remis en obligatoire : des bons de demandes de
        // livraison, sans commande, peuvent exister en base. Les rendre
        // invalides casserait la table.
    }

    private function colonneObligatoire(string $colonne): bool
    {
        $resultat = DB::select(
            "SELECT IS_NULLABLE
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME   = 'bl_client'
                AND COLUMN_NAME  = ?",
            [$colonne]
        );

        return isset($resultat[0]) && $resultat[0]->IS_NULLABLE === 'NO';
    }
};
