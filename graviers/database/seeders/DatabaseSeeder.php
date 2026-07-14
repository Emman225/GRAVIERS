<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        \App\Models\Pays::factory(5)->create();
        \App\Models\Ville::factory(10)->create();
        \App\Models\TypeUser::insert([
            ["nom" => "Super Administrateur"],
            ["nom" => "Administrateur"],
            ["nom" => "Gestionnaire"],
            ["nom" => "Client"],
            ["nom" => "Fournisseur"],
            ["nom" => "Apporteur d'affaires"],
        ]);
        \App\Models\TypeLivraison::insert([
            ["libelle" => "En vrac"],
            ["libelle" => "En Big Bag"],
            ["libelle" => "En sacs"],
        ]);
        \App\Models\TypeVehicule::insert([
            ["libelle" => "Camion"],
            ["libelle" => "Remorque"],
            ["libelle" => "Ben"],
            ["libelle" => "Moto"],
        ]);
        \App\Models\UniteProduit::insert([
            ["abreviation" => "T", "libelle" => "Tonne"],
        ]);
        \App\Models\User::factory(50)->create();
        \App\Models\Produit::factory(100)->create();
        \App\Models\Categorie::factory(5)->create();
        \App\Models\ModePaiement::factory(5)->create();
        \App\Models\CategorieProduit::factory(150)->create();
        \App\Models\Client::factory(10)->create();
        \App\Models\Apporteur::factory(10)->create();
        \App\Models\Livreur::factory(10)->create();
        \App\Models\Fournisseur::factory(10)->create();
        \App\Models\AdresseLivraison::factory(15)->create();
        \App\Models\Devis::factory(50)->create();
        \App\Models\Commande::factory(50)->create();
        \App\Models\Livraison::factory(50)->create();
        \App\Models\Reduction::factory(100)->create();
        \App\Models\Paiement::factory(300)->create();
        \App\Models\Enlevement::factory(50)->create();
        \App\Models\StockProduit::factory(200)->create();
        \App\Models\DetailDevis::factory(80)->create();
        \App\Models\ImageProduit::factory(125)->create();
        \App\Models\LignePaiement::factory(200)->create();
        \App\Models\DetailCommande::factory(200)->create();
        \App\Models\Banniere::factory(10)->create();
        \App\Models\NoteProduit::factory(100)->create();
    }
}
