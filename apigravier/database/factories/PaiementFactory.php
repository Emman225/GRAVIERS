<?php

namespace Database\Factories;

use Help;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Paiement>
 */
class PaiementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => rand(1, 10),
            'devis_id' => rand(1, 50),
            'code' => strtoupper(Help::ChaineAleatoire(15)),
            'libelle' => $this->faker->sentence(),
            'montant_total' => rand(10000, 1000000),
            'montant_restant' => rand(10000, 1000000),
        ];
    }
}
