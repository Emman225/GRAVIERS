<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * users.contact était en varchar(15), alors que :
 *
 *   - le formulaire « Mon profil » accepte 20 caractères ;
 *   - sa validation en autorisait 30 ;
 *   - son propre exemple, « 07 XX XX XX XX », invite à saisir des espaces.
 *
 * Un numéro avec indicatif et espaces — « +225 07 12 34 56 78 », 19 caractères —
 * passait donc toutes les vérifications avant d'être refusé par la base, en
 * pleine écriture : page blanche et erreur 500, sans le moindre message.
 *
 * La colonne est portée à 20, la taille du champ. La validation est alignée et
 * le numéro est désormais normalisé avant enregistrement (espaces retirés),
 * comme le font déjà les formulaires d'inscription.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY contact VARCHAR(20) NOT NULL DEFAULT ''");
    }

    public function down(): void
    {
        // Rétrécir suppose de ne plus rien avoir de trop long.
        DB::statement('UPDATE users SET contact = LEFT(contact, 15) WHERE CHAR_LENGTH(contact) > 15');
        DB::statement("ALTER TABLE users MODIFY contact VARCHAR(15) NOT NULL");
    }
};
