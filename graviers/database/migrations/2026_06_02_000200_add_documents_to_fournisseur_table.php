<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fournisseur', function (Blueprint $table) {
            if (!Schema::hasColumn('fournisseur', 'dfe')) {
                $table->string('dfe')->nullable();
            }
            if (!Schema::hasColumn('fournisseur', 'registre_commerce')) {
                $table->string('registre_commerce')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('fournisseur', function (Blueprint $table) {
            foreach (['dfe', 'registre_commerce'] as $col) {
                if (Schema::hasColumn('fournisseur', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
