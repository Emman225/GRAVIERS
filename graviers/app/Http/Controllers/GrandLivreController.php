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

    /**
     * Les clients d'un grand livre, choisis EN BASE et non dans la vue.
     *
     * Les deux écrans chargeaient tous les comptes clients — à terme comme
     * ordinaires — puis écartaient les autres dans le Blade. Trois effets :
     * la moitié des lignes était lue pour rien, le compte des lignes affichées
     * était inconnu du contrôleur, et un compte sans fiche client produisait
     * une ligne muette.
     */
    private function clientsDuGrandLivre(int $aTerme)
    {
        return Client::with('user')
            ->where('client_a_terme', $aTerme)
            ->whereHas('user', fn ($q) => $q->where('type_user_id', 4))
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get();
    }

    public function grandLivreClientOrdinaire(){

        return view('grand-livre.clientOrdinaire',[
            'clients' => $this->clientsDuGrandLivre(0),
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
            'clients' => $this->clientsDuGrandLivre(1),
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
