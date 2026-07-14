<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ville', function (Blueprint $table) {
            if (!Schema::hasColumn('ville', 'region_id')) {
                $table->unsignedBigInteger('region_id')->nullable()->after('pays_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ville', function (Blueprint $table) {
            $table->dropColumn('region_id');
        });
    }
};
