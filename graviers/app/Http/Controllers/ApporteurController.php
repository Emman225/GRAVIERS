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
use Illuminate\Support\Str;
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
                   -- Montant NET de la commande recalculé depuis les lignes : la colonne
                   -- cde.montant_total contient le HT côté site et le NET côté mobile.
                   (IFNULL((SELECT SUM(d.prix * d.qte) FROM detail_commande d
                             WHERE d.commande_id = cde.id AND d.deleted_at IS NULL), 0)
                    + IFNULL(cde.cout_livraison_client, 0)
                    + IFNULL((SELECT t.montant FROM tva_commande t WHERE t.commande_id = cde.id LIMIT 1), 0)
                    - IFNULL(cde.remise, 0)) AS montant_total,
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

        // E-mail OU login, comme l'application mobile — voir le commentaire
        // détaillé dans LivreurController::login(). Ici c'était l'inverse du
        // site livreur : seul l'e-mail était accepté, et un apporteur qui
        // saisissait son login se voyait refuser ses identifiants.
        $identifiant = $request->email;
        $user = User::where(function ($q) use ($identifiant) {
                $q->where('email', $identifiant)->orWhere('login', $identifiant);
            })
            ->where('type_user_id',6)
            ->first();
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

        // L'entreprise paie l'apporteur par DEUX chemins : la demande qu'il
        // initie, et le règlement qu'un administrateur saisit sur une de ses
        // commissions. Cet écran ne montrait que le premier — il ne voyait donc
        // nulle part les sommes versées à notre propre initiative, et son
        // historique ne retombait pas sur ce qu'il avait réellement reçu.
        $demandes = DemandePaiement::where('user_id', $user->id)
            ->with('modePaiement')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (DemandePaiement $d) => (object) [
                'reference'     => $d->numero ?: ('#' . $d->id),
                'date'          => $d->created_at,
                'montant'       => (float) $d->montant,
                // 1 = acceptée, 2 = refusée, NULL/0 = en attente.
                'statut'        => (int) ($d->paye ?? 0),
                'mode'          => $d->modePaiement?->libelle,
                'date_paiement' => (int) $d->paye === 1 ? $d->updated_at : null,
                'origine'       => 'Vous',
                'detail'        => 'Demande de paiement',
            ]);

        // Les règlements issus d'une demande sont EXCLUS : ils portent
        // `demande_paiement_id` et sont déjà listés sous leur demande. Sans
        // cette exclusion, le même versement apparaîtrait deux fois.
        //
        // La colonne vient d'une migration : sans elle, on s'en passe plutôt
        // que de priver l'apporteur de sa page.
        $reglements = collect();

        if ($apporteur) {
            $reglements = \App\Models\PaiementApporteur::with(['modePaiement', 'commission'])
                ->where('apporteur_id', $apporteur->id)
                ->where('statut', 1)
                ->when(
                    \Illuminate\Support\Facades\Schema::hasColumn('paiement_apporteur', 'demande_paiement_id'),
                    fn ($q) => $q->whereNull('demande_paiement_id')
                )
                ->orderByDesc('date_paiement')
                ->get()
                ->map(fn ($p) => (object) [
                    'reference'     => $p->reference ?: ('#' . $p->id),
                    'date'          => $p->date_paiement ?? $p->created_at,
                    'montant'       => (float) $p->montant,
                    // Un règlement enregistré est un versement fait.
                    'statut'        => 1,
                    'mode'          => $p->modePaiement?->libelle,
                    'date_paiement' => $p->date_paiement ?? $p->created_at,
                    'origine'       => "L'entreprise",
                    'detail'        => $p->commission
                        ? 'Commission ' . ($p->commission->numero ?: '#' . $p->commission->id)
                        : 'Règlement de commission',
                ]);
        }

        $mouvements = $demandes->concat($reglements)
            ->sortByDesc(fn ($m) => $m->date)
            ->values();

        $recus   = $mouvements->where('statut', 1);
        $attente = $mouvements->where('statut', 0);

        return view('apporteur.Paiement', [
            'mouvements'       => $mouvements,
            'apporteur'        => $apporteur,
            'totalDemandes'    => $mouvements->count(),
            'totalEnAttente'   => $attente->count(),
            'totalPayees'      => $recus->count(),
            'totalRefusees'    => $mouvements->where('statut', 2)->count(),
            'montantPaye'      => (float) $recus->sum('montant'),
            'montantEnAttente' => (float) $attente->sum('montant'),
        ]);
    }


    public function profile(){
        return view('apporteur.profile');
    }

    /**
     * Enregistre une pièce d'identité déposée depuis le formulaire PUBLIC
     * d'inscription, sous un nom que l'envoyeur ne contrôle pas.
     *
     * L'extension est déduite du CONTENU du fichier (extension()) et non de celle
     * qu'annonce l'envoyeur (getClientOriginalExtension()) ; elle est en outre
     * confrontée à une liste blanche. Le nom, lui, est aléatoire : aucune donnée
     * de la requête n'y entre, ce qui exclut aussi bien la double extension que
     * l'écriture hors du dossier prévu.
     *
     * @return string chemin relatif au disque « public », à stocker en base
     */
    private function enregistrerPieceIdentite($fichier, string $prefixe): string
    {
        $extension = strtolower((string) $fichier->extension());

        // Filet de sécurité : la validation mimes: en amont laisse déjà passer
        // uniquement ces formats, mais on ne s'en remet pas à un seul contrôle
        // pour un formulaire ouvert à tous.
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'pdf'], true)) {
            $extension = 'bin';
        }

        $nom = $prefixe . '-' . date('YmdHis') . '-' . Str::random(20) . '.' . $extension;

        return $fichier->storeAs('piecesApporteurs', $nom, 'public');
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

                // Ce formulaire est PUBLIC : n'importe quel visiteur peut y déposer
                // deux fichiers, qui atterrissent dans un dossier servi par le web.
                // Le nom du fichier ne doit donc rien devoir à ce que l'envoyeur
                // fournit. L'ancienne construction reprenait deux valeurs de la
                // requête :
                //   - getClientOriginalExtension(), l'extension ANNONCÉE par
                //     l'envoyeur : un fichier au contenu d'image valide mais nommé
                //     « .php » passait le contrôle de type (qui déduit l'extension du
                //     contenu) et était pourtant écrit avec l'extension « .php » ;
                //   - nom_prenom, simplement validé comme une chaîne : un « ../ »
                //     permettait d'écrire hors du dossier prévu.
                // Trois fichiers .php déposés le 14/06/2025 par ce formulaire sont
                // encore présents dans storage/app/public/piecesApporteurs.
                //
                // On génère désormais un nom aléatoire et une extension déduite du
                // CONTENU réel du fichier, en repassant par le disque « public ».
                $recto = $this->enregistrerPieceIdentite($request->file('recto'), 'recto');
                $verso = $this->enregistrerPieceIdentite($request->file('verso'), 'verso');

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

                    // Email NON bloquant : le token vient d'être effacé en base. Si l'envoi
                    // échoue, l'apporteur voyait une erreur 500 alors que son compte était
                    // confirmé — et un second essai répondait « déjà vérifié ».
                    try {
                        Mail::send(new ConfirmationCreationCompteApporteur($user->nom_prenoms, $user->email));
                    } catch (\Throwable $e) {
                        \Log::warning('Email confirmation apporteur non envoyé: '.$e->getMessage());
                    }

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
