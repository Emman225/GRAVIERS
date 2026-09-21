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
use App\Support\AffaireCommissionnable;
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

    // [RETIRÉS] paiementPage() / paiement() — écran de règlement d'une VENTE
    // (/paiement/create/{commande}). Le paiement y naissait DÉJÀ VALIDÉ, sans
    // agence, sans reçu et sans seconde signature, et sa ligne de paiement ne
    // portait ni service ni service_id — Commande::montantPayeComptant() ne la
    // voyait donc pas. Le guichet /comptant/encaissements le remplace :
    // double validation, agence, reçu, et reprise de la commission de
    // l'apporteur ainsi que des points de fidélité, qui étaient calculés ici.


    public function paiementaprescommande(){
        $data['image'] = config('constantes.logo_pdf');
        $data['paiement'] = Paiement::find(1);
        $client = $data['paiement']->client ?? null;
        $fneData = FneService::getDonneesFne(null, $client);
        $data = array_merge($data, $fneData);
        return PDF::loadView('document.factureApresCommande',$data)->stream('facture.pdf');
    }



    // [RETIRÉES] paiementLocation() / paiementLocationTraitement() — écran de
    // règlement d'une location. Le paiement y naissait DÉJÀ VALIDÉ, sans agence,
    // sans reçu et sans seconde signature. Le guichet
    // /encaissements/locations (LocationComptantController) le remplace :
    // il applique la double validation et reprend la commission de l'apporteur
    // ainsi que les points de fidélité, qui étaient calculés ici.

    public function paiementList(){

        // N'afficher que les paiements RÉELLEMENT encaissés (client facturé) :
        // ligne de paiement au statut ACTIF. On exclut ainsi les paiements en ligne
        // initiés mais non confirmés (INACTIF) et les paiements annulés (statut 3).
        $lignePaiment = LignePaiement::where('statut', Help::$STATUT_ACTIF)
            ->orderByDesc('created_at')
            ->get();

        return view('paiement.list',[
            'lignes' => $lignePaiment
        ]);
    }

    public function facture($ligne, $action){

        // LE REÇU DU CLIENT EST CELUI DU GUICHET (07/09/2026).
        //
        // Cette page rendait un document au dessin d'une facture, dont la
        // désignation portait le CODE interne du paiement (PAY-…) et non le
        // numéro de la commande. Le client demandait un reçu au modèle de
        // l'agence (/recu/{id}), avec le numéro de sa commande : c'est
        // désormais le même document, produit par RecuPaiement.
        $lignePaiement = LignePaiement::find($ligne);
        $paiementRecu  = $lignePaiement?->paiement;
        if ($paiementRecu) {
            // Un client ne voit que ses propres reçus ; l'écran des
            // paiements du back-office, lui, les voit tous.
            $utilisateur = Auth::user();
            if ($utilisateur && (int) $utilisateur->type_user_id === (int) Help::$USER_CLIENT) {
                $clientConnecte = \App\Models\Client::where('user_id', $utilisateur->id)->first();
                abort_unless($clientConnecte && (int) $clientConnecte->id === (int) $paiementRecu->client_id, 403);
            }

            $numeroRecu = $paiementRecu->numero_recu
                ?: (\App\Services\RecuPaiement::estEnLigne($paiementRecu)
                    ? \App\Services\RecuPaiement::attribuerNumeroSiAbsent($paiementRecu)
                    : ($paiementRecu->code ?? $paiementRecu->id));
            $pdfRecu = \App\Services\RecuPaiement::pdf($paiementRecu);
            $nomRecu = 'Recu_' . $numeroRecu . '.pdf';

            return $action == 'voir' ? $pdfRecu->stream($nomRecu) : $pdfRecu->download($nomRecu);
        }

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

        // LA PART FISCALE DU REGLEMENT, AU LIEU D'UNE TVA ECRITE A ZERO.
        //
        // Le recu annoncait « TOTAL HT = montant encaisse », « TVA 0 »,
        // « TOTAL TTC = le meme montant », et un resume fiscal affirmant
        // « TVA exo.leg — Pas de TVA sur HT 00,00% ». Autrement dit : il
        // declarait une exoneration sur des reglements qui, eux, contenaient
        // bien de la TVA. Le montant encaisse est un TTC presente comme un HT.
        //
        // On repart de l'affaire reglee — vente, location ou transport — qui
        // porte son propre montant de TVA, et on en prend la part
        // proportionnelle a ce qui a ete encaisse : un client qui regle la
        // moitie de sa commande a acquitte la moitie de sa TVA.
        $data = array_merge($data, $this->partFiscaleDuReglement($data['ligne']));

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
            // HT recalculé depuis les lignes : cde.montant_total contient le HT pour une
            // commande créée sur le site et le NET pour une commande créée depuis le
            // mobile. L'ancien calcul ajoutait TVA et livraison par-dessus un montant qui
            // les contenait déjà -> commande mobile affichée à tort comme non soldée.
            $req = "SELECT IFNULL(li_tot.paye, 0) AS paye,
                            (COALESCE(NULLIF(ht.montant_ht, 0), cde.montant_total, 0) + cde.cout_livraison_client + IFNULL(tva.montant, 0) - cde.remise) AS montant_a_payer,
                            ((COALESCE(NULLIF(ht.montant_ht, 0), cde.montant_total, 0) + cde.cout_livraison_client + IFNULL(tva.montant, 0) - cde.remise) - IFNULL(li_tot.paye, 0)) AS montant_restant,
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
                    LEFT JOIN (
                        SELECT d.commande_id, SUM(d.prix * d.qte) AS montant_ht
                        FROM detail_commande d
                        WHERE d.deleted_at IS NULL
                        GROUP BY d.commande_id
                    ) ht ON ht.commande_id = cde.id
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
            // Encaissement par un agent : instrument réel, pas « en agence ».
            // (Écran désormais remplacé par « Encaissements Agence », mais la route
            // reste accessible : on garde la même règle pour éviter toute divergence.)
            'moyens' => ModePaiement::listePourAgent(),
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

        $data['image'] = config('constantes.logo_pdf');
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
                            'libelle_article' => "Paiement DALAKOUN",
                            'quantite' => 1,
                            'montant' => intVal($request->montant),//ceil($commande->montant_total),
                            'lib_order' => "Paiement commande de produit DALAKOUN",
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

                        // $retour n'a jamais été défini dans cette méthode : l'ancien
                        // « $retour->code = ... » fatalisait au lieu d'afficher l'échec.
                        return back()->with('fail', 'Le paiement en ligne a échoué : '
                            . ($ret['message'] ?? 'erreur inconnue'));
                    }
        }else{

            // dd($client->client_a_terme);
            if($client->client_a_terme == 0){

                // Aucune validation n'existait sur cette branche : sans case cochée,
                // Commande::find(null) renvoyait null, le foreach fatalisait, puis
                // $ligne restait indéfinie plus bas -> erreur après écritures.
                $request->validate([
                    'commande_id' => 'required|array|min:1',
                    'mode'        => 'required',
                    'montant'     => 'required|numeric|min:1',
                ],[
                    'commande_id.required' => 'Veuillez sélectionner au moins une commande à régler',
                    'mode.required'        => 'Veuillez sélectionner un mode de paiement',
                    'montant.required'     => 'Veuillez saisir le montant encaissé',
                ]);

                $commande = Commande::find($request->commande_id);

                // Paiements réellement dus pour les commandes cochées. On les collecte
                // AVANT d'écrire quoi que ce soit : l'ancien code écrivait commande par
                // commande et pouvait s'arrêter en cours de route, laissant des
                // encaissements partiels enregistrés.
                $aRegler = [];
                $totalDu = 0;
                foreach($commande as $cde){
                    // Le filtre sur le SERVICE manquait : un paiement de LOCATION portant
                    // le même service_id qu'une commande pouvait être soldé à sa place.
                    // orWhereNull conserve les anciennes lignes sans service renseigné.
                    $p = Paiement::where('service_id', $cde->id)
                        ->where(function ($q) { $q->where('service', Help::$COMMANDE)->orWhereNull('service'); })
                        ->where('statut', '<>', 3)->where('montant_restant', '>', 0)->latest()->first();
                    if (!$p) {
                        // Commande sans paiement en attente (déjà soldée, ou paiement
                        // jamais créé) : on l'ignore au lieu de fataliser sur $p->...
                        continue;
                    }
                    $aRegler[] = $p;
                    $totalDu += (float) $p->montant_restant;
                }

                if (empty($aRegler)) {
                    return back()->with('fail', "Aucun montant restant à encaisser sur la sélection.");
                }

                $montantDonne = (float) $request->montant;
                if ($montantDonne < $totalDu) {
                    // Le montant saisi vaut pour l'ENSEMBLE de la sélection : l'ancien code
                    // le remettait à sa valeur initiale à chaque tour de boucle, si bien que
                    // 10 000 F soldaient deux commandes de 10 000 F.
                    return back()->with('fail', 'Le client doit payer la totalité des commandes sélectionnées ('
                        . number_format($totalDu, 0, ',', ' ') . ' fcfa).');
                }

                $ligne = null;
                DB::transaction(function () use ($aRegler, $request, $apporteur, &$ligne, &$montantDonne) {
                    foreach ($aRegler as $p) {
                        $montantPaiement = (float) $p->montant_restant;

                        $p->montant_restant = 0;
                        $p->statut = 1;
                        $p->update();

                        $ligne = new LignePaiement;
                        $ligne->paiement_id = $p->id;
                        $ligne->mode_paiement_id = $request->mode;
                        $ligne->reference = $p->code;
                        // Montant RÉELLEMENT encaissé sur cette commande. L'ancien code
                        // enregistrait $p->montant_total (le total de la commande) : une
                        // commande déjà payée pour moitié générait une ligne au montant
                        // plein -> caisse et grand livre surévalués.
                        $ligne->montant = $montantPaiement;
                        $ligne->user_id = Auth::user()->id;
                        $ligne->statut = 1;
                        $ligne->service_id = $p->service_id;
                        $ligne->service = $p->service;
                        $ligne->code_paiement = $p->code;
                        $ligne->save();

                        $montantDonne -= $montantPaiement;

                        $this->addCommission($apporteur, $ligne);
                    }
                });

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


        // Aucune ligne de paiement écrite (sélection vide côté « à terme », ou aucun
        // reste à encaisser) : le reçu ne peut pas être produit et l'ancien code
        // fatalisait sur $ligne->id. On revient à l'écran avec un message.
        if (!isset($ligne) || !$ligne) {
            return back()->with('fail', "Aucun encaissement n'a été enregistré : vérifiez votre sélection.");
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

    /**
     * CE QUE CONTIENT REELLEMENT UN REGLEMENT : HT, TVA, TTC.
     *
     * `ligne_paiement.montant` est ce que le client a VERSE — donc un TTC. Le
     * recu le presentait comme un HT avec une TVA nulle.
     *
     * La TVA se lit sur l'affaire reglee, jamais recalculee ici : chaque type
     * d'affaire a sa propre regle (la TVA du transport est meme optionnelle),
     * et la reproduire serait s'exposer a deux verites differentes.
     *
     * Le prorata traite les reglements partiels : la TVA acquittee suit la
     * part versee. Si le total de l'affaire est introuvable ou nul, on ne
     * devine pas — on rend le montant en TTC avec une TVA a zero, ce qui
     * revient a l'ancien comportement mais seulement dans ce cas-la.
     */
    private function partFiscaleDuReglement($ligne): array
    {
        $verse = (float) optional($ligne)->montant;
        $paiement = optional($ligne)->paiement;

        $affaire = null;
        switch (optional($paiement)->service) {
            case Help::$COMMANDE:
                $affaire = Commande::find($paiement->service_id);
                $tvaAffaire = (float) (optional($affaire)->TvaCommande->montant ?? 0);
                break;
            case Help::$LOCATION:
                $affaire = \App\Models\Location::find($paiement->service_id);
                $tvaAffaire = (float) (optional($affaire)->tvaLocation->montant ?? 0);
                break;
            case Help::$LIVRAISON:
                $affaire = \App\Models\DemandeLivraison::find($paiement->service_id);
                $tvaAffaire = (float) \App\Models\TvaCommande::where('commande_id', $paiement->service_id)
                    ->where('type_affaire', Help::$LIVRAISON)
                    ->where('statut', Help::$STATUT_ACTIF)
                    ->sum('montant');
                break;
            default:
                $tvaAffaire = 0.0;
        }

        $totalAffaire = $affaire ? (float) $affaire->montantAPayer() : 0.0;
        $taux = (float) (Configuration::first()->tva ?? 0);

        if ($totalAffaire <= 0 || $tvaAffaire <= 0 || $taux <= 0) {
            return [
                'total_ht'       => $verse,
                'total_tva'      => 0.0,
                'total_ttc'      => $verse,
                'taux_tva'       => 0.0,
                'base_taxee'     => 0.0,
                'base_non_taxee' => $verse,
            ];
        }

        // DEUX BASES, ET NON UNE SEULE.
        //
        // Un reglement couvre de la marchandise — taxee — et un transport qui,
        // lui, ne l'est pas (la TVA sur le transport est une option, desactivee
        // par defaut). Ramener le tout a un seul taux donnait des valeurs
        // absurdes sur le resume fiscal : 17,14 %, 10,91 %, 16,53 % selon la
        // part de livraison. Aucune de ces valeurs n'est un taux de TVA.
        //
        // La base taxee se DEDUIT de la TVA elle-meme : au taux en vigueur,
        // 18 F de TVA correspondent a 100 F de marchandise. Le reste du total —
        // livraison, moins la remise — forme la base non taxee. La somme des
        // deux plus la TVA redonne exactement le total de l'affaire.
        $part = $verse / $totalAffaire;

        $baseTaxee    = $tvaAffaire / ($taux / 100);
        $baseNonTaxee = $totalAffaire - $baseTaxee - $tvaAffaire;

        $tva = round($tvaAffaire * $part);
        $bt  = round($baseTaxee * $part);
        $bnt = round($verse - $tva - $bt);

        return [
            'total_ht'       => $bt + $bnt,
            'total_tva'      => $tva,
            'total_ttc'      => round($verse),
            'taux_tva'       => $taux,
            'base_taxee'     => $bt,
            'base_non_taxee' => $bnt,
        ];
    }

    private function addCommission($apporteur, $l){
        if($apporteur){
            $taux = $this->resolveTauxCommission($apporteur);
            // LA CIBLE DE LA COMMISSION SE RESOUT EN DEUX TEMPS.
            //
            // Constate en production le 28/08/2026 : trois commissions portaient
            // `commande_id = NULL`, et l'application apporteur affichait
            // « null null (# null) — Total : 0 F ». Le montant, lui, etait juste.
            //
            // La chaine : le reglement porte sur une FACTURE, la facture peut
            // n'avoir aucun `service_id` (colonne nullable), le paiement herite
            // de ce vide, et la commission avec lui. Rien ne le controlait.
            //
            // On lit donc le paiement, PUIS la facture en second recours. Le
            // type d'affaire suit la meme source : `commande_id` est polymorphe,
            // et l'annoncer faux est pire que de l'ignorer.
            $paiement = $l->paiement;
            $facture  = optional($paiement)->facture;

            // LA LIGNE DE REGLEMENT SAIT DEJA SUR QUOI ELLE PORTE.
            //
            // Ses `service` / `service_id` sont renseignes par CHACUN des
            // points d'appel, juste avant l'appel. On les lisait pourtant en
            // dernier, apres deux sauts de relation qui peuvent echouer.
            $cible   = $l->service_id ?: (optional($paiement)->service_id ?: optional($facture)->service_id);
            $service = $l->service    ?: (optional($paiement)->service    ?: optional($facture)->service);

            // ON NE COMMISSIONNE QUE CE QUI PEUT ETRE RATTACHE.
            //
            // Une demande de livraison ne se commissionne pas — le guichet des
            // livraisons n'en cree aucune —, et une affaire introuvable ne se
            // commissionne pas davantage : la commission crediterait le solde
            // de l'apporteur sans justification tracable, et s'afficherait
            // sans client sur son telephone.
            //
            // On journalise plutot que de creer : une commission absente se
            // rattrape, une commission fausse se propage.
            $type = AffaireCommissionnable::typeSiRattachable($service, $cible);

            if (!$type) {
                \Log::warning('Commission non creee : affaire non commissionnable', [
                    'apporteur_id'    => $apporteur->id,
                    'ligne_paiement'  => $l->id,
                    'paiement_id'     => optional($paiement)->id,
                    'facture_id'      => optional($facture)->id,
                    'service'         => $service,
                    'service_id'      => $cible,
                    'montant_ecarte'  => round($l->montant * $taux / 100),
                ]);
                return;
            }

            $commission = new CommissionApporteur;
            $commission->commande_id  = $cible;
            $commission->apporteur_id = $apporteur->id;
            $commission->type_affaire = $type;
            // Arrondi au franc entier : le FCFA n'a pas de decimales (meme
            // convention que PaiementEnLigne).
            $commission->montant = round($l->montant * $taux / 100);
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
