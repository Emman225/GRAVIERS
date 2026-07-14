<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Client;

use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;
use Help;

class CartController extends Controller
{
    //


    public function updateQuantite(Request $request){
        $request->validate([
            'rowId' => 'required',
            'qty' => 'required|numeric|min:0.1',
        ]);

        // Mise à jour de la quantité
        Cart::update($request->rowId, $request->qty);

        // Récupère le produit mis à jour
        $item = Cart::get($request->rowId);

        $tva = 0.18; // TVA par défaut à 18%
        // Si l'utilisateur est connecté, on récupère le taux de TVA du client
        if(Auth::check()){
            $client =  Client::where('user_id', Auth::user()->id)->first();
            $tva = Client::tva($client);
        }
        $ht = Cart::total();
        $montantTva = $ht * $tva;
        $ttc = $ht + $montantTva;

        return response()->json([
            'success' => true,
            'subtotal' => $item->subtotal(),//number_format($item->subtotal(), 0, '', ' ') , // sous-total ligne
            'total' => number_format($ht, 0, '', ' '),
            'tva' => number_format($montantTva, 0, '', ' '),
            'ttc' => number_format($ttc, 0, '', ' '),   // total panier
        ]);
    }



    public function updateAll(Request $request)
    {

        $qte = $request->qte;
        if($qte < 0.1){
                $qte = 0.1;
            }

        // $donnees = $request->input('data');

            // // Mise à jour de la quantité
            // Cart::update($request->rowId, $request->qty);

            // // Récupère le produit mis à jour
            // $item = Cart::get($request->rowId);

            // $tva = 0.18; // TVA par défaut à 18%
            // // Si l'utilisateur est connecté, on récupère le taux de TVA du client
            // if(Auth::check()){
            //     $client =  Client::where('user_id', Auth::user()->id)->first();
            //     $tva = Client::tva($client);
            // }
            // $ht = Cart::total();
            // $montantTva = $ht * $tva;
        // $ttc = $ht + $montantTva;

        $requestMontant = Help::montantStringVersEnt($request->montant);
        $prod = Cart::get($request->rowId);

        $modif ['premiere'] = $prod;

        // On utilise $qte (déjà clampé à 0.1 minimum) plutôt que $request->qte
        // brut, sinon une valeur 0 supprimerait silencieusement l'item du panier.
        if($prod->qty != $qte){
            $row = Cart::update($request->rowId, $qte);
        }else{

            $nbre =$requestMontant/$request->prixUnitaire;
            $nbreFormat = Help::truncateToTwoDecimals($nbre);
            // $qte = number_format($nbreFormat,2,'.',' ');
            $qte = $nbreFormat < 0.1 ? 0.1 : $nbreFormat;
            // dd($qte);
            $row = Cart::update($request->rowId,$qte);
        }

        // return response()->json(['qui?' => $dire,'qte' => $qte, 'rowid' => $request->rowId], );

        $item = Cart::get($request->rowId);


        $tva = 0.18; // TVA par défaut à 18%

        // Si l'utilisateur est connecté, on récupère le taux de TVA du client
        if(Auth::check()){
            $client =  Client::where('user_id', Auth::user()->id)->first();
            $tva = Client::tva($client);
        }

        $ht = Cart::total();

        $montantTva = $ht * $tva;
        $ttc = $ht + $montantTva;




        return response()->json([
            'success' => true,
            'subtotal' => $item->subtotal(),//number_format($item->subtotal(), 0, '', ' ') , // sous-total ligne
            'total' => number_format($ht, 0, '', ' '),
            'tva' => number_format($montantTva, 0, '', ' '),
            'ttc' => number_format($ttc, 0, '', ' '),   // total panier
            'rowId' => $item->rowId,
            'qte' => $item->qty,
            'qtte' => $qte ? $qte : null,
            'montant' => $request->montant,
            'prixUnitaire' => $request->prixUnitaire,
            'autre' => $request->all(),

        ]);
    }


}
