<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UN POINT DE FIDÉLITÉ SE GAGNE, IL NE S'OFFRE PAS À L'INSCRIPTION.
 *
 * La colonne `client.point` était créée avec une valeur par défaut de 1
 * (migration du 04/12/2024). Tout client inscrit démarrait donc avec un point
 * — dix francs de remise — sans avoir jamais rien réglé.
 *
 * Constaté le 27/08/2026 : un client passe une première commande, choisit le
 * règlement en agence, ne se présente pas au guichet — et convertit malgré
 * tout un point sur sa commande suivante. La remise est réelle, l'encaissement
 * qui aurait dû la financer n'a jamais eu lieu.
 *
 * Deux choses ici :
 *
 *   1. LA VALEUR PAR DÉFAUT PASSE À ZÉRO, pour les inscriptions à venir.
 *
 *   2. LE POINT DÉJÀ OFFERT EST REPRIS, mais seulement là où il ne peut être
 *      que celui-là : un solde d'EXACTEMENT 1 point chez un client dont AUCUN
 *      règlement n'a jamais été validé. Un client qui a réellement encaissé
 *      1 000 F porterait 2 points (le point offert plus le sien) et n'est donc
 *      pas concerné ; un client qui a payé et dépensé ses points non plus, dès
 *      lors qu'il a un règlement validé. Au 27/08/2026, sur la base de
 *      travail : 18 clients concernés, 4 écartés par cette précaution.
 *
 * La marche arrière rétablit la valeur par défaut, mais NE REDONNE PAS le
 * point : le rendre à des clients qui ne l'ont jamais gagné répéterait la
 * faute que cette migration corrige.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('client', 'point')) {
            return;
        }

        Schema::table('client', function (Blueprint $table) {
            $table->float('point')->default(0)->change();
        });

        DB::table('client')
            ->where('point', 1)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('paiement')
                  ->whereColumn('paiement.client_id', 'client.id')
                  ->where('paiement.statut', 1);
            })
            ->update(['point' => 0]);
    }

    public function down(): void
    {
        if (!Schema::hasColumn('client', 'point')) {
            return;
        }

        Schema::table('client', function (Blueprint $table) {
            $table->float('point')->default(1)->change();
        });
    }
};
