<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ligne_paiement', function (Blueprint $table) {
            $table->string('moyen_paiement', 30)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ligne_paiement', function (Blueprint $table) {
            $table->string('moyen_paiement', 30)->nullable(false)->change();
        });
    }
};
