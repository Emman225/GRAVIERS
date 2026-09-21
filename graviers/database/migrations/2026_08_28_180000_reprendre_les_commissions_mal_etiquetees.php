<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * REPRISE DES COMMISSIONS ÉTIQUETÉES « LOCATION » À TORT.
 *
 * La migration du 28/08 à 15 h avait déjà corrigé les lignes existantes. Mais
 * le point de création du guichet continuait d'en produire : il déduisait le
 * type d'affaire de `produit.type_affaire`, colonne qui classe le CATALOGUE et
 * non la nature de l'affaire.
 *
 * Constaté en production le 28/08/2026 à 13 h 08, commission n° 44 : elle
 * désignait la commande 138 — qui existe, client 54, 192 500 F — mais portait
 * `type_affaire = 'LOCATION'`. La jointure cherchait dans `location`, ne
 * trouvait rien, et l'application apporteur affichait « null null — Total :
 * 0 F ». Le montant, lui, restait juste : il vit sur la ligne elle-même.
 *
 * La source est corrigée (`CommandeComptantController::crediterApporteur`
 * inscrit désormais 'VENTE', puisque `commande_id` y reçoit un identifiant de
 * COMMANDE). Cette migration reprend les lignes créées entre-temps.
 *
 * LA MÊME PRUDENCE QUE LA PREMIÈRE FOIS : on ne rebascule que ce qui est
 * CERTAIN — un identifiant qui désigne une commande existante et qui ne
 * désigne AUCUNE location. Les identifiants présents dans les deux tables sont
 * ambigus et laissés tels quels : les toucher fausserait une commission juste.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('commission_apporteur')) {
            return;
        }

        DB::table('commission_apporteur')
            ->where('type_affaire', 'LOCATION')
            ->whereNotNull('commande_id')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('commande')
                  ->whereColumn('commande.id', 'commission_apporteur.commande_id');
            })
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('location')
                  ->whereColumn('location.id', 'commission_apporteur.commande_id');
            })
            ->update(['type_affaire' => 'VENTE']);
    }

    /**
     * Pas de marche arrière : réétiqueter ces lignes en « LOCATION » les
     * renverrait vers une table où elles n'existent pas. On ne remet pas un
     * défaut en place.
     */
    public function down(): void
    {
    }
};
