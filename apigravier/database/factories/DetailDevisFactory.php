<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DetailDevis>
 */
class DetailDevisFactory extends Factory
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
            'devis_id' => rand(1, 10),
            'qte' => rand(1, 10),
            'prix' => rand(200000, 5000000),
        ];
    }
}
