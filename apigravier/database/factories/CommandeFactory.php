<?php

namespace Database\Factories;

use Help;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Commande>
 */
class CommandeFactory extends Factory
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
            'devis_id' => rand(1, 50),
            'client_id' => rand(1, 10),
            'adresse_livraison_id' => rand(1, 15),
            'mode_paiement_id' => rand(1, 5),
            'date_commande' => $this->faker->dateTime(),
            'montant_total' => rand(10000, 1000000),
            'etat_commande' => [Help::$COMMANDE_EN_ATTENTE, Help::$COMMANDE_EN_TRAITEMENT, Help::$COMMANDE_TERMINE][rand(0, 2)],
            'type_livraison_id' => rand(1, 3),
            'date_fin_livraison' => $this->faker->dateTime(),
        ];
    }
}
