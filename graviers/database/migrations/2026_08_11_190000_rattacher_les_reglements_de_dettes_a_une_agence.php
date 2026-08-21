<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agence des règlements de dettes — fournisseurs, apporteurs, livreurs.
 *
 * Les encaissements clients portent l'agence du caissier depuis la migration
 * précédente. Les DÉCAISSEMENTS, eux, n'en portaient aucune : un règlement de
 * dette sortait de la caisse sans qu'on sache de quelle caisse. Le
 * rapprochement d'une agence était donc faux par construction, puisqu'il ne
 * voyait que les entrées.
 *
 * La demande de paiement (demande_paiement) est également concernée : c'est le
 * point d'entrée du règlement d'un livreur, d'un fournisseur ou d'un apporteur
 * depuis l'écran des dettes.
 *
 * Nullable : les lignes déjà enregistrées n'ont pas d'agence, et on n'en
 * invente pas — une agence supposée fausserait la caisse aussi sûrement qu'une
 * agence absente, mais sans qu'on puisse le voir.
 */
return new class extends Migration
{
    private array $tables = [
        'paiement_fournisseur',
        'paiement_apporteur',
        'paiement_livreur',
        'demande_paiement',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table) || Schema::hasColumn($table, 'agence_id')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedBigInteger('agence_id')->nullable()->after('user_id');
                $t->index('agence_id');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'agence_id')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->dropIndex(['agence_id']);
                $t->dropColumn('agence_id');
            });
        }
    }
};
