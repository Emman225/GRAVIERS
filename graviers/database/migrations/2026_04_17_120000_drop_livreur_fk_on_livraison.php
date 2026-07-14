<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Supprimer FK livreur_id sur livraison (permet null pour "sans livraison")
        $fk = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_NAME = 'livraison' AND CONSTRAINT_NAME = 'livraison_livreur_id_foreign' AND TABLE_SCHEMA = DATABASE()");
        if (count($fk) > 0) {
            Schema::table('livraison', function (Blueprint $table) {
                $table->dropForeign('livraison_livreur_id_foreign');
            });
        }

        // Rendre livreur_id nullable dans enlevement aussi
        Schema::table('enlevement', function (Blueprint $table) {
            $table->unsignedBigInteger('livreur_id')->nullable()->change();
        });
    }

    public function down(): void {}
};
