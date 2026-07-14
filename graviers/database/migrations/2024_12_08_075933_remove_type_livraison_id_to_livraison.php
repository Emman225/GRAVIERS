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
        Schema::table('livraison', function (Blueprint $table) {
            if (Schema::hasColumn('livraison', 'type_livraison_id')) {
                $table->dropForeign(['type_livraison_id']);
                $table->dropColumn('type_livraison_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('livraison', function (Blueprint $table) {
            //
        });
    }
};
