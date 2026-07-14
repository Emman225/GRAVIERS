<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \Illuminate\Http\Response;
use App\Models\Categorie;
use App\Models\Produit;
use App\Models\Client;
use App\Models\PrixPersonnalise;
use App\Models\UniteProduit;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\productRequest;
use App\Http\Requests\imageRequest;
use App\Http\Requests\categoryRequest;
use Illuminate\Support\Facades\Storage;
use App\Models\ImageProduit;
use App\Models\CategorieProduit;
use Help;
use Illuminate\Auth\Access\Response as AccessResponse;
use Illuminate\Foundation\Events\VendorTagPublished;

class ProductsController extends Controller
{
    //
    public function productsList (){

        // $infoProduct = ImageProduit::all();

        // On affiche tous les produits (actifs ET inactifs, hors supprimés) afin de
        // pouvoir réactiver un produit passé à statut=2 — sinon il devient invisible
        // partout et impossible à republier depuis l'interface.
        return view('produit.products-list',[
            'produits' => Produit::orderByDesc('nom')->get()
        ]);
    }

    // Active / désactive un produit (publication au catalogue).
    public function toggleStatut(Produit $produit){
        $produit->statut = ($produit->statut == Help::$STATUT_ACTIF)
            ? Help::$STATUT_INACTIF
            : Help::$STATUT_ACTIF;
        $produit->save();

        $message = $produit->statut == Help::$STATUT_ACTIF
            ? 'Produit activé : il est désormais visible au catalogue.'
            : 'Produit désactivé : il est masqué du catalogue.';

        return back()->with('success', $message);
    }

    public function productsCategory (){

        // dd($client);
        $lists = Categorie::liste();
        // dd($list);
        return view('produit.categories',[
            'lists' => $lists,
            'categorie' => new Categorie()
        ]);
    }

    public function editCategory(Categorie $categorie){

        $lists = Categorie::liste();
        return view('produit.categories',[
            'lists' => $lists,
            'categorie' => $categorie
        ]);
    }

    public function editCategoryTraitement(Request $request, Categorie $categorie){
        $img_path = '';
        if($request->image){
            if ($categorie->image == '') {
                $img_path = $request->image->storeAs('categorieImage','public');
            }else{
                $img_path = $request->image->storeAs("categorieImage", $categorie->image, 'public');
            }
        }else{
            $img_path = $categorie->image;
        }
        $categorie->update([
            'nom' => $request->nom,
            'parent_id' => $request->parent > 0 ? $request->parent : 0,
            'description' => $request->description,
            'image' => $img_path,
            'icon' => $img_path,
        ]);

        return redirect()->route('product.editCategory',$categorie)->with('success','Succès');

    }

    public function deleteCategory(Categorie $categorie){
        $categorie->update([
            'statut' => Help::$STATUT_INACTIF,
            'deleted_at' => date('Y-m-d H:i:s')
        ]);

        return redirect()->route('product.category')->with('success','Supprimé avec succès');
    }

    public function productsAdd (){

        $produit = new Produit();
        $categories = Categorie::liste();

        return view('produit.add-products',[
            'categories' => $categories,
            'produit' => $produit,
            'unites' => UniteProduit::all(),
            'fournisseurs' => \App\Models\Fournisseur::liste(),
        ]);
    }

    public function saveCategorie(categoryRequest $request){
        $img = $request->image;
        $img_path = $img->store('categorieImage','public');
        $add = [
            'nom' => $request->nom,
           // 'parent' => $request->parent,
            'parent_id' => $request->parent,
            'description' => $request->description,
            'image' => $img_path,
            'icon' => $img_path,
            'statut' => 1
        ];
        Categorie::create($add);
        return redirect()->route('product.category')->with('succes','enregistré');
    }

    public function produitCategorie($nomCategorie){

        $categorie = Categorie::where('nom',$nomCategorie)->first();
        $client = (Auth::user())? Client::where('user_id',Auth::user()->id)->first() : new Client;
        $prixPerso = [];
        if ($client && $client->id) {
            $prixPerso = Produit::prixPersonnalisesPour($client);
        }
        // Mêmes produits que la boutique/home : actifs et de type VENTE uniquement
        // (on n'expose pas les produits désactivés ni les articles de location).
        $produits = $categorie
            ? $categorie->produits()->where('produit.statut', 1)->where('produit.type_affaire', 'VENTE')->avecFournisseur()->get()
            : collect();
        return view('produit.categorieListProduit',[
            'produits' => $produits,
            'categories' => Categorie::where('statut', 1)->get(),
            'nom' => $nomCategorie,
            'client' => $client,
            'prixPerso' => $prixPerso,
        ]);

    }

    public function saveProduct(Request $request){
       // dd($request);, imageRequest $image
        $request->validate([
            'reference' => 'required|string|max:10',
            'nom' => 'required',
            'abreviation' => 'required|string|max:10',
            'unite' => 'required',
            'prix_moyen' => 'required|integer',
            'reduction' => 'required',
            'prix_fournisseur' => 'required',
            'fournisseur' => 'required|exists:fournisseur,id',
            'qte' => 'required|integer|min:0',
            'categories' => 'required',
            'type_affaire' => 'required',
            'description' => 'required',
            'meilleur_note' => 'required',
            'image' => 'required|image|max:2048',
        ],[
            'image.max' => 'Votre image ne doit pas exeder 2Mo',
            'fournisseur.required' => 'Veuillez choisir un fournisseur.',
            'fournisseur.exists' => 'Le fournisseur sélectionné est invalide.',
            'qte.required' => 'Veuillez renseigner la quantité en stock.',
        ]);

        // Le select envoie 1 (Location) / 2 (Vente) ; la colonne est un enum LOCATION/VENTE.
        $typeAffaire = ((int) $request->type_affaire === 1) ? Help::$LOCATION : Help::$VENTE;

        $add = [
            'reference' => $request->reference,
            'nom' => $request->nom,
            'abreviation' => $request->abreviation,
            'unite_produit_id' => $request->unite,
            'prix_moyen' => $request->prix_moyen,
            'prix_reduction' => $request->reduction,
            'description' => $request->description,
            'meilleur_note' => $request->meilleur_note,
            'type_affaire' => $typeAffaire,
            'prix_fournisseur' => $request->prix_fournisseur,
            'caution' => $request->caution ?? 0,
        ];


        // dd($add);

        $produit = Produit::create($add);

        if ($request->hasFile('image')) {
            $request->validate([
                'image' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048', // Exemple de validation
            ]);

            $nomImage = $request->nom.'-'.$request->reference.'.'.$request->file('image')->getClientOriginalExtension();

            $request->file('image')->move(public_path('storage/productsImage'), $nomImage);

            $imageProduit = ImageProduit::create([
                'image' => 'productsImage/'.$nomImage,
                'produit_id' => $produit->id,
                'defaut' => 1
            ]);
        }

        foreach($request->categories as $categorie){
                $produit->categories()->attach($categorie);
        }

        // Rattachement au fournisseur (stock_produit) : c'est ce qui fait
        // "appartenir" le produit à un fournisseur et le rend visible au catalogue.
        \App\Models\StockProduit::updateOrCreate(
            ['fournisseur_id' => $request->fournisseur, 'produit_id' => $produit->id],
            [
                'qte'         => $request->qte,
                'prix'        => $request->prix_fournisseur,
                'seuil_alert' => 10,
                'statut'      => Help::$STATUT_ACTIF,
            ]
        );

        return redirect()->route('product.add')->with('success','Produit enregistré');
    }

    public function edit($id){
        $produit = Produit::find($id);
        // dd($produit->nom);
        return view('produit.edit-products',[
            'produit' => $produit,
            'unites' => UniteProduit::all(),
            'categories' => Categorie::all()
        ]);
    }

    // Mise à jour de produit côté gestionnaire
    public function update(Produit $produit, Request $request){



        $img = ImageProduit::where('produit_id','=',$produit->id)->first()->image;

        if($request->hasFile('image')){

            Storage::disk('public')->delete($img);
            // dd($img);

                $img = $request->image;
                $img_path = $img -> store('productsImage','public');

                $imageToUpdate = ImageProduit::where('produit_id','=',$produit->id);

                $imageToUpdate->update([
                    'image' => $img_path,
                    'produit_id' => $produit->id
                ]);
            }


                $produit->categories()->sync($request->categories);

            $produit->update([
                'reference' => $request->reference,
                'nom' => $request->nom,
                'abreviation' => $request->abreviation,
                'unite_produit_id' => $request->unite,
                'prix_moyen' => $request->prix_moyen,
                'description' => $request->description,
                'meilleur_note' => $request->meilleur_note,
                // Le select envoie 1 (Location) / 2 (Vente) : on stocke le libellé
                // attendu par les catalogues (comme à la création), sinon l'édition
                // écrivait '1'/'2' et le produit disparaissait des catalogues.
                'type_affaire'=> ((int) $request->type_affaire === 1) ? Help::$LOCATION : Help::$VENTE,
                'prix_reduction' => $request->reduction,
                'prix_fournisseur' => $request->prix_fournisseur,
                'caution' => $request->caution ?? 0
        ]);


        // dd($produit->categories);

        // $produit -> update($request->validated());
        return redirect()->route('product.edit',$produit->id)->with('success','modifié');

    }

    public function delete(Produit $produit){

        $produit->update([
            'deleted_at' => date('Y-m-d H:i:s')
        ]);

        return redirect()->route('product.list')->with('succes','Produit supprimé');

    }
}
