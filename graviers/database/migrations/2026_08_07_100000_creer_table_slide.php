<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Diapositives du carrousel de la page d'accueil.
 *
 * Les six diapositives étaient écrites en dur dans client/index.blade.php :
 * changer un titre, un prix ou une photo demandait une intervention sur le code.
 * Elles deviennent des données, administrables depuis le back-office comme les
 * bannières.
 *
 * La migration REPREND les six diapositives existantes à l'identique — textes,
 * images, badges, boutons — pour que la page d'accueil soit rigoureusement la
 * même avant et après. Rien n'est perdu, rien n'est à ressaisir.
 *
 * Le champ `image` accepte deux formes :
 *   - un fichier livré avec le thème  : « frontend/assets/imgs/slider/x.jpg »
 *   - un fichier téléversé par l'admin : « imageSlide/xxxx.jpg », relatif au
 *     disque public.
 * Slide::urlImage() choisit la bonne racine selon le cas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('slide')) {
            return;
        }

        Schema::create('slide', function (Blueprint $table) {
            $table->id();

            $table->string('image');

            // Pastille en haut de la diapositive.
            $table->string('badge_texte', 100)->nullable();
            $table->enum('badge_type', ['NEUTRE', 'NEW', 'PROMO', 'HOT'])->default('NEUTRE');

            // Titre sur deux lignes : la seconde est mise en valeur.
            $table->string('titre', 150);
            $table->string('titre_accent', 150)->nullable();
            $table->text('description')->nullable();

            // Arguments à puces, un par ligne.
            $table->text('caracteristiques')->nullable();

            // Encart chiffré (ex. « 15+ » / « années d'expérience »).
            $table->string('deco_valeur', 40)->nullable();
            $table->string('deco_libelle', 80)->nullable();

            $table->string('bouton1_texte', 60)->nullable();
            $table->string('bouton1_lien', 255)->nullable();
            $table->string('bouton2_texte', 60)->nullable();
            $table->string('bouton2_lien', 255)->nullable();

            $table->unsignedSmallInteger('num_ordre')->default(0);
            // 1 = affichée sur le site, 0 = retirée. Même convention que banniere.
            $table->unsignedTinyInteger('statut')->default(1);

            $table->softDeletes();
            $table->timestamps();
        });

        $maintenant = now();
        $base = 'frontend/assets/imgs/slider/';

        $slides = [
            [
                'image' => $base . 'slide-camion-benne.jpg',
                'badge_texte' => "N°1 EN CÔTE D'IVOIRE", 'badge_type' => 'NEW',
                'titre' => 'Construisez vos rêves,', 'titre_accent' => 'on fournit le reste',
                'description' => 'Sable, gravier, ciment, fer, briques... toute la matière première de qualité pour réussir votre chantier au meilleur prix.',
                'caracteristiques' => "Qualité garantie\nPrix professionnels\nCatalogue complet",
                'deco_valeur' => '15+', 'deco_libelle' => "années d'expérience",
                'bouton1_texte' => 'Commander maintenant', 'bouton1_lien' => '#popular-categories',
                'bouton2_texte' => 'Demander un devis', 'bouton2_lien' => '/demande-de-livraison',
                'num_ordre' => 1,
            ],
            [
                'image' => $base . 'slide-camion-sable.jpg',
                'badge_texte' => 'LIVRAISON EXPRESS', 'badge_type' => 'PROMO',
                'titre' => 'Livraison express', 'titre_accent' => 'sur tous vos chantiers',
                'description' => "Recevez vos matériaux directement sur site en moins de 24h, partout en Côte d'Ivoire. Flotte de camions dédiée.",
                'caracteristiques' => "Livraison rapide\nSuivi en temps réel\nToute la CI",
                'deco_valeur' => '< 24h', 'deco_libelle' => 'livraison express',
                'bouton1_texte' => 'Demander une livraison', 'bouton1_lien' => '/demande-de-livraison',
                'bouton2_texte' => 'Voir les produits', 'bouton2_lien' => '#popular-categories',
                'num_ordre' => 2,
            ],
            [
                'image' => $base . 'slide-camion-gravier.jpg',
                'badge_texte' => 'OFFRE LIMITÉE', 'badge_type' => 'HOT',
                'titre' => 'Des prix qui défient', 'titre_accent' => 'toute concurrence',
                'description' => 'Profitez de tarifs professionnels sur toute notre gamme de matériaux. Plus vous commandez, plus vous économisez.',
                'caracteristiques' => "Tarifs dégressifs\nDevis gratuit\nAucun frais caché",
                'deco_valeur' => '-15%', 'deco_libelle' => 'sur commandes en gros',
                'bouton1_texte' => 'Voir nos offres', 'bouton1_lien' => '#popular-categories',
                'bouton2_texte' => 'Devis personnalisé', 'bouton2_lien' => '/demande-de-livraison',
                'num_ordre' => 3,
            ],
            [
                'image' => $base . 'slide-ciment.jpg',
                'badge_texte' => 'QUALITÉ CERTIFIÉE', 'badge_type' => 'NEUTRE',
                'titre' => 'Du ciment de qualité', 'titre_accent' => 'pour des fondations solides',
                'description' => "Large gamme de ciment certifié pour tous types de constructions : maison, bâtiment, ouvrages d'art.",
                'caracteristiques' => "Norme CEM I & II\nSacs 50 kg\nStock permanent",
                'deco_valeur' => '100%', 'deco_libelle' => 'certifié & contrôlé',
                'bouton1_texte' => 'Voir le catalogue', 'bouton1_lien' => '#popular-categories',
                'bouton2_texte' => 'Commander en gros', 'bouton2_lien' => '/demande-de-livraison',
                'num_ordre' => 4,
            ],
            [
                'image' => $base . 'slide-fer.jpg',
                'badge_texte' => 'BESTSELLER', 'badge_type' => 'HOT',
                'titre' => 'Barres de fer & armatures', 'titre_accent' => 'au meilleur tarif',
                'description' => 'Renforcez vos ouvrages avec nos fers à béton de qualité supérieure. Disponibles en tous diamètres et longueurs.',
                'caracteristiques' => "Acier haute résistance\nSur-mesure possible\nCoupe gratuite",
                'deco_valeur' => 'Ø 6→32', 'deco_libelle' => 'tous diamètres',
                'bouton1_texte' => 'Acheter maintenant', 'bouton1_lien' => '#popular-categories',
                'bouton2_texte' => 'Calculer le besoin', 'bouton2_lien' => '/demande-de-livraison',
                'num_ordre' => 5,
            ],
            [
                'image' => $base . 'slide-briques.jpg',
                'badge_texte' => 'REMISE GROS VOLUMES', 'badge_type' => 'PROMO',
                'titre' => 'Briques & parpaings', 'titre_accent' => 'pour murs et clôtures',
                'description' => 'Commandez en gros et bénéficiez de remises exceptionnelles. Briques pleines, creuses et parpaings standards.',
                'caracteristiques' => "Plusieurs formats\nRobustesse certifiée\nStock permanent",
                'deco_valeur' => 'Gros', 'deco_libelle' => 'volumes -20%',
                'bouton1_texte' => 'Passer commande', 'bouton1_lien' => '#popular-categories',
                'bouton2_texte' => 'Devis gratuit', 'bouton2_lien' => '/demande-de-livraison',
                'num_ordre' => 6,
            ],
        ];

        foreach ($slides as $slide) {
            DB::table('slide')->insert($slide + [
                'statut' => 1,
                'created_at' => $maintenant,
                'updated_at' => $maintenant,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('slide');
    }
};
