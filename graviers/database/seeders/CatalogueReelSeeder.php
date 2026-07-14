<?php

namespace Database\Seeders;

use Help;
use Illuminate\Database\Seeder;
use App\Models\Produit;
use App\Models\Categorie;
use App\Models\UniteProduit;
use App\Models\ImageProduit;

/**
 * Catalogue réaliste DALAKOUN SARL (matériaux de construction).
 *
 * Remplace les données Faker (noms type « Jimmy Jones », prix aléatoires,
 * image gravier identique partout) par un catalogue cohérent :
 *   - unités métier (Tonne, m³, Sac, Barre, Unité)
 *   - 5 catégories réelles avec une image propre par catégorie
 *   - ~19 produits crédibles (référence, prix de vente, prix barré, note)
 *   - une image distincte par catégorie (téléchargée dans
 *     storage/app/public/productsImage/catalogue/)
 *
 * Idempotent : updateOrCreate par référence — relançable sans doublon.
 *
 *   php artisan db:seed --class=CatalogueReelSeeder
 *
 * Les anciens produits Faker de type VENTE sont désactivés (statut INACTIF)
 * et non supprimés : ils disparaissent de la boutique et de la recherche
 * (qui filtrent statut=ACTIF) tout en préservant l'historique des commandes.
 * Les produits de type LOCATION ne sont jamais touchés.
 */
class CatalogueReelSeeder extends Seeder
{
    /** Désactiver les anciens produits VENTE Faker. Mettre à false pour ne rien purger. */
    private const PURGE_ANCIENS = true;

    /** Dossier (relatif au disque public) où sont rangées les images de catalogue. */
    private const IMG = 'productsImage/catalogue/';

    public function run(): void
    {
        $actif = Help::$STATUT_ACTIF;

        // 1) UNITÉS ---------------------------------------------------------
        $unites = [
            ['abreviation' => 'T',   'libelle' => 'Tonne'],
            ['abreviation' => 'M3',  'libelle' => 'Mètre cube'],
            ['abreviation' => 'SAC', 'libelle' => 'Sac'],
            ['abreviation' => 'BAR', 'libelle' => 'Barre'],
            ['abreviation' => 'U',   'libelle' => 'Unité'],
        ];
        $uniteId = [];
        foreach ($unites as $u) {
            $m = UniteProduit::updateOrCreate(
                ['abreviation' => $u['abreviation']],
                ['libelle' => $u['libelle']]
            );
            $uniteId[$u['abreviation']] = $m->id;
        }

        // 2) CATÉGORIES (image nettoyée) -----------------------------------
        $catsDef = [
            'Gravier'      => self::IMG . 'gravier.jpg',
            'Sable'        => self::IMG . 'sable.jpg',
            'Ciment'       => self::IMG . 'ciment.jpg',
            'Barre de fer' => self::IMG . 'fer.jpg',
            'Brique'       => self::IMG . 'brique.jpg',
        ];
        $catId = [];
        foreach ($catsDef as $nom => $img) {
            $cat = Categorie::withTrashed()->where('nom', $nom)->first();
            if (!$cat) {
                $cat = new Categorie();
                $cat->nom = $nom;
                $cat->parent_id = 0;
            }
            if (method_exists($cat, 'trashed') && $cat->trashed()) {
                $cat->restore();
            }
            $cat->image = $img;
            // La section « Achat par catégorie » de la home affiche $categorie->icon,
            // pas ->image : on aligne les deux sur l'image propre de la catégorie.
            $cat->icon = $img;
            $cat->statut = $actif;
            $cat->save();
            $catId[$nom] = $cat->id;
        }

        // 3) PRODUITS -------------------------------------------------------
        // [ref, nom, abreviation, unite, prix_vente, prix_barré(0=aucun), note%, categorie]
        $produits = [
            // GRAVIER
            ['GRA-0-5',   'Gravier concassé 0/5',        'GRA0-5',   'M3',  12000, 14000, 90, 'Gravier'],
            ['GRA-5-15',  'Gravier concassé 5/15',       'GRA5-15',  'M3',  13000, 0,     80, 'Gravier'],
            ['GRA-15-25', 'Gravier concassé 15/25',      'GRA15-25', 'M3',  13500, 0,    100, 'Gravier'],
            ['GRA-25-40', 'Gravier 25/40 tout-venant',   'GRA25-40', 'M3',  11000, 0,     70, 'Gravier'],
            // SABLE
            ['SAB-FIN',   'Sable fin de lagune',         'SABFIN',   'M3',   8000, 0,     80, 'Sable'],
            ['SAB-GROS',  'Sable gros pour béton',       'SABGROS',  'M3',   9000, 10000, 90, 'Sable'],
            ['SAB-MER',   'Sable de mer lavé',           'SABMER',   'M3',   7500, 0,     70, 'Sable'],
            // CIMENT
            ['CIM-CPA45', 'Ciment CPA 45 - sac 50 kg',   'CPA45',    'SAC',  5500, 6000, 100, 'Ciment'],
            ['CIM-CPJ35', 'Ciment CPJ 35 - sac 50 kg',   'CPJ35',    'SAC',  4800, 0,     90, 'Ciment'],
            ['CIM-COLLE', 'Ciment-colle carrelage 25 kg','CIMCOLLE', 'SAC',  6500, 0,     80, 'Ciment'],
            // BARRE DE FER
            ['FER-6',     'Barre de fer 6 mm (12 m)',    'FER6',     'BAR',  2000, 0,     80, 'Barre de fer'],
            ['FER-8',     'Barre de fer 8 mm (12 m)',    'FER8',     'BAR',  3200, 0,     90, 'Barre de fer'],
            ['FER-10',    'Barre de fer 10 mm (12 m)',   'FER10',    'BAR',  4800, 0,     90, 'Barre de fer'],
            ['FER-12',    'Barre de fer 12 mm (12 m)',   'FER12',    'BAR',  6800, 7500, 100, 'Barre de fer'],
            ['FER-TS',    'Treillis soudé ST25',         'FERTS',    'U',   12000, 0,     70, 'Barre de fer'],
            // BRIQUE / PARPAING
            ['BRQ-P15',   'Parpaing creux 15',           'PARP15',   'U',     350, 0,     80, 'Brique'],
            ['BRQ-P20',   'Parpaing creux 20',           'PARP20',   'U',     450, 500,   90, 'Brique'],
            ['BRQ-PLEIN', 'Brique pleine rouge',         'BRQPLEIN', 'U',     200, 0,     70, 'Brique'],
            ['BRQ-HOUR',  'Hourdis 12',                  'HOURDIS12','U',     600, 0,     80, 'Brique'],
        ];

        $descriptions = [
            'Gravier'      => "Granulat concassé de qualité supérieure pour béton, fondations et voirie. Livré propre et calibré.",
            'Sable'        => "Sable sélectionné pour mortier, chape et béton. Faible teneur en argile, bonne granulométrie.",
            'Ciment'       => "Liant hydraulique conforme aux normes, idéal pour maçonnerie, dalle et enduit.",
            'Barre de fer' => "Acier à béton haute adhérence (HA), longueur standard 12 m, pour armatures et ferraillage.",
            'Brique'       => "Élément de maçonnerie robuste pour murs porteurs et cloisons. Bonne résistance à la compression.",
        ];

        $refsCatalogue = [];
        foreach ($produits as [$ref, $nom, $abrev, $unite, $prix, $barre, $note, $cat]) {
            $refsCatalogue[] = $ref;

            $p = Produit::withTrashed()->where('reference', $ref)->first() ?? new Produit();
            if ($p->trashed()) {
                $p->restore();
            }
            $p->reference        = $ref;
            $p->nom              = $nom;
            $p->abreviation      = $abrev;
            $p->unite            = $unite;
            $p->unite_produit_id = $uniteId[$unite];
            $p->description      = $descriptions[$cat];
            $p->prix_moyen       = $prix;     // prix de vente affiché
            $p->prix_reduction   = $barre;    // prix barré (0 = pas de promo)
            $p->meilleur_note    = $note;     // pourcentage 0-100 (barre + étoiles)
            $p->type_affaire     = Help::$VENTE;
            $p->statut           = $actif;
            $p->save();

            // Catégorie (pivot categorie_produit)
            $p->categories()->syncWithoutDetaching([$catId[$cat] => ['statut' => $actif]]);

            // Image par défaut (distincte par catégorie)
            ImageProduit::updateOrCreate(
                ['produit_id' => $p->id, 'defaut' => true],
                ['image' => $catsDef[$cat], 'statut' => $actif]
            );
        }

        $this->command->info(count($produits) . ' produits du catalogue créés / mis à jour.');

        // 4) PURGE DES ANCIENS PRODUITS FAKER (VENTE uniquement) -----------
        if (self::PURGE_ANCIENS) {
            $anciens = Produit::where('type_affaire', Help::$VENTE)
                ->whereNotIn('reference', $refsCatalogue)
                ->get();
            foreach ($anciens as $a) {
                $a->statut = Help::$STATUT_INACTIF;
                $a->save();
            }
            $this->command->warn($anciens->count() . ' ancien(s) produit(s) VENTE désactivé(s) (statut INACTIF, historique préservé).');
        }
    }
}
