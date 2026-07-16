<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * « Retrait sur place » pour les LOCATIONS.
 *
 * Le client pouvait déjà choisir « Retrait sur place » au checkout (le bloc radio
 * onMeLivre de client/adresse.blade.php n'est conditionné à aucun type_affaire, il
 * s'affiche donc aussi pour les locations), mais ce choix n'était persisté NULLE PART :
 * contrairement à `commande`, la table `location` n'avait pas de colonne est_livrable.
 * Il ne servait qu'à ne pas facturer la livraison, puis était perdu.
 *
 * Conséquence : validerLocation exige livreur + vehicule (required), donc une location
 * en retrait était INVALIDABLE — il fallait inventer un livreur, ce qui créait une
 * livraison fantôme et envoyait au client un code de livraison qui n'arriverait jamais.
 *
 * On aligne donc `location` sur `commande` (qui a est_livrable depuis 2026_04_14_130000).
 *
 * Défaut à 1 (livrable) et NON 0 comme sur commande : c'est le comportement actuel de
 * toutes les locations existantes. Un défaut à 0 ferait basculer d'un coup tout
 * l'historique en « retrait ».
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('location', 'est_livrable')) {
            return;
        }

        Schema::table('location', function (Blueprint $table) {
            $table->boolean('est_livrable')->default(1)->after('cout_livraison_client');
        });

        // Rattrapage de l'historique. Seule trace exploitable du retrait : le coût de
        // livraison nul (une vraie livraison est toujours facturée au moins
        // cout_livraison_min, cf. Help::coutLivraison ; le retrait, lui, court-circuite
        // tout le calcul -> 0).
        //
        // MAIS cout_livraison_client n'existe que depuis 2026_07_01_140000 : les locations
        // antérieures valent 0 par DÉFAUT, sans rapport avec le choix réel. On restreint
        // donc le rattrapage aux locations ENCORE EN ATTENTE, cad les seules où le drapeau
        // sert encore à quelque chose (celles déjà traitées ne seront jamais revalidées).
        // En cas d'erreur sur l'une d'elles, le gestionnaire peut de toute façon corriger
        // le mode directement sur la page de validation.
        DB::table('location')
            ->where('etat_location', 'EN ATTENTE')
            ->where(function ($q) {
                $q->where('cout_livraison_client', 0)->orWhereNull('cout_livraison_client');
            })
            ->update(['est_livrable' => 0]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('location', 'est_livrable')) {
            Schema::table('location', function (Blueprint $table) {
                $table->dropColumn('est_livrable');
            });
        }
    }
};
