<?php

namespace Database\Factories;

use Help;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nom_prenoms' => $this->faker->name(),
            'email' => $this->faker->email(),
            'contact' => $this->faker->e164PhoneNumber(),
            'login' => strtoupper(Help::ChaineAleatoire(5)),
            'password' => Help::HashPassword("1234"),
            'photo' => "image.png",
            'adresse' => $this->faker->address(),
            'pays_id' => rand(1, 5),
            'ville_id' => rand(1, 10),
            'type_user_id' => rand(1, 6),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
