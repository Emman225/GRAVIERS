<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Les données de la page de vérification de la DGI, mémorisées sur la facture (lot 96, 16/09/2026). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facture', function (Blueprint $table) {
            if (!Schema::hasColumn('facture', 'fne_verification_payload')) {
                $table->json('fne_verification_payload')->nullable()->after('fne_response_payload');
            }
        });
    }

    public function down(): void
    {
        Schema::table('facture', function (Blueprint $table) {
            if (Schema::hasColumn('facture', 'fne_verification_payload')) {
                $table->dropColumn('fne_verification_payload');
            }
        });
    }
};
