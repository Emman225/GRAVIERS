<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** La nature de l'organisation pour la DGI (B2B, B2G, B2F) — lot 100, 16/09/2026. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client', function (Blueprint $table) {
            if (!Schema::hasColumn('client', 'nature_fne')) {
                $table->string('nature_fne', 4)->nullable()->after('type_client');
            }
        });
    }

    public function down(): void
    {
        Schema::table('client', function (Blueprint $table) {
            if (Schema::hasColumn('client', 'nature_fne')) {
                $table->dropColumn('nature_fne');
            }
        });
    }
};
