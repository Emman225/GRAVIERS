<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuration', function (Blueprint $table) {
            $table->string('raison_sociale')->nullable();
            $table->string('ncc')->nullable();
            $table->string('regime_imposition')->nullable();
            $table->string('centre_impots')->nullable();
            $table->string('rccm')->nullable();
            $table->string('ref_bancaires')->nullable();
            $table->string('cnps')->nullable();
            $table->string('capital_social')->nullable();
            $table->string('adresse_siege')->nullable();
            $table->string('telephone')->nullable();
            $table->string('email_entreprise')->nullable();
            $table->string('nom_etablissement')->nullable();
            $table->string('nom_pdv')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('configuration', function (Blueprint $table) {
            $table->dropColumn([
                'raison_sociale', 'ncc', 'regime_imposition', 'centre_impots',
                'rccm', 'ref_bancaires', 'cnps', 'capital_social',
                'adresse_siege', 'telephone', 'email_entreprise',
                'nom_etablissement', 'nom_pdv'
            ]);
        });
    }
};
