<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D'OÙ VIENT LA RÉMUNÉRATION DE CETTE COURSE.
 *
 * Deux sources possibles : la grille de facturation du livreur, ou son mode de
 * tarification quand aucune tranche ne couvre la course. Le montant seul ne
 * permet pas de les distinguer — on ne pouvait que le déduire du fait que
 * « Frais KM » soit vide, ce qui est une inférence, pas une preuve.
 *
 * On enregistre donc la source AU MOMENT DU CALCUL. La recalculer après coup
 * donnerait la réponse d'aujourd'hui, pas celle du jour de la course : une
 * tranche ajoutée depuis ferait mentir l'historique.
 *
 * Colonne nullable : les livraisons antérieures n'ont pas cette information, et
 * l'inventer serait pire que de l'admettre.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('livraison', 'source_tarif')) {
            Schema::table('livraison', function (Blueprint $table) {
                $table->string('source_tarif', 10)->nullable()->after('frais_km');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('livraison', 'source_tarif')) {
            Schema::table('livraison', function (Blueprint $table) {
                $table->dropColumn('source_tarif');
            });
        }
    }
};
