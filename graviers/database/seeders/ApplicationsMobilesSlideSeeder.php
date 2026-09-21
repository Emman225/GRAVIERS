<?php

namespace Database\Seeders;

use App\Models\Slide;
use Illuminate\Database\Seeder;

/**
 * UNE DIAPOSITIVE DU CARROUSEL D'ACCUEIL POUR LES TROIS APPLICATIONS MOBILES (lot 110, 17/09/2026).
 *
 * Client, Livreur et Apporteur d'affaires : la diapositive renvoie à la section « applications »
 * de l'accueil, où se trouvent les boutons de téléchargement. Son visuel est livré avec le site
 * (frontend/assets/imgs/slider/slide-applications-mobiles.jpg).
 *
 * À lancer :  php artisan db:seed --class=ApplicationsMobilesSlideSeeder
 *
 * Relançable sans risque : la diapositive est reconnue à son titre et mise à jour, jamais
 * dupliquée. Elle se modifie ensuite depuis le back-office (Divers → Carrousel d'accueil).
 */
class ApplicationsMobilesSlideSeeder extends Seeder
{
    public const TITRE = 'Mon Gravier dans votre poche';

    public function run(): void
    {
        $donnees = [
            'image'            => 'frontend/assets/imgs/slider/slide-applications-mobiles.jpg',
            'badge_texte'      => 'NOUVEAU',
            'badge_type'       => 'NEW',
            'titre'            => self::TITRE,
            'titre_accent'     => 'trois applications mobiles',
            'description'      => "Client, Livreur et Apporteur d'affaires : commandez, livrez et gagnez des commissions depuis votre téléphone.",
            'caracteristiques' => "Application Client\nApplication Livreur\nApplication Apporteur d'affaires",
            'deco_valeur'      => '3',
            'deco_libelle'     => 'applications Android',
            'bouton1_texte'    => 'Télécharger les applications',
            'bouton1_lien'     => '#applications',
            'bouton2_texte'    => 'En savoir plus',
            'bouton2_lien'     => '/a-propos',
            'statut'           => 1,
        ];

        $slide = Slide::withTrashed()->where('titre', self::TITRE)->first();
        if ($slide) {
            $slide->restore();
            $slide->update($donnees);
            $this->command?->info('Diapositive « ' . self::TITRE . ' » mise à jour (id ' . $slide->id . ').');
            return;
        }

        // Juste après la première diapositive : visible dès la deuxième rotation.
        $premier = (int) (Slide::where('statut', 1)->min('num_ordre') ?? 0);
        $donnees['num_ordre'] = $premier + 1;
        Slide::where('num_ordre', '>=', $donnees['num_ordre'])->increment('num_ordre');
        $slide = Slide::create($donnees);
        $this->command?->info('Diapositive « ' . self::TITRE . ' » créée (id ' . $slide->id . ', ordre ' . $slide->num_ordre . ').');
    }
}
