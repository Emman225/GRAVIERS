<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LE BON DE LIVRAISON PART AU CLIENT ENTREPRISE (lot 84, 15/09/2026).
 *
 *  - livraison.date_livree : le moment où la course est passée « LIVREE » (site
 *    ou application) — la date imprimée dans « Date enlèvement ou livraison »
 *    et dans l'historique du bon ; à défaut, la validation du fournisseur.
 *  - enlevement.bon_envoye_le : le bon de livraison n'est envoyé qu'une fois.
 * Colonnes facultatives : sans elles, tout fonctionne (sans mémoire d'envoi).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('livraison') && !Schema::hasColumn('livraison', 'date_livree')) {
            Schema::table('livraison', fn (Blueprint $t) => $t->dateTime('date_livree')->nullable());
        }
        if (Schema::hasTable('enlevement') && !Schema::hasColumn('enlevement', 'bon_envoye_le')) {
            Schema::table('enlevement', fn (Blueprint $t) => $t->dateTime('bon_envoye_le')->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('livraison') && Schema::hasColumn('livraison', 'date_livree')) {
            Schema::table('livraison', fn (Blueprint $t) => $t->dropColumn('date_livree'));
        }
        if (Schema::hasTable('enlevement') && Schema::hasColumn('enlevement', 'bon_envoye_le')) {
            Schema::table('enlevement', fn (Blueprint $t) => $t->dropColumn('bon_envoye_le'));
        }
    }
};
