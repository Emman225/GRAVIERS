<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('paiement_fournisseur')) {
            Schema::create('paiement_fournisseur', function (Blueprint $table) {
                $table->id();
                $table->date('date_paiement');
                $table->unsignedBigInteger('enlevement_id');
                $table->unsignedBigInteger('fournisseur_id');
                $table->bigInteger('montant')->default(0);
                $table->unsignedBigInteger('mode_paiement_id')->nullable();
                $table->string('reference', 80)->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->smallInteger('statut')->default(1);
                $table->timestamps();
                $table->softDeletes();
                $table->index('enlevement_id');
                $table->index('fournisseur_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('paiement_fournisseur');
    }
};
