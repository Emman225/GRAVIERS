<?php

namespace App\Http\Controllers;

use Help;
use App\Models\User;
use App\Models\Client;
use App\Models\Paiement;
use App\Models\TypeUser;
use App\Models\Apporteur;
use Illuminate\Http\Request;
use App\Mail\confirmationEmail;
use App\Models\DemandePaiement;
use Illuminate\Support\Facades\DB;
use App\Models\CommissionApporteur;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Http\Requests\ApporteurRequest;
use App\Mail\confirmationTokenApporteur;
use App\Mail\ConfirmationCreationCompteApporteur;
use Cviebrock\EloquentSluggable\Services\SlugService;




class ApporteurController extends Controller
{
    //

    public function home(){
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('apporteur.login')->with('failInfo', 'Veuillez vous reconnecter.');
        }

        $apporteur = Apporteur::where('user_id', $user->id)->first();
        if (!$apporteur) {
            return redirect()->route('apporteur.login')->with('failInfo', 'Compte apporteur introuvable. Veuillez vous reconnecter.');
        }

        $now                  = \Carbon\Carbon::now();
        $startOfMonth         = $now->copy()->startOfMonth();
        $startOfPreviousMonth = $now->copy()->subMonth()->startOfMonth();
        $endOfPreviousMonth   = $now->copy()->subMonth()->endOfMonth();

        $clients = Client::where('code_parrain', $apporteur->code)->get();

        $idApporteur = $apporteur->id;

        // Gains via les commissions apporteur
        $gainMensuel = (float) CommissionApporteur::where('apporteur_id', $idApporteur)
            ->whereMonth('created_at', $now->format('m'))
            ->whereYear('created_at', $now->format('Y'))
            ->sum('montant');

        $gainMoisPrecedent = (float) CommissionApporteur::where('apporteur_id', $idApporteur)
            ->whereBetween('created_at', [$startOfPreviousMonth, $endOfPreviousMonth])
            ->sum('montant');

        $evolutionGain = $gainMoisPrecedent > 0
            ? (($gainMensuel - $gainMoisPrecedent) / $gainMoisPrecedent) * 100
            : ($gainMensuel > 0 ? 100 : 0);

        $gainTotal = (float) CommissionApporteur::where('apporteur_id', $idApporteur)->sum('montant');

        $totalCommissions   = CommissionApporteur::where('apporteur_id', $idApporteur)->count();
        $commissionsMois    = CommissionApporteur::where('apporteur_id', $idApporteur)
            ->where('created_at', '>=', $startOfMonth)->count();

        // Top 5 filleul(e)s les plus rentables
        $topFilleules = DB::select("
            SELECT cli.id, cli.nom, cli.prenom, cli.contact1,
                   COUNT(DISTINCT com.commande_id) AS nb_commandes,
                   COALESCE(SUM(com.montant), 0) AS total_commission
            FROM commission_apporteur com
            JOIN commande cde ON com.commande_id = cde.id
            JOIN client cli   ON cli.id = cde.client_id
            WHERE com.apporteur_id = ?
            GROUP BY cli.id, cli.nom, cli.prenom, cli.contact1
            ORDER BY total_commission DESC
            LIMIT 5
        ", [$idApporteur]);

        $paiements = DB::select("
            SELECT cde.id AS commande_id,
                   cde.numero AS num_commande,
                   cde.created_at AS date_commande,
                   cde.montant_total,
                   CONCAT(cli.nom,' ',cli.prenom) AS client,
                   com.montant AS montant_recu,
                   com.created_at AS date_paiement,
                   com.statut_commission
            FROM commission_apporteur com
            JOIN commande cde ON com.commande_id = cde.id
            JOIN client cli   ON cli.id = cde.client_id
            WHERE com.apporteur_id = ?
            ORDER BY com.created_at DESC
        ", [$idApporteur]);

        return view('apporteur.dashboard', [
            'apporteur'         => $apporteur,
            'clients'           => $clients,
            'paiements'         => $paiements,
            'gainMensuel'       => $gainMensuel,
            'gainMoisPrecedent' => $gainMoisPrecedent,
            'evolutionGain'     => $evolutionGain,
            'gainTotal'         => $gainTotal,
            'totalCommissions'  => $totalCommissions,
            'commissionsMois'   => $commissionsMois,
            'topFilleules'      => $topFilleules,
        ]);
    }
    public function register(){
        return view('apporteur.register');
    }
    public function loginPage(){
        return view('apporteur.login');
    }



    // connexion de l'apporteur d'affaire
    public function login(Request $request){

        $user = User::where('email',$request->email)->where('type_user_id',6)->first();
        // dd($user);

        if($user){

            // On verifie si l'utilisateur a vérifié son email avant de le connecter grâce à un token null
               if(Help::HashVerifier($request->password, $user->password)){
                $token = $user->token;
                    if($token == null){
                        if($user->statut == 2){
                            return back()->with('block', "Vous ne pouvez pas vous connecter pour le moment. Veuillez contacter l'administrateur pour plus d'information");
                        }
                        // dd('token existe');
                        Auth::login($user);
                        $request -> session() -> regenerate();
                        // dd('vous êtes connecté');
                        return redirect()->route('apporteur.home');
                    }elseif($token){
                        return redirect()->route('apporteur.login')->with('failToken','Vous devez verifier votre email pour vous connecter');
                    }
               }else{
                // dd('fail');
                return redirect()->route('apporteur.login')->with('failInfo','L\'email ou le mot de passe ne correspond pas');
               }

        }else{
            return redirect()->route('apporteur.login')->with('failInfo','L\'email ou le mot de passe ne correspond pas');
        }

    }



    public function paiement(){
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('apporteur.login')->with('failInfo', 'Veuillez vous reconnecter.');
        }

        $apporteur = Apporteur::where('user_id', $user->id)->first();

        $paiements = DemandePaiement::where('user_id', $user->id)
            ->with('modePaiement')
            ->orderByDesc('created_at')
            ->get();

        $totalDemandes  = $paiements->count();
        $totalEnAttente = $paiements->where('paye', 0)->count();
        $totalPayees    = $paiements->where('paye', 1)->count();
        $totalRefusees  = $paiements->where('paye', 2)->count();
        $montantPaye    = (float) $paiements->where('paye', 1)->sum('montant');
        $montantEnAttente = (float) $paiements->where('paye', 0)->sum('montant');

        return view('apporteur.Paiement', [
            'paiements'        => $paiements,
            'apporteur'        => $apporteur,
            'totalDemandes'    => $totalDemandes,
            'totalEnAttente'   => $totalEnAttente,
            'totalPayees'      => $totalPayees,
            'totalRefusees'    => $totalRefusees,
            'montantPaye'      => $montantPaye,
            'montantEnAttente' => $montantEnAttente,
        ]);
    }


    public function profile(){
        return view('apporteur.profile');
    }

    // création du compte
    public function store(Request $request){

        // try {
            //code...

            $request->validate([
                "nom_prenom" => "required|string|min:3|max:255",
                "email" => "required|string|email:rfc,dns|max:255|unique:users,email",
                "contact" => "required|digits_between:8,15|unique:users,contact",
                "adresse" => "required|string|min:3|max:255",
                "recto" => "required|file|mimes:jpg,jpeg,png,pdf|max:5120",
                "verso" => "required|file|mimes:jpg,jpeg,png,pdf|max:5120",
                "password" => "required|string|min:8|max:100",
            ],[
                "nom_prenom.required" => "Le nom complet est obligatoire !",
                "nom_prenom.string" => "Le nom complet doit être une chaîne de caractères.",
                "nom_prenom.min" => "Le nom complet doit contenir au moins 3 caractères.",
                "nom_prenom.max" => "Le nom complet ne doit pas dépasser 255 caractères.",

                "email.required" => "L'adresse e-mail est obligatoire !",
                "email.string" => "L'adresse e-mail est invalide.",
                "email.email" => "Veuillez saisir une adresse e-mail valide.",
                "email.max" => "L'adresse e-mail ne doit pas dépasser 255 caractères.",
                "email.unique" => "Cet e-mail est déjà utilisé.",

                "contact.required" => "Le numéro de contact est obligatoire !",
                "contact.digits_between" => "Le numéro de contact doit contenir entre 8 et 15 chiffres.",
                "contact.unique" => "Ce numéro de contact est déjà utilisé.",

                "adresse.required" => "L'adresse est obligatoire !",
                "adresse.string" => "L'adresse doit être une chaîne de caractères.",
                "adresse.min" => "L'adresse doit contenir au moins 3 caractères.",
                "adresse.max" => "L'adresse ne doit pas dépasser 255 caractères.",

                "recto.required" => "La pièce recto est obligatoire !",
                "recto.file" => "Le fichier recto est invalide.",
                "recto.mimes" => "La pièce recto doit être au format JPG, JPEG, PNG ou PDF.",
                "recto.max" => "La pièce recto ne doit pas dépasser 5 Mo.",

                "verso.required" => "La pièce verso est obligatoire !",
                "verso.file" => "Le fichier verso est invalide.",
                "verso.mimes" => "La pièce verso doit être au format JPG, JPEG, PNG ou PDF.",
                "verso.max" => "La pièce verso ne doit pas dépasser 5 Mo.",

                "password.required" => "Le mot de passe est obligatoire !",
                "password.string" => "Le mot de passe est invalide.",
                "password.min" => "Le mot de passe doit contenir au moins 8 caractères.",
                "password.max" => "Le mot de passe ne doit pas dépasser 100 caractères.",
            ]);

            // dd($request->email);

            $userEmail = User::where('email',$request->email)->first();
            $userLogin = User::where('login', $request->email)->first();

            if ($request->hasFile('recto') && $request->hasFile('verso')) {

                // enregistrement de la carte recto
                $destination = base_path('public/storage/piecesApporteurs/');

                $recto = 'piecesApporteurs/recto'.'-'.$request->nom_prenom.''. date('YmdHis') .'.'.$request->file('recto')->getClientOriginalExtension();
                $request->file('recto')->move($destination, $recto);

                // enregistrement de la carte verso
                $destination = base_path('public/storage/piecesApporteurs/');
                $verso = 'piecesApporteurs/verso'.'-'.$request->nom_prenom.''. date('YmdHis') .'.'.$request->file('verso')->getClientOriginalExtension();
                $request->file('verso')->move($destination, $verso);
                // $path = $request->file('fichier')->move($destination, 'public');
                // dd($destination);

            }

            // dd($userEmail, $request->email,$userLogin);
            //  RECUPERATION DU TYPE USER
            $typeUserId = TypeUser::where('nom', 'like', '%apporteur%')->value('id');
            $source = $request->nom_prenom;
            $code = SlugService::createSlug(User::class, 'login', $source).Help::ChaineAleatoire(4); //Creation de login à partir de la methode SLUGGABLE
            // dd($request->_token);

            $numeroToken = Help::getNumberToken(4);
            // Enregistrement de la table user
            $dataUser = [
                'login' => $code,
                'password' => Help::HashPassword($request->password),
                'email' => $request->email,
                'adresse' => $request->adresse,
                'type_user_id' => $typeUserId,
                'contact' => $request->contact,
                'token' => $numeroToken,
                'nom_prenoms' => $request->nom_prenom,
                'statut' => 2
            ];
            // dd($dataUser);
            $user = User::create($dataUser);
            $userId = $user->id;

            $data = [
                'code' => $code,
                'solde' => 0.0,
                'user_id' => $userId,
                'piece_recto' => $recto,
                'piece_verso' => $verso,
                'zone_intervention' => $request->zone_intervention,

            ];
            $nom = $request->nom_prenom;

            $apporteurId = Apporteur::create($data);

            try {
                Mail::send(new confirmationTokenApporteur($nom, $request->email, $numeroToken));
            } catch (\Exception $e) {
                \Log::error('Erreur envoi email apporteur: ' . $e->getMessage());
            }

            return redirect()->route('apporteur.pageCode',['email' => $request->email])->with('succes','Succès, Veuillez confirmer votre Email !!');

        // } catch (\Throwable $th) {
        //     return view('layout.errorCatchBack');
        // }
    }

    public function pageDeCode(Request $request){

         return view('apporteur.confirmationToken',[
            'email' => $request->query('email'),

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

        // dd($user);

        if ($user) {
            if ($user->token) {
                if ($user->token === $request->token) {

                    // On vide le token et connecte l'utilisateur


                    DB::table('users')->where('email', $request->email)->update(['token' => null]);

                    // Auth::login($user);

                    Mail::send(new ConfirmationCreationCompteApporteur($user->nom_prenoms, $user->email));

                    return redirect()->route('apporteur.login')->with('success', 'Votre compte a bien été confirmé, connectez-vous !');
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

    public function confirmation($token){
        // dd($token);

        $tokenExisting = User::where('token',$token)->first();

        // dd($tokenExisting);

        if($tokenExisting == null){

            return redirect('/pageError');

        }else{
            $user = User::where('token',$token)->first();
            $user->update([
                'token' => null
            ]);
            Auth::login($user);
            return redirect()->route('apporteur.home');
        }

    }

    public function filleule(){
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('apporteur.login')->with('failInfo', 'Veuillez vous reconnecter.');
        }

        $apporteur = Apporteur::where('user_id',$user->id)->first();
        if (!$apporteur) {
            return redirect()->route('apporteur.login')->with('failInfo', 'Apporteur introuvable.');
        }

        // Statistiques de commission par filleul
        $statsParClient = DB::select("
            SELECT cde.client_id,
                   COUNT(DISTINCT com.commande_id) AS nb_commandes,
                   COALESCE(SUM(com.montant), 0) AS total_commission,
                   MAX(com.created_at) AS derniere_commission
            FROM commission_apporteur com
            JOIN commande cde ON com.commande_id = cde.id
            WHERE com.apporteur_id = ?
            GROUP BY cde.client_id
        ", [$apporteur->id]);

        $statsMap = collect($statsParClient)->keyBy('client_id');

        $clients = Client::where('code_parrain', $apporteur->code)
            ->orderBy('nom')
            ->get();

        $nbActifs   = $clients->where('statut', 1)->count();
        $nbATerme   = $clients->where('client_a_terme', 1)->count();
        $totalCommissionsFilleules = (float) collect($statsParClient)->sum('total_commission');

        return view('apporteur.filleule',[
            'clients'                   => $clients,
            'apporteur'                 => $apporteur,
            'statsMap'                  => $statsMap,
            'nbActifs'                  => $nbActifs,
            'nbATerme'                  => $nbATerme,
            'totalCommissionsFilleules' => $totalCommissionsFilleules,
        ]);
    }

    public function parametreApporteur(){
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('apporteur.login')->with('failInfo', 'Veuillez vous reconnecter.');
        }

        $apporteur = Apporteur::where('user_id', $user->id)->first();
        if (!$apporteur) {
            return redirect()->route('apporteur.login')->with('failInfo', 'Apporteur introuvable.');
        }

        return view('apporteur.parametre',[
            'user' => $user,
            'apporteur' => $apporteur
        ]);
    }



    public function ApporteurUpdate(Request $request){
        $user = Auth::user();
        // dd(Hash::check($request->oldPassWord, $user->password));
        $apporteur = Apporteur::where('user_id', $user->id)->first();

        if(User::where('login', $request->login)->first() && $request->login != $user->login ){
            return redirect()->route('apporteur.parametreApporteur')->with('loginExiste','Cet login est déjà utilisé');
        }
        if(User::where('email', $request->email)->first() && $request->email != $user->email ){
            return redirect()->route('apporteur.parametreApporteur')->with('emailExiste','Cet login est déjà utilisé');
        }



        if($request->oldPassWord != null){
            if($request->newPassWord != null){

                if($request->newPassWord == $request->confirmPassWord){

                    if(Help::HashVerifier($request->oldPassWord, $user->password)){

                        Auth::user()->update([
                            'password' => Hash::make($request->newPassWord)
                        ]);

                    }else{

                        return redirect()->route('apporteur.parametreApporteur')->with('errorPassword','Mauvais mot de passe');
                    }
                }else{
                    return redirect()->route('apporteur.parametreApporteur')->with('passDifferent','Les deux mots de passe ne correspondent pas');
                }
            }

        }else{
            if($request->newPassWord != null){
                return redirect()->route('apporteur.parametreApporteur')->with('avant','Remplissez le champs ANCIEN MOT DE PASSE SVP');
            }
        }


        DB::table('users')
            ->where('id',$user->id)
            ->update([
                'email' => $request->email,
                'contact' => $request->contact,
                'login' => $request->login,
                'nom_prenoms' => $request->nom_prenom,
                'adresse' => $request->adresse,
            ]);

        // $apporteur = Apporteur::where('user_id',$user->id)->first();

        // $apporteur->update([
        //     'nom' => $request->nom,
        //     'prenom' => $request->prenom,
        // ]);


        return redirect()->route('apporteur.parametreApporteur')->with('success','Les changement ont été appliqués');
    }

    // INSERTION DES INFOS DANS LA BASE DE DONNEE


}
