<?php

namespace Tests\Feature;

use App\Models\Pays;
use App\Models\Region;
use App\Models\User;
use App\Models\Ville;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * CRÉER UNE RÉGION ET UNE VILLE — DEUX ÉCRANS QUI NE FONCTIONNAIENT PAS.
 *
 * Signalés le 27/08/2026, et vérifiés dans le code puis en base :
 *
 *   · /les-villes renvoyait une ERREUR 500 à l'enregistrement. Le contrôleur
 *     écrivait `user_id`, colonne que la table `ville` ne possède pas, et
 *     n'écrivait jamais `pays_id`, pourtant obligatoire et sans valeur par
 *     défaut. Reproduit à l'identique : « Unknown column 'user_id' in 'field
 *     list' ». Aucune ville ne pouvait être créée.
 *
 *   · /les-regions refusait tout enregistrement avec « La longitude est
 *     requise ». Le formulaire ne portait aucun champ pour les coordonnées :
 *     le script les écrivait dans « #long » et « #lat », protégé par un test
 *     d'existence qui échouait toujours. Aucune région ne pouvait être créée.
 *
 *   · la MODIFICATION d'une région ne validait rien et recopiait ce qu'elle
 *     recevait. Le formulaire n'envoyant pas les coordonnées, elle écrivait
 *     NULL par-dessus des valeurs justes : onze des dix-neuf régions de la base
 *     s'étaient retrouvées sans coordonnées ni description.
 *
 * Ces essais tiennent les trois. Ils passent par les VRAIES routes, avec un
 * compte réellement connecté : un essai qui appellerait le contrôleur en direct
 * ne dirait rien du formulaire ni des droits d'accès.
 */
class RegionsEtVillesTest extends TestCase
{
    use DatabaseTransactions;

    /** Les modèles du projet ne déclarent pas de `$fillable` : on affecte à la main. */
    private function creerRegion(string $nom, ?float $long, ?float $lat, ?string $description): Region
    {
        $region = new Region;
        $region->nom = $nom;
        $region->description = $description;
        $region->long = $long;
        $region->lat = $lat;
        // `regions.user_id` est obligatoire et sans valeur par defaut.
        $region->user_id = User::query()->value('id');
        $region->save();

        return $region;
    }

    private function administrateur(): User
    {
        // Type 2 = Administrateur. Le rattachement à une agence n'entre pas en
        // jeu ici : ces écrans ne touchent pas à la caisse.
        return User::factory()->create(['type_user_id' => 2, 'statut' => 1]);
    }

    /** Le défaut d'origine : l'insertion partait avec une colonne inexistante. */
    public function test_une_ville_peut_etre_creee(): void
    {
        if (! Pays::exists()) {
            $pays = new Pays;
            $pays->nom = "Côte d'ivoire";
            $pays->save();
        }
        $region = $this->creerRegion('Region essai ' . uniqid(), -4.01, 5.32, 'x');

        $reponse = $this->actingAs($this->administrateur())
            ->post('/les-villes', ['nom' => 'Dabou', 'region_id' => $region->id]);

        $reponse->assertRedirect(route('dest.lesVilles'));

        // On ne controle PAS la cle « success » en session : Flasher la capte
        // pour la rejouer en bulle, et elle n'y figure plus. C'est l'etat de la
        // base qui fait foi — et c'est de toute facon ce qui compte.
        $ville = Ville::where('nom', 'Dabou')->orderByDesc('id')->first();
        $this->assertNotNull($ville, "La ville n'a pas été enregistrée : c'est l'erreur 500 d'origine.");
        $this->assertNotNull($ville->pays_id, "`pays_id` est obligatoire : sans lui, MySQL refuse l'insertion.");
    }

    /** Sans pays enregistré, on explique — on ne renvoie pas une erreur 500. */
    public function test_sans_pays_enregistre_le_message_est_clair(): void
    {
        // Pour eprouver le garde-fou il faut une base SANS pays. La base de
        // travail en porte un, et beaucoup de tables s'y rattachent : on leve
        // donc les controles de cles le temps du vidage. Tout est annule par la
        // transaction de l'essai, et les controles sont retablis aussitot.
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        try {
            DB::table('ville')->delete();
            DB::table('pays')->delete();
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        }

        $region = $this->creerRegion('Region essai ' . uniqid(), -4.01, 5.32, 'x');
        $nomVille = 'Ville essai ' . uniqid();

        $reponse = $this->actingAs($this->administrateur())
            ->post('/les-villes', ['nom' => $nomVille, 'region_id' => $region->id]);

        $reponse->assertSessionHas('erreurVille');
        $this->assertDatabaseMissing('ville', ['nom' => $nomVille]);
    }

    /** Le formulaire doit PORTER les champs que le contrôleur exige. */
    public function test_le_formulaire_des_regions_transmet_les_coordonnees(): void
    {
        // Le formulaire a sa propre page depuis le 03/09/2026.
        $vue = file_get_contents(resource_path('views/gestionnaire/formRegion.blade.php'));

        // On retire les commentaires : la leçon de l'essai sur les cartes du
        // back-office, qui se contentait d'un mot trouvé dans une explication.
        $vue = preg_replace('/\{\{--.*?--\}\}/s', '', $vue);

        foreach (['long', 'lat'] as $champ) {
            $this->assertMatchesRegularExpression(
                '/<input[^>]*name="' . $champ . '"/',
                $vue,
                "Le formulaire des régions ne transmet pas « $champ » : le contrôleur "
                . "l'exige, l'enregistrement échouera donc systématiquement."
            );
        }
    }

    /** Une région complète s'enregistre. */
    public function test_une_region_peut_etre_creee(): void
    {
        // Un nom qui n'existe pas : `regions.nom` est unique, et les dix-neuf
        // régions de Côte d'Ivoire sont déjà en base. Ma première version prenait
        // « Nawa » — déjà pris — et l'essai échouait sur une erreur 500, ce qui a
        // justement mis au jour l'absence de contrôle d'unicité.
        $nom = 'Region essai ' . uniqid();

        $reponse = $this->actingAs($this->administrateur())->post('/les-regions', [
            'nom' => $nom,
            'adresse_geo' => 'Soubré, Côte d’Ivoire',
            'long' => -6.5936,
            'lat' => 5.7856,
        ]);

        $reponse->assertRedirect(route('show.lesRegions'));
        $this->assertDatabaseHas('regions', ['nom' => $nom]);
    }

    /**
     * UN NOM DÉJÀ PRIS DOIT DONNER UN MESSAGE, PAS UNE ERREUR 500.
     *
     * `regions.nom` est unique en base. Sans contrôle applicatif, MySQL refusait
     * l'insertion et l'exception remontait jusqu'à l'écran — c'est ce qui serait
     * arrivé au premier essai de ressaisie d'une région existante.
     */
    public function test_un_nom_de_region_deja_pris_est_refuse_proprement(): void
    {
        $existante = Region::orderBy('id')->firstOrFail();

        $reponse = $this->actingAs($this->administrateur())->post('/les-regions', [
            'nom' => $existante->nom,
            'adresse_geo' => 'Quelque part',
            'long' => -4.0,
            'lat' => 5.3,
        ]);

        $reponse->assertSessionHasErrors('nom');
        $this->assertSame(
            1,
            Region::where('nom', $existante->nom)->count(),
            'Le doublon a été créé malgré la contrainte.'
        );
    }

    /**
     * MODIFIER UNE RÉGION NE DOIT PLUS EFFACER SES COORDONNÉES.
     *
     * C'est le point le plus coûteux des trois : il détruisait des données
     * justes, en silence, à chaque enregistrement.
     */
    public function test_modifier_une_region_sans_coordonnees_est_refuse(): void
    {
        $region = $this->creerRegion('Region essai ' . uniqid(), -6.59, 5.78, 'Soubré');

        $reponse = $this->actingAs($this->administrateur())
            ->from(route('show.modifierRegion', $region))
            ->post(route('show.modifierRegionValid', $region), ['nom' => $region->nom]);

        $reponse->assertSessionHasErrors(['long', 'lat', 'adresse_geo']);

        $region->refresh();
        $this->assertEquals(-6.59, (float) $region->long, 'La longitude a été effacée.');
        $this->assertEquals(5.78, (float) $region->lat, 'La latitude a été effacée.');
        $this->assertSame('Soubré', $region->description, 'La description a été effacée.');
    }

    /**
     * SUPPRIMER UNE RÉGION — ERREUR 500 constatée le 27/08/2026.
     *
     * La méthode écrivait `deleted_at`, colonne que la table `regions` ne
     * possède pas. Aucune région ne pouvait être supprimée, et l'écran restait
     * blanc. Reproduit : « Unknown column 'deleted_at' in 'field list' ».
     */
    public function test_une_region_sans_ville_se_supprime(): void
    {
        $region = $this->creerRegion('Region essai ' . uniqid(), -4.0, 5.3, 'x');

        $this->actingAs($this->administrateur())
            ->get(route('show.supprimerRegion', $region))
            ->assertRedirect(route('show.lesRegions'));

        $this->assertNull(
            Region::find($region->id),
            "La région n'a pas été supprimée : c'est l'erreur 500 d'origine."
        );
    }

    /**
     * UNE RÉGION QUI PORTE DES VILLES NE SE SUPPRIME PAS EN SILENCE.
     *
     * Aucune clé étrangère ne relie `ville.region_id` à `regions` : supprimer
     * la région laisserait ses villes pointer dans le vide, exactement comme
     * les 59 liens de catégorie orphelins trouvés ailleurs dans cette base.
     */
    public function test_une_region_avec_des_villes_est_refusee(): void
    {
        $region = $this->creerRegion('Region essai ' . uniqid(), -4.0, 5.3, 'x');

        $ville = new Ville;
        $ville->nom = 'Ville essai ' . uniqid();
        $ville->region_id = $region->id;
        $ville->pays_id = Pays::orderBy('id')->value('id');
        $ville->save();

        $this->actingAs($this->administrateur())
            ->get(route('show.supprimerRegion', $region))
            ->assertSessionHas('erreurRegion');

        $this->assertNotNull(
            Region::find($region->id),
            'La région a été supprimée alors que des villes y sont rattachées.'
        );
    }

    /** Le refus doit se VOIR : une clé que Flasher ne capte pas, et affichée. */
    public function test_les_messages_de_refus_sont_affiches(): void
    {
        // CHAQUE MESSAGE SUR LA PAGE OU IL ARRIVE.
        //
        // « erreurRegion » vient du refus de suppression, qui renvoie sur la
        // LISTE. « erreurVille » vient de l'enregistrement, qui revient sur le
        // FORMULAIRE — et depuis que celui-ci a sa page, ce n'est plus la meme.
        foreach ([
            'gestionnaire/lesRegions.blade.php' => 'erreurRegion',
            'gestionnaire/formVille.blade.php' => 'erreurVille',
        ] as $vue => $cle) {
            $contenu = file_get_contents(resource_path('views/' . $vue));
            $contenu = preg_replace('/\{\{--.*?--\}\}/s', '', $contenu);

            // On exige la CONDITION, pas le simple mot : chercher
            // « session('erreurRegion') » n'importe ou le trouvait dans le corps
            // du bloc, et l'essai passait meme apres avoir neutralise le @if.
            $this->assertStringContainsString(
                "@if (session('" . $cle . "'))",
                $contenu,
                "$vue n'affiche pas « $cle » : le refus resterait invisible, et le "
                . "gestionnaire croirait son enregistrement passé."
            );
        }
    }
}
