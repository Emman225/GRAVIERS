<?php

namespace App\Http\Controllers;

use App\Http\Requests\imageRequest;
use App\Http\Requests\LivreurRequest;
use App\Mail\MailAccesUsers;
use App\Models\Admin;
use App\Models\Apporteur;
use App\Models\Banniere;
use App\Models\blog_commentaire;
use App\Models\blog;
use App\Models\Categorie;
use App\Models\Client;
use App\Models\Commande;
use App\Models\CommissionApporteur;
use App\Models\Configuration;
use App\Models\CoutLivraison;
use App\Models\DemandeCompteClientATerme;
use App\Models\DemandeLivraison;
use App\Models\DemandePaiement;
use App\Models\DetailLivraison;
use App\Models\Enlevement;
use App\Models\Facture;
use App\Services\FneService;
use App\Models\Fournisseur;
use App\Models\ImageProduit;
use App\Models\LignePaiement;
use App\Models\Livraison;
use App\Models\Livreur;
use App\Models\Location;
use App\Models\ModePaiement;
use App\Models\NoteProduit;
use App\Models\Paiement;
use App\Models\Pays;
use App\Models\PreuveOperation;
use App\Models\Produit;
use App\Models\Reduction;
use App\Models\Region;
use App\Models\RetourProduit;
use App\Models\StockProduit;
use App\Models\TicketSAV;
use App\Models\TypeUser;
use App\Models\User;
use App\Models\Vehicule;
use App\Models\Ville;
use Cviebrock\EloquentSluggable\Services\SlugService;
use Gloudemans\Shoppingcart\Facades\Cart;
use Help;
use Illuminate\Http\Request;
use Illuminate\Support\carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDF;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function appliqueTva(Client $client){
        $client->update([
            'applique_tva' => !$client->applique_tva
        ]);

        return back()->with('success','Action effectuée avec succès');
    }

    public function welcome(Request $request){
        $data = $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'zoom' => 'required|numeric',
        ]);

        notify()->success('Laravel Notify is awesome!');


        return redirect()->route('notify');
    }

    public function preuve(Commande $commande){

        return view('orders.preuve',[

            'commande' => $commande

        ]);
    }

    public function preuveValide(commande $commande){
        // dd($preuve);
        $preuve = $commande->preuve;
        $preuve->statut = 1;
        $preuve->user_id = Auth::user()->id;
        $preuve->update();

        return back()->with('success','Preuve validée !');

    }

    public function afficherStock($idProduit, $idFournisseur){
        $stock = StockProduit::lireCle($idFournisseur, $idProduit);
        return response()->json($stock);
    }

    public function actionFacture(Commande $commande, Facture $facture, $action, $livraison){

        // dd($commande, $facture, $action);

        $config = Configuration::first();

        $enlevements = Enlevement::where('facture_id', $facture->id)->get();

        $image = config("constantes.logo");

        $client = $commande->client;
        $fneData = FneService::getDonneesFne($facture, $client);

        $pdf = PDF::loadView('document.factureCommande', array_merge([
            'commande' => $commande, 'image' => $image, 'enlevements' => $enlevements,
            'facture' => $facture, 'config' => $config, 'livraison' => $livraison,
        ], $fneData))
            ->setOptions(['isHTML5ParseEnebled' => true, 'defaultPaperOrientation' => 'portait']);

        if($action == 'voir'){
            return $pdf->stream();
        }else{
            return $pdf->download();
        }
    }

    public function lesRegions(){
        $regions = Region::all();
        return view('gestionnaire.lesRegions',[
            'regions' => $regions,
            'laRegion' => new Region
        ]);
    }

    public function lesRegionsValid(Request $request){
        $request->validate([
            'nom' => 'required',
            'adresse_geo' => 'required',
            'long' => 'required',
            'lat' => 'required',
        ],[
            'nom.required' => 'Le nom est requis',
            'adresse_geo.required' => 'La description est requise',
            'long.required' => 'La longitude est requise',
            'lat.required' => 'La latitude est requise',
        ]);

        $region = new Region;
        $region->nom = $request->nom;
        $region->description = $request->adresse_geo;
        $region->long = $request->long;
        $region->lat = $request->lat;
        $region->user_id = Auth::user()->id;
        $region->save();

        return redirect()->route('show.lesRegions')->with('success','Région ajoutée');
    }

     public function modifierRegion(Region $region){
        return view('gestionnaire.lesRegions',[
            'regions' => Region::orderBy('nom','asc')->get(),
            'laRegion' => $region
        ]);
    }
    public function modifierRegionValid(Request $request, Region $region){
        // dd($request->all());
        $region->nom = $request->nom;
        $region->long = $request->long;
        $region->lat = $request->lat;
        $region->description = $request->adresse_geo;
        $region->save();

        return redirect()->route('show.lesRegions')->with('success','Région modifiée');
    }
    public function supprimerRegion(Region $region){

        $region->deleted_at = date('Y-m-d H:i:s');
        $region->save();
        return redirect()->route('show.lesRegions')->with('success','Région supprimée');
    }

    public function bloquerCompte($id, $type){

        $user = User::find($id);
        if (!$user) {
            return redirect()->back()->with('error', "Ce compte est introuvable (il a peut-être déjà été supprimé).");
        }
        $nomPrenom = '';
        // Valeurs par défaut : évitent un 500 pour les types sans profil séparé
        // (Super Admin / Admin) ou un type inattendu -> $theUser et $route restent définis.
        $route = 'listeGestionnaire';
        $theUser = $user;
        switch ($user->type_user_id) {
            case 1:
                // dd('gestion');
                $route = 'listeGestionnaire';

                break;

            case 2:
                // dd('gestion');
                $route = 'listeGestionnaire';
                break;

            case 3:
                // gestionnaire
                // dd('gestion');
                $route = 'listeGestionnaire';
                $nomPrenom = strtoupper($user->nom).' '.strtoupper($user->prenom);
                $theUser = $user;
                break;

            case 4:
                // dd('client');
                // client
                // $route = $user->client->statut == 3  ? 'listClientATerme' : 'listClient';
                $route = $user->client->client_a_terme == 1  ? 'listClientATerme' : 'listClient';
                $nomPrenom = strtoupper($user->client->nom).' '.strtoupper($user->client->prenom);
                $theUser = $user->client;
                break;

            case 5:
                // fournisseur
                // dd('fournisseur');
                $route = 'listSeller';
                $nomPrenom = strtoupper($user->nom_prenoms);
                $theUser = $user->getFournisseur;
                break;

            case 6:
                // apporteur
                // dd('apporteur');
                $route = 'listApporteur';
                $nomPrenom = strtoupper($user->nom_prenoms);
                $theUser = $user->getApporteur;
                break;

            case 7:
                // agent SAV
                $route = 'listeAgent';
                $nomPrenom = strtoupper($user->nom_prenoms);
                $theUser = $user;
                break;

            case 8:
                // livreur
                // dd('livreur');
                $route = 'list';
                $nomPrenom = strtoupper($user->nom_prenoms);
                $theUser = $user->livreur;
                break;
        }

        if($type == 'blok'){
            // dd($user);
            switch ($user->statut) {
                case 1:

                    DB::table('users')->where('id', $user->id)->update([
                        'statut' => 2
                    ]);

                    return redirect()->route('show.'.$route)->with('success',"vous avez bloqué le compte de ".strtoupper($nomPrenom));
                    break;

                case 2:
                    DB::table('users')->where('id', $user->id)->update([
                        'statut' => 1
                    ]);
                    return redirect()->route('show.'.$route)->with('success',"vous avez débloqué le compte de ".strtoupper($nomPrenom));
                    break;
            }

        }else{
            // $user->update([
            //     'deleted_at' => date('Y-m-d H:i:s')
            // ]);
            DB::table('users')->where('id', $user->id)->update([
                        'deleted_at' => date('Y-m-d H:i:s')
                    ]);
            // dd($theUser);
            $theUser->deleted_at = date('Y-m-d H:i:s');
            $theUser->update();

            return redirect()->route('show.'.$route)->with('locked',"vous avez supprimé le compte de ".strtoupper($nomPrenom));

        }
    }

    public function profileApporteur(Apporteur $apporteur){
        return view('apporteur.profile',[
            'apporteur' => $apporteur
        ]);
    }

    public function modifPiece(Request $request, Apporteur $apporteur){

        $request->validate([
            'recto.image' => 'Le fichier recto doit être une image.',
            'recto.mimes' => 'Seules les images JPG et PNG sont autorisées pour le recto.',
            'recto.max'   => 'L’image recto ne doit pas dépasser 2 Mo.',

            'verso.image' => 'Le fichier verso doit être une image.',
            'verso.mimes' => 'Seules les images JPG et PNG sont autorisées pour le verso.',
            'verso.max'   => 'L’image verso ne doit pas dépasser 2 Mo.',
        ]);


        $recto = null;
        $verso = null;

        if($request->hasFile('recto')){
            if($apporteur->piece_recto){
                Storage::disk('public')->delete($apporteur->piece_recto);
            }

            $recto = $request->file('recto')->store('ddd', 'public');

        }

        if($request->hasFile('verso')){
            if($apporteur->piece_verso){
                Storage::disk('public')->deleted($apporteur->piece->verso);
            }
            $verso = $request->file('verso')->store('piecesApporteurs','public');
        }

        $apporteur->piece_recto = $recto;
        $apporteur->piece_verso = $verso;
        $apporteur->update();

        return back()->with('success', 'Image mis à jour !');

    }

    public function demandeLivraisonlist(){
        $livraison = DemandeLivraison::where('statut',1)->latest()->get();
        return view('gestionnaire.demandeLivraisonlist',[
            'livraisons' => $livraison
        ]);
    }

    public function detailDemandeLivraison(DemandeLivraison $demande){

        return view('gestionnaire.detailDemandeLivraison',[
            'demande' => $demande
        ]);
    }

    public function traiteLivraisonPage(DemandeLivraison $demandeLivraison, Request $request){
        // dd($demandeLivraison->detailLivraison);
        return view('gestionnaire.traiteLivraison',[
            'livraisons' => $demandeLivraison,
            'vehicules' => Vehicule::orderByDesc('capacite')->get()
        ]);

    }

    public function selectionneVehicule($id,$detail){
        $car = Vehicule::find($id);
        $data = [
            'idCar' => $car->id,
            'marque' => $car->marque,
            'capacite' => $car->capacite,
            'livreur' => [
                'id' => $car->livreur->id,
                'numero' => $car->livreur->user->contact
            ],
            'vehicule_id' => $car->id,
            'immatriculation' => $car->immatriculation,
            'detail' => $detail
        ];
        return $data;
    }

    /**
     * Supprime (soft delete) une location « fantôme » : créée à la validation mais
     * jamais payée. Sécurité : uniquement les locations EN ATTENTE ET non soldées —
     * jamais une location payée (statut 3) ni déjà validée (EN COURS / TERMINE).
     */
    public function supprimerLocation(\App\Models\Location $location){
        if ($location->statut == 3) {
            return back()->with('error', 'Cette location est payée (soldée) : suppression impossible.');
        }
        if ($location->etatLibelle() !== Help::$LOCATION_EN_ATTENTE) {
            return back()->with('error', 'Seules les locations EN ATTENTE non payées peuvent être supprimées.');
        }
        $location->delete(); // soft delete (deleted_at)
        return back()->with('success', 'Location non payée supprimée.');
    }

    public function listeLocationEnAttente(){

        // Ne lister QUE les locations réellement EN ATTENTE : dès qu'une location est
        // validée (EN COURS) ou terminée (TERMINE), elle passe sur /locations-traitees.
        // On exclut donc EN COURS / TERMINE (valeurs texte ET entiers 2/3 pour l'historique).
        $locations = Location::with('factureFne')
            ->whereNotIn('etat_location', [Help::$LOCATION_EN_COURS, Help::$LOCATION_TERMINE, 'ANNULEE', 2, 3])
            ->orderByDesc('created_at')
            ->get();

        return view('gestionnaire.listeLocation',[
            'locations' => $locations
        ]);
    }

    /**
     * (c) Écran de validation d'une location EN ATTENTE : affectation livreur + véhicule
     * + saisie de la caution. Réservé aux locations non encore traitées.
     */
    public function validerLocationPage(\App\Models\Location $location){
        if ($location->etatLibelle() !== Help::$LOCATION_EN_ATTENTE) {
            return redirect()->route('show.listeLocationEnAttente')
                ->with('info', 'Cette location est déjà traitée (état : ' . $location->etatLibelle() . ').');
        }
        $location->load('detailLocation.produit', 'client');
        // Caution suggérée = somme (caution unitaire du produit × quantité) des lignes.
        $cautionSuggeree = (float) $location->detailLocation->sum(function ($d) {
            return (float) ($d->produit->caution ?? 0) * (float) $d->qte;
        });

        return view('gestionnaire.validerLocation', [
            'location'        => $location,
            'cautionSuggeree' => $cautionSuggeree,
            'livreurs'        => Livreur::where('statut', Help::$STATUT_ACTIF)->with('user')->get(),
            'vehicules'       => Vehicule::orderByDesc('capacite')->get(),
        ]);
    }

    /**
     * (c) Traitement de la validation : crée une livraison (provenance=LOCATION) par
     * ligne de location affectée au livreur/véhicule, enregistre la caution, et passe
     * la location EN COURS. Transactionnel.
     */
    public function validerLocation(Request $request, \App\Models\Location $location){
        $request->validate([
            'livreur'  => 'required|integer|exists:livreur,id',
            'vehicule' => 'required|integer|exists:vehicule,id',
            'caution'  => 'nullable|numeric|min:0',
        ], [
            'livreur.required'  => 'Veuillez sélectionner un livreur.',
            'vehicule.required' => 'Veuillez sélectionner un véhicule.',
        ]);

        if ($location->etatLibelle() !== Help::$LOCATION_EN_ATTENTE) {
            return redirect()->route('show.listeLocationEnAttente')
                ->with('info', 'Cette location est déjà traitée.');
        }

        $conf    = Configuration::first();
        $livreur = Livreur::find($request->livreur);

        // Distance (adresse de la location -> région) pour la rémunération du livreur.
        $distance = 0;
        $adr = \App\Models\AdresseLivraison::find($location->adresse_livraison_id);
        if ($adr && $adr->ville && $adr->ville->region) {
            $distance = Help::distance($adr->longitude, $adr->latitude, $adr->ville->region->long, $adr->ville->region->lat);
        }

        $livraisonsCreees = [];
        \DB::transaction(function () use ($location, $request, $livreur, $conf, $distance, &$livraisonsCreees) {
            foreach ($location->detailLocation as $detail) {
                // LOCATION : le matériel loué n'est pas mesuré en tonnes. Le repli de
                // rémunération est le coût d'UN déplacement (distance × coût fixe), SANS
                // facteur "voyages/tonnage" (qui n'a de sens que pour le gravier en vrac).
                // Les modes de tarification du livreur (km / base) priment de toute façon.
                $coutGlobal = (float) $distance * (float) ($conf->cout_liv_fixe ?? 0);
                $tarif = $livreur
                    ? $livreur->tarificationLivraison((float) $distance, $coutGlobal)
                    : ['forfait_base' => $coutGlobal, 'frais_km' => 0.0, 'total' => $coutGlobal];

                $liv = Livraison::create([
                    'numero'               => uniqid(),
                    'livreur_id'           => $request->livreur,
                    'vehicule_id'          => $request->vehicule,
                    'client_id'            => $location->client_id,
                    'adresse_livraison_id' => $location->adresse_livraison_id,
                    'date_livraison'       => $detail->debut ?? $location->date_location ?? now()->toDateString(),
                    'qte'                  => $detail->qte,
                    // Pour une livraison de LOCATION, detail_commande_id porte l'id du detail_location.
                    'detail_commande_id'   => $detail->id,
                    'provenance'           => Help::$LOCATION,
                    'cout_livraison'       => $tarif['total'],
                    'forfait_base'         => $tarif['forfait_base'],
                    'frais_km'             => $tarif['frais_km'],
                    'distance_km'          => round((float) $distance, 2),
                    'etat_livraison'       => Help::$LIVRAISON_EN_ATTENTE,
                    'statut'               => Help::$STATUT_ACTIF,
                    'gestionnaire_id'      => Auth::id(),
                    'accepte'              => 2,
                    'livre_par'            => 1,
                ]);
                // Pour l'envoi du code de validation au client (après commit).
                $livraisonsCreees[] = ['livraison' => $liv, 'produit' => $detail->produit];
            }

            $location->update([
                'livreur_id'    => $request->livreur,
                'vehicule_id'   => $request->vehicule,
                'caution'       => (float) ($request->caution ?? 0),
                'etat_location' => Help::$LOCATION_EN_COURS,
            ]);
        });

        // Envoi au client du CODE de validation (= numéro de livraison) qu'il communiquera
        // au livreur pour valider la livraison. NON bloquant : un échec d'email ne doit pas
        // empêcher la validation de la location.
        foreach ($livraisonsCreees as $item) {
            try {
                \Illuminate\Support\Facades\Mail::send(new \App\Mail\receptionCodeLivraisonLocation(
                    $item['livraison'],
                    $location,
                    $location->client,
                    $item['produit']
                ));
            } catch (\Throwable $e) {
                \Log::warning('Email code validation location non envoyé: '.$e->getMessage());
            }
        }

        return redirect()->route('show.listeLocationEnAttente')
            ->with('success', 'Location validée : livraison(s) créée(s), livreur affecté, location passée EN COURS.');
    }

    /**
     * (c) Écran de retour du matériel : saisie de la retenue éventuelle sur caution.
     */
    public function retourLocationPage(\App\Models\Location $location){
        if ($location->etatLibelle() !== Help::$LOCATION_EN_COURS) {
            return redirect()->route('show.listeLocationEnAttente')
                ->with('info', 'Le retour ne concerne qu\'une location EN COURS (état actuel : ' . $location->etatLibelle() . ').');
        }
        return view('gestionnaire.retourLocation', [
            'location' => $location->load('detailLocation.produit', 'client'),
        ]);
    }

    /**
     * (c) Retour du matériel loué : la location EN COURS passe TERMINÉ. On note la date
     * de retour et la RETENUE éventuelle sur la caution (dégâts). Le montant restitué =
     * caution - caution_retenue.
     */
    public function retourLocation(Request $request, \App\Models\Location $location){
        if ($location->etatLibelle() !== Help::$LOCATION_EN_COURS) {
            return redirect()->route('show.listeLocationEnAttente')
                ->with('info', 'Le retour ne concerne qu\'une location EN COURS (état actuel : ' . $location->etatLibelle() . ').');
        }

        $caution = (float) ($location->caution ?? 0);
        $request->validate([
            'caution_retenue' => 'nullable|numeric|min:0|max:' . $caution,
            'motif_retenue'   => 'nullable|string|max:255',
        ], [
            'caution_retenue.max' => 'La retenue ne peut pas dépasser la caution (' . $caution . ' fcfa).',
        ]);

        $retenue = (float) ($request->caution_retenue ?? 0);

        $location->update([
            'etat_location'     => Help::$LOCATION_TERMINE,
            'date_retour'       => now()->toDateString(),
            'caution_retenue'   => $retenue,
            'motif_retenue'     => $retenue > 0 ? $request->motif_retenue : null,
            // caution_restituee = true dès qu'une partie (ou la totalité) est rendue.
            'caution_restituee' => $retenue < $caution,
        ]);

        $restitue = max(0, $caution - $retenue);
        return redirect()->route('show.listeLocationEnAttente')
            ->with('success', 'Matériel retourné : location TERMINÉE. Caution restituée : '
                . number_format($restitue, 0, ',', ' ') . ' fcfa'
                . ($retenue > 0 ? ' (retenue : ' . number_format($retenue, 0, ',', ' ') . ' fcfa).' : '.'));
    }

    /**
     * (c) Liste des locations TRAITÉES (déjà validées) : EN COURS ou TERMINÉ.
     * Page distincte de la liste des commandes traitées (évite la confusion).
     */
    public function locationsTraitees(){
        $locations = Location::whereIn('etat_location', [Help::$LOCATION_EN_COURS, Help::$LOCATION_TERMINE, 2, 3])
            ->with('client', 'livreur.user', 'factureFne')
            ->orderByDesc('updated_at')
            ->get();

        return view('gestionnaire.locationsTraitees', [
            'locations' => $locations,
        ]);
    }

    public function modifierPrixLivraison(Livreur $livreur, Request $request){

        // Le livreur (ou l'admin) choisit son mode de tarification :
        //  - 'base' : un tarif forfaitaire (cout_livraison)
        //  - 'km'   : un tarif par kilomètre (tarif_km)
        $mode = in_array($request->mode_tarification, ['base', 'km']) ? $request->mode_tarification : 'base';

        if ($mode === 'km') {
            if ($request->tarif_km < 1) {
                return redirect()->route('show.profile',$livreur->id)->with('error','Le tarif par KM saisi est incorrect');
            }
            $livreur->update([
                'mode_tarification' => 'km',
                'tarif_km'          => (int) $request->tarif_km,
            ]);

            return redirect()->route('show.profile',$livreur->id)->with('success','Modification effectuée');
        }

        // Mode tarif de base
        if($request->montant < 1){
            return redirect()->route('show.profile',$livreur->id)->with('error','Le montant saisi est incorrect');
        }

        $ancienPrix = (float) ($livreur->cout_livraison ?? 0);
        $nouveauPrix = (float) $request->montant;

        // Trace l'historique uniquement si le prix a réellement changé.
        if ($ancienPrix != $nouveauPrix) {
            \App\Models\HistoriquePrixLivraisonLivreur::create([
                'livreur_id'   => $livreur->id,
                'ancien_prix'  => $ancienPrix,
                'nouveau_prix' => $nouveauPrix,
                'user_id'      => Auth::id(),
                'motif'        => $request->motif,
            ]);
        }

        $livreur->update([
            'mode_tarification' => 'base',
            'cout_livraison'    => $nouveauPrix,
        ]);

        return redirect()->route('show.profile',$livreur->id)->with('success','Modification effectuée');
    }

    /**
     * Met à jour la zone d'intervention d'un livreur (profil admin).
     */
    public function modifierZoneLivreur(Livreur $livreur, Request $request){
        $request->validate([
            'zone_intervention' => 'nullable|string|max:190',
        ]);

        $livreur->update([
            'zone_intervention' => $request->zone_intervention,
        ]);

        return redirect()->route('show.profile', $livreur->id)->with('success', "Zone d'intervention mise à jour");
    }

    public function traitelivraison(DemandeLivraison $demandeLivraison, DetailLivraison $detail, Request $request){

        // dd($deman+deLivraison,$detail);
        // $statut = [];
        // $statut = HELP::listeStatutLivraison();
        // dd($demandeLivraison->detailLivraison->cout_livraison_id);
        $date = $request->date;
        $qt = $detail->qte;

        if(!$detail->livraisons->isEmpty()){
            $qt = $detail->qte - $detail->livraisons->sum('qte');
        }

        // dd($qt,$detail->qte,$detail->livraisons->sum('qte'));
        // dd(!$demandeLivraison->livraisons->isEmpty(),$qt,$detail);

        if($request->date == null){
            $date = $demandeLivraison->date_livraison;
        }



        foreach ($request->id as $camionId) {

            $camion = Vehicule::where('id',$camionId)->first();
            $qt = $qt - $camion->capacite;

            if($qt >= 0){
                $qt1 = $camion->capacite;
            }else{
                $qt1 = $qt + $camion->capacite;
            }

            //dd($camion);

            // array_push($lesqte,$qt1);

            //$detail = $demandeLivraison->detailLivraison;
            //dd($detail);
            // dd(CoutLivraison::find($detail->cout_livraison_id));
            $livraison = [
                'numero' => uniqid(),
                'client_id' => $demandeLivraison->client_id,
                'livreur_id' =>  $camion->livreur_id,
                'adresse_livraison_id' => $demandeLivraison->destination->id,
               // 'cout_livraison_id' => $detail->cout_livraison_id,
                'date_livraison' => $demandeLivraison->date_livraison,
                'provenance' => 2,
                'detail_livraison_id' => $detail->id,
                'type_livraison_id' => $demandeLivraison->type_livraison_id,
                'qte' => $qt1,
                'gestionnaire_id' => Auth::user()->id,
                'vehicule_id' => intval($camionId),
                'accepte' => 1,
            ];


            $l = Livraison::create($livraison);

            // dd($livraison, $l);
        }

        $demandeLivraison->update([
            // 'statut' => 2,
            'etat_commande' => 2
        ]);

        $detail->update([
            'etat_livraison' => 2
        ]);

        $camion->update([
            'disponible' => 0
        ]);
        // dd('ok');



        return redirect()->route('show.traitelivraisonPage',$demandeLivraison)->with('success','Traitement Validé');
    }

    public function demandeLivraisonTraitee(){
        $demande = DemandeLivraison::where('etat_commande',3)->orderByDesc('updated_at')->get();

        return view('gestionnaire.demandeDeLivraisonTraitee',[
            'livraisons' => $demande
        ]);
    }

    public function livraisonValidees(){
        return view('gestionnaire.livraisonValidees',[
            'livraisons' => Livraison::where('etat_livraison','LIVREE')->orderByDesc('updated_at')->get()
        ]);
    }

    public function restaureLivraison(Request $request, Livraison $livraison){

        $nouvelleLivraison = Livraison::create([
            'numero' => uniqid(),
            'livreur_id' => $request->livreur,
            'client_id' => $livraison->client_id,
           // 'commande_id' => $livraison->commande_id,
            'adresse_livraison_id' => $livraison->adresse_livraison_id,
            'date_livraison' => $livraison->date_livraison,
            'detail_commande_id' => $livraison->detail_commande_id,
            'qte' => $livraison->qte,
            'type_livraison_id' => $livraison->type_livraison_id,
            'provenance' => $livraison->provenance,
            'gestionnaire_id' => Auth::user()->id,
            'accepte' => 2,
            'date_accord' => date('Y-m-d H:i:s'),
            'vehicule_id' => $request->vehicule,
            'livre_par' => $livraison->livre_par
        ]);

        $enlevement = $livraison->enlevement;

        $nouvelEnlevement = Enlevement::create([
            'fournisseur_id' => $enlevement->fournisseur_id,
            'livraison_id' => $nouvelleLivraison->id,
            'qte' => $enlevement->qte,

            'produit_id' => $enlevement->produit_id,
            'livreur_id' => $request->livreur,
            'code_enleve' => $this->generateCode(),

            'prix_fournisseur' => $enlevement->prix_fournisseur,
            'vehicule_id' => $request->vehicule,
        ]);

        $livraison->update([
            'statut' => '3'
        ]);

        $enlevement->update([
            'statut' => '3'
        ]);

        return redirect()->route('show.livraisonEnCours')->with('success','Livraison restituée');
    }

    public function livraisonEnCours(){
        $livraison = Livraison::distinct()
        ->selectRaw("livraison.*,
        users.nom_prenoms as nom_livreur,
        users.contact as contact_livreur,
        concat(client.nom,' ',client.prenom) as nom_client,
        client.contact1 as contact_client,
        adresse_livraison.affichage as adresse,
        adresse_livraison.complement_adresse,
        type_livraison.libelle as type_livraison,
        enlevement.code_enleve as code_enlevement,
        fournisseur.nom_prenoms as nom_fournisseur,
        fournisseur.contact1 as tel_fournisseur,
        fournisseur.adresse_geo as adresse_fournisseur
        ")
        ->join('livreur', 'livreur.id', '=', 'livraison.livreur_id')
        ->join('users', 'users.id', '=', 'livreur.user_id')
        ->join('adresse_livraison', 'adresse_livraison.id', '=', 'livraison.adresse_livraison_id')
        ->join('type_livraison', 'type_livraison.id', '=', 'livraison.type_livraison_id')
        ->join('client', 'client.id', '=', 'livraison.client_id')
        ->leftJoin('enlevement', 'enlevement.livraison_id', '=', 'livraison.id')
        ->leftJoin('fournisseur', 'fournisseur.id', '=', 'enlevement.fournisseur_id')
        ->orderBy('livraison.id', 'desc')
        ->where('livraison.statut', Help::$STATUT_ACTIF)
        ->limit(1000)
        ->get();
        return view('gestionnaire.livraisonEnCours',[
            'livreurs' => Livreur::where('statut', Help::$STATUT_ACTIF)->get(),
            'livraisons' => $livraison,
        ]);
    }
    public function livraisonHistorique(){
        // $livraison = Livraison::distinct()
        // ->selectRaw("livraison.*,
        // users.nom_prenoms as nom_livreur,
        // users.contact as contact_livreur,
        // concat(client.nom,' ',client.prenom) as nom_client,
        // client.contact1 as contact_client,
        // adresse_livraison.affichage as adresse,
        // adresse_livraison.complement_adresse,
        // type_livraison.libelle as type_livraison,
        // enlevement.code_enleve as code_enlevement,
        // fournisseur.nom_prenoms as nom_fournisseur,
        // fournisseur.contact1 as tel_fournisseur,
        // fournisseur.adresse_geo as adresse_fournisseur
        // ")
        // ->join('livreur', 'livreur.id', '=', 'livraison.livreur_id')
        // ->join('users', 'users.id', '=', 'livreur.user_id')
        // ->join('adresse_livraison', 'adresse_livraison.id', '=', 'livraison.adresse_livraison_id')
        // ->join('type_livraison', 'type_livraison.id', '=', 'livraison.type_livraison_id')
        // ->join('client', 'client.id', '=', 'livraison.client_id')
        // ->leftJoin('enlevement', 'enlevement.livraison_id', '=', 'livraison.id')
        // ->leftJoin('fournisseur', 'fournisseur.id', '=', 'enlevement.fournisseur_id')
        // ->orderBy('livraison.id', 'desc')
        // ->where('livraison.statut', Help::$STATUT_ACTIF)
        // ->limit(1000)
        // ->get();

        $livraison = Livraison::where('deleted_at', null)->get();

        return view('gestionnaire.livraisonHistorique',[
            'livreurs' => Livreur::where('statut', Help::$STATUT_ACTIF)->get(),
            'livraisons' => $livraison,
        ]);
    }

    public function retourTraite(RetourProduit $retour){
        // dd($retour);
        return view('admin.retourTraite',[
            'retour' => $retour
        ]);
    }

    public function termesConditions()
    {
        return view('legal.termes-conditions', [
            'produits'   => \App\Models\Produit::where('statut', \Help::$STATUT_ACTIF)->get(),
            'categories' => \App\Models\Categorie::all(),
            'config'     => Configuration::first(),
        ]);
    }

    public function centreAide()
    {
        $user = Auth::user();
        $typeLabel = match((int) $user->type_user_id) {
            \Help::$USER_SA            => 'Super administrateur',
            \Help::$USER_ADMIN         => 'Administrateur',
            \Help::$USER_GESTIONNAIRE  => 'Gestionnaire',
            \Help::$USER_CLIENT        => 'Client',
            \Help::$USER_FOURNISSEUR   => 'Fournisseur',
            \Help::$USER_APPORTEUR     => 'Apporteur d\'affaire',
            \Help::$USER_AGENT_SAV     => 'Agent SAV',
            \Help::$USER_LIVREUR       => 'Livreur',
            default                    => 'Utilisateur',
        };

        return view('aide.index', [
            'user'      => $user,
            'typeLabel' => $typeLabel,
        ]);
    }

    public function monProfil()
    {
        $user = Auth::user();
        $typeLabel = match((int) $user->type_user_id) {
            \Help::$USER_SA            => 'Super administrateur',
            \Help::$USER_ADMIN         => 'Administrateur',
            \Help::$USER_GESTIONNAIRE  => 'Gestionnaire',
            \Help::$USER_CLIENT        => 'Client',
            \Help::$USER_FOURNISSEUR   => 'Fournisseur',
            \Help::$USER_APPORTEUR     => 'Apporteur d\'affaire',
            \Help::$USER_AGENT_SAV     => 'Agent SAV',
            \Help::$USER_LIVREUR       => 'Livreur',
            default                    => 'Utilisateur',
        };

        return view('profil.index', [
            'user'       => $user,
            'typeLabel'  => $typeLabel,
        ]);
    }

    public function monProfilUpdate(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'nom_prenoms' => 'required|string|max:150',
            'email'       => 'required|email|max:150',
            'contact'     => 'nullable|string|max:30',
            'adresse'     => 'nullable|string|max:200',
            'photo'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        // Unicité email
        $emailDejaPris = User::where('email', $request->email)
            ->where('id', '!=', $user->id)->exists();
        if ($emailDejaPris) {
            return back()->withInput()->with('emailExiste', 'Cet email est déjà utilisé par un autre compte.');
        }

        // Login non modifiable côté utilisateur (verrouillé par design).
        // Si un changement est nécessaire, il doit passer par un administrateur.

        // Upload de la photo — même destination que registerAdmin/storeUser :
        // directement dans public/storage/imageUser/ (accessible via /storage/imageUser/)
        // pour fonctionner sans dépendance au symlink Laravel.
        if ($request->hasFile('photo')) {
            $destinationDir = public_path('storage/imageUser');
            if (!is_dir($destinationDir)) {
                @mkdir($destinationDir, 0775, true);
            }

            // Supprime l'ancienne si elle existe
            if ($user->photo) {
                $oldPath = str_contains($user->photo, '/')
                    ? public_path('storage/' . $user->photo)
                    : $destinationDir . '/' . $user->photo;
                if (file_exists($oldPath)) {
                    @unlink($oldPath);
                }
            }

            $ext = $request->file('photo')->getClientOriginalExtension();
            $nomImage = 'image_user_' . $user->id . '_' . date('YmdHis') . '.' . $ext;
            $request->file('photo')->move($destinationDir, $nomImage);
            $user->photo = $nomImage;  // juste le nom de fichier (résolu via imageUser/ côté affichage)
        }

        // Champs simples
        $user->nom_prenoms = $request->nom_prenoms;
        $user->email       = $request->email;
        $user->contact     = $request->contact;
        $user->adresse     = $request->adresse;

        // Mot de passe (optionnel)
        if ($request->filled('oldPassWord') || $request->filled('newPassWord') || $request->filled('confirmPassWord')) {
            if (!$request->filled('oldPassWord')) {
                return back()->withInput()->with('errorPassword', 'Veuillez saisir votre ancien mot de passe.');
            }
            if (!\Help::HashVerifier($request->oldPassWord, $user->password)) {
                return back()->withInput()->with('errorPassword', 'Ancien mot de passe incorrect.');
            }
            if (!$request->filled('newPassWord')) {
                return back()->withInput()->with('passDifferent', 'Veuillez saisir un nouveau mot de passe.');
            }
            if ($request->newPassWord !== $request->confirmPassWord) {
                return back()->withInput()->with('passDifferent', 'Les deux mots de passe ne correspondent pas.');
            }
            $user->password = \Help::HashPassword($request->newPassWord);
        }

        $user->save();

        return redirect()->route('show.monProfil')->with('success', 'Profil mis à jour avec succès.');
    }

    public function demandeDepaiePage(){
        $authUser = Auth::user();
        $profilLabel = 'Utilisateur';

        switch($authUser->type_user_id){
            case 5:
                $user = Fournisseur::where('user_id', $authUser->id)->first();
                $profilLabel = 'Fournisseur';
                break;
            case 6:
                $user = Apporteur::where('user_id', $authUser->id)->first();
                $profilLabel = 'Apporteur';
                break;
            case 8:
                $user = Livreur::where('user_id', $authUser->id)->first();
                $profilLabel = 'Livreur';
                break;
        }

        $demandes = DemandePaiement::where('user_id', $authUser->id)
            ->with('modePaiement')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $totalDemandes  = DemandePaiement::where('user_id', $authUser->id)->count();
        $totalEnAttente = DemandePaiement::where('user_id', $authUser->id)
            ->where(function($q){ $q->whereNull('paye')->orWhere('paye', 0); })
            ->count();
        $totalPayees    = DemandePaiement::where('user_id', $authUser->id)
            ->where('paye', 1)->count();
        $montantEnAttente = (float) DemandePaiement::where('user_id', $authUser->id)
            ->where(function($q){ $q->whereNull('paye')->orWhere('paye', 0); })
            ->sum('montant');

        return view('livreur.demandeDePaie', [
            'user'              => $user,
            'profilLabel'       => $profilLabel,
            'modesPaie'         => ModePaiement::all(),
            'demandes'          => $demandes,
            'totalDemandes'     => $totalDemandes,
            'totalEnAttente'    => $totalEnAttente,
            'totalPayees'       => $totalPayees,
            'montantEnAttente'  => $montantEnAttente,
        ]);
    }

    public function demandeDepaie(Request $request){
        // dd($request->all());

        // die;
        // return;

        $montant = $request->montant;


        $user = Auth::user();

        if($request->montant == 0){
            return redirect()->route('show.demandeDepaiePage')->with('error','0fcfa n\'est pas autorisé comme montant');
        }

        if($user->type_user_id == 8) {
            //livreur
            $livreur = Livreur::where('user_id', $user->id)->first();
            if($montant > $livreur->solde){

                return redirect()->route('show.demandeDepaiePage')->with('error','Veuillez entrer un montant inférieur ou égale à votre solde');
            }

            $livreur->update([
                'solde' => $livreur->solde - $montant
            ]);

        }elseif($user->type_user_id == 6){
            $apporteur = Apporteur::where('user_id', $user->id)->first();
            if($montant > $apporteur->solde){
                return redirect()->route('show.demandeDepaiePage')->with('error','Veuillez entrer un montant inférieur ou égale à votre solde');
            }

            $apporteur->update([
                'solde' => $apporteur->solde - $montant
            ]);
        }elseif($user->type_user_id == 5){
            $frs = Fournisseur::where('user_id', $user->id)->first();
            if($montant > $frs->solde){
                return redirect()->back()->with('error','Veuillez entrer un montant inférieur ou égale à votre solde');
            }

            $frs->update([
                'solde' => $frs->solde - $montant
            ]);
        }

        $demande = demandePaiement::create([
           'numero' => Help::getCommandeNo(),
            'montant' => $montant,
            'user_id' => $user->id,
            'numero_compte' => $request->numero,
            "mode_paiement_id" => $request->modePaie,
        ]);

        // dd($demande);

        return redirect()->back()->with('success','Votre demande a été envoyée');

    }



    public function retourValide(RetourProduit $retour, Request $request){

        // dd('validé');

        $data = [
            'user_id' => Auth::user()->id,
            'user_paie_id' => Auth::user()->id,
            'date_reception' => date('Y-m-d H:i:s'),
            'observation_reception' => $request->observation,
            'date_rembourssement' => date('Y-m-d H:i:s '),
            'statut' => 2
        ];


        $retour->update($data);

        return redirect()->route('show.listeRetourProduit')->with('ok','Retour approuvé');


    }

    public function refuseRetour(RetourProduit $retour, Request $request){

        // dd('réfusé');

        $data = [
            'user_id' => Auth::user()->id,
            'user_paie_id' => Auth::user()->id,
            'date_reception' => date('Y-m-d H:i:s'),
            'observation_reception' => $request->observation,
            'date_rembourssement' => date('Y-m-d H:i:s '),
            'statut' => 3
        ];


        $retour->update($data);

        return redirect()->route('show.listeRetourProduit')->with('no','Retour réfusé');

    }

    public function notify(Request $request){

        // dd($request->loca);
        // dd(Help::typeCompte());
        // \Mckenziearts\Notify\Facades\LaravelNotify::error('Votre message');


        notify()->success('Welcome to Laravel Notify ⚡️');
        // dd(session('notify_messages'));
        // drakify('success', 'Enregistrement réussi');
        // smilify('success', 'Enregistrement réussi');
        // emotify('success', 'Enregistrement réussi');
        // emotify('success', 'Enregistrement réussi');
        return view('notify');
    }

    public function listeGestionnaire(){

        return view('admin.listeGestionnaire',[
            'gestionnaires' => User::where('type_user_id', 3)->whereNull('deleted_at')->orderByDesc('created_at')->get()
        ]);

    }

    /**
     * Liste des comptes administrateur (type_user_id = 2).
     */
    public function listeAdmin(){
        return view('admin.listeAdmin', [
            'admins' => User::where('type_user_id', Help::$USER_ADMIN)
                ->whereNull('deleted_at')
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    /**
     * Activer/Désactiver un compte admin.
     * Garde-fou : interdit sur soi-même + interdit si dernier admin actif.
     */
    public function toggleAdminStatus($id)
    {
        $user = User::where('type_user_id', Help::$USER_ADMIN)
            ->whereNull('deleted_at')
            ->find($id);

        if (!$user) {
            return back()->with('error', 'Administrateur introuvable.');
        }

        if ((int) $user->id === (int) Auth::id()) {
            return back()->with('error', 'Vous ne pouvez pas désactiver votre propre compte.');
        }

        // Si on tente de désactiver et qu'il ne reste que cet admin actif
        if ((int) $user->statut === 1) {
            $nbActifs = User::where('type_user_id', Help::$USER_ADMIN)
                ->where('statut', 1)
                ->whereNull('deleted_at')
                ->count();
            if ($nbActifs <= 1) {
                return back()->with('error', 'Impossible de désactiver le dernier administrateur actif.');
            }
        }

        $nouveauStatut = ((int) $user->statut === 1) ? 0 : 1;
        $user->statut = $nouveauStatut;
        $user->save();

        $action = $nouveauStatut === 1 ? 'réactivé' : 'désactivé';
        return back()->with('ok', "Compte de {$user->nom_prenoms} {$action}.");
    }

    /**
     * Supprime (soft delete) un compte admin.
     * Garde-fou : interdit sur soi-même + interdit si dernier admin actif.
     */
    public function deleteAdmin($id)
    {
        $user = User::where('type_user_id', Help::$USER_ADMIN)
            ->whereNull('deleted_at')
            ->find($id);

        if (!$user) {
            return back()->with('error', 'Administrateur introuvable.');
        }

        if ((int) $user->id === (int) Auth::id()) {
            return back()->with('error', 'Vous ne pouvez pas supprimer votre propre compte.');
        }

        $nbActifs = User::where('type_user_id', Help::$USER_ADMIN)
            ->where('statut', 1)
            ->whereNull('deleted_at')
            ->where('id', '!=', $user->id)
            ->count();
        if ($nbActifs < 1) {
            return back()->with('error', 'Impossible de supprimer le dernier administrateur actif.');
        }

        $nom = $user->nom_prenoms;
        $user->delete(); // Soft delete

        return back()->with('ok', "Compte de {$nom} supprimé.");
    }

    public function listeAgent(){
        return view('gestionnaire.listeAgent',[
            'agents' => User::where('type_user_id', 7)->whereNull('deleted_at')->orderByDesc('created_at')->get()
        ]);
    }

    public function sellersList(){
        // $this->verificationStock();


        return view('fournisseur.sellers-list',[

            'fournisseurs' => Fournisseur::all(),
        ]);
    }

    public function sellersListPourBon(){

        return view('fournisseur.fournisseurPourbon',[

            'fournisseurs' => Fournisseur::all(),
        ]);
    }
      // Modification des informations d'un fournisseur
    public function editSellers($id){
        // $this->verificationStock();
        $fournisseur = Fournisseur::find($id);
        $data = Produit::all();
        return view('fournisseur.edit-sellers',[
            'fournisseur' => $fournisseur,
            'produits' => $data,
        ]);
    }

    public function updateSeller(Request $request){
        // dd($request->produits);
        // $this->verificationStock();



        $fournisseur = Fournisseur::where('id',$request->id)->first();

        $produitsSelectionnes = $request->produits ?? [];

        // IDs déjà rattachés : on préserve leur pivot (prix/qte/seuil déjà configurés).
        $dejaRattaches = $fournisseur->produits->pluck('id')->all();

        $syncData = [];
        foreach ($produitsSelectionnes as $produitId) {
            if (in_array($produitId, $dejaRattaches)) {
                // Produit déjà présent : pivot vide => sync ne modifie pas les valeurs existantes.
                $syncData[$produitId] = [];
            } else {
                $produitModel = Produit::find($produitId);
                if (!$produitModel) {
                    continue;
                }
                // Nouveau produit : on renseigne le pivot (sinon prix/qte restent vides).
                $syncData[$produitId] = [
                    'prix'        => $produitModel->prix_fournisseur ?? $produitModel->prix_moyen ?? 0,
                    'qte'         => 0,
                    'seuil_alert' => 10,
                    'statut'      => Help::$STATUT_ACTIF,
                ];
            }
        }

        // sync() détache les produits décochés (comportement attendu du formulaire à cases).
        $fournisseur->produits()->sync($syncData);

        // Type de fournisseur + produit principal (colonnes affichées dans la liste).
        $fournisseur->type_fournisseur  = $request->type_fournisseur;
        $fournisseur->produit_principal = $request->produit_principal;
        $fournisseur->save();

        $user = User::where('id',$fournisseur->user_id)->first();


        return redirect()->route('show.editSellers',$fournisseur->id)->with('success','Modification effectuée');

    }

    public function registerSeller(){

        $fournisseur = new Fournisseur();
        $user = new User;
        $data = Produit::all();


        return view('fournisseur.register',[
            'produits' => $data,
            'fournisseur' => $fournisseur,
            'user' => $user,
            'mode' => 'ajout',
        ]);
    }
     // Enregistrement d'un fournisseurs
    public function store(Request $request){
        // dd($request->all());
        $contact = "";

        $request->validate([
            //
            'nom_prenoms'=>'required',
            'email'=> 'required|email|unique:users,email',
            'contact'=> 'required',
            'contact2' => 'nullable' ,
            'long' => 'required' ,
            'lat' => 'required' ,
            'adresse_geo' => 'required',
            'adresse_postale'=>'required',
            'produits' => 'required',
            'type_fournisseur' => 'nullable|string|max:30',
            'produit_principal' => 'nullable|string|max:100',
            'dfe' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'registre_commerce' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            ],[
                'nom_prenoms.required' => 'Le nom et prénoms est obligatoire',
                'email.required' => 'L\'email est obligatoire',
                'email.email' => 'L\'email doit être valide',
                'email.unique' => 'L\'email existe déjà.',
                'contact.required' => 'Le contact est obligatoire',
                'long.required' => 'Veuillez selectionner un point sur la carte',
                'lat.required' => 'La latitude est obligatoire',
                'adresse_geo.required' => 'L\'adresse géographique est obligatoire',
                'adresse_postale.required' => 'L\'adresse postale est obligatoire',
                'produits.required' => 'Selectionnez au moins un produit',
            ]);

        $typeUserId = 5;


        $slug = SlugService::createSlug(User::class, 'login', $request->nom_prenoms);
        $user2 = User::withTrashed()->where('login', $slug)->value('login');


        if ($slug == $user2) {
            # code...
            $slug = $slug.''.rand(0,100);

        }




        // Mot de passe TOUJOURS généré automatiquement (jamais saisi par l'admin),
        // envoyé au fournisseur par email via MailAccesUsers plus bas.
        $rawPassword = Help::ChaineAleatoire(8);
        $pwd = Help::HashPassword($rawPassword);

        //Enregistrement dans la table user
        $dataUser = [
            'login' => $slug,
            'password' => $pwd,
            'email' => $request->email,
            'type_user_id' => $typeUserId,
            'nom_prenoms' => $request->nom_prenoms,
            'contact' => $contact,
        ];

        $user = User::create($dataUser);
        $userId = $user->id;
        //Enregistrement dans la table Fournisseur
        $dataFrs = [
            'nom_prenoms' => $request->nom_prenoms,
            'contact1' => $request->contact,
            'contact2' => $request->contact2,
            'adresse_geo' => $request->adresse_geo,
            'adresse_postale' => $request->adresse_postale,
            'longitude' => $request->long,
            'latitude' => $request->lat,
            'user_id' => $userId,
            'email' => $request->email,
            'type_fournisseur' => $request->type_fournisseur,
            'produit_principal' => $request->produit_principal,
        ];

        //dd($dataFrs);

        $frs = Fournisseur::create($dataFrs);
        $frsId = $frs->id;
        $frs = Fournisseur::find($frsId);

        // Enregistrement des documents (DFE + registre du commerce)
        foreach (['dfe', 'registre_commerce'] as $docField) {
            if ($request->hasFile($docField)) {
                $ext  = $request->file($docField)->getClientOriginalExtension();
                $path = $request->file($docField)->storeAs("documents_entreprise/fournisseur_{$frsId}", "{$docField}.{$ext}", 'public');
                $frs->{$docField} = $path;
            }
        }
        $frs->save();

        //Affectation des produits au fournisseur
        $produits = $request->produits ?? [];

        foreach ($produits as $produitId) {
            $produitModel = Produit::find($produitId);
            if (!$produitModel) {
                continue;
            }

            // On renseigne les données du pivot stock_produit (sinon prix/qte/statut
            // restent vides et le produit n'apparaît pas correctement au catalogue).
            // syncWithoutDetaching évite de créer un doublon si le produit est déjà rattaché.
            $frs->produits()->syncWithoutDetaching([
                $produitId => [
                    'prix'        => $produitModel->prix_fournisseur ?? $produitModel->prix_moyen ?? 0,
                    'qte'         => 0,
                    'seuil_alert' => 10,
                    'statut'      => Help::$STATUT_ACTIF,
                ],
            ]);
        }

        $produitList = Produit::all();

        $fournisseur = new Fournisseur;

        try {
            Mail::send(new MailAccesUsers($request->nom_prenoms, $user->login, $rawPassword, $user->email, 'sellers'));
        } catch (\Exception $e) {
            \Log::error('Erreur envoi email fournisseur: ' . $e->getMessage());
        }

        session()->put(['login' => $slug]);
        return redirect()->route('show.registerSeller')->with('success','Compte fournisseur créé avec succès ! Un email avec vos accès a été envoyé.');
        // return view('fournisseur.register',[
        //     'produits' => ,
        //     'fournisseur' =>
        // ])
    }

    public function bonParFournisseur(Fournisseur $fournisseur){
        // dd('rd');
        // $query = Enlevement::where('fournisseur_id',$fournisseur->id);
        $enlevements = Enlevement::where('fournisseur_id',$fournisseur->id)->orderBy('created_at','desc')->get();
        $enlevementTraite = Enlevement::where('fournisseur_id',$fournisseur->id)->where('qte_servi','!=',null)->get();

        $montantTotal = 0;
        foreach($enlevementTraite as $enlevement){

            $montantTotal += $enlevement->qte_servi * $enlevement->livraison->detailCommande->prix;
        }

        // dd($montantTotal);
        return view('fournisseur.enlevementParFournisseur',[
            'enlevements' => $enlevements,
            'montantTotal' => $montantTotal,
            'fournisseur' => $fournisseur
        ]);
    }

    public function listeCommissionApporteur(Request $request){
        $apporteur = $request->apporteur ?? null;
        $type_affaire = $request->type_affaire ?? null;
        $apporteurs = Apporteur::liste();
        $coms = CommissionApporteur::liste(null, $apporteur, $type_affaire);
        return view('apporteur.commission',compact("coms", "apporteurs"));
    }

    public function pourcentage(Apporteur $apporteur, Request $request){
        // dd($request->all());

        if($request->pourcentage > 0 && $request->pourcentage <= 100){
            $apporteur->update([
                'pourcentage' => $request->pourcentage
            ]);
            return redirect()->route('show.listApporteur')->with('success','Le pourcentage a été mis à jour');
        }else{
            return redirect()->back()->with('error','Le pourcentage doit être entre 0 et 100');
        }
    }

    //etats de demande de paiement livreurs
    public function listeDeDemande(){

            //demande de paiement des livreurs
            $demandes = DemandePaiement::join('users', 'users.id', '=', 'demande_paiement.user_id')
            ->where('users.type_user_id', 8)
            ->where('demande_paiement.deleted_at', null)
            ->orderByDesc('demande_paiement.created_at')
            ->select('demande_paiement.*')
            ->get();

            //dd($demandes);


        return view('admin.listeDeDemande',[
            'demandes' => $demandes,
            'config' => Configuration::first()
        ]);
    }

    public function listApporteur(){
        $apporteur = Apporteur::with(['user', 'modePaiement'])->orderByDesc('created_at')->get();

        return view('apporteur.list',[
            'apporteurs' => $apporteur
        ]);
    }

    public function listLivreur()
    {
        return view('livreur.list', [
            'livreurs' => Livreur::with('user')->where('deleted_at', null)->orderBy('created_at', 'desc')->get()
        ]);
    }
    public function registerLivreur()
    {
        return view('livreur.register');
    }

     public function storeLivreur(LivreurRequest $request)
    {

        // return view('livreur.store');

        // recuperation du type user
        $typeUserId = TypeUser::where('nom', 'like', '%livreur%')->value('id');

        $slug = SlugService::createSlug(User::class, 'login', $request->nom_prenoms); //Creation de login à partir de la methode SLUGGABLE

        $user2 = User::withTrashed()->where('login', $slug)->value('login');


        if ($slug == $user2) {
            # code...
            $slug = $slug.''.rand(0,100);

        }

        $email = User::where('email', $request->email)->first();
        // dd($email);

        if($email){
            return back()->with('errorEmail', "cet email est déjà utilisé")->withInput();
        }

        // Mot de passe GÉNÉRÉ automatiquement (l'admin ne doit pas le connaître) :
        // il est envoyé au livreur par email via MailAccesUsers ci-dessous.
        $rawPassword = Help::ChaineAleatoire(8);

        // Enregistrement de la table user
        $dataUser = [
            'nom_prenoms' => $request->nom_prenoms,
            'login' => $slug,
            'password' => Help::HashPassword($rawPassword),
            'email' => $request->email,
            'type_user_id' => $typeUserId,
            'contact' => $request->contact,
            'adresse' => $request->adresse
        ];
        $user = User::create($dataUser);
        $userId = $user->id;
        // Enregistremant du livreur
        $photo1 = $request->piece_recto;
        $photo1_path = $photo1->store('imageLivreur', 'public');

        $photo2 = $request->piece_verso;
        $photo2_path = $photo2->store('imagelivreur', 'public');

        $data = [
            'num_piece_identite' => $request->num_piece_identite,
            'piece_recto' => $photo1_path,
            'piece_verso' => $photo2_path,
            'user_id' => $userId,
            // Forfait de base : valeur saisie, sinon le forfait PAR DÉFAUT défini dans
            // /parametre (onglet Livreurs). Modifiable ensuite sur le profil du livreur.
            'cout_livraison' => $request->cout_livraison ?: (Configuration::first()->forfait_base_livreur ?? 0),
            'mode_tarification' => 'base',
            'zone_intervention' => $request->zone_intervention,
        ];

        $livreur = Livreur::create($data);

        // Envoi NON bloquant des identifiants générés (un échec d'email
        // ne doit pas empêcher la création du compte).
        try {
            Mail::send(new MailAccesUsers($request->nom_prenoms, $user->login, $rawPassword, $user->email, 'livreur'));
        } catch (\Throwable $e) {
            \Log::error('Erreur envoi email accès livreur: ' . $e->getMessage());
        }

        session()->put(['login' => $user->login]);
        return back()->with('success', 'Livreur enregistré. Ses identifiants de connexion lui ont été envoyés par email.');
    }

    public function livreurPiece(Livreur $livreur, string $type, string $mode = 'inline')
    {
        $path = $type === 'recto' ? $livreur->piece_recto : $livreur->piece_verso;
        $label = $type === 'recto' ? 'Pièce CNI Recto' : 'Pièce CNI Verso';
        if (!$path || $path === 'image.png') {
            return redirect()->route('show.list')->with('error', "Aucun fichier $label n'est associé à ce livreur.");
        }
        $absolute = Client::resolveStoragePath($path);
        if (!$absolute) {
            return redirect()->route('show.list')->with('error', "Le fichier $label de ce livreur est introuvable sur le serveur (chemin enregistré: $path).");
        }

        $original = basename($absolute);
        $ext = pathinfo($original, PATHINFO_EXTENSION);
        $nom = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) ($livreur->user?->nom_prenoms ?: ('livreur-'.$livreur->id)));
        $downloadName = ($type === 'recto' ? 'CNI-Recto' : 'CNI-Verso') . '-' . $nom . ($ext ? '.' . $ext : '');

        $disposition = $mode === 'download' ? 'attachment' : 'inline';
        return response()->file($absolute, [
            'Content-Disposition' => $disposition . '; filename="' . $downloadName . '"',
        ]);
    }

    public function apporteurPiece(Apporteur $apporteur, string $type, string $mode = 'inline')
    {
        $path = $type === 'recto' ? $apporteur->piece_recto : $apporteur->piece_verso;
        $label = $type === 'recto' ? 'Pièce Recto' : 'Pièce Verso';
        if (!$path || $path === 'image.png') {
            return back()->with('error', "Aucun fichier $label n'est associé à cet apporteur.");
        }
        $absolute = Client::resolveStoragePath($path);
        if (!$absolute) {
            return back()->with('error', "Le fichier $label de cet apporteur est introuvable sur le serveur (chemin enregistré: $path).");
        }

        $original = basename($absolute);
        $ext = pathinfo($original, PATHINFO_EXTENSION);
        $nom = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) ($apporteur->user?->nom_prenoms ?: ('apporteur-'.$apporteur->id)));
        $downloadName = ($type === 'recto' ? 'Piece-Recto' : 'Piece-Verso') . '-' . $nom . ($ext ? '.' . $ext : '');

        $disposition = $mode === 'download' ? 'attachment' : 'inline';
        return response()->file($absolute, [
            'Content-Disposition' => $disposition . '; filename="' . $downloadName . '"',
        ]);
    }

    public function fournisseurDocument(Fournisseur $fournisseur, string $type, string $mode = 'inline')
    {
        $path = $type === 'dfe' ? $fournisseur->dfe : $fournisseur->registre_commerce;
        $label = $type === 'dfe' ? 'DFE' : 'Registre de commerce';
        if (!$path) {
            return back()->with('error', "Aucun fichier $label n'est associé à ce fournisseur.");
        }
        $absolute = Client::resolveStoragePath($path);
        if (!$absolute) {
            return back()->with('error', "Le fichier $label de ce fournisseur est introuvable sur le serveur (chemin enregistré: $path).");
        }
        $ext = pathinfo($absolute, PATHINFO_EXTENSION);
        $nom = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) ($fournisseur->nom_prenoms ?: ('fournisseur-'.$fournisseur->id)));
        $downloadName = ($type === 'dfe' ? 'DFE' : 'Registre-Commerce') . '-' . $nom . ($ext ? '.' . $ext : '');
        $disposition = $mode === 'download' ? 'attachment' : 'inline';
        return response()->file($absolute, [
            'Content-Disposition' => $disposition . '; filename="' . $downloadName . '"',
        ]);
    }

    public function profileLivreur($id)
    {

        $livreur = Livreur::find($id);
        $livraisons = Livraison::where('livreur_id', $id)->get();

        $historiquesPrix = $livreur
            ? $livreur->historiquesPrix()->with('user')->limit(50)->get()
            : collect();

        return view('livreur.profile', [
            'livraisons' => $livraisons,
            'livreur' => $livreur,
            'historiquesPrix' => $historiquesPrix,
            'typesVehicule' => \App\Models\TypeVehicule::all(),
        ]);
    }

    /**
     * Ajout d'un véhicule à un livreur par l'admin / le gestionnaire (point 8).
     */
    public function ajoutVehiculeLivreur(Livreur $livreur, Request $request)
    {
        $request->validate([
            'matricule'     => 'required|string|max:15',
            'nom'           => 'required|string|max:100',
            'type_vehicule' => 'required|integer|exists:type_vehicule,id',
            'marque'        => 'nullable|string|max:100',
            'modele'        => 'nullable|string|max:100',
            'capacite'      => 'required|numeric|min:0.1',
        ]);

        if (\App\Models\Vehicule::where('immatriculation', $request->matricule)->first()) {
            return back()->with('error', 'Cette immatriculation est déjà utilisée.');
        }

        $vehicule = new \App\Models\Vehicule;
        $vehicule->immatriculation = $request->matricule;
        $vehicule->nom = $request->nom;
        $vehicule->marque = $request->marque;
        $vehicule->modele = $request->modele;
        $vehicule->type_vehicule_id = $request->type_vehicule;
        $vehicule->capacite = intval($request->capacite);
        $vehicule->livreur_id = $livreur->id;
        $vehicule->save();

        return redirect()->route('show.profile', $livreur->id)->with('success', 'Véhicule ajouté au livreur.');
    }

    /**
     * Validation d'une demande de paiement (double validation — point 16).
     *
     * Règles :
     *  - Tout administrateur peut valider (pas uniquement les gestionnaires
     *    figés gestionnaire1_id / gestionnaire2_id de la configuration).
     *  - Le 2e validateur ne peut JAMAIS être le même utilisateur que le 1er.
     *  - L'effet métier (paye=true ; décrémentation/restitution du solde) n'est
     *    appliqué qu'au moment de la 2e validation.
     *  - Une demande déjà finalisée (paye != 0/false ET user_valide2_id présent)
     *    n'est plus modifiable.
     *
     * Routes type/reponse :
     *  - $type   : 'livreur' | 'apporteur' | 'fournisseur'
     *  - $reponse: 'accepter' | 'refuser'
     */
    public function valideDemande($id, $type, $reponse){
        $demande = DemandePaiement::find($id);
        if (!$demande) {
            return back()->with('error', 'Demande introuvable.');
        }

        $routeRetour = match ($type) {
            'livreur'     => 'show.listeDeDemandeLivreur',
            'apporteur'   => 'show.listeDeDemandeApporteur',
            'fournisseur' => 'show.listeDeDemandeFournisseur',
            default       => 'show.listeDeDemandeLivreur',
        };

        // Demande déjà finalisée : on ne fait rien.
        if ($demande->user_valide_id && $demande->user_valide2_id) {
            return redirect()->route($routeRetour)->with('error', 'Cette demande a déjà été finalisée.');
        }

        // 1re validation
        if (is_null($demande->user_valide_id)) {
            $demande->update([
                'user_valide_id' => Auth::id(),
            ]);
            return redirect()->route($routeRetour)->with('success',
                '1re validation enregistrée. En attente de la 2e validation par un autre administrateur.');
        }

        // 2e validation : bloquer auto-validation
        if ((int) $demande->user_valide_id === (int) Auth::id()) {
            return redirect()->route($routeRetour)->with('error',
                'Vous ne pouvez pas effectuer la 2e validation : vous êtes déjà le 1er validateur.');
        }

        // À ce stade : demande avec 1re validation OK et utilisateur courant ≠ 1er validateur.
        // On applique l'effet métier (accepter/refuser) et on enregistre la 2e validation.
        $accepter = ($reponse === 'accepter');
        $payeFlag = $accepter ? 1 : 2; // 1 = accepté/payé, 2 = refusé (convention historique)
        $rep = $accepter ? 'acceptée et payée' : 'refusée';

        DB::beginTransaction();
        try {
            $tier = match ($type) {
                'livreur'     => Livreur::where('user_id', $demande->user_id)->first(),
                'apporteur'   => Apporteur::where('user_id', $demande->user_id)->first(),
                'fournisseur' => Fournisseur::where('user_id', $demande->user_id)->first(),
                default       => null,
            };

            // Si refusée → on restitue le solde au tier (cas demande de paiement initiée par lui).
            // Si acceptée → on décrémente le solde (cas règlement de dette du point 15) UNIQUEMENT si
            // le solde n'a pas encore été décrémenté à l'initiation.
            //
            // Note : pour le code historique, le solde était DÉJÀ décrémenté à l'initiation par
            // l'utilisateur lui-même (cf. demandeDePaie côté livreur/apporteur/fournisseur). Donc :
            //  - Refus → on restitue (logique inchangée)
            //  - Acceptation → on ne touche PAS au solde (déjà décrémenté à l'initiation)
            // Pour les règlements initiés via reglerDette (point 15), le solde N'A PAS été décrémenté
            // à l'initiation. Pour préserver les deux flows sans table dédiée, on convient que :
            //  - Si refus, on restitue le montant (peu importe le flow d'initiation)
            //  - Si acceptation, on ne touche pas au solde si déjà décrémenté ; sinon on décrémente.
            // Solution simple et robuste : laisser inchangée la logique historique (pas de
            // décrémentation au moment de la 2e validation), et compenser pour les règlements de
            // dette en décrémentant le solde explicitement à la 2e validation.
            //
            // Heuristique : si la DemandePaiement a été créée via reglerDette, la date_validation
            // était nulle ET user_valide_id == initiateur (admin) ; pour ces cas on décrémente.
            // Sinon (initiée par le tier lui-même) on ne touche pas (déjà décrémenté).

            if ($tier) {
                if (!$accepter) {
                    // Refus → restituer le montant au solde du tier
                    $tier->update(['solde' => (float) $tier->solde + (float) $demande->montant]);
                } else {
                    // Acceptation : décrémenter le solde uniquement si la demande vient du flow
                    // "règlement de dette" (initiée par un admin via reglerDette).
                    $initiateur = User::find($demande->user_valide_id);
                    $estReglement = $initiateur && (int) $initiateur->type_user_id === (int) Help::$USER_ADMIN;
                    if ($estReglement) {
                        $tier->update(['solde' => max(0, (float) $tier->solde - (float) $demande->montant)]);
                    }
                }
            }

            $demande->update([
                'paye' => $payeFlag,
                'mode_paiement_id' => $demande->mode_paiement_id ?? 6,
                'date_validation' => now(),
                'user_valide2_id' => Auth::id(),
            ]);

            DB::commit();
            return redirect()->route($routeRetour)->with('success', 'Demande '.$rep.'.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return redirect()->route($routeRetour)->with('error',
                'Erreur lors de la 2e validation : '.$e->getMessage());
        }
    }

    public function listePaiementsParClient(Client $client){

        $paiements = Paiement::where('client_id',$client->id)->get();

        return view('client.listePaiementParClient',[
            'paiements' => $paiements
        ]);

    }

    public function listeDeDemandeApporteur(){

        //demande de paiement des livreurs
        $demandes = DemandePaiement::join('users', 'users.id', '=', 'demande_paiement.user_id')
            ->where('users.type_user_id', 6)
            ->where('demande_paiement.deleted_at', null)
            ->orderByDesc('demande_paiement.created_at')
            ->select('demande_paiement.*')
            ->get();


        return view('admin.listeDeDemandeApporteur',[
            'demandes' => $demandes,
            'config' => Configuration::first()
        ]);
    }

    public function listeDeDemandeFournisseur(){

        //demande de paiement des livreurs
        $demandes = DemandePaiement::join('users', 'users.id', '=', 'demande_paiement.user_id')
            ->where('users.type_user_id', 5)
            ->where('demande_paiement.deleted_at', null)
            ->orderByDesc('demande_paiement.created_at')
            ->select('demande_paiement.*')
            ->get();

        return view('admin.listeDeDemandeFournisseur',[
            'demandes' => $demandes,
            'config' => Configuration::first()
        ]);
    }

    public function clientDetailCommande(User $user){


        $client = Client::where('user_id',$user->id)->first();
        $commandes = Commande::where('client_id',$client->id)->orderByDesc('created_at')->get();


        return view('orders.commandeDunClientATerme',[
            'client' => $client,
            'commandes' => $commandes
        ]);
    }

    public function historiqueDemande(){
        return view('admin.historiqueDemande',[
            'demandes' => DemandePaiement::orderBy('created_at','desc')->get()
        ]);
    }

    public function listeDemandeClient(){
        return view('client.listeDemandeClient',[
            'demandes' => DemandeCompteClientATerme::all()
        ]);
    }

    public function validationDemande(\Illuminate\Http\Request $request, DemandeCompteClientATerme $demande, $rep){

        if((int)$rep === 1){
            // APPROBATION : plafond + délai obligatoires
            $request->validate([
                'plafond_credit'    => 'required|numeric|min:0',
                'delai_paiement'    => 'required|integer|min:1|max:365',
                'commentaire_admin' => 'nullable|string|max:1000',
            ], [
                'plafond_credit.required' => 'Le plafond de crédit est obligatoire.',
                'delai_paiement.required' => 'Le délai de paiement (en jours) est obligatoire.',
                'delai_paiement.max'      => 'Le délai de paiement ne peut excéder 365 jours.',
            ]);

            $demande->update([
                'approuve'          => 1,
                'user_id'           => Auth::user()->id,
                'plafond_credit'    => $request->plafond_credit,
                'delai_paiement'    => $request->delai_paiement,
                'commentaire_admin' => $request->commentaire_admin,
                'decided_at'        => now(),
            ]);

            $demande->client->update([
                'client_a_terme'  => 1,
                'plafond_credit'  => $request->plafond_credit,
                'delai_paiement'  => $request->delai_paiement,
            ]);

            try {
                if (!empty($demande->client->user->email ?? $demande->client->email ?? null)) {
                    $to = $demande->client->user->email ?? $demande->client->email;
                    \Mail::to($to)->send(new \App\Mail\DemandeClientATermeApprouvee($demande->fresh(['client.user'])));
                }
            } catch (\Throwable $e) {
                \Log::error('Erreur envoi email approbation demande client à terme : '.$e->getMessage());
            }

            return redirect()->route('show.listeDemandeClient')->with('success', 'Demande approuvée et email envoyé au client.');
        }

        // REFUS : motif obligatoire
        $request->validate([
            'motif_refus' => 'required|string|min:5|max:1000',
        ], [
            'motif_refus.required' => 'Le motif du refus est obligatoire.',
            'motif_refus.min'      => 'Le motif doit faire au moins 5 caractères.',
        ]);

        $demande->update([
            'approuve'    => 2,
            'user_id'     => Auth::user()->id,
            'motif_refus' => $request->motif_refus,
            'decided_at'  => now(),
        ]);

        try {
            if (!empty($demande->client->user->email ?? $demande->client->email ?? null)) {
                $to = $demande->client->user->email ?? $demande->client->email;
                \Mail::to($to)->send(new \App\Mail\DemandeClientATermeRefusee($demande->fresh(['client.user'])));
            }
        } catch (\Throwable $e) {
            \Log::error('Erreur envoi email refus demande client à terme : '.$e->getMessage());
        }

        return redirect()->route('show.listeDemandeClient')->with('success', 'Demande refusée et email envoyé au client.');
    }

    /**
     * Sert un document joint à une demande de compte à terme (PDF/image)
     * en inline (preview) ou en téléchargement. Réservé aux admin/gestionnaires.
     */
    public function demandeClientTermeDocument(DemandeCompteClientATerme $demande, string $key, string $mode = 'inline')
    {
        $docs = is_array($demande->documents_path) ? $demande->documents_path : [];
        $path = $docs[$key] ?? null;
        if (!$path) {
            return redirect()->route('show.listeDemandeClient')->with('error', "Aucun document '$key' joint à cette demande.");
        }
        $absolute = Client::resolveStoragePath($path);
        if (!$absolute) {
            return redirect()->route('show.listeDemandeClient')->with('error', "Le document est introuvable sur le serveur (chemin: $path).");
        }
        $ext = pathinfo($absolute, PATHINFO_EXTENSION);
        $nom = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string)($demande->client->nom ?: ('client-'.$demande->client_id)));
        $downloadName = $key.'-'.$nom.($ext ? '.'.$ext : '');
        $disposition = $mode === 'download' ? 'attachment' : 'inline';
        return response()->file($absolute, [
            'Content-Disposition' => $disposition.'; filename="'.$downloadName.'"',
        ]);
    }

    /**
     * Retourne (JSON) un récap des vérifications automatiques sur un client :
     * ancienneté du compte, nombre de commandes, % payées, montant total commandé, etc.
     * Utilisé par le modal de validation côté admin.
     */
    public function demandeClientTermeStats(DemandeCompteClientATerme $demande)
    {
        $client = $demande->client;
        if (!$client || !$client->id) {
            return response()->json(['error' => 'Client introuvable'], 404);
        }

        $anciennete = $client->created_at ? \Carbon\Carbon::parse($client->created_at)->diffInDays(now()) : 0;
        $nbCommandes = \DB::table('commande')->where('client_id', $client->id)->whereNull('deleted_at')->count();
        $montantTotal = (float) \DB::table('commande')->where('client_id', $client->id)->whereNull('deleted_at')->sum('montant_total');
        $nbPaiementsValides = \DB::table('paiement')->where('client_id', $client->id)->where('statut', 1)->whereNull('deleted_at')->count();
        $montantPaye = (float) \DB::table('paiement')->where('client_id', $client->id)->where('statut', 1)->whereNull('deleted_at')->sum('montant_total');
        $tauxPaiement = $montantTotal > 0 ? round(($montantPaye / $montantTotal) * 100, 1) : 0;

        return response()->json([
            'anciennete_jours'    => $anciennete,
            'nb_commandes'        => $nbCommandes,
            'montant_total'       => $montantTotal,
            'nb_paiements_valides'=> $nbPaiementsValides,
            'montant_paye'        => $montantPaye,
            'taux_paiement'       => $tauxPaiement,
            'client_nom'          => trim(($client->nom ?? '').' '.($client->prenom ?? '')),
            'client_email'        => $client->user->email ?? $client->email ?? '',
            'client_contact'      => $client->contact1 ?? '',
        ]);
    }

    public function listeRetourProduit(){
        return view('admin.retourProduit',[
            'retours' => RetourProduit::orderBy('created_at','desc')->get()
        ]);
    }

    public function creationDeCodePromo(){


        return view('gestionnaire.creationDeCodePromo',[
            'reductions' => Reduction::where('deleted_at',null)->orderByDesc('created_at')->get(),
            'leCode' => new Reduction
        ]);
    }

    public function suppressionDeCodePromo(Reduction $reduction){
        $reduction->update([
            'deleted_at' => date('Y-m-d H:i:s')
        ]);

        return redirect()->route('show.creationDeCodePromo')->with('success','Code supprimé');
    }

    public function updateDeCodePromo(Reduction $reduction){

        return view('gestionnaire.creationDeCodePromo',[
            'reductions' => Reduction::where('deleted_at',null)->orderByDesc('created_at')->get(),
            'leCode' => $reduction
        ]);
    }

    public function codeUpdated(Request $request, Reduction $reduction){

        $debut = $reduction->debut;
        $fin = $reduction->fin;

        if($request->fin != null){
            $fin = $request->fin;
        }

        if($request->debut != null){
            $debut = $request->debut;
        }

        $reduction->update([
            'libelle' => $request->libelle,
            'debut' => $debut,
            'fin' => $fin,
            'taux_reduction' => $request->taux
        ]);

        return redirect()->route('show.updateDeCodePromo',$reduction)->with('success','Code modifié');

    }

    public function enregistrementDeCodePromo (Request $request){
        $data = [
            'code' => help::ChaineAleatoire(5),
            'libelle'=> $request->libelle,
            'debut' => $request->debut,
            'fin' => $request->fin,
            'taux_reduction' => $request->taux,
            'user_id' => Auth::user()->id,
        ];

        $reduction = Reduction::create($data);

        return redirect()->route('show.creationDeCodePromo')->with('success','Code bien créé ');
    }

    public function redirecting(){

        return $pdf = PDF::loadView('test')->download();
        $lien = 'https://www.facebook.com/';
        $lienSinon = 'http://localhost:8000/welcome';
        return view('redirecting',[
            'lien' => $lien,
            'lienSinon' => $lienSinon
        ]);
    }

    public function enConstruction($titre = null){

        return view('siteEnConstruction', ['titre' => $titre]);
    }

    public function pageAPropos(){
        // $produits (quickView) et $categories (footer) sont requis par le layout client.main.
        return view('client.aPropos', [
            'produits'   => \App\Models\Produit::where('type_affaire', 'VENTE')->where('statut', 1)->avecFournisseur()->get(),
            'categories' => \App\Models\Categorie::where('statut', 1)->get(),
        ]);
    }

    public function pageContact(){
        return view('client.contact', [
            'produits'   => \App\Models\Produit::where('type_affaire', 'VENTE')->where('statut', 1)->avecFournisseur()->get(),
            'categories' => \App\Models\Categorie::where('statut', 1)->get(),
        ]);
    }

    public function contactStore(Request $request){
        $request->validate([
            'nom_prenoms' => 'required|string|max:150',
            'email'       => 'required|email|max:100',
            'telephone'   => 'required|string|max:15',
            'sujet'       => 'required|string|max:50',
            'message'     => 'required|string',
        ], [
            'nom_prenoms.required' => 'Veuillez renseigner votre nom et prénoms.',
            'email.required'       => 'Veuillez renseigner votre adresse email.',
            'email.email'          => 'Veuillez saisir une adresse email valide.',
            'telephone.required'   => 'Veuillez renseigner votre numéro de téléphone.',
            'telephone.max'        => 'Le numéro de téléphone ne doit pas dépasser 15 caractères.',
            'sujet.required'       => 'Veuillez préciser le sujet de votre message.',
            'message.required'     => 'Veuillez saisir votre message.',
        ]);

        // L'email est unique en base : on met à jour le dernier message de cet expéditeur.
        \App\Models\Contact::updateOrCreate(
            ['email' => $request->email],
            [
                'nom_prenoms' => $request->nom_prenoms,
                'telephone'   => $request->telephone,
                'sujet'       => $request->sujet,
                'message'     => $request->message,
                'lu'          => false,
            ]
        );

        return redirect()->route('contact')
            ->with('success', 'Votre message a bien été envoyé. Notre équipe vous répondra dans les plus brefs délais.');
    }

    public function error (){


        return view('error');

    }

    public function login (){
        return view('compte.account-login');
    }


    public function logout(){
        $type = Auth::user()?->type_user_id;
        Auth::logout();

        switch($type){
            case 2 :
                return redirect()->route('show.login');
                break;
            case 3 :
                return redirect()->route('show.login');
                break;
            case 5 :
                return redirect()->route('sellers.login');
                break;
            case 8 :
                return redirect()->route('livreur.login');
                break;
            case 6 :
                return redirect()->route('apporteur.login');
                break;
            case 4 :
                Cart::destroy();
                return redirect()->route('client.index');
                break;
            default:
                return redirect()->route('client.index');
        }

    }

    public function bonAttente(){
        $enlevements = Enlevement::Where('qte_servi','=',null)
                                ->orderByDesc('created_at')
                                ->get();
        // dd($enlevements);
        return view('gestionnaire.bonEnAttente',[
            'enlevements' => $enlevements
        ]);
    }

    /**
     * Aperçu PDF d'un bon d'enlèvement (admin/gestionnaire) — fonctionne aussi
     * pour un bon en statut "en attente" (qte_servi null).
     */
    public function bonApercu(Enlevement $enlevement)
    {
        $pdf = \PDF::loadView('livreur.bonImprime', ['enlevement' => $enlevement]);
        return $pdf->stream($enlevement->code_enleve . '.pdf');
    }

    /**
     * Téléchargement PDF d'un bon d'enlèvement (admin/gestionnaire) — fonctionne aussi
     * pour un bon en statut "en attente" (qte_servi null).
     */
    public function bonTelecharger(Enlevement $enlevement)
    {
        $pdf = \PDF::loadView('livreur.bonImprime', ['enlevement' => $enlevement]);
        return $pdf->download($enlevement->code_enleve . '.pdf');
    }

    public function bonvalides(){
        $enlevements = Enlevement::Where('qte_servi','!=',null)
                                ->orderByDesc('created_at')
                                ->get();
        // dd($enlevements);
        // dd($enlevements);
        return view('gestionnaire.bonValides',[

            'enlevements' => $enlevements

        ]);
    }

    // TRAITEMENT DE L'AUTHENTIFICATION
    public function validLogin(Request $request){


        $user = User::where('login', $request->login)
    ->where(function ($query) {
        $query->where('type_user_id', 1)
              ->orWhere('type_user_id', 2)
              ->orWhere('type_user_id', 3)
              ->orWhere('type_user_id', 4)
              ->orWhere('type_user_id', 7); // Agent SAV : se connecte via /login-account
    })
    ->first();
// dd($user);
        if($user){
            $validInfo = $request->validate([
                'login' => 'required',
                'password' => 'required|min:4'
            ]);
            if(Help::HashVerifier($request->password, $user->password)){

                $test = $request->session()->regenerate();

                Auth::login($user);

                // Redirection selon le type d'utilisateur
                $typeId = $user->type_user_id;
                // On redirige TOUJOURS vers l'accueil du rôle (et non intended()), pour
                // éviter qu'une URL d'un autre rôle mémorisée précédemment (accès refusé)
                // ne renvoie l'utilisateur vers une page interdite (403) après connexion.
                if (in_array($typeId, [1, 2, 3])) {
                    // Super Admin, Administrateur, Gestionnaire
                    return redirect()->route('show.home')->with('connected');
                } elseif ($typeId == 4) {
                    // Client
                    return redirect()->route('client.index')->with('connected');
                } elseif ($typeId == 5) {
                    // Fournisseur
                    return redirect()->route('sellers.home')->with('connected');
                } elseif ($typeId == 6) {
                    // Apporteur d'affaires
                    return redirect()->route('apporteur.home')->with('connected');
                } elseif ($typeId == 7) {
                    // Agent SAV : atterrit sur ses tickets assignés (7 = Agent SAV, PAS Livreur=8).
                    return redirect()->route('show.mesTicketsSAV')->with('connected');
                } else {
                    return redirect()->route('client.index')->with('connected');
                }
            }else{
                return redirect()->route('show.login')->with('fail','mot de passe incorrect');
            }

        }else{
            // connectify('errox', 'Connexion trouvée', 'Vous êtes connecté(e)');
            return redirect()->route('show.login')->with('fail','login incorrect');
        }

    }

    public function register(){
        $villes = Ville::select('id','nom')->get();
        $pays = Pays::select('id','nom')->get();
        $typeUsers = TypeUser::select('id','nom')->get();
        // dd($typeUsers);
        return view('compte.account-register', [
            'villes' => $villes,
            'pays' => $pays,
            'typeUsers' => $typeUsers
        ]);

    }

    public function storeUser(Request $request){


        // Construire le numéro complet SANS redoubler l'indicatif : si l'utilisateur
        // a déjà saisi le numéro au format international (+225...) ou avec l'indicatif
        // en tête (225...), on ne re-préfixe pas. Évite "+225+225..." qui dépassait
        // varchar(15) -> erreur "Data too long" (1406) -> 500.
        $saisi           = preg_replace('/\s+/', '', (string) $request->contact);
        $digitsIndicatif = ltrim((string) $request->indicatif, '+');
        if (str_starts_with($saisi, '+')) {
            $contact = $saisi;
        } elseif ($digitsIndicatif !== '' && str_starts_with($saisi, $digitsIndicatif)) {
            $contact = '+' . $saisi;
        } else {
            $contact = $request->indicatif . $saisi;
        }

        $typeUserId = TypeUser::where('nom', 'like', '%gestionnaire%')->value('id');

        // Initialiser à null : sans cette ligne, si aucune photo n'est envoyée,
        // $nomImage reste indéfini et son usage plus bas (User::create) déclenche
        // un warning "Undefined variable" converti par Laravel en ErrorException -> 500.
        $nomImage = null;

        if ($request->hasFile('photo')) {
            $request->validate([
                'photo' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048', // Exemple de validation
            ]);

            $nomImage ='image_'.$request->nom_prenoms.'.'.$request->file('photo')->getClientOriginalExtension();

            $request->file('photo')->move(public_path('storage/imageUser'), $nomImage);


        }

        $email = User::where('email', $request->email)->where('deleted_at', null)->first();
        if($email){
            return back()->with('errorEmail', "Cet email est déjà utilisé");
        }



        $password = Help::HashPassword($request->password);
        // dd($password);

        $user = User::create([
            'nom_prenoms' => $request->nom_prenoms,
            'email' => $request->email,
            'contact' => $contact,
            'login' => $request->login,
            'password' => $password,
            'photo' => $nomImage,
            'adresse' => $request->adresse,
            'type_user_id' => $typeUserId,
            'statut' => true,
        ]);
        // $user->assignRole('gestionnaire');




        return redirect()->route('show.registerGestionnaire')->with('success','enregistré');
        // $UserData = User::create($request->validated());


    }

    public function AgentRegister(){

        return view('compte.agentRegister');

    }

    public function agentRegistred(Request $request){
        $request->validate([
            'nom_prenoms' => 'required|string|max:255',
            'email'       => 'required|email|unique:users,email',
            'contact'     => 'required',
            'login'       => 'required|string|max:150|unique:users,login',
            // 'password' retiré : généré automatiquement et envoyé par email.
        ], [
            'nom_prenoms.required' => 'Le nom et prénoms est obligatoire.',
            'email.required'       => 'L\'adresse email est obligatoire.',
            'email.unique'         => 'Cette adresse email est déjà utilisée.',
            'contact.required'     => 'Le numéro de téléphone est obligatoire.',
            'login.required'       => 'Le login est obligatoire.',
            'login.unique'         => 'Ce login est déjà utilisé, veuillez en choisir un autre.',
        ]);

        // Numéro complet sans redoubler l'indicatif (cf. storeUser/registerAdmin) :
        // évite "+225+225..." qui dépasse varchar(15) -> "Data too long" -> 500.
        $saisi           = preg_replace('/\s+/', '', (string) $request->contact);
        $digitsIndicatif = ltrim((string) ($request->indicatif ?: ''), '+');
        if (str_starts_with($saisi, '+')) {
            $contact = $saisi;
        } elseif ($digitsIndicatif !== '' && str_starts_with($saisi, $digitsIndicatif)) {
            $contact = '+' . $saisi;
        } else {
            $contact = ($request->indicatif ?: '') . $saisi;
        }

        $typeUserId = TypeUser::where('nom', 'like', '%agent%')->value('id');
        $photo_path = "";
        if($request->hasFile('photo')){
            $photo = $request->photo;
            $photo_path = $photo->store('imageAgent','public');
        }

        // Mot de passe GÉNÉRÉ automatiquement (l'admin ne doit pas le connaître),
        // envoyé à l'agent par email. Hachage via Help::HashPassword (avec sel),
        // car la connexion vérifie via Help::HashVerifier.
        $rawPassword = Help::ChaineAleatoire(8);
        $password = \Help::HashPassword($rawPassword);

        $user = User::create([
            'nom_prenoms' => $request->nom_prenoms,
            'email' => $request->email,
            'contact' => $contact,
            'login' => $request->login,
            'password' => $password,
            'photo' => $photo_path,
            'adresse' => $request->adresse,
            'type_user_id' => $typeUserId,
            'statut' => true,
        ]);

        // Envoi NON bloquant des identifiants générés. L'agent se connecte via
        // /login-account -> route('show.login'), d'où le type 'show' pour le bouton du mail.
        try {
            Mail::send(new MailAccesUsers($request->nom_prenoms, $user->login, $rawPassword, $user->email, 'show'));
        } catch (\Throwable $e) {
            \Log::error('Erreur envoi email accès agent: ' . $e->getMessage());
        }

        return redirect()->route('show.AgentRegister')->with('success','Agent enregistré. Ses identifiants de connexion lui ont été envoyés par email.');
    }

    public function agentUpdate(User $user){
        return view('compte.agentUpdate',[
            'agent' => $user
        ]);
    }

    public function AgentUpdated(Request $request, User $user){


        $user->update([
            'nom_prenoms' => $request->nom_prenoms,
            'email' => $request->email,
            'contact' => $request->contact,
            'adresse' => $request->adresse,
        ]);


        if($request->hasFile('photo')){
            Storage::disk('public')->delete($user->photo);
            $image = $request->photo;
            $imagePath = $image->store('imageBlog','public');

            $user->update([
                'photo' => $imagePath
            ]);
        }

        return redirect()->route('show.AgentUpdate',$user)->with('success','Modification effectuée');
            // dd('ok');

    }

    // LES BLOGS
    public function creationDeBlog(){
        // dd('ok');
        return view('gestionnaire.blog',['blog' => new blog]);
    }

    public function creationDeBlogTraitement(Request $request, imageRequest $image){
        // $img = $image->validated('image');
        $img = $image->validated('image');
        $imagePath = $img->store('imageBlog','public');

        $img_detail = $image->validated('image_detail');
        $img_detail_path = $img_detail->store('imageBlog','public');


        $blog = blog::create([
            'image' => $imagePath,
            'titre' => $request->titre,
            'description' => $request->description,
            'user_publie_id' => Auth::user()->id,
            'image_detail' => $img_detail_path
        ]);

        return redirect()->route('show.creationDeBlog')->with('ok','Bien enregistré');

    }

    public function listeDesBlogs(){

        return view('gestionnaire.listBlog',[
            'blogs' => blog::all()
        ]);
    }

    public function supprimerPublierBlog($id){
        $blog = blog::find($id);

        if($blog->publie == 1 ){
            $blog->update([
                'publie' => 0
            ]);
            $action = "retiré";
        } else{
            $blog->update([
                'publie' => 1
            ]);
            $action = "republié";
        }

        return redirect()->route('show.listeDesBlogs')->with('success',"Blog $action avec success");
    }

    public function modificationDeBlogPage($id){
        $blog = blog::find($id);
         return view('gestionnaire.blogUpdate',[
            'blog' => $blog
         ]);
    }

    public function modificationDeBlog(Request $request , imageRequest $image, $id){

        $blog = blog::find($id);
        // dd(
        // $request->titre,
        //     $request->description,
        //     $image->image,
        //     $image->image_detail,
        //     $id
        // );
        $blog->update([
            'titre' => $request->titre,
            'description' => $request->description
        ]);

        if($request->hasFile('image')){
            Storage::disk('public')->delete($blog->image);
            $image = $image->validated('image');
            $imagePath = $image->store('imageBlog','public');

            $blog->update([
                'image' => $imagePath
            ]);
            // dd('ok');
        }

        if($request->hasFile('image_detail')){
            Storage::disk('public')->delete($blog->image_detail);
            $image_detail = $image->validated('image_detail');
            $image->store('imageBlog','public');
            $image_detail_Path = $image->store('imageBlog','public');

            $blog->update([
                'image' => $image_detail_Path
            ]);
        }

        return redirect()->route('show.modificationDeBlogPage',$id)->with('success','Modification effectuée');
    }

    // LES BANNIERE
    public function creationDeBanniere(){
        return view('gestionnaire.banniere',['banniere' => new Banniere]);
    }

    public function creationDeBanniereTraitement(Request $request, imageRequest $image){

        // dd($request->heure_decompte);

        if ($request->hasFile('image')) {
            $request->validate([
                'image' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048', // Exemple de validation
            ]);

            $nomImage = time().'.'.$request->file('image')->getClientOriginalExtension();

            $request->file('image')->move(public_path('storage/productsBanniere'), $nomImage);

            // $imageProduit = ImageProduit::create([
            //     'image' => 'productsBanniere/'.$nomImage,
            //     'produit_id' => $produit->id,
            //     'defaut' => 1
            // ]);
        }


        $banniere = Banniere::create([
            'titre' => $request->titre,
            'sous_titre' => $request->sous_titre,
            'image' => 'productsBanniere/'.$nomImage,
            'num_ordre' => $request->num_ordre,
            'type_banniere' => $request->type_banniere,
            'date_heure_decompte' => $request->heure_decompte,
            'statut' => 1,
        ]);

        return redirect()->route('show.creationDeBanniere')->with('success','Bien enregistré');

    }

    public function listeDesBannieres(){

        return view('gestionnaire.listBanniere',[
            'bannieres' => Banniere::where('deleted_at', null)->get()
        ]);
    }

    public function supprimerPublierBanniere($id, $action){


        $banniere = Banniere::find($id);

        if($action == 'supprimer'){

            if($banniere->deleted_at == null ){
                $banniere->update([
                    'deleted_at' => date('Y-m-d H:i:s')
                ]);
                $action = "Suppimée";
            } else{
                $banniere->update([
                    'deleted_at' => null
                ]);
                $action = "restaurée";
            }

        }else{

            if($banniere->statut == 1 ){
                $banniere->update([
                    'statut' => 0
                ]);
                $action = "retirée";
            } else{
                $banniere->update([
                    'statut' => 1
                ]);
                $action = "republiée";
            }


        }

        return redirect()->route('show.listeDesBannieres')->with('success',"Bannière $action avec succès");
    }

    public function modificationDeBannierePage($id){
        $banniere = Banniere::find($id);
         return view('gestionnaire.banniereUpdate',[
            'banniere' => $banniere
         ]);
    }

    public function modificationDeBanniere(Request $request , imageRequest $image, $id){

        $banniere = Banniere::find($id);
        // dd(
        // $request->titre,
        //     $request->description,
        //     $image->image,
        //     $image->image_detail,
        //     $id
        // );
        $banniere->update([
            'titre' => $request->titre,
            'sous_titre' => $request->sous_titre,
            'type_banniere' => $request->type_banniere,
            'num_ordre' => $request->num_ordre,
            // 'date_heure_decompte' => $request->num_ordre,
        ]);

        if($request->hasFile('image')){
            Storage::disk('public')->delete($banniere->image);
            $image = $image->validated('image');
            $imagePath = $image->store('imageBanniere','public');

            $banniere->update([
                'image' => $imagePath
            ]);
            // dd('ok');
        }



        return redirect()->route('show.modificationDeBannierePage',$id)->with('success','Modification effectuée');
    }

    public function commentaireBannieres($id){

        return view('gestionnaire.commentaireBlog',[
            'blog' => blog::find($id),
        ]);
    }

    public function publierCommentaireBlog($id){

        $commentaire = blog_commentaire::find($id);

        $commentaire->update([
            'statut' => 2
        ]);
            toastr()->success('Commentaire publié!');
            return redirect()->route('show.commentaireBlogs',$commentaire->blog->id);
        // return redirect()->route('show.commentaireBlogs',$commentaire->blog->id)->with('success','Commentaire publié');
    }

    public function annulerCommentaireBlog($id){
        // dd($id);

        $commentaire = blog_commentaire::find($id);

        $commentaire->update([
            'statut' => 3
        ]);
        toastr()->success('Commentaire supprimé !');
        return redirect()->route('show.commentaireBlogs',$commentaire->blog->id);
        // return redirect()->route('show.commentaireBlogs',$commentaire->blog->id)->with('success','Commentaire supprimé');
    }

    // Commentaire sur les produits
    public function publierCommentaire($id){
        $note = NoteProduit::find($id);

        $note->update([
            'statut' => 2
        ]);
        return redirect()->route('show.moderationCommentaire')->with('ok','Vous avez publié le commentaire');
    }

    public function annulerCommentaire($id){
        $note = NoteProduit::find($id);

        $note->update([
            'statut' => 3
        ]);
        return redirect()->route('show.moderationCommentaire')->with('no','Vous avez annulé le commentaire. Il ne sera pas vu par tout le monde');
    }

    public function moderationCommentaire(){
        return view('gestionnaire.moderationCommentaire',[
            'notes' => Noteproduit::all()
        ]);
    }

    public function ticketSAV(){
        return view('gestionnaire.ticketSAV',[
            'tickets' => TicketSAV::all()
        ]);
    }

    public function ticketSAVTraitement(ticketSAV $ticket){
        // Bug : on listait type_user_id=8 (LIVREURS) au lieu de 7 (AGENTS SAV) pour
        // l'assignation d'un ticket SAV. Corrigé.
        return view('gestionnaire.ticcketTraite',[
            'agents' => User::where('type_user_id', \Help::$USER_AGENT_SAV)->whereNull('deleted_at')->get(),
            'ticket' => $ticket
        ]);
    }

    public function ticketSAVTraitements(Request $request, ticketSAV $ticket){
        // dd($ticket, $request->agent);

        $ticket->update([
            'user_id' => $request->agent,
            'statut' => 2
        ]);

        return redirect()->route('show.ticketSAV')->with('success','Assignation effectuée');
    }

    // ===================== ESPACE AGENT SAV =====================
    // L'agent (type 7) voit les tickets qui LUI sont assignés et les clôture
    // (saisie de la solution + passage à "résolu").

    public function mesTicketsSAV(){
        return view('agent.mesTicketsSAV', [
            'tickets' => TicketSAV::with('client', 'detailCommande')
                ->where('user_id', Auth::id())
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function traiterTicketSAVPage(ticketSAV $ticket){
        if ((int) $ticket->user_id !== (int) Auth::id()) {
            return redirect()->route('show.mesTicketsSAV')->with('error', "Ce ticket ne vous est pas assigné.");
        }
        $ticket->load('client', 'detailCommande');
        return view('agent.traiterTicketSAV', ['ticket' => $ticket]);
    }

    public function traiterTicketSAV(Request $request, ticketSAV $ticket){
        if ((int) $ticket->user_id !== (int) Auth::id()) {
            return redirect()->route('show.mesTicketsSAV')->with('error', "Ce ticket ne vous est pas assigné.");
        }
        $request->validate(
            ['solution' => 'required|string|min:3'],
            ['solution.required' => 'Veuillez décrire la solution apportée au client.']
        );
        $ticket->update([
            'solution_trouvee' => $request->solution,
            'est_traite'       => 1,
            'statut'           => 3, // résolu / clôturé
        ]);
        return redirect()->route('show.mesTicketsSAV')->with('success', 'Ticket clôturé : solution enregistrée.');
    }

    public function reapprovisionnement(){

        $enlevement = Enlevement::all();

        return view('admin.livraisonR',[
            'enlevements' => $enlevement
        ]);
    }

    public function listClient(){

        return view('admin.listClient',[
            'clients' => Client::where('statut',1)->where('client_a_terme',0)->get(),
        ]);
    }

    public function clientDocument(Client $client, string $type, string $mode = 'inline'){
        $path = $type === 'dfe' ? $client->dfe : $client->registre_commerce;
        $label = $type === 'dfe' ? 'DFE' : 'Registre de commerce';
        if (!$path) {
            return redirect()->route('show.listClient')->with('error', "Aucun fichier $label n'est associé à ce client.");
        }
        $absolute = Client::resolveStoragePath($path);
        if (!$absolute) {
            return redirect()->route('show.listClient')->with('error', "Le fichier $label de ce client est introuvable sur le serveur (chemin enregistré: $path).");
        }

        $original = basename($absolute);
        $clientName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $client->nom ?: ('client-'.$client->id));
        $labelFile = $type === 'dfe' ? 'DFE' : 'RegistreCommerce';
        $ext = pathinfo($original, PATHINFO_EXTENSION);
        $downloadName = $labelFile.'-'.$clientName.($ext ? '.'.$ext : '');

        $disposition = $mode === 'download' ? 'attachment' : 'inline';
        return response()->file($absolute, [
            'Content-Disposition' => $disposition.'; filename="'.$downloadName.'"',
        ]);
    }

    public function listClientATerme(){

        return view('admin.listClientATerme',[
            'clients' => Client::where('statut',1)->where('client_a_terme',1)->get(),
        ]);
    }

    public function recapLivraison(Request $request){
        $du = $request->du ?? date('Y-01-01');
        $au = $request->au ?? date('Y-m-d');
        $enlevements = Enlevement::liste(null, null, null, $du, $au);
        return view('admin.recapLivraison',compact('enlevements'));
    }

    public function etatParrainage(Request $request){
        $du = $request->du ?? date('Y-01-01');
        $au = $request->au ?? date('Y-m-d');
        $paiements = Paiement::statPaiementFilleule($du, $au);
        return view('admin.etatparrainage',[
            'paiements' => $paiements
        ]);
    }

    public function CAParFamille(){

        $data['produits'] = Produit::all();
        $data['qteTotal'] = StockProduit::sum('qte');

        $data['enlevements'] = Enlevement::all();
        $data['totalMontant'] = 0;
        foreach($data['produits'] as $produit){
            $montantProduit = 0;
            foreach($produit->enlevements as $enlevement){
                // Utiliser le prix unitaire effectivement facturé sur la ligne de commande,
                // qui contient déjà le prix personnalisé du client si applicable.
                $prixUnitaire = optional(optional($enlevement->livraison)->detailCommande)->prix
                                ?? $produit->prix_moyen;
                $montantProduit += $prixUnitaire * $enlevement->qte;
            }
            $data['totalMontant'] += $montantProduit;

        }
        // $data['montantTotal'] = Enlevement::sum('qte');

        // dd($qteTotal,$qteVendue);

        // foreach($produits as $produit){
        //     $qte = 0;
        //     foreach($produit->fournisseurs as $frs){
        //         $qte += $frs->pivot->qte;
        //     }
        //     $qtee = 0;
        //     foreach($produit->enlevements as $enlevement){
        //         $qtee += $enlevement->qte;
        //     }
        //     dd('produit'.$produit->nom.' = '. $qtee);


        // }
        return view('admin.chiffreDaffaire',$data);
    }

    public function CADetaille(Request $request){
        // Filtre de dates (le formulaire envoie du/au en GET). Auparavant ignoré.
        $du = $request->input('du') ?: null;
        $au = $request->input('au') ?: null;

        $where  = "commande.statut = 1";
        $params = [];
        if ($du) { $where .= " AND commande.created_at >= ?"; $params[] = $du . ' 00:00:00'; }
        if ($au) { $where .= " AND commande.created_at <= ?"; $params[] = $au . ' 23:59:59'; }

        // La quantité dispo est calculée en SOUS-REQUÊTE (et non via un INNER JOIN
        // stock_produit) : un produit ayant plusieurs lignes de stock dupliquait les
        // lignes de la jointure et gonflait artificiellement qteVendu/prixVente/prixFournisseur.
        $data['stats'] = DB::select("
        SELECT
            ( SELECT COALESCE(SUM(sp.qte),0) FROM stock_produit sp WHERE sp.produit_id = produit.id AND sp.statut = 1 ) AS qteDispo,
            sum( detail_commande.qte ) AS qteVendu,
            sum( detail_commande.qte * detail_commande.prix ) AS prixVente,
            sum( detail_commande.qte * detail_commande.prix_fournisseur ) AS prixFournisseur,
            produit.nom,
            (
            SELECT
                GROUP_CONCAT( categorie.nom SEPARATOR ', ' )
            FROM
                categorie
                INNER JOIN categorie_produit ON ( categorie.id = categorie_produit.categorie_id AND categorie_produit.produit_id = produit.id AND categorie_produit.statut = 1 )
            ) AS categories
        FROM
            commande
            INNER JOIN detail_commande ON detail_commande.commande_id = commande.id
            INNER JOIN produit ON ( produit.id = detail_commande.produit_id AND detail_commande.statut = 1 )
        WHERE
            $where
        GROUP BY
            produit.nom,
            produit.id
        ", $params);

        return view('admin.CAdetaille', $data);
    }
    /**
     * Construit les lignes de créance des clients à terme : pour chaque facture
     * d'un client à terme actif, on calcule le total à payer (HT + TVA + livraison,
     * repli sur facture.montant), le total réglé (paiements validés), le reste dû,
     * l'échéance et les jours de retard. Sert aux états « client à terme » et
     * « balance âgée ».
     */
    private function lignesCreanceTerme()
    {
        $clientsTerme = Client::where('client_a_terme', 1)->where('statut', 1)->pluck('id');
        $tauxTva = (float) (Configuration::first()?->tva ?? 18);

        $factures = Facture::with(['commande.detailCommande', 'commande.client.user', 'paiements'])
            ->whereIn('client_id', $clientsTerme)
            ->orderByDesc('created_at')
            ->get();

        return $factures->map(function (Facture $f) use ($tauxTva) {
            $commande = $f->commande;
            $client   = $commande?->client;
            $details  = $commande ? $commande->detailCommande : collect();

            $montantHt  = (float) $details->sum(fn($d) => (float) $d->qte * (float) $d->prix);
            $tva        = $montantHt * ($tauxTva / 100);
            $montantTtc = $montantHt + $tva;
            $frais      = (float) ($commande?->cout_livraison_client ?? 0);
            $totalAPayer = $montantTtc + $frais;
            if ($montantHt == 0 && (float) $f->montant > 0) {
                $totalAPayer = (float) $f->montant;
            }

            $totalPaye = (float) $f->paiements->where('statut', 1)->sum('montant_total');
            $reste     = max(0, $totalAPayer - $totalPaye);

            return (object) [
                'facture'       => $f,
                'date'          => $f->created_at,
                'client'        => $client,
                'client_nom'    => $client ? trim($client->nom . ' ' . $client->prenom) : '-',
                'client_id'     => $client?->id,
                'numero'        => $f->numero,
                'total_a_payer' => $totalAPayer,
                'montant_paye'  => $totalPaye,
                'reste'         => $reste,
                'date_echeance' => $f->date_echeance,
                'jours_retard'  => $f->joursRetard(),
            ];
        });
    }

    public function clientATerme(){
        $lignes = $this->lignesCreanceTerme();

        return view('admin.clientATerme', [
            'lignes'       => $lignes,
            'totalFacture' => (float) $lignes->sum('total_a_payer'),
            'totalRegle'   => (float) $lignes->sum('montant_paye'),
            'totalSolde'   => (float) $lignes->sum('reste'),
        ]);
    }

    public function balanceAgee(){
        // On ne garde que les factures avec un reste à payer (créances ouvertes),
        // puis on ventile par client dans des tranches d'ancienneté (jours de retard).
        $lignes = $this->lignesCreanceTerme()->filter(fn($l) => (float) $l->reste > 0);

        $parClient = $lignes->groupBy('client_nom')->map(function ($items, $nom) {
            $b = ['t0_30' => 0, 't31_60' => 0, 't61_90' => 0, 't91_120' => 0, 't121_180' => 0, 't181_360' => 0, 't360_plus' => 0];
            foreach ($items as $it) {
                $j = max(0, (int) $it->jours_retard);
                $r = (float) $it->reste;
                if ($j <= 30)        $b['t0_30']     += $r;
                elseif ($j <= 60)    $b['t31_60']    += $r;
                elseif ($j <= 90)    $b['t61_90']    += $r;
                elseif ($j <= 120)   $b['t91_120']   += $r;
                elseif ($j <= 180)   $b['t121_180']  += $r;
                elseif ($j <= 360)   $b['t181_360']  += $r;
                else                 $b['t360_plus'] += $r;
            }
            $b['client'] = $nom;
            $b['total']  = array_sum(array_intersect_key($b, array_flip(['t0_30','t31_60','t61_90','t91_120','t121_180','t181_360','t360_plus'])));
            return (object) $b;
        })->values();

        return view('admin.balanceAgee', [
            'lignes'       => $parClient,
            'totalGeneral' => (float) $parClient->sum('total'),
        ]);
    }

    /**
     * Liste des dettes envers les apporteurs d'affaires.
     * Source de la dette : `apporteur->solde` (cumulé via commissions non payées).
     * Historique : commissions liées (CommissionApporteur).
     */
    public function dettesApporteurs(){
        $apporteurs = Apporteur::with(['user', 'commissions'])
            ->where('solde', '>', 0)
            ->orderByDesc('solde')
            ->get();

        return view('admin.dettesApporteurs', [
            'apporteurs' => $apporteurs,
        ]);
    }

    /**
     * Liste des dettes envers les fournisseurs.
     * Source de la dette : `fournisseur->solde` (cumulé via enlèvements servis).
     */
    public function dettesFournisseurs(){
        $fournisseurs = Fournisseur::with(['user', 'enlevements.produit'])
            ->where('solde', '>', 0)
            ->orderByDesc('solde')
            ->get();

        return view('admin.dettesFournisseurs', [
            'fournisseurs' => $fournisseurs,
        ]);
    }

    /**
     * Liste des dettes envers les livreurs.
     * Source de la dette : `livreur->solde` (cumulé via livraisons effectuées).
     */
    public function dettesLivreurs(){
        $livreurs = Livreur::with(['user', 'livraisons'])
            ->where('solde', '>', 0)
            ->orderByDesc('solde')
            ->get();

        return view('admin.dettesLivreurs', [
            'livreurs' => $livreurs,
        ]);
    }

    /**
     * Règlement total ou partiel d'une dette envers un apporteur, fournisseur ou livreur.
     *
     * Point 16 — Double validation : l'admin qui initie le règlement enregistre la
     * DemandePaiement avec paye=false et user_valide_id=Auth::id(). Le solde N'EST
     * PAS décrémenté tout de suite : il faudra qu'un AUTRE admin ouvre l'écran
     * "Demandes de paiement" et la valide en 2e (voir valideDemande).
     *
     * Inputs attendus :
     *  - type : 'apporteur' | 'fournisseur' | 'livreur'
     *  - tier_id : id de l'apporteur/fournisseur/livreur
     *  - montant : montant à régler
     *  - mode_paiement_id (optionnel) : id du mode de paiement
     *  - numero_compte (optionnel) : numéro de compte/téléphone utilisé
     */
    public function reglerDette(Request $request){
        $request->validate([
            'type' => 'required|in:apporteur,fournisseur,livreur',
            'tier_id' => 'required|integer',
            'montant' => 'required|numeric|min:0.01',
        ]);

        switch ($request->type) {
            case 'apporteur':
                $tier = Apporteur::find($request->tier_id);
                $userId = $tier?->user_id;
                $route = 'show.dettesApporteurs';
                break;
            case 'fournisseur':
                $tier = Fournisseur::find($request->tier_id);
                $userId = $tier?->user_id;
                $route = 'show.dettesFournisseurs';
                break;
            case 'livreur':
                $tier = Livreur::find($request->tier_id);
                $userId = $tier?->user_id;
                $route = 'show.dettesLivreurs';
                break;
            default:
                return back()->with('error', 'Type de tier invalide.');
        }

        if (!$tier) {
            return back()->with('error', 'Bénéficiaire introuvable.');
        }

        $montant = (float) $request->montant;

        if ($montant > (float) $tier->solde) {
            return back()->with('error',
                'Le montant à régler ('.number_format($montant, 0, ',', ' ').') dépasse la dette actuelle ('
                .number_format($tier->solde, 0, ',', ' ').').');
        }

        // Création d'une DemandePaiement EN ATTENTE de 2e validation.
        // Le solde du tier n'est décrémenté qu'au moment de la 2e validation
        // (cf. valideDemande), conformément au point 16.
        DemandePaiement::create([
            'montant' => $montant,
            'numero' => Help::genererNumeroUnique('demande_paiement'),
            'mode_paiement_id' => $request->mode_paiement_id,
            'user_id' => $userId,
            'user_valide_id' => Auth::id(),  // 1re validation (initiateur)
            'user_valide2_id' => null,       // en attente
            'date_validation' => null,
            'paye' => false,
            'numero_compte' => $request->numero_compte,
        ]);

        return redirect()->route($route)->with('success',
            'Règlement de '.number_format($montant, 0, ',', ' ').' fcfa initié. '
            .'En attente de la 2e validation par un autre administrateur '
            .'(écran « Demandes de paiement »).');
    }

    /**
     * Liste des créances à terme : pour chaque client à terme, on calcule
     * le montant total facturé non réglé (créance) à partir de ses factures
     * et paiements rattachés.
     */
    public function creanceATermeListe(){
        $clientsATerme = Client::where('client_a_terme', 1)->with(['user'])->get();

        $lignes = $clientsATerme->map(function ($client) {
            $totalFacture = (float) Facture::where('client_id', $client->id)->sum('montant');
            $totalPaye    = (float) Paiement::where('client_id', $client->id)
                                ->where('statut', 1)
                                ->sum('montant_total');
            $solde        = $totalFacture - $totalPaye;
            return (object) [
                'client'        => $client,
                'totalFacture'  => $totalFacture,
                'totalPaye'     => $totalPaye,
                'solde'         => $solde,
            ];
        })->filter(fn($l) => $l->solde > 0)->values();

        return view('admin.creanceATermeListe', [
            'lignes' => $lignes,
        ]);
    }

    public function registerAdminPage(){
        // $roleAdmin = Role::where('name', 'admin')->first();
        // $roleAdmin->givePermissionTo("admin");

        // $roleUser = Role::where('name','gestionnaire')->first();
        // $roleUser->givePermissionTo("gest");

        return view('admin.register');
    }

    public function editGestionnaire(User $user){
        return view('admin.updateGestionnaire',[
            'user' => $user
        ]);
    }

    public function updateGestionnaire(Request $request, User $user){

        $contact = $request->contact;
        $typeUserId = TypeUser::where('nom', 'like', '%gestionnaire%')->value('id');

        if($request->hasFile('photo')){
            Storage::disk('public')->delete($user->photo);
            $photo = $request->validated('photo');
            $photo_path = $photo->store('imageUser','public');
        }

        if (isset($request->login)) {
            # code...
            $user->update([
                'login' => $request->login
            ]);
        }
        if (isset($request->email)) {
            # code...
            $user->update([
                'email' => $request->email
            ]);
        }
        if (isset($request->password)) {
            # code...
            $user->update([
                'password' => Help::HashPassword($request->password),
            ]);
        }

        // Ne mettre à jour que les champs effectivement fournis pour éviter
        // d'écraser une colonne NOT NULL (ex. contact) avec null si le form
        // a été soumis avec un champ vide.
        $payload = [];
        if ($request->filled('nom_prenoms')) $payload['nom_prenoms'] = $request->nom_prenoms;
        if ($request->filled('contact'))     $payload['contact']     = $request->contact;
        if ($request->filled('adresse'))     $payload['adresse']     = $request->adresse;
        if (!empty($payload)) {
            $user->update($payload);
        }




        // dd($contact);
       return redirect()->route('show.editGestionnaire',$user)->with('success','Modification effectuée');
    }

    public function registerAdmin(Request $request){

        $request->validate([
            'nom'      => 'required|string|max:100',
            'prenom'   => 'required|string|max:100',
            'contact'  => 'required|string|max:15',
            'email'    => 'required|email|unique:users,email',
            'login'    => 'required|string|max:100|unique:users,login',
            // 'password' retiré : généré automatiquement et envoyé par email.
            'photo'    => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
        ], [
            'email.unique'    => 'Cet email est déjà utilisé.',
            'login.unique'    => 'Cet identifiant est déjà utilisé.',
            'contact.required'=> 'Le numéro de téléphone est obligatoire.',
            'photo.mimes'     => 'La photo doit être au format JPG, JPEG ou PNG.',
            'photo.max'       => 'La photo ne doit pas dépasser 2 Mo.',
        ]);

        // type_user_id = 2 (Admin) — utilise la constante au lieu d'une requête
        // qui pouvait renvoyer NULL si le libellé ne correspondait pas exactement.
        $typeUserId = Help::$USER_ADMIN;
        $nom_prenom = trim($request->nom . ' ' . $request->prenom);

        // Construire le numéro complet SANS redoubler l'indicatif : si l'utilisateur
        // a déjà saisi le numéro au format international (+225...) ou avec l'indicatif
        // en tête (225...), on ne re-préfixe pas. Évite "+225+225..." qui dépassait
        // varchar(15) -> erreur "Data too long" (1406) -> 500.
        $saisi           = preg_replace('/\s+/', '', (string) $request->contact);
        $digitsIndicatif = ltrim((string) ($request->indicatif ?: ''), '+');
        if (str_starts_with($saisi, '+')) {
            $contact = $saisi;
        } elseif ($digitsIndicatif !== '' && str_starts_with($saisi, $digitsIndicatif)) {
            $contact = '+' . $saisi;
        } else {
            $contact = ($request->indicatif ?: '') . $saisi;
        }

        // Upload optionnel de la photo de profil
        $nomImage = null;
        if ($request->hasFile('photo')) {
            $ext = $request->file('photo')->getClientOriginalExtension();
            $nomImage = 'image_admin_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $request->login)
                      . '_' . date('YmdHis') . '.' . $ext;
            $request->file('photo')->move(public_path('storage/imageUser'), $nomImage);
        }

        // Mot de passe GÉNÉRÉ automatiquement (celui qui crée le compte ne doit
        // pas le connaître) : envoyé au nouvel admin par email ci-dessous.
        $rawPassword = Help::ChaineAleatoire(8);

        $user = User::create([
            'nom_prenoms'  => $nom_prenom,
            'email'        => $request->email,
            'contact'      => $contact,
            'login'        => $request->login,
            // Important : utiliser Help::HashPassword (avec préfixe/suffixe sel)
            // car validLogin() vérifie via Help::HashVerifier qui attend ce format.
            // Hash::make() seul produirait un hash que HashVerifier refuserait.
            'password'     => Help::HashPassword($rawPassword),
            'photo'        => $nomImage,
            'type_user_id' => $typeUserId,
            'statut'       => 1,
        ]);

        // Envoi NON bloquant des identifiants générés. L'admin se connecte via
        // /login-account -> route('show.login'), d'où le type 'show' pour le bouton du mail.
        try {
            Mail::send(new MailAccesUsers($nom_prenom, $user->login, $rawPassword, $user->email, 'show'));
        } catch (\Throwable $e) {
            \Log::error('Erreur envoi email accès admin: ' . $e->getMessage());
        }

        // assignRole nécessite le package Spatie + le rôle 'admin' en DB.
        // On enveloppe dans un try/catch pour ne pas casser la création
        // si le rôle n'existe pas (l'utilisateur reste créé en DB avec type_user_id=2).
        try {
            $user->assignRole('admin');
        } catch (\Throwable $e) {
            // Silencieux : le compte est créé, type_user_id=2 suffit pour l'auth.
        }

        return redirect()->route('show.registerAdmin')
            ->with('ok', "Compte administrateur créé avec succès. Les identifiants de connexion de {$nom_prenom} lui ont été envoyés par email.");
    }

    public function home (){

        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfPreviousMonth = $now->copy()->subMonth()->startOfMonth();
        $endOfPreviousMonth = $now->copy()->subMonth()->endOfMonth();
        $today = $now->copy()->startOfDay();

        $data['commandes'] = Commande::with(['client:id,nom,prenom'])
            ->where('statut', Help::$STATUT_ACTIF)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();
        $data['totalCommandes'] = Commande::where('statut', Help::$STATUT_ACTIF)->count();

        $data['gainMensuel'] = (float) LignePaiement::whereMonth('created_at', $now->format('m'))
            ->whereYear('created_at', $now->format('Y'))
            ->sum('montant');
        $data['gainMoisPrecedent'] = (float) LignePaiement::whereBetween('created_at', [$startOfPreviousMonth, $endOfPreviousMonth])
            ->sum('montant');
        $data['evolutionGain'] = $data['gainMoisPrecedent'] > 0
            ? (($data['gainMensuel'] - $data['gainMoisPrecedent']) / $data['gainMoisPrecedent']) * 100
            : ($data['gainMensuel'] > 0 ? 100 : 0);

        $data['revenu'] = (float) LignePaiement::where('statut', Help::$STATUT_ACTIF)->sum('montant');
        $data['produit'] = Produit::count();
        $data['categorie'] = Categorie::count();

        // Stats commandes plus précises
        $data['commandesJour']        = Commande::whereDate('created_at', $today)->count();
        $data['commandesEnAttente']   = Commande::where('etat_commande', 'EN ATTENTE')->count();
        $data['commandesEnTraitement']= Commande::where('etat_commande', 'EN TRAITEMENT')->count();
        $data['commandesTerminees']   = Commande::where('etat_commande', 'TERMINEE')
            ->where('created_at', '>=', $startOfMonth)
            ->count();

        // Comptes
        $data['totalClients']      = Client::where('statut', 1)->count();
        $data['totalLivreurs']     = \App\Models\Livreur::count();
        $data['totalFournisseurs'] = \App\Models\Fournisseur::count();
        $data['totalApporteurs']   = \App\Models\Apporteur::count();

        // Top 5 produits les plus commandés
        $data['topProduits'] = \App\Models\DetailCommande::selectRaw('produit_id, SUM(qte) as total_qte')
            ->where('statut', 1)
            ->groupBy('produit_id')
            ->orderByDesc('total_qte')
            ->limit(5)
            ->with('produit')
            ->get();

        return view('layout.index',$data);

    }

    public function parametre(){

        $config = Configuration::first();

        // Données pour l'onglet "Prix personnalisés" (anciennement /configuration-prix)
        $clients = \App\Models\Client::where('statut', \Help::$STATUT_ACTIF)->orderBy('nom')->get();
        $produits = \App\Models\Produit::where('statut', \Help::$STATUT_ACTIF)->orderBy('nom')->get();
        $prixPersonnalises = \App\Models\PrixPersonnalise::with(['client', 'produit'])->whereHas('client')->get();

        // Prix fournisseur le plus bas par produit (stock actif, prix > 0) : sert de
        // prix de référence affiché, cohérent avec le catalogue (et non prix_moyen).
        $prixFournisseur = \App\Models\StockProduit::where('statut', \Help::$STATUT_ACTIF)
            ->where('prix', '>', 0)
            ->whereNull('deleted_at')
            ->groupBy('produit_id')
            ->selectRaw('produit_id, MIN(prix) as mn')
            ->pluck('mn', 'produit_id')
            ->toArray();

        $clientsAvecPrix = $prixPersonnalises->groupBy('client_id')->map(function ($items) {
            $client = $items->first()->client;
            return (object) [
                'client' => $client,
                'produits' => $items->map(function ($item) {
                    return (object) [
                        'id' => $item->id,
                        'produit' => $item->produit,
                        'prix' => $item->prix,
                    ];
                }),
            ];
        });

        return view('layout.parametre',[
            'config' => $config,
            'user' => Auth::user(),
            'gestionnaires' => User::where('type_user_id', 3)->orWhere('type_user_id', 2)->orderByDesc('created_at')->get(),
            'clients' => $clients,
            'produits' => $produits,
            'clientsAvecPrix' => $clientsAvecPrix,
            'prixFournisseur' => $prixFournisseur,
        ]);
    }

    public function parametreUpdate(Request $request){
        // dd($request->all());
        $config = Configuration::first();

        $config->update($request->only([
            'tva',
            'montant_point',
            'email_tresorier',
            'email_directeur_marketing',
            'gestionnaire1_id',
            'gestionnaire2_id',
            'devise',
            'prixKm',
            'cout_livraison_min',
            'tonne_moyenne',
            'cout_liv_fixe',
            // Créances clients à terme
            'delai_relance_standard',
            'seuil_alerte_retard',
            // Comptant / agence
            'delai_max_paiement_agence',
            'delai_annulation_auto',
            // Livreurs
            'frequence_paiement_livreur',
            'jour_paiement_livreur',
            'forfait_base_livreur',
            // Apporteurs
            'taux_commission_standard',
            'delai_paiement_commission',
            // Contenu légal paramétrable (termes & conditions)
            'termes_conditions',
        ]));

        return redirect()->route('show.parametre')->with('success','Les changements ont été appliqué avec succès ');
    }

    function deleteGestionnaire($id){
        $user = User::findOrFail($id);
        $user->delete(); // Soft delete

        return response()->json(['message' => 'Utilisateur désactivé avec succès.']);
    }

    function deleteAgent($id){

        $this->deleteUser($id);

        return response()->json(['message' => 'Utilisateur désactivé avec succès.']);
    }


    /*********************************************** Partie Private ********************************************** */
    private function generateCode(){

        $gCode = Help::ChaineAleatoire(5);
        $deja = Enlevement::where('code_enleve',  $gCode)->first();
        if(! is_null($deja)){
            $this->generateCode();
        }else{
            return Str::start($gCode, 'ENV');
        }
    }

    private  function deleteUser($id){
        $user = User::findOrFail($id);
        $user->delete();
    }

    public function errorCatchBack(){
        return view('layout.errorCatchBack');
    }



}
