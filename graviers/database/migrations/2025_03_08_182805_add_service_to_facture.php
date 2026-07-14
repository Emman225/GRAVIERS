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
        Schema::table('facture', function (Blueprint $table) {
            if (Schema::hasColumn('facture', 'commande_id')) {
                $table->dropForeign(['commande_id']);
                $table->dropColumn('commande_id');
            }
            if (!Schema::hasColumn('facture', 'service_id')) {
                $table->unsignedBigInteger('service_id')->index()->nullable();
            }
            if (!Schema::hasColumn('facture', 'service')) {
                $table->enum('service', [Help::$COMMANDE, Help::$LOCATION, Help::$LIVRAISON])->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('facture', function (Blueprint $table) {
            //
        });
    }
};
