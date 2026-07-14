<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gestion complète des demandes d'annulation (ventes + locations) :
     *  - valeur ANNULEE ajoutée aux enums etat_commande / etat_location
     *    (marqueur d'annulation propre, sans détourner le champ statut qui
     *    porte déjà l'état de PAIEMENT : 1/2/3) ;
     *  - colonnes de décision sur demande_annulation_commande (qui a traité,
     *    quand, approuvée ou refusée).
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE commande MODIFY etat_commande ENUM('EN ATTENTE','EN TRAITEMENT','TERMINEE','ANNULEE') NOT NULL DEFAULT 'EN ATTENTE'");
        DB::statement("ALTER TABLE location MODIFY etat_location ENUM('EN ATTENTE','EN COURS','TERMINE','ANNULEE') NOT NULL DEFAULT 'EN ATTENTE'");

        Schema::table('demande_annulation_commande', function (Blueprint $table) {
            if (!Schema::hasColumn('demande_annulation_commande', 'decision')) {
                // 1 = approuvée (service annulé), 2 = refusée
                $table->tinyInteger('decision')->nullable()->after('est_traite');
            }
            if (!Schema::hasColumn('demande_annulation_commande', 'traite_par')) {
                $table->unsignedBigInteger('traite_par')->nullable()->after('decision');
            }
            if (!Schema::hasColumn('demande_annulation_commande', 'decided_at')) {
                $table->dateTime('decided_at')->nullable()->after('traite_par');
            }
        });
    }

    public function down(): void
    {
        Schema::table('demande_annulation_commande', function (Blueprint $table) {
            foreach (['decision', 'traite_par', 'decided_at'] as $col) {
                if (Schema::hasColumn('demande_annulation_commande', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        // Les valeurs ANNULEE éventuelles empêcheraient un retour d'enum : on ne le rétrécit pas.
    }
};
