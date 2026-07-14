<?php

namespace App\Http\Controllers;

use PDF;
use Help;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Livreur;
use App\Models\Commande;
use App\Models\TypeUser;
use App\Models\Vehicule;
use App\Models\Livraison;
use App\Models\Enlevement;
use App\Models\TypeVehicule;
use Illuminate\Http\Request;
use App\Models\DemandePaiement;
use Illuminate\Support\Facades\DB;
//use Illuminate\Support\Carbon;
use Illuminate\Support\facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\LivreurRequest;
use App\Models\DetailCommande;
use \Cviebrock\EloquentSluggable\Services\SlugService;

class LivreurController extends Controller
{




    public function modificationVehicule(Vehicule $vehicule)
    {

        return view('livreur.modifierVehicule', [
            'vehicule' => $vehicule,
            'types' => TypeVehicule::all(),
            'mode' => "modif",
        ]);
    }

    public function modificationVehiculeTraitement(Vehicule $vehicule, Request $request){

        $request->validate([
            'matricule'=> 'required|string|max:15',
            'marque' => 'required|string|max:100',
            'modele' => 'required|string|max:100',
            'type_vehicule' => 'required|integer|exists:type_vehicule,id',
            'capacite' => 'required|numeric|min:0.1',
            'nom' => 'required|string|max:100',
        ]);

        $livreur = Livreur::where('user_id', Auth::user()->id)->first();


        $mode = $request->mode;
        if ($mode == "ajout") {

            if (Vehicule::where('immatriculation', $request->matricule)->first()) {
                return redirect()->route('livreur.ajoutVehicule')->with('error', 'Cet matricule est déjà utilisé');
            }
        }
        // else {

        //     $rVehicule = json_decode($request->vehicule);

        //     $vehicule = Vehicule::find($rVehicule->id);
        // }


        $vehicule->immatriculation = $request->matricule;
        $vehicule->marque = $request->marque;
        $vehicule->modele = $request->modele;
        $vehicule->type_vehicule_id = $request->type_vehicule;
        $vehicule->capacite = intval($request->capacite);
        $vehicule->livreur_id = $livreur->id;
        $vehicule->nom = $request->nom;
        $vehicule->update();


        return redirect()->back()->with('success', 'Véhicule Modifié');


    }

    public function supressionVehicule(Vehicule $vehicule)
    {

        //dd($vehicule);

        $vehicule->update([
            'deleted_at' => date('Y-m-d H:i:s'),
            'statut' => 1,
        ]);
        return redirect()->route('livreur.listeVehicule')->with('success', 'Véhicule supprimé');
    }

    public function loginPage()
    {

        return view('livreur.login');
    }

    public function ajoutVehiculePage()
    {

        $vehicule = new Vehicule;
        //dd($vehicule);

        return view('livreur.ajoutVehicule', [
            'types' => TypeVehicule::all(),
            'vehicule' => $vehicule,
            'mode' => "ajout",
        ]);
    }

    public function actionBonEnlevement(Enlevement $enlevement, $action)
    {
        if ($action == 'accepter') {
            $enlevement->livraison->update([
                'accepte' => 1,
                'date_accord' => date('Y-m-d H:i:s'),
                'etat_livraison' => 2
            ]);
        } else {
            $enlevement->livraison->update([
                'accepte' => 3,
                'date_accord' => date('Y-m-d H:i:s'),

            ]);
        }

        return redirect()->route('livreur.bon')->with('success', "Vous avez $action la livraison");
    }

    public function listeDesDemandesDePaiement()
    {
        $livreur  = Livreur::where('user_id', Auth::user()->id)->first();
        $demandes = DemandePaiement::where('user_id', Auth::user()->id)
            ->with('modePaiement')
            ->orderByDesc('created_at')
            ->get();

        return view('livreur.listeDesDemandesDePaiement', [
            'livreur'          => $livreur,
            'demandes'         => $demandes,
            'totalDemandes'    => $demandes->count(),
            'totalEnAttente'   => $demandes->where('paye', 0)->count(),
            'totalPayees'      => $demandes->where('paye', 1)->count(),
            'totalRefusees'    => $demandes->where('paye', 2)->count(),
            'montantEnAttente' => (float) $demandes->where('paye', 0)->sum('montant'),
            'montantPaye'      => (float) $demandes->where('paye', 1)->sum('montant'),
        ]);
    }

    public function ajoutVehicule(Request $request)
    {
        $request->validate([
            'matricule'=> 'required|string|max:15',
            'marque' => 'required|string|max:100',
            'modele' => 'required|string|max:100',
            'type_vehicule' => 'required|integer|exists:type_vehicule,id',
            'capacite' => 'required|numeric|min:0.1',
            'nom' => 'required|string|max:100',
        ]);
        // dd($request->matricule, $request->marque, $request->modele,$request->poids, $request->capacite);
        $livreur = Livreur::where('user_id', Auth::user()->id)->first();
        // $vehiculeData = [
        //     'immatriculation' => $request->matricule,
        //     'marque' => $request->marque,
        //     'modele' => $request->modele,
        //     'type_vehicule_id' => $request->type_vehicule,
        //     'capacite' =>intval($request->capacite),
        //     'livreur_id' => $livreur->id,
        //     'nom' => $request->nom,
        // ];
        //dd($vehiculeData);

        $mode = $request->mode;
        if ($mode == "ajout") {

            if (Vehicule::where('immatriculation', $request->matricule)->first()) {
                return redirect()->route('livreur.ajoutVehicule')->with('error', 'Cet matricule est déjà utilisé');
            }
        } else {
            //mode modification -
            $rVehicule = json_decode($request->vehicule);
            //dd($rVehicule->id);
            $vehicule = Vehicule::find($rVehicule->id);
        }
        //requete d'insertion
        //$dateHrDuJr = Carbon::now();
        // $sql = "INSERT INTO vehicule ( `immatriculation`, `type_vehicule_id`, `livreur_id`, `nom`, `capacite`, `updated_at`, `created_at`) VALUES (?,?,?,?,?,?,?) ";
        // DB::select($sql, [1]);
        //dd($vehiculeData);
        //$vehicule = Vehicule::create($vehiculeData);

        //dd($vehicule);
        $vehicule = new Vehicule;
        $vehicule->immatriculation = $request->matricule;
        $vehicule->marque = $request->marque;
        $vehicule->modele = $request->modele;
        $vehicule->type_vehicule_id = $request->type_vehicule;
        $vehicule->capacite = intval($request->capacite);
        $vehicule->livreur_id = $livreur->id;
        $vehicule->nom = $request->nom;
        $vehicule->save();

        if ($mode != "ajout") {
            return redirect()->route('livreur.listeVehicule')->with('success', 'Véhicule enregistré');
        }

        return redirect()->route('livreur.ajoutVehicule')->with('success', 'Véhicule enregistré');
    }

    public function login(Request $request)
    {
        $user = User::where('login', $request->login)->where('type_user_id', 8)->first();

        if ($user) {

            if (Help::HashVerifier($request->password, $user->password)) {

                if($user->statut == 2){
                    return back()->with('block', "Vous ne pouvez pas vous connecter pour le moment. Veuillez contacter l'administrateur pour plus d'information");
                }
                $request->session()->regenerate();
                // dd($d);
                Auth::login($user);
                // dd(Auth::user());
                return redirect()->route('livreur.home');
            } else {
                return redirect()->back()->withInput(['login'])->withInput(['login'])->with('fail','Mot de passe ou login incorrect.');
            }
        } else {
            return redirect()->back()->withInput(['login'])->with('fail','Mot de passe ou login incorrect.');
        }
    }

    public function home()
    {
        $livreur = Livreur::where('user_id', Auth::user()->id)->first();

        $now                  = Carbon::now();
        $startOfMonth         = $now->copy()->startOfMonth();
        $startOfPreviousMonth = $now->copy()->subMonth()->startOfMonth();
        $endOfPreviousMonth   = $now->copy()->subMonth()->endOfMonth();
        $startOfWeek          = $now->copy()->startOfWeek();
        $today                = $now->copy()->startOfDay();

        $livraisonEffectuees = Livraison::where('livreur_id', $livreur->id)
                                        ->where('accepte', 1)
                                        ->where('etat_livraison', 'LIVREE')
                                        ->get();

        $livraisonAttente = Livraison::where('livreur_id', $livreur->id)
                                     ->where('accepte', 2)
                                     ->where('etat_livraison', 'EN ATTENTE')
                                     ->get();

        // Livraisons acceptées mais pas encore livrées
        $livraisonEnCours = Livraison::where('livreur_id', $livreur->id)
                                     ->where('accepte', 1)
                                     ->where('etat_livraison', 'EN ATTENTE')
                                     ->count();

        $livraisonsAujourdhui = Livraison::where('livreur_id', $livreur->id)
                                         ->where('etat_livraison', 'LIVREE')
                                         ->whereDate('updated_at', $today)
                                         ->count();

        $livraisonsSemaine = Livraison::where('livreur_id', $livreur->id)
                                      ->where('etat_livraison', 'LIVREE')
                                      ->where('updated_at', '>=', $startOfWeek)
                                      ->count();

        $livraisonsMois = Livraison::where('livreur_id', $livreur->id)
                                   ->where('etat_livraison', 'LIVREE')
                                   ->where('updated_at', '>=', $startOfMonth)
                                   ->count();

        // Gains = coûts de livraison des livraisons LIVREES (ce que le livreur a gagné
        // en livrant). L'ancien calcul lisait paiement_livreur, table morte depuis que
        // l'enregistrement manuel est neutralisé (les paiements réels passent par les
        // demandes mobiles) -> la carte "Gain ce mois" restait toujours à 0.
        $gainMensuel = (float) Livraison::where('livreur_id', $livreur->id)
            ->where('etat_livraison', 'LIVREE')
            ->whereMonth('date_livraison', $now->format('m'))
            ->whereYear('date_livraison', $now->format('Y'))
            ->sum('cout_livraison');

        $gainMoisPrecedent = (float) Livraison::where('livreur_id', $livreur->id)
            ->where('etat_livraison', 'LIVREE')
            ->whereBetween('date_livraison', [$startOfPreviousMonth, $endOfPreviousMonth])
            ->sum('cout_livraison');

        $evolutionGain = $gainMoisPrecedent > 0
            ? (($gainMensuel - $gainMoisPrecedent) / $gainMoisPrecedent) * 100
            : ($gainMensuel > 0 ? 100 : 0);

        $gainTotal = (float) Livraison::where('livreur_id', $livreur->id)
            ->where('etat_livraison', 'LIVREE')
            ->sum('cout_livraison');

        // Top 5 clients les plus livrés
        $topClients = Livraison::selectRaw('client_id, COUNT(*) as total_livraisons')
            ->where('livreur_id', $livreur->id)
            ->where('etat_livraison', 'LIVREE')
            ->whereNotNull('client_id')
            ->groupBy('client_id')
            ->orderByDesc('total_livraisons')
            ->limit(5)
            ->with('client')
            ->get();

        // Dernières livraisons (toutes confondues)
        $dernieresLivraisons = Livraison::where('livreur_id', $livreur->id)
            ->with(['client', 'commande', 'produit'])
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        return view('livreur.dashboard', [
            'livreur'              => $livreur,
            'livraisonEffectuees'  => $livraisonEffectuees,
            'livraisonAttente'     => $livraisonAttente,
            'livraisonEnCours'     => $livraisonEnCours,
            'livraisonsAujourdhui' => $livraisonsAujourdhui,
            'livraisonsSemaine'    => $livraisonsSemaine,
            'livraisonsMois'       => $livraisonsMois,
            'gainMensuel'          => $gainMensuel,
            'gainMoisPrecedent'    => $gainMoisPrecedent,
            'evolutionGain'        => $evolutionGain,
            'gainTotal'            => $gainTotal,
            'topClients'           => $topClients,
            'dernieresLivraisons'  => $dernieresLivraisons,
        ]);
    }

    public function listeVehicule()
    {
        $livreur = Livreur::where('user_id', Auth::user()->id)->first();
        return view('livreur.listeVehicule', [
            'vehicules' => Vehicule::orderByDesc('created_at')->where('livreur_id',$livreur->id)->where('deleted_at', null)->get()
        ]);
    }

    public function vehiculeDispo(Vehicule $vehicule)
    {

    // $vehicule->dispnible = !$vehicule->disponible;

        // dd($vehicule);

        if ($vehicule->disponible == 0) {
            $vehicule->update([
                'disponible' => 1
            ]);
            $msg = "Le vehicule $vehicule->immatriculation est maintenant disponible";
        } else {
            $vehicule->update([
                'disponible' => 0
            ]);
            $msg = "Le vehicule $vehicule->immatriculation n'est plus disponible";
        }



        return redirect()->route('livreur.listeVehicule')->with('success', $msg);
    }

    public function bon()
    {
        $idLivreur = Livreur::where('user_id', Auth::user()->id)->value('id');

        $enlevements = Enlevement::where('livreur_id', $idLivreur)
            ->whereNull('fournisseur_validation')
            ->with(['fournisseur', 'produit', 'livraison.vehicule'])
            ->orderByDesc('created_at')
            ->get()
            ->filter(function ($e) {
                return $e->qte_servi === null
                    && optional($e->livraison)->accepte != 3
                    && $e->fournisseur !== null;
            })
            ->values();

        $aAccepter = $enlevements->filter(fn($e) => optional($e->livraison)->accepte == 2)->count();
        $accepted  = $enlevements->filter(fn($e) => optional($e->livraison)->accepte == 1)->count();

        return view('livreur.bon', [
            'enlevements'  => $enlevements,
            'totalBons'    => $enlevements->count(),
            'aAccepter'    => $aAccepter,
            'accepted'     => $accepted,
        ]);
    }

    public function bonValides()
    {
        $idLivreur = Livreur::where('user_id', Auth::user()->id)->value('id');

        $enlevements = Enlevement::where('livreur_id', $idLivreur)
            ->whereNotNull('fournisseur_validation')
            ->with(['fournisseur', 'produit', 'livraison.vehicule'])
            ->orderByDesc('updated_at')
            ->get()
            ->filter(function ($e) {
                return $e->qte_servi !== null
                    && optional($e->livraison)->accepte == 1
                    && $e->fournisseur !== null;
            })
            ->values();

        $totalQte = (float) $enlevements->sum(function ($e) {
            return (float) ($e->qte_servi ?? $e->qte ?? 0);
        });

        return view('livreur.bonValides', [
            'enlevements' => $enlevements,
            'totalBons'   => $enlevements->count(),
            'totalQte'    => $totalQte,
        ]);
    }

    public function validationLivraison(Request $request)
    {
        // dd($request->code);
        $livreur = Livreur::where('user_id', Auth::user()->id)->first();

        $livraison = Livraison::where('numero', $request->code)->where('livreur_id', $livreur->id)->first();

        // dd($livraison->adresse_livraison_id);

        if ($livraison) {

            if ($livraison->statut == 2) {

                return back()->with('info', 'Vous avez déjà validé cette livraison');
            }

            $livraison->update([
                'etat_livraison' => 3,
                'statut' => 1
                //'statut' => 2
            ]);

            if ($livraison->provenance == 'COMMANDE') {
                $livraison->detailCommande->update([
                    'etat_livraison' => 3
                ]);

                $commande = DB::select("select c.* from commande c, detail_commande d, livraison l where c.id = d.`commande_id` and d.`id`=l.`detail_commande_id` and l.id=$livraison->id")[0];

                // $lesLivraisons = DB::select("select l.* from commande c, detail_commande d, livraison l where c.id = d.`commande_id` and d.`id`=l.`detail_commande_id` and c.id=$commande->id");

                $lesLivraisons = Commande::join('detail_commande', 'detail_commande.commande_id', '=', 'commande.id')
                    ->join('livraison', 'livraison.detail_commande_id', '=', 'detail_commande.id')
                    ->where('commande.id', $commande->id)
                    ->get();

                $total = $lesLivraisons->count();

                $cpte = 0;

                foreach ($lesLivraisons as $livraison) {
                    if ($livraison->etat_livraison == 'LIVREE') {
                        $cpte++;
                    }
                }

                if ($total == $cpte) {

                    //finaliser la commande
                    DB::update('update commande set etat_commande = 3 where id = ?', [$commande->id]);
                }

                //recuperer la quantité de tous les detailCommande
                //$totalDetailCommande = DetailCommande::where('commande_id', $commande->id)->sum('qte');

                //$laCommande = $livraison->commande;

                //$totalDetailCommande = $laCommande->detailCommande->sum('qte');

                // $totalQteLivree = $laCommande->livraisons->where('etat_livraison','LIVREE')->sum('qte');

                // if($totalDetailCommande == $totalQteLivree){
                //     $livraison->commande->update([
                //         'etat_commande' => 3
                //     ]);
                // }

            } else { //demande de livraison

                $demandeLivraison = $livraison->detailLivraison->demandeLivraison;
                $qteALivrer = $demandeLivraison->detailLivraison->sum('qte');


                // $demandeLivraison = Livraison::where('adresse_livraison_id',$livraison->adresse_livraison_id)->get();
                // $totals = $demandeLivraison->count();
                $qteLivree = 0;

                foreach ($demandeLivraison->livraisons as $livraison) {
                    if ($livraison->etat_livraison == 'LIVREE') {
                        $qteLivree += $livraison->qte;
                    }
                }

                // dd($qteALivrer,$qteLivree);
                if ($qteALivrer == $qteLivree) {
                    $livraison->detailLivraison->demandeLivraison->update([
                        'etat_commande' => 3
                    ]);
                }
            }

            $livreur->update([
                'solde' => $livreur->solde + $livraison->cout_livraison
            ]);

            // dd('ok');

            return redirect()->route('livreur.livraisonValides')->with('success', 'Livraison validée: ' . $request->code);
        } else {
            return back()->with('info', 'Code de livraison invalide');
        }
    }

    public function enRoute(Livraison $livraison)
    {
        $livraison->update([
            'etat_livraison' => 3
        ]);

        $livraison->detailLivraison->demandeLivraison->update([
            'etat_commande' => 2
        ]);

        return redirect()->route('livreur.livraison')->with('info', 'Livraison en cours...');
    }

    public function livraison()
    {

        // Récuperation de l'identifiant du livreur courant
        $idLivreur = Livreur::where('user_id', '=', Auth::user()->id)->value('id');


        // Récuperation des bons d'enlèvement liès aux livraisons
        $enlevements = Enlevement::where('livreur_id', '=', $idLivreur)->get();
        // livre_par est un SMALLINT (1 = LIVREUR, 2 = CLIENT). Comparer à la chaîne
        // 'LIVREUR' (castée en 0 par MySQL) ne matchait JAMAIS -> liste toujours vide.
        $livraisons = Livraison::where('livreur_id', $idLivreur)->where('livre_par', 1)->orderBy('created_at', 'desc')->get();
        return view('livreur.livraison', [
            'enlevements' => $enlevements,
            'livraisons' => $livraisons
        ]);
    }

    public function bonRecherche(Request $request)
    {
        $livreur = Livreur::where('user_id', Auth::user()->id)->first();
        $enlevement = Enlevement::where('livreur_id', $livreur->id)->where('code_enleve', $request->code)->first();
        return view('livreur.bonDetail', [
            'enlevement' => $enlevement
        ]);
    }

    public function bonImprime(Enlevement $enlevement)
    {

        $data['enlevement'] = $enlevement;
        $pdf = PDF::loadView('livreur.bonImprime', $data);
        return $pdf->download($enlevement->code_enleve . '.pdf');
    }

    public function afficheBon(Enlevement $enlevement)
    {

        $data['enlevement'] = $enlevement;
        $pdf = PDF::loadView('livreur.bonImprime', $data);
        return $pdf->stream();
    }

    public function livraisonValides()
    {
        $idLivreur = Livreur::where('user_id', Auth::user()->id)->value('id');

        // livre_par est un SMALLINT (1 = LIVREUR, 2 = CLIENT). L'ancienne comparaison
        // à la chaîne 'LIVREUR' (castée en 0 par MySQL) excluait TOUTES les livraisons
        // -> la DataTable restait vide alors que le mobile affichait "Effectuée".
        $livraisons = Livraison::where('livreur_id', $idLivreur)
            ->where('accepte', 1)
            ->where('etat_livraison', 'LIVREE')
            ->where('livre_par', 1)
            ->with(['client', 'vehicule', 'enlevement.produit', 'detailLivraison', 'AdresseLivraison'])
            ->orderByDesc('updated_at')
            ->get()
            ->filter(fn($l) => $l->client !== null)
            ->values();

        $now = Carbon::now();
        $livraisonsCeMois = $livraisons->filter(fn($l) => optional($l->updated_at)->month === $now->month && optional($l->updated_at)->year === $now->year)->count();

        return view('livreur.livraisonValides', [
            'livraisons'        => $livraisons,
            'totalLivraisons'   => $livraisons->count(),
            'livraisonsCeMois'  => $livraisonsCeMois,
        ]);
    }

    public function livreurDisponible()
    {
        return view('livreur.list', [
            'livreurs' => Livreur::all()
        ]);
    }





    public function detailBon(Enlevement $enlevement)
    {
        return view('livreur.bonDetail', [
            'enlevement' => $enlevement
        ]);
    }

    public function bonValidation(Enlevement $enlevement)
    {
        // dd($enlevement);

        $enlevement->update([
            'livreur_validation' => date('Y-m-d H:i:s')
        ]);

        return redirect()->route('livreur.bon.detail', $enlevement);
    }

    public function parametreLivreur()
    {
        return view('livreur.parametre', [
            'livreur' => Livreur::where('user_id', Auth::user()->id)->first()
        ]);
    }

    public function parametreLivreurUpdate(Request $request)
    {

        $user = Auth::user();
        // dd(Hash::check($request->oldPassWord, $user->password));
        $livreur = Livreur::where('user_id', $user->id)->first();

        if (User::where('login', $request->login)->first() && $request->login != $user->login) {
            return redirect()->route('livreur.parametreLivreur')->with('loginExiste', 'Cet login est déjà utilisé');
        }
        if (User::where('email', $request->email)->first() && $request->email != $user->email) {
            return redirect()->route('livreur.parametreLivreur')->with('emailExiste', 'Cet login est déjà utilisé');
        }



        if ($request->oldPassWord != null) {
            if ($request->newPassWord != null) {

                if ($request->newPassWord == $request->confirmPassWord) {

                    if (Help::HashVerifier($request->oldPassWord, $user->password)) {

                        Auth::user()->update([
                            'password' => Help::HashPassword($request->newPassWord)
                        ]);
                    } else {

                        return redirect()->route('livreur.parametreLivreur')->with('errorPassword', 'Mauvais mot de passe');
                    }
                } else {
                    return redirect()->route('livreur.parametreLivreur')->with('passDifferent', 'Les deux mots de passe ne correspondent pas');
                }
            }
        } else {
            if ($request->newPassWord != null) {
                return redirect()->route('livreur.parametreLivreur')->with('avant', 'Remplissez le champs ANCIEN MOT DE PASSE SVP');
            }
        }


        $user->update([
            'email' => $request->email,
            'contact' => $request->contact,
            'login' => $request->login,
            'adresse' => $request->adresse,
            'nom_prenoms' => $request->nom_prenoms
        ]);

        $livreur = Livreur::where('user_id', Auth::user()->id)->first();


        $livreur->update([
            'nom' => $request->nom,
            'prenom' => $request->prenom,
        ]);


        return redirect()->route('livreur.parametreLivreur')->with('success', 'Les changement ont été appliqués');
    }
}
