<?php

namespace Database\Factories;

use Help;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Apporteur>
 */
class ApporteurFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => rand(1, 50),
            'code' => strtoupper(Help::ChaineAleatoire(10)),
            'solde' => rand(0, 100000),
        ];
    }
}
