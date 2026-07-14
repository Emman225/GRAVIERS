<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuration', function (Blueprint $table) {
            if (!Schema::hasColumn('configuration', 'delai_relance_standard')) {
                $table->integer('delai_relance_standard')->default(7);
            }
            if (!Schema::hasColumn('configuration', 'seuil_alerte_retard')) {
                $table->integer('seuil_alerte_retard')->default(15);
            }
        });

        $modesAttendus = [
            'Virement bancaire',
            'Chèque',
            'Espèces',
            'Carte bancaire',
        ];

        foreach ($modesAttendus as $libelle) {
            $exists = DB::table('mode_paiement')->where('libelle', $libelle)->exists();
            if (!$exists) {
                DB::table('mode_paiement')->insert([
                    'libelle'    => $libelle,
                    'statut'     => 1,
                    'en_ligne'   => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('configuration', function (Blueprint $table) {
            foreach (['delai_relance_standard', 'seuil_alerte_retard'] as $col) {
                if (Schema::hasColumn('configuration', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
