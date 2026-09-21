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
        // Enlèvement dont la livraison a été supprimée : ->livraison->update() sur null
        // renvoyait une erreur 500 au livreur au moment d'accepter ou de refuser le bon.
        if ($enlevement->livraison == null) {
            return back()->with('error', "Ce bon n'est plus rattaché à une livraison. Contactez le gestionnaire.");
        }

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
        $livreur = Livreur::where('user_id', Auth::user()->id)->first();

        // L'entreprise paie le livreur par DEUX chemins : la demande qu'il
        // initie, et le règlement qu'un administrateur saisit sur une de ses
        // courses. Cet écran ne montrait que le premier — il ne voyait donc
        // nulle part les sommes versées à notre propre initiative, et son
        // historique ne retombait pas sur ce qu'il avait réellement reçu.
        $demandes = DemandePaiement::where('user_id', Auth::user()->id)
            ->with('modePaiement')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (DemandePaiement $d) => (object) [
                'reference'     => $d->numero ?: ('#' . $d->id),
                'date'          => $d->created_at,
                'montant'       => (float) $d->montant,
                // 1 = acceptée, 2 = refusée, NULL/0 = en attente.
                'statut'        => (int) ($d->paye ?? 0),
                // Point 20 : validée → « À payer » → « Effectuée ».
                'etat_reglement' => $d->etat_reglement,
                'libelle_etat'   => $d->libelleReglement(),
                'mode'          => $d->modePaiement?->libelle,
                'date_paiement' => (int) $d->paye === 1 ? ($d->date_effectuee ?? $d->updated_at) : null,
                'origine'       => 'Vous',
                'detail'        => 'Demande de paiement',
            ]);

        // Les règlements issus d'une demande sont EXCLUS : ils portent
        // `demande_paiement_id` et sont déjà listés sous leur demande. Sans
        // cette exclusion, le même versement apparaîtrait deux fois.
        //
        // La colonne vient d'une migration : sans elle, on s'en passe plutôt
        // que de priver le livreur de sa page.
        $reglements = collect();

        if ($livreur) {
            $reglements = \App\Models\PaiementLivreur::with(['modePaiement', 'livraison'])
                ->where('livreur_id', $livreur->id)
                ->where('statut', 1)
                ->when(
                    \Illuminate\Support\Facades\Schema::hasColumn('paiement_livreur', 'demande_paiement_id'),
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
                    // Point 20 (09/09/2026) : « À payer » puis « Effectuée », comme une demande.
                    'etat_reglement' => $p->etat_reglement,
                    'libelle_etat'   => $p->libelleReglement(),
                    'mode'          => $p->modePaiement?->libelle,
                    'date_paiement' => $p->date_paiement ?? $p->created_at,
                    'origine'       => "L'entreprise",
                    'detail'        => $p->livraison
                        ? 'Course ' . ($p->livraison->numero ?: '#' . $p->livraison->id)
                        : 'Règlement de course',
                ]);
        }

        $mouvements = $demandes->concat($reglements)
            ->sortByDesc(fn ($m) => $m->date)
            ->values();

        $recus   = $mouvements->where('statut', 1);
        $attente = $mouvements->where('statut', 0);

        return view('livreur.listeDesDemandesDePaiement', [
            'livreur'          => $livreur,
            'mouvements'       => $mouvements,
            'totalDemandes'    => $mouvements->count(),
            'totalEnAttente'   => $attente->count(),
            'totalPayees'      => $recus->count(),
            'totalRefusees'    => $mouvements->where('statut', 2)->count(),
            'montantEnAttente' => (float) $attente->sum('montant'),
            'montantPaye'      => (float) $recus->sum('montant'),
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
        // Login OU e-mail, comme l'application mobile.
        //
        // Le site n'acceptait QUE le login : un livreur qui se connecte au
        // mobile avec son adresse e-mail — ce que l'application autorise —
        // recevait ici « Mot de passe ou login incorrect », avec les mêmes
        // identifiants. Le message accusait le mot de passe alors que le
        // compte n'avait tout simplement pas été trouvé.
        //
        // Le OU est ENTRE PARENTHÈSES : sans cela, la condition deviendrait
        // « login = X OU (email = X ET type = livreur) », et n'importe quel
        // utilisateur du système ayant ce login entrerait dans l'espace livreur.
        $identifiant = $request->login;
        $user = User::where(function ($q) use ($identifiant) {
                $q->where('login', $identifiant)->orWhere('email', $identifiant);
            })
            ->where('type_user_id', 8)
            ->first();

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

            // LE FOURNISSEUR DOIT AVOIR SERVI LE BON.
            //
            // Même règle que dans l'API du livreur : sans elle, une course
            // pouvait être clôturée — et le livreur crédité — alors que le bon
            // d'enlèvement n'avait jamais été servi. La commande passait
            // TERMINEE et rejoignait « commandes traitées » pendant que le bon
            // restait « en attente d'enlèvement » chez le fournisseur, et la
            // vente n'entrait dans AUCUN chiffre d'affaires : les trois écrans
            // de CA ne comptent que les bons portant une `fournisseur_validation`.
            //
            // Deux points d'entrée, deux gardes : le livreur clôture depuis son
            // application OU depuis le site, et n'en protéger qu'un laisserait
            // la porte ouverte.
            //
            // Le contrôle ne s'applique QUE s'il existe un bon : une location ou
            // une demande de livraison n'en a pas, et les bloquer arrêterait des
            // flux qui ne concernent aucun fournisseur.
            $bon = Enlevement::where('livraison_id', $livraison->id)
                ->where('statut', Help::$STATUT_ACTIF)
                ->first();

            if ($bon && empty($bon->fournisseur_validation)) {
                // Cle « fail » et non « error » : Flasher capte error/success et
                // les rejoue en bulle flottante, qui s efface d elle-meme. Ce
                // message doit rester a l ecran — le livreur est chez le client
                // et doit pouvoir le lire, voire le montrer.
                return back()->with('fail',
                    'Le fournisseur n\'a pas encore validé le bon d\'enlèvement'
                    . ($bon->code_enleve ? ' (' . $bon->code_enleve . ')' : '')
                    . ' : la livraison ne peut pas être clôturée. Demandez-lui de valider son bon.');
            }

            // La colonne etat_livraison contient des LIBELLÉS ("EN ATTENTE",
            // "EN COURS LIVRAISON", "LIVREE") et non des numéros. Écrire 3 y plaçait
            // la chaîne « 3 » : la livraison n'était donc reconnue comme livrée
            // NULLE PART — ni dans le test « toutes les lignes sont-elles livrées ? »
            // dix lignes plus bas (qui compare à 'LIVREE'), ni dans les grands livres,
            // ni sur la fiche commande, qui affichait « Non livrée » indéfiniment.
            $livraison->update([
                'etat_livraison' => Help::$LIVRAISON_LIVREE,
                'statut' => 1
                //'statut' => 2
            ]);
            // Le bon de livraison part au client entreprise (lot 84, 15/09/2026),
            // une seule fois par bon, différé, jamais bloquant.
            \App\Services\BonDeLivraisonClient::envoyerApresLivraison($livraison);

            if ($livraison->provenance == 'COMMANDE') {
                $ligne = $livraison->detailCommande;

                // Quantité livrée cumulée : seule l'application mobile du livreur
                // la tenait à jour. Une validation faite depuis le site laissait la
                // ligne à 0, d'où le statut « Non livrée » sur la fiche commande.
                // CE QUE LE FOURNISSEUR A SERVI, ET NON CE QUI A ÉTÉ DEMANDÉ.
                //
                // On ajoutait `livraison->qte`, la quantité demandée. Un
                // enlèvement partiel — 5 t servies sur 15 — créditait donc le
                // client de 15, la ligne passait LIVREE et la commande TERMINEE :
                // elle quittait la liste des commandes à traiter alors qu'il
                // restait 10 t à servir. Constaté sur la commande 627042.
                $qteLivree = min(
                    (float) $ligne->qte,
                    (float) ($ligne->qte_livree ?? 0) + $livraison->quantiteRemise()
                );

                $ligne->update([
                    // LIVREE seulement si la ligne est entièrement servie. On y
                    // écrivait LIVREE quoi qu'il arrive : une livraison partielle
                    // rendait donc la ligne éligible au retour alors que le client
                    // n'avait pas encore tout reçu. Même règle que l'API.
                    'etat_livraison' => ($qteLivree >= (float) $ligne->qte)
                        ? Help::$LIVRAISON_LIVREE
                        : Help::$LIVRAISON_EN_COURS,
                    'qte_livree' => $qteLivree,
                ]);

                $commande = DB::select("select c.* from commande c, detail_commande d, livraison l where c.id = d.`commande_id` and d.`id`=l.`detail_commande_id` and l.id=$livraison->id")[0];

                // $lesLivraisons = DB::select("select l.* from commande c, detail_commande d, livraison l where c.id = d.`commande_id` and d.`id`=l.`detail_commande_id` and c.id=$commande->id");

                // LA COMMANDE SE CLÔT SUR LES QUANTITÉS, PAS SUR UN COMPTE DE COURSES.
                //
                // On comptait ici les livraisons de la commande et on exigeait
                // qu'elles soient TOUTES à l'état LIVREE. Une course REFUSÉE par
                // le livreur entrait dans ce compte sans jamais pouvoir être
                // livrée : la commande restait EN TRAITEMENT pour toujours, même
                // une fois la marchandise reconfiée à un autre livreur et remise
                // au client.
                //
                // C'est la règle de l'application mobile qui est juste, et c'est
                // désormais la seule : une commande est terminée quand chacune de
                // ses lignes a reçu la quantité commandée. Un refus jamais
                // reconfié laisse la ligne incomplète — donc la commande ouverte,
                // ce qui est le comportement voulu.
                $toutLivre = DetailCommande::where('commande_id', $commande->id)
                    ->where('statut', Help::$STATUT_ACTIF)
                    ->get()
                    ->every(fn ($d) => (float) ($d->qte_livree ?? 0) >= (float) $d->qte);

                if ($toutLivre) {

                    // Finaliser la commande. etat_commande contient lui aussi des
                    // libellés ("EN ATTENTE", "TERMINEE"...) : l'ancien « = 3 » y
                    // écrivait la chaîne « 3 », et la commande n'apparaissait donc
                    // jamais dans la liste des commandes traitées.
                    DB::update('update commande set etat_commande = ? where id = ?', [Help::$COMMANDE_TERMINE, $commande->id]);
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

            } elseif ($livraison->provenance == Help::$LOCATION) {
                // LOCATION (10/09/2026) : cette course tombait dans la branche des
                // demandes de livraison, dont elle n'a aucune relation (detailLivraison
                // à null) — une clôture depuis le site échouait. Même règle que
                // l'API : la livraison du matériel ne TERMINE pas la location, elle
                // reste EN COURS jusqu'au retour ; la ligne livrée passe EN COURS.
                $ligneLocation = \App\Models\DetailLocation::find($livraison->detail_commande_id);
                if ($ligneLocation) {
                    if ($ligneLocation->etat_location === Help::$LOCATION_EN_ATTENTE) {
                        $ligneLocation->update(['etat_location' => Help::$LOCATION_EN_COURS]);
                    }
                    $locationLivree = \App\Models\Location::find($ligneLocation->location_id);
                    if ($locationLivree && $locationLivree->etatLibelle() === Help::$LOCATION_EN_ATTENTE) {
                        $locationLivree->update(['etat_location' => Help::$LOCATION_EN_COURS]);
                    }
                }
            } else { //demande de livraison

                // LA LIGNE TRANSPORTÉE EST MARQUÉE, elle aussi.
                //
                // La clôture mettait à jour la ligne d'une COMMANDE — quantité
                // livrée, état — mais laissait celle d'une DEMANDE DE LIVRAISON
                // intacte : l'article restait « EN TRAITEMENT » pour toujours au
                // back-office, alors qu'il avait été transporté et remis.
                //
                // L'application mobile du livreur, elle, la met à jour depuis sa
                // correction. Le résultat dépendait donc du CANAL par lequel le
                // livreur clôturait : même course, deux états en base.
                //
                // Une ligne peut demander plusieurs camions : elle n'est LIVREE
                // que lorsque la quantité livrée couvre la quantité demandée.
                $ligneTransport = $livraison->detailLivraison;

                if ($ligneTransport) {
                    $livreePourLaLigne = (float) Livraison::where('detail_livraison_id', $ligneTransport->id)
                        ->where('etat_livraison', Help::$LIVRAISON_LIVREE)
                        ->whereNull('deleted_at')
                        ->sum('qte');

                    $ligneTransport->update([
                        'etat_livraison' => ($livreePourLaLigne >= (float) $ligneTransport->qte)
                            ? Help::$LIVRAISON_LIVREE
                            : Help::$LIVRAISON_EN_COURS,
                    ]);
                }

                $demandeLivraison = $livraison->detailLivraison->demandeLivraison;
                $qteALivrer = $demandeLivraison->detailLivraison->sum('qte');


                // $demandeLivraison = Livraison::where('adresse_livraison_id',$livraison->adresse_livraison_id)->get();
                // $totals = $demandeLivraison->count();
                $qteLivree = 0;

                // Variable de boucle NOMMÉE À PART. Elle s'appelait « $livraison »,
                // comme la livraison en cours de clôture : la boucle l'écrasait, et
                // tout ce qui suivait travaillait sur la DERNIÈRE livraison
                // parcourue. Le livreur était donc crédité du coût de celle-là, pas
                // de la sienne.
                foreach ($demandeLivraison->livraisons as $uneLivraison) {
                    if ($uneLivraison->etat_livraison == Help::$LIVRAISON_LIVREE) {
                        $qteLivree += $uneLivraison->qte;
                    }
                }

                // dd($qteALivrer,$qteLivree);
                if ($qteLivree >= $qteALivrer) {
                    // État écrit en toutes lettres : etat_commande est un ENUM, où
                    // un entier désigne la POSITION de la valeur, pas la valeur.
                    $livraison->detailLivraison->demandeLivraison->update([
                        'etat_commande' => Help::$COMMANDE_TERMINE
                    ]);
                }
            }

            $livreur->update([
                'solde' => $livreur->solde + $livraison->cout_livraison
            ]);

            // Le véhicule redevient disponible dès qu'il n'a plus aucune course
            // en cours. Il était mis à 0 à l'affectation sans jamais être
            // libéré : après sa première course, il disparaissait définitivement
            // de la liste proposée au gestionnaire.
            Vehicule::libererSiPlusAucuneCourse($livraison->vehicule_id);

            // dd('ok');

            return redirect()->route('livreur.livraisonValides')->with('success', 'Livraison validée: ' . $request->code);
        } else {
            return back()->with('info', 'Code de livraison invalide');
        }
    }

    public function enRoute(Livraison $livraison)
    {
        // Le livreur déclare qu'il PART : la livraison passe « en cours », elle
        // n'est pas terminée. C'est validerLivraison() qui la clôturera.
        //
        // La colonne est une énumération MySQL :
        //   enum('EN ATTENTE','EN TRAITEMENT','LIVREE','EN COURS LIVRAISON')
        // Écrire un NOMBRE n'enregistre pas ce nombre mais la valeur à cette
        // position. Le 3 écrit ici stockait donc « LIVREE » : le simple départ du
        // livreur était comptabilisé comme une livraison effectuée, la commande
        // pouvait apparaître soldée avant que la marchandise ne soit remise.
        $livraison->update([
            'etat_livraison' => Help::$LIVRAISON_EN_COURS
        ]);

        $livraison->detailLivraison->demandeLivraison->update([
            'etat_commande' => Help::$COMMANDE_EN_TRAITEMENT
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
        // Le même document que le courriel du client (lot 84) : date, historique.
        return \App\Services\BonDeLivraisonClient::pdf($enlevement)->download($enlevement->code_enleve . '.pdf');
    }

    public function afficheBon(Enlevement $enlevement)
    {
        return \App\Services\BonDeLivraisonClient::pdf($enlevement)->stream();
    }

    /**
     * L'application des livreurs clôture une course dans l'API ; celle-ci
     * demande au site d'envoyer le bon de livraison au client entreprise, en
     * présentant le jeton partagé JETON_INTERNE (lot 84, 15/09/2026).
     */
    public function envoyerBonInterne(Request $request, $livraisonId)
    {
        $jeton = (string) config('constantes.jeton_interne');
        if ($jeton === '' || !hash_equals($jeton, (string) $request->input('jeton', ''))) {
            return response()->json(['code' => 403, 'message' => 'Jeton interne absent ou invalide'], 403);
        }
        $livraison = Livraison::find($livraisonId);
        if (!$livraison) {
            return response()->json(['code' => 404, 'message' => 'Livraison introuvable'], 404);
        }

        return response()->json(['code' => 200, 'envoye' => \App\Services\BonDeLivraisonClient::envoyerApresLivraison($livraison, true)]);
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
