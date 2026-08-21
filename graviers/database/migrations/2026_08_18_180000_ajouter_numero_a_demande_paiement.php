<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La colonne `numero` manquait à `demande_paiement` sur le serveur.
 *
 * UserController::demandeDepaie écrit `numero` à chaque demande de paiement
 * (livreur, apporteur, fournisseur) :
 *
 *     DemandePaiement::create(['numero' => Help::getCommandeNo(), ...])
 *
 * Or aucune migration ne l'avait jamais créée : elle existait en développement
 * — ajoutée à la main, ou héritée d'un import — mais pas en production. Toute
 * demande de paiement s'y terminait donc par
 * « Unknown column 'numero' in 'field list' », c'est-à-dire une erreur 500 et
 * une page blanche.
 *
 * Le portail fournisseur, lui, avait contourné le problème en commentant la
 * ligne `numero` — ce qui explique qu'il fonctionnait pendant que l'écran
 * partagé échouait.
 *
 * `numero` n'est pas décoratif : valideDemande s'en sert comme repli pour les
 * demandes antérieures à `solde_debite_initiation` (seul un règlement de dette
 * initié par un administrateur en génère un). On rétablit donc la colonne
 * plutôt que de cesser de l'écrire.
 *
 * Nullable : les demandes déjà enregistrées n'en ont pas, et ne doivent pas
 * être rejetées.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('demande_paiement', 'numero')) {
            Schema::table('demande_paiement', function (Blueprint $table) {
                $table->string('numero', 50)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('demande_paiement', 'numero')) {
            Schema::table('demande_paiement', function (Blueprint $table) {
                $table->dropColumn('numero');
            });
        }
    }
};
