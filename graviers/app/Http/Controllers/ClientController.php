<?php

namespace App\Http\Controllers;

use App\Exports\exportCommandeClient;
use App\Http\Controllers\PaiementController;
use App\Http\Controllers\PaiementEnLigne;
use App\Mail\confirmationEmail;
use App\Mail\confirmClient;
use App\Mail\ConfirmPaiement;
use App\Mail\emailCommande;
use App\Mail\emailLocation;
use App\Mail\emailPaiementLocationClient;
use App\Models\AdresseLivraison;
use App\Models\Apporteur;
use App\Models\BlClient;
use App\Models\blog_commentaire;
use App\Models\blog;
use App\Models\Categorie;
use App\Models\Client;
use App\Models\Commande;
use App\Models\CommissionApporteur;
use App\Models\Compte;
use App\Models\Configuration;
use App\Models\CoutLivraison;
use App\Models\DemandeAnnulationCommande;
use App\Models\DemandeCompteClientATerme;
use App\Models\DemandeLivraison;
use App\Models\DetailCommande;
use App\Models\DetailDevis;
use App\Services\FneService;
use App\Models\DetailLivraison;
use App\Models\DetailLocation;
use App\Models\Devis;
use App\Models\ImageProduit;
use App\Models\LignePaiement;
use App\Models\like;
use App\Models\Livraison;
use App\Models\Location;
use App\Models\ModePaiement;
use App\Models\NoteProduit;
use App\Models\Paiement;
use App\Models\Pays;
use App\Models\PreuveOperation;
use App\Models\PreuveOpreation;
use App\Models\PrixPersonnalise;
use App\Models\Produit;
use App\Models\Reduction;
use App\Models\Region;
use App\Models\RetourProduit;
use App\Models\test;
use App\Models\TicketSAV;
use App\Models\TvaCommande;
use App\Models\TypeLivraison;
use App\Models\TypeUser;
use App\Models\TypeVehicule;
use App\Models\UniteProduit;
use App\Models\User;
use App\Models\Vehicule;
use App\Models\Ville;
use Carbon\Carbon;
use Darryldecode\Cart\Facades;
use Gloudemans\Shoppingcart\Facades\Cart;
use Help;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PDF;

// require_once 'packages/dompdf/autoload.inc.php';
class ClientController extends Controller
{

    public function accueil(){
        $client = Auth::user() ? Client::where('user_id', Auth::user()->id)->first() : null;
        $prixPerso = [];
        if ($client && $client->id) {
            $prixPerso = Produit::prixPersonnalisesPour($client);
        }
        // Eager loading des relations utilisées en vue pour éviter N+1
        // (image.first + categories sur chaque produit, produits sur chaque catégorie)
        $produits = Produit::with(['image', 'categories'])
            ->where('type_affaire', 'VENTE')
            ->where('statut', 1)
            ->avecFournisseur()
            // Affichage du moins cher au plus cher selon le prix fournisseur le plus bas.
            // Les produits sans prix fournisseur sont renvoyés à la fin.
            ->orderByRaw('COALESCE((SELECT MIN(sp.prix) FROM stock_produit sp WHERE sp.produit_id = produit.id AND sp.statut = 1 AND sp.deleted_at IS NULL AND sp.prix > 0), 999999999999) ASC')
            ->paginate(12)
            ->onEachSide(2);

        $categories = Categorie::with(['produits' => function ($q) {
            // Préfixer avec produit. car la relation many-to-many passe par
            // categorie_produit, et 'statut' existe sur les 3 tables.
            $q->where('produit.statut', 1)->where('produit.type_affaire', 'VENTE')->avecFournisseur();
        }, 'produits.image'])->get();

        // Prix fournisseur le plus bas (parmi les stocks actifs avec un prix défini)
        // pour chaque produit. Sert de prix affiché au catalogue (le client commande à ce prix).
        $prixFournisseur = \App\Models\StockProduit::where('statut', 1)
            ->where('prix', '>', 0)
            ->groupBy('produit_id')
            ->selectRaw('produit_id, MIN(prix) as mn')
            ->pluck('mn', 'produit_id')
            ->toArray();

        // Surcharge d'affichage uniquement (non persistée) : on aligne le prix affiché
        // du catalogue sur le prix fournisseur le plus bas. Le prix métier (prixPour)
        // utilisé pour le panier/commande reste inchangé.
        $appliquerPrix = function ($p) use ($prixFournisseur) {
            if (isset($prixFournisseur[$p->id])) {
                $p->prix_moyen = (float) $prixFournisseur[$p->id];
            }
            return $p;
        };
        $produits->getCollection()->transform($appliquerPrix);
        foreach ($categories as $cat) {
            foreach ($cat->produits as $p) {
                $appliquerPrix($p);
            }
        }

        return view('client.index',[
            'produits' => $produits,
            'categories' => $categories,
            'client' => $client,
            'prixPerso' => $prixPerso,
            'prixFournisseur' => $prixFournisseur,
        ]);
    }

    public function gestionVehicule(){

        $client = Client::where('user_id', Auth::user()->id)->first();

        return view('client.gestionVehicule',[
            'types' => TypeVehicule::all(),
            'vehicules' => Vehicule::liste($client->id),
            'produits' => Produit::all(),
            'client' => $client,
            'categories' => Categorie::all(),
            'vehicule' => new Vehicule,
        ]);
    }
    public function ajoutVehicule(Request $request){


        $request->validate([
            'marque' => 'required',
            'modele' => 'required',
            'matricule' => 'required',
            'type' => 'required',
            'capacite' => 'required|integer',
        ],[
            'marque.required' => 'Veuillez entrer une marque',
            'modele.required' => 'Veuillez entrer un modele',
            'matricule.required' => 'Veuillez entrer un matricule',
            'type.required' => 'Veuillez entrer un type de vehicule',
            'capacite.required' => 'Veuillez entrer une capacite valide',
            'capacite.number' => 'La capacité doit être un nombre',

        ]);

        $v = new Vehicule;
        $v->marque = $request->marque;
        $v->modele = $request->modele;
        $v->type_vehicule_id = $request->type;
        $v->immatriculation = $request->matricule;
        $v->capacite = $request->capacite;
        $v->livreur_id = Client::where('user_id', Auth::user()->id)->first()->id;
        $v->save();

        return back()->with('success', 'Enregistré');

    }

    public function modifierVehicule(Vehicule $vehicule){
        $client = Client::where('user_id', Auth::user()->id)->first();

        return view('client.gestionVehicule',[
            'types' => TypeVehicule::all(),
            'vehicules' => Vehicule::liste($client->id),
            'produits' => Produit::all(),
            'client' => $client,
            'categories' => Categorie::all(),
            'vehicule' => $vehicule,
        ]);
    }

    public function modifierVehiculeTraite(Request $request, Vehicule $vehicule){
        $request->validate([
            'marque' => 'required',
            'modele' => 'required',
            'matricule' => 'required',
            'type' => 'required',
            'capacite' => 'required|integer',
        ],[
            'marque.required' => 'Veuillez entrer une marque',
            'modele.required' => 'Veuillez entrer un modele',
            'matricule.required' => 'Veuillez entrer un matricule',
            'type.required' => 'Veuillez entrer un type de vehicule',
            'capacite.required' => 'Veuillez entrer une capacite valide',
            'capacite.number' => 'La capacité doit être un nombre',

        ]);

        $vehicule->marque = $request->marque;
        $vehicule->modele = $request->modele;
        $vehicule->type_vehicule_id = $request->type;
        $vehicule->immatriculation = $request->matricule;
        $vehicule->capacite = $request->capacite;
        $vehicule->livreur_id = Client::where('user_id', Auth::user()->id)->first()->id;
        $vehicule->save();

        return redirect()->route('client.gestionVehicule')->with('success', 'Modifié');
    }

    public function supprimerVehicule(Vehicule $vehicule){

        $vehicule->deleted_at = date('Y-m-d H:i:s');
        $vehicule->save();

        return back()->with('success','Supprimé');
    }

    public function factureTest(){
        $data = 'frontend/assets/imgs/logo/logooBlanc.svg';
        $pdf = PDF::loadView('factureTest',['image' => $data]);

        return $pdf->download();
    }

    public function listeFacture(Commande $commande){

        return view('client.listeFacture',[
            'commande' => $commande,
            'categories' => Categorie::all(),
            'produits' => Produit::all(),
            'client' => Client::where('user_id',Auth::user()->id)->first()
        ]);
    }

    public function nouvelleFacture($numero){
        $commande = Commande::where('numero',$numero)->first();

        //$image = public_path('storage\logo\logooBlanc.svg');
        // $image = Storage::url('logo/logoVide300.png');
        $image = config('constantes.logo');
       // dd($image);  //public_path('storage\logo\logooBlanc.svg');

        // return view('document.factureCommande',[
        //     'commande' => $commande,
        //     'image' => $image
        // ]);
        // return 'nn';

        $config = Configuration::first();
        $client = $commande->client;
        $fneData = FneService::getDonneesFne(null, $client);

        $pdf = PDF::loadView('document.factureCommande', array_merge([
            'commande' => $commande, 'image' => $image, 'config' => $config,
            'enlevements' => collect(), 'facture' => new \App\Models\Facture(), 'livraison' => 0,
        ], $fneData))
            ->setOptions(['isHTML5ParseEnebled' => true, 'defaultPaperOrientation' => 'portait']);

        return $pdf->stream();

    }

    public function exportCommande(){
        $image = config("constantes.logo");;
        $client = Client::where('user_id',Auth::user()->id)->first();
        $commandes = Commande::where('client_id',$client->id)->orderByDesc('created_at')->get();

        $pdf = PDF::loadView('document.etatCommande',['commandes' => $commandes,'image' => $image])
                    ->setOptions(['isHTML5ParseEnebled' => true, 'defaultPaperOrientation' => 'portait'] );

        return $pdf->download('Mes commandes IMLOD.pdf');

        // $client = Client::where('user_id',Auth::user()->id)->first();

        // $commandes = Commande::where('client_id',$client->id)->orderByDesc('created_at')->get();


        // return Excel::download(new exportCommandeClient($commandes) , 'Mes-commandes.xlsx');
    }
    public function exportDemandeDeLivraison(){
        $image = config("constantes.logo");;
        $client = Client::where('user_id',Auth::user()->id)->first();
        $livraisons = DemandeLivraison::where('client_id',$client->id)->orderByDesc('created_at')->get();

        $pdf = PDF::loadView('document.etatLivraison',['livraisons' => $livraisons,'image' => $image])
                    ->setOptions(['isHTML5ParseEnebled' => true, 'defaultPaperOrientation' => 'portait'] );

        return $pdf->download('Mes demandes de livraison IMLOD.pdf');

        // $client = Client::where('user_id',Auth::user()->id)->first();

        // $commandes = Commande::where('client_id',$client->id)->orderByDesc('created_at')->get();


        // return Excel::download(new exportCommandeClient($commandes) , 'Mes-commandes.xlsx');
    }
    public function exportLocation(){
        $image = config("constantes.logo");;
        $client = Client::where('user_id',Auth::user()->id)->first();
        $locations = Location::where('client_id',$client->id)->orderByDesc('created_at')->get();

        $pdf = PDF::loadView('document.etatLocation',['locations' => $locations,'image' => $image])
                    ->setOptions(['isHTML5ParseEnebled' => true, 'defaultPaperOrientation' => 'landscape '] );
        // var_dump($locations.'<br>');

        // die;
        return $pdf->stream();

        // $client = Client::where('user_id',Auth::user()->id)->first();

        // $commandes = Commande::where('client_id',$client->id)->orderByDesc('created_at')->get();


        // return Excel::download(new exportCommandeClient($commandes) , 'Mes-commandes.xlsx');
    }

    public function etatCommande(){
        $client = Client::where('user_id',Auth::user()->id)->first();

        $pdf = PDF::loadView('client.etatCommande',['client' => $client])
                    ->setOptions(['isHTML5ParseEnebled' => true, 'defaultPaperOrientation' => 'portait'] );

        return $pdf->stream();

        return view('client.etatCommande',[
            'client' => $client,
        ]);

    }

    public function detailDeLocation(Location $location){

        // dd($location);
        return view('client.detailDeLocation',[
            'location' => $location,
            'client' => Client::where('user_id',Auth::user()->id)->first(),
            'produits' => Produit::all(),
            'categories' => Categorie::all()
        ]);
    }

    public function techargerFacture($numero){
        $commande = Commande::where('numero',$numero)->first();

        $image = config("constantes.logo");
        // return view('document.factureCommande',[
        //     'commande' => $commande,
        //     'image' => $image
        // ]);
        // return 'nn';

        $config = Configuration::first();
        $client = $commande->client;
        $fneData = FneService::getDonneesFne(null, $client);

        $pdf = PDF::loadView('document.factureCommande', array_merge([
            'commande' => $commande, 'image' => $image, 'config' => $config,
            'enlevements' => collect(), 'facture' => new \App\Models\Facture(), 'livraison' => 0,
        ], $fneData))
            ->setOptions(['isHTML5ParseEnebled' => true, 'defaultPaperOrientation' => 'portait']);

        return $pdf->download($numero.'.pdf');

    }

    public function commandeValidee($numero){
        // dd($numero);

        $commande = Commande::where('numero',$numero)->first();
        $prixPerso = [];
        if (Auth::user()) {
            $client = Client::where('user_id', Auth::user()->id)->first();
            if ($client && $client->id) {
                $prixPerso = Produit::prixPersonnalisesPour($client);
            }
        }

        $image = config("constantes.logo");
        return view('client.commandeValidee',[
            'commande' => $commande,
            'image' => $image,
            'config' => Configuration::first(),
            'prixPerso' => $prixPerso,
        ]);




    }
    public function factureDevis($numero){
        $devis = Devis::where('numero',$numero)->first();

        $image = config("constantes.logo");;
        // return view('document.factureCommande',[
        //     'commande' => $commande,
        //     'image' => $image
        // ]);
        // return 'nn';

        $config = Configuration::first();
        $client = $devis->client;
        $fneData = FneService::getDonneesFneDevis($devis, $client);

        $pdf = PDF::loadView('document.factureDevis', array_merge([
            'devis' => $devis, 'image' => $image, 'config' => $config,
        ], $fneData))
            ->setOptions(['isHTML5ParseEnebled' => true, 'defaultPaperOrientation' => 'portait']);

        return $pdf->stream();

    }

    public function avisNote(Produit $produit,Request $request){

        // dd($produit);

        $client = Client::where('user_id',Auth::user()->id)->first();

        $data = [
            'avis' => $request->avis,
            'note' => $request->note,
            'produit_id' => $produit->id,
            'client_id' => $client->id
        ];

        $note = NoteProduit::create($data);
        return redirect()->route('client.produit.info',$produit)->with('rated','Merci d\'voir donné votre avis');
    }

    public function cart(){
        return view('client.cart');
    }

    public function recupererLesUnites(){
        $unites = UniteProduit::all();
        $libelles = [];
        foreach($unites as $unite){
            array_push($libelles, $unite->libelle);
        }

        return $libelles;

        return response()->json([
            'libelle' => $libelles
        ]);
    }

    public function demandeLivraison(){
        $this->viderSession();
        // $client = Client::find(2);
        // $be = Compte::find(2);
        // $cpt = Compte::find(1);
        // $client->comptes()->attach($cpt);
        // $client->comptes()->attach($be);
        // dd('demande delivraison');

        return view('client.demandeDeLivraison',[
            'villes' => Ville::all(),
            'produits' => Produit::all(),
            'client' => Auth::user() ? Client::where('user_id',Auth::user()->id)->first() : new Client,
            'categories' => Categorie::all(),
            'unites' => Uniteproduit::all(),
            'paiements' => ModePaiement::liste(),
            'types_livraison' => TypeLivraison::all()
        ]);
    }

    public function demandeClientATermePage(){
        return view('client.clientATerme',[
            'villes' => Ville::all(),
            'produits' => Produit::all(),
            'client' => (Auth::user()) ? Client::where('user_id',Auth::user()->id)->first() : new Client,
            'categories' => Categorie::all(),
            'client' => client::where('user_id', Auth::user()->id)->first()
        ]);
    }

    public function listeRetourProduit(){
        return view('admin.retourProduit');
    }

    public function recapLivraison(Request $request){
        // dd($request->all());
        // $km = round($request->km)/1000;
        $km = Help::distance($request->long, $request->lat, $request->long1, $request->lat1);
        // dd($request->km,$km1);


        if ($request->hasFile('fichier')) {

            $request->validate(['fichier' => 'required|mimes:pdf|max:2048'], [
                'fichier.mimes' => 'Le fichier doit être au format PDF',
                'fichier.max' => 'Le fichier ne doit pas dépasser 2 Mo',
            ]);

            // $destination = base_path('public/storage/productsImage');
            $destination = storage_path('app/public/temp_pdfs'); // disque 'public' réel (cohérent avec move vers lesBons + lecture)
            $nomPdf = 'bon'.'-'.Auth::user()->client->nom.'-'.Auth::user()->client->prenom.'-'. date('YmdHis') .'.pdf'; // extension forcée : jamais l'extension d'origine (anti-upload de .php exécutable)

            $request->file('fichier')->move($destination, $nomPdf);

            session()->put([
                'cheminFichier' => 'temp_pdfs/'.$nomPdf,
                'numero_bon_commande' => $request->numero_bon,
                'fichier' => $nomPdf
            ]);

        }

        $conf = Configuration::first();



        //dd($request);
        $couts = CoutLivraison::all();

        // $km = round($request->km);



        $prix = 0;
        // foreach ($couts as $cout) {
        //     switch ($km) {
        //         case ($km >= $cout->distance_min_km && $km<= $cout->distance_max_km):
        //             $prix = $km * $cout->prix_km;
        //             break;
        //     }
        // }


        $prix = $km * $conf->prixKm;

        if($prix < $conf->cout_livraison_min){
            $prix = $conf->cout_livraison_min;
        }


        $client = HELP::clientValide();
        $tva = Client::tva($client);
        $montantTva = $prix * $tva;

        //table produit
        $produits = [];
        foreach ($request->produit as $key => $value) {
            # code...
            $ligne = [
                'nom_produit' =>$value,
                'qte' => $request->qte[$key],
                'unite' => $request->unite[$key],
                'desc' => $request->description[$key]
            ];

            array_push($produits,$ligne);
        }

        //dd($produits);
        session()->put([

            'affichagePec' => $request->affichage,
            'villePec' => $request->ville,
            'longPec' => $request->long,
            'latPec' => $request->lat,

            'affichageDest' => $request->affichage1,
            'villeDest' => $request->ville1,
            'longDest' => $request->long1,
            'latDest' => $request->lat1,

            'km' => $km,

           /* 'nom_produit' => $request->produit,
            'qte' => $request->qte,
            'unite' => $request->unite,
            'description' => $request->description,*/
            'produits' => $produits,
            'montant_total' => $prix,
            'tva' => $tva,
            'montantTva' => $montantTva,
            'date' => $request->date,
            'paiement' => $request->paiement,
            'type_livraison' => $request->type_livraison
        ]);

        $mode = ModePaiement::find($request->paiement);

        // dd(session('qte'),session('unite'), session('poids'));



        return view('orders.recapLivraison',[
            'villes' => Ville::all(),
            'produits' => Produit::all(),
            'client' => $client,
            'categories' => Categorie::all(),
            'unites' => UniteProduit::all(),
            'mode' => $mode,

        ]);

        return view('client.recapLivraison',[
            'villes' => Ville::all(),
            'produits' => Produit::all(),
            'client' => Client::where('user_id',Auth::user()->id)->first(),
            'categories' => Categorie::all(),
            'unites' => UniteProduit::all(),
        ]);
    }

    public function valideDemande(){

        $couts = CoutLivraison::all();

        $km = round(session('km'))/1000;
        $prix = 0;
        $coutLivraison = 0;
        foreach ($couts as $cout) {
            switch ($km) {
                case ($km >= $cout->distance_min_km && $km<= $cout->distance_max_km):
                    $prix = $km * $cout->prix_km;
                    $coutLivraison = $cout->id;
                    break;
            }
        }

        // dd($coutLivraison);

        if($prix == 0){
            $prix = $km * 5000;
        }
        $paiement = ModePaiement::find(session('paiement'));
        // dd($paiement);
        $type_livraison = TypeLivraison::where('libelle',session('type_livraison'))->first();
        $unite = UniteProduit::where('abreviation', session('unite'))->first();

        // dd($mode, $type_livraison,$unite,$mode->id,$unite->id);

        // dd(session('poids'));
        $client = Client::where('user_id',Auth::user()->id)->first();


        // enregistrement de l'adresse de prise en charge
        $villePec = Ville::where('id',session('villePec'))->first();
        // dd($villePec,session('villePec'));

        $adressePec = [
            'client_id' => $client->id,
            'pays_id' => $villePec->pays->id,
            'ville_id' => $villePec->id,
            'longitude' => session('longPec'),
            'latitude' => session('latPec'),
            'affichage' =>session('affichagePec'),
        ];
        $a = AdresseLivraison::create($adressePec);

        $villeDest = Ville::where('id',session('villeDest'))->first();
        $adresseDest = [
            'client_id' => $client->id,
            'pays_id' => $villeDest->pays->id,
            'ville_id' => $villeDest->id,
            'longitude' => session('longDest'),
            'latitude' => session('latDest'),
            'affichage' =>session('affichageDest'),
        ];
        $b = AdresseLivraison::create($adresseDest);


        $livraison = [
            'numero' => uniqid(),
            'libelle' => session('libelle'),
            'description' => session('description'),
            'client_id' => $client->id,
            'adresse_livraison_pec_id' => $a->id,
            'adresse_livraison_dest_id' => $b->id,
            'montantTotal' => session('montant_total'),
            'date_livraison' => session('date'),
            'mode_paiement_id' => $paiement->id,
            'type_livraison_id' => $type_livraison->id,

        ];
        $c = DemandeLivraison::create($livraison);

        // dd($c);

        foreach (session('produits') as $key => $produit) {

            # code...
            $detailLivraison = [
                'nom_produit' => $produit['nom_produit'],
                'qte' => $produit['qte'],
                'unite_produit_id' => $produit['unite'],
                'demande_livraison_id' => $c->id,
                'etat_livraison' => 1,
                // 'cout_livraison_id' => $coutLivraison,
                'description' => $produit['desc'],

            ];
            $d = DetailLivraison::create($detailLivraison);
        }

        $tva = TvaCommande::create([
            'client_id' => $client->id,
            'commande_id' => $c->id,
            'montant' => session('montantTva'),
            'type_affaire' => Help::$LIVRAISON,
        ]);



               // ***************************************************************************************// ***************************************************************************************// ***************************************************************************************

                // $paiement = new Paiement();
                // $paiement->client_id = $client->id;
                // $paiement->devis_id = $devis->id;
                // $paiement->code = $c->numero;
                // $paiement->libelle = "Paiement commande de produit IMLOD";
                // $paiement->montant_total = $c->montantTotal;
                // $paiement->montant_restant = Auth::user()->client->client_a_terme == 1 ? $c->montantTotal + session('montantTva'): 0;
                // $paiement->statut = Help::$STATUT_INACTIF;
                // $paiement->service_id = $c->id;
                // $paiement->service = Help::$LIVRAISON;
                // $paiement->save();

                $ret = array();
                $retour = new \stdClass();
                $retour->code = null;
                $retour->message = null;
                // dd($client->client_a_terme == false, session('paiement'), $c->montantTotal + session('montantTva'));
                if ($client->client_a_terme == false && session('paiement') != 1 && $c->montantTotal + session('montantTva') < 2000000) {
                    $codePaiement = Help::getCommandeNo();
                    $nomPrenoms = $client->nom;
                    $arrNoms = explode(" ", $nomPrenoms);
                    $leNom = $client->nom;
                    $lePrenom = $client->prenom ?: $client->nom;
                    // if (count($arrNoms) >= 2) {
                    //     $leNom = $arrNoms[0];
                    //     $lePrenom = $arrNoms[1];
                    // } else {
                    //     $leNom = $arrNoms[0];
                    //     $lePrenom = $arrNoms[0];
                    // }
                    // dd(session('paiement'));

                    $ret = PaiementEnLigne::initierPaiement(
                        [
                            'code_paiement' => $codePaiement,
                            // 'credential_id' => "",
                            'nom_usager' => $leNom,
                            'prenom_usager' => $lePrenom,
                            'telephone' => $client->contact1,
                            'email' => $client->user->email,
                            'libelle_article' => "Paiement IMLOD",
                            'quantite' => 1,
                            'montant' => ceil($c->montantTotal + session('montantTva')),
                            'lib_order' => "Paiement commande de produit IMLOD",
                            'Url_Retour' => Help::urlPaiement(route('client.verifiePaiement', ['codePaiement' => $codePaiement])),
                            'Url_Callback' => Help::urlPaiement(route('callBackPaiement')),
                        ],
                        $codePaiement,
                        $codePaiement,
                        $client,
                        $c->montantTotal + session('montantTva'),
                        session('paiement'),
                        $c->id,
                        Help::$LIVRAISON
                    );

                    // dd($ret['message'], $ret['code']);


                    if ($ret['code'] == 200){
                        // session()->put('message', $ret['message']);

                        // $pourcentPromo = 0;
                        //     $montantPoint = 0;
                        //     if(session('reduction_id')){
                        //         $data['reduction'] = Reduction::find(session('reduction_id'));
                        //         $pourcentPromo = $data['reduction']->taux_reduction;
                        //     }

                        //     if(session('point_reduc')){
                        //         $point = Configuration::first();
                        //         $montantPoint = $point->montant_point * session('point_reduc');
                        // }

                        return Redirect::away($ret['message']);
                    } else {

                        // dd('annulé');

                        $retour->code = $ret['code'];
                        $retour->message = $ret['message'];
                    }
                }

                // ***************************************************************************************// ***************************************************************************************/   / ***************************************************************************************


        session()->forget([

            'affichagePec',
            'villePec',
            'longPec',
            'latPec',

            'affichageDest',
            'villeDest',
            'longDest',
            'latDest',
            'km',
            'produits',
            'montant_total',
            'date',
            'paiement',
            'type_livraison'
        ]);



        return redirect()->route('client.livraisonValide',$c)->with('success','Votre demande de livraison a bien été envoyé');
    }

    public function livraisonValide(DemandeLivraison $livraison){

        // dd($livraison);
        return view('orders.livraisonValide',[
            'livraison' => $livraison,
            'client' => Client::where('user_id',Auth::user()->id)->first(),
        ]);
    }

    public function demandeClientATerme(Request $request){
        $request->validate([
            'objet'       => 'required|string|max:255',
            'description' => 'required|string|min:20',
            'documents.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
        ], [
            'description.min' => 'Veuillez détailler votre demande (au moins 20 caractères).',
            'documents.*.mimes' => 'Les documents doivent être au format PDF, JPG, PNG, DOC ou DOCX.',
            'documents.*.max' => 'Chaque document ne doit pas dépasser 5 Mo.',
        ]);

        $client = Client::where('user_id',Auth::user()->id)->first();

        $demande = DemandeCompteClientATerme::where('client_id',$client->id)->orderByDesc('id')->first();

        if($demande){
            if($demande->approuve == 0){
                return redirect()->route('client.demandeClientATermePage')->with('info','Vous avez déjà une demande en cours...');
            }
            if($demande->approuve == 1){
                return redirect()->route('client.demandeClientATermePage')->with('info','Déjà client à terme');
            }
            // approuve == 2 (rejetée) → autorisé à refaire une demande
        }

        // Upload des documents (clé logique → chemin stocké)
        $docsPaths = [];
        if ($request->hasFile('documents')) {
            foreach ($request->file('documents') as $key => $file) {
                if ($file && $file->isValid()) {
                    $docsPaths[$key] = $file->store('demandes_client_terme', 'public');
                }
            }
        }

        DemandeCompteClientATerme::create([
            'objet'           => $request->objet,
            'description'     => $request->description,
            'documents_path'  => !empty($docsPaths) ? $docsPaths : null,
            'client_id'       => $client->id,
            'user_id'         => Auth::user()->id,
            'approuve'        => 0,
        ]);

        return redirect()->route('client.demandeClientATermePage')
            ->with('success','Votre demande a été envoyée. Vous recevrez un email de confirmation après examen par notre équipe.');
    }

    public function modifierAdresseLivraison(Commande $commande){

        return view('client.modificationAdresseLivraison',[
            'commande' => $commande,
            'villes' => Ville::all(),
            'produits' => Produit::all(),
            'categories' => Categorie::all(),
            'client' => Client::where('user_id',Auth::user()->id)->first()
        ]);

    }

    public function adresseLivraisonModifiee(Commande $commande, Request $request){

        // dd($request->all(), $commande,$commande->adresseLivraison);

        $ville = Ville::find($request->ville);
        $client = Client::where('user_id',Auth::user()->id)->first();

        $commande->adresseLivraison->update([
            'client_id' => $client->id,
            'pays_id' => $ville->pays_id,
            'ville_id' => $ville->id,
            'affichage' => $request->affichage,
            'longitude' => $request->long,
            'latitude' => $request->lat
        ]);

        // dd($request->affichage,$commande->adresseLivraison->affichage);

        return redirect()->route('client.validationLivraisonPage',$commande)->with('success','Modification effectuée');

    }

    public function retourProduitPage(){
        $client = Client::where('user_id',Auth::user()->id)->first();

        // dd($client->id);

        return view('client.retourProduit',[
            'commandes' => Commande::where('client_id',$client->id)->get(),
            // 'villes' => Ville::all(),
            'produits' => Produit::all(),
            'client' => (Auth::user()) ? Client::where('user_id',Auth::user()->id)->first() : new Client,
            'categories' => Categorie::all()
        ]);
    }

    public function motifPage(DetailCommande $detail){

        return view('client.motifRetour',[

            'produits' => Produit::all(),
            'client' => (Auth::user()) ? Client::where('user_id',Auth::user()->id)->first() : new Client,
            'categories' => Categorie::all(),
            'detail' => $detail
        ]);
    }

    public function motif(DetailCommande $detail, Request $request){

        $client = Client::where('user_id',Auth::user()->id)->first();
        $dataRetour = [
            'motif' => $request->motif,
            'client_id' => $client->id,
            'detail_commande_id' => $detail->id
        ];
        // dd($dataRetour);

        RetourProduit::create($dataRetour);
        return redirect()->route('client.retourProduitPage')->with('success','Votre demande a bien été envoyée. Vous recevrez un email pour la confirmation de votre requête');


    }

    public function demandeAnnulationCommande($numero){
        // dd(Commande::where('numero',$numero)->first(),$numero);

        $commande = Commande::where('numero',$numero)->first();

        // dd($commande->etat_commande);

        return view('client.annulationCommande',[
            'commande' => Commande::where('numero',$numero)->first(),
            'produits' => Produit::all(),
            'client' => (Auth::user()) ? Client::where('user_id',Auth::user()->id)->first() : new Client,
            'categories' => Categorie::all(),

        ]);
    }

    public function demandeAnnulationCommandeTraitement($numero, Request $request){
        // dd($numero,$request->motif);
        $commande = Commande::where('numero',$numero)->first();

        // Anti-doublon : une seule demande en attente par commande.
        $existante = DemandeAnnulationCommande::where('commande_id', $commande->id)
            ->where('type_affaire', 'VENTE')
            ->where('est_traite', false)
            ->exists();
        if ($existante) {
            return redirect()->route('client.monCompte')
                ->with('info', "Vous avez déjà une demande d'annulation en cours pour cette commande.");
        }

        $demande = DemandeAnnulationCommande::create([
            'client_id' => $commande->client_id,
            'user_id' => Auth::user()->id, // colonne NOT NULL
            'commande_id' =>$commande->id,
            'motif' => $request->motif,
            'est_traite' => false,
            'type_affaire' => 'VENTE',
            'statut' => 1,
        ]);

        return redirect()->route('client.monCompte')->with('success','Votre demande à bien été envoyé');

    }

    public function blog(){

        return view('client.blogPage',[
            'produits' => Produit::all(),
            'client' => Auth::user() ? Client::where('user_id',Auth::user()->id)->first() : new Client,
            'categories' => Categorie::all(),
            'commande' => Commande::first(),
            'blogs' => blog::where('publie',1)->orderBy('created_at','desc')->get()
        ]);
    }

    public function detailBlog($id){

        return view('client.blogDetail',[
            'blog' => blog::find($id),
            'produits' => Produit::all(),
            'client' => Auth::user() ? Client::where('user_id',Auth::user()->id)->first() : new Client,
            'categories' => Categorie::all(),
        ]);
    }

    public function commentaireEtNote(Request $request,$id){

        $commentaire = blog_commentaire::create([
            'note' => $request->note,
            'commentaire' => $request->commentaire,
            'client_id' => Client::where('user_id',Auth::user()->id)->value('id'),
            'blog_id' => $id
        ]);

        return redirect()->back()->with('success','Commentaire bien envoyé');
    }


    public function wishList(){

        $client = Client::where('user_id',Auth::user()->id)->first();
        $whishList = like::where('client_id',$client->id)->get();
        $prixPerso = [];
        if ($client && $client->id) {
            $prixPerso = Produit::prixPersonnalisesPour($client);
        }

        return view('client.wishList',[
            'produits' => Produit::all(),
            'client' => $client,
            'categories' => Categorie::all(),
            'prixPerso' => $prixPerso,
        ]);
    }

    public function modeDePaiement(Request $request){



        //dd($request->all());

        session()->forget([
            'remise'
        ]);

        $client = Client::where('user_id',Auth::user()->id)->first();

        $this->infoLivraison($request, $client);

        $total = Cart::total();

        $prixPerso = [];
        if ($client && $client->id) {
            $prixPerso = Produit::prixPersonnalisesPour($client);
        }

        return view('client.modePaiement',[
            // 'devis' => $devis,
            'produits' => Produit::all(),
            'pays' => Pays::all(),
            'villes' => Ville::all(),
            'client' => Auth::user() ?  Client::where('user_id',Auth::user()->id)->first(): new Client,
            'categories' => Categorie::all(),
            'modes'=> ModePaiement::liste(),
            'total' => $total,
            'typeLivraison' => TypeLivraison::all(),
            'conf' => Configuration::first(),
            'tva' => Client::tva($client),
            'prixPerso' => $prixPerso,
        ]);
    }

    public function infoLivraison($request,$client){
        $arr = [];
        $conf = Configuration::first();

        if($request->onMeLivre == 'oui'){
            $request->validate([
                'ville' => 'required',
                'infoSup' => 'required',
                'long' => 'required',
                'lat' => 'required',
                'region' => 'required'
            ],[
                'ville.required' => 'Veuillez choisir une ville',
                'infoSup.required' => 'Veuillez entrer une adresse',
                'long.required' => 'veuillez selectionner une longitude sur la carte',
                'lat.required' => 'veuiller selectionner une latitude sur la carte',
                'region.required' => 'Veuillez choisir une région',
            ]);

            $livraison = Help::coutLivraison($request->long, $request->lat, $request->region);

            /* array_push($arr,[
                'ville' => $request->ville,
                'infoSup' => $request->infoSup,
                'long' =>$request->long ,
                'lat' =>$request->lat,
                'montantHT' => Cart::total(),
                'tva' => Cart::total() * Client::tva($client),
                'montantTTC' => Cart::total() + (Cart::total()* Client::tva($client)),
                'km' => $livraison['km'],
                'cout_livraison' =>$livraison['cout_livraison'],

            ]);*/

        }else{

            /* array_push($arr,[
                'ville' => null,
                'infoSup' => null,
                'long' =>null ,
                'lat' =>null,
                'montantHT' => Cart::total(),
                'tva' => Cart::total()* Client::tva($client),
                'montantTTC' => Cart::total() + (Cart::total()* Client::tva($client)),
                'km' => null,
                'cout_livraison' =>null,
            ]);*/
            $livraison = [
                'km' => 0,
                'cout_livraison' => 0,
            ];
            foreach (Cart::content() as $item) {

                // Mise à jour du panier avec le coût
                $options = $item->options->toArray(); // garder les autres options
                $options['cout_livraison'] = 0;

                Cart::update($item->rowId, [
                    'options' => $options,
                ]);


            }

        }

        // dd($arr);
        array_push($arr, [
            'ville' => $request->ville,
            'infoSup' => $request->infoSup,
            'long' => $request->long,
            'lat' => $request->lat,
            'montantHT' => Cart::total(),
            'tva' => Cart::total() * Client::tva($client),
            'montantTTC' => Cart::total() + (Cart::total() * Client::tva($client)),
            'km' => $livraison['km'],
            'cout_livraison' => $livraison['cout_livraison'],
            'estLivrable' => $request->onMeLivre,
        ]);

        session()->put($arr);


        if(isset($request->dateDebutLocation) && isset($request->dateFinLocation)){

            $nbreJour = Carbon::parse($request->dateDebutLocation)->diffInDays(Carbon::parse($request->dateFinLocation));

            session()->put([
                'dateDebutLocation' => $request->dateDebutLocation,
                'dateFinLocation' => $request->dateFinLocation,
                'nbre_jour'=> $nbreJour
            ]);
        }
    }

    public function devisModeDePaiement(Devis $devis, Request $request){
        // dd($devis);
        session()->put([
            'devis' => $devis->id
        ]);

        session()->put([
            'ville' => $request->ville,
            'infoSup' => $request->affichage,
            'long' =>$request->long ,
            'lat' =>$request->lat,
        ]);

        if(isset($request->dateDebutLocation) && isset($request->dateFinLocation)){
            session()->put([
                'dateDebutLocation' => $request->dateDebutLocation,
                'dateFinLocation' => $request->dateFinLocation,
            ]);
        }

        $client = Client::where('user_id',Auth::user()->id)->first();
        $prixPerso = [];
        if ($client && $client->id) {
            $prixPerso = Produit::prixPersonnalisesPour($client);
        }

        return view('client.modePaiement',[
            // 'devis' => $devis,
            'produits' => Produit::all(),
            'pays' => Pays::all(),
            'villes' => Ville::all(),
            'client' => $client,
            'categories' => Categorie::all(),
            'modes'=> ModePaiement::liste(),
            'total' => $devis->montant,
            'devis' => $devis,
            'typeLivraison' => TypeLivraison::orderBy('libelle')->get(),
            'conf' => Configuration::first(),
            'tva' => Client::tva($client),
            'prixPerso' => $prixPerso,
        ]);
    }

    public function ValideProduit(Commande $commande, Produit $produit){

        $item = DetailCommande::where('commande_id',$commande->id)->where('produit_id',$produit->id)->first();
        // dd($item);
        // dd($item);

        $item -> update([
            'statut' => 3
        ]);

        $details = DetailCommande::where('commande_id',$commande->id)->get();

        $cpteProduitLivree = 0;
        // dd($details);
        foreach($details as $detail){

            if($detail->statut == 3){
                // dd('bloque');
                $cpteProduitLivree++;

            }
        }



        if($cpteProduitLivree == $commande->produits->count()){

            $commande->update([
                'etat_commande' => 3
            ]);

        }



        return redirect()->route('client.validationLivraisonPageGet',$commande->numero)->with('livree','Livraison vallidée');
    }

    public function like($id){

        if(Auth::user()){

            $client = Client::where('user_id',Auth::user()->id)->first();
            // dd('je like');
            $like = like::where('client_id',$client->id)->where('produit_id',$id)->first();


            if($like){

                if($like->deleted_at == null){
                    $like->update([

                        'deleted_at' => date('Y-m-d H:i:s')
                    ]);
                    $rep = 'rétiré';
                }else{
                    $like->update([

                        'deleted_at' => null
                    ]);
                    $rep = 'ajouté à nouveau';
                }


            }else{

                $like = like::create([
                    'produit_id' => $id,
                    'client_id' => Client::where('user_id',Auth::user()->id)->value('id')
                ]);

                $rep = 'ajouté';
            }

            return response()->json([
                // Exclure les souhaits "retirés" (deleted_at != null) pour rester
                // cohérent avec le compteur affiché au chargement (header.blade.php).
                'count' => like::where('client_id',$client->id)->whereNull('deleted_at')->count(),
                'auth' => true,
                'rep' => 'Produit '.$rep
            ]);
        }else{
            return response()->json([
                'auth' => false,
                'rep' => 'Vous devez vous connecter pour ajouter un produit à votre liste de souhait'
            ]);
        }

    }

    public function likePlus($id){
        $client = Client::where('user_id',Auth::user()->id)->first();
        // dd('je like');
        $like = like::where('client_id',$client->id)->where('produit_id',$id)->first();




            if($like->deleted_at == null){
                $like->update([
                    'deleted_at' => date('Y-m-d H:i:s')
                ]);
            }

        return redirect()->route('client.wishList')->with('success','Action effectuée avec succès');
    }

    public function index(){
        $this->viderSession();

        $client = (Auth::user()) ? Client::where('user_id',Auth::user()->id)->first() : new Client;
        $prixPerso = [];
        if ($client && $client->id) {
            $prixPerso = Produit::prixPersonnalisesPour($client);
        }

        $produits = Produit::where('type_affaire','VENTE')->where('statut', 1)->paginate(12)->onEachSide(2);

        return view('client.index',[
            'categories' => Categorie::all(),
            'produits' => $produits,
            'client' => $client,
            'prixPerso' => $prixPerso,
        ]);
    }

    public function ValidationLocationPage(){

        return view('client.validationLocation');

    }

    public function ajouterPanier($produit, Request $request){

        $produit = Produit::find($produit);
        // dd($produit,$produit->UniteProduit);



        if(Cart::content()->isNotEmpty() ){
            foreach(Cart::content() as $product){
                if($product->options->type_affaire != $produit->type_affaire){
                    // dd(Cart::content(),$produit->type_affaire);
                    Cart::destroy();
                    break;
                }
            }
            // dd('pas vide pareil');
        }
        // dd('vide');

        // dd($produit);
            // dd($produit->image->image);
        foreach($produit->image as $image){
            $images = $image;
        }


        // $userId = $request->_token;
        // $image = $produit->imageproduit->image;
        // dd($image);

        // On verifie si le produit selectionné existe déjà dans la selection courante
        $produitExistant = Cart::search(function ($cartItem, $rowId) use ($produit) {
            return $cartItem->id === $produit->id ;
        });

        if(!$produitExistant->isEmpty()){
            return $count = -1;
            // return redirect()->back()->with('deja','Le produit a déjà été ajouté') ;
        }

        // Déterminer le prix (personnalisé ou normal) — source de vérité unique
        $prix = $produit->prixPour(Produit::clientCourant());

        $quantite = (int) $request->input('quantite', 1);
        if ($quantite < 1) {
            $quantite = 1;
        }

        // Ajout du produit à la selection
        Cart::add($produit->id, $produit->nom, $quantite, $prix, [
            'image' => $images->image,
            'note' => $produit->meilleur_note,
            'ville' => '',
            'infoSup' => '',
            'type' => '',
            'cout_livraison' => 0,
            'unite' => $produit->UniteProduit->abreviation,
            'type_affaire' => $produit->type_affaire,
            'prix_fournisseur' => $produit->prix_fournisseur,

            ])->associate('App\Models\Produit');

        return $count = Cart::content()->count();

        // dd($Product);
    }

    public function location(){
        $this->viderSession();

        $client = (Auth::user()) ? Client::where('user_id',Auth::user()->id)->first() : new Client;
        $prixPerso = [];
        if ($client && $client->id) {
            $prixPerso = Produit::prixPersonnalisesPour($client);
        }

        $produits = Produit::where('type_affaire','LOCATION')->where('statut', 1)->avecFournisseur()->get();
        if (!empty($prixPerso)) {
            foreach ($produits as $produit) {
                if (isset($prixPerso[$produit->id])) {
                    $produit->prix_reduction = $produit->prix_moyen;
                    $produit->prix_moyen = $prixPerso[$produit->id];
                }
            }
        }

        return view('client.locationPage',[
            'categories' => Categorie::all(),
            'produits' => $produits,
            'client' => $client,
            'prixPerso' => $prixPerso,
        ]);
    }

    public function choixDateProduitLocation(Request $request){
        // dd($request->all());

        $client= Auth::user()->client;

        $arr = [];
        $conf = Configuration::first();

        if($request->onMeLivre == 'oui'){

            $request->validate([
                'ville' => 'required',
                'infoSup' => 'required',
                'long' => 'required',
                'lat' => 'required',
                'region' => 'required'
            ],[
                'ville.required' => 'Veuillez choisir une ville',
                'infoSup.required' => 'Veuillez entrer une adresse',
                'long.required' => 'veuillez selectionner une longitude sur la carte',
                'lat.required' => 'veuiller selectionner une latitude sur la carte',
                'region.required' => 'Veuillez choisir une région',
            ]);

            $livraison = Help::coutLivraison($request->long, $request->lat, $request->region);
            // session()->put([
            //     'km' => $livraison['km'],
            //     'cout_livraison' =>$livraison['cout_livraison'],
            // ]);

            array_push($arr,[
                'ville' => $request->ville,
                'infoSup' => $request->infoSup,
                'long' =>$request->long ,
                'lat' =>$request->lat,
                'km' => $livraison['km'],
                'cout_livraison' =>$livraison['cout_livraison'],
                'tva' => Cart::total() * Client::tva($client),
            ]);
        }else{

            array_push($arr,[
                'ville' => null,
                'infoSup' => null,
                'long' =>null ,
                'lat' =>null,
                'km' => null,
                'cout_livraison' =>null,
                'tva' => Cart::total() * Client::tva($client),
            ]);

        }

        // dd(empty($livraison));


        // dd($arr);
        session()->put($arr);
        // dd(session()->all());

        // $dataPromo = $this->reductionAppliquee();

        // dd($dataPromo);
        return view('client.choixDateProduitLocation',[
            'produits' => Produit::all(),
            'client' => (Auth::user())? Client::where('user_id',Auth::user()->id)->first() : new Client,
            'categories' => Categorie::all(),
            'total' => Cart::total(),
            // 'reduc' => $dataPromo['config'],
            // 'montantPoint' => $dataPromo['montantPoint'],
            // 'montantPromo' => $dataPromo['montantPromo'],
            'conf' => Configuration::first(),
            'livraison' => empty($livraison) ? null : $livraison,
            'tva' => Client::tva($client),
        ]);

       return view('client.choixDateProduitLocation');
    }

    public function choixDateProduitLocationTraitement(Request $request){

        // dd(today()->format('Y-m-d') > '2025-04-27');

        $datesDebut = [];
        $datesFin = [];
        $nombreDeJour = [];


        foreach($request->fin as $key => $date){

            if($request->debut[$key] < today()->format('Y-m-d')){
                // return redirect(route();
                return back()->with('errorDebut','La date de début doit être supérieure à la date d\'aujourd\'hui');
            }

            if($request->fin[$key] < $request->debut[$key] || $request->fin[$key] < today()->format('Y-m-d')){
                return back()->with
                ('errorFin','La date de fin doit être supérieure à la date de début ou à la date d\'aujourd\'hui');
            }


            $nbre = Carbon::parse($request->debut[$key])->diffInDays(Carbon::parse($request->fin[$key]));

            array_push($datesDebut,$request->debut[$key]);
            array_push($datesFin,$request->fin[$key]);
            array_push($nombreDeJour,$nbre+1);


            // dd($request->debut[$key] - $datesFin,$request->fin[$key]);

        }

        // $dataPromo = $this->reductionAppliquee();

        session()->put([
            'debuts' => $datesDebut,
            'fins' => $datesFin,
            'nbre_jour' => $nombreDeJour,

        ]);
        $total = 0;

        $i = 0;

        foreach (Cart::content() as $key => $produit ){

            // Utilise le prix capturé dans le panier (déjà personnalisé pour ce client si applicable)
            // au lieu de relire prix_moyen brut, sinon le prix personnalisé est ignoré pour les locations.
            $total += $produit->price * $produit->qty * session('nbre_jour')[$i];

            $i++;
        }
        // dd();

        // $dataPromo = $this->reductionAppliqueeLocation();

        session()->put([
            'totalLocation' => $total,
            'montantTotal' => $total + ($total * Client::tva(Auth::user()->client)),
        ]);

        // Source de vérité unique du montant à payer : session('0')['montantTTC'].
        // On l'initialise ici (base, sans remise) ; appliquerCodePromo /
        // AppliquerPointDeReduction le recalculent si une remise est appliquée.
        $taux   = Client::tva(Auth::user()->client);
        $remise = round(session('remise') ?? 0);
        $htNet  = max(0, $total - $remise);
        $tab = session('0') ?: [];
        $tab['tva']        = round($htNet * $taux);
        $tab['montantTTC'] = round($htNet + $tab['tva'] + ($tab['cout_livraison'] ?? 0));
        session(['0' => $tab]);


        // if(session('reduction_id')){
        //     $total = $total - $dataPromo['montantPromo'];
        // }
        // if(session('point_reduc')){
        //     $total = $total - $dataPromo['montantPoint'];
        // }

        $client = Auth::user()->client;

        // dd($dataPromo['montantPromo'],$dataPromo['montantPoint']);

        // return redirect()->route('client.modeDePaiement');
        return view('client.modePaiement',[
            'produits' => Produit::all(),
            'client' => (Auth::user())? Client::where('user_id',Auth::user()->id)->first() : new Client,
            'categories' => Categorie::all(),
            'total' => $total,
            // 'reduc' => $dataPromo['config'],
            // 'montantPoint' => $dataPromo['montantPoint'],
            // 'montantPromo' => $dataPromo['montantPromo'],
            'modes' => ModePaiement::liste(),
            'typeLivraison' => TypeLivraison::all(),
            'conf' => Configuration::first(),
            'tva' => Client::tva($client),
        ]);

    }

    public function appliquerCodePromo(Request $request){
        // dd('ok');
        // $total = Cart::total();
        $devis = new Devis;
        if($request->has('devis_id')){
            $devis = Devis::find($request->devis_id);
        }
        session()->put([
                'remise' => 0
            ]);
        $montantAEnlever = 0;
        $cout_livraison = 0;
        $tva = 0;

        $reduction = Reduction::where('code',$request->code)->first();


        if($reduction){
            if($reduction->est_utilise == 1){
                return response()->json(['statut' => -1, 'message' => 'Ce code promo a déjà été utilisé.']);
            }

            // Code désactivé ou hors période de validité => non applicable.
            $aujourdhui = date('Y-m-d');
            if (isset($reduction->statut) && $reduction->statut == 0) {
                return response()->json(['statut' => -1, 'message' => "Ce code promo n'est plus actif."]);
            }
            if (!empty($reduction->debut) && $aujourdhui < \Carbon\Carbon::parse($reduction->debut)->format('Y-m-d')) {
                return response()->json(['statut' => -1, 'message' => "Ce code promo n'est pas encore valide (à partir du " . \Carbon\Carbon::parse($reduction->debut)->format('d-m-Y') . ').']);
            }
            if (!empty($reduction->fin) && $aujourdhui > \Carbon\Carbon::parse($reduction->fin)->format('Y-m-d')) {
                return response()->json(['statut' => -1, 'message' => 'Ce code promo a expiré le ' . \Carbon\Carbon::parse($reduction->fin)->format('d-m-Y') . '.']);
            }

            if(session('0') && session('0')['cout_livraison']){
                $cout_livraison = session('0')['cout_livraison'];
            }

            if(session('0') && session('0')['tva']){
                $tva = session('0')['tva'];
            }

            if($devis->id){
                $cout_livraison = $devis->cout_livraison;
                $tva = $devis->tva;
            }

            session()->put([
                'reduction_id' => $reduction->id
            ]);


            // $total = Cart::total() - (Cart::total() * $reduction->taux_reduction)/100;
            // $total = Cart::total() + $cout_livraison + $tva;
            // Base HT de la remise :
            //  - flux devis : le panier est vide → montant du devis ;
            //  - flux location : total location (prix × qté × nombre de jours), stocké
            //    dans session('totalLocation') — Cart::total() ignorerait les jours ;
            //  - flux vente : total du panier.
            $total = $devis->id
                ? (float) $devis->montant
                : (session('type') == 'location' ? (float) session('totalLocation') : Cart::total());

            $montantAEnlever = $total * ($reduction->taux_reduction/100);

            if(session('point_reduc')){
                $config = Configuration::first();

                $valeurPoints = session('point_reduc') * $config->montant_point;

                // Remise totale = remise promo + valeur des points.
                $montantAEnlever += $valeurPoints;
            }

            // Plafond : la remise ne peut JAMAIS dépasser le HT marchandise, sinon
            // HT net / TVA / TTC deviendraient négatifs.
            if ($montantAEnlever > $total) {
                $montantAEnlever = $total;
            }

            $montantTotal = $total - $montantAEnlever;


            session(['remise' => session('remise') + $montantAEnlever]);

            // puisque certaines informations sont dans un tableau à deux dimensions on peut la les modifier directement
            // on recupère d'abord le tableau, on modifie la valeur puis on remet à jour la session
            // TVA calculée sur le HT net (après remise), comme avant l'application du promo.
            $tva = $montantTotal * Client::tva(Auth::user()->client);
            $data = session('0') ?: [];
            $data["tva"] = $tva;
            $data['montantTTC'] = $montantTotal + $cout_livraison + $tva;
            session()->put([
                '0' => $data
            ]);

            session()->put([
                'test' => session('remise') * Client::tva(Auth::user()->client)
            ]);
            // dd($total);
            // return redirect()->route('client.monPanier')->with('success','Panier mis à jour');
            return response()->json([
                'statut' => 1,
                'total' => session('0')['montantTTC'],
                'tva' => session('0')['tva'],
                'montantEnleve' => $montantAEnlever,
            ]);
        }else{
            return response()->json(['statut' => -1, 'message' => 'Ce code promo est invalide.']);
        }

    }

    public function AppliquerPointDeReduction(Request $request){
        session()->put([
                'remise' => 0
            ]);
        $client = Client::where('user_id',Auth::user()->id)->first();

        // Validation : nombre de points entier et strictement positif (un point
        // négatif AUGMENTERAIT le total).
        $points = (int) $request->point;
        if ($points < 1) {
            return response()->json([
                'statut' => -1,
                'message' => 'Veuillez saisir un nombre de points valide.',
            ]);
        }

        if($points > $client->point){
            // return redirect()->route('client.monPanier')->with('failPoint','Vous n\'avez pas ce nombre de point');
            return response()->json([
                'statut' => -1,
                'message' => "Vous n'avez pas suffisamment de points. Solde disponible : " . (int) $client->point . ' point(s).',
            ]);
        }

        // Base HT marchandise (identique au flux code promo) :
        // montant du devis si on paie un devis, sinon le total du panier.
        $devis = $request->has('devis_id') ? Devis::find($request->devis_id) : null;
        // Flux location : base = total location (prix × qté × jours) via session('totalLocation'),
        // sinon Cart::total() ignorerait le nombre de jours.
        $total = ($devis && $devis->id)
            ? (float) $devis->montant
            : (session('type') == 'location' ? (float) session('totalLocation') : Cart::total());

        $data = session('0') ?: [];
        $cout_livraison = ($devis && $devis->id)
            ? $devis->cout_livraison
            : ($data['cout_livraison'] ?? 0);

        $montantAEnlever = 0;

        // Remise promo éventuelle : pourcentage appliqué sur le HT marchandise
        // (même base que appliquerCodePromo, pour un résultat identique quel que
        // soit l'ordre d'application promo/points).
        if(session('reduction_id')){
            $reduction = Reduction::find(session('reduction_id'));
            if($reduction){
                $montantAEnlever += $total * ($reduction->taux_reduction/100);
            }
        }

        $config = Configuration::first();

        // Valeur des points fidélité.
        $montantAEnlever += $points * $config->montant_point;

        // Plafond : la remise (promo + points) ne peut dépasser le HT marchandise,
        // sinon HT net / TVA / TTC deviendraient négatifs.
        if ($montantAEnlever > $total) {
            $montantAEnlever = $total;
        }

        // HT net après toutes les remises.
        $montantTotal = $total - $montantAEnlever;

        session(['remise' => $montantAEnlever]);
        session()->put([
            'point_reduc' => $points,
            // 'reduc' => $config
        ]);

        // TVA sur le HT net + TTC = HT net + livraison + TVA (cohérent avec le code promo).
        $tva = $montantTotal * Client::tva($client);
        $data['tva'] = $tva;
        $data['montantTTC'] = $montantTotal + $cout_livraison + $tva;
        session()->put([
            '0' => $data
        ]);

        return response()->json([
            'statut' => 1,
            'total' => $data['montantTTC'],
            'tva' => $tva,
            'montantEnleve' => $montantAEnlever
        ]);

    }

    public function monPanier(){
        $this->viderSession();

        foreach(Cart::content() as $produit){

            if($produit->options->type_affaire == "LOCATION"){
                // dd('ok');
                return redirect()->route('client.panierLocation');
            }
        }


        if(session('devisAModifier')){
            $devisEnCours = Devis::find(session('devisAModifier'));
            // Auto-récupération : on ne renvoie vers l'édition du devis QUE s'il existe
            // encore ET n'est pas déjà converti en commande (statut 2). Sinon le drapeau
            // est périmé (devis terminé/supprimé) -> on le nettoie et on affiche le
            // panier normalement, au lieu de bloquer le client sur ce devis.
            if($devisEnCours && $devisEnCours->statut != 2){
                return redirect()->route('devis.editDevis', $devisEnCours->id);
            }
            session()->forget(['devisAModifier', 'niveauModifDevis']);
        }

        $client = HELP::clientValide() ;

        return view('client.monPanier',[
            'produits' => Produit::all(),
            'client' => $client,
            'categories' => Categorie::all(),
            'totalCommande' => Cart::total(),
            'conf' => Configuration::first(),
            'tva' => Client::tva($client),
            // 'reduc' => $dataPromo['config'],
            // 'montantPoint' => $dataPromo['montantPoint'],
            // 'montantPromo' => $dataPromo['montantPromo']

        ]);
    }

    public function panierLocation(){
        $this->viderSession();
        $total = Cart::total();

        if(Auth::user()){
            // dd('connecté');
            $client = Client::where('user_id',Auth::user()->id)->first();
            $tva = Client::tva($client);
        }else{
            // dd('non connecté');
            $client = new Client;
            $tva = Configuration::first()->tva / 100;
        }

        // dd($tva);





        return view('client.panierLocation',[
            'produits' => Produit::all(),
            'client' => (Auth::user())? Client::where('user_id',Auth::user()->id)->first() : new Client,
            'categories' => Categorie::all(),
            'total' => $total,
            'totalCommande' => Cart::total(),
            'tva' => $tva

        ]);
    }

    public function panierPage(){

        return view('client.panier');
    }

    public function panierPages(){

        return view('client.paniier');
    }

    public function incremente($rowId){
        Cart::update($rowId,Cart::get($rowId)->qty+1 );
        if(Auth::user()){

            return redirect()->route('client.paniier')->with('ok','La quantité de l\'article a été mis à jour');
        }else{

            return redirect()->route('client.panier')->with('ok','La quantité de l\'article a été mis à jour');
        }
    }

    public function listeDevis(){
        $client = Client::where('user_id',Auth::user()->id)->first();
        $prixPerso = [];
        if ($client && $client->id) {
            $prixPerso = Produit::prixPersonnalisesPour($client);
        }
        return view('client.listDevis',[
            'produits' => Produit::all(),
            'devis' => Devis::where('client_id',$client->id)->where('statut',1)->get(),
            'client' => $client,
            'categories' => Categorie::all(),
            'prixPerso' => $prixPerso,
        ]);

    }

    public function detailCommande(Commande $commande){
        $client = Client::where('user_id',Auth::user()->id)->first();
        $prixPerso = [];
        if ($client && $client->id) {
            $prixPerso = Produit::prixPersonnalisesPour($client);
        }
        return view('client.listCommande',[
            'produits' => Produit::all(),
            'commande' => $commande,
            'client' => $client,
            'categories' => Categorie::all(),
            'prixPerso' => $prixPerso,
        ]);
    }

    public function listeDesPaiements($paye){
        $client = Auth::user()->client;



        // if($paye == 1){
            $paiements = Paiement::where('client_id',$client->id)->where('statut',$paye)->get();
            // $lesPaiements = collect();
            // $i = 1;
            // $paiements = Paiement::where('client_id',$client->id)->where('statut',1)->get();

            // foreach($paiements as $p){

            //     $totalLignePaiement = $p->lignePaiement->where('statut',1)->sum('montant');

            //     if($totalLignePaiement == $p->montant_total){
            //         $lesPaiements->put($i,$p);
            //         $i++;
            //     }
            // }

        // }else{
        //     $paiements = Paiement::where('client_id',$client->id)->where('statut',2)->get();
        // }
        return response()->json($paiements);

    }

    public function afficherMontant(Request $request){

        // dd($request->all());

        $user = Auth::user();

        $paiements = Paiement::find($request->paiements);
        $client = $paiements->first()->client;
        // dd($paiements);
        $total = 0;

        foreach($paiements as $paiement){
            $total += $paiement->montant_total;
        }


        $codePaiement = $paiements->first()->code;


        $retour = new \stdClass();
        $retour->code = null;
        $retour->message = null;
        $ret = PaiementEnLigne::initierPaiement(
            [
                'code_paiement' => $codePaiement, //WARNING : A VERIFIER
                // 'credential_id' => "",
                'nom_usager' => $client->nom,
                'prenom_usager' => $client->prenom ?: $client->nom,
                'telephone' => $client->contact1,
                'email' => $client->user->email,
                'libelle_article' => "Paiement IMLOD",
                'quantite' => 1,
                'montant' => ceil($total),
                'lib_order' => "Paiement commande de produit IMLOD",
                'Url_Retour' => $user->type_user_id == 4 ? route('client.monCompte') : route('show.listClientATerme'), //route("ouvreApp", ['codePaiement' => $codePaiement]),
                'Url_Callback' => route('callBackPaiement'),
            ],
            $codePaiement, //WARNING : A VERIFIER
            $client,
            $paiements->first()->id, //WARNING : A VERIFIER
            $total,
            6, //WARNING : A VERIFIER
            null, //WARNING : A VERIFIER
            null,
            $paiements
            // Help::$COMMANDE //WARNING : A VERIFIER
        );

        // dd($ret['message']);


        if ($ret['code'] == 200){

            // dd('paiement effectué');
            // $commande->update([
            //     'statut' => 4
            // ]);

            return Redirect::away($ret['message']);
        } else {

            $retour->code = $ret['code'];
            $retour->message = $ret['message'];
        }

    }

    public function ProduitUpdate(Request $request){
        // dd($request->all());
        // return 1;
        // dd('ok');
        // dd($request->rowId);
        // $taille = count($request->rowId);

        $nbPanier = Cart::count();

     /*   return response()->json([
            'status' => 'success',
            'message' => $request->rowId        ]);*/


        if($nbPanier > 0){

            $rowsId = $request->rowId;
            $totalMontants = $request->montant;
            $totalQtes = $request->qte;

            foreach($rowsId as $key => $rowId){

                $produit = Cart::get($rowId);
                //var_dump($key, $rowId, $totalMontants[$key], $totalQtes[$key], $produit->price);
               // echo "<br><br>";
               // $partieEntiere = intdiv(intval($totalMontants[$key]), $produit->price);
               $partieEntiere = ($produit->qty == $totalQtes[$key]) ? intdiv(intval($totalMontants[$key]), $produit->price) : $totalQtes[$key] ;
                //update du panier
                // dd($rowId, $partieEntiere);
                Cart::update($rowId, $partieEntiere);

                //Un produit
                //$prixUniModif = $;
            }
        }

        $data = array();

        foreach (Cart::content() as $prod) {
           array_push($data,$prod);
        //    break;
        }


        if(session('devisAModifier')){
            return redirect()->route('client.editDevis',session('devisAModifier'))->with('ok','Le panier a été mis à jour');
        }

        foreach(Cart::content() as $produit){

            if($produit->options->type_affaire == "LOCATION"){
                // dd('ok');
                return redirect()->route('client.panierLocation')->with('ok','Le panier a été mis à jour');
            }
        }

        return redirect()->route('client.monPanier')->with('ok','Le panier a été mis à jour');

    }

    public function supprimerCompte(){
        $user = User::where('id',Auth::user()->id)->first();

        if($user->statut == 3 ){

            $user->update([
                'statut' => 1
            ]);

            return redirect()->route('client.monCompte')->with('removeDelete','Votre demande de suppression a été annulé avec succès');
        }

        $user->update([
            'statut' => 3
        ]);
        return redirect()->route('client.monCompte')->with('delete','Une demande de suppression de compte a été envoyée');
    }

    public function nettoyerPanier(){
        Cart::destroy();
        return redirect()->route('client.index');
    }

    public function supprimerProduit( $rowId){

        Cart::remove($rowId);

        return redirect()->route('client.monPanier')->with('remove','Produit supprimé !!');

    }

    public function validationLivraisonPage(Commande $commande){

        // dd($commande);
        return view('client.validationLivraison',[
            'commande' => $commande,
            'categories' => Categorie::all(),
            'produits' => Produit::all(),
            'client' => Client::where('user_id',Auth::user()->id)->first()
        ]);
    }

    public function recuperationProduit(Commande $commande){


        return view('client.recuperationProduit',[
            'commande' => $commande,
            'categories' => Categorie::all(),
            'produits' => Produit::all(),
            'client' => Client::where('user_id',Auth::user()->id)->first()
        ]);
    }

    public function actionLivraison(Livraison $livraison, $action){
        if ($action == 'accepter') {
            $livraison->update([
                'accepte' => 1,
                'date_accord' => date('Y-m-d H:i:s'),
                'etat_livraison' => 2
            ]);
        } else {
            $livraison->update([
                'accepte' => 3,
                'date_accord' => date('Y-m-d H:i:s'),

            ]);
        }

        return redirect()->route('client.recuperationProduit',$livraison->detailCommande->commande)->with('success', "Vous avez $action la livraison");
    }

    public function validationLivraison(Commande $commande){
        // dd($commande);

        $commande->update([
            'etat_commande' => 3
        ]);
        return redirect()->route('client.monCompte')->with('livree','Commande validée ');

    }

    public function loginPage(){
        // dd(Cart::content());
        $client = (Auth::user()) ?  Client::where('user_id',Auth::user()->id)->first() : new Client;
        return view('client.login',[
            'produits' => Produit::all(),
            'client' => $client,
            'categories' => Categorie::all()
        ]);
    }

    public function search(Request $request){
        $client = (Auth::user()) ? Client::where('user_id',Auth::user()->id)->first() : new Client;
        $prixPerso = [];
        if ($client && $client->id) {
            $prixPerso = Produit::prixPersonnalisesPour($client);
        }

        $terme = trim((string) $request->search);

        $produits = Produit::where('statut', \Help::$STATUT_ACTIF)
            ->when($terme !== '', function ($query) use ($terme) {
                $like = '%'.$terme.'%';
                $query->where(function ($q) use ($terme, $like) {
                    $q->where('reference', $terme)
                      ->orWhere('nom', 'LIKE', $like)
                      ->orWhere('abreviation', 'LIKE', $like)
                      ->orWhere('description', 'LIKE', $like)
                      ->orWhereHas('categories', function ($c) use ($like) {
                          $c->where('nom', 'LIKE', $like);
                      });
                });
            })
            ->avecFournisseur()
            ->orderBy('nom', 'asc')
            ->get();

        // Prix fournisseur le plus bas par produit : on aligne le prix affiché de la
        // recherche sur celui de l'accueil (cohérence du catalogue). Surcharge d'affichage
        // uniquement (non persistée).
        $prixFournisseur = \App\Models\StockProduit::where('statut', 1)
            ->where('prix', '>', 0)
            ->groupBy('produit_id')
            ->selectRaw('produit_id, MIN(prix) as mn')
            ->pluck('mn', 'produit_id')
            ->toArray();

        $produits->transform(function ($p) use ($prixFournisseur) {
            if (isset($prixFournisseur[$p->id])) {
                $p->prix_moyen = (float) $prixFournisseur[$p->id];
            }
            return $p;
        });

        return view('client.search-produit',[
            'produits' => $produits,
            'client' => $client,
            'categories' => categorie::all(),
            'prixPerso' => $prixPerso,
            'prixFournisseur' => $prixFournisseur,
        ]);
    }

    public function login(Request $request){
        // dd($request->email);
        $user = User::where('email',$request->email)->where('type_user_id',4)->first();

        if($user){
            // $info = [
            //     'email' => $request->email,
            //     'password' => $request->password
            // ];

            // On verifie si l'utilisateur a vérifié son email avant de le connecter grâce à un token null
            if(Help::HashVerifier($request->password, $user->password)){
                $token = $user->token;
                if($token == null){
                    if($user->statut == 2){
                        return back()->with('block', "Vous ne pouvez pas vous connecter pour le moment. Veuillez contacter l'administrateur pour plus d'information");
                    }
                    $request -> session() -> regenerate();

                    Auth::login($user);
                    $client = $user->client ?? Client::lireSurUser($user->id);
                    Auth::user()->tva = Client::tva($client);
                    // dd(Auth::user()->tva);
                    // $clientId = Client::where('user_id',$user->id)->value('id');
                        if(Cart::count()>0){
                            if(Cart::content()->first()->options->type == 'devis'){

                                $this->panierEnDevis(Auth::user()->id);

                            }elseif(Cart::content()->first()->options->type == 'commande'){

                                $this->panierEnCommande(Auth::user()->id);
                            }
                            // On verifie s'il y a une adresse enregistrée

                        }

                    return redirect()->route('client.monCompte');

                }elseif($token){
                    return redirect()->route('client.login')->with('failToken','Vous devez verifier votre email avant de vous connecter');
                }
            }else{

                return redirect()->route('client.login')->with('failInfo','L\'email ou le mot de passe est incorrect');
            }

        }else{
            return redirect()->route('client.login')->with('failInfo','L\'email ou le mot de passe est incorrect');
        }
    }

    public function registerPage(){
        return view('client.register',[
            'produits' => Produit::all(),
            'categories' => Categorie::all(),
            'villes' => Ville::all(),
            'pays' => Pays::all()
        ]);
    }

    public function ajoutBonCommande(Request $request){
        $total = Cart::total();
        session()->put([
            'ville' => $request->ville,
            'infoSup' => $request->affichage,
            'long' =>$request->long ,
            'lat' =>$request->lat,
        ]);

        if(isset($request->dateDebutLocation) && isset($request->dateFinLocation)){

            $nbreJour = Carbon::parse($request->dateDebutLocation)->diffInDays(Carbon::parse($request->dateFinLocation));
            // dd($nbreJour);
            session()->put([
                'dateDebutLocation' => $request->dateDebutLocation,
                'dateFinLocation' => $request->dateFinLocation,
                'nbre_jour'=> $nbreJour
            ]);
        }
        if(session('devis')){
            session()->forget('devis');
        }

        $clientObj = Auth::user() ? Client::where('user_id', Auth::user()->id)->first() : new Client;
        $prixPerso = Produit::prixPersonnalisesPour($clientObj && $clientObj->id ? $clientObj : null);
        return view('client.ajoutDeBonDeCommande',[
            'produits' => Produit::all(),
            'pays' => Pays::all(),
            'villes' => Ville::all(),
            'client' => $clientObj,
            'categories' => Categorie::all(),
            'modes'=> ModePaiement::liste(),
            'total' => $total,
            'prixPerso' => $prixPerso,
        ]);
    }

    public function register(Request $request){

        // dd($request->all());
        $userEmail = User::where('email',$request->email)->orWhere('login',$request->email)->first();
        if($userEmail){
            return redirect()->route('client.register')->with('existEmail','Cet Email est déjà utilisé')->withInput();
        }

        // try {
            //code...

            if($request->type == 1){
                // validation des données d'un particulier
                $request->validate([
                    "prenom" => "required",
                    "nom" => "required",
                    "email" => "required|email",
                    "pays" => "required|integer",
                    "ville" => "required|integer",
                    "contact1" => "required|digits:10",
                    "contact2" => "nullable|digits:10",
                    "adresse" => "required",
                    "password" => "required",
                    "code_promo" => "nullable",
                    "condition" => "required"
                ],[
                    "prenom.required" => "Veuillez remplir ce champs !",
                    "nom.required" => "Veuillez remplir ce champs !",
                    "email.required" => "Veuillez remplir ce champs !",
                    "pays.required" => "Veuillez choisir un pays !",
                    "ville.required" => "Veuillez choisir une ville !",
                    "contact1.required" => "Veuillez remplir ce champs !",
                    "contact1.numeric" => "Veuillez entrer un numéro valide !",
                    "contact1.digits" => "Veuillez entrer un numéro valide !",
                    "contact2.numeric" => "Veuillez entrer un numéro valide !",
                    "contact2.digits" => "Veuillez entrer un numéro valide !",
                    "adresse.required" => "Veuillez remplir ce champs !",
                    "password.required" => "Veuillez remplir ce champs !",
                    "condition.required" => "Veuillez accepter les conditions d'utilisation !"
                ]);

            } else {
                // validation des données d'une entreprise
                $request->validate([
                    "raisonSociale" => "required",
                    "email" => "required|email",
                    "pays" => "required|integer",
                    "ville" => "required|integer",
                    "contact1" => "required|digits:10",
                    "contact2" => "required",
                    "adresse" => "required",
                    "password" => "required",
                    "rccm" => "required",
                    "ncc" => "required",
                    "dfe" => "required|file|mimes:pdf,jpg,jpeg,png|max:5120",
                    "registre_commerce" => "required|file|mimes:pdf,jpg,jpeg,png|max:5120",
                    "condition" => "required"
                ],[
                    "raisonSociale.required" => "La raison sociale est obligatoire !",
                    "email.required" => "Veuillez remplir ce champs !",
                    "contact1.required" => "Veuillez remplir ce champs !",
                    "contact2.required" => "Veuillez remplir ce champs !",
                    "adresse.required" => "Veuillez remplir ce champs !",
                    "password.required" => "Veuillez remplir ce champs !",
                    "rccm.required" => "Le RCCM est obligatoire !",
                    "ncc.required" => "Le NCC est obligatoire !",
                    "dfe.required" => "Le fichier DFE est obligatoire !",
                    "dfe.mimes" => "Le DFE doit être au format PDF, JPG, JPEG ou PNG.",
                    "dfe.max" => "Le DFE ne doit pas dépasser 5 Mo.",
                    "registre_commerce.required" => "Le Registre de commerce est obligatoire !",
                    "registre_commerce.mimes" => "Le RC doit être au format PDF, JPG, JPEG ou PNG.",
                    "registre_commerce.max" => "Le RC ne doit pas dépasser 5 Mo.",
                    "condition.required" => "Veuillez accepter les conditions d'utilisation !"
                ]);
            }

            // on verifie si l'email ou le login n'est pas déjà utilisé
            $userEmail = User::where('email',$request->email)->orWhere('login',$request->email)->first();
            if($userEmail){
                return redirect()->route('client.register')->with('existEmail','Cet Email est déjà utilisé')->withInput();
            }

            $typeUser = TypeUser::where('nom','LIKE','%client%')->value('id');

            if($request->code_parrain != null){
                $codeApporteur = Apporteur::where('code',$request->code_parrain)->first();

                if($codeApporteur == null){
                    return redirect()->route('client.register')->with('failCode','Code promo invalide')->withInput();
                };
            }

            $numeroToken = Help::getNumberToken(4);

            $nomPrenoms = $request->raisonSociale
                ? $request->raisonSociale
                : trim($request->nom . ' ' . $request->prenom);

            $dataUser = [
                'nom_prenoms' => $nomPrenoms,
                'email' => $request->email,
                'password' => Help::HashPassword($request->password),
                'type_user_id' => $typeUser,
                'token' => $numeroToken,
                'adresse' => $request->adresse,
                'ville_id' => $request->ville,
                'login' => $request->email,
                'contact' => $request->contact1,
            ];

            try {
                $user = User::create($dataUser);
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                return redirect()->route('client.register')->with('existEmail','Cet Email est déjà utilisé')->withInput();
            }
            $userId = $user->id;

            if($request->raisonSociale != null){
                $nom = $request->raisonSociale;
                $prenom = '';

            }else{
                $nom = $request->nom;
                $prenom = $request->prenom;
            }

            $dfePath = null;
            $rcPath = null;
            if ($request->type == 2) {
                if ($request->hasFile('dfe')) {
                    $dfePath = $request->file('dfe')->store('documents_entreprise', 'public');
                }
                if ($request->hasFile('registre_commerce')) {
                    $rcPath = $request->file('registre_commerce')->store('documents_entreprise', 'public');
                }
            }

            $dataClient = [
                'nom' => $nom,
                'prenom' => $prenom,
                'email' => $request->email,
                'contact1' => $request->contact1,
                'contact2' => $request->contact2,
                // Le formulaire envoie type = 1 (Particulier) ou 2 (Entreprise).
                // On stocke la valeur métier attendue partout ailleurs ('PARTICULIER'/'ENTREPRISE').
                'type_client' => $request->type == 2 ? 'ENTREPRISE' : 'PARTICULIER',
                'rccm_clt' => $request->rccm,
                'ncc_clt' => $request->ncc,
                'user_id' => $userId,
                'dfe' => $dfePath,
                'registre_commerce' => $rcPath,
            ];

            $client = Client::create($dataClient);

            if($request->code_promo != null){
                $parrain = Apporteur::where('code', $request->code_promo)->first();
                if($parrain){
                    $client->update([
                        'code_parrain' => $request->code_promo,
                        'date_parrainage' => date('Y-m-d H:i:s'),
                        'parrain_id' => $parrain->id,
                    ]);
                }
            }



            $clientId = $client->id;
            $nom = $request->nom.' '.$request->prenom;
            $url = route('client.pageToken', ['email' => $request->email]);
            // dd($url,$request->email);


            // La classe est déclarée en minuscule : App\Mail\confirmClient (fichier confirmClient.php).
            // On la référence EXACTEMENT comme déclarée pour que l'autoload la trouve sur Linux.
            // Envoi NON bloquant : un échec d'email ne doit pas empêcher la création du compte.
            try {
                Mail::send(new confirmClient($nom, $request->email, $numeroToken));
            } catch (\Throwable $e) {
                \Log::warning('Email de confirmation inscription non envoyé: '.$e->getMessage());
            }

            session()->forget([
                'type',
                'ville',
                'infoSup',
                'lat',
                'long',
                'mode'
            ]);

            // return view('client.pageConfirmationToken');

            return redirect()->route('client.pageToken',['email' => $request->email])->with('success','Succès, veuillez consulter votre boite email pour confirmer votre inscription');
        // } catch (\Throwable $th) {
        //     return view('errorCatch',[
        //         'message' => $th->getMessage(),
        //         'code' => $th->getCode()
        //     ]);
        // }
    }

    public function pageToken(Request $request){

        return view('client.pageConfirmationToken',[
            'email' => $request->query('email'),
            'produits' => Produit::all(),
            'categories' => Categorie::all(),
        ]);
    }

    public function confirmationToken (Request $request){

        $request->validate([
            'token' => 'required'
        ],[
            'token.required' => 'Veuillez remplir ce champs !'
        ]);

        // dd($request->email);

        $user = User::where('email', $request->email)->first();


        if ($user) {
            if ($user->token) {
                if ($user->token === $request->token) {

                    // On vide le token et connecte l'utilisateur
                    $user->update([
                        'token' => null
                    ]);

                    // Auth::login($user);

                    return redirect()->route('client.login')->with('success', 'Votre compte a bien été confirmé, connectez-vous !');
                } else {
                    // Token incorrect
                    return back()->with('error', 'Code invalide');
                }
            } else {
                // ℹ️ Token déjà null = déjà vérifié
                return back()->with('info', 'Votre compte a déjà été vérifié !');
            }
        } else {
            //Utilisateur introuvable (potentiellement ajouté selon ton flow)
            return back()->with('error', 'Utilisateur introuvable');
        }


    }

    public function update(Request $request){
        // dd($request->all());

        $request->merge([
            'contact1' => preg_replace('/\D/', '', $request->contact1),
            'contact2' => preg_replace('/\D/', '', $request->contact2),
        ]);



        $request->validate([
            // "nom" => "required",
            // "prenom" => "required",
            "contact1" => "required|numeric|digits:10",
            "contact2" => "required|numeric|digits:10",
            // "email" => "required|unique:users,email",
            "ville" => "required",
            "adresse" => "required",
            "code" => "nullable",
            "password" => "nullable",
            "newPassword" => "nullable",
            "confirmPassword" => "nullable",

        ],[
            'nom.required' => "Champ obligatoire",
            'prenom.required' => "Champ obligatoire",
            'contact1.digits' => "Entrez 10 chiffres",
            'contact2.digits' => "Entrez 10 chiffres",
            // 'email.required' => "Champ obligatoire",
            // 'email.unique' => "Email déjà utilisé",
            'ville.required' => "Champ obligatoire",
            'adresse.required' => "Champ obligatoire",
        ]);
        $client = Client::where('user_id',Auth::user()->id)->first();

        if($request->password != null){
            if($request->newPassword != null){
                if($request->confirmPassword != null){
                    if(Help::HashVerifier($request->password, $client->user->password)){

                        Auth::user()->update([
                            'password' => Help::HashPassword($request->newPassword)
                        ]);
                    }else{

                        return redirect()->route('client.monCompte')->with('error','Mauvais mot de passe');
                    }
                }else{
                    return back()->with('info','Vous devez renseigner un nouveau mot de passe pour le modifier');
                }
            }else{
                return back()->with('info','Vous devez renseigner un nouveau mot de passe pour le modifier');
            }

        }



        $request->password;
        $request->newPassword;
        $request->confirmPassword;


        $code = $request->code;
        $client = Client::where('user_id',Auth::user()->id)->first();
        // dd($client->password);
        if($code != null){
            $apporteur = Apporteur::where('code',$code)->first();
            if($apporteur){
                $dataClient = [
                    "code_parrain" => $code,
                    "parrain_id" => $apporteur->id,
                    "date_parrainage" => date('Y-m-d H:i:s')
                ];

            }else{
                return redirect()->back()->with('errorCode','Le code promo saisi n\'est pas valide');
            }
        }

        //  dd($dataClient);




        if($request->password != null){

            if(Help::HashVerifier($request->password, $client->user->password)){
                Auth::user()->update([
                    'password' => Help::HashPassword($request->newPassword)
                ]);
            }else{

                return redirect()->route('client.monCompte')->with('errorPassword','Mauvais mot de passe');
            }
        }


        $dataUser = [
            'adresse' => $request->adresse,
            'ville_id' => $request->ville
        ];
        Auth::user()->update($dataUser);

        if($request->raisonSociale){
            $nom = $request->raisonSociale;
            $prenom = '';
        }else{
            $nom = $request->nom;
            $prenom = $request->prenom;
        }

        $dataClientAdd = [
            'nom' => $nom,
            'prenom' => $prenom,
            'contact1' => $request->contact1,
            'contact2' => $request->contact2,
            'ncc_clt' => $request->ncc,
            'rccm_clt' => $request->rmmc
        ];

        $dataClient = array_merge($dataClientAdd, $dataClient ?? []);

        // dd($dataClient);

        $client->update($dataClient);

        return redirect()->route('client.monCompte')->with('success','Vos information on bien été modifiées');

    }

    public function ticketSAV(){

        $client = Client::where('user_id',Auth::user()->id)->first();

        // dd($client->id);

        return view('client.produitDeticket',[
            'commandes' => Commande::where('client_id',$client->id)->get(),
            // 'villes' => Ville::all(),
            'produits' => Produit::all(),
            'client' => (Auth::user()) ? Client::where('user_id',Auth::user()->id)->first() : new Client,
            'categories' => Categorie::all()
        ]);

    }

    public function infoTicketSAV(DetailCommande $detail){
        // dd($detail);


        return view('client.infoTicketSAV',[
            'produits' => Produit::all(),
            'client' => (Auth::user()) ? Client::where('user_id',Auth::user()->id)->first() : new Client,
            'categories' => Categorie::all(),
            'detail' => $detail
        ]);

    }

    public function creationTicket(Request $request, detailCommande $detail){

        $user = User::find(Auth::user()->id);
        $client = $user?->client;
        if(!$user || !$client){
            return redirect()->route('client.monCompte')->with('error','Client introuvable.');
        }

        $request->validate([
            'objet'   => 'required|string|max:255',
            'message' => 'required|string',
        ], [
            'objet.required'   => "Veuillez préciser l'objet du ticket.",
            'message.required' => 'Veuillez décrire votre demande.',
        ]);

        // user_id = AGENT SAV assigné : reste NULL tant que le gestionnaire n'a pas
        // assigné le ticket. (Avant : la clé 'user' — inexistante — était ignorée et
        // user_id NOT NULL provoquait une erreur 500.)
        TicketSAV::create([
            'numero'             => uniqid(),
            'client_id'          => $client->id,
            'detail_commande_id' => $detail->id,
            'objet'              => $request->objet,
            'message'            => $request->message,
        ]);

        return redirect()->route('client.ticketSAV')->with('success','Demande envoyée');
    }

    /**
     * Liste des tickets SAV du client connecté, avec leur avancement
     * (Nouveau / En traitement / Résolu) et la solution une fois clôturé.
     */
    public function mesTicketsSAVClient(){
        $client = Client::where('user_id', Auth::user()->id)->first();
        if (!$client) {
            return redirect()->route('client.monCompte')->with('error', 'Client introuvable.');
        }

        return view('client.mesTicketsSAV', [
            'produits'   => Produit::all(),
            'categories' => Categorie::all(),
            'client'     => $client,
            'tickets'    => TicketSAV::with('detailCommande')
                ->where('client_id', $client->id)
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function monCompte(){
        $this->viderSession();

        $client = Client::where('user_id', Auth::user()->id)->first();
        if (!$client) {
            return redirect()->route('client.login')->with('error', 'Client introuvable. Connectez-vous à nouveau.');
        }

        $commandes = Commande::where('client_id', $client->id)->where('statut', Help::$STATUT_ACTIF)->orderBy('created_at','desc')->get();
        $demandes = DemandeLivraison::where('client_id', $client->id)->orderBy('created_at','desc')->get();
        $paiements = Paiement::where('client_id', $client->id)->where('statut', 2)->orderBy('created_at','desc')->get();

        $location = Location::where('client_id', $client->id)->orderBy('created_at','desc')->get();

        return view('client.monCompte',[
            'produits' => Produit::all(),
            'client' => Client::where('user_id',Auth::user()->id)->first(),
            'categories' => Categorie::all(),
            'demandeLivraions' => $demandes,
            'commandes' => $commandes,
            'devis' => Devis::where('client_id',$client->id)->orderBy('statut','asc')->orderBy('created_at','desc')->get(),
            'locations' => $location,
            'villes' => Ville::all(),
            'paiements' => $paiements,
            'types' => TypeVehicule::all()
        ]);
    }

    public function detaiDemandeDeLivraison(DemandeLivraison $livraison){
        // dd($livraison);

        return view('client.detaiDemandeDeLivraison',[
            'livraison' => $livraison,
            'categories' => Categorie::all(),
            'produits' => Produit::all(),
            'client' => Client::where('user_id',Auth::user()->id)->first()

        ]);
    }

    public function confirmationEmailClient($token){
            $user = User::where('token',$token)->first();

            if($user){
                DB::table('users')
                ->where('id', $user->id)
                ->update(['token' => null]);

                Auth::login($user);
                return redirect()->route('client.index');
            }else{
                return redirect()->route('notFound');
            }
    }

    public function home(){

        return view('client.home',[
            'produits' => ImageProduit::all()
        ]);
    }

    public function devis(){
        $clientId = Client::where('user_id',Auth::user()->id)->value('id');
        $devis = Devis::where('client_id',$clientId)->where('statut',1)->get();
        return view('client.devis',[
            'devis' => $devis
        ]);
    }

    public function commande(){
        $clientId = Client::where('user_id',Auth::user()->id)->value('id');
        $commandes = Commande::where('client_id',$clientId)->get();
        return view('client.commande',[
            'commandes' => $commandes
        ]);
    }

    public function produitInfo(Produit $produit){
        $lesNotes = NoteProduit::where('produit_id',$produit->id)->where('statut',2)->select('note')->get();
        $sommeDesNotes = NoteProduit::where('produit_id',$produit->id)->sum('note');

        $data =  [
            'zero'=> 0,
            'one' => 0,
            'two' => 0,
            'three'=> 0,
            'four'=> 0,
            'five'=> 0,
            'somme' => $sommeDesNotes
        ];

        foreach ($lesNotes as $note) {
            switch ($note->note) {
                case 0:
                    $data['zero']++;
                    break;
                case 1:
                    $data['one']++;
                    break;
                case 2:
                    $data['two']++;
                    break;
                case 3:
                    $data['three']++;
                    break;
                case 4:
                    $data['four']++;
                    break;
                case 5:
                    $data['five']++;
                    break;
            }
        }

        $client = (Auth::user()) ? Client::where('user_id',Auth::user()->id)->first() : new Client;
        $prixPerso = [];
        if ($client && $client->id) {
            $prixPerso = Produit::prixPersonnalisesPour($client);
        }

        // Aligne le prix affiché de la fiche sur le prix fournisseur le plus bas
        // (cohérent avec l'accueil/recherche/panier). prixPour(null) ignore le prix
        // personnalisé (géré séparément en vue via $prixPerso). Surcharge d'affichage.
        $produit->prix_moyen = $produit->prixPour(null);

        return view('client.infoProduit',[
            'data' => $data,
            'lesNotes' => $lesNotes,
            'produit' => $produit,
            // Catégories actives uniquement, avec leur relation produits contrainte
            // (statut ACTIF + VENTE) pour des compteurs justes et ne pas exposer
            // les produits désactivés/catégories de test dans la barre latérale.
            'categories' => Categorie::where('statut', 1)
                ->with(['produits' => function ($q) {
                    $q->where('produit.statut', 1)->where('produit.type_affaire', 'VENTE');
                }])
                ->get(),
            'produits' => Produit::where('type_affaire', 'VENTE')->where('statut', 1)->avecFournisseur()->get(),
            'client' => $client,
            'prixPerso' => $prixPerso,
        ]);
    }

    public function enregistrementLocation(){



        if(Auth::user()){




            // if($total > 2000000){
            //     return redirect()->route('client.referenceBancaire');
            // }
            $user = Auth::user();
                    // $nomPrenom = $user->client->nom.' '.$user->client->prenom;


            $config = Configuration::first();

            $client = Client::where('user_id',Auth::user()->id)->first();

            // Adresse de livraison (un enregistrement d'adresse est inoffensif s'il n'aboutit pas).
            if(session('0')['ville']){
                $ville = Ville::where('id',session('0')['ville'])->first();

                $dataAdresse = [
                    'client_id' => $client->id,
                    'pays_id' => $ville->pays->id,
                    'ville_id' => $ville->id,
                    'longitude' => session('0')['long'],
                    'latitude' => session('0')['lat'],
                    'affichage' => session('0')['infoSup'],
                ];
                $adresse = AdresseLivraison::create($dataAdresse);
                $adresseId = $adresse->id;
            }else{
                $adresseId =  null;
            }

            $total = session('totalLocation');

            // Montant réellement dû = TTC net (identique à l'écran mode-paiement).
            $montantTTC = round(session('0')['montantTTC']
                ?? (max(0, session('totalLocation') - round(session('remise') ?? 0))
                    + (session('0')['tva'] ?? 0) + (session('0')['cout_livraison'] ?? 0)));

            // BROUILLON de la location (produits + dates + montants). Permet de ne créer
            // la location qu'APRÈS confirmation du paiement en ligne, ou de la créer
            // directement pour les modes hors-ligne / en cas d'échec d'initiation.
            $details = [];
            $i = 0;
            foreach (Cart::content() as $produit) {
                $details[] = [
                    'produit_id'  => $produit->model->id,
                    'qte'         => $produit->qty,
                    'debut'       => session('debuts')[$i] ?? null,
                    'fin'         => session('fins')[$i] ?? null,
                    'prix'        => $produit->qty * $produit->price * (session('nbre_jour')[$i] ?? 1),
                    'nombre_jour' => session('nbre_jour')[$i] ?? 1,
                ];
                $i++;
            }
            $payload = [
                'location' => [
                    'numero'                => uniqid(),
                    'client_id'             => $client->id,
                    'mode_paiement_id'      => session('mode_paiement'),
                    'adresse_livraison_id'  => $adresseId,
                    'montant_total'         => $total,
                    'cout_livraison_client' => session('0')['cout_livraison'] ?? 0,
                    'remise'                => round(session('remise') ?? 0),
                ],
                'details' => $details,
                'tva'     => intVal(round(session('0')['tva'] ?? (session('totalLocation') * Client::tva($client)))),
            ];

            $online = ($client->client_a_terme == false && session('mode_paiement') != 1 && $montantTTC < 2000000);

            if ($online) {
                // PAIEMENT EN LIGNE : la location N'EST PAS encore créée. On initie le paiement
                // en portant le brouillon ($payload) ; la Location sera créée à la CONFIRMATION
                // (callback PaySecure serveur-à-serveur, ou URL de retour verifiePaiement).
                $codePaiement = Help::getCommandeNo();
                $ret = PaiementEnLigne::initierPaiement(
                    [
                        'code_paiement' => $codePaiement,
                        'nom_usager'    => $client->nom,
                        'prenom_usager' => $client->prenom ?: $client->nom,
                        'telephone'     => $client->contact1,
                        'email'         => $client->user->email,
                        'libelle_article' => "Paiement IMLOD",
                        'quantite'      => 1,
                        'montant'       => $montantTTC,
                        'lib_order'     => "Paiement location IMLOD",
                        'Url_Retour'    => Help::urlPaiement(route('client.verifiePaiement', ['codePaiement' => $codePaiement])),
                        'Url_Callback'  => Help::urlPaiement(route('callBackPaiement')),
                    ],
                    $codePaiement,
                    $codePaiement,
                    $client,
                    $montantTTC,
                    session('mode_paiement'),
                    0,                 // service_id = 0 : la location n'existe pas encore
                    Help::$LOCATION,
                    null,
                    $payload           // brouillon (stocké sur paiement.donnees_service)
                );

                if (($ret['code'] ?? null) == 200) {
                    session()->put('message', $ret['message']);
                    Cart::destroy();
                    return Redirect::away($ret['message']);   // location créée à la confirmation
                }
                // Échec d'initiation du paiement : on retombe sur la création directe (rien perdu).
            }

            // Création DIRECTE : modes hors-ligne (à terme, paiement=1, > 2M) OU échec init en ligne.
            $location = Location::creerDepuisDonnees($payload, 1);
            Cart::destroy();

            return view('orders.recapLocation',[
                'location' => $location,
                'config'   => Configuration::first()
            ]);
        }else{

                    return redirect()->route('client.login')->with('info','Connecté vous de pouvoir valider la location');
                }

    }

    public function paiementValide($code){
        return view('client.paiementValide',[
            'code' => $code
        ]);
    }

    public function panierCommande(Request $request, Devis $devis ){

        // $request->validate([
        //     'numero_bon' => 'required',
        //     'fichier' => 'required',
        //     'type_livraison' => 'required',
        //     'date_livraison' => 'required',
        //     'mode' => 'required',
        // ],[
        //     'numero_bon.required' => 'Veuillez entrer un numero de bon de commande',
        //     'fichier.required' => 'veuillez selectionner un fichier',
        //     'type_livraison.required' => 'veuillez sélectionner un type de livraison',
        //     'date_livraison.required' => 'Veuillez choisir la date de livraison',
        //     'mode.required' => 'Veuillez sélectionner un mode de livraison',
        // ]);

            if(Auth::user()){

                // Garde anti-doublon (BUG double soumission) : en flux panier (sans devis),
                // un panier déjà vide signifie qu'une commande vient d'être créée par une
                // 1re requête. On évite de créer une commande vide en double.
                if (!$devis->id && Cart::content()->isEmpty()) {
                    return redirect()->route('client.commande')
                        ->with('info', 'Votre commande a déjà été enregistrée.');
                }

                // on initialise les variables qui peuvent changer de valeur selon que nous sommes dans une modification de devis ou pas
                $date_livraison = session('date_livraison');
                $mode_paiement = session('mode_paiement');
                $numero = NULL;
                $remise = 0;
                $type_livraison = session('type_livraison');
                $cout_livraison = 0;
                $montantTva = 0;


                $user = Auth::user();

                $nomPrenom = $user->client->nom.' '.$user->client->prenom;



                $etat = Help::listeStatutCommande();

                $client = Client::where('user_id',$user->id)->first();


                if($devis->id == null){

                    $ville = Ville::where('id',session('0')['ville'])->first();
                    $cout_livraison = session('0')? session('0')['cout_livraison'] : 0;
                    $livrable = session('0')['estLivrable'] == 'oui' ? 1 : 0;
                    $montantTva = session('0')['tva'] ?? 0;
                    // dd($date_livraison,$ville,$mode_paiement,$type_livraison,$cout_livraison,$livrable,$montantTva);
                    // dd(session('type'));

                    $adresse_id = null;

                    if(session('0')['ville'] != null){

                        $dataAdresse = [
                            'client_id' => $client->id,
                            'pays_id' => $ville ? $ville->pays->id:0,
                            'ville_id' => $ville ? $ville->id : 0,
                            'longitude' => session('0')['long'],
                            'latitude' => session('0')['lat'],
                            'affichage' => session('0')['infoSup'],
                            'complement_adresse' => session('0')['infoSup'],
                        ];


                        $adresse = AdresseLivraison::create($dataAdresse);
                        $adresse_id = $adresse->id;
                    }

                    $total = Cart::total();

                    $remise = ceil(session('remise')) ?? 0;

                    $config = Configuration::first();

                    $dataDevis = [
                        'numero' => Help::genererNumeroUnique('devis'),
                        'client_id' => $client->id,
                        'date_livraison' => session('date_livraison'),
                        'adresse_livraison_id' => $adresse_id,
                        'type_livraison_id' => $type_livraison,
                        'mode_paiement_id' =>$mode_paiement,

                        'tva' => $montantTva,
                        'cout_reduction' => session('remise'),
                        'cout_livraison' => session('0')['cout_livraison'],
                        // 'montant' => session('0')['montantTTC'] - session('0')['tva'], //$totalPlusTva,
                        // 'montant' => session('0')['montantTTC'] , //$totalPlusTva,
                        'montant' => session('0')['montantHT'] , //$totalPlusTva,
                        'montant_ht' => session('0')['montantHT'],
                        'service' => session('type') == 'commande' ? 1 : 2,
                    ];

                    $devis = Devis::create($dataDevis);

                    $devisId = $devis->id;

                    if(session('reduction_id')){
                        $reduction = Reduction::find(session('reduction_id'));

                        $reduction->update([
                            'est_utilise' => 1,
                            'client_id' => $client->id,
                            'devis_id' => $devis->id
                        ]);
                    }

                    // Débit des points fidélité utilisés (colonne 'point') : dans la
                    // commande directe, la valeur des points était déduite du montant
                    // mais le solde du client n'était jamais réduit -> points
                    // réutilisables. On le décrémente ici, à la validation.
                    if(session('point_reduc')){
                        $client->update([
                            'point' => max(0, $client->point - session('point_reduc'))
                        ]);
                    }

                    foreach(Cart::content() as $produit){
                        DetailDevis::create([
                            'produit_id' => $produit->id,
                            'devis_id' => $devisId,
                            'qte' => $produit->qty,
                            'prix' => $produit->price,
                            'cout_livraison' => $produit->options->cout_livraison
                        ]);

                    }
                }else{

                    $adresse_id = $devis->adresse_livraison_id;
                    // $date_livraison = $devis->date_Livraison;
                    // $mode_paiement = $devis->mode_paiement_id;
                    // Remise = celle appliquée par le client sur la page de paiement
                    // (code promo / points, en session) si présente ; sinon la remise
                    // d'origine du devis. Sans ça, la réduction saisie au paiement était
                    // ignorée et le client payait plein tarif.
                    $remise = (session('reduction_id') || session('point_reduc'))
                        ? ceil((float) session('remise'))
                        : $devis->cout_reduction;
                    // $type_livraison = $devis->type_livraison_id;
                    $cout_livraison = $devis->cout_livraison;
                    $livrable = $devis->adresse_livraison_id != null ? 1 : 0;
                    $montantTva = $devis->tva;

                }

                // Une commande qui NÉCESSITE un paiement en ligne (client ordinaire +
                // mode en ligne + passerelle configurée) est créée « EN ATTENTE DE
                // PAIEMENT » : elle n'apparaît PAS dans la file de traitement du
                // gestionnaire tant que le paiement n'est pas confirmé (sinon on
                // traiterait une commande non payée). Le callback / la vérification
                // pull la passe en « EN ATTENTE » à la confirmation du paiement.
                $paiementEnLigneRequis = ($client->client_a_terme == false && $mode_paiement != 1 && config('paysecure.url'));
                $etatInitial = $paiementEnLigneRequis ? Help::$COMMANDE_EN_ATTENTE_PAIEMENT : $etat[0];

                $commande = Commande::create([
                    'numero' => $devis->numero,
                    'etat_commande' => $etatInitial,
                    'devis_id' => $devis->id,
                    'client_id' => $client->id,
                    'date_livraison' => $date_livraison,
                    'adresse_livraison_id' => $adresse_id,
                    'mode_paiement_id' => $mode_paiement,
                    'montant_total' => $devis->montant,
                    'remise' => $remise,
                    'type_livraison_id' => $type_livraison,
                    'cout_livraison_client' => $cout_livraison,
                    'est_livrable' => $livrable,
                ]);

                if (session('cheminFichier')) {

                    // Chemin temporaire (relatif au disque 'public')
                    $sourcePath = session('cheminFichier'); // 'temp_pdfs/nom-du-fichier.pdf'

                    // Chemin définitif (relatif au disque 'public')
                    $destinationPath = 'lesBons/' . basename($sourcePath);

                    // Déplacer le fichier (silencieux si déjà déplacé)
                    if (Storage::disk('public')->exists($sourcePath)) {
                        Storage::disk('public')->move($sourcePath, $destinationPath);
                    }

                    $bl = BlClient::create([
                        'numero' => session('numero_bon_commande'),
                        'client_id' => $client->id,
                        'fichier' => $destinationPath,
                        'commande_id' => $commande->id
                    ]);

                    // Purger les clés de session pour ne pas réutiliser sur la prochaine commande
                    session()->forget(['cheminFichier', 'numero_bon_commande', 'fichier']);
                }

                if($devis->id){

                    foreach($devis->detaildevis as $detail){
                        $detailCommande = DetailCommande::create([
                            'produit_id' => $detail->produit_id,
                            'commande_id' => $commande->id,
                            'qte' => $detail->qte,
                            'prix' => $detail->prix,
                            'prix_fournisseur' => $detail->prix_fournisseur,
                            'cout_livraison' => $detail->cout_livraison,
                        ]);
                    }
                    $devis->update([
                        'statut' => 2
                    ]);

                    // Consommer le code promo appliqué sur la page de paiement (flux
                    // devis existant) : le marquer utilisé pour qu'il ne resserve pas.
                    // Idempotent (la branche « nouveau devis » l'a déjà fait le cas échéant).
                    if(session('reduction_id')){
                        $reduction = Reduction::find(session('reduction_id'));
                        if($reduction && !$reduction->est_utilise){
                            $reduction->update([
                                'est_utilise' => 1,
                                'client_id'   => $client->id,
                                'devis_id'    => $devis->id,
                            ]);
                        }
                    }

                    // Débit des points fidélité utilisés (colonne 'point') : c'est ici,
                    // à la VALIDATION de la commande, que le solde est réellement réduit
                    // (le flux devis ne le faisait nulle part auparavant).
                    if(session('point_reduc')){
                        $client->update([
                            'point' => max(0, $client->point - session('point_reduc'))
                        ]);
                    }
                }else{
                    foreach(Cart::content() as $produit){
                        $detailCommande = DetailCommande::create([
                            'produit_id' => $produit->model->id,
                            'commande_id' => $commande->id,
                            'qte' => $produit->qty,
                            'prix' => $produit->price,
                            'prix_fournisseur' => $produit->options->prix_fournisseur,
                            'cout_livraison' => $produit->options->cout_livraison
                        ]);
                    }
                }

                $tva = TvaCommande::create([
                    'client_id' => $client->id,
                    'montant' => $montantTva,
                    'commande_id' => $commande->id,
                    'type_affaire' => 2
                ]);

                // $paiement = new Paiement();
                // $paiement->client_id = $client->id;
                // $paiement->devis_id = $devis->id;
                // $paiement->code = Help::getCommandeNo();
                // $paiement->libelle = "Paiement commande de produit IMLOD";
                // $paiement->montant_total = $commande->montant_total + $commande->TvaCommande->montant + $commande->cout_livraison_client - $commande->remise;
                // $paiement->montant_restant = $commande->montant_total + $commande->TvaCommande->montant + $commande->cout_livraison_client - $commande->remise;
                // $paiement->statut = Help::$STATUT_INACTIF;
                // $paiement->service_id = $commande->id;
                // $paiement->service = Help::$COMMANDE;
                // $paiement->save();

                $ret = array();

                if($devis->id){
                    $total = ($devis->montant + $devis->cout_livraison + $devis->tva) - $devis->cout_reduction;
                }else{

                    $total = (session('0')['montantTTC'] - session('0')['tva']) + (session('0') ? session('0')['cout_livraison'] : 0) + (session('0')['tva']);
                }

                // Journaliser pour diagnostic
                \Log::info('panierCommande - paiement', [
                    'client_a_terme' => $client->client_a_terme,
                    'total' => $total,
                    'mode_paiement' => $mode_paiement,
                ]);

                // Déclencher le paiement en ligne si : client ordinaire ET mode ≠ En Agence (id=1)
                if ($client->client_a_terme == false && $mode_paiement != 1) {

                    // Vérifier que le service de paiement en ligne est configuré
                    if (!config('paysecure.url')) {
                        Cart::destroy();
                        return redirect()->route('client.commandeValidee', $commande->numero)
                            ->with('warning', 'Votre commande a été enregistrée. Le paiement en ligne n\'est pas disponible pour le moment. Rendez-vous dans Mon Compte pour réessayer.');
                    }

                    $codePaiement = Help::getCommandeNo();
                    $leNom = $client->nom;
                    $lePrenom = $client->prenom ?: $client->nom;

                    $ret = PaiementEnLigne::initierPaiement(
                        [
                            'code_paiement' => $codePaiement,
                            'nom_usager' => $leNom,
                            'prenom_usager' => $lePrenom,
                            'telephone' => $client->contact1,
                            'email' => $client->user->email,
                            'libelle_article' => "Paiement IMLOD",
                            'quantite' => 1,
                            // Total net depuis les LIGNES (cf. Commande::montantAPayer) : sûr
                            // quelle que soit l'origine (web: montant_total=HT, mobile: net).
                            'montant' => intVal($commande->montantAPayer()),
                            'lib_order' => "Paiement commande de produit IMLOD",
                            'Url_Retour' => Help::urlPaiement(route('client.verifiePaiement', ['codePaiement' => $codePaiement])),
                            'Url_Callback' => Help::urlPaiement(route('callBackPaiement')),
                        ],
                        $codePaiement,   // $numero
                        $codePaiement,   // $codePaiement (identique ici, utilisé pour la LignePaiement)
                        $client,
                        intVal($commande->montantAPayer()),
                        ($commande->mode_paiement_id) ? $commande->mode_paiement_id : 0,
                        $commande->id,
                        Help::$COMMANDE
                    );

                    \Log::info('panierCommande - initierPaiement résultat', [
                        'ret_code' => $ret['code'] ?? null,
                        'ret_message' => $ret['message'] ?? null,
                    ]);

                    if ($ret['code'] == 200) {
                        // La commande est créée : on vide le panier avant de partir vers la passerelle
                        // (sinon le produit reste dans le panier après un paiement en ligne réussi).
                        Cart::destroy();
                        return Redirect::away($ret['message']);
                    } else {
                        // Le paiement en ligne a échoué — rediriger avec message d'avertissement
                        Cart::destroy();
                        return redirect()->route('client.commandeValidee', $commande->numero)
                            ->with('warning', 'Votre commande a été enregistrée mais le paiement en ligne a échoué (' . ($ret['message'] ?? 'Erreur inconnue') . '). Rendez-vous dans Mon Compte pour réessayer le paiement.');
                    }
                }
                $modePaiment = ModePaiement::find($commande->mode_paiement_id)->value('description');

                // toastr()->success('Commande validée');
                // Montants depuis les LIGNES (cf. Commande::montantAPayer/montantHT).
                // Envoi NON bloquant : un échec d'email ne doit pas casser la commande.
                try {
                    Mail::send(new emailCommande(
                                    $commande,
                                    $commande->TvaCommande->montant,
                                    $commande->montantAPayer(),
                                    $commande->cout_livraison_client,
                                    $commande->remise,
                                    $modePaiment,
                                    $commande->montantHT()
                                ));
                } catch (\Throwable $e) {
                    \Log::warning('Email commande non envoyé: '.$e->getMessage());
                }
                Cart::destroy();
                return redirect()->route('client.commandeValidee',$commande->numero)->with('success','Votre commande a bien été enregistrée ! Rendez-vous dans la rubrique Mon Compte pour suivre votre commande');
            }else{

                return redirect()->route('client.login');
            }


    }

    public function verifiePaiement($codePaiement){


        $paiement = Paiement::where('code', $codePaiement)->first();

        // Garde : code de paiement inconnu (URL erronée, paiement initié côté mobile
        // dont le code diffère, lien re-visité après purge...) -> message propre au
        // lieu d'un 500 ("Attempt to read property on null") en page blanche.
        if (!$paiement) {
            // Repli : les paiements mobiles stockent le code PaySecure sur les lignes
            // (ligne_paiement.code_paiement), pas sur paiement.code.
            $lignePaiement = LignePaiement::where('code_paiement', $codePaiement)->first();
            $paiement = $lignePaiement ? Paiement::find($lignePaiement->paiement_id) : null;
        }
        if (!$paiement) {
            return redirect()->route('client.index')
                ->with('info', "Paiement introuvable pour ce code. Si vous venez de payer, consultez Mon Compte pour vérifier votre commande/location.");
        }

        $lignePaiementCount = $paiement->lignePaiements->count();
        $count = 0;

        // $lesLignesValides = DB::select("select COUNT(*) from ligne_paiement where paiement_id = :paiement_id and statut = :statut", ['paiement_id' => $paiement->id, 'statut' => 1 ]);
        // $lesLignes = DB::select("select COUNT(*) from ligne_paiement where paiement_id = :paiement_id ", ['paiement_id' => $paiement->id]);

        // $l = DB::select("select id from paiement");

        // dd($l, $lesLignesValides, $lesLignes,$paiement->id, LignePaiement::all());
        foreach($paiement->lignePaiements as $ligne){
            if($ligne->statut == 1){
                $count++;
            }
        }
        // dd($count, $lignePaiementCount,  );
        if($count == $lignePaiementCount){
        // if($lesLignesValides == $lesLignes){
            //   paiement effectué

            $paiement->montant_restant = 0;
            $paiement->update();

            switch ($paiement->service) {
                case Help::$COMMANDE:
                    $commande = Commande::find($paiement->service_id);
                    // Emails NON bloquants : un échec d'envoi ne doit pas provoquer une
                    // page blanche à l'affichage du reçu après un paiement réussi.
                    if ($commande) {
                        try {
                            $tvaCmd = $commande->TvaCommande->montant ?? 0;
                            Mail::send(new emailCommande(
                                $commande,
                                $tvaCmd,
                                // Total NET et HT via les méthodes du modèle (calcul depuis les
                                // lignes) : montant_total contient le HT côté web mais le NET
                                // côté mobile -> l'ancien calcul double-comptait TVA/livraison
                                // pour les commandes mobiles.
                                $commande->montantAPayer(),
                                $commande->cout_livraison_client,
                                $commande->remise,
                                optional(ModePaiement::find($commande->mode_paiement_id))->libelle,
                                $commande->montantHT()
                            ));
                            Mail::send(new ConfirmPaiement($paiement, $commande, $commande->client->user->email ?? ''));
                        } catch (\Throwable $e) {
                            \Log::warning('Email paiement commande non envoyé: '.$e->getMessage());
                        }
                    }
                    break;
                case Help::$LOCATION:
                    // Crée la location depuis le brouillon si le callback PaySecure ne l'a pas
                    // encore fait (idempotent). Le paiement est soldé (montant_restant = 0 ci-dessus).
                    $location = Location::creerDepuisPaiement($paiement);
                    if ($location) {
                        $location->statut = 3;
                        $location->save();
                        // Email NON bloquant. Classe corrigée : emailPaiementLocationClient
                        // (Location, int) — l'ancien appel passait la Location à emailLocation
                        // qui attend (User, Location, ...) -> TypeError avalé, email jamais parti.
                        try {
                            $tvaLoc    = $location->tvaLocation->montant ?? 0;
                            $remiseLoc = $location->remise ?? 0;
                            Mail::send(new emailPaiementLocationClient(
                                $location,
                                intVal(max(0, $location->montant_total - $remiseLoc) + $tvaLoc + ($location->cout_livraison_client ?? 0))
                            ));
                        } catch (\Throwable $e) {
                            \Log::warning('Email paiement location non envoyé: '.$e->getMessage());
                        }
                    }
                    break;
                default:
                    break;
            }

            $data['image'] = config("constantes.logo");
            $data['paiement'] = $paiement;
            $data['categories'] = Categorie::all();
            $data['produits'] = Produit::all();
            $data['client'] = Client::where('user_id',Auth::user()?->id)->first() ?? new Client;

            return view('welcome',$data);

            // return PDF::loadView('document.factureApresCommande',$data)->stream('facture.pdf');

        }else{
            // paiement non effectué
            return redirect()->route('client.index')->with('info','Votre paiement n\'a pas été effectué. Vous pouvez enregistrer en devis pour payer plutard');
        }
    }
    public function referenceBancaire(){
        return view('client.pageReferenceBancaire',[
            'produits' => Produit::all(),
            'categories' => Categorie::all(),
        ]);
    }

    public function validationReference(Request $request, Devis $devis){
        // dd($devis);$
        $client = Client::where('user_id', Auth::user()->id)->first();

        $request->validate([
            'fichier' => 'required|max:2048|mimes:pdf',
            'date_operation' => 'required',
            'banque' => 'required',
            'num_compte' => 'required',
            'reference' => 'required',
        ],[
            'fichier.mimes' => 'Le fichier doit être au format PDF',
            'fichier.required' => 'Vous devez charger votre reçu de paiement',

            'banque.required' => 'Veuillez entrer le nom de la banque',
            'date_operation' => 'Veuillez entrer la date à laquelle vous avez fait le virement',
            'num_compte.required' => 'Veuillez entrer votre numéro de compte',
            'reference.required' => 'La référence est requise',
        ]);

        $destination = base_path('public/storage/preuveVirement/');
        $nomPdf = 'Fichier'.'-'.Auth::user()->client->nom.'-'.Auth::user()->client->prenom.'-'. date('YmdHis') .'.pdf'; // extension forcée : jamais l'extension d'origine (anti-upload de .php exécutable)
        $request->file('fichier')->move($destination, $nomPdf);

        $cout_livraison = 0;
        $livrable = 1;
        $montantTva = 0;
        $type_livraison = null;

        // on verifie si un devis existe pour y récuperer l'adresse de livraison dans le cas contraire on crée une nouvelle adresse de livraison
        if($devis->id){

            if($devis->adresse_livraison_id){

                $ville = Ville::find($devis->adresseLivraison->ville_id);

                $adresse_id = $devis->adresse_livraison_id;
                $mode_paiement_id = $devis->mode_paiement_id;
                $cout_livraison = $devis->cout_livraison;
                $livrable = 1;
                $type_livraison = $devis->type_livraison_id;
            }
            $montantTva = $devis->tva;
            $date_livraison = $devis->date_Livraison;



        }else{


            if(session('0')['ville'] != null){
                $ville = Ville::find(session('0')['ville']);

                $dataAdresse = [
                    'client_id' => $client->id,
                    'pays_id' => $ville ? $ville->pays->id:0,
                    'ville_id' => $ville ? $ville->id : 0,
                    'longitude' => session('0')['long'],
                    'latitude' => session('0')['lat'],
                    'affichage' => session('0')['infoSup'],
                    'complement_adresse' => session('0')['infoSup'],
                ];

                $adresse = AdresseLivraison::create($dataAdresse);
                $adresse_id = $adresse->id;
                $date_livraison = session('date_livraison');
            }

            if(session('0')['cout_livraison']){
                $cout_livraison = session('0')['cout_livraison'];
                $type_livraison = session('type_livraison');
            }

            $livrable = session('0')['estLivrable'] == 'oui' ? 1 : 0;
            $montantTva = session('0')['tva'] ?? 0;

            $mode_paiement_id = session('mode_paiement');
        }






                $user = Auth::user();
                $nomPrenom = $user->client->nom.' '.$user->client->prenom;
                $id = Auth::user()->id;
                $etat = Help::listeStatutCommande();

                // $ville = Ville::where('id',session('0')['ville'])->first();

                $client = Client::where('user_id',$id)->first();




                // $adresseId = $adresse->id;
                $total = Cart::total();

                $promo = 0;
                $remise = ceil(session('remise')) ?? 0;

                if(session('type') == 'commande' || $devis->service == 'VENTE'){

                    $config = Configuration::first();


                    // On verifie s'il n'y a pas de devis on en crée un nouveau
                    if($devis->id == null){
                        $dataDevis = [
                            'numero' => Help::genererNumeroUnique('devis'),
                            'client_id' => $client->id,
                            'adresse_livraison_id' => session('date_livraison'),
                            'montant' => session('0')['montantTTC'] - session('0')['tva'] //$totalPlusTva,
                        ];


                        $devis = Devis::create($dataDevis);

                        foreach(Cart::content() as $produit){
                            DetailDevis::create([
                                'produit_id' => $produit->id,
                                'devis_id' => $devis->id,
                                'qte' => $produit->qty,
                                'prix' => $produit->price,
                                'cout_livraison' => $produit->options->cout_livraison
                            ]);
                        }


                    }


                    $devisId = $devis->id;

                    if(session('reduction_id')){
                        $reduction = Reduction::find(session('reduction_id'));

                        $reduction->update([
                            'est_utilise' => 1,
                            'client_id' => $client->id,
                            'devis_id' => $devis->id
                        ]);
                    }


                    // dd( $promo);
                    $commande = Commande::create([
                        'numero' => $devis->numero,
                        'etat_commande' => $etat[0],
                        'devis_id' => $devis->id,
                        'client_id' => $client->id,
                        'adresse_livraison_id' => $adresse_id ? $adresse_id : null,
                        'mode_paiement_id' => $mode_paiement_id,
                        'montant_total' => $devis->montant,
                        'remise' => $remise,
                        'date_livraison' => $date_livraison,
                        'type_livraison_id' => $type_livraison,
                        'cout_livraison_client' => $cout_livraison,
                        'est_livrable' => $livrable,
                        // Type livraison
                    ]);



                    // dd('apres commande');

                    // *******************************

                    if (session('cheminFichier')) {

                        // Chemin temporaire (relatif au disque 'public')
                        $sourcePath = session('cheminFichier'); // 'temp_pdfs/nom-du-fichier.pdf'

                        // Chemin définitif (relatif au disque 'public')
                        $destinationPath = 'lesBons/' . basename($sourcePath);

                        // Déplacer le fichier (silencieux si déjà déplacé)
                        if (Storage::disk('public')->exists($sourcePath)) {
                            Storage::disk('public')->move($sourcePath, $destinationPath);
                        }

                        $bl = BlClient::create([
                            'numero' => session('numero_bon_commande'),
                            'client_id' => $client->id,
                            'fichier' => $destinationPath,
                            'commande_id' => $commande->id
                        ]);

                        // Purger les clés de session pour ne pas réutiliser sur la prochaine commande
                        session()->forget(['cheminFichier', 'numero_bon_commande', 'fichier']);
                    }


                    foreach($devis->detaildevis as $detail){
                        $detailCommande = DetailCommande::create([
                            'produit_id' => $detail->produit_id,
                            'commande_id' => $commande->id,
                            'qte' => $detail->qte,
                            'prix' => $detail->prix,
                            'prix_fournisseur' => $detail->prix_fournisseur,
                            'cout_livraison' => $detail->cout_livraison,
                        ]);
                    }
                    $devis->update([
                        'statut' => 2
                    ]);

                    $tva = TvaCommande::create([
                        'client_id' => $client->id,
                        'montant' => $montantTva,
                        'commande_id' => $commande->id,
                        'type_affaire' => 2
                    ]);

                    $service = Help::$COMMANDE;
                    $service_id = $commande->id;
                }
                if(session('type') == 'location' || $devis->service == 'LOCATION'){

                    $total = session('totalLocation');

                    // ************************************
                    $dataPromo = $this->reductionAppliquee();

                    $remise = ceil(session('remise')) ?? 0;
                    $location = [
                        'numero' => uniqid(),
                        'client_id' => $client->id,
                        'mode_paiement_id' => session('mode_paiement'),
                        'adresse_livraison_id' => isset($adresse) ? $adresse->id : null,
                        // 'date_location' => session('dateDebutLocation'),
                        'montant_total' => $total,
                        'etat_location' => Help::$LOCATION_EN_ATTENTE,
                        'cout_livraison_client' => session('0')['cout_livraison'],
                        // Remise (code promo / points) : cohérence facture / montant payé.
                        'remise' => round(session('remise') ?? 0),
                    ];

                    $location = Location::create($location);

                    $i=0;

                    foreach(Cart::content() as $produit){

                        $detail_location = DetailLocation::create([
                            'produit_id' => $produit->model->id,
                            'location_id' => $location->id,
                            'qte' => $produit->qty,
                            'debut' => session('debuts')[$i],
                            'fin' => session('fins')[$i],
                            'prix' => $produit->qty * $produit->price * session('nbre_jour')[$i],
                            'nombre_jour' => session('nbre_jour')[$i],
                            'etat_location' => Help::$LOCATION_EN_ATTENTE,

                        ]);

                        $i++;

                    }

                    // TVA NETTE (sur le HT après remise), cohérente avec le mode-paiement.
                    $laTva = TvaCommande::create([
                        'client_id' => $client->id,
                        'cout_livraison_client' => session('0')['cout_livraison'],
                        'commande_id' => $location->id,
                        'montant' => intVal(round(session('0')['tva'] ?? (session('totalLocation') * Client::tva($client)))),
                        'type_affaire' => Help::$LOCATION,
                    ]);

                    $service = Help::$LOCATION;
                    $service_id = $location->id;
                }


                $preuve = new PreuveOperation;
                $preuve->reference = $request->reference;
                $preuve->client_id = $client->id;
                $preuve->service = $service;
                $preuve->date_operation = $request->date_operation;
                $preuve->banque = $request->banque;
                $preuve->commande_id = $service_id;
                $preuve->num_compte =$request->num_compte;
                $preuve->fichier = 'preuveVirement/'.$nomPdf;
                $preuve->note_supp = $request->note_supp;
                $preuve->save();


                if(session('type') == 'commande' || $devis->service == 'VENTE'){
                    return redirect()->route('client.commandeValidee',$commande->numero)->with('success','Votre commande a bien été enregistrée ! Rendez-vous dans la rubrique Mon Compte pour suivre votre commande');
                }
                if( session('type') == 'location' || $devis->service == 'LOCATION'){
                    return view('orders.recapLocation',[
                        'location' => $location,
                        // 'reduc' => $point,
                        // 'promo' => $reduction,
                        'config' => Configuration::first()
                    ]);
                    return redirect()->route('client.locationValidee',$location->numero)->with('success','Votre location a bien été enregistrée ! Rendez-vous dans la rubrique Mon Compte pour suivre votre location');
                }

    }


    // convertir le devis en commande

    public function devisCommande(Devis $devis){

        $etat = Help::listeStatutCommande();

        // $devis = Devis::where('id',session('devis'))->first();

        $client = Client::where('user_id',Auth::user()->id)->first();
        // $ville = null;

        // if($devis->adresseLivraison){
        //     $ville = $devis->adresseLivraison->ville;

        // }

        // $dataAdresse = [
        //     'client_id' => $client->id,
        //     'pays_id' => $ville->pays->id,
        //     'ville_id' => $ville->id,
        //     'longitude' => session('long'),
        //     'latitude' => session('lat'),
        //     'affichage' => session('infoSup'),
        // ];

        // $adresse = AdresseLivraison::create($dataAdresse);

        /**
         * date_livraison
         * mode
         * est
         */

        $commande = Commande::create([
            'numero' => $devis->numero,
            'etat_commande' => $etat[0],
            'devis_id' => $devis->id,
            'date_livraison' => session('date_livraison'),
            'client_id' => $devis->client_id,
            'adresse_livraison_id' => $devis->adresseLivraison_id,
            'mode_paiement_id' => session('mode'),
            'montant_total' => $devis->montant,
            'est_livrable' => session('0')['estLivrable'] == 'oui' ? 1 : 0,
            'cout_livraison_client' => $devis->cout_livraison,
        ]);

        // session()->forget('mmode',)

        $tvaCommande = TvaCommande::create([
            'client_id' => $client->id,
            'commande_id' => $commande->id,
            'montant' => $devis->tva,
        ]);

        $commandeId = $commande->id;

        foreach($devis->detaildevis as $detail){
            $detailCommande = DetailCommande::create([
                'produit_id' => $detail->produit_id,
                'commande_id' => $commandeId,
                'qte' => $detail->qte,
                'prix' => $detail->prix,
                'prix_fournisseur' => $detail->prix_fournisseur,
            ]);
        }

        $devis->update([
            'statut' => 2
        ]);

        $montantPoint = 0;
        $pourcentPromo = 0;

        $nomPrenom = $client->nom.' '.$client->prenom;

        Mail::send(new emailCommande($client->user->email,
                                        $nomPrenom,
                                        $commande,
                                        $commande->montant_total,
                                        $montantPoint,
                                        $pourcentPromo));

        session()->forget('type');
        return redirect()->route('client.commandeValidee',$commande->numero)->with('success','Votre commande a bien été enregistrée ! Rendez-vous dans la rubrique Mon Compte pour suivre votre commande');

        return redirect()->route('client.index')->with('success','Commande validée. Rendez-vous dans la rubrique MON COMPTE');

    }

    public function devisAdresse(Devis $devis){
        $client = (Auth::user()) ? Client::where('user_id',Auth::user()->id)->first() : new Client;
        // dd($devis);

        foreach($devis->detailDevis as $detail){
            $type_affaire = ($detail->produit->type_affaire);
            break;
        }

        session([
            'type' => 'devis',
            'type_affaire' => $type_affaire,
            'niveauModifDevis' => 2
        ]);

        // dd(session('type_affaire'));

        return view('client.adresse',[
            'devis' => $devis,
            'produits' => Produit::all(),
            'pays' => Pays::all(),
            'villes' => Ville::all(),
            'client' => $client,
            'regions' => Region::all(),
            'categories' => Categorie::all(),
            'total' => $devis->montant,
            'conf' => Configuration::first(),
            'tva' => Client::tva($client),
            'type_affaire' => session('type_affaire')
        ]);

    }

    public function locationAdresse(){
        $client = (Auth::user()) ? Client::where('user_id',Auth::user()->id)->first() : new Client;

        $dataPromo = $this->reductionAppliquee();
        // dd($dataReduction);
        // dd($devis);

        // foreach($devis->detailDevis as $detail){
        //     $type_affaire = ($detail->produit->type_affaire);
        //     break;

        $total = Cart::total();
        // }

        //    session()->forget('type','type_affaire');

        session()->put([
            'type' => 'location'
        ]);
        // session()->forget('commande');

        return view('client.adresse',[
            'client' => (Auth::user())? Client::where('user_id',Auth::user()->id)->first() : new Client,
            'produits' => Produit::all(),
            'pays' => Pays::all(),
            'villes' => Ville::all(),
            'regions' => Region::all(),
            'client' => $client,
            'categories' => Categorie::all(),
            'total' => $dataPromo['total'],
            'reduc' => $dataPromo['config'],
            'montantPoint' => $dataPromo['montantPoint'],
            'montantPromo' => $dataPromo['montantPromo'],
            'conf' => Configuration::first(),
            'tva' => Client::tva($client),
        ]);



    }

    public function recapLocation(Request $request){
        // dd('rf');


        session()->put([
            'type_livraison' =>$request->type_livraison,
        ]);


        $client = Auth::user()->client;
        // dd(Cart::total()*Client::tva($client));

        if($client->type_client == 'ENTREPRISE'){
            // dd('rd');
            $request->validate([
                'fichier' => 'nullable|mimes:pdf|max:2048',
                'numero_bon' => 'required|max:255',
            ],
            [
                'fichier.required' => 'Le fichier est requis',
                'numero_bon.required' => 'Le numéro de bon est requis',
                'fichier.mimes' => 'Le fichier doit être au format PDF',
                'fichier.max' => 'Le fichier ne doit pas dépasser 2 Mo',
            ]);

            if ($request->hasFile('fichier')) {

                // $destination = base_path('public/storage/productsImage');
                $destination = storage_path('app/public/temp_pdfs'); // disque 'public' réel (cohérent avec move vers lesBons + lecture)
                $nomPdf = 'bon'.'-'.Auth::user()->client->nom.'-'.Auth::user()->client->prenom.'-'. date('YmdHis') .'.pdf'; // extension forcée : jamais l'extension d'origine (anti-upload de .php exécutable)
                $request->file('fichier')->move($destination, $nomPdf);
                // $path = $request->file('fichier')->move($destination, 'public');
                session()->put([
                    'cheminFichier' => 'temp_pdfs/'.$nomPdf,
                    'numero_bon_commande' => $request->numero_bon,
                    'fichier' => $nomPdf,
                ]);

            }
            if($client->client_a_terme == 0){
                session()->put([
                    'mode_paiement' => $request->mode
                ]);
            }

        }else{

            if($client->client_a_terme == 0){
                session()->put([
                    'mode_paiement' => $request->mode
                ]);
            }
        }

        // dd(session());


        $mode = ModePaiement::find($request->mode);
        $ville = Ville::find(session('0')['ville']);
        $total = session('totalLocation');
        // $totalAvecReduction = $total;

        $dataPromo = $this->reductionAppliquee();

            if($dataPromo['montantPromo']){
                $total = $total - $dataPromo['montantPromo'];
                session()->put([
                    'montantPromo' => $dataPromo['montantPromo']
                ]);
            }
            if($dataPromo['montantPoint']){
                $total = $total - $dataPromo['montantPoint'];
                session()->put([
                    'montantPoint' => $dataPromo['montantPoint']
                ]);
            }

        return view('orders.recapLocation',[
            'produits' => Produit::all(),
            'client' => Auth::user() ? Client::where('user_id',Auth::user()->id)->first(): new Client,
            'categories' => Categorie::all(),
            'total' => $total,
            'promo' => $dataPromo['reduction'],
            'reduc' => $dataPromo['config'],
            'lieu' => session('infoSup'),
            'ville' => $ville == null ? null : $ville->nom,
            'mode' => $mode->libelle,
            'config' => Configuration::first(),
            'tva' => Client::tva($client),
            // 'client' => Auth::user()
        ]);
    }

    public function recapDevisLocation(Request $request){

        // dd('ok');

        session()->put([
            'type_livraison' =>$request->type_livraison,
        ]);

        $client = Auth::user()->client;
        // dd(Cart::total()*Client::tva($client));

        if($client->type_client == 'ENTREPRISE'){

            $request->validate([
                'fichier' => 'required|mimes:pdf|max:2048',
                'numero_bon' => 'required|max:255',
            ],
            [
                'fichier.required' => 'Le fichier est requis',
                'numero_bon.required' => 'Le numéro de bon est requis',
                'fichier.mimes' => 'Le fichier doit être au format PDF',
                'fichier.max' => 'Le fichier ne doit pas dépasser 2 Mo',
            ]);

            if ($request->hasFile('fichier')) {

                // $destination = base_path('public/storage/productsImage');
                $destination = storage_path('app/public/temp_pdfs'); // disque 'public' réel (cohérent avec move vers lesBons + lecture)
                $nomPdf = 'bon'.'-'.Auth::user()->client->nom.'-'.Auth::user()->client->prenom.'-'. date('YmdHis') .'.pdf'; // extension forcée : jamais l'extension d'origine (anti-upload de .php exécutable)
                $request->file('fichier')->move($destination, $nomPdf);
                // $path = $request->file('fichier')->move($destination, 'public');
                session()->put([
                    'cheminFichier' => 'temp_pdfs/'.$nomPdf,
                    'numero_bon_commande' => $request->numero_bon,
                    'fichier' => $nomPdf,
                ]);

            }
            if($client->client_a_terme == 0){
                session()->put([
                    'mode_paiement' => $request->mode
                ]);
            }

        }else{

            if($client->client_a_terme == 0){
                session()->put([
                    'mode_paiement' => $request->mode
                ]);
            }
        }

        // dd(session());


        $mode = ModePaiement::find($request->mode);
        $ville = Ville::find(session('0')['ville']);
        $total = session('totalLocation');
        // $totalAvecReduction = $total;

        $dataPromo = $this->reductionAppliquee();

            if($dataPromo['montantPromo']){
                $total = $total - $dataPromo['montantPromo'];
                session()->put([
                    'montantPromo' => $dataPromo['montantPromo']
                ]);
            }
            if($dataPromo['montantPoint']){
                $total = $total - $dataPromo['montantPoint'];
                session()->put([
                    'montantPoint' => $dataPromo['montantPoint']
                ]);
            }

        return view('orders.recapDevisLocation',[
            'produits' => Produit::all(),
            'client' => Auth::user() ? Client::where('user_id',Auth::user()->id)->first(): new Client,
            'categories' => Categorie::all(),
            'total' => $total,
            'promo' => $dataPromo['reduction'],
            'reduc' => $dataPromo['config'],
            'lieu' => session('infoSup'),
            'ville' => $ville == null ? null : $ville->nom,
            'mode' => $mode->libelle,
            'config' => Configuration::first(),
            'tva' => Client::tva($client),
            // 'client' => Auth::user()
        ]);
    }

    public function commandeAdresse(Devis $devis){
        $client = (Auth::user()) ? Client::where('user_id',Auth::user()->id)->first() : new Client;

        session([
            'type' => 'commande'
        ]);

        $total = Cart::total();
        $data['reduction'] = null;
        $montantPromo = null;
        $montantPoint = null;

        if(session('reduction_id')){

            $data['reduction'] = Reduction::find(session('reduction_id'));

            $total = $total - ($total * $data['reduction']->taux_reduction)/100;
            $montantPromo = ($total * $data['reduction']->taux_reduction)/100;
        }

        if(session('point_reduc')){
            // dd('ok');
            $config = Configuration::first();
            $total = $total - session('point_reduc') * $config->montant_point;
            $montantPoint = session('point_reduc') * $config->montant_point;
        }
        // dd($montantPromo);
        return view('client.adresse',[
            'devis' => $devis,
            'produits' => Produit::all(),
            'pays' => Pays::all(),
            'villes' => Ville::all(),
            'client' => $client,
            'categories' => Categorie::all(),
            'total' => $total,
            'reduction' => $data['reduction'],
            'montantPromo' => $montantPromo,
            'montantPoint' => $montantPoint,
            'regions' => Region::all(),
            'conf' => Configuration::first(),
            'tva' => Client::tva($client),
        ]);

    }

    public function panierDevis(){

        // dd('ok');
        if(Auth::user()){

            $devis = $this->panierEnDevis(Auth::user()->id);
            return redirect()->route('client.devisValide',$devis)->with('success','Votre dévis a été enregistré');

        }else{

            return redirect()->route('client.login');
        }


    }

    public function devisValide(Devis $devis){

        // dd('ok');

        return view('orders.devisValide',[
            'devis' => $devis
        ]);
    }

    public function recapCommandeVenantDunDevis(Request $request, Devis $devis){

        //dd($request->all());

        if ($request->hasFile('fichier')) {

                $request->validate(['fichier' => 'required|mimes:pdf|max:2048'], [
                    'fichier.mimes' => 'Le fichier doit être au format PDF',
                    'fichier.max' => 'Le fichier ne doit pas dépasser 2 Mo',
                ]);

                // $destination = base_path('public/storage/productsImage');
                $destination = storage_path('app/public/temp_pdfs'); // disque 'public' réel (corrige le double public/ + cohérence move/lecture)
                $nomPdf = 'bon'.'-'.Auth::user()->client->nom.'-'.Auth::user()->client->prenom.'-'. date('YmdHis') .'.pdf'; // extension forcée : jamais l'extension d'origine (anti-upload de .php exécutable)
                $request->file('fichier')->move($destination, $nomPdf);
                // $path = $request->file('fichier')->move($destination, 'public');
                session()->put([
                    'cheminFichier' => 'temp_pdfs/'.$nomPdf,
                    'numero_bon_commande' => $request->numero_bon,
                    'fichier' => $nomPdf,
                ]);

            }

        session()->put([
            'mode_paiement' => $request->mode,
            'type_livraison' => $request->type_livraison,
            'date_livraison' => $request->date_livraison,

        ]);




        $mode = ModePaiement::find($request->mode);


        $ville = Ville::find(session('ville'));

        if($devis->id && $devis->adresseLivraison) {
            $ville = $devis->adresseLivraison->ville;
        }

        // Base = HT du DEVIS (le panier est vide dans le flux devis ; l'ancien
        // Cart::total() valait 0, ce qui écrasait le total et la remise).
        $totalHT = (float) $devis->montant;
        $promo = 0;
        $reducPoint = 0;
        $remise = 0;

        if(session('reduction_id')){
            $data['reduction'] = Reduction::find(session('reduction_id'));
            if($data['reduction']){
                $promo = $data['reduction']->taux_reduction;
                $remise += $totalHT * ($promo / 100);
            }
        }
        if(session('point_reduc')){
            $config = Configuration::first();
            $reducPoint = session('point_reduc') * $config->montant_point;
            $remise += $reducPoint;
        }

        // Plafond : la remise ne peut dépasser le HT marchandise.
        if ($remise > $totalHT) {
            $remise = $totalHT;
        }

        $total = $totalHT - $remise; // HT net après remise

        // dd($mode);
        return view('orders.recapDevisVersCommande',[
            'produits' => Produit::all(),
            'client' => Auth::user() ? Client::where('user_id',Auth::user()->id)->first(): new Client,
            'categories' => Categorie::all(),
            'total' => $total,
            'remise' => $remise,
            'promo' => $promo,
            'reducPoint' => $reducPoint,
            'lieu' => session('infoSup'),
            'ville' => $ville ? $ville->nom : 'Pas de livraison',
            'mode' => $mode,
            'devis' => $devis,
            // 'client' => Auth::user()
        ]);

    }

    public function grandLivre(){
        $client = Client::where('user_id',Auth::user()->id)->first();

        return view('client.grandLivre',[
            'commandes' => $client->commande,
            'categories' => Categorie::all(),
            'produits' => Produit::all(),
            'client' => Client::where('user_id',Auth::user()->id)->first()
        ]);
    }

    public function listePaiementCommandeClientBE($etat){

        $client = Client::where('user_id',Auth::user()->id)->first();

        if($etat =='effectues'){

            $req  ="SELECT cde.id AS commande_id,
                                        cde.numero AS num_commande,
                                        cde.created_at AS date_commande,
                                        cde.est_livrable,
                                        li.id AS ligne_id,
                                        li.reference AS code_paiement,
                                        li.montant,
                                        li.created_at AS date_paiement,
                                        mp.description AS mode_paiement
                                FROM ligne_paiement li
                                JOIN commande cde ON li.service_id = cde.id
                                JOIN paiement p ON p.id = li.paiement_id
                                JOIN mode_paiement mp ON li.mode_paiement_id = mp.id
                                WHERE p.client_id = $client->id AND p.statut <> 3
                                ORDER BY li.created_at
                                        ";

         }else{

            if($client->client_a_terme == 1){
            // liste de paiement en attente pour les clients à terme
                $req = "SELECT
                            DISTINCT(f.id) AS facture_id,
                            cde.id AS commande_id,
                            cde.numero AS num_commande,
                            cde.client_id,
                            cde.numero AS num_commande,
                            cde.created_at AS date_commande,
                            f.montant AS montant_a_payer,
                            p.montant_total AS total_paye,
                            (SELECT montant_restant FROM paiement WHERE service_id = cde.id ORDER BY created_at DESC LIMIT 1) AS montant_restant
                        FROM facture f
                        JOIN commande cde ON f.service_id = cde.id
                        LEFT JOIN paiement p ON p.facture_id = f.id AND p.statut = 2
                        WHERE cde.client_id = $client->id
                        -- GROUP BY f.id, cde.id
                        -- HAVING montant_restant > 0
                        ORDER BY cde.created_at
                        ";
            }else{
                // liste de paiement en attente pour les clients ordinaire
                $req = "SELECT IFNULL(SUM(li.montant), 0) AS paye,
                                (cde.montant_total + cde.cout_livraison_client + tva.montant - cde.remise) AS montant_a_payer,
                                ((cde.montant_total + cde.cout_livraison_client + tva.montant - cde.remise) - IFNULL(SUM(li.montant), 0) ) AS montant_restant,
                                cde.numero AS num_commande,
                                cde.created_at AS date_commande,
                                cde.id as commande_id

                        FROM commande cde
                        LEFT JOIN ligne_paiement li ON li.service_id = cde.id
                        LEFT JOIN tva_commande tva ON tva.commande_id = cde.id
                        WHERE cde.client_id = $client->id
                        GROUP BY cde.id,
                                tva.montant,
                                cde.montant_total,
                                cde.cout_livraison_client,
                                cde.remise,
                                cde.numero,
                                cde.created_at
                        HAVING montant_restant > 0";
            }
         }

         $lignes = DB::select($req);

        return view('client.listePaiement',[
            'lignes' => $lignes,
            'etat' => $etat,
            'categories' => Categorie::all(),
            'produits' => Produit::all(),
            'client' => Client::where('user_id',Auth::user()->id)->first(),
            'moyens' => ModePaiement::liste()
        ]);

    }


    public function listePaiementCommandeClientBEOld(){

        $client = Client::where('user_id',Auth::user()->id)->first();

        if($client->client_a_terme == 1){

            return view('client.listePaiement',[
                'commandes' => $client->commande->where('statut',1),
                // 'commande' => $commande,
                'categories' => Categorie::all(),
                'produits' => Produit::all(),
                'client' => Client::where('user_id',Auth::user()->id)->first()
            ]);

        }else{

            // $c = $client->paiements->where('statut',Help::$STATUT_ACTIF);


            // $paiements = Paiement::where('devis_id',$commande->id)->get();

            return view('client.listePaiement',[
                'paiements' => $client->paiements->where('statut',Help::$STATUT_ACTIF),
                // 'commande' => $commande,
                'categories' => Categorie::all(),
                'produits' => Produit::all(),
                'client' => Client::where('user_id',Auth::user()->id)->first()
            ]);

        }


    }

    public function recapCommande(Request $request){

        // dd($request->all());

        $client = Auth::user()->client;

        // En POST : stocker les nouvelles données en session
        if ($request->isMethod('post')) {
            session()->put([
                'type_livraison' => $request->type_livraison,
                'date_livraison' => $request->date_livraison,
            ]);

            if($client->type_client == 'ENTREPRISE'){
                if ($request->hasFile('fichier')) {
                    $request->validate(['fichier' => 'required|mimes:pdf|max:2048'], [
                        'fichier.mimes' => 'Le fichier doit être au format PDF',
                        'fichier.max' => 'Le fichier ne doit pas dépasser 2 Mo',
                    ]);
                    $destination = storage_path('app/public/temp_pdfs'); // disque 'public' réel (corrige le double public/ + cohérence move/lecture)
                    $nomPdf = 'bon'.'-'.Auth::user()->client->nom.'-'.Auth::user()->client->prenom.'-'. date('YmdHis') .'.pdf'; // extension forcée : jamais l'extension d'origine (anti-upload de .php exécutable)
                    $request->file('fichier')->move($destination, $nomPdf);
                    session()->put([
                        'cheminFichier' => 'temp_pdfs/'.$nomPdf,
                        'numero_bon_commande' => $request->numero_bon,
                        'fichier' => $nomPdf,
                    ]);
                }
            }

            if ($request->mode) {
                session()->put(['mode_paiement' => $request->mode]);
            }
        }

        // Récupérer le mode de paiement (POST ou session)
        $modeId = $request->mode ?? session('mode_paiement');
        $mode = $modeId ? ModePaiement::find($modeId) : null;

        $ville = Ville::find(session('ville'));

        $lieu = session('0') ? (session('0')['infoSup'] ?? null) : null;

        return view('orders.recapPanierVersCommande',[
            'produits' => Produit::all(),
            'client' => Auth::user() ? Client::where('user_id',Auth::user()->id)->first(): new Client,
            'categories' => Categorie::all(),
            'total' => Cart::total(),
            'lieu' => $lieu,
            'ville' => $ville == null ? null : $ville->nom,
            'mode' => $mode,
            'config' => Configuration::first(),
            'tva' => Client::tva($client),
        ]);

    }

    // convertir le panier en devis

    private function panierEnDevis($id){

            $client = Client::where('user_id',$id)->first();

            $config = Configuration::first();

            $total = Cart::total();
            // $promo = null;

            $cout_livraison = 0;
            $remise = 0;
            $adresse_livraison_id = null;
            if(session('0')['cout_livraison']){
                $cout_livraison = session('0')['cout_livraison'];

                $ville = Ville::find(session('0')['ville']);

                $adresse_livraison = AdresseLivraison::create([
                    'client_id' => $client->id,
                    'ville_id' => session('0')['ville'],
                    'pays_id' => $ville->pays_id,
                    'affichage' => session('0')['infoSup'],
                    'longitude' => session('0')['long'],
                    'latitude' => session('0')['lat'],
                ]);
                $adresse_livraison_id = $adresse_livraison->id;

            }

            if(session('remise')){
                $remise = session('remise');
            }

                $dataDevis = [
                    'numero' => Help::getCommandeNo(),
                    'client_id' => $client->id,
                    'montant' => $total,
                    'tva' => session('0')['tva'],
                    'cout_livraison' => $cout_livraison,
                    'mode_paiement' => session('mode'),
                    'mode_paiement_id' => session('mode'),
                    'cout_reduction' => $remise,
                    'adresse_livraison_id' => $adresse_livraison_id,
                    'montant_ht' => Cart::total(),
                    'type_livraison_id' => session('type_livraison_id'),
                    'service' => 1,
                    'date_livraison' => session('date_livraison'),
                ];

                $devis = Devis::create($dataDevis);

                $devisId = $devis->id;

                foreach(Cart::content() as $produit){

                    $detailDevis = DetailDevis::create([
                        'produit_id' => $produit->id,
                        'devis_id' => $devisId,
                        'qte' => $produit->qty,
                        'prix' => $produit->price,
                        'prix_fournisseur' => $produit->options->prix_fournisseur,
                        'cout_livraison' => $produit->options->cout_livraison ? $produit->options->cout_livraison : null ,

                    ]);
                }
                session()->forget([
                    'type',
                    'devisAModifier'
                ]);
                Cart::destroy();

                return $devis;

    }

    private function panierEnCommande($id){
        // dd(route('callBackPaiement'),route('client.monPanier'));

        $etat = Help::listeStatutCommande();

        $ville = Ville::where('id',session('ville'))->first();
        $client = Client::where('user_id',$id)->first();





        $dataAdresse = [
            'client_id' => $client->id,
            'pays_id' => $ville->pays->id,
            'ville_id' => $ville->id,
            'longitude' => session('long'),
            'latitude' => session('lat'),
            'affichage' => session('infoSup'),
        ];

        $adresse = AdresseLivraison::create($dataAdresse);
        $adresseId = $adresse->id;
        $total = Cart::total();

        $promo = null;
        // if(session('reduction_id')){

        //     $reduction = Reduction::find(session('reduction_id'));

        //     $reduction->update([
        //         'est_utilise' => 1,
        //         'client_id' => $client->id,
        //         'devis_id' => $devis->id
        //     ]);
        // }
        if(session('point_reduc')){
            $config = Configuration::first();
            $total = $total - session('point_reduc') * $config->montant_point;

            // Colonne réelle = 'point' (singulier) : l'ancien 'points' visait une
            // colonne inexistante -> les points n'étaient jamais débités. max(0,…)
            // empêche un solde négatif.
            $client->update([
                'point' => max(0, $client->point - session('point_reduc'))
            ]);
        }


        $config = Configuration::first();

        $laTva = ($total * $config->tva)/100;

        $totalPlusTva = $total + $laTva;

        // dd($total, $laTva,$totalPlusTva,$config->tva);




                $dataDevis = [
                    'numero' => Help::getCommandeNo(),
                    'client_id' => $client->id,
                    'adresse_livraison_id' => $adresseId,
                    'montant' => $totalPlusTva,
                ];

                $devis = Devis::create($dataDevis);

                $devisId = $devis->id;

                if(session('reduction_id')){
                    $reduction = Reduction::find(session('reduction_id'));

            $promo = $reduction->taux_reduction;

            $total = Cart::total() - (Cart::total() * $reduction->taux_reduction)/100;

                    $reduction->update([
                        'est_utilise' => 1,
                        'client_id' => $client->id,
                        'devis_id' => $devis->id
                    ]);
                }

                foreach(Cart::content() as $produit){

                    DetailDevis::create([
                        'produit_id' => $produit->id,
                        'devis_id' => $devisId,
                        'qte' => $produit->qty,
                        'prix' => $produit->price,
                        'prix_fournisseur' => $produit->options->prix_fournisseur
                    ]);

                }



                // session()->forget('');

                // dd($devis->montant);

                $commande = Commande::create([
                    'numero' => $devis->numero,
                    'etat_commande' => $etat[0],
                    'devis_id' => $devis->id,
                    'client_id' => $client->id,
                    'adresse_livraison_id' => $adresseId,
                    'mode_paiement_id' => session('mode'),
                    'montant_total' => $devis->montant,
                    'remise' => $promo,
                    'est_livrable' => session('0')['estLivrable'] == 'oui' ? 1 : 0,
                ]);

                // ********************************

                if (session('cheminFichier')) {

                    // Chemin temporaire (relatif au disque 'public')
                    $sourcePath = session('cheminFichier'); // 'temp_pdfs/nom-du-fichier.pdf'

                    // Chemin définitif (relatif au disque 'public')
                    $destinationPath = 'lesBons/' . basename($sourcePath);

                    // Déplacer le fichier (silencieux si déjà déplacé)
                    if (Storage::disk('public')->exists($sourcePath)) {
                        Storage::disk('public')->move($sourcePath, $destinationPath);
                    }

                    $bl = BlClient::create([
                        'numero' => session('numero_bon_commande'),
                        'client_id' => $client->id,
                        'fichier' => $destinationPath,
                        'commande_id' => $commande->id
                    ]);

                    // Purger les clés de session pour ne pas réutiliser sur la prochaine commande
                    session()->forget(['cheminFichier', 'numero_bon_commande', 'fichier']);

                }

                // ********************************

                // if (session('cheminFichier')) {

                //     // Chemin temporaire (relatif au disque 'public')
                //     $sourcePath = session('cheminFichier'); // 'temp_pdfs/nom-du-fichier.pdf'

                //     // Chemin définitif (relatif au disque 'public')
                //     $destinationPath = 'lesBons/' . basename($sourcePath);

                //     // Déplacer le fichier
                //     Storage::disk('public')->move($sourcePath, $destinationPath);

                //     // $source = 'storage'.session('cheminFichier');

                //     // $destination = 'storage/lesBons'.session('fichier');

                //     // dd($source,$destination);
                //     // $newPath = 'lesBons/' . basename(session('cheminFichier'));
                //     // Storage::disk('public')->move($source, $destination);

                //     $bl = BlClient::create([
                //         'numero' => session('numero_bon_commande'),
                //         'client_id' => $client->id,
                //         'fichier' => $destinationPath,
                //         'commande_id' => $commande->id
                //     ]);

                // }


                $commandeId = $commande->id;

                foreach($devis->detaildevis as $detail){
                    $detailCommande = DetailCommande::create([
                        'produit_id' => $detail->produit_id,
                        'commande_id' => $commandeId,
                        'qte' => $detail->qte,
                        'prix' => $detail->prix,
                        'prix_fournisseur' => $detail->prix_fournisseur
                    ]);
                }
                $devis->update([
                    'statut' => 2
                ]);

                $tva = TvaCommande::create([
                    'client_id' => $client->id,
                    'montant' => $laTva,
                    'commande_id' => $commande->id,
                    'type_affaire' => 2
                ]);

                // *************************************

                $paiement = new Paiement();
                $paiement->client_id = $client->id;
                $paiement->devis_id = $devis->id;
                $paiement->code = $commande->numero;
                $paiement->libelle = "Paiement commande de produit IMLOD";
                $paiement->montant_total = $commande->montant_total;
                $paiement->montant_restant = 0;
                $paiement->statut = Help::$STATUT_INACTIF;
                $paiement->save();

                $ret = array();
                if ($client->client_a_terme == false) {
                    $codePaiement = Help::getCommandeNo();
                    $nomPrenoms = $client->nom;
                    $arrNoms = explode(" ", $nomPrenoms);
                    $leNom = $client->nom;
                    $lePrenom = $client->prenom ?: $client->nom;
                    // if (count($arrNoms) >= 2) {
                    //     $leNom = $arrNoms[0];
                    //     $lePrenom = $arrNoms[1];
                    // } else {
                    //     $leNom = $arrNoms[0];
                    //     $lePrenom = $arrNoms[0];
                    // }

                    $retour = new \stdClass();
                    $retour->code = null;
                    $retour->message = null;

                    $ret = PaiementEnLigne::initierPaiement(
                        [
                            'code_paiement' => $codePaiement,
                            // 'credential_id' => "",
                            'nom_usager' => $leNom,
                            'prenom_usager' => $lePrenom,
                            'telephone' => $client->contact1,
                            'email' => $client->user->email,
                            'libelle_article' => "Paiement IMLOD",
                            'quantite' => 1,
                            'montant' => ceil($commande->montant_total),
                            'lib_order' => "Paiement commande de produit IMLOD",
                            'Url_Retour' => route('client.monPanier'), //route("ouvreApp", ['codePaiement' => $codePaiement]),
                            'Url_Callback' => route('callBackPaiement'),
                        ],
                        $codePaiement,
                        $client,
                        $paiement->id,
                        $commande->montant_total,
                        session('mode'),
                        $commande->id,
                        Help::$COMMANDE
                    );

                    // dd($ret['message']);


                    if ($ret['code'] == 200){
                        // $retour->code = 201;
                        // $retour->message = $ret['message'];
                        // return redirect()->away($ret['message']);
                        // Commande créée : vider le panier avant la redirection passerelle.
                        Cart::destroy();
                        return Redirect::away($ret['message']);
                    } else {
                        $retour->code = $ret['code'];
                        $retour->message = $ret['message'];
                    }

                    Mail::send(new emailCommande($client->user->email,
                                        $nomPrenom,
                                        $commande,
                                        $commande->montant_total,
                                        $montantPoint,
                                        $pourcentPromo));
                }

                // *************************************
                // dd($commande);

                Cart::destroy();

                return $commande->id;
    }


    private function enregistrementDeLocation($id){


        $config = Configuration::first();


        $ville = Ville::where('id',session('ville'))->first();
        $client = Client::where('user_id',$id)->first();





        $dataAdresse = [
            'client_id' => $client->id,
            'pays_id' => $ville->pays->id,
            'ville_id' => $ville->id,
            'longitude' => session('long'),
            'latitude' => session('lat'),
            'affichage' => session('infoSup'),
        ];

        $adresse = AdresseLivraison::create($dataAdresse);

        $total = session('totalLocation');

        // ************************************
        $dataPromo = $this->reductionAppliquee();

            if($dataPromo['montantPromo']){
                $total = $total - $dataPromo['montantPromo'];
            }
            if($dataPromo['montantPoint']){
                $total = $total - $dataPromo['montantPoint'];
            }

        // ************************************


        $promo = null;
        if(session('reduction_id')){
            $data['reduction'] = Reduction::find(session('reduction_id'));

            $promo = $data['reduction']->taux_reduction;

            $total = Cart::total() - (Cart::total() * $data['reduction']->taux_reduction)/100;

            $data['reduction']->update([
                'est_utilise' => 1,
                'client_id' => $client->id
            ]);
        }
        if(session('point_reduc')){
            $total = $total - session('point_reduc') * $config->montant_point;

            // Colonne réelle = 'point' (singulier) : l'ancien 'points' visait une
            // colonne inexistante -> les points n'étaient jamais débités. max(0,…)
            // empêche un solde négatif.
            $client->update([
                'point' => max(0, $client->point - session('point_reduc'))
            ]);
        }

        $total = $total + $total*($config->tva/100);

                $location = [
                    'numero' => uniqid(),
                    'client_id' => $client->id,
                    'mode_paiement_id' => session('mode_paiement'),
                    'adresse_livraison_id' => $adresse->id,
                    // 'date_location' => session('dateDebutLocation'),
                    'montant_total' => $total,
                    'etat_location' => Help::$LOCATION_EN_ATTENTE,
                    'cout_livraison_client' => session('0')? session('0')['cout_livraison_client'] : 0,
                    // 'remise' =>
                ];

                $location = Location::create($location);

                $i=0;

                foreach(Cart::content() as $produit){

                    $detail_location = DetailLocation::create([
                        'produit_id' => $produit->model->id,
                        'location_id' => $location->id,
                        'qte' => $produit->qty,
                        'debut' => session('debuts')[$i],
                        'fin' => session('fins')[$i],
                        // $produit->price = prix issu de prixPour() (prix personnalisé si défini),
                        // au lieu de prix_moyen brut, sinon le prix personnalisé est ignoré pour les locations.
                        'prix' => $produit->qty * $produit->price * session('nbre_jour')[$i],
                        'nombre_jour' => session('nbre_jour')[$i],
                        'etat_location' => Help::$LOCATION_EN_ATTENTE
                    ]);

                    $i++;

                }



                Cart::destroy();
                return $location->id;
    }

    private function reductionAppliqueeLocation(){

        if(session('type') == 'location'){
            $total = 0;

            $i = 0;
            foreach (Cart::content() as $key => $produit ){

                // Prix capturé dans le panier (déjà personnalisé pour ce client si applicable)
                $total += $produit->price * $produit->qty * session('nbre_jour')[$i];
                    $i++;
            }
            $data['total'] = $total;


        }else{

            $data['total'] = Cart::total();

        }

        // dd($data['total']);

        $data['config'] = Configuration::first();
        $promo = null;
        $data['montantPromo'] = null;
        $data['montantPoint'] = null;

        if(session('reduction_id')){
            $data['reduction'] = Reduction::find(session('reduction_id'));

            $promo = $data['reduction']->taux_reduction;

            $total = $data['total'] - ($data['total'] * $promo/100);


            $data['montantPromo'] = ($data['total'] * $promo)/100;

            // dd($data['total'],$promo/100, $data['montantPromo']);

        }
        if(session('point_reduc')){
            // $config = Configuration::first();
            $data['total'] = $data['total'] - session('point_reduc') * $data['config']->montant_point;

            $data['montantPoint'] = session('point_reduc') * $data['config']->montant_point;
        }

        return $data;



    }


    private function reductionAppliquee(){

        $data['total'] = Cart::total();
        $total = Cart::total();
        $dataPromo['config'] = null;
        $data['reduction'] = null;


        // dd($data['total']);

        $data['config'] = Configuration::first();
        $promo = null;
        $data['montantPromo'] = null;
        $data['montantPoint'] = null;

        if(session('reduction_id')){
            $data['reduction'] = Reduction::find(session('reduction_id'));

            $promo = $data['reduction']->taux_reduction;

            $data['total'] = $total - ($total * $promo/100);


            $data['montantPromo'] = ($total * $promo)/100;

            // dd($data['total'],$promo/100, $data['montantPromo']);

            // dd($data['total']);

        }
        if(session('point_reduc')){
            // $config = Configuration::first();
            $data['total'] = $data['total'] - session('point_reduc') * $data['config']->montant_point;

            $data['montantPoint'] = session('point_reduc') * $data['config']->montant_point;
        }

        return $data;



    }

    public function viderSession(){
          session()->forget([
                    'devis',
                    'type',
                    'ville',
                    'infoSup',
                    'long',
                    'lat',
                    'debuts',
                    'fins',
                    'nbre_jour',
                    'totalLocation',
                    'reduction_id',
                    'point_reduc',
                    'montantPromo',
                    'montantPoint',
                    'cout_livraison',
                    'montantTotal',
                    'tva',
                    '0',
                    'mode_paiement',
                    'remise',
                    'totalCommande',
                    'type_livraison_id',
                    'mode',
                    'fichier',
                    'numero_bon_commande',
                    'cheminFichier',
                    'date_livraison',
                    'affichagePec',
                    'villePec',
                    'longPec',
                    'latPec',

                    'affichageDest',
                    'villeDest',
                    'longDest',
                    'latDest',
                    'km',
                    'produits',
                    'montant_total',
                    'date',
                    'paiement',
                    'type_livraison',
                    'type_affaire',
                ]);
    }
}
