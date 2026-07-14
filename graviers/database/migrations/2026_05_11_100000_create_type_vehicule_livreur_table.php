<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('type_vehicule_livreur')) {
            Schema::create('type_vehicule_livreur', function (Blueprint $table) {
                $table->id();
                $table->string('libelle', 60)->unique();
                $table->decimal('capacite_tonnes', 8, 2)->nullable();
                $table->string('description', 255)->nullable();
                $table->boolean('statut')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // FK non-bloquante côté livreur (nullable)
        if (Schema::hasTable('livreur') && !Schema::hasColumn('livreur', 'type_vehicule_id')) {
            Schema::table('livreur', function (Blueprint $table) {
                $table->unsignedBigInteger('type_vehicule_id')->nullable()->after('id');
                $table->index('type_vehicule_id');
            });
        }

        // Seed depuis la feuille « Paramètres » du fichier 04_Suivi_Dettes_Livreurs.xlsx
        $seeds = [
            ['libelle' => 'Tricycle',   'capacite_tonnes' => 1.0,  'description' => 'Petites quantités, courtes distances'],
            ['libelle' => 'Camion 5T',  'capacite_tonnes' => 5.0,  'description' => 'Quantités moyennes'],
            ['libelle' => 'Camion 10T', 'capacite_tonnes' => 10.0, 'description' => 'Grosses quantités'],
            ['libelle' => 'Camion 25T', 'capacite_tonnes' => 25.0, 'description' => 'Très grosses quantités'],
            ['libelle' => 'Benne',      'capacite_tonnes' => 15.0, 'description' => 'Gravier / sable en vrac'],
        ];

        foreach ($seeds as $row) {
            $exists = DB::table('type_vehicule_livreur')->where('libelle', $row['libelle'])->exists();
            if (!$exists) {
                DB::table('type_vehicule_livreur')->insert(array_merge($row, [
                    'statut'     => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('livreur', 'type_vehicule_id')) {
            Schema::table('livreur', function (Blueprint $table) {
                $table->dropIndex(['type_vehicule_id']);
                $table->dropColumn('type_vehicule_id');
            });
        }
        Schema::dropIfExists('type_vehicule_livreur');
    }
};
