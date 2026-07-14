<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facture', function (Blueprint $table) {
            $table->string('numero_fne', 50)->nullable()->unique()->after('numero');
        });
    }

    public function down(): void
    {
        Schema::table('facture', function (Blueprint $table) {
            $table->dropColumn('numero_fne');
        });
    }
};
