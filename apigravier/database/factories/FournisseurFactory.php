<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Fournisseur>
 */
class FournisseurFactory extends Factory
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
            'nom_prenoms' => $this->faker->name(),
            'email' => $this->faker->email(),
            'contact1' => $this->faker->e164PhoneNumber(),
            'contact2' => $this->faker->e164PhoneNumber(),
            'adresse_geo' => $this->faker->address(),
            'adresse_postale' => $this->faker->postcode(),
            'longitude' => $this->faker->longitude(),
            'latitude' => $this->faker->latitude(),
        ];
    }
}
