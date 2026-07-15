<?php

namespace App\Http\Controllers;


use PDF;
use Help;
use App\Models\User;
use App\Models\Produit;
use App\Models\Commande;
use App\Models\TypeUser;
use App\Models\Categorie;
use App\Models\Enlevement;
use App\Models\Fournisseur;
use App\Models\ModePaiement;
use App\Models\StockProduit;
use Illuminate\Http\Request;
use App\Models\Configuration;
use App\Models\DemandePaiement;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\sellerRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use \Cviebrock\EloquentSluggable\Services\SlugService;


class SellerController extends Controller
{

    public function home()
    {
        $this->verificationStock();
        $fournisseur = Fournisseur::where('user_id', Auth::user()->id)->first();
        $idFrs       = $fournisseur->id;

        $now          = \Carbon\Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $today        = $now->copy()->startOfDay();

        $bons = Enlevement::where('fournisseur_id', $idFrs)
            ->with(['produit', 'livraison.vehicule'])
            ->orderByDesc('created_at')
            ->get();

        $totalBons       = $bons->count();
        $bonsEnAttente   = $bons->whereNull('fournisseur_validation')->count();
        $bonsValides     = $bons->whereNotNull('fournisseur_validation')->count();
        $bonsAujourdhui  = $bons->filter(fn($b) => optional($b->created_at)->isSameDay($today))->count();
        $bonsCeMois      = $bons->filter(fn($b) => optional($b->created_at) >= $startOfMonth)->count();

        $totalProduits   = $fournisseur->produits()->count();
        $totalQuantites  = (float) $fournisseur->produits()->sum('stock_produit.qte');

        // Top 5 produits servis (par quantité servie)
        $topProduits = $bons->groupBy('produit_id')
            ->map(function ($items) {
                $first = $items->first();
                return (object) [
                    'produit'   => $first?->produit,
                    'nb_bons'   => $items->count(),
                    'total_qte' => (float) $items->sum(fn($e) => (float) ($e->qte_servi ?? $e->qte ?? 0)),
                ];
            })
            ->sortByDesc('total_qte')
            ->take(5)
            ->values();

        $dernieresLivraisons = $bons->take(10);

        return view('fournisseur.dashboard', [
            'fournisseur'         => $fournisseur,
            'bons'                => $bons,
            'totalBons'           => $totalBons,
            'bonsEnAttente'       => $bonsEnAttente,
            'bonsValides'         => $bonsValides,
            'bonsAujourdhui'      => $bonsAujourdhui,
            'bonsCeMois'          => $bonsCeMois,
            'totalProduits'       => $totalProduits,
            'totalQuantites'      => $totalQuantites,
            'topProduits'         => $topProduits,
            'dernieresLivraisons' => $dernieresLivraisons,
        ]);
    }

    //Afficher la liste des fournisseurs





    public function livraisons()
    {
        return view('fournisseur.livraison');
    }


    public function bons()
    {
        $this->verificationStock();

        // Récuperation de l'identifiant du fournisseur courant
        $fournisseur = Fournisseur::where('user_id', '=', Auth::user()->id)->first();
        // dd($idLivreur);

        // Récuperation des bons d'enlèvement liès aux livraisons
        $enlevement = Enlevement::where('fournisseur_id', '=', $fournisseur->id)->where('qte_servi', null)->orderByDesc('created_at')->get();
        // dd($enlevement);

        $tab = [];
        foreach ($fournisseur->produits as $produit) {
            // dd($produit);
            $env = [
                $produit->nom => $enlevement->where('produit_id', $produit->id)->count(),
            ];
            array_push($tab, $env);
        }

        // dd($tab);



        // dd($fournisseur->produits);

        return view('fournisseur.bon', [
            'enlevements' => $enlevement,
            'fournisseur' => $fournisseur,


        ]);
    }


    public function accepte()
    {
        $this->verificationStock();

        // Récuperation de l'identifiant du fournisseur courant
        $fournisseur = Fournisseur::where('user_id', '=', Auth::user()->id)->first();
        // dd($idLivreur);

        // Récuperation des bons d'enlèvement liès au fournisseur courant déjà servi
        $enlevement = Enlevement::where('fournisseur_id', '=', $fournisseur->id)->where('qte_servi', '!=', null)->orderByDesc('updated_at')->get();
        // dd($enlevement);
        $tab = [];

        foreach ($fournisseur->produits as $produit) {
            // dd($produit);
            $env = [
                $produit->nom => $enlevement->where('produit_id', $produit->id)->count(),
            ];
            array_push($tab, $env);
        }
        // dd($tab);

        return view('fournisseur.accepte', [
            'enlevements' => $enlevement,
            'fournisseur' => $fournisseur,
        ]);
    }

    public function refuse()
    {
        $this->verificationStock();

        // Récuperation de l'identifiant du fournisseur courant
        $idFournisseur = Fournisseur::where('user_id', '=', Auth::user()->id)->value('id');
        // dd($idLivreur);

        // Récuperation des bons d'enlèvement liès aux livraisons
        $enlevement = Enlevement::where('fournisseur_id', '=', $idFournisseur)->where('fournisseur_validation', '!=', null)->get();
        // dd($enlevement);

        return view('fournisseur.accepte', [
            'enlevements' => $enlevement
        ]);
    }
    public function formBon(Request $request)
    {
        $this->verificationStock();
        $idFournisseur = Fournisseur::where('user_id', '=', Auth::user()->id)->value('id');
        $enlevement = Enlevement::where('fournisseur_id', '=', $idFournisseur)->get();
        $bon = Enlevement::where('code_enleve', $request->code)->where('fournisseur_id', $idFournisseur)->first();

        if ($bon == null) {

            return redirect()->route('sellers.bons', [
                'enlevements' => $enlevement
            ])->with('fail', 'Code invalide');

        } else {
            // dd('valide');

            return redirect()->route('sellers.bon.detail', $request->code);
        }

    }

    public function bonDetail($code)
    {
        $this->verificationStock();
        $idFournisseur = Fournisseur::where('user_id', '=', Auth::user()->id)->value('id');
        $enlevement = Enlevement::where('fournisseur_id', '=', $idFournisseur)->get();
        $bon = Enlevement::where('code_enleve', $code)->where('fournisseur_id', $idFournisseur)->first();
        if ($bon) {
            $produit = StockProduit::where('produit_id', $bon->produit_id)->where('fournisseur_id', $bon->fournisseur_id)->first();
        } else {
            $produit = new Produit;
        }

        return view('fournisseur.detailBon', [
            'produit' => $produit,
            'bon' => $bon
        ]);
    }

    public function bonImprime($code)
    {
        $this->verificationStock();

        $data['idFournisseur'] = Fournisseur::where('user_id', '=', Auth::user()->id)->value('id');
        $data['enlevement'] = Enlevement::where('fournisseur_id', '=', $data['idFournisseur'])->get();
        $data['bon'] = Enlevement::where('code_enleve', $code)->where('fournisseur_id', $data['idFournisseur'])->first();
        $data['produit'] = StockProduit::where('produit_id', $data['bon']->produit_id)->where('fournisseur_id', $data['bon']->fournisseur_id)->first();
        // dd($code);
        // return view('fournisseur.bonImprime',$data);

        $pdf = PDF::loadView('fournisseur.bonImprime', $data)->download();
        // return $pdf;

        return $pdf;

        // notify()->success('Laravel Notify is awesome!');
        // return redirect()->route('paye.create',$numero);
    }

    public function afficheBon($code)
    {
        $this->verificationStock();

        $data['idFournisseur'] = Fournisseur::where('user_id', '=', Auth::user()->id)->value('id');
        $data['enlevement'] = Enlevement::where('fournisseur_id', '=', $data['idFournisseur'])->get();
        $data['bon'] = Enlevement::where('code_enleve', $code)->where('fournisseur_id', $data['idFournisseur'])->first();
        $data['produit'] = StockProduit::where('produit_id', $data['bon']->produit_id)->where('fournisseur_id', $data['bon']->fournisseur_id)->first();
        // dd($code);
        // return view('fournisseur.bonImprime',$data);

        $pdf = PDF::loadView('fournisseur.bonImprime', $data)->stream();
        // return $pdf;

        return $pdf;

    }


    public function bonValidation(Request $request, $code)
    {
        $this->verificationStock();

        // dd($code, $request->qteServi);
        // $enleve = Enlevement::where('code_enleve',$code)->first();

        $fournisseur = Fournisseur::where('user_id', '=', Auth::user()->id)->first();

        $bon = Enlevement::where('code_enleve', $code)->where('fournisseur_id', $fournisseur->id)->first();

        $commande = DB::select("select c.* from commande c, detail_commande d, livraison l where c.id = d.`commande_id` and d.`id`=l.`detail_commande_id` and l.id=" . $bon->livraison->id)[0];
        // $d = Commande::find($commande->id)->detailCommande->sum('qte');
        // dd($commande, $d, );

        if ($bon == null) {
            return back()->with('error', 'Code invalide');
        }

        $bon->livraison->detailCommande->update([
            'etat_livraison' => 3
        ]);

        $bon->livraison->update([
            //'etat_livraison' => 3
            // livre_par est un entier : 1 = LIVREUR, 2 = CLIENT (récupération par le client).
            // Pour une récupération client, la validation du bon = livraison LIVREE (3).
            'etat_livraison' => $bon->livraison->livre_par == 2 ? 3 : 4,
        ]);
        $stock = StockProduit::where('fournisseur_id', $fournisseur->id)->where('produit_id', $bon->produit_id)->first();

        // $stock->update([
        //     'qte' => $request->qteServi
        // ]);


        $bon->update([
            'fournisseur_validation' => date('Y-m-d H:i:s'),
            'qte_servi' => $request->qteServi
        ]);



        $produit = StockProduit::where('produit_id', $bon->produit_id)->where('fournisseur_id', $bon->fournisseur_id)->first();



        $nouveauSolde = $fournisseur->solde + ($request->qteServi * $produit->prix);
        $produit->update([
            'qte' => $produit->qte - $bon->qte_servi
        ]);
        // dd($nouveauSolde);
        $fournisseur->update([
            'solde' => $nouveauSolde
        ]);


        if ($bon->livraison->livre_par == 2) { // 2 = récupération par le client
            // la livraison liée à la commande
            $commande = DB::select("select c.* from commande c, detail_commande d, livraison l where c.id = d.`commande_id` and d.`id`=l.`detail_commande_id` and l.id=" . $bon->livraison->id)[0];
            $totalQteALivrer = Commande::find($commande->id)->detailCommande->sum('qte');


            $lesLivraisons = Commande::join('detail_commande', 'detail_commande.commande_id', '=', 'commande.id')
                ->join('livraison', 'livraison.detail_commande_id', '=', 'detail_commande.id')
                ->where('commande.id', $commande->id)
                ->get();

            $total = $lesLivraisons->count();

            $qteLivree = 0;

            foreach ($lesLivraisons as $livraison) {
                if ($livraison->etat_livraison == 'LIVREE') {
                    // $qteLivree++;
                    $qteLivree += $livraison->qte;
                }
            }

            if ($totalQteALivrer == $qteLivree) {

                //finaliser la commande
                DB::update('update commande set etat_commande = 3 where id = ?', [$commande->id]);
            }
        }

        // dd($request->qteServi);

        return redirect()->route('sellers.bon.detail', $code);
    }

    // Afficher le formulaire d'enregistrement d'un fournisseur


    public function listePaiements()
    {
        $fournisseur = Fournisseur::where('user_id', Auth::user()->id)->first();
        $demandes = DemandePaiement::where('user_id', Auth::user()->id)
            ->with('modePaiement')
            ->orderByDesc('created_at')
            ->get();

        return view('fournisseur.listeDesPaiements', [
            'fournisseur'      => $fournisseur,
            'demandes'         => $demandes,
            'config'           => Configuration::first(),
            'totalDemandes'    => $demandes->count(),
            'totalPayees'      => $demandes->where('paye', 1)->count(),
            'totalNonPayees'   => $demandes->where('paye', '!=', 1)->count(),
            'montantPaye'      => (float) $demandes->where('paye', 1)->sum('montant'),
            'montantEnAttente' => (float) $demandes->where('paye', '!=', 1)->sum('montant'),
        ]);
    }

    public function loginPage()
    {
        return view('fournisseur.login');
    }

    // TRAITEMENT DE L'AUTHENTIFICATION
    public function validLogin(Request $request)
    {
        // dd('jsjs');
        $user = User::where('login', $request->login)->where('type_user_id', 5)->first();

        if (!$user) {
            // dd('pas trouvé');
            return redirect()->route('sellers.login')->with('fail', 'mot de passe ou login incorrect 1');

        } else {
            // dd('trouvé');

            if (Help::HashVerifier($request->password, $user->password)) {
                if($user->statut == 2){
                    return back()->with('block', "Vous ne pouvez pas vous connecter pour le moment. Veuillez contacter l'administrateur pour plus d'information");
                }
                $request->session()->regenerate();
                Auth::login($user);
                return redirect()->route('sellers.home');
            } else {
                return redirect()->route('sellers.login')->with('fail', 'mot de passe ou login incorrect 2');

            }
        }

    }

    public function demandeDepaieFournisseur()
    {

        $data['modesPaie'] = ModePaiement::liste();
        $data['frs'] = Fournisseur::where('user_id', Auth::user()->id)->first();

        return view('fournisseur.fournisseurDemandePaiement', $data);
    }

    public function demandeDepaieFournisseurTraitement(Request $request)
    {
        if ($request->montant == 0) {
            return redirect()->route('sellers.demandeDepaieFournisseur')->with('error', '0fcfa n\'est pas autorisé comme montant');
        }

        //livreur
        $user = Auth::user();
        if ($request->montant > $user->getFournisseur->solde) {

            return redirect()->route('sellers.demandeDepaieFournisseur')->with('error', 'Veuillez entrer un montant inférieur ou égale à votre solde');
        }

        $user->getFournisseur->update([
            'solde' => $user->getFournisseur->solde - $request->montant
        ]);


        $demande = demandePaiement::create([
            //  'numero' => Help::getCommandeNo(),
            'montant' => $request->montant,
            'numero_compte' => $request->numero,
            'user_id' => $user->id,
            'mode_paiement_id' => $request->modePaie,
        ]);

        return redirect()->route('sellers.demandeDepaieFournisseur')->with('success', 'Votre demande a été envoyée');
    }



    public function stock()
    {
        $this->verificationStock();
        // dd(Auth::user());

        // récuperation de l'id du fournisseur en fonction de id user
        $idFournisseur = Fournisseur::where('user_id', '=', Auth::user()->id)->first()->id;

        $fournisseur = Fournisseur::find($idFournisseur);

        return view(
            'fournisseur.stock',
            [
                'fournisseur' => $fournisseur
            ]
        );
    }

    public function addProducts()
    {

        $categories = Categorie::all();
        return view('fournisseur.add-products', [
            'categories' => $categories
        ]);
    }

    public function profile($id)
    {
        $this->verificationStock();

        $fournisseur = Fournisseur::find($id);
        $produits = $fournisseur->produits;
        $enlevement = Enlevement::where('fournisseur_id', $id)->where('statut', 2)->get();
        // dd($enlevement);

        return view('fournisseur.profile', [
            'fournisseur' => $fournisseur,
            'produits' => $produits,
            'enlevements' => $enlevement
        ]);
    }

    // Formulaire de modification d'un produit du stock
    public function editProducts($id)
    {
        $this->verificationStock();

        $produit = Produit::find($id);

        $idFournisseur = Fournisseur::where('user_id', '=', Auth::user()->id)->first()->id;
        $stocks = StockProduit::where('fournisseur_id', '=', $idFournisseur)
            ->where('produit_id', '=', $id)->first();


        return view('fournisseur.edit-products', [
            'produits' => $produit,
            'stock' => $stocks
        ]);
    }

    // Modification d'un produit du stock d'un fournisseur
    public function update(Request $request, $produit)
    {

        $request->validate([
            'seuil'    => 'required|integer|min:0|max:9999999',
            'quantite' => 'required|integer|min:0|max:9999999',
            'prix'     => 'required|numeric|min:0',
        ], [
            'seuil.required'    => 'Ce champs est obligatoire',
            'seuil.integer'     => 'Veuillez saisir un nombre entier',
            'seuil.max'         => 'La valeur du seuil ne peut pas dépasser 9 999 999',
            'quantite.required' => 'Ce champs est obligatoire',
            'quantite.integer'  => 'Veuillez saisir un nombre entier',
            'quantite.max'      => 'La quantité ne peut pas dépasser 9 999 999',
            'prix.required'     => 'Ce champs est obligatoire',
            'prix.numeric'      => 'Veuillez saisir un nombre',
        ]);

        try {
            // On recupère l'id du fournisseur courant
            $idFournisseur = Fournisseur::where('user_id', '=', Auth::user()->id)->first()->id;
            $stock = StockProduit::where('fournisseur_id', '=', $idFournisseur)
                ->where('produit_id', '=', $produit)->first();

            if ($stock) {
                $stock->prix = $request->prix;
                $stock->qte = $request->quantite;
                $stock->seuil_alert = $request->seuil;
                $stock->save();
            }

            session()->forget([
                'finDeStock',
                'produit_id'
            ]);
            
            $this->verificationStock();

            return redirect()->route('sellers.edit', $produit)->with('succes', 'Modification effectuée');
            
        } catch (\Exception $e) {
            \Log::error('Erreur update produit: ' . $e->getMessage() . ' - Ligne: ' . $e->getLine());
            return back()->with('error', 'Une erreur serveur est survenue : ' . $e->getMessage());
        }

    }



    public function parametreFournisseur()
    {
        return view('fournisseur.parametre', [
            'frs' => Fournisseur::where('user_id', Auth::user()->id)->first()
        ]);
    }

    public function FournisseurUpdate(Request $request)
    {

        $user = Auth::user();
        // dd(Hash::check($request->oldPassWord, $user->password));
        $frs = Fournisseur::where('user_id', $user->id)->first();

        if (User::where('login', $request->login)->first() && $request->login != $user->login) {
            return redirect()->route('sellers.parametreFournisseur')->with('loginExiste', 'Cet login est déjà utilisé');
        }
        if (User::where('email', $request->email)->first() && $request->email != $user->email) {
            return redirect()->route('sellers.parametreFournisseur')->with('emailExiste', 'Cet login est déjà utilisé');
        }



        if ($request->oldPassWord != null) {
            if ($request->newPassWord != null) {

                if ($request->newPassWord == $request->confirmPassWord) {

                    if (Help::HashVerifier($request->oldPassWord, $user->password)) {

                        Auth::user()->update([
                            'password' => Help::HashPassword($request->newPassWord)
                        ]);

                    } else {

                        return redirect()->route('sellers.parametreFournisseur')->with('errorPassword', 'Mauvais mot de passe');
                    }
                } else {
                    return redirect()->route('sellers.parametreFournisseur')->with('passDifferent', 'Les deux mots de passe ne correspondent pas');
                }
            }

        } else {
            if ($request->newPassWord != null) {
                return redirect()->route('sellers.parametreFournisseur')->with('avant', 'Remplissez le champs ANCIEN MOT DE PASSE SVP');
            }
        }


        $frs->update([
            'nom' => $request->nom,
            'prenom' => $request->prenom,
            'contact1' => $request->contact1,
            'contact2' => $request->contact2,
            'adresse_geo' => $request->adresse_geo,
            'adresse_postale' => $request->adresse_postale,

        ]);

        $user->update([
            'email' => $request->email,
            'login' => $request->login,
        ]);


        return redirect()->route('sellers.parametreFournisseur')->with('success', 'Les changement ont été appliqués');
    }

    private function verificationStock()
    {
        $fournisseur = Fournisseur::where('user_id', Auth::user()->id)->first();
        $produits = [];
        // dd($fournisseur);
        foreach ($fournisseur->produits as $produit) {

            if ($produit->pivot->qte < $produit->pivot->seuil_alert) {
                array_push($produits, $produit->id);
            }
        }

        if (count($produits) > 0) {
            session()->put([
                'finDeStock' => true,
                'produits' => $produits
            ]);
            # code...
        } else {
            session()->forget([
                'finDeStock',
                'produits'
            ]);
        }

    }
}
