<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('relance_client_terme')) {
            Schema::create('relance_client_terme', function (Blueprint $table) {
                $table->id();
                $table->date('date_relance');
                $table->unsignedBigInteger('facture_id')->nullable();
                $table->unsignedBigInteger('client_id');
                $table->string('type_relance', 30)->nullable();
                $table->string('niveau', 30)->nullable();
                $table->text('reponse_client')->nullable();
                $table->text('action_suivante')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index('client_id');
                $table->index('facture_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('relance_client_terme');
    }
};
