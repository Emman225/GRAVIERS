<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Livreur;
use App\Models\Fournisseur;
use App\Models\Enlevement;
use App\Models\Livraison;
use App\Models\Client;
use Carbon\Carbon;
use App\Models\Commande;

class GrandLivreController extends Controller
{
    //

    public function grandLivreFournisseur(){
        return view('grand-livre.fournisseur',[
            'fournisseurs' => Fournisseur::where('deleted_at',null)->get()
        ]);
    }

    public function grandLivreLivreur(){
        return view('grand-livre.livreur',[
            'livreurs' => Livreur::where('deleted_at',null)->get()
        ]);
    }

    public function grandLivreClientOrdinaire(){

        $users = User::where('type_user_id',4)->get();

        return view('grand-livre.clientOrdinaire',[
            'users' => $users,
        ]);
    }

    public function clientOrdinaireDetail(Client $client){
        return view('grand-livre.clientOrdinaireDetail',[
            'commandes' => Commande::where('client_id',$client->id)->get(),
            'client' => $client
        ]);
    }
    public function clientOrdinairePaiements(Client $client){
        // dd($client->lignes);
        return view('grand-livre.clientPaiements',[
            'lignes' => $client->lignes->where('statut',1)->sortByDesc('created_at'),
            'client' => $client
        ]);
    }
    public function clientOrdinaireFactures(Client $client){
        return view('grand-livre.clientFactures',[
            'factures' => $client->factures,
            'client' => $client
        ]);
    }

    public function grandLivreClientATerme(){
        return view('grand-livre.clientATerme',[
            'users' => User::where('type_user_id',4)->get()
        ]);
    }

    public function detailFournisseur(Fournisseur $fournisseur){
        return view('grand-livre.fournisseurBon',[
            'enlevements' => Enlevement::where('qte_servi','!=',null)->where('fournisseur_id',$fournisseur->id)->get(),
            'fournisseur' => $fournisseur
        ]);
    }

    public function livreurDetail(Livreur $livreur){
        return view('grand-livre.livreurDetail',[
            'enlevements' => Enlevement::where('qte_servi','!=',null)->where('livreur_id',$livreur->id)->get(),
            'livraisons' => Livraison::where('livreur_id','=',$livreur->id)->where('etat_livraison','LIVREE')->get(),
            'livreur' => $livreur
        ]);
    }




}
