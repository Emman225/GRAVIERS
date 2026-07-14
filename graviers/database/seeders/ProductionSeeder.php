<?php

namespace Database\Seeders;

use Help;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use App\Models\Pays;
use App\Models\TypeUser;
use App\Models\TypeLivraison;
use App\Models\TypeVehicule;
use App\Models\UniteProduit;
use App\Models\ModePaiement;
use App\Models\Region;
use App\Models\Ville;
use App\Models\Configuration;
use App\Models\User;
use App\Models\Fournisseur;

/**
 * Seeder de PRODUCTION — base propre, SANS aucune donnée de test (faker).
 *
 * À utiliser à la place de DatabaseSeeder (qui, lui, génère 100 % de fausses
 * données). Insère uniquement :
 *   - données de référence nettoyées (pays, types, 10 modes de paiement,
 *     régions/villes réelles dédupliquées) ;
 *   - une Configuration avec les valeurs opérationnelles (les champs légaux
 *     RESTENT À COMPLÉTER via Paramètres : raison sociale, NCC, etc.) ;
 *   - le compte admin « imlod » (mot de passe inchangé : 2024) ;
 *   - UN fournisseur principal (obligatoire : sans fournisseur actif, le
 *     catalogue n'a ni stock ni prix et ne s'affiche pas en boutique) ;
 *   - le vrai catalogue via les seeders dédiés (CatalogueReel/Location/Stock).
 *
 * Idempotent (firstOrCreate/updateOrCreate) : relançable sans doublon.
 *
 *   php artisan db:seed --class=ProductionSeeder --force
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        // La plupart des modèles de référence ont $guarded = ['*'] : on désactive
        // la protection mass-assignment le temps du seeding.
        Model::unguard();

        $actif = Help::$STATUT_ACTIF;

        // 1) PAYS -----------------------------------------------------------
        $ci = Pays::firstOrCreate(
            ['code' => 'CI'],
            ['nom' => "Côte d'ivoire", 'indicatif' => '+225', 'statut' => $actif]
        );

        // 2) TYPES UTILISATEUR — ids FIXES 1..6 (le code se base dessus :
        //    connexion admin = type_user_id ∈ 1,2,3 ; client = 4 ; fournisseur = 5 ; apporteur = 6).
        $typesUser = [
            1 => 'Super Administrateur', 2 => 'Administrateur', 3 => 'Gestionnaire',
            4 => 'Client', 5 => 'Fournisseur', 6 => "Apporteur d'affaires",
            7 => 'Agent SAV',   // le code : listeAgent => type_user_id = 7
            8 => 'Livreur',     // le code : LivreurController::login => type_user_id = 8
        ];
        foreach ($typesUser as $id => $nom) {
            TypeUser::updateOrCreate(['id' => $id], ['nom' => $nom]);
        }
        $idAdministrateur = 2;
        $idFournisseur    = 5;

        // 3) TYPES DE LIVRAISON --------------------------------------------
        foreach (['En vrac', 'En Big Bag', 'En sacs'] as $lib) {
            TypeLivraison::firstOrCreate(['libelle' => $lib]);
        }

        // 4) TYPES DE VEHICULE ---------------------------------------------
        foreach (['Camion', 'Remorque', 'Ben', 'Moto'] as $lib) {
            TypeVehicule::firstOrCreate(['libelle' => $lib]);
        }

        // 5) UNITES (le CatalogueReelSeeder complète M3/SAC/BAR/U) ----------
        UniteProduit::firstOrCreate(['abreviation' => 'T'], ['libelle' => 'Tonne']);

        // 6) MODES DE PAIEMENT (10 réels, ordre conservé) ------------------
        // ids FIXES (le code se base sur id=1 = En Agence pour le paiement en ligne).
        $modes = [
            1  => ['libelle' => 'En Agence (virement, Chèque, Espèce)', 'description' => 'En Agence'],
            2  => ['libelle' => 'Orange Money',        'description' => 'Orange Money'],
            3  => ['libelle' => 'Moov Money',          'description' => 'Moov Money'],
            4  => ['libelle' => 'Mtn Money',           'description' => 'Mtn Money'],
            5  => ['libelle' => 'Wave',                'description' => 'Wave'],
            6  => ['libelle' => 'Virement bancaire',   'description' => 'Virement bancaire'],
            7  => ['libelle' => 'Chèque',              'description' => 'Chèque'],
            8  => ['libelle' => 'Espèces',             'description' => 'Espèces'],
            9  => ['libelle' => 'Carte bancaire',      'description' => 'Carte bancaire'],
            10 => ['libelle' => 'Paiement en agence',  'description' => 'Retrait des commissions en agence'],
        ];
        // en_ligne = 1 pour les canaux numériques (mobile money, virement, carte) :
        // ils alimentent la liste renvoyée par l'API mobile (ModePaiement::liste qui
        // filtre en_ligne=1) — sinon les selects "mode de paiement" du mobile sont vides.
        $modesEnLigne = [2, 3, 4, 5, 6, 9]; // Orange, Moov, Mtn, Wave, Virement, Carte
        foreach ($modes as $id => $m) {
            ModePaiement::updateOrCreate(['id' => $id], [
                'libelle'     => $m['libelle'],
                'description' => $m['description'],
                'statut'      => $actif,
                'en_ligne'    => in_array($id, $modesEnLigne) ? 1 : 0,
            ]);
        }

        // 7) REGIONS réelles (test entries retirées) -----------------------
        // Coordonnées GPS (long, lat) du point de référence de la région : servent
        // à calculer le coût de livraison (distance client ↔ région × prixKm).
        // Les régions à 0/0 sont à compléter par l'admin (écran « Les régions »).
        $regions = [
            'Lagunes'        => [-3.96, 5.33],
            'La Mé'          => [-4.01, 5.34],
            'Gôh'            => [-4.00, 5.32],
            'Comoé'          => [-3.31, 6.08],
            'Guémon'         => [0, 0],
            'Tchologo'       => [0, 0],
            'Gontougo'       => [0, 0],
            'Iffou'          => [0, 0],
            'Sud-Comoé'      => [0, 0],
            'Kabadougou'     => [0, 0],
            "N'zi"           => [0, 0],
            'Worodougou'     => [0, 0],
            'Nawa'           => [0, 0],
            'Bélier'         => [0, 0],
            'Haut-Sassandra' => [0, 0],
        ];
        $regionId = [];
        foreach ($regions as $nom => $coord) {
            $r = Region::firstOrCreate(
                ['nom' => $nom],
                ['description' => '', 'long' => $coord[0], 'lat' => $coord[1], 'user_id' => 1]
            );
            $regionId[$nom] = $r->id;
        }

        // 8) VILLES réelles (dédupliquées, soft-deleted retirées) ----------
        $villes = [
            'Abidjan' => 'Lagunes', 'Bingerville' => 'Lagunes', 'Anyama' => 'Lagunes', 'Dabou' => 'Lagunes',
            'Agboville' => 'La Mé', 'Adzopé' => 'La Mé',
            'Grand-Bassam' => 'Comoé',
            'Divo' => 'Gôh', 'Ouragahio' => 'Gôh',
            'Duekoué' => 'Guémon', 'Bangolo' => 'Guémon',
            'Ferkessédougou' => 'Tchologo', 'Bondoukou' => 'Gontougo', 'Daoukro' => 'Iffou',
            'Aboisso' => 'Sud-Comoé', 'Odienné' => 'Kabadougou', 'Dimbokro' => "N'zi",
            'Séguéla' => 'Worodougou', 'Méagui' => 'Nawa',
        ];
        foreach ($villes as $nomVille => $nomRegion) {
            Ville::firstOrCreate(
                ['nom' => $nomVille, 'pays_id' => $ci->id],
                ['region_id' => $regionId[$nomRegion] ?? null, 'statut' => $actif]
            );
        }
        $villeAbidjan = Ville::where('nom', 'Abidjan')->value('id');

        // 9) CONFIGURATION (valeurs opérationnelles ; LÉGAL à compléter) ----
        Configuration::firstOrCreate(['id' => 1], [
            'tva' => 18,
            'montant_point' => 10,
            'devise' => 'PTS',
            'prixKm' => 5,
            'cout_livraison_min' => 5,
            'tonne_moyenne' => 40,
            'cout_liv_fixe' => 0,
            'delai_relance_standard' => 7,
            'seuil_alerte_retard' => 15,
            'delai_max_paiement_agence' => 3,
            'delai_annulation_auto' => 7,
            'frequence_paiement_livreur' => 'Hebdomadaire',
            'jour_paiement_livreur' => 'Vendredi',
            'taux_commission_standard' => 3.00,
            'delai_paiement_commission' => 15,
            // ⚠️ À COMPLÉTER via Paramètres (obligatoires pour la facturation/FNE) :
            'raison_sociale' => '', 'ncc' => '', 'regime_imposition' => '',
            'centre_impots' => '', 'rccm' => '', 'cnps' => '', 'capital_social' => '',
            'adresse_siege' => '', 'telephone' => '', 'email_entreprise' => '',
            'nom_etablissement' => 'GRAVIERS', 'nom_pdv' => 'PDV-01',
            'email_tresorier' => '', 'email_directeur_marketing' => '',
        ]);

        // 10) ADMIN « imlod » (mot de passe 2024 conservé) -----------------
        // Hash bcrypt existant de "2024" repris tel quel pour ne pas changer le mot de passe.
        $hash2024 = '$2y$12$abK7eswTq8vgSuy9qLK1CODhxw59ZCdqdZr3ZHN7ppgb4/dtPPdyW';
        $admin = User::updateOrCreate(
            ['login' => 'imlod'],
            [
                'nom_prenoms'  => 'DEV GRAVIER',
                'email'        => 'devfneconnect@gmail.com',
                'contact'      => '0709910850',
                'password'     => $hash2024,
                'adresse'      => 'Abidjan',
                'pays_id'      => $ci->id,
                'ville_id'     => $villeAbidjan,
                'type_user_id' => $idAdministrateur,
                'statut'       => $actif,
            ]
        );

        // Configuration : gestionnaire1 = admin
        Configuration::where('id', 1)->update(['gestionnaire1_id' => $admin->id]);

        // 11) FOURNISSEUR PRINCIPAL (obligatoire pour le stock catalogue) ---
        $userFrs = User::updateOrCreate(
            ['login' => 'fournisseur1'],
            [
                'nom_prenoms'  => 'Fournisseur Principal',
                'email'        => 'fournisseur1@gravierci.com',
                'contact'      => '0700000000',
                'password'     => Help::HashPassword('2024'),
                'adresse'      => 'Abidjan',
                'pays_id'      => $ci->id,
                'ville_id'     => $villeAbidjan,
                'type_user_id' => $idFournisseur,
                'statut'       => $actif,
            ]
        );
        Fournisseur::updateOrCreate(
            ['user_id' => $userFrs->id],
            [
                'nom_prenoms' => 'Fournisseur Principal',
                'email'       => 'fournisseur1@gravierci.com',
                'contact1'    => '0700000000',
                'adresse_geo' => 'Abidjan',
                'statut'      => $actif,
                'solde'       => 0,
            ]
        );

        // 12) CATALOGUE RÉEL + STOCK FOURNISSEUR ---------------------------
        $this->call([
            CatalogueReelSeeder::class,
            CatalogueLocationSeeder::class,
            StockFournisseurSeeder::class,
        ]);

        Model::reguard();

        $this->command->info('ProductionSeeder terminé : référentiel + admin imlod + 1 fournisseur + catalogue réel.');
    }
}
