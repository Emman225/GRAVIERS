<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Mode « site en construction » (lot 114, 19/09/2026) : un interrupteur dans la configuration. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('configuration', 'site_en_construction')) {
            Schema::table('configuration', function (Blueprint $table) {
                $table->boolean('site_en_construction')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('configuration', 'site_en_construction')) {
            Schema::table('configuration', function (Blueprint $table) {
                $table->dropColumn('site_en_construction');
            });
        }
    }
};
