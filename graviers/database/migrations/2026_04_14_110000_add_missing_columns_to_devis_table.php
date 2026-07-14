<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devis', function (Blueprint $table) {
            if (!Schema::hasColumn('devis', 'date_livraison')) {
                $table->date('date_livraison')->nullable();
            }
            if (!Schema::hasColumn('devis', 'adresse_livraison_id')) {
                $table->unsignedBigInteger('adresse_livraison_id')->nullable();
            }
            if (!Schema::hasColumn('devis', 'type_livraison_id')) {
                $table->unsignedBigInteger('type_livraison_id')->nullable();
            }
            if (!Schema::hasColumn('devis', 'mode_paiement_id')) {
                $table->unsignedBigInteger('mode_paiement_id')->nullable();
            }
            if (!Schema::hasColumn('devis', 'tva')) {
                $table->double('tva')->nullable()->default(0);
            }
            if (!Schema::hasColumn('devis', 'cout_reduction')) {
                $table->double('cout_reduction')->nullable()->default(0);
            }
            if (!Schema::hasColumn('devis', 'cout_livraison')) {
                $table->double('cout_livraison')->nullable()->default(0);
            }
            if (!Schema::hasColumn('devis', 'montant_ht')) {
                $table->double('montant_ht')->nullable()->default(0);
            }
            if (!Schema::hasColumn('devis', 'service')) {
                $table->smallInteger('service')->nullable()->default(1);
            }
        });
    }

    public function down(): void
    {
        Schema::table('devis', function (Blueprint $table) {
            $table->dropColumn([
                'date_livraison', 'adresse_livraison_id', 'type_livraison_id',
                'mode_paiement_id', 'tva', 'cout_reduction', 'cout_livraison',
                'montant_ht', 'service'
            ]);
        });
    }
};
