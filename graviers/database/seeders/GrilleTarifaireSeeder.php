<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Grille tarifaire de transport, pour la recette.
 *
 * Le contenu vit dans la COMMANDE « grille:remplir » : sur l'hébergement
 * mutualisé, une classe ajoutée dans database/seeders n'est pas toujours
 * résolue par l'autoloader — d'où « Target class does not exist » — et
 * « composer dump-autoload » n'y est pas toujours disponible. Les commandes,
 * elles, fonctionnent.
 *
 * Ce seeder n'est donc qu'un raccourci pour les environnements de
 * développement, où db:seed est l'habitude.
 */
class GrilleTarifaireSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->call('grille:remplir', ['--apply' => true]);
    }
}
