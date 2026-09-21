<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UNE COMMANDE NE PEUT PAS TOMBER À ZÉRO FRANC.
 *
 * Les points de fidélité pouvaient couvrir la totalité d'une commande. Il
 * suffisait pour cela que le client choisisse de venir chercher sa marchandise
 * (donc aucun coût de livraison) et que ses points valent le montant du panier.
 *
 * Le total à payer tombait alors à 0, et la commande devenait une IMPASSE,
 * quel que soit le mode de règlement choisi :
 *
 *   · EN LIGNE  — la commande était créée « EN ATTENTE DE PAIEMENT », donc
 *     invisible dans la file du gestionnaire, et la passerelle était appelée
 *     avec un montant de 0. Les points, eux, étaient DÉJÀ débités : un refus
 *     de la passerelle laissait le client sans ses points et avec une commande
 *     que personne ne voyait ;
 *
 *   · EN AGENCE — la commande était bien visible, mais l'encaissement exige
 *     un montant d'au moins 1 franc (`min:1`) : le caissier ne pouvait pas la
 *     solder ;
 *
 *   · PAR VIREMENT — il fallait joindre le justificatif d'un virement de 0.
 *
 * La règle retenue : les points ne réduisent le total à payer que jusqu'à ce
 * plancher. Le reliquat de points RESTE au compte du client et servira à la
 * commande suivante — rien n'est perdu, la remise est seulement étalée.
 *
 * 1 000 F par défaut : au-dessus du seuil des passerelles mobile money, et
 * assez bas pour ne pas priver le client de sa remise.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('configuration', 'montant_minimum_a_payer')) {
            Schema::table('configuration', function (Blueprint $table) {
                $table->double('montant_minimum_a_payer', 10, 2)->default(1000);
            });

            // Les configurations déjà en base n'ont pas de valeur : sans ce
            // remplissage, le plancher vaudrait zéro et la règle ne
            // s'appliquerait pas — exactement la situation qu'elle corrige.
            DB::table('configuration')
                ->whereNull('montant_minimum_a_payer')
                ->orWhere('montant_minimum_a_payer', '<', 0)
                ->update(['montant_minimum_a_payer' => 1000]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('configuration', 'montant_minimum_a_payer')) {
            Schema::table('configuration', function (Blueprint $table) {
                $table->dropColumn('montant_minimum_a_payer');
            });
        }
    }
};
