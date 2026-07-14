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
        Schema::create('blog_commentaires', function (Blueprint $table) {
            $table->id();
            $table->integer('note')->nullable();
            $table->text('commentaire')->nullable();
            $table->foreignId('client_id')->constrained('client');
            $table->foreignId('blog_id')->constrained('blogs');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blog_commentaires');
    }
};
