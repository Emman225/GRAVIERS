<?php

namespace Database\Factories;

use Help;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LignePaiement>
 */
class LignePaiementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'paiement_id' => rand(1, 10),
            'mode_paiement_id' => rand(1, 10),
            'reference' => strtoupper(Help::ChaineAleatoire(10)),
            'moyen_paiement' => ["OM", "MOMO", "WAVE", "VIREMENT", "ESPECE", "CHEQUE"][rand(0, 5)],
            'date_paiement' => $this->faker->dateTime(),
            'montant' => rand(1000000, 100000000),
        ];
    }
}
