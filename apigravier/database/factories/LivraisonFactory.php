<?php

namespace Database\Factories;

use Help;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Livraison>
 */
class LivraisonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'numero' => strtoupper(Help::ChaineAleatoire(15)),
            'livreur_id' => rand(1, 10),
            'client_id' => rand(1, 10),
            'detail_commande_id' => rand(1, 10),
            'adresse_livraison_id' => rand(1, 10),
            'type_livraison_id' => rand(1, 10),
            'date_livraison' => $this->faker->date(),
            'qte' => rand(1, 20),
            'etat_livraison' => [Help::$LIVRAISON_EN_ATTENTE, Help::$LIVRAISON_EN_TRAITEMENT, Help::$LIVRAISON_LIVREE][rand(0, 2)],
            'date_livraison' => $this->faker->dateTime(),
        ];
    }
}
