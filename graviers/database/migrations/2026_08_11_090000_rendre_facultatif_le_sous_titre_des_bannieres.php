<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Le sous-titre d'une bannière est présenté comme facultatif — le formulaire
 * ne le marque pas obligatoire et la validation l'accepte vide — mais la
 * colonne refusait la valeur nulle. Créer une bannière sans sous-titre
 * échouait donc sur une erreur SQL, présentée à l'utilisateur comme une panne
 * du site alors qu'il n'avait rien fait de faux.
 *
 * La longueur est portée de 80 à 255 caractères par la même occasion : la
 * validation acceptait déjà 255, si bien qu'un sous-titre un peu long était
 * refusé par la base après avoir passé toutes les vérifications.
 *
 * `->change()` n'est pas utilisable ici : il exige doctrine/dbal, absent du
 * projet.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE banniere MODIFY sous_titre VARCHAR(255) NULL DEFAULT NULL');
    }

    public function down(): void
    {
        // Restaurer la contrainte suppose de ne plus avoir ni valeur nulle ni
        // valeur trop longue, sinon MySQL refuse la modification ou tronque en
        // silence selon le mode SQL actif.
        DB::statement("UPDATE banniere SET sous_titre = '' WHERE sous_titre IS NULL");
        DB::statement('UPDATE banniere SET sous_titre = LEFT(sous_titre, 80) WHERE CHAR_LENGTH(sous_titre) > 80');
        DB::statement('ALTER TABLE banniere MODIFY sous_titre VARCHAR(80) NOT NULL');
    }
};
