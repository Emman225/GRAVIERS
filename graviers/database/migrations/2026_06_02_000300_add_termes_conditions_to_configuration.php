<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuration', function (Blueprint $table) {
            if (!Schema::hasColumn('configuration', 'termes_conditions')) {
                $table->longText('termes_conditions')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('configuration', function (Blueprint $table) {
            if (Schema::hasColumn('configuration', 'termes_conditions')) {
                $table->dropColumn('termes_conditions');
            }
        });
    }
};
