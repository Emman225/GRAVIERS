<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reduction', function (Blueprint $table) {
            if (!Schema::hasColumn('reduction', 'devis_id')) {
                $table->unsignedBigInteger('devis_id')->nullable()->after('client_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reduction', function (Blueprint $table) {
            $table->dropColumn('devis_id');
        });
    }
};
