<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UN REÇU PAR RÈGLEMENT, PAS UN PAR CHEMIN.
 *
 * Un paiement en ligne est confirmé par jusqu'à trois chemins — le retour du
 * client sur le site, l'appel de la passerelle, la vérification planifiée —
 * et chacun envoyait son courriel. La date d'envoi du reçu est inscrite sur
 * le règlement : le premier chemin qui passe envoie, les autres se taisent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('paiement', 'recu_envoye_le')) {
            Schema::table('paiement', function (Blueprint $table) {
                $table->dateTime('recu_envoye_le')->nullable()->after('numero_recu');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('paiement', 'recu_envoye_le')) {
            Schema::table('paiement', function (Blueprint $table) {
                $table->dropColumn('recu_envoye_le');
            });
        }
    }
};
