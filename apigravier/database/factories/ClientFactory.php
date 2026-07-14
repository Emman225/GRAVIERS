<?php

namespace Database\Factories;

use Help;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Client>
 */
class ClientFactory extends Factory
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
            'nom' => $this->faker->lastName(),
            'prenom' => $this->faker->firstName(),
            'email' => $this->faker->email(),
            'contact1' => $this->faker->e164PhoneNumber(),
            'contact2' => $this->faker->e164PhoneNumber(),
            'code_parrain' => strtoupper(Help::ChaineAleatoire(6)),
            'rccm_clt' => Help::ChaineAleatoire(15),
            'ncc_clt' => Help::ChaineAleatoire(10),
            'type_client' => [Help::$PARTICULIER, Help::$ENTREPRISE][rand(0, 1)],
        ];
    }
}
