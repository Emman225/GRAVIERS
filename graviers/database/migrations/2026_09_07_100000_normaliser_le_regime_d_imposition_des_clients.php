<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * LES FICHES CLIENT GARDENT LEUR RÉGIME, SOUS FORME DE CODE.
 *
 * La colonne stockait le libellé entier du menu déroulant (« RNI — Régime
 * Normal d'Imposition »). Les intitulés changent le 07/09/2026 ; pour que les
 * clients existants affichent le nouveau texte, on ramène chaque valeur à son
 * code (RNI, RSI, RME, RE). L'affichage passe par App\Support\RegimeImposition,
 * qui sait aussi lire l'ancienne forme : la migration n'est donc pas un
 * préalable à l'affichage, seulement une remise en ordre.
 *
 * SQL volontairement élémentaire (UPDATE … LIKE) : identique en local et en
 * production.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['RNI', 'RSI', 'RME', 'RE'] as $code) {
            DB::table('client')
                ->where('regime_imposition', '<>', $code)
                ->where(function ($q) use ($code) {
                    $q->where('regime_imposition', 'like', $code . ' %')
                      ->orWhere('regime_imposition', 'like', $code . '—%')
                      ->orWhere('regime_imposition', 'like', $code . '-%');
                })
                ->update(['regime_imposition' => $code]);
        }
    }

    public function down(): void
    {
        // Rien à défaire : l'affichage comprend les deux formes.
    }
};
