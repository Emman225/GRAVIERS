<?php

namespace Database\Factories;

use Help;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DetailCommande>
 */
class DetailCommandeFactory extends Factory
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
            'commande_id' => rand(1, 10),
            'qte' => rand(1, 10),
            'prix' => rand(200000, 5000000),
            'etat_livraison' => [Help::$LIVRAISON_EN_ATTENTE, Help::$LIVRAISON_EN_TRAITEMENT, Help::$LIVRAISON_LIVREE][rand(0, 2)],
        ];
    }
}
