<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Caution unitaire d'un produit de LOCATION (fcfa). Sert à pré-remplir la caution
 * lors de la validation d'une location (somme caution × quantité par ligne).
 * Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produit', function (Blueprint $table) {
            if (!Schema::hasColumn('produit', 'caution')) {
                $table->double('caution')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('produit', function (Blueprint $table) {
            if (Schema::hasColumn('produit', 'caution')) {
                $table->dropColumn('caution');
            }
        });
    }
};
