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

    public function unWey()
    {
            // DB::select("TRUNCATE TABLE logs");

        //     dd(DB::select("SELECT * FROM vehicule ORDER BY created_at DESC"));
            // dd( LignePaiement::orderBy('created_at', 'desc')->get(), Paiement::orderBy('created_at', 'desc')->get());

        // return view('client.index',[
        //         'categories' => Categorie::all(),
        //         'produits' => Produit::where('type_affaire','VENTE')->get(),
        //         'client' =>   (Auth::user()) ? Client::where('user_id',Auth::user()->id)->first() : new Client,
        //     ]);

        // dd(paiement::orderByDesc("created_at")->get(), LignePaiement::orderByDesc("created_at")->get());
        // dd(DB::select("SELECT * FROM paiement"));
        // dd(Facture::orderBy('created_at', 'desc')->get(), Paiement::orderBy('created_at', 'desc')->get(), LignePaiement::orderBy('created_at', 'desc')->get());
        // return view('welcome',[
        //     'categories' => Categorie::all(),
        //     'produits' => Produit::all(),
        //     'client' => Client::where('user_id',Auth::user()?->id)->first() ?? new Client
        // ]);

        $tables = [
            'adresse_livraison',
            'apporteur',
            'bl_client',
            'commande',
            'client',
            'commission_apporteur',
            'demande_annulation_commande',
            'demande_compte_client_a_terme',
            'demande_livraison',
            'demande_paiement',
            'detail_commande',
            'detail_devis',
            'detail_livraison',
            'detail_location',
            'devis',
            'enlevement',
            'facture',
            'fournisseur',
            'ligne_paiement',
            'likes',
            'livraison',
            'livreur',
            'location',
            'paiement',
            'reduction',
            'retour_produit',
            'tva_commande',
            'vehicule',
        ];

        // Désactiver les contraintes FK (important si relations)
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        foreach ($tables as $table) {
            DB::statement("TRUNCATE TABLE `$table`");
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        DB::statement("DELETE FROM users WHERE login != 'imlod'");


        dd(Livraison::all(), Fournisseur::all(), Livreur::all(), Apporteur::all());

        $pass = '$2y$12$abK7eswTq8vgSuy9qLK1CODhxw59ZCdqdZr3ZHN7ppgb4/dtPPdyW';

        $logs = DB::select("update users set password = '$pass' where id = 130");
        dd(LignePaiement::orderBy('created_at', 'desc')->get(), Paiement::all());

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
