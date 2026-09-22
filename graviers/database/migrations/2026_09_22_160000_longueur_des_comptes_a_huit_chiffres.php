<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module « Écritures comptables » : la longueur par défaut passe à huit chiffres
 * (lot 124, 22/09/2026).
 *
 * La balance Sage du comptable porte des comptes à HUIT chiffres — 40110000,
 * 41100000, 52110000, 57110000. Le module proposait six par défaut : un
 * paramétrage laissé tel quel aurait produit des numéros que son logiciel
 * n'aurait pas reconnus, et la longueur ne se change plus une fois des comptes
 * créés.
 *
 * Le réglage reste libre (6, 7 ou 8) : seule la valeur de DÉPART change, et
 * seulement tant que RIEN n'a encore été saisi. Dès qu'un compte existe, la
 * migration ne touche à rien.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('configuration', 'longueur_compte_comptable')) {
            return;
        }

        $dejaEnService = Schema::hasTable('compte_comptable') && DB::table('compte_comptable')->exists();
        if ($dejaEnService) {
            return;
        }

        DB::table('configuration')->where('longueur_compte_comptable', 6)->update(['longueur_compte_comptable' => 8]);
    }

    public function down(): void
    {
        // On ne revient pas en arrière : reculer la longueur sous des comptes déjà
        // créés les rendrait invalides d'un coup.
    }
};
