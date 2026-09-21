<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LA DÉROGATION AU POURCENTAGE GÉNÉRAL, produit par produit.
 *
 * Le taux DALAKOUN s'applique à tout le catalogue. Certains articles demandent
 * pourtant un traitement propre : un produit d'appel vendu à faible marge, un
 * matériel rare vendu plus cher. Ce champ, laissé vide, ne change rien ;
 * renseigné, il prime sur le taux général pour ce seul produit.
 *
 * La colonne est créée maintenant parce que le CALCUL du prix doit savoir la
 * lire. Elle n'est encore modifiable par aucun écran : l'étape suivante lui
 * ajoutera sa saisie, avec la même double validation que le taux général.
 * Personne ne peut donc, aujourd'hui, dévier un prix sans contrôle.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('produit', 'pourcentage_dalakoun')) {
            return;
        }

        Schema::table('produit', function (Blueprint $table) {
            // NULL = pas de dérogation, le taux général s'applique.
            // Une valeur, même zéro, est une décision : zéro signifie « vendu
            // au prix d'achat », et doit donc être distinguée de l'absence.
            $table->double('pourcentage_dalakoun')->nullable()->after('prix_fournisseur');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('produit', 'pourcentage_dalakoun')) {
            return;
        }

        Schema::table('produit', function (Blueprint $table) {
            $table->dropColumn('pourcentage_dalakoun');
        });
    }
};
