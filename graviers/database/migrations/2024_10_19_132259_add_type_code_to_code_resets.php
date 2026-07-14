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
        Schema::table('code_resets', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->default(0);
            $table->unsignedSmallInteger('type_code')->nullable()->default(0);
            $table->dateTime('expiration_date')->nullable()->default(date('Y-m-d'));
            $table->boolean('utilise')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('code_resets', function (Blueprint $table) {
            //
        });
    }
};
