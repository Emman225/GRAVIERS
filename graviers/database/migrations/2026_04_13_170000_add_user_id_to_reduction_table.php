<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reduction', function (Blueprint $table) {
            if (!Schema::hasColumn('reduction', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('client_id');
            }
            // client_id doit aussi être nullable car le formulaire ne renseigne pas de client
            if (Schema::hasColumn('reduction', 'client_id')) {
                $table->unsignedBigInteger('client_id')->nullable()->change();
            }
            // montant_reduction doit être nullable (non fourni par le formulaire)
            if (Schema::hasColumn('reduction', 'montant_reduction')) {
                $table->double('montant_reduction')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('reduction', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });
    }
};
