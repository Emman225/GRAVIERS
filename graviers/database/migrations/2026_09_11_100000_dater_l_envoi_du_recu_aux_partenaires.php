<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UN REÇU PAR RÈGLEMENT DE PARTENAIRE (11/09/2026).
 *
 * Le bordereau d'un règlement de dette (fournisseur, livreur, apporteur) part
 * désormais par courriel au partenaire quand le règlement est finalisé. La
 * date d'envoi est inscrite sur le règlement, comme sur `paiement` pour les
 * clients : le premier chemin qui passe envoie, les autres se taisent.
 *
 * Colonne facultative ; sans elle, l'envoi se fait tout de même (sans mémoire).
 */
return new class extends Migration
{
    private const TABLES = ['paiement_fournisseur', 'paiement_livreur', 'paiement_apporteur'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'recu_envoye_le')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dateTime('recu_envoye_le')->nullable()->after('user_effectuee_id');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'recu_envoye_le')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('recu_envoye_le');
                });
            }
        }
    }
};
