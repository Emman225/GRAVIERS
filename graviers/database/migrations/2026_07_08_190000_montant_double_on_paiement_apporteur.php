<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * paiement_apporteur.montant était BIGINT : les commissions calculées en %
     * ont des décimales (ex. 11,8 FCFA) et le paiement stocké était arrondi
     * (11,8 -> 12), créant un écart avec le reste à payer de la commission.
     */
    public function up(): void
    {
        if (Schema::hasColumn('paiement_apporteur', 'montant')) {
            DB::statement('ALTER TABLE paiement_apporteur MODIFY montant DOUBLE NOT NULL DEFAULT 0');
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE paiement_apporteur MODIFY montant BIGINT NOT NULL DEFAULT 0');
    }
};
