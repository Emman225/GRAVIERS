<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('livraison', function (Blueprint $table) {
            if (!Schema::hasColumn('livraison', 'gestionnaire_id')) {
                $table->unsignedBigInteger('gestionnaire_id')->nullable();
            }
            if (!Schema::hasColumn('livraison', 'livre_par')) {
                $table->smallInteger('livre_par')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('livraison', function (Blueprint $table) {
            $table->dropColumn(['gestionnaire_id', 'livre_par']);
        });
    }
};
