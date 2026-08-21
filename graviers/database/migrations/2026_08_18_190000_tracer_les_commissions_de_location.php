<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Les commissions d'apporteur sur LOCATION ne laissaient aucune trace.
 *
 * Sur une vente, l'encaissement crée une `commission_apporteur` puis crédite
 * le solde. Sur une location, LocationComptantController créditait le solde
 * SANS créer la moindre commission :
 *
 *     $apporteur->update(['solde' => $apporteur->solde + $montantTranche * $taux / 100]);
 *
 * Conséquence : ce solde n'était justifiable par aucune pièce. Impossible de
 * savoir d'où il venait, de le contrôler, ou de le reconstituer s'il dérivait
 * — et l'écran « Commissions » ignorait purement et simplement les locations.
 *
 * La table anticipait pourtant le cas : `type_affaire` est un ENUM
 * ('LOCATION','VENTE'). Ce qui bloquait, c'est `commande_id` NOT NULL — une
 * location n'est pas une commande. On rend donc `commande_id` nullable et on
 * ajoute `location_id`, l'un ou l'autre étant renseigné selon l'affaire.
 *
 * Écrit en SQL brut plutôt qu'avec le constructeur de schéma : `->change()`
 * réclame doctrine/dbal, absent de ce projet. Chaque étape est précédée de sa
 * vérification, pour que la migration puisse être rejouée sans erreur.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. La clé étrangère sur `commande_id` interdit de rendre la colonne
        //    nullable tant qu'elle est en place. On la retire si elle existe,
        //    en lisant son vrai nom plutôt qu'en le devinant.
        foreach ($this->clesEtrangeresSur('commande_id') as $nom) {
            DB::statement("ALTER TABLE `commission_apporteur` DROP FOREIGN KEY `{$nom}`");
        }

        // 2. La colonne accepte désormais l'absence de commande.
        DB::statement('ALTER TABLE `commission_apporteur` MODIFY `commande_id` BIGINT UNSIGNED NULL');

        // 3. Et la location trouve sa place.
        if (!Schema::hasColumn('commission_apporteur', 'location_id')) {
            DB::statement('ALTER TABLE `commission_apporteur` ADD COLUMN `location_id` BIGINT UNSIGNED NULL AFTER `commande_id`');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('commission_apporteur', 'location_id')) {
            DB::statement('ALTER TABLE `commission_apporteur` DROP COLUMN `location_id`');
        }
    }

    /**
     * Le nom réel des clés étrangères posées sur une colonne.
     *
     * Il varie selon la façon dont la table a été créée : le deviner
     * (`table_colonne_foreign`) faisait échouer la migration là où la
     * convention n'avait pas été suivie.
     */
    private function clesEtrangeresSur(string $colonne): array
    {
        $lignes = DB::select(
            'SELECT CONSTRAINT_NAME AS nom
               FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND COLUMN_NAME = ?
                AND REFERENCED_TABLE_NAME IS NOT NULL',
            ['commission_apporteur', $colonne]
        );

        return array_map(fn ($l) => $l->nom, $lignes);
    }
};
