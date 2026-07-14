<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Reduction>
 */
class ReductionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => rand(100000, 999999),
            'libelle' => $this->faker->sentence(),
            'debut' => $this->faker->date(),
            'fin' => $this->faker->date(),
            'est_utilise' => false,
            'taux_reduction' => rand(1, 10),
            'montant_reduction' => rand(1, 10),
            'devis_id' => rand(1, 50),
            'client_id' => rand(1, 10),
        ];
    }
}
