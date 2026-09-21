<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** La facture certifiée (vente, avoir) part une seule fois au client (lot 93, 16/09/2026). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facture', function (Blueprint $table) {
            if (!Schema::hasColumn('facture', 'courriel_envoye_le')) {
                $table->timestamp('courriel_envoye_le')->nullable()->after('fne_response_payload');
            }
        });
    }

    public function down(): void
    {
        Schema::table('facture', function (Blueprint $table) {
            if (Schema::hasColumn('facture', 'courriel_envoye_le')) {
                $table->dropColumn('courriel_envoye_le');
            }
        });
    }
};
