<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ticket_sav', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique()->index();
            $table->foreignId('client_id')->constrained('client');
            $table->foreignId('user_id')->constrained('users'); //Agent affecté
            $table->foreignId('detail_commande_id')->constrained('detail_commande'); //Ligne commande qui represente un produit
            $table->string('objet');
            $table->mediumText('message');
            $table->boolean('est_traite')->default(false);
            $table->mediumText('solution_trouvee')->nullable();
            $table->smallInteger('statut')->default(Help::$STATUT_ACTIF);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_sav');
    }
};
