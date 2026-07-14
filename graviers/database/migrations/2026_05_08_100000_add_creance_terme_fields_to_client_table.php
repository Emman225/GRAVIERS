<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client', function (Blueprint $table) {
            if (!Schema::hasColumn('client', 'plafond_credit')) {
                $table->bigInteger('plafond_credit')->default(0)->after('point');
            }
            if (!Schema::hasColumn('client', 'delai_paiement')) {
                $table->integer('delai_paiement')->default(30)->after('plafond_credit');
            }
            if (!Schema::hasColumn('client', 'notes')) {
                $table->text('notes')->nullable()->after('delai_paiement');
            }
        });
    }

    public function down(): void
    {
        Schema::table('client', function (Blueprint $table) {
            foreach (['plafond_credit', 'delai_paiement', 'notes'] as $col) {
                if (Schema::hasColumn('client', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
