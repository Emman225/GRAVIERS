<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commande', function (Blueprint $table) {
            if (!Schema::hasColumn('commande', 'est_livrable')) {
                $table->boolean('est_livrable')->default(0)->after('cout_livraison_client');
            }
        });
    }

    public function down(): void
    {
        Schema::table('commande', function (Blueprint $table) {
            $table->dropColumn('est_livrable');
        });
    }
};
