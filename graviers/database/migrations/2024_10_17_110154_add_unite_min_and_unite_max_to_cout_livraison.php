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
        Schema::table('cout_livraison', function (Blueprint $table) {
            $table->float('unite_min')->default(0);
            $table->float('unite_max')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cout_livraison', function (Blueprint $table) {
            //
        });
    }
};
