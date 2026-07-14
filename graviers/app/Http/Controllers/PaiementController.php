<?php

namespace App\Http\Controllers;

use App\Http\Controllers\PaiementEnLigne;
use App\Mail\emailPaiement;
use App\Mail\emailPaiementClient;
use App\Mail\emailPaiementLocation;
use App\Mail\emailPaiementLocationClient;
use App\Models\Apporteur;
use App\Models\Client;

use App\Models\Commande;
use App\Models\CommissionApporteur;
use App\Models\Configuration;
use App\Models\Devis;
use App\Models\Facture;
use App\Models\LignePaiement;
use App\Models\Location;
use App\Models\ModePaiement;
use App\Models\Paiement;
use Help;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redirect;
use PDF;
use App\Services\FneService;

class PaiementController extends Controller
{
    //

    public function paiementPage($numero){


        $info = Commande::where('id','=',$numero)->first();
        // dd($info->devis_id);
        $data['devis'] = Devis::where('id', $info->devis_id)->first();
        // dd($data['devis']->paiements);
        if($info->devis->paiements->isEmpty()){
            $montant_restant = $info->devis->montant;

        }else{
            $montant = $info->devis->paiements->sortByDESC('created_at')->first();
            $montant_restant = $montant->montant_restant;
        }
        // dd($montant_restant);
        $data['montant_restant'] = $montant_restant;
        $data['commande'] = Commande::where('id','=',$numero)->first();
        $data['modePaiement'] = ModePaiement::all();

        return view('paiement.createPaiement',$data);
    }

    public function paiement($id, Request $request){

        try {
            //code...


            $info = Commande::where('id','=',$id)->first();

            if($info->devis->paiements->isEmpty()){
                // $montant_total = $info->devis->montant;
                // Total NET dû (HT + TVA + livraison − remise) : montant_total ne
                // contient que le HT côté web -> régler le HT soldait la commande à
                // tort alors que TVA et livraison restaient dues.
                $montant_total = $info->montantAPayer();
                $montant_restant = $montant_total - $request->montant;
                // d('dans if');

            }else{
                $montant = $info->devis->paiements->sortByDESC('created_at')->first();
                $montant_total = $montant->montant_total;

                $montant_restant = $montant->montant_restant - $request->montant;
            }

            // dd($montant_total);

            // recuperer le client_id lié à la commande
            // $client = Commande::where('id','=',$id)->value('client_id');
            $client = $info->client_id;


            // recuperer le devis_id
            $devis = Devis::where('numero','=',$info->numero)->value('id');
            // dd($devis);
            $code = uniqid(5);

            $dataPaiement = [
                'client_id' => $client,
                'devis_id' => $devis,
                'code' => $code,
                'libelle' => $request->libelle,
                'montant_total' => $montant_total,
                'montant_restant' => $montant_restant,

            ];
            $reference = 'ref_hsd8frre'.rand(10,100);

            if($montant_restant>0){
                $info->update([
                    'statut' => 2
                ]);
            }elseif($montant_restant==0){
                $info->update([
                    'statut' => 3
                ]);

                $info->client->update([
                    'points' => $info->client->point + 200
                ]);
            }
            if($montant_restant<0 ){
                return redirect()->route('paye.create',$id)->with('fail','Le montant entré est supérieur au montant dû');
            }
            $paiement = Paiement::create($dataPaiement);
            $paiementId = $paiement->id;

            $dataLignePaiement = [
                'paiement_id' => $paiementId,
                'mode_paiement_id' => $request->mode,
                'reference' => $request->libelle,
                'moyen_paiement' => $request->moyen.''.rand(10,100),
                'montant' => $request->montant,
                'user_paie_id' => Auth::user()->id
            ];
            $data['ligne'] = LignePaiement::create($dataLignePaiement);
            $data['user'] = Auth::user();

            // dd('ok');
            // $montant = $info->montant_total;
            $montant = $request->montant;
            $client = Client::where('id',$info->client_id)->first();
            if($client->code_parrain){

                $apporteur = Apporteur::where('code',$client->code_parrain)->first();
                $solde = $apporteur->solde;


                    $tauxApp = $this->resolveTauxCommission($apporteur);
                    $commission = [
                        'commande_id' => $info->id,
                        'apporteur_id' => $apporteur->id,
                        'montant' => $montant * $tauxApp / 100,
                        'type_affaire' => $info->detailCommande->first()->produit->type_affaire,
                        'montantPaye' => $montant,
                        'statut' => 1
                    ];

                    $commission = CommissionApporteur::create($commission);

                    // Incrémente le solde (ne l'écrase pas) pour cumuler les commissions.
                    $apporteur->update([
                        'solde' => $apporteur->solde + $commission->montant
                    ]);
                }


            $config = Configuration::first();

            $data['image'] = 'frontend/assets/imgs/logo/logooBlanc.svg';
            $data['client'] = $client;

            // dd($client,$data['client']);


            Mail::send(new emailPaiement(Auth::user(),$info, $request->montant,$config->email_tresorier,$data));
            Mail::send(new emailPaiementClient($info,$request->montant,$data));
            // dd(Auth::user(),$info, $request->montant,$config->email_tresorier,$request->montant);

            $fneData = FneService::getDonneesFne(null, $client);
            $data = array_merge($data, $fneData);

            $pdf = PDF::loadView('document.facture',$data);

            return $pdf->stream('Paiement '.$data['ligne']->created_at.'.pdf');

        } catch (\Throwable $th) {
            // dd($th);
            return view('layout.errorCatchBack');
        }

    }

    public function paiementaprescommande(){
        $data['image'] = 'frontend/assets/imgs/logo/logooBlanc.svg';
        $data['paiement'] = Paiement::find(1);
        $client = $data['paiement']->client ?? null;
        $fneData = FneService::getDonneesFne(null, $client);
        $data = array_merge($data, $fneData);
        return PDF::loadView('document.factureApresCommande',$data)->stream('facture.pdf');
    }



    public function paiementLocation(Location $location){
        //dd($location);
        return view('paiement.paiementLocation',[
            'location' => $location,
            'modePaiement' => ModePaiement::all(),
        ]);
    }

    public function paiementLocationTraitement( Request $request, Location $location){

        try{
        // dd($request, $location);

            if($location->paiements->isEmpty()){
                // $montant_total = $info->devis->montant;
                $montant_total = $location->montant_total;
                $montant_restant = $montant_total - $request->montant;
                // d('dans if');

            }else{
                $montant = $location->paiements->sortByDESC('created_at')->first();
                $montant_total = $montant->montant_total;

                $montant_restant = $montant->montant_restant - $request->montant;
            }


            $code = uniqid(5);

            $dataPaiement = [
                'client_id' => $location->client_id,
                // La table paiement n'a pas de location_id : on lie via service/service_id.
                'service' => Help::$LOCATION,
                'service_id' => $location->id,
                'code' => $code,
                'libelle' => $request->libelle,
                'montant_total' => $montant_total,
                'montant_restant' => $montant_restant
            ];
            $reference = 'ref_hsd8frre'.rand(10,100);

            if($montant_restant>0){
                $location->update([
                    'statut' => 2
                ]);
            }elseif($montant_restant==0){
                $location->update([
                    'statut' => 3
                ]);

                $location->client->update([
                    'points' => $location->client->point + 200
                ]);
            }

            if($montant_restant<0 ){
                return redirect()->route('paye.paiementLocationTraitement',$location)->with('fail','Le montant entré est supérieur au montant dû');
            }
            $paiement = Paiement::create($dataPaiement);


            $dataLignePaiement = [
                'paiement_id' => $paiement->id,
                'mode_paiement_id' => $request->mode,
                'reference' => $request->libelle,
                'moyen_paiement' => $request->moyen,
                'montant' => $request->montant
            ];
            $data['ligne'] = LignePaiement::create($dataLignePaiement);
            $data['user'] = Auth::user();

            // dd('ok');
            // Le TAUX de commission dépend de la taille de l'affaire (montant total
            // de la location), mais la commission ne porte que sur la TRANCHE payée
            // maintenant. L'ancien code appliquait le taux au montant total à CHAQUE
            // versement -> une location réglée en 3 fois payait la commission 3×.
            $montantTotalLoc = (float) $location->montant_total;
            $montantTranche  = (float) $request->montant;
            $client = Client::find($location->client_id);
            if($client && $client->code_parrain){

                $apporteur = Apporteur::where('code',$client->code_parrain)->first();
                // $solde n'est lu qu'APRÈS avoir vérifié que l'apporteur existe :
                // un code_parrain orphelin provoquait un null deref (page d'erreur
                // caissier) alors que le Paiement venait d'être créé.
                if($apporteur){
                    if ($montantTotalLoc < 5000000) {
                        $taux = 2.5;
                    } elseif ($montantTotalLoc <= 20000000) {
                        $taux = 5;
                    } else {
                        $taux = 7;
                    }
                    $apporteur->update([
                        'solde' => (float) $apporteur->solde + ($montantTranche * $taux / 100)
                    ]);
                }
            }


            // dd($apporteur)

            $config = Configuration::first();


            $email = Mail::send(new emailPaiementLocation(Auth::user(),$location, $request->montant,$config->email_tresorier));

            // dd($email);
            Mail::send(new emailPaiementLocationClient($location,$request->montant));
            // dd(Auth::user(),$info, $request->montant,$config->email_tresorier,$request->montant);

            $clientLoc = $location->client ?? null;
            $fneDataLoc = FneService::getDonneesFne(null, $clientLoc);
            $data = array_merge($data, $fneDataLoc);

            $pdf = PDF::loadView('document.facture',$data);

            return $pdf->stream('Paiement '.$data['ligne']->created_at.'.pdf');
        } catch (\Throwable $th) {
            // dd($th);
            return view('layout.errorCatchBack');
        }
    }

    public function paiementList(){

        $lignePaiment = LignePaiement::orderByDesc('created_at')->get();

        return view('paiement.list',[
            'lignes' => $lignePaiment
        ]);
    }

    public function facture($ligne, $action){

        // if(gettype($ligne) == 'string'){
        //    $data['ligne'] = LignePaiement::where('reference',$ligne)->first();
        // }elseif(gettype($ligne) == 'integer'){
            $data['ligne'] = LignePaiement::find($ligne);
        // }

        // dd($data['ligne'], $ligne);


        $data['image'] = config("constantes.logo");

        $clientFacture = $data['ligne']->paiement->client ?? null;
        $fneDataFacture = FneService::getDonneesFne(null, $clientFacture);
        $data = array_merge($data, $fneDataFacture);

        // getDonneesFne(null, ...) laisse fne_numero='' (aucune Facture DGI liée à ce reçu).
        // Le document affiche « Reçu de paiement Nº {{ $fne_numero }} » : on renseigne donc
        // la référence du paiement — numéro de reçu si présent, sinon le code de transaction
        // (celui de l'URL /client/paiement/verifie/{code}), sinon la référence de la ligne.
        $paiementRecu = optional($data['ligne'])->paiement;
        $data['fne_numero'] = optional($paiementRecu)->numero_recu
            ?: (optional($paiementRecu)->code ?: (optional($data['ligne'])->reference ?: ''));

        $pdf = PDF::loadView('document.facture',$data);

        if($action == 'voir'){
            return $pdf->stream('Paiement '.$data['ligne']->created_at.'.pdf');
        }else{
            return $pdf->download('Paiement '.$data['ligne']->created_at.'.pdf');
        }
    }

    public function effectuerPaiement(Client $client){


        // Au cas ou on parcours les paiements initialisés pour chaque commande
        // $paiements = DB::select("SELECT
        //                 cde.id AS commande_id,
        //                 cde.client_id,
        //                 cde.numero AS num_commande,
        //                 cde.created_at AS date_commande,
        //                 p.id AS paiement_id,
        //                 p.montant_total AS montant_a_payer,
        //                 p.montant_restant
        //             FROM paiement p
        //             JOIN commande cde ON p.service_id = cde.id
        //             WHERE cde.client_id = $client->id AND p.statut <> 3 AND p.montant_restant > 0
        //             ORDER BY cde.created_at
        //             ");

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
                LEFT JOIN paiement p ON p.facture_id = f.id AND p.statut <> 3
                WHERE cde.client_id = $client->id
                -- GROUP BY f.id, cde.id
                -- HAVING montant_restant > 0
                ORDER BY cde.created_at
                ";
        }else{
            // liste de paiement en attente pour les clients non à terme
            $req = "SELECT IFNULL(li_tot.paye, 0) AS paye,
                            (cde.montant_total + cde.cout_livraison_client + IFNULL(tva.montant, 0) - cde.remise) AS montant_a_payer,
                            ((cde.montant_total + cde.cout_livraison_client + IFNULL(tva.montant, 0) - cde.remise) - IFNULL(li_tot.paye, 0)) AS montant_restant,
                            cde.numero AS num_commande,
                            cde.created_at AS date_commande,
                            cde.id as commande_id

                    FROM commande cde
                    LEFT JOIN (
                        SELECT service_id, SUM(montant) AS paye
                        FROM ligne_paiement
                        GROUP BY service_id
                    ) li_tot ON li_tot.service_id = cde.id
                    LEFT JOIN tva_commande tva ON tva.commande_id = cde.id
                    WHERE cde.client_id = $client->id
                    HAVING montant_restant > 0";
        }

        // dd(DB::select($req));

        // paiement_id,
        // commande_id,
        // date_commande
        // num_commande,
        // client_id, ,
        // montant_a_payer,
        // total_paye, montant_restant




            // facture_id, commande_id, client_id, num_commande, date_commande, montant_a_payer, total_paye, montant_restant

        $paiements = DB::select($req);
        // dd($paiements, $client->id);

        return view('paiement.effectuerPaiement',[
            'paiements' => $paiements,
            'client' => $client,
            'moyens' => ModePaiement::all(),
        ]);
    }

    // option de plusieurs ligne_paiement par paiement
    public function effectuerPaiementTraitementOld(Request $request){

        $request->validate([
            'paiements' => 'required|array|min:1',
            'mode' => 'required',
        ],[
            'paiements.required' => 'Veuillez sélectionner au moins une facture à payer',
            'mode.required' => 'Veuillez sélectionner un mode de paiement'
        ]);

        $paiements = Paiement::find($request->paiements);

        $client = Client::find($paiements->first()->client_id);
        $apporteur = Apporteur::find($client->parrain_id);
        // dd($apporteur);
        $montantDonne = $request->montant;

        foreach($paiements as $p){
            // apporteur_id, commande_id, montant

            $montantPaiement = $p->montant_restant;

            if($montantDonne >= $montantPaiement ){

                $p->montant_restant = 0;
                $p->statut = 1;
                $p->update();

                $ligne = new LignePaiement;
                $ligne->paiement_id = $p->id;
                $ligne->mode_paiement_id = $request->mode;
                $ligne->reference = $p->code;
                $ligne->montant = $montantDonne;
                $ligne->user_id = Auth::user()->id;
                $ligne->statut = 1;
                $ligne->service_id = $p->service_id;
                $ligne->service = $p->service;
                $ligne->code_paiement = $p->code;
                $ligne->save();
                $this->addCommission($apporteur, $ligne);
            }else{

                $montantrestant = $montantPaiement - $montantDonne;
                // $montantFacture = $facture->montant - $montantFacture;

                $p->montant_restant = $montantrestant;
                $p->update();

                $ligne = new LignePaiement;
                $ligne->paiement_id = $p->id;
                $ligne->mode_paiement_id = $request->mode;
                $ligne->reference = $p->code;
                $ligne->montant = $montantDonne;
                $ligne->user_id = Auth::user()->id;
                $ligne->statut = 1;
                $ligne->service_id = $p->service_id;
                $ligne->service = $p->service;
                $ligne->code_paiement = $p->code;
                $ligne->save();

                $this->addCommission($apporteur, $ligne);
                break;
            }



        }

        $data['image'] = 'frontend/assets/imgs/logo/logooBlanc.svg';
        $data['ligne'] = DB::select("SELECT li.reference,
                                            li.created_at AS date_paiement,
                                            li.service,
                                            CASE
                                                WHEN li.service = 'COMMANDE' THEN cde.numero
                                                WHEN li.service = 'LOCATION' THEN lo.numero
                                                WHEN li.service = 'LIVRAISON' THEN liv.numero

                                            END AS num_service,
                                            li.montant AS montant_paye,
                                            mp.description AS mode_paiement,
                                            CONCAT(cli.nom,' ',cli.prenom) AS nom_prenom,
                                            CONCAT(cli.contact1,'/',cli.contact2) AS contact,
                                            u.adresse,
                                            pays.nom AS pays,
                                            ville.nom AS ville
                                    FROM ligne_paiement li
                                    LEFT JOIN commande cde ON cde.id = li.service_id
                                    LEFT JOIN location lo ON lo.id = li.service_id
                                    LEFT JOIN livraison  liv ON liv.id = li.service_id
                                    JOIN paiement p ON li.paiement_id = p.id
                                    JOIN client cli ON cli.id = p.client_id
                                    JOIN mode_paiement mp ON mp.id = li.mode_paiement_id
                                    JOIN users u ON u.id = cli.user_id
                                    LEFT JOIN ville ON u.ville_id = ville.id
                                    LEFT JOIN pays ON pays.id = u.pays_id
                                    WHERE li.id = $ligne->id

                                             ");

        $fneDataAgence = FneService::getDonneesFne();
        $data = array_merge($data, $fneDataAgence);
        return PDF::loadView('document.recuPaieAgence',$data)->stream('facture.pdf');


        // return redirect()->route('paye.effectuerPaiement',$client)->with('success','Paiement effectué avec succès');


    }

    // option de chaque ligne_paiement correspond à un paiement, paiement à partir de factureseffectuerPaiement
    public function effectuerPaiementTraitement(Request $request, Client $client){
        // dd($client->client_a_terme == false);
        $apporteur = Apporteur::find($client->parrain_id);

        // trouver qui fait le paiement, un client ou un gestionnaire
        // pour un client un lance le paiement en ligne, pour le gestionnaire c'est un paiement sur place

        if(Auth::user()->type_user_id == 4){
            // paiement en ligne par le client
            $enLigne = true;

        }else if(Auth::user()->type_user_id == 3 || Auth::user()->type_user_id == 1 || Auth::user()->type_user_id == 2){
            // paiement sur place par le gestionnaire
            $enLigne = false;

        }else{
            // dd('non autorisé');
            return back()->with('error','Vous n\'êtes pas autorisé à effectuer ce paiement');
        }

        if($enLigne){
            $request->validate([
                'factures' => 'required|array|min:1',
                'mode' => 'required',
            ],[
                'factures.required' => 'Veuillez sélectionner au moins une facture à payer',
                'mode.required' => 'Veuillez sélectionner un mode de paiement'
            ]);

            if($request->montant >= 2000000){
                return back()->with('error','Le montant maximum pour un paiement en ligne est de 1 999 999 FCFA. Veuillez contacter l\'administrateur pour plus d\'informations.');
            }

            $factures = Facture::find($request->factures);
            // dd($factures, 5);
            $codePaiement = Help::getCommandeNo();

            // On paie des FACTURES : le service_id/service du paiement doit provenir
            // de la facture réglée, pas d'une valeur codée en dur. L'ancien code
            // passait service_id = 1 -> pour un client BE (non à terme) le paiement
            // était enregistré sur la commande id=1, qui se retrouvait soldée à tort
            // au callback tandis que la vraie commande restait impayée.
            $premiereFacture = $factures->first();
            if (!$premiereFacture) {
                return back()->with('error', 'Aucune facture valide sélectionnée.');
            }

            $ret = PaiementEnLigne::initierPaiement(
                        [
                            'code_paiement' => $codePaiement,
                            // 'credential_id' => "",
                            'nom_usager' => $client->nom,
                            'prenom_usager' => $client->prenom ?: $client->nom,
                            'telephone' => $client->contact1,
                            'email' => $client->user->email,
                            'libelle_article' => "Paiement IMLOD",
                            'quantite' => 1,
                            'montant' => intVal($request->montant),//ceil($commande->montant_total),
                            'lib_order' => "Paiement commande de produit IMLOD",
                            'Url_Retour' => route('client.verifiePaiement', ['codePaiement' => $codePaiement]), //route("ouvreApp", ['codePaiement' => $codePaiement]),
                            'Url_Callback' => route('callBackPaiement'),
                        ],
                        $codePaiement,
                        $codePaiement,
                        $client,
                        intVal($request->montant),
                        $request->mode,
                        $premiereFacture->service_id,
                        $premiereFacture->service,
                        $factures,
                    );
                     if ($ret['code'] == 200){

                        return Redirect::away($ret['message']);

                    } else {

                        $retour->code = $ret['code'];
                        $retour->message = $ret['message'];
                        // $retour->code = $ret['code'];
                        // $retour->message = $ret['message'];
                    }
        }else{

            // dd($client->client_a_terme);
            if($client->client_a_terme == 0){

                $commande = Commande::find($request->commande_id);




                // initialisation du montant payé pour calculer la commission de l'apporteur d'affaire
                $montantAvantCommission = 0;
                foreach($commande as $cde){
                    // Récupérer le paiement lié à la commande
                     $p = Paiement::where('service_id', $cde->id)->where('statut', '<>', 3)->where('montant_restant', '>', 0)->latest()->first();
                    // dd($cde->id, $p);
                    // dd($apporteur);
                    $montantDonne = $request->montant;

                    // apporteur_id, commande_id, montant

                    $montantPaiement = $p->montant_restant;

                    if($montantDonne >= $montantPaiement ){

                        $p->montant_restant = 0;
                        $p->statut = 1;
                        $p->update();
                        $montantLigne = $montantDonne - $montantPaiement;
                        // dd($montantDonne,$montantPaiement,$request->mode);

                        $ligne = new LignePaiement;
                        $ligne->paiement_id = $p->id;
                        $ligne->mode_paiement_id = $request->mode;
                        $ligne->reference = $p->code;
                        $ligne->montant = $p->montant_total;
                        $ligne->user_id = Auth::user()->id;
                        $ligne->statut = 1;
                        $ligne->service_id = $p->service_id;
                        $ligne->service = $p->service;
                        $ligne->code_paiement = $p->code;
                        $ligne->save();

                        // $montantAvantCommission += $ligne->montant;
                        $this->addCommission($apporteur, $ligne);
                    }else{
                        // le client doit payer la totalité de la facture
                        return back()->with('fail','Le client doit payer la totalité de la facture');

                    }
                }

                // paiement de la commisison de l'apporteur
                // $this->addCommission($apporteur, $montantAvantCommission);



                // return back()->with('success','Paiement effectué avec succès');

            }else{

                $request->validate([
                    'factures' => 'required|array|min:1',
                    'mode' => 'required',
                ],[
                    'factures.required' => 'Veuillez sélectionner au moins une facture à payer',
                    'mode.required' => 'Veuillez sélectionner un mode de paiement'
                ]);

                // dd($request->factures);

                $factures = Facture::find($request->factures);
                // dd($factures, $request->factures);
                $client = $factures->first()->commande->client;

                $montantDonne = $request->montant;

                // initialisation du montant payé pour calculer la commission de l'apporteur d'affaire
                $montantAvantCommission = 0;

                foreach($factures as $facture){
                    // $client = $facture->commande->client;

                    $montantFacture = $facture->montant;

                    // si la facture a des paiements à son actif
                    if(!$facture->paiements->isEmpty()){
                        $montantFacture = $facture->paiements->sortByDesc('created_at')->first()->montant_restant;
                    }

                    if($montantDonne >= $montantFacture ){

                        $montantDonne = $montantDonne - $montantFacture;
                        $facture->update([
                            'statut' => 1,
                        ]);
                        $paiement = new Paiement;
                        $paiement->libelle = 'Paiement commande en agence';
                        $paiement->client_id = $client->id;
                        $paiement->facture_id = $facture->id;
                        $paiement->montant_total = $facture->montant;
                        $paiement->montant_restant = 0;
                        $paiement->service_id = $facture->service_id;
                        $paiement->service = $facture->service;
                        $paiement->code = Help::getCommandeNo();
                        $paiement->statut = 1;
                        $paiement->save();

                        // $paiement = Paiement::create([
                        //     'libelle' => 'Paiement commande en agence',
                        //     'client_id' => $facture->commande->client_id,
                        //     'facture_id' => $facture->id,
                        //     'montant_total' => $facture->commande->montant_total,
                        //     'montant_restant' => 0,
                        //     'service_id' => $facture->service_id,
                        //     'servie' => $facture->service,
                        //     'code' => Help::getCommandeNo(),
                        //     'statut' => 1,
                        // ]);

                        $ligne = new LignePaiement;
                        $ligne->paiement_id = $paiement->id;
                        $ligne->mode_paiement_id = $request->mode;
                        $ligne->reference = $paiement->code;
                        // $ligne->moyen_paiement = $request->moyen;
                        $ligne->montant = $montantFacture;
                        $ligne->user_id = Auth::user()->id;
                        $ligne->statut = 1;
                        $ligne->service_id = $facture->service_id;
                        $ligne->service = $facture->service;
                        $ligne->code_paiement = $paiement->code;
                        $ligne->save();

                        // $ligne = LignePaiement::create([
                        //     'paiement_id' => $paiement->id,
                        //     'mode_paiement_id' => $request->mode,
                        //     'reference' => $paiement->code,
                        //     // 'moyen_paiement' => $request->moyen,
                        //     'montant' => $montantFacture,
                        //     'user_id' => Auth::user()->id,
                        //     'statut' => 1,
                        //     'service_id' => $facture->service_id,
                        //     'service' => $facture->service,
                        //     'code_paiement' => $paiement->code,
                        // ]);

                        // paiement de la commission de l'apporteur d'affaire
                        $this->addCommission($apporteur, $ligne);

                    }else{

                        $montantrestant = $montantFacture - $montantDonne;
                        // $montantFacture = $facture->montant - $montantFacture;

                        $paiement = new Paiement;
                        $paiement->libelle = 'Paiement commande en agence';
                        $paiement->client_id = $client->id;
                        $paiement->facture_id = $facture->id;
                        $paiement->montant_total = $facture->montant;
                        $paiement->montant_restant = $montantrestant;
                        $paiement->service_id = $facture->service_id;
                        $paiement->service = $facture->service;
                        $paiement->code = Help::getCommandeNo();
                        $paiement->save();

                        // $paiement= Paiement::create([
                        //     'libelle' => 'Paiement commande en agence',
                        //     'client_id' => $facture->commande->client_id,
                        //     'facture_id' => $facture->id,
                        //     'montant_total' => $facture->commande->montant_total,
                        //     'montant_restant' => $montantrestant,
                        //     'service_id' => $facture->service_id,
                        //     'service' => $facture->service,

                        //     'code' => Help::getCommandeNo(),
                        // ]);


                        $ligne = new LignePaiement;
                        $ligne->paiement_id = $paiement->id;
                        $ligne->mode_paiement_id = $request->mode;
                        $ligne->reference = $paiement->code;
                        $ligne->moyen_paiement = $request->moyen;
                        $ligne->montant = $montantDonne;
                        $ligne->user_id = Auth::user()->id;
                        $ligne->statut = 1;
                        $ligne->service_id = $facture->service_id;
                        $ligne->service = $facture->service;
                        $ligne->code_paiement = $paiement->code;
                        $ligne->save();

                        // $ligne = LignePaiement::create([
                        //     'paiement_id' => $paiement->id,
                        //     'mode_paiement_id' => $request->mode,
                        //     'reference' => $request->libelle,
                        //     'moyen_paiement' => $request->moyen,
                        //     'montant' => $montantDonne,
                        //     'user_id' => Auth::user()->id,
                        //     'statut' => 1,
                        //     'service_id' => $facture->service_id,
                        //     'service' => $facture->service,
                        //     'code_paiement' => $paiement->code,
                        // ]);

                        // paiement de la commission de l'apporteur d'affaire
                        $this->addCommission($apporteur, $ligne);
                        break;
                    }
                }

                

            }
        }


        $data['image'] = config('constantes.logo');
        $data['ligne'] = DB::select("SELECT li.reference,
                                            li.created_at AS date_paiement,
                                            li.service,
                                            CASE
                                                WHEN li.service = 'COMMANDE' THEN cde.numero
                                                WHEN li.service = 'LOCATION' THEN lo.numero
                                                WHEN li.service = 'LIVRAISON' THEN liv.numero

                                            END AS num_service,
                                            li.montant AS montant_paye,
                                            mp.description AS mode_paiement,
                                            CONCAT(cli.nom,' ',cli.prenom) AS nom_prenom,
                                            CONCAT(cli.contact1,'/',cli.contact2) AS contact,
                                            u.adresse,
                                            pays.nom AS pays,
                                            ville.nom AS ville
                                    FROM ligne_paiement li
                                    LEFT JOIN commande cde ON cde.id = li.service_id
                                    LEFT JOIN location lo ON lo.id = li.service_id
                                    LEFT JOIN livraison  liv ON liv.id = li.service_id
                                    JOIN paiement p ON li.paiement_id = p.id
                                    JOIN client cli ON cli.id = p.client_id
                                    JOIN mode_paiement mp ON mp.id = li.mode_paiement_id
                                    JOIN users u ON u.id = cli.user_id
                                    LEFT JOIN ville ON u.ville_id = ville.id
                                    LEFT JOIN pays ON pays.id = u.pays_id
                                    WHERE li.id = $ligne->id

                                             ");

        $fneDataAgence = FneService::getDonneesFne();
        $data = array_merge($data, $fneDataAgence);
        return PDF::loadView('document.recuPaieAgence',$data)->stream('facture.pdf');



        // $data['paiement'] = $paiement;
        // $data['montant'] = $request->montant;
        // return PDF::loadView('document.recuPaieAgence',$data)->stream('facture.pdf');


        // return redirect()->route('paye.effectuerPaiement',$client)->with('success','Paiement effectué avec succès');


    }

    private function addCommission($apporteur, $l){
        if($apporteur){
            $taux = $this->resolveTauxCommission($apporteur);
            $commission = new CommissionApporteur;
            $commission->commande_id = $l->paiement->service_id;
            $commission->apporteur_id = $apporteur->id;
            $commission->montant = $l->montant * $taux / 100;
            $commission->save();

            $apporteur->solde += $commission->montant;
            $apporteur-> update();
        }
    }

    /**
     * Renvoie le taux de commission applicable pour un apporteur :
     * son taux personnalisé s'il est > 0, sinon le taux standard de la configuration.
     */
    private function resolveTauxCommission($apporteur): float
    {
        $taux = (float) ($apporteur->pourcentage ?? 0);
        if ($taux <= 0) {
            $config = Configuration::first();
            $taux = (float) ($config?->taux_commission_standard ?? 3);
        }
        return $taux;
    }
}
