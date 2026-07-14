<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facture', function (Blueprint $table) {
            if (!Schema::hasColumn('facture', 'date_echeance')) {
                $table->date('date_echeance')->nullable()->after('client_id');
            }
            if (!Schema::hasColumn('facture', 'observations')) {
                $table->text('observations')->nullable()->after('date_echeance');
            }
            if (!Schema::hasColumn('facture', 'statut_creance')) {
                $table->string('statut_creance', 30)->nullable()->after('observations');
            }
        });
    }

    public function down(): void
    {
        Schema::table('facture', function (Blueprint $table) {
            foreach (['date_echeance', 'observations', 'statut_creance'] as $col) {
                if (Schema::hasColumn('facture', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
