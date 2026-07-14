<?php

namespace Database\Seeders;

use Help;
use Illuminate\Database\Seeder;
use App\Models\Produit;
use App\Models\UniteProduit;
use App\Models\ImageProduit;

/**
 * Catalogue réaliste du MATÉRIEL DE LOCATION (type_affaire = LOCATION).
 *
 * Remplace les anciens produits de location Faker (noms de personnes type
 * « Cleve Schaden », prix aléatoires, image gravier identique partout — qui
 * faisaient ressembler la page /location-materiel-construction à la home) par
 * de vrais engins de chantier, avec une image distincte et un tarif à la journée.
 *
 * Idempotent : updateOrCreate par référence — relançable sans doublon.
 *
 *   php artisan db:seed --class=CatalogueLocationSeeder
 *
 * Les anciens produits LOCATION Faker sont désactivés (statut INACTIF) et non
 * supprimés : ils disparaissent de la page location tout en préservant
 * l'historique des locations. Les produits VENTE ne sont jamais touchés.
 */
class CatalogueLocationSeeder extends Seeder
{
    /** Désactiver les anciens produits LOCATION Faker. */
    private const PURGE_ANCIENS = true;

    /** Dossier (relatif au disque public) des images de catalogue. */
    private const IMG = 'productsImage/catalogue/';

    public function run(): void
    {
        $actif = Help::$STATUT_ACTIF;

        // Unité "Jour" (tarif à la journée). Réutilise l'unité existante si présente.
        $uniteJour = UniteProduit::updateOrCreate(
            ['abreviation' => 'J'],
            ['libelle' => 'Jour']
        );

        // [ref, nom, abreviation, prix/jour, prix_barré(0=aucun), note%, image]
        $engins = [
            ['LOC-PELLE',    'Pelle hydraulique sur chenilles', 'PELLE',   150000, 0,      95, 'engin-pelle.png'],
            ['LOC-MINI',     'Mini-pelle de chantier',          'MINIPEL',  90000, 0,      90, 'engin-pelle.png'],
            ['LOC-BETON',    'Bétonnière 350 L',                'BETON',    20000, 0,      85, 'engin-betonniere.png'],
            ['LOC-POMPE',    'Pompe à béton',                   'POMPE',   130000, 150000, 90, 'engin-pompe.png'],
            ['LOC-PROJET',   'Machine à béton projeté (gunite)','PROJET',   95000, 0,      80, 'engin-projection.png'],
            ['LOC-DEMOL',    'Robot de démolition',             'DEMOL',   175000, 0,      88, 'engin-demolition.png'],
        ];

        $descriptions = [
            'LOC-PELLE'   => "Pelle hydraulique sur chenilles pour terrassement, excavation et chargement. Louée avec opérateur sur demande. Tarif à la journée.",
            'LOC-MINI'    => "Mini-pelle compacte idéale pour les travaux en espace réduit : tranchées, fondations, aménagements. Tarif à la journée.",
            'LOC-BETON'   => "Bétonnière 350 litres robuste pour le malaxage de béton et mortier sur chantier. Facile à déplacer. Tarif à la journée.",
            'LOC-POMPE'   => "Pompe à béton pour acheminer le béton en hauteur ou à distance. Idéale pour dalles et structures. Tarif à la journée.",
            'LOC-PROJET'  => "Machine à béton projeté (gunite) pour murs, talus et structures. Rendement élevé. Tarif à la journée.",
            'LOC-DEMOL'   => "Robot de démolition télécommandé pour démolition intérieure et travaux lourds en sécurité. Tarif à la journée.",
        ];

        $refsCatalogue = [];
        foreach ($engins as [$ref, $nom, $abrev, $prix, $barre, $note, $img]) {
            $refsCatalogue[] = $ref;

            $p = Produit::withTrashed()->where('reference', $ref)->first() ?? new Produit();
            if ($p->trashed()) {
                $p->restore();
            }
            $p->reference        = $ref;
            $p->nom              = $nom;
            $p->abreviation      = $abrev;
            $p->unite            = 'J';
            $p->unite_produit_id = $uniteJour->id;
            $p->description      = $descriptions[$ref];
            $p->prix_moyen       = $prix;    // tarif de location affiché (par jour)
            $p->prix_reduction   = $barre;   // tarif barré (0 = pas de promo)
            $p->meilleur_note    = $note;
            $p->type_affaire     = Help::$LOCATION;
            $p->statut           = $actif;
            $p->save();

            // Image par défaut (engin distinct). Pas de catégorie : la location
            // n'apparaît pas dans la section "Achat par catégorie" de la boutique.
            ImageProduit::updateOrCreate(
                ['produit_id' => $p->id, 'defaut' => true],
                ['image' => self::IMG . $img, 'statut' => $actif]
            );
        }

        $this->command->info(count($engins) . ' matériels de location créés / mis à jour.');

        // Purge des anciens produits LOCATION Faker.
        if (self::PURGE_ANCIENS) {
            $anciens = Produit::where('type_affaire', Help::$LOCATION)
                ->whereNotIn('reference', $refsCatalogue)
                ->get();
            foreach ($anciens as $a) {
                $a->statut = Help::$STATUT_INACTIF;
                $a->save();
            }
            $this->command->warn($anciens->count() . ' ancien(s) matériel(s) de location désactivé(s) (statut INACTIF, historique préservé).');
        }
    }
}
