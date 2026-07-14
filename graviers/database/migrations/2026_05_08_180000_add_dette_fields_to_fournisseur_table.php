<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fournisseur', function (Blueprint $table) {
            if (!Schema::hasColumn('fournisseur', 'code')) {
                $table->string('code', 30)->nullable()->after('id');
                $table->index('code');
            }
            if (!Schema::hasColumn('fournisseur', 'type_fournisseur')) {
                $table->string('type_fournisseur', 30)->nullable();
            }
            if (!Schema::hasColumn('fournisseur', 'produit_principal')) {
                $table->string('produit_principal', 100)->nullable();
            }
            if (!Schema::hasColumn('fournisseur', 'delai_paiement')) {
                $table->integer('delai_paiement')->default(30);
            }
            if (!Schema::hasColumn('fournisseur', 'notes')) {
                $table->text('notes')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('fournisseur', function (Blueprint $table) {
            foreach (['code', 'type_fournisseur', 'produit_principal', 'delai_paiement', 'notes'] as $col) {
                if (Schema::hasColumn('fournisseur', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
