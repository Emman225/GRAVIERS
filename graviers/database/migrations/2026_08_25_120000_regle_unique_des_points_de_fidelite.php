<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UNE SEULE RÈGLE POUR LES POINTS DE FIDÉLITÉ, ET DE QUOI LES REPRENDRE.
 *
 * Trois règles coexistaient, selon la façon dont le client payait :
 *
 *   · au guichet          : +200 points forfaitaires, écrits en dur ;
 *   · en ligne (mobile)   : 1 à 15 points selon la grille interval_point ;
 *   · en ligne (site)     : RIEN.
 *
 * Un client réglant 5 000 000 F gagnait donc 200 points au guichet, 1 par le
 * mobile, zéro depuis le site. Le canal de paiement décidait de la récompense.
 *
 * Deux colonnes suffisent à tout remettre d'aplomb :
 *
 *   · configuration.montant_pour_un_point — « X francs encaissés = 1 point ».
 *     Réglé à 1 000 F : avec un point valant 10 F, l'entreprise rend 1 % des
 *     achats. La règle devient paramétrable, comme la TVA ou la valeur du
 *     point, au lieu de vivre dans deux fichiers de code.
 *
 *   · paiement.points_attribues — COMBIEN ce règlement a rapporté.
 *     Sans cette trace, annuler une commande obligerait à DEVINER les points à
 *     reprendre. On retire désormais exactement ce qui a été donné.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('configuration', 'montant_pour_un_point')) {
            Schema::table('configuration', function (Blueprint $table) {
                $table->double('montant_pour_un_point', 8, 2)->default(1000);
            });

            // Les configurations déjà en base n'ont pas de valeur : sans ce
            // remplissage, une division par zéro attendrait le premier
            // encaissement.
            DB::table('configuration')
                ->where('montant_pour_un_point', '<=', 0)
                ->orWhereNull('montant_pour_un_point')
                ->update(['montant_pour_un_point' => 1000]);
        }

        if (!Schema::hasColumn('paiement', 'points_attribues')) {
            Schema::table('paiement', function (Blueprint $table) {
                // Les règlements ANTÉRIEURS restent à zéro : on ne sait pas ce
                // qu'ils ont donné, et inventer un chiffre serait pire que de
                // n'en avoir aucun. Une annulation portant sur eux ne retirera
                // donc rien — c'est le comportement le plus sûr.
                $table->double('points_attribues', 8, 2)->default(0);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('configuration', 'montant_pour_un_point')) {
            Schema::table('configuration', function (Blueprint $table) {
                $table->dropColumn('montant_pour_un_point');
            });
        }

        if (Schema::hasColumn('paiement', 'points_attribues')) {
            Schema::table('paiement', function (Blueprint $table) {
                $table->dropColumn('points_attribues');
            });
        }
    }
};
