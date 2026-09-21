<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DES COMMISSIONS DE VENTE ÉTAIENT ÉTIQUETÉES « LOCATION ».
 *
 * `commission_apporteur.commande_id` est POLYMORPHE : selon `type_affaire`, il
 * désigne une COMMANDE ou une LOCATION. Or l'un des trois points de création —
 * `PaiementController::addCommission`, celui du règlement au guichet — ne
 * renseignait pas la colonne. Elle est NOT NULL sans valeur par défaut, et
 * MySQL retient alors la PREMIÈRE valeur de l'énumération, qui se trouve être
 * 'LOCATION'.
 *
 * Ces commissions allaient donc chercher leur client dans la table des
 * locations. Constaté le 28/08/2026 sur l'application apporteur :
 * « null null (# null) — Total : 0 F ». Le montant de la commission restait
 * juste, puisqu'il vit sur la ligne elle-même ; seuls le client et le montant
 * de l'affaire manquaient.
 *
 * LE CRITÈRE DE REPRISE EST ÉTROIT, et c'est voulu. On ne rebascule une ligne
 * en 'VENTE' que si son identifiant désigne une COMMANDE qui existe ET aucune
 * LOCATION. Une ligne dont l'identifiant existe des deux côtés est ambiguë :
 * on n'y touche pas, quitte à laisser un cas à traiter à la main plutôt que
 * d'en fausser un autre.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('commission_apporteur') || !Schema::hasTable('location')) {
            return;
        }

        DB::table('commission_apporteur')
            ->where('type_affaire', 'LOCATION')
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

    public function down(): void
    {
        // On ne rebascule rien en arrière : ces lignes portent désormais le type
        // JUSTE, et les remettre en 'LOCATION' recréerait le défaut corrigé ici.
    }
};
