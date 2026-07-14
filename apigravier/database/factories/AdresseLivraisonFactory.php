<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AdresseLivraison>
 */
class AdresseLivraisonFactory extends Factory
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
            'pays_id' => rand(1, 5),
            'ville_id' => rand(1, 10),
            'affichage' => substr($this->faker->address(), 0, 50),
            'complement_adresse' => $this->faker->address(),
            'defaut' => false,
            'longitude' => $this->faker->longitude(),
            'latitude' => $this->faker->latitude(),
        ];
    }
}
