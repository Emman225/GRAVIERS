<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La table contact portait une contrainte d'unicité sur l'adresse e-mail, et
 * l'enregistrement se faisait par updateOrCreate sur cette même colonne : un
 * client qui écrivait une seconde fois ÉCRASAIT son message précédent, même
 * des mois plus tard et sur un tout autre sujet. Il ne restait qu'une ligne
 * par adresse.
 *
 * L'unicité est levée : un message est un événement daté, pas une fiche
 * d'annuaire. Les messages déjà perdus le restent, mais plus aucun ne le sera.
 *
 * Un index simple remplace l'index unique : retrouver tous les échanges avec
 * une même personne reste rapide.
 */
return new class extends Migration
{
    public function up(): void
    {
        // L'index peut déjà avoir été retiré à la main : on ne fait rien dans
        // ce cas plutôt que de faire échouer toute la migration.
        if ($this->indexExiste('contact_email_unique')) {
            Schema::table('contact', function (Blueprint $table) {
                $table->dropUnique('contact_email_unique');
            });
        }

        if (!$this->indexExiste('contact_email_index')) {
            Schema::table('contact', function (Blueprint $table) {
                $table->index('email', 'contact_email_index');
            });
        }
    }

    public function down(): void
    {
        // Rétablir l'unicité suppose de n'avoir qu'un message par adresse :
        // on ne détruit rien ici, on se contente de retirer l'index simple.
        if ($this->indexExiste('contact_email_index')) {
            Schema::table('contact', function (Blueprint $table) {
                $table->dropIndex('contact_email_index');
            });
        }
    }

    private function indexExiste(string $nom): bool
    {
        return count(DB::select("SHOW INDEX FROM contact WHERE Key_name = ?", [$nom])) > 0;
    }
};
