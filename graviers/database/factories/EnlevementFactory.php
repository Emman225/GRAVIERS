<?php

namespace Database\Factories;

use Help;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Enlevement>
 */
class EnlevementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fournisseur_id' => rand(1, 10),
            'livraison_id' => rand(1, 10),
            'qte' => rand(1, 10),
            'produit_id' => rand(1, 10),
        ];
    }
}
