<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\NoteProduit>
 */
class NoteProduitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'produit_id' => rand(1, 10),
            'client_id' => rand(1, 10),
            'avis' => $this->faker->sentence(),
            'note' => rand(1, 5),
        ];
    }
}
