<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ticket_sav.user_id = l'AGENT SAV assigné. À la création par le client, aucun agent
 * n'est encore assigné → user_id doit pouvoir être NULL. La colonne était NOT NULL,
 * ce qui provoquait une erreur 500 à la création d'un ticket. On la rend nullable.
 * (doctrine/dbal absent -> ALTER en SQL brut ; type bigint unsigned conservé.)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('ticket_sav', 'user_id')) {
            DB::statement('ALTER TABLE ticket_sav MODIFY user_id BIGINT UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ticket_sav', 'user_id')) {
            DB::statement('ALTER TABLE ticket_sav MODIFY user_id BIGINT UNSIGNED NOT NULL');
        }
    }
};
