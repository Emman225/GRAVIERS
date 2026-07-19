<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * qte_livree était INTEGER alors que les quantités sont décimales.
 *
 * detail_commande.qte est un FLOAT (les commandes se font en tonnes : 0.5, 1.5…)
 * et livraison.qte aussi, mais qte_livree (migration 2024_09_14) a été créée en
 * INTEGER. À la validation d'une livraison partielle, MySQL ARRONDIT la somme :
 * livrer 0.5 tonne sur 1 stockait qte_livree = 1 -> la ligne paraissait
 * totalement livrée -> la commande passait TERMINEE et disparaissait de
 * /orders-list, sans aucun moyen de traiter le reliquat (cas réel : commande 41,
 * livraison de 0.50 sur qte 1.00).
 *
 * Tout le système lit cette colonne : le passage TERMINEE (LivreurController
 * mobile, logique « toutLivre »), le badge NON_LIVREE/PARTIELLE/TOTALE
 * (DetailCommande::statutLivraison, orders-details). On aligne donc son type
 * sur celui de qte (DOUBLE).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('detail_commande', 'qte_livree')) {
            DB::statement("ALTER TABLE detail_commande MODIFY qte_livree DOUBLE NULL");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('detail_commande', 'qte_livree')) {
            DB::statement("ALTER TABLE detail_commande MODIFY qte_livree INT NULL");
        }
    }
};
