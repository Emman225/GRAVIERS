<?php

namespace Database\Factories;

use Help;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Banniere>
 */
class BanniereFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'titre' => $this->faker->title(),
            'sous_titre' => $this->faker->sentence(),
            'image' => "image.png",
            'num_ordre' => rand(1, 10),
            'type_banniere' => [Help::$BANNIERE_TOP, Help::$BANNIERE_FLASH, Help::$BANNIERE_BOTTOM][rand(0, 2)],
            'date_heure_decompte' => $this->faker->dateTime(),
        ];
    }
}
