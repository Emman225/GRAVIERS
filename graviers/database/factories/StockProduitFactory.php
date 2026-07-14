<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockProduit>
 */
class StockProduitFactory extends Factory
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
            'produit_id' => rand(1, 10),
            'qte' => rand(1, 10),
            'prix' => rand(1000000, 100000000),
        ];
    }
}
