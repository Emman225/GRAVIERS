<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detail_devis', function (Blueprint $table) {
            if (!Schema::hasColumn('detail_devis', 'cout_livraison')) {
                $table->double('cout_livraison')->nullable()->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('detail_devis', function (Blueprint $table) {
            $table->dropColumn('cout_livraison');
        });
    }
};
