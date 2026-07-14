<?php

namespace App\Http\Controllers;
use App\Models\Apporteur;
use App\Models\Categorie;
use App\Models\Client;
use App\Models\Facture;
use App\Models\Fournisseur;
use App\Models\LignePaiement;
use App\Models\Livraison;
use App\Models\Livreur;
use App\Models\ModePaiement;
use App\Models\Paiement;
use App\Models\Produit;
use App\Models\TypeUser;
use App\Models\User;
use Help;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TestController extends Controller
{
    //

    public function recapDevisLocation(Request $request)
    {

        dd('ok');

    }

    public function select()
    {

        // dd(User::all()->pluck('id'));
        //    DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // $tables = [
        //     'adresse_livraison',
        //     'bl_client',
        //     'commande',
        //     'detail_commande',
        //     'detail_devis',
        //     'detail_livraison',
        //     'detail_location',
        //     'devis',
        //     'enlevement',
        //     'demande_livraison',
        //     'facture',
        //     'ligne_paiement',
        //     'livraison',
        //     'likes',
        //     'location',
        //     'paiement',
        //     'reduction',
        //     'tva_commande',
        //     'preuve_operation_banque',
        // ];

        // foreach ($tables as $table) {
        //     DB::table($table)->truncate();
        // }

        // DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        return redirect()->route('client.index');
    }


    public function redirection()
    {
        dd('ça redirige');
    }
}
