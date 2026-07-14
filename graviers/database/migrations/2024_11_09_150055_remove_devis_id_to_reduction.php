<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reduction', function (Blueprint $table) {
            $table->dropColumn('montant_reduction');
            if (Schema::hasColumn('reduction', 'devis_id')) {
                $table->dropForeign(['devis_id']);
                $table->dropColumn('devis_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reduction', function (Blueprint $table) {
            //
        });
    }
};
