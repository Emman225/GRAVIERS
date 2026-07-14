<?php

namespace App\Http\Controllers;

use Cart;
use Help;
use App\Models\Pays;
use App\Models\Devis;
use App\Models\Ville;
use App\Models\Client;
use App\Models\Produit;
use App\Models\Categorie;
use App\Models\DetailDevis;
use App\Models\ModePaiement;
use Illuminate\Http\Request;
use App\Models\Configuration;
use App\Models\TypeLivraison;
use App\Models\AdresseLivraison;
use App\Models\PrixPersonnalise;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\TestController;

class DevisController extends Controller
{
    //

    public function editDevis(Devis $devis){

        if(!session('devisAModifier')){

            $total = $devis->montant;

            Cart::destroy();
            $prixPerso = [];
            $clientObj = Auth::user() ? Client::where('user_id', Auth::user()->id)->first() : null;
            if ($clientObj) {
                $prixPerso = Produit::prixPersonnalisesPour($clientObj);
            }

            // Prix fournisseur le plus bas pour tous les produits du devis, en UNE requête
            // (évite un appel prixPour()/requête stock par ligne dans la boucle).
            $produitIds = $devis->detailDevis->pluck('produit_id')->filter()->unique()->all();
            $prixFournisseur = \App\Models\StockProduit::whereIn('produit_id', $produitIds)
                ->where('statut', \Help::$STATUT_ACTIF)
                ->where('prix', '>', 0)
                ->whereNull('deleted_at')
                ->groupBy('produit_id')
                ->selectRaw('produit_id, MIN(prix) as mn')
                ->pluck('mn', 'produit_id')
                ->toArray();

            foreach($devis->detailDevis as $detail){
                // Prix actualisé, même logique que Produit::prixPour() mais sans requête par ligne :
                // prix personnalisé client > prix fournisseur le plus bas > prix_moyen.
                $prix = $prixPerso[$detail->produit->id]
                    ?? ($prixFournisseur[$detail->produit->id] ?? (float) $detail->produit->prix_moyen);
                Cart::add($detail->produit->id, $detail->produit->nom, $detail->qte, $prix, [
                    'image' => $detail->produit->image->first()->image,
                    'note' => $detail->produit->meilleur_note,
                    'unite' => $detail->produit->UniteProduit->abreviation,
                    'type_affaire' => $detail->produit->type_affaire,
                    'prix_fournisseur' => $detail->produit->prix_fournisseur,

                    ])->associate('App\Models\Produit');
            }

            session()->put([
                'devisAModifier' => $devis->id,
                'niveauModifDevis' => 1
            ]);
        }else{
            $total = Cart::total();
        }
        $client = HELP::clientValide();

        // Ajout du produit à la selection
        return view('client.modifierDevis',[
            'total' => $total,
            'produits' => Produit::all(),
            'categories' => Categorie::all(),
            'devis' => $devis,
            'totalCommande' => Cart::total(),
            'conf' => Configuration::first(),
            'tva' => Client::tva($client),
            'client' => $client,
        ]);



    }

    // public function updateDevis(Devis $devis, Request $request){

    //     $nbPanier = Cart::count();
    //     if($nbPanier > 0){

    //         $rowsId = $request->rowId;
    //         $totalMontants = $request->montant;
    //         $totalQtes = $request->qte;

    //         foreach($rowsId as $key => $rowId){

    //             $produit = Cart::get($rowId);

    //             $partieEntiere = ($produit->qty == $totalQtes[$key]) ? intdiv(intval($totalMontants[$key]), $produit->price) : $totalQtes[$key] ;

    //             Cart::update($rowId, $partieEntiere);
    //         }

    //         dd(cart::content());
    //     }

    //     $data = array();

    //     foreach (Cart::content() as $prod) {
    //        array_push($data,$prod);
    //     }


    //     if(session('devisAModifier')){
    //         return redirect()->route('client.editDevis',session('devisAModifier'))->with('ok','Le panier a été mis à jour');
    //     }

    //     foreach(Cart::content() as $produit){

    //         if($produit->options->type_affaire == "LOCATION"){
    //             // dd('ok');
    //             return redirect()->route('client.panierLocation')->with('ok','Le panier a été mis à jour');
    //         }
    //     }

    //     return redirect()->route('client.monPanier')->with('ok','Le panier a été mis à jour');
    // }

    public function updateDevis(Devis $devis, Request $request){
        // dd($devis, $request->all());
        $client = Client::where('user_id', Auth::user()->id)->first();
        if(session('devisAModifier')){

            switch(session('niveauModifDevis')){
                case 1: // niveau 1 : on enregistre les informations du panier uniquement

                    $dev = Devis::find(session('devisAModifier'));
                    $this->newDetailDevis($dev);

                    $devis->update([
                            'montant' => Cart::total(),
                            'tva' => Client::tva($client) * Cart::total(),
                        ]);

                    $this->annulerModificationDevis($devis);

                    break;

                case 2: // niveau 2 : on enregistre les informations de l'adresse de livraison
                    $dev = Devis::find(session('devisAModifier'));
                    $this->newDetailDevis($dev);

                    $adresseId = null;

                    if(session('0')['ville']){
                        $ville = Ville::find(session('0')['ville']);

                        $adresse_livraison = AdresseLivraison::create([
                                'client_id' => $client->id,
                                'ville_id' => session('0')['ville'],
                                'pays_id' => $ville->pays_id,
                                'affichage' => session('0')['infoSup'],
                                'longitude' => session('0')['long'],
                                'latitude' => session('0')['lat'],
                            ]);
                        $adresseId = $adresse_livraison->id;
                    }
                    $devis->update([
                            'montant' => Cart::total(),
                            'tva' => Client::tva($client) * Cart::total(),
                            'adresse_livraison_id' => $adresseId,
                            'cout_livraison' => session('0')['cout_livraison'],
                        ]);

                    $this->annulerModificationDevis($devis);

                    break;
                case 3:
                    // niveau 3 : on enregistre les informations du mode de livraison, mode de paiement, date de livraison
                    // $devis->update([
                    //     'mode_livraison' => $request->mode_livraison,
                    //     'mode_paiement' => $request->mode_paiement,
                    //     'date_livraison' => $request->date_livraison,
                    // ]);
                    break;
            }

            return redirect()->route('client.devisValide',$devis)->with('success','Votre dévis a été enregistré');

        }
    }

    public function annulerModificationDevis($devis){

        session()->forget([
                        'devisAModifier',
                        'niveauModifDevis'
                    ]);
        Cart::destroy();
        return redirect()->route('client.index')->with('success', 'Modification annulée avec succès.');
    }

    public function recapDevis(Request $request, Devis $devis){

        $client = Client::where('user_id', Auth::user()->id)->first();
        $livraison = new ClientController;
        $livraison->infoLivraison($request,$client);

        // switch(session('niveauModifDevis')){
        //     case 1:


        //         session()->put([
        //             'cout_livraison' => $calcule['cout_livraison'],
        //             'km' => $calcule['km'],
        //         ]);

        //         dd('ici');

        //         break;
        //     case 2:
        //         // niveau 2 : on enregistre les informations de l'adresse de livraison
        //         $devis->adresseLivraison()->update([
        //             'longitude' => $request->longitude,
        //             'latitude' => $request->latitude,
        //             'ville_id' => $request->ville_id,
        //         ]);
        //         break;
        //     case 3:
        //         // niveau 3 : on enregistre les informations du mode de livraison, mode de paiement, date de livraison
        //         $devis->update([
        //             'mode_livraison' => $request->mode_livraison,
        //             'mode_paiement' => $request->mode_paiement,
        //             'date_livraison' => $request->date_livraison,
        //         ]);
        //         break;
        // }

            $total = Cart::total();

            $client = Client::where('user_id',Auth::user()->id)->first();

            $conf = Configuration::first();

        return view('orders.recapDevis',[
            'devis' => $devis,
            'produits' => Produit::all(),
            'client' => HELP::clientValide(),
            'categories' => Categorie::all(),
            'total' => $total,

        ]);

    }

    public function modePaiement(Devis $devis){
        session()->put([
                    'devis' => $devis->id,
                    'type' => 'devis'
                ]);
                $client = Client::where('user_id',Auth::user()->id)->first();


        return view('client.modePaiement',[
            // 'devis' => $devis,
            'produits' => Produit::all(),
            'pays' => Pays::all(),
            'villes' => Ville::all(),
            'client' => $client,
            'categories' => Categorie::all(),
            'modes'=> ModePaiement::all(),
            'total' => $devis->montant,
            'devis' => $devis,
            'typeLivraison' => TypeLivraison::orderBy('libelle')->get(),
            'conf' => Configuration::first(),
            'tva' => Client::tva($client),
        ]);
    }

    private function newDetailDevis($devis){
         foreach($devis->detailDevis as $detail){
            $detail->update([
                'deleted_at' => date('Y-m-d H:i:s')
            ]);
        }
        foreach(Cart::content() as $produit){

            $detailDevis = DetailDevis::create([
                'produit_id' => $produit->id,
                'devis_id' => $devis->id,
                'qte' => $produit->qty,
                'prix' => $produit->price,
                'prix_fournisseur' => $produit->options->prix_fournisseur
            ]);
        }

    }
}
