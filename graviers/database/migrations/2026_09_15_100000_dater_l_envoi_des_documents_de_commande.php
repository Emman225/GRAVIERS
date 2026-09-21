<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LA PROFORMA OU LA FACTURE D'UNE COMMANDE PART UNE SEULE FOIS (lot 81, 15/09/2026).
 *
 * Après la commande, le client reçoit sa proforma en PDF ; s'il a payé en
 * ligne, sa facture. Plusieurs chemins peuvent y mener (validation du panier,
 * retour de la passerelle, rappel serveur, vérification différée) : la date
 * d'envoi est inscrite sur la commande, le premier chemin qui passe envoie,
 * les autres se taisent — comme paiement.recu_envoye_le pour les reçus.
 *
 * Colonnes facultatives : sans elles, l'envoi se fait tout de même (sans mémoire).
 */
return new class extends Migration
{
    private const COLONNES = ['proforma_envoyee_le', 'facture_envoyee_le'];

    public function up(): void
    {
        if (!Schema::hasTable('commande')) {
            return;
        }
        foreach (self::COLONNES as $colonne) {
            if (!Schema::hasColumn('commande', $colonne)) {
                Schema::table('commande', function (Blueprint $t) use ($colonne) {
                    $t->dateTime($colonne)->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('commande')) {
            return;
        }
        foreach (self::COLONNES as $colonne) {
            if (Schema::hasColumn('commande', $colonne)) {
                Schema::table('commande', function (Blueprint $t) use ($colonne) {
                    $t->dropColumn($colonne);
                });
            }
        }
    }
};
