<?php

namespace Database\Factories;

use Help;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Produit>
 */
class ProduitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => Help::ChaineAleatoire(10),
            'nom' => $this->faker->name(),
            'abreviation' => Help::ChaineAleatoire(10),
            'unite' => "T",
            'description' => $this->faker->paragraph(),
            'prix_moyen' => rand(10000, 1000000),
            'prix_reduction' => rand(10000, 1000000),
            'meilleur_note' => 5,
            'unite_produit_id' => 1,
        ];
    }
}
