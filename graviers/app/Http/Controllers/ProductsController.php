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

    /**
     * La page de création. Le formulaire ne partage plus l'écran de la liste :
     * on y consultait et on y saisissait au même endroit, et la liste reculait
     * de tout un formulaire au premier clic sur « Nouvelle catégorie ».
     */
    public function nouvelleCategorie(){

        return view('produit.formCategorie',[
            'lists' => Categorie::liste(),
            'categorie' => new Categorie()
        ]);
    }

    public function editCategory(Categorie $categorie){

        $lists = Categorie::liste();
        return view('produit.formCategorie',[
            'lists' => $lists,
            'categorie' => $categorie
        ]);
    }

    public function editCategoryTraitement(Request $request, Categorie $categorie){
        // storeAs() était appelée avec deux arguments là où elle en attend trois :
        // le disque « public » y était pris pour le NOM du fichier, et l'image
        // atterrissait à côté de sa destination. L'autre branche repassait le
        // chemin complet en guise de nom, ce qui redoublait le dossier
        // (« categorieImage/categorieImage/… »). On enregistre simplement le
        // nouveau fichier, comme à la création.
        $img_path = $request->hasFile('image')
            ? $request->file('image')->store('categorieImage', 'public')
            : $categorie->image;
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
            // Le formulaire affiche le prix de vente calculé à mesure qu'on
            // saisit le prix d'achat : il lui faut le taux en vigueur.
            'tauxDalakoun' => \App\Models\PourcentageDalakoun::tauxEnVigueur(),
            'categories' => $categories,
            'produit' => $produit,
            'unites' => UniteProduit::all(),
            'fournisseurs' => \App\Models\Fournisseur::liste(),
        ]);
    }

    public function saveCategorie(categoryRequest $request){

        // L'IMAGE EST FACULTATIVE. On appelait store() sur elle sans vérifier
        // qu'un fichier avait été joint : créer une catégorie sans image
        // provoquait « Call to a member function store() on null », c'est-à-dire
        // une page blanche et une erreur 500 côté navigateur.
        $img_path = $request->hasFile('image')
            ? $request->file('image')->store('categorieImage', 'public')
            : null;

        $add = [
            'nom' => $request->nom,
            // `parent_id` n'accepte pas le vide en base, et aucun parent choisi
            // donnait donc une seconde erreur 500. Zéro = catégorie racine,
            // convention déjà retenue par l'écran de modification.
            'parent_id' => $request->parent > 0 ? $request->parent : 0,
            'description' => $request->description,
            'image' => $img_path,
            'icon' => $img_path,
            'statut' => 1
        ];
        Categorie::create($add);
        return redirect()->route('product.category')->with('succes','enregistré');
    }

    /** Les tris proposés, et ce qu'ils veulent dire. */
    public const TRIS_CATEGORIE = [
        'tendance'  => 'Tendance',
        'prix_asc'  => 'Prix croissant',
        'prix_desc' => 'Prix décroissant',
        'nouveaute' => 'Nouveautés',
        'note'      => 'Meilleure note',
    ];

    public function produitCategorie(Request $request, $nomCategorie){

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
        // Le prix d'une page de catégorie doit être celui du panier, comme
        // partout ailleurs sur la boutique.
        \App\Models\Produit::alignerPrixAffiche($produits);

        // LE TRI ET LE FILTRE PORTENT SUR LE PRIX AFFICHÉ.
        //
        // « Trier par » ne triait rien : les cinq entrées du menu pointaient sur
        // « # », un reste du gabarit. Et le filtre par prix était commenté.
        //
        // Le prix retenu est celui que le visiteur VOIT : le prix personnalisé
        // s'il en a un, sinon le prix catalogue qui vient d'être calculé. Trier
        // sur la colonne stockée classerait selon un montant que personne
        // n'affiche — c'est exactement ce qui avait cassé le filtre par montant
        // de l'application mobile.
        $prixAffiche = function ($produit) use ($prixPerso) {
            return (float) ($prixPerso[$produit->id] ?? $produit->prix_moyen);
        };

        $prixMin = $request->filled('prix_min') ? (float) $request->prix_min : null;
        $prixMax = $request->filled('prix_max') ? (float) $request->prix_max : null;

        // Bornes inversées : on les remet dans l'ordre plutôt que de ne rien
        // rendre. Saisir 5000 puis 1000 est une maladresse, pas une demande de
        // liste vide.
        if ($prixMin !== null && $prixMax !== null && $prixMin > $prixMax) {
            [$prixMin, $prixMax] = [$prixMax, $prixMin];
        }

        if ($prixMin !== null) {
            $produits = $produits->filter(fn ($p) => $prixAffiche($p) >= $prixMin);
        }

        if ($prixMax !== null) {
            $produits = $produits->filter(fn ($p) => $prixAffiche($p) <= $prixMax);
        }

        $tri = array_key_exists($request->tri, self::TRIS_CATEGORIE) ? $request->tri : 'tendance';

        $produits = match ($tri) {
            'prix_asc'  => $produits->sortBy($prixAffiche),
            'prix_desc' => $produits->sortByDesc($prixAffiche),
            'nouveaute' => $produits->sortByDesc('created_at'),
            'note'      => $produits->sortByDesc('meilleur_note'),
            default     => $produits,
        };

        $produits = $produits->values();

        // UNE PAGINATION QUI DIT LA VÉRITÉ.
        //
        // La page affichait « 1 2 3 … 6 » en dur, quel que soit le nombre
        // d'articles : six pages annoncées pour quatre produits, et aucun des
        // liens ne menait nulle part.
        $parPage = 20;

        $produits = new \Illuminate\Pagination\LengthAwarePaginator(
            $produits->forPage($request->input('page', 1), $parPage),
            $produits->count(),
            $parPage,
            $request->input('page', 1),
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('produit.categorieListProduit',[
            'produits' => $produits,
            'categories' => Categorie::where('statut', 1)->get(),
            'nom' => $nomCategorie,
            'client' => $client,
            'prixPerso' => $prixPerso,
            'tri' => $tri,
            'tris' => self::TRIS_CATEGORIE,
            'prixMin' => $prixMin,
            'prixMax' => $prixMax,
        ]);

    }

    public function saveProduct(Request $request){
       // dd($request);, imageRequest $image
        $request->validate([
            'reference' => 'required|string|max:10',
            'nom' => 'required',
            'abreviation' => 'required|string|max:10',
            'unite' => 'required',
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
            // Prix de vente CALCULÉ, jamais saisi : prix d'achat majoré du
            // pourcentage DALAKOUN.
            'prix_moyen' => round(\App\Models\PourcentageDalakoun::appliquerA((float) $request->prix_fournisseur)),
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
            'tauxDalakoun' => \App\Models\PourcentageDalakoun::tauxEnVigueur(),
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

            // LES PRIX D'ACHAT SE CORRIGENT ICI.
            //
            // Une seule vue de toute l'application écrivait `stock_produit.prix` :
            // l'espace du fournisseur, qui résout le fournisseur depuis
            // l'utilisateur CONNECTÉ. Aucun administrateur ne pouvait donc
            // corriger un prix d'achat faux — celui-là même qui fait désormais
            // le prix de vente. Deux prix aberrants restaient ainsi intouchables.
            $request->validate([
                'prix_achat.*' => 'nullable|numeric|min:0|max:99999999',
            ], [
                'prix_achat.*.numeric' => 'Veuillez saisir un nombre',
                'prix_achat.*.min'     => 'Un prix d\'achat ne peut pas être négatif',
                'prix_achat.*.max'     => 'Ce prix d\'achat est hors limites',
            ]);

            foreach ((array) $request->input('prix_achat', []) as $ligneId => $montant) {
                if ($montant === null || $montant === '') {
                    continue;
                }

                // Bornée au produit ouvert : un identifiant forgé n'atteint
                // pas la ligne de stock d'un autre produit.
                \App\Models\StockProduit::where('id', (int) $ligneId)
                    ->where('produit_id', $produit->id)
                    ->update(['prix' => (float) $montant]);
            }

            // RETIRER UN FOURNISSEUR D'UN PRODUIT.
            //
            // On désactive la ligne au lieu de la supprimer : les bons, les
            // paiements et l'historique déjà émis restent lisibles. Le statut
            // inactif est déjà respecté partout — prix de vente, réapprovision-
            // nement, choix du fournisseur sur un bon.
            $lignes = \App\Models\StockProduit::where('produit_id', $produit->id)
                ->whereNull('deleted_at')
                ->get();

            $retires = (array) $request->input('fournisseur_retire', []);

            if ($lignes->isNotEmpty() && count($retires) >= $lignes->count()) {
                // Sans aucun fournisseur, le prix de vente ne se recalcule plus :
                // le catalogue garderait un prix que plus rien ne justifie.
                return back()
                    ->withInput()
                    ->with('produit_erreur', 'Un produit doit garder au moins un fournisseur. Corrigez plutôt le prix d\'achat de celui que vous vouliez retirer.');
            }

            foreach ($lignes as $ligne) {
                $ligne->update([
                    'statut' => isset($retires[$ligne->id])
                        ? Help::$STATUT_INACTIF
                        : Help::$STATUT_ACTIF,
                ]);
            }

            $produit->update([
                'reference' => $request->reference,
                'nom' => $request->nom,
                'abreviation' => $request->abreviation,
                'unite_produit_id' => $request->unite,
                // Le prix de vente se calcule sur le prix d'achat RÉEL — celui des
                // lignes de stock, propre à chaque fournisseur — et non sur un
                // champ du formulaire qui, en modification, n'en est que le reflet.
                'prix_moyen' => round(\App\Models\PourcentageDalakoun::appliquerA(
                    (float) (\App\Models\Produit::prixAchatDe($produit->id) ?? $produit->prix_fournisseur),
                    $produit->pourcentage_dalakoun
                )),
                'description' => $request->description,
                'meilleur_note' => $request->meilleur_note,
                // Le select envoie 1 (Location) / 2 (Vente) : on stocke le libellé
                // attendu par les catalogues (comme à la création), sinon l'édition
                // écrivait '1'/'2' et le produit disparaissait des catalogues.
                'type_affaire'=> ((int) $request->type_affaire === 1) ? Help::$LOCATION : Help::$VENTE,
                'prix_reduction' => $request->reduction,
                'prix_fournisseur' => $request->filled('prix_fournisseur')
                    ? $request->prix_fournisseur
                    : $produit->prix_fournisseur,
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
