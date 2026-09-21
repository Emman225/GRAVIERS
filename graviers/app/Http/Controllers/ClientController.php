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
use Illuminate\Support\Facades\Schema;
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
use App\Models\Slide;
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

    /**
     * Les quatre blocs mis en avant sur l'accueil, cinq produits chacun.
     *
     * Ils affichaient tous la même chose : même source, même critère
     * (meilleur_note >= 90), aucune borne. Chacun repose désormais sur ce que
     * son titre annonce :
     *
     *   - Meilleures ventes   : quantités vendues depuis toujours ;
     *   - Produits tendance   : quantités vendues sur les 30 derniers jours,
     *                           ce qui distingue l'élan récent du palmarès
     *                           historique ;
     *   - Ajoutés récemment   : date de création de la fiche produit ;
     *   - Meilleure note      : note attribuée au produit.
     *
     * Les commandes ANNULÉES et les lignes désactivées sont exclues : une
     * commande annulée n'est pas une vente.
     *
     * Un bloc incomplet est complété par les produits les mieux notés qu'il ne
     * contient pas déjà. Sans ce repli, un site sans historique de ventes —
     * juste après une remise à zéro de la base — afficherait deux colonnes
     * vides sur sa page d'accueil.
     */
    private function blocsMisEnAvant(): array
    {
        // Colonnes qualifiées : « statut » et « type_affaire » existent aussi sur
        // detail_commande et commande, et deviennent ambigus dès la jointure.
        $catalogue = fn () => Produit::with('image')
            ->where('produit.type_affaire', 'VENTE')
            ->where('produit.statut', 1);

        // En DEUX temps, volontairement : d'abord le classement (uniquement des
        // colonnes agrégées ou groupées), puis les fiches produits.
        //
        // Un « SELECT produit.* ... GROUP BY produit.id » n'est accepté que par
        // les moteurs qui savent déduire la dépendance fonctionnelle à la clé
        // primaire. MySQL 8 le fait, MariaDB non : sous ONLY_FULL_GROUP_BY, la
        // même requête y échoue. Écrite ainsi, elle passe partout.
        $parVentes = function ($depuis = null) use ($catalogue) {
            $classement = \Illuminate\Support\Facades\DB::table('detail_commande as dc')
                ->join('commande as c', 'c.id', '=', 'dc.commande_id')
                ->where('dc.statut', 1)
                ->where('c.statut', 1)
                ->where('c.etat_commande', '!=', 'ANNULEE')
                ->when($depuis, fn ($q) => $q->where('c.date_commande', '>=', $depuis))
                ->groupBy('dc.produit_id')
                ->orderByRaw('SUM(dc.qte) DESC')
                ->limit(30)
                ->pluck('dc.produit_id')
                ->all();

            if (empty($classement)) {
                return collect();
            }

            // Le classement porte sur l'historique : le catalogue tranche
            // ensuite (un produit hors vente ou désactivé ne remonte pas).
            // D'où la marge de 30 avant de retenir les 5 premiers.
            $fiches = $catalogue()->whereIn('produit.id', $classement)->get()->keyBy('id');

            return collect($classement)
                ->map(fn ($id) => $fiches->get($id))
                ->filter()
                ->take(5)
                ->values();
        };

        $mieuxNotes = $catalogue()->orderByDesc('meilleur_note')->limit(15)->get();

        // Complète un bloc trop court sans jamais y répéter un produit.
        $completer = function ($bloc) use ($mieuxNotes) {
            $dejaLa = $bloc->pluck('id')->all();
            return $bloc->concat($mieuxNotes->whereNotIn('id', $dejaLa))->take(5)->values();
        };

        return [
            'blocMeilleuresVentes' => $completer($parVentes()),
            'blocTendance'         => $completer($parVentes(now()->subDays(30))),
            'blocRecents'          => $completer($catalogue()->orderByDesc('produit.created_at')->limit(5)->get()),
            'blocMeilleureNote'    => $completer($mieuxNotes->take(5)),
        ];
    }

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

        // LA MÊME RÈGLE QUE PARTOUT AILLEURS : prix d'achat le plus élevé majoré
        // du pourcentage DALAKOUN.
        //
        // Cet écran gardait sa propre copie du calcul, sur le fournisseur le
        // MOINS cher et sans marge. Elle écrasait le prix calculé : après la
        // mise en service du pourcentage, l'accueil affichait encore 100 F un
        // gravier vendu 14 850.
        Produit::alignerPrixAffiche($produits);

        foreach ($categories as $cat) {
            Produit::alignerPrixAffiche($cat->produits);
        }

        return view('client.index', array_merge($this->blocsMisEnAvant(), [
            'produits' => $produits,
            'categories' => $categories,
            'client' => $client,
            'prixPerso' => $prixPerso,
            // C'est CETTE méthode qui sert la racine du site (route client.index) ;
            // index() rend la même vue mais depuis une autre adresse. Les deux
            // doivent fournir les diapositives ET les quatre blocs mis en avant,
            // sans quoi la page tombe sur « Undefined variable ».
            'slides' => Slide::liste(),
        ]));
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
        $data = config('constantes.logo_pdf');
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

    /**
     * LA PROFORMA OU LA FACTURE DE LA COMMANDE, EN PDF (lot 81, 15/09/2026) :
     * le bouton de « Mon compte ». Le document est celui du courriel.
     */
    public function documentCommandePdf($numero){
        $commande = Commande::where('numero', $numero)->firstOrFail();
        $client   = Client::where('user_id', Auth::id())->first();
        if (!$client || (int) $commande->client_id !== (int) $client->id) {
            abort(403);
        }

        return \App\Services\DocumentDeCommande::pdf($commande)
            // Prévisualisation par défaut (lot 95, 16/09/2026) ; ?action=telecharger pour le fichier.
            ->{request('action') === 'telecharger' ? 'download' : 'stream'}(\App\Services\DocumentDeCommande::nomFichier($commande));
    }

    /**
     * L'API des mobiles demande au site d'envoyer la proforma (ou la facture)
     * d'une commande passée depuis l'application, en présentant le jeton
     * partagé JETON_INTERNE — comme pour le reçu de paiement (routes/api.php).
     */
    public function envoyerDocumentInterne(Request $request, $numero){
        $jeton = (string) config('constantes.jeton_interne');
        if ($jeton === '' || !hash_equals($jeton, (string) $request->input('jeton', ''))) {
            return response()->json(['code' => 403, 'message' => 'Jeton interne absent ou invalide'], 403);
        }

        $commande = Commande::where('numero', $numero)->first();
        if (!$commande) {
            return response()->json(['code' => 404, 'message' => 'Commande introuvable'], 404);
        }

        $envoye = \App\Services\DocumentDeCommande::envoyerParCourriel($commande, true);

        return response()->json([
            'code'   => 200,
            'envoye' => $envoye,
            'type'   => \App\Services\DocumentDeCommande::type($commande),
        ]);
    }

    /** Les factures DGI du client pour l'application (lot 95, 16/09/2026), par le jeton interne. */
    public function facturesInterne(Request $request, Client $client){
        $jeton = (string) config('constantes.jeton_interne');
        if ($jeton === '' || !hash_equals($jeton, (string) $request->input('jeton', ''))) {
            return response()->json(['code' => 403, 'message' => 'Jeton interne absent ou invalide'], 403);
        }

        return response()->json(['code' => 200, 'data' => \App\Services\FacturesDuClient::pourLApplication($client)]);
    }

    /** Le PDF d'une facture DGI (vente, location, transport, avoir) pour l'application (lot 95). */
    public function facturePdfInterne(Request $request, $factureId){
        $jeton = (string) config('constantes.jeton_interne');
        if ($jeton === '' || !hash_equals($jeton, (string) $request->input('jeton', ''))) {
            return response()->json(['code' => 403, 'message' => 'Jeton interne absent ou invalide'], 403);
        }
        $facture = \App\Models\Facture::find($factureId);
        if (!$facture) {
            return response()->json(['code' => 404, 'message' => 'Facture introuvable'], 404);
        }
        $pdf = \App\Services\CourrielFactureFne::pdf($facture);
        if (!$pdf) {
            return response()->json(['code' => 404, 'message' => 'Document indisponible'], 404);
        }

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . \App\Services\CourrielFactureFne::nomFichier($facture) . '"',
        ]);
    }

    /**
     * « Mes paiements » du client pour l'application (lot 89, 16/09/2026) : l'API
     * demande la liste avec le jeton partagé JETON_INTERNE.
     */
    public function paiementsInterne(Request $request, Client $client){
        $jeton = (string) config('constantes.jeton_interne');
        if ($jeton === '' || !hash_equals($jeton, (string) $request->input('jeton', ''))) {
            return response()->json(['code' => 403, 'message' => 'Jeton interne absent ou invalide'], 403);
        }

        return response()->json(['code' => 200, 'data' => \App\Services\PaiementsDuClient::pourLApplication($client)]);
    }

    /** Le client connecté, ou 403 si l'affaire n'est pas à lui (lot 85). */
    private function clientProprietaire($affaire): Client
    {
        $client = Client::where('user_id', Auth::id())->first();
        if (!$client || !$affaire || (int) $affaire->client_id !== (int) $client->id) {
            abort(403);
        }

        return $client;
    }

    /** Proforma ou facture d'une LOCATION, en PDF (lot 85, 15/09/2026). */
    public function documentLocationPdf(Location $location){
        $this->clientProprietaire($location);

        return \App\Services\DocumentDAffaire::pdfLocation($location)
            // Prévisualisation par défaut (lot 95, 16/09/2026) ; ?action=telecharger pour le fichier.
            ->{request('action') === 'telecharger' ? 'download' : 'stream'}(strtolower(\App\Services\DocumentDAffaire::typeLocation($location)) . '-location-' . $location->numero . '.pdf');
    }

    /** Proforma ou facture d'une DEMANDE DE LIVRAISON, en PDF (lot 85, 15/09/2026). */
    public function documentLivraisonPdf(DemandeLivraison $demande){
        $this->clientProprietaire($demande);

        return \App\Services\DocumentDAffaire::pdfLivraison($demande)
            // Prévisualisation par défaut (lot 95, 16/09/2026) ; ?action=telecharger pour le fichier.
            ->{request('action') === 'telecharger' ? 'download' : 'stream'}(strtolower(\App\Services\DocumentDAffaire::typeLivraison($demande)) . '-livraison-' . $demande->numero . '.pdf');
    }

    /** Les factures DGI d'une location ou d'une demande de livraison (lot 85). */
    public function listeFactureAffaire($service, $id){
        if ($service === 'location') {
            $affaire = Location::findOrFail($id);
            $codeService = Help::$LOCATION;
            $libelle = 'location';
        } elseif ($service === 'livraison') {
            $affaire = DemandeLivraison::findOrFail($id);
            $codeService = Help::$LIVRAISON;
            $libelle = 'demande de livraison';
        } else {
            abort(404);
        }
        $client = $this->clientProprietaire($affaire);

        return view('client.listeFactureAffaire', [
            'affaire'    => $affaire,
            'libelle'    => $libelle,
            'factures'   => \App\Services\DocumentDAffaire::factures($codeService, (int) $affaire->id),
            'categories' => Categorie::all(),
            'produits'   => Produit::all(),
            'client'     => $client,
        ]);
    }

    /** Une facture DGI de location ou de transport du client, à voir ou télécharger (lot 85). */
    public function factureAffairePdf(\App\Models\Facture $facture, $action = 'voir'){
        $client = Client::where('user_id', Auth::id())->first();
        if (!$client || (int) $facture->client_id !== (int) $client->id) {
            abort(403);
        }
        $pdf = \App\Services\DocumentDAffaire::pdfFacture($facture);
        if (!$pdf) {
            abort(404);
        }
        $nom = \App\Services\DocumentDAffaire::nomFichierFacture($facture);

        return $action === 'telecharger' ? $pdf->download($nom) : $pdf->stream($nom);
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

        $pdf = PDF::loadView('document.etatCommande',['commandes' => $commandes,'image' => $image, 'client' => $client])
                    ->setOptions(['isHTML5ParseEnebled' => true, 'defaultPaperOrientation' => 'portait'] );

        return $pdf->download('Mes commandes DALAKOUN.pdf');

        // $client = Client::where('user_id',Auth::user()->id)->first();

        // $commandes = Commande::where('client_id',$client->id)->orderByDesc('created_at')->get();


        // return Excel::download(new exportCommandeClient($commandes) , 'Mes-commandes.xlsx');
    }
    public function exportDemandeDeLivraison(){
        $image = config("constantes.logo");;
        $client = Client::where('user_id',Auth::user()->id)->first();
        $livraisons = DemandeLivraison::where('client_id',$client->id)->orderByDesc('created_at')->get();

        $pdf = PDF::loadView('document.etatLivraison',['livraisons' => $livraisons,'image' => $image, 'client' => $client])
                    ->setOptions(['isHTML5ParseEnebled' => true, 'defaultPaperOrientation' => 'portait'] );

        return $pdf->download('Mes demandes de livraison DALAKOUN.pdf');

        // $client = Client::where('user_id',Auth::user()->id)->first();

        // $commandes = Commande::where('client_id',$client->id)->orderByDesc('created_at')->get();


        // return Excel::download(new exportCommandeClient($commandes) , 'Mes-commandes.xlsx');
    }
    public function exportLocation(){
        $image = config("constantes.logo");;
        $client = Client::where('user_id',Auth::user()->id)->first();
        $locations = Location::where('client_id',$client->id)->orderByDesc('created_at')->get();

        $pdf = PDF::loadView('document.etatLocation',['locations' => $locations,'image' => $image, 'client' => $client])
                    ->setOptions(['isHTML5ParseEnebled' => true, 'defaultPaperOrientation' => 'landscape '] );
        // var_dump($locations.'<br>');

        // die;
        return $pdf->stream();

        // $client = Client::where('user_id',Auth::user()->id)->first();

        // $commandes = Commande::where('client_id',$client->id)->orderByDesc('created_at')->get();


        // return Excel::download(new exportCommandeClient($commandes) , 'Mes-commandes.xlsx');
    }

    public function etatCommande(){
        // La vue client.etatCommande n'a jamais été créée : PDF::loadView levait
        // « View not found » (erreur 500) pour quiconque ouvrait /etat-commande.
        // Aucune page du site ne pointe vers cette route ; on renvoie l'utilisateur
        // vers son compte plutôt que de le laisser sur un écran cassé.
        return redirect()->route('client.monCompte');
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

        // Un seul jeu de données pour l'écran, le PDF et le courriel (lot 81) :
        // le titre dit « Proforma » ou « Facture de vente » selon le paiement.
        return view('client.commandeValidee', \App\Services\DocumentDeCommande::donnees($commande));




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
            'paiements' => ModePaiement::listePourClient(),
            'types_livraison' => TypeLivraison::all()
        ]);
    }

    public function demandeClientATermePage(){

        $client = Client::where('user_id', Auth::id())->first();

        // Sans fiche client, la vue lisait $client->DemandeCompteClientATerme sur
        // null : page blanche en erreur 500.
        if ($client == null) {
            return redirect()->route('client.monCompte')
                ->with('error', "Votre fiche client est introuvable. Contactez-nous pour la régulariser.");
        }

        // La DERNIÈRE demande, explicitement. La relation hasOne du modèle ne
        // porte aucun tri : après un refus suivi d'une nouvelle demande, elle
        // pouvait renvoyer l'ancienne. Le traitement de l'envoi, lui, prend déjà
        // orderByDesc('id') — les deux écrans doivent parler de la même demande.
        $demande = DemandeCompteClientATerme::where('client_id', $client->id)
            ->orderByDesc('id')
            ->first();

        // La clé 'client' était passée DEUX FOIS : la seconde écrasait la
        // première, qui prévoyait pourtant le cas d'un visiteur.
        return view('client.clientATerme',[
            'villes' => Ville::all(),
            'produits' => Produit::all(),
            'categories' => Categorie::all(),
            'client' => $client,
            'demande' => $demande,
            // Le compte à terme est réservé aux ENTREPRISES (décision de gestion
            // du 08/08/2026) : le dossier exigé — RCCM, bilan, pièce du dirigeant
            // — n'a pas d'équivalent pour un particulier, qui envoyait donc un
            // dossier vide sans que rien ne l'en empêche.
            // Un particulier ayant déjà une demande en cours continue de la voir :
            // on ne lui masque pas une démarche engagée.
            'reserveEntreprise' => $client->type_client !== 'ENTREPRISE' && $demande === null,
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

            // LE FORMAT ACCEPTE EST DEFINI A UN SEUL ENDROIT : voir
            // App\Support\BonDeCommandeJoint. Recopiee sur quatre ecrans, la regle
            // se serait fatalement ecartee d'elle-meme.
            $request->validate(
                ['fichier' => \App\Support\BonDeCommandeJoint::regle()],
                \App\Support\BonDeCommandeJoint::messages()
            );

            $destination = Storage::disk('public')->path('temp_pdfs'); // racine reelle du disque (cf. config/filesystems.php)
            // L'extension vient de la LISTE BLANCHE, jamais du nom recu.
            $nomPdf = \App\Support\BonDeCommandeJoint::nomDeFichier(
                $request->file('fichier'), Auth::user()->client->display_name);

            $request->file('fichier')->move($destination, $nomPdf);

            session()->put([
                'cheminFichier' => 'temp_pdfs/'.$nomPdf,
                'numero_bon_commande' => $request->numero_bon,
                'fichier' => $nomPdf
            ]);

        }

        $conf = Configuration::first();



        // PRIX DU TRANSPORT — lu dans la GRILLE TARIFAIRE, comme le fait déjà
        // l'application mobile.
        //
        // Le site calculait auparavant « distance × prixKm », avec un plancher.
        // Deux défauts :
        //   · la QUANTITÉ n'entrait pas dans le calcul — une tonne et cent
        //     cinquante tonnes coûtaient le même prix, ce qui n'a pas de sens
        //     pour du transport ;
        //   · le prix DÉPENDAIT DU CANAL. Sur un même trajet d'une tonne entre
        //     Yopougon et Cocody, le site annonçait 59 FCFA quand l'application
        //     en demandait 20 000.
        //
        // La grille est désormais la seule source, pour les deux canaux : on
        // additionne, ligne par ligne, le forfait de la tranche correspondante
        // (unité, quantité, distance). Une ligne sans tarif est ignorée ; si
        // AUCUNE n'en a, la demande ne peut pas être chiffrée et on le dit.
        $prix = 0;
        $lignesSansTarif = [];

        foreach ($request->produit as $cle => $nomProduit) {
            $cout = CoutLivraison::lireSurCle(
                $request->unite[$cle] ?? 0,
                (float) ($request->qte[$cle] ?? 0),
                $km
            );

            if (($cout->id ?? 0) > 0) {
                $prix += (float) $cout->prix_km;
            } else {
                $lignesSansTarif[] = $nomProduit;
            }
        }

        if ($prix <= 0) {
            return back()->withInput()->with('tarif_introuvable', sprintf(
                "Nous ne pouvons pas chiffrer ce transport : aucun tarif n'est défini pour %s sur %s km. "
                . "Contactez-nous, nous établirons un devis.",
                count($lignesSansTarif) > 1 ? 'ces marchandises' : 'cette marchandise',
                rtrim(rtrim(number_format($km, 1, ',', ' '), '0'), ',')
            ));
        }

        $client = Help::clientValide();

        // LA TVA SUR LE TRANSPORT EST UNE OPTION.
        //
        // Le site ajoutait 18 % au coût du transport, l'application mobile non :
        // la même course coûtait 23 600 F depuis le site et 20 000 F depuis le
        // téléphone. L'arbitrage du 13/08/2026 l'avait supprimée, en gardant la
        // mécanique pour le jour où le régime changerait.
        //
        // Elle est désormais commandée par un réglage — Paramètres → Livraison —
        // et reste DÉSACTIVÉE par défaut : sans décision explicite, le transport
        // se facture hors taxe, comme aujourd'hui.
        //
        // Le taux est lu au moment de la demande et le montant figé avec elle :
        // une course chiffrée hors taxe le reste, même si l'option est activée
        // le lendemain. On ne rouvre pas un prix annoncé à un client.
        $tva = Client::tva($client);

        $tvaSurTransport = (int) ($conf->tva_transport ?? 0) === 1;
        $montantTva = $tvaSurTransport ? round($prix * $tva) : 0;

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

        // Cette page ENREGISTRE la demande à partir de ce que le formulaire a
        // déposé en session, puis vide ces clés (voir la fin de la méthode) et
        // redirige. Elle n'a donc de sens qu'une seule fois, aussitôt après le
        // formulaire.
        //
        // Or son URL n'a aucun paramètre : elle est rejouable telle quelle. Un
        // rafraîchissement, un retour arrière, un lien remis dans la barre
        // d'adresse ou un favori la rappelaient avec une session déjà vidée, et
        // la méthode partait droit dans le mur : « Attempt to read property
        // "pays" on null », erreur 500, page blanche. Le client venait pourtant
        // d'enregistrer sa demande correctement.
        //
        // On ramène ces cas au formulaire, avec l'explication.
        $enSession = ['villePec', 'villeDest', 'produits', 'paiement', 'type_livraison'];

        foreach ($enSession as $cle) {
            if (!session()->has($cle) || session($cle) === null || session($cle) === '') {
                return redirect()->route('client.demandeLivraison')
                    ->with('error', "Votre demande de livraison n'est plus en cours de saisie. "
                        . "Si vous venez de la valider, retrouvez-la dans « Mes demandes de livraison ». "
                        . "Sinon, renseignez à nouveau le formulaire ci-dessous.");
            }
        }

        // LE PRIX N'EST PAS CALCULÉ ICI.
        //
        // Il l'était — sur une formule à part : « km × prix_km », avec un repli
        // à « km × 5 000 », en ignorant l'unité et la quantité. Mais le montant
        // ainsi obtenu n'était utilisé nulle part : la demande est enregistrée
        // avec `session('montant_total')`, que `recapLivraison()` a posé en
        // additionnant, ligne par ligne, le forfait de la tranche de la grille.
        //
        // Quinze lignes qui ressemblaient au calcul du prix de la course sans
        // en être : le jour où quelqu'un aurait voulu ajuster le tarif des
        // demandes de livraison, il les aurait trouvées, modifiées, et n'aurait
        // pas compris pourquoi rien ne changeait. Elles chargeaient au passage
        // les 96 tranches à chaque validation, pour les jeter.
        //
        // La grille reste la seule source du prix, ici comme ailleurs.
        $paiement = ModePaiement::find(session('paiement'));
        // dd($paiement);
        $type_livraison = TypeLivraison::where('libelle',session('type_livraison'))->first();
        $unite = UniteProduit::where('abreviation', session('unite'))->first();

        // dd($mode, $type_livraison,$unite,$mode->id,$unite->id);

        // dd(session('poids'));
        $client = Client::where('user_id',Auth::user()->id)->first();

        // LE PLAFOND DU PAIEMENT EN LIGNE — AVANT LA CRÉATION DE LA DEMANDE.
        //
        // Le test existait plus bas, écrit à la main, et il se contentait de
        // SAUTER la passerelle : la demande était enregistrée sans qu'un franc
        // soit encaissé et sans que le client soit prévenu. On le lui dit ici,
        // et rien n'est créé tant qu'il n'a pas choisi un mode possible.
        $montantDemande = (float) session('montant_total') + (float) session('montantTva');

        if ($paiement
            && !\App\Support\PlafondPaiementEnLigne::modeAutorise($paiement, $montantDemande)) {
            return back()->with('error', \App\Support\PlafondPaiementEnLigne::refus());
        }

        // PLAFOND DE CRÉDIT. Le contrôle gardait les six points de création de
        // commandes et de locations, mais pas celui-ci : un client à terme
        // pouvait accumuler du transport sans aucune limite. Une demande de
        // livraison l'engage pourtant comme le reste — la marchandise circule et
        // il paiera plus tard.
        //
        // Le montant retenu est le net réellement dû : transport + TVA. Aucune
        // remise n'est appliquée à ce stade sur une demande de livraison.
        $engageLivraison = (float) session('montant_total') + (float) session('montantTva');
        // L'AIRSI fait partie de ce que le client devra (10/09/2026).
        $engageLivraison += \Help::airsiPour($client, $engageLivraison,
            (float) session('montant_total'), (float) Client::tvaTransport($client));

        // Une avance disponible couvre d'abord la demande : seul le reliquat
        // engage le crédit — même règle que la vente (point 19). Un mode en
        // ligne ne touche pas à l'avance.
        $modeEnLigneLivraison = $paiement && (int) $paiement->en_ligne === 1;
        if (!$modeEnLigneLivraison) {
            $engageLivraison = max(0, $engageLivraison - \App\Services\Avances::soldeDisponible($client));
        }

        if ($refus = $this->refusPlafondCredit($client, $engageLivraison)) {
            return redirect()->route('client.demandeLivraison')->with('error', $refus);
        }

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
            // MÊME FORMAT DE NUMÉRO QUE LES VENTES.
            //
            // Le site écrivait `uniqid()` : une suite de treize caractères
            // comme « 6a999a12546cb », quand une commande de vente porte six
            // chiffres. Deux affaires du même client, voisines dans les
            // listes, ne se lisaient pas de la même façon — et l'application
            // mobile, elle, générait déjà les six chiffres (LivraisonController
            // de l'API). Même défaut, et même correction, que sur les numéros
            // de location.
            'numero' => \Help::genererNumeroUnique('demande_livraison'),
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

            // Le formulaire transmet l'IDENTIFIANT de l'unité. La table en
            // attend deux formes : la clé et le libellé, ce dernier obligatoire
            // et sans valeur par défaut. Il n'était pas renseigné : la demande
            // s'enregistrait, puis l'insertion de ses lignes échouait en erreur
            // 500 — le client voyait une page blanche, et le gestionnaire
            // héritait d'une demande sans aucun produit.
            $uniteProduit = UniteProduit::find($produit['unite']);

            $detailLivraison = [
                'nom_produit' => $produit['nom_produit'],
                'qte' => $produit['qte'],
                'unite' => $uniteProduit?->libelle ?: ($uniteProduit?->abreviation ?: 'U'),
                'unite_produit_id' => $produit['unite'],
                'demande_livraison_id' => $c->id,
                // État en toutes lettres : etat_livraison est un ENUM, où un
                // entier désigne la position de la valeur et non la valeur.
                'etat_livraison' => Help::$LIVRAISON_EN_ATTENTE,
                'description' => $produit['desc'] ?? '',

            ];
            $d = DetailLivraison::create($detailLivraison);
        }

        // Le transport n'est pas soumis à la TVA : aucune ligne n'est écrite
        // quand le montant est nul, plutôt qu'une ligne à zéro. Une écriture à
        // zéro laisserait croire à une taxe calculée puis exonérée, alors qu'il
        // n'y en a simplement pas. La condition est conservée pour le jour où
        // le régime changerait : le reste de la chaîne (montantAPayer) sait
        // déjà additionner cette ligne si elle existe.
        if ((float) session('montantTva') > 0) {
            TvaCommande::create([
                'client_id' => $client->id,
                'commande_id' => $c->id,
                'montant' => \Help::arrondiFranc(session('montantTva') ?? 0),
                'type_affaire' => Help::$LIVRAISON,
            ]);
        }
        // AIRSI figé sur la demande (10/09/2026) : transport TTC.
        $c->update(['airsi' => \Help::airsiPour($client,
            (float) session('montant_total') + \Help::arrondiFranc((float) (session('montantTva') ?? 0)),
            (float) session('montant_total'), (float) Client::tva($client))]);

        // Bon de commande joint par le client.
        //
        // Le fichier était bien téléversé et déposé dans « temp_pdfs » par
        // recapLivraison, mais RIEN ne le reliait ensuite à la demande : le
        // gestionnaire qui la traite ne pouvait pas le consulter, et le fichier
        // restait orphelin sur le disque.
        //
        // Les clés de session n'étaient pas purgées non plus : le bon joint à
        // une demande de livraison pouvait se retrouver rattaché à la COMMANDE
        // suivante du même client, qui n'a rien à voir avec elle.
        //
        // Même traitement que pour les commandes et les locations : on déplace
        // le fichier de « temp_pdfs » vers « lesBons », on l'enregistre, puis on
        // purge la session.
        if (session('cheminFichier')) {
            $sourcePath = session('cheminFichier');
            $destinationPath = 'lesBons/' . basename($sourcePath);

            if (Storage::disk('public')->exists($sourcePath)) {
                Storage::disk('public')->move($sourcePath, $destinationPath);
            }

            BlClient::create([
                'numero'               => session('numero_bon_commande'),
                'client_id'            => $client->id,
                'fichier'              => $destinationPath,
                'demande_livraison_id' => $c->id,
            ]);
        }

        // Purge inconditionnelle : même sans fichier, un numéro de bon resté en
        // session serait repris par la demande ou la commande suivante.
        session()->forget(['cheminFichier', 'numero_bon_commande', 'fichier']);

               // ***************************************************************************************// ***************************************************************************************// ***************************************************************************************

                // $paiement = new Paiement();
                // $paiement->client_id = $client->id;
                // $paiement->devis_id = $devis->id;
                // $paiement->code = $c->numero;
                // $paiement->libelle = "Paiement commande de produit DALAKOUN";
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
                // Hors ligne / en ligne selon le flag en_ligne du mode (et non id=1) :
                // « Paiement en agence » (en_ligne=0) reste un paiement hors ligne.
                $modeLivObj = session('paiement') ? ModePaiement::find(session('paiement')) : null;
                // Le paiement en ligne repose sur le MODE CHOISI, plus sur le statut
                // du client. Il était sauté pour tout client à terme : celui qui
                // désignait Orange Money ou Wave voyait sa demande enregistrée sans
                // qu'aucun règlement ne parte. Même correction que pour les commandes
                // et les locations, où le verrou a déjà été retiré.
                // Le plafond a déjà été opposé au client plus haut, AVANT que la
                // demande n'existe. Ici il ne reste que le caractère du mode :
                // le laisser une seconde fois ferait deux règles à tenir, et
                // celle-ci escamotait le paiement au lieu de le refuser.
                if ($modeLivObj && $modeLivObj->en_ligne == 1) {
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
                            'libelle_article' => "Paiement DALAKOUN",
                            'quantite' => 1,
                            'montant' => ceil($c->montantTotal + session('montantTva')),
                            'lib_order' => "Paiement commande de produit DALAKOUN",
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



        // AVANCE DU CLIENT (10/09/2026) : une demande réglée « en agence »
        // s'impute d'elle-même sur les avances disponibles, comme une vente.
        // Un mode en ligne n'y touche pas, même si la passerelle a échoué.
        $messageAvance = null;
        if (!($modeLivObj && $modeLivObj->en_ligne == 1)) {
            $imputationAvance = \App\Services\Avances::imputerSurDemandeLivraison($c, Auth::id());
            $messageAvance = \App\Services\Avances::messageImputation($imputationAvance, Help::$LIVRAISON) ?: null;
        }

        return redirect()->route('client.livraisonValide',$c)
            ->with('success','Votre demande de livraison a bien été envoyé')
            ->with('avance_imputee', $messageAvance);
    }

    public function livraisonValide(DemandeLivraison $livraison){

        // dd($livraison);
        return view('orders.livraisonValide',[
            'livraison' => $livraison,
            'client' => Client::where('user_id',Auth::user()->id)->first(),
        ]);
    }

    public function demandeClientATerme(Request $request){
        // LES PIÈCES SONT OBLIGATOIRES (07/09/2026). Le formulaire les
        // exige aussi, mais seul le serveur fait foi : le message nomme la
        // pièce qui manque, pour que le client sache quoi joindre.
        $request->validate([
            'objet'       => 'required|string|max:255',
            'description' => 'required|string|min:20',
            'documents.rccm'     => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
            'documents.bilan'    => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
            'documents.piece_id' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'documents.autre'    => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
        ], [
            'description.min' => 'Veuillez détailler votre demande (au moins 20 caractères).',
            'documents.rccm.required'     => 'Pièce manquante : le RCCM / registre de commerce est obligatoire.',
            'documents.bilan.required'    => "Pièce manquante : l'attestation de revenus ou le bilan est obligatoire.",
            'documents.piece_id.required' => "Pièce manquante : la pièce d'identité du dirigeant est obligatoire.",
            'documents.*.mimes' => 'Les documents doivent être au format PDF, JPG, PNG, DOC ou DOCX.',
            'documents.*.max' => 'Chaque document ne doit pas dépasser 5 Mo.',
        ]);

        $client = Client::where('user_id',Auth::user()->id)->first();

        // Sans cette fiche, $client->id plus bas serait lu sur null.
        if ($client == null) {
            return redirect()->route('client.monCompte')
                ->with('error', "Votre fiche client est introuvable. Contactez-nous pour la régulariser.");
        }

        // Compte à terme réservé aux ENTREPRISES. Le contrôle est refait ICI et
        // pas seulement à l'affichage : masquer un formulaire n'empêche pas
        // d'envoyer la requête directement à cette adresse.
        if ($client->type_client !== 'ENTREPRISE') {
            return redirect()->route('client.demandeClientATermePage')
                ->with('error', "Le compte à terme est réservé aux clients professionnels. Contactez-nous si votre activité relève de ce statut.");
        }

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

        // Même protection que sur l'ouverture d'un ticket : la page expose le
        // produit, sa quantité, son prix et le numéro de commande à partir d'un
        // identifiant pris dans l'URL.
        $leClient = Client::where('user_id', Auth::user()->id)->first();
        if (!$leClient || optional($detail->commande)->client_id !== $leClient->id) {
            return redirect()->route('client.retourProduitPage')
                ->with('error', "Ce produit ne figure pas dans vos commandes.");
        }

        return view('client.motifRetour',[

            'produits' => Produit::all(),
            'client' => (Auth::user()) ? Client::where('user_id',Auth::user()->id)->first() : new Client,
            'categories' => Categorie::all(),
            'detail' => $detail
        ]);
    }

    public function motif(DetailCommande $detail, Request $request){

        // Le motif n'était vérifié que par l'attribut « required » du champ,
        // c'est-à-dire par le navigateur : une requête envoyée directement
        // créait une demande de retour au motif VIDE, impossible à instruire.
        $request->validate([
            'motif' => 'required|string|min:15|max:1000',
        ], [
            'motif.required' => 'Veuillez indiquer le motif de votre retour.',
            'motif.min'      => 'Décrivez le problème en quelques mots (15 caractères au minimum).',
            'motif.max'      => 'Le motif ne doit pas dépasser 1000 caractères.',
        ]);

        $client = Client::where('user_id',Auth::user()->id)->first();

        if (!$client) {
            return redirect()->route('client.monCompte')
                ->with('error', "Votre fiche client est introuvable. Contactez-nous pour la régulariser.");
        }

        // La ligne de commande doit appartenir au client connecté. Rien ne le
        // vérifiait : l'identifiant venant de l'URL, n'importe quel client
        // connecté pouvait déposer un retour sur la commande d'un autre.
        if (optional($detail->commande)->client_id !== $client->id) {
            return redirect()->route('client.retourProduitPage')
                ->with('error', "Ce produit ne figure pas dans vos commandes.");
        }

        // Une seule demande VIVANTE par ligne : le formulaire renvoyé, ou un
        // double clic, en créait autant de copies sans que rien ne l'indique.
        //
        // Une demande REFUSÉE (statut 3) ne bloque pas : le client peut en
        // déposer une nouvelle, avec un élément qu'il n'avait pas donné la
        // première fois. Un refus n'est pas une fin de non-recevoir définitive.
        if ($detail->retour && (int) $detail->retour->statut !== 3) {
            $etat = (int) $detail->retour->statut === 2 ? 'déjà approuvée' : "en cours d'examen";
            return redirect()->route('client.retourProduitPage')
                ->with('success', "Une demande de retour existe déjà pour ce produit ; elle est $etat.");
        }

        // user_id n'était PAS renseigné alors que la colonne l'exige : sur un
        // serveur en mode SQL strict, l'enregistrement échouait purement et
        // simplement, et le client recevait une page d'erreur. Le comportement
        // dépendait donc de la configuration de la base, pas du code.
        $dataRetour = [
            'motif' => $request->motif,
            'client_id' => $client->id,
            'detail_commande_id' => $detail->id,
            'user_id' => Auth::user()->id,
            // Date de la demande de retour. Sans elle, la colonne héritait de la
            // valeur par défaut figée du schéma, comme date_commande.
            'date_retour' => now()->toDateString(),
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

        // Pagination réelle : la vue affichait des numéros de page figés (1 à 6)
        // qui ne menaient nulle part, et chargeait tous les articles d'un coup.
        // withCount : le nombre de commentaires publiés est affiché sur chaque
        // vignette, sans requête supplémentaire par article.
        $blogs = blog::where('publie', 1)
            ->withCount(['commentairesPublies as nb_commentaires'])
            ->orderByDesc('created_at')
            ->paginate(3); // lot 109 : trois articles par page, le reste en pagination

        // 'commande' n'était utilisé que par une variable morte de la vue, mais
        // Commande::first() renvoie null sur une base sans commande : la page
        // tombait alors en erreur. La variable a disparu des deux côtés.
        return view('client.blogPage',[
            'produits' => Produit::all(),
            'client' => Auth::user() ? Client::where('user_id',Auth::user()->id)->first() : new Client,
            'categories' => Categorie::all(),
            'blogs' => $blogs
        ]);
    }

    public function detailBlog($id){

        // find() seul renvoyait null sur un identifiant inconnu ou sur un article
        // retiré du site, et la vue tombait en erreur 500 dès la première ligne.
        // On ne sert ici que ce qui est réellement publié.
        $blog = blog::where('publie', 1)->find($id);

        if ($blog == null) {
            return redirect()->route('client.blog')
                ->with('fail', "Cet article n'est plus disponible.");
        }

        // Compteur de lectures : la vue l'affiche depuis toujours mais personne
        // ne l'incrémentait, il restait donc vide.
        // COALESCE est indispensable : la colonne est nulle sur tous les articles
        // existants, et « vu + 1 » vaut NULL en SQL — le compteur serait resté
        // bloqué à vide indéfiniment. Le calcul est fait par la base, sans
        // écraser une lecture concurrente.
        blog::whereKey($blog->id)->update([
            'vu' => \Illuminate\Support\Facades\DB::raw('COALESCE(`vu`, 0) + 1')
        ]);
        $blog->refresh();

        // Seuls les commentaires validés par la modération sont visibles.
        $commentaires = $blog->commentairesPublies()->with('client')->latest()->get();

        // Le client connecté doit savoir que son propre commentaire attend
        // encore une validation, sinon il croit que l'envoi a échoué.
        $monCommentaireEnAttente = null;
        $clientConnecte = Auth::check() ? Client::where('user_id', Auth::id())->first() : null;

        if ($clientConnecte) {
            $monCommentaireEnAttente = blog_commentaire::where('blog_id', $blog->id)
                ->where('client_id', $clientConnecte->id)
                ->where('statut', blog_commentaire::EN_ATTENTE)
                ->latest()
                ->first();
        }

        return view('client.blogDetail',[
            'blog' => $blog,
            'commentaires' => $commentaires,
            'monCommentaireEnAttente' => $monCommentaireEnAttente,
            // Quelques autres articles, pour ne pas laisser le lecteur sans suite.
            'autresBlogs' => blog::where('publie', 1)->where('id', '!=', $blog->id)
                ->orderByDesc('created_at')->take(3)->get(),
            'produits' => Produit::all(),
            'client' => Auth::user() ? Client::where('user_id',Auth::user()->id)->first() : new Client,
            'categories' => Categorie::all(),
        ]);
    }

    public function commentaireEtNote(Request $request,$id){

        // Aucun contrôle n'était fait : on pouvait enregistrer un commentaire
        // vide, une note de 900, ou commenter un article inexistant.
        $donnees = $request->validate([
            'commentaire' => ['required','string','min:2','max:2000'],
            'note'        => ['nullable','integer','min:1','max:5'],
        ],[
            'commentaire.required' => 'Merci de saisir votre commentaire.',
            'commentaire.min'      => 'Votre commentaire est trop court.',
            'commentaire.max'      => 'Votre commentaire ne doit pas dépasser 2000 caractères.',
            'note.integer'         => 'La note doit être un nombre entier.',
            'note.min'             => 'La note doit être comprise entre 1 et 5.',
            'note.max'             => 'La note doit être comprise entre 1 et 5.',
        ]);

        $blog = blog::where('publie', 1)->find($id);

        if ($blog == null) {
            return redirect()->route('client.blog')
                ->with('fail', "Cet article n'est plus disponible.");
        }

        // Le middleware 'auth' garantit un utilisateur connecté, mais pas qu'il
        // possède une fiche client : sans elle, client_id partait à null et
        // l'insertion échouait sur la contrainte de clé étrangère.
        $clientId = Client::where('user_id', Auth::id())->value('id');

        if ($clientId == null) {
            return redirect()->back()
                ->with('fail', "Votre compte client doit être complété avant de pouvoir commenter.");
        }

        blog_commentaire::create([
            'note'        => $donnees['note'] ?? null,
            'commentaire' => $donnees['commentaire'],
            'client_id'   => $clientId,
            'blog_id'     => $blog->id,
            // En attente : un commentaire n'apparaît sur le site qu'une fois
            // validé en back-office.
            'statut'      => blog_commentaire::EN_ATTENTE,
        ]);

        return redirect()->back()
            ->with('success','Merci ! Votre commentaire a bien été envoyé. Il sera visible après validation.');
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

        // LE MONTANT QUI DÉCIDE DU PLAFOND, c'est celui qu'on paiera : le TTC
        // livraison comprise, exactement la somme que la page affiche en face
        // de « Montant TTC ». Comparer le HT laisserait passer une commande de
        // 1 900 000 HT, soit 2 254 000 à régler.
        $tvaClient = Client::tva($client);
        $montantAPayer = $total + $total * $tvaClient + (session('0')['cout_livraison'] ?? 0);

        return view('client.modePaiement',[
            // 'devis' => $devis,
            'produits' => Produit::all(),
            'pays' => Pays::all(),
            'villes' => Ville::all(),
            'client' => Auth::user() ?  Client::where('user_id',Auth::user()->id)->first(): new Client,
            'categories' => Categorie::all(),
            // Au-delà du plafond, le menu ne propose plus que le règlement en
            // agence : inutile d'offrir un mode que la suite refusera.
            'modes'=> ModePaiement::listePourClient($montantAPayer),
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
        // La TVA sur le transport (point 5) suit le taux du client, comme sa
        // marchandise ; Help::tvaSurTransport rend 0 tant que la case du
        // paramétrage n'est pas cochée.
        $tvaTransport = Help::tvaTransportPour($client, (float) $livraison['cout_livraison']);

        array_push($arr, [
            'ville' => $request->ville,
            'infoSup' => $request->infoSup,
            'long' => $request->long,
            'lat' => $request->lat,
            'montantHT' => Cart::total(),
            'tva' => Cart::total() * Client::tva($client),
            // montantTTC = TOUT ce qui est dû : marchandise + TVA + transport
            // + TVA transport. Les recalculs (remise, points, location)
            // écrivent la même chose, et le montant remis à la passerelle
            // se lit ici, sans rien lui rajouter.
            'montantTTC' => Cart::total() + (Cart::total() * Client::tva($client))
                + (float) $livraison['cout_livraison'] + $tvaTransport,
            'km' => $livraison['km'],
            'cout_livraison' => $livraison['cout_livraison'],
            'tva_transport' => $tvaTransport,
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
            'modes'=> ModePaiement::listePourClient(),
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

            // « TERMINEE » en toutes lettres : etat_commande est un ENUM, où un
            // entier désigne la POSITION de la valeur. « 3 » tombe juste
            // aujourd'hui, mais l'énumération a déjà été étendue une fois cette
            // semaine — une valeur insérée ailleurs qu'à la fin changerait le
            // sens de cette ligne sans provoquer la moindre erreur.
            $commande->update([
                'etat_commande' => Help::$COMMANDE_TERMINE
            ]);

        }



        // La route s'appelle 'client.validationLivraisonPage' (il n'a jamais existé de
        // route 'client.validationLivraisonPageGet') : le client validait son produit,
        // l'enregistrement passait, puis la redirection provoquait une erreur 500.
        // Le paramètre est l'objet Commande (liaison sur l'id), pas le numéro.
        return redirect()->route('client.validationLivraisonPage',$commande)->with('livree','Livraison vallidée');
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


        // Même règle que la boutique : on montre le prix qui sera facturé.
        Produit::alignerPrixAffiche($produits);
        return view('client.index', array_merge($this->blocsMisEnAvant(), [
            'categories' => Categorie::all(),
            'produits' => $produits,
            'client' => $client,
            'prixPerso' => $prixPerso,
            // Diapositives du carrousel : elles étaient écrites en dur dans la vue,
            // elles se gèrent désormais depuis le back-office. Seules celles dont le
            // statut est actif remontent, dans l'ordre choisi (Slide::liste()).
            'slides' => Slide::liste(),
        ]));
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

        // Le catalogue de location montrait `prix_moyen` pendant que le panier
        // facturait le prix fournisseur le plus bas : la bétonnière annoncée
        // 20 000 arrivait au panier à 100.
        Produit::alignerPrixAffiche($produits);

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
                // LE CHOIX DU CLIENT DOIT ÊTRE ENREGISTRÉ.
                //
                // Il était lu ici pour calculer le coût, puis PERDU : le flux
                // des ventes le range dans la session (infoLivraison), celui
                // des locations ne le rangeait nulle part. La création de la
                // location retombait donc sur son repli « livrable », et une
                // location « Retrait sur place » se présentait au gestionnaire
                // comme une livraison. Il affectait un livreur, et la course
                // partait SANS adresse — « Lieu : null » sur le mobile livreur,
                // et une distance de 0 km pour sa rémunération.
                'estLivrable' => 'oui',
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
                'estLivrable' => 'non',
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
        $tab['tva']           = round($htNet * $taux);
        $tab['tva_transport'] = Help::tvaTransportPour(Auth::user()?->client, (float) ($tab['cout_livraison'] ?? 0));
        $tab['montantTTC']    = round($htNet + $tab['tva'] + ($tab['cout_livraison'] ?? 0) + $tab['tva_transport']);
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
            'modes' => ModePaiement::listePourClient(),
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
            // La TVA sur le transport ne bouge pas avec la remise : celle du
            // devis s'il y en a un, sinon celle posée à l'étape adresse.
            $data['tva_transport'] = $devis->id
                ? (float) ($devis->tva_transport ?? 0)
                : (float) ($data['tva_transport'] ?? Help::tvaTransportPour(Auth::user()->client, (float) $cout_livraison));
            $data['montantTTC'] = $montantTotal + $cout_livraison + $tva + $data['tva_transport'];
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

        // PLANCHER DE PAIEMENT.
        //
        // Les points pouvaient couvrir la commande ENTIÈRE : sans livraison, le
        // total à payer tombait à ZÉRO, et la commande devenait une impasse — la
        // passerelle appelée avec 0, l'encaissement au guichet refusant le montant
        // nul (`min:1`), le virement supposant un justificatif de 0. Ils ne
        // réduisent plus le total au-delà du minimum paramétré ; le reliquat reste
        // au compte du client pour la commande suivante.
        //
        // `Client::tva()` rend un TAUX FRACTIONNAIRE (0,18) ; l'aide attend un
        // pourcentage.
        $remisePromo = $montantAEnlever;
        $pointsRetenus = (int) Help::pointsUtilisables(
            (float) $total,
            (float) $remisePromo,
            (float) Client::tva($client) * 100,
            (float) $cout_livraison,
            (float) $config->montant_point,
            (float) $points,
            (float) ($client->point ?? 0)
        );

        // Valeur des points fidélité réellement retenus.
        $montantAEnlever += $pointsRetenus * $config->montant_point;

        // Plafond : la remise (promo + points) ne peut dépasser le HT marchandise,
        // sinon HT net / TVA / TTC deviendraient négatifs.
        if ($montantAEnlever > $total) {
            $montantAEnlever = $total;
        }

        // HT net après toutes les remises.
        $montantTotal = $total - $montantAEnlever;

        session(['remise' => $montantAEnlever]);
        session()->put([
            // Ce sont les points RETENUS qui seront débités à la commande, pas
            // ceux demandés : les deux doivent être la même valeur, sinon le
            // client perdrait des points sans en voir l'effet.
            'point_reduc' => $pointsRetenus,
            // 'reduc' => $config
        ]);

        // TVA sur le HT net + TTC = HT net + livraison + TVA (cohérent avec le code promo).
        $tva = $montantTotal * Client::tva($client);
        $data['tva'] = $tva;
        $data['tva_transport'] = ($devis && $devis->id)
            ? (float) ($devis->tva_transport ?? 0)
            : (float) ($data['tva_transport'] ?? Help::tvaTransportPour($client, (float) $cout_livraison));
        $data['montantTTC'] = $montantTotal + $cout_livraison + $tva + $data['tva_transport'];
        session()->put([
            '0' => $data
        ]);

        return response()->json([
            'statut' => 1,
            'total' => $data['montantTTC'],
            'tva' => $tva,
            'pointsRetenus' => $pointsRetenus,
            'message' => $pointsRetenus < $points
                ? "Seuls {$pointsRetenus} point(s) ont été appliqués : une commande "
                  . "ne peut pas descendre en dessous de "
                  . number_format((float) $config->montant_minimum_a_payer, 0, ',', ' ')
                  . " F. Vos autres points restent disponibles."
                : null,
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

        $client = Help::clientValide() ;

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
        // Ligne absente du panier (page rouverte après expiration de la session) :
        // Cart::get() renvoyait null et ->qty faisait tomber la page en erreur 500.
        $ligne = Cart::get($rowId);
        if(!$ligne){
            return redirect()->route('client.panier')->with('fail','Cet article n\'est plus dans votre panier');
        }
        Cart::update($rowId, $ligne->qty + 1);

        return redirect()->route('client.panier')->with('ok','La quantité de l\'article a été mis à jour');
    }

    /**
     * Diminuer d'une unité la quantité d'une ligne du panier.
     *
     * Cette méthode n'existait PAS alors que la route 'client.decremente' et le
     * bouton « − » du panier y renvoyaient : le clic produisait systématiquement
     * une erreur 500 (« Method decremente does not exist »). Symétrique de
     * incremente(), avec un plancher à 1 (la suppression se fait par la croix).
     */
    public function decremente($rowId){
        $ligne = Cart::get($rowId);
        if(!$ligne){
            return redirect()->route('client.panier')->with('fail','Cet article n\'est plus dans votre panier');
        }

        $nouvelleQte = max(1, $ligne->qty - 1);
        Cart::update($rowId, $nouvelleQte);

        return redirect()->route('client.panier')->with('ok','La quantité de l\'article a été mis à jour');
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
                'libelle_article' => "Paiement DALAKOUN",
                'quantite' => 1,
                'montant' => ceil($total),
                'lib_order' => "Paiement commande de produit DALAKOUN",
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
            // Route réellement déclarée : 'devis.editDevis' (modification-de-devis-{devis}).
            // 'client.editDevis' n'existe pas -> erreur 500 en mettant à jour le panier
            // pendant la modification d'un devis.
            return redirect()->route('devis.editDevis',session('devisAModifier'))->with('ok','Le panier a été mis à jour');
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

        // État écrit en toutes lettres, jamais par son rang dans l'ENUM.
        $commande->update([
            'etat_commande' => Help::$COMMANDE_TERMINE
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

        // Même règle que l'accueil et le panier : le prix montré est celui qui
        // sera facturé.
        Produit::alignerPrixAffiche($produits);

        return view('client.search-produit',[
            'produits' => $produits,
            'client' => $client,
            'categories' => categorie::all(),
            'prixPerso' => $prixPerso,
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

                    // Lot 114 : en mode « site en construction », la connexion mène à l'accueil.
                    if (\App\Models\Configuration::siteEnConstruction()) {
                        return redirect()->route('client.index');
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
            'modes'=> ModePaiement::listePourClient(),
            'total' => $total,
            'prixPerso' => $prixPerso,
        ]);
    }

    /**
     * Reprend une inscription commencée mais jamais confirmée.
     *
     * L'inscription depuis l'application mobile crée le compte et la fiche client
     * en statut inactif, puis attend la saisie d'un code envoyé par e-mail. Tant
     * que le client ne l'a pas saisi, son adresse est prise sans qu'il existe de
     * compte utilisable.
     *
     * Le formulaire web opposait alors « Cet Email est déjà utilisé » et s'arrêtait
     * là : une impasse, pour quelqu'un qui essayait précisément de finir ce qu'il
     * avait commencé. L'application mobile, elle, renvoie un code dans ce cas.
     *
     * On fait pareil : un nouveau code part, et le client est conduit à l'écran de
     * saisie. Un compte réellement ACTIF continue, lui, d'être refusé — l'adresse
     * appartient bien à quelqu'un.
     *
     * @return \Illuminate\Http\RedirectResponse|null  null si rien à reprendre
     */
    private function reprendreInscriptionEnAttente(?User $user)
    {
        if (!$user) {
            return null;
        }

        $client = Client::where('user_id', $user->id)->first();

        $enAttente = (int) $user->statut === (int) Help::$STATUT_INACTIF
            || ($client && (int) $client->statut === (int) Help::$STATUT_INACTIF);

        if (!$enAttente) {
            return null;
        }

        $token = Help::getNumberToken(4);
        $user->update(['token' => $token]);

        // Le renvoi emprunte les deux canaux, comme l'inscription : c'est
        // justement quand le courriel n'est pas arrivé que le client redemande
        // un code.
        \App\Services\WhatsAppService::envoyerCode($user->contact, $token);

        // Envoi non bloquant : si le SMTP tombe, on ne renvoie pas le client sur une
        // erreur 500 après lui avoir promis un code. On le lui dit.
        try {
            Mail::send(new confirmClient($client?->nom ?: $user->nom_prenoms, $user->email, $token));
        } catch (\Throwable $e) {
            \Log::error("Echec envoi du code de reprise d'inscription à {$user->email} : " . $e->getMessage());

            return redirect()->route('client.register')->withInput()->with(
                'existEmail',
                "Votre inscription a déjà été commencée avec cette adresse mais n'a jamais "
                . "été confirmée. L'envoi du code vient d'échouer : contactez-nous pour "
                . "finaliser votre compte."
            );
        }

        return redirect()->route('client.pageToken', ['email' => $user->email])->with(
            'success',
            "Votre inscription avait été commencée sans être confirmée. Un nouveau code "
            . "vient d'être envoyé à {$user->email} : saisissez-le ci-dessous pour activer "
            . "votre compte."
        );
    }

    public function register(Request $request){

        // dd($request->all());
        $userEmail = User::where('email',$request->email)->orWhere('login',$request->email)->first();
        if($userEmail){
            if ($reprise = $this->reprendreInscriptionEnAttente($userEmail)) {
                return $reprise;
            }
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
                    // « confirmed » compare avec le champ password_confirmation du
                    // formulaire. Sans lui, une faute de frappe passait : le compte
                    // etait cree avec un mot de passe que le client ne connaissait
                    // pas, et il fallait le reinitialiser avant la premiere commande.
                    "password" => "required|confirmed",
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
                    "password.confirmed" => "Les deux mots de passe ne correspondent pas !",
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
                    // « confirmed » compare avec le champ password_confirmation du
                    // formulaire. Sans lui, une faute de frappe passait : le compte
                    // etait cree avec un mot de passe que le client ne connaissait
                    // pas, et il fallait le reinitialiser avant la premiere commande.
                    "password" => "required|confirmed",
                    "rccm" => "required",
                    "ncc" => "required",
                    "nature_fne" => "nullable|in:B2B,B2G,B2F",
                    // Le regime d'imposition du CLIENT figure sur la facture,
                    // et la DGI l'attend sur une facture entre entreprises.
                    // Il etait lu par FneService depuis toujours, mais aucun
                    // ecran ne permettait de le saisir : la ligne sortait vide.
                    "regime_imposition" => "required|string|" . \App\Support\RegimeImposition::regle(),
                    "dfe" => "required|file|mimes:pdf,jpg,jpeg,png|max:5120",
                    "registre_commerce" => "required|file|mimes:pdf,jpg,jpeg,png|max:5120",
                    "condition" => "required"
                ],[
                    "raisonSociale.required" => "La raison sociale est obligatoire !",
                    "regime_imposition.required" => "Le régime d'imposition est obligatoire : il figure sur vos factures.",
                    "email.required" => "Veuillez remplir ce champs !",
                    "contact1.required" => "Veuillez remplir ce champs !",
                    "contact2.required" => "Veuillez remplir ce champs !",
                    "adresse.required" => "Veuillez remplir ce champs !",
                    "password.required" => "Veuillez remplir ce champs !",
                    "password.confirmed" => "Les deux mots de passe ne correspondent pas !",
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
                if ($reprise = $this->reprendreInscriptionEnAttente($userEmail)) {
                    return $reprise;
                }
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

            // LE NOM DU COMPTE, QUEL QUE SOIT LE PROFIL.
            //
            // Il ne lisait que « raisonSociale » ou « display_name ». Or le
            // formulaire d'inscription ne porte AUCUN champ display_name : un
            // particulier arrivait donc ici avec un nom vide, et la colonne
            // users.nom_prenoms n'accepte pas de valeur nulle. L'inscription
            // partait en erreur 500 — aucun particulier ne pouvait creer de
            // compte depuis le site.
            //
            // On retombe sur le prenom et le nom, qui sont exiges par la
            // validation du particulier : ils existent forcement a ce stade.
            $nomPrenoms = $request->raisonSociale
                ?: ($request->display_name
                    ?: trim(($request->prenom ?? '') . ' ' . ($request->nom ?? '')));

            if ($nomPrenoms === '') {
                return redirect()->route('client.register')
                    ->with('existEmail', "Nous n'avons pas pu lire votre nom. Reprenez le formulaire.")
                    ->withInput();
            }

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
                'regime_imposition' => $request->regime_imposition,
                // La nature de l'organisation pour la DGI (lot 100) : B2B par défaut.
                'nature_fne' => $request->type == 2
                    ? (in_array($request->nature_fne, ['B2B', 'B2G', 'B2F'], true) ? $request->nature_fne : 'B2B')
                    : null,
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

            // LE CODE PART AUSSI PAR WHATSAPP.
            //
            // Beaucoup de clients n'ont pas d'adresse consultée régulièrement,
            // le message part parfois en indésirables, et le serveur d'envoi
            // peut être lent. Un code jamais reçu, c'est un compte jamais
            // activé — donc une vente perdue.
            //
            // Les deux partent : le client se sert de celui qui lui arrive en
            // premier. L'envoi ne lève jamais et ne fait rien tant qu'aucun
            // compte fournisseur n'est configuré.
            $codeEnvoyeParWhatsapp = \App\Services\WhatsAppService::envoyerCode(
                $request->contact1,
                $numeroToken
            );

            session()->forget([
                'type',
                'ville',
                'infoSup',
                'lat',
                'long',
                'mode'
            ]);

            // return view('client.pageConfirmationToken');

            return redirect()->route('client.pageToken', ['email' => $request->email])
                ->with('success', $codeEnvoyeParWhatsapp
                    ? 'Succès. Votre code de confirmation vous a été envoyé par e-mail et par WhatsApp.'
                    : 'Succès, veuillez consulter votre boite email pour confirmer votre inscription');
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

                    // Une inscription née sur l'application mobile laisse le compte ET
                    // la fiche client inactifs jusqu'à la saisie du code. Cette page ne
                    // faisait qu'effacer le jeton — parce qu'une inscription web naît
                    // déjà active — et le client restait bloqué après avoir pourtant
                    // saisi le bon code.
                    //
                    // On active les deux, exactement comme le fait la validation du code
                    // côté application (UtilisateurController::557 de l'API).
                    if ((int) $user->statut === (int) Help::$STATUT_INACTIF) {
                        $user->update(['statut' => Help::$STATUT_ACTIF]);
                    }

                    $clientDuCompte = Client::where('user_id', $user->id)->first();
                    if ($clientDuCompte && (int) $clientDuCompte->statut === (int) Help::$STATUT_INACTIF) {
                        $clientDuCompte->update(['statut' => Help::$STATUT_ACTIF]);
                    }

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
            // Photo de profil (lot 105, 17/09/2026).
            "photo" => "nullable|image|mimes:jpg,jpeg,png,webp|max:2048",

        ],[
            'photo.image' => "La photo doit être une image.",
            'photo.mimes' => "La photo doit être au format JPG, PNG ou WEBP.",
            'photo.max' => "La photo ne doit pas dépasser 2 Mo.",
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
        // LA PHOTO DE PROFIL (lot 105, 17/09/2026) : même fichier et même chemin
        // que l'application mobile (imageUser/{id}.png dans le stockage public du
        // site), pour qu'une photo changée d'un côté se voie de l'autre.
        if ($request->hasFile('photo')) {
            $cheminPhoto = 'imageUser/' . Auth::id() . '.png';
            \Illuminate\Support\Facades\Storage::disk('public')->put($cheminPhoto, file_get_contents($request->file('photo')->getRealPath()));
            $dataUser['photo'] = $cheminPhoto;
        }
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
            ...(in_array($request->nature_fne, ['B2B', 'B2G', 'B2F'], true) ? ['nature_fne' => $request->nature_fne] : []),
            // Le formulaire de modification ne porte pas le régime : sans
            // cette garde, chaque enregistrement de la fiche l'effaçait.
            ...($request->filled('regime_imposition')
                ? ['regime_imposition' => \App\Support\RegimeImposition::code($request->regime_imposition) ?? $request->regime_imposition]
                : []),
            // Le champ du formulaire s'appelle « rccm ». La faute de frappe
            // « rmmc » lisait un champ inexistant : chaque modification de la
            // fiche remplaçait donc le RCCM de l'entreprise par une valeur
            // vide, sans que rien ne le signale.
            'rccm_clt' => $request->rccm
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

        // La page affiche le produit, sa quantité, son prix et le numéro de
        // commande à partir d'un simple identifiant d'URL : sans ce contrôle,
        // n'importe quel client connecté lisait le détail des commandes des
        // autres en changeant le numéro dans la barre d'adresse.
        $leClient = Client::where('user_id', Auth::user()->id)->first();
        if (!$leClient || optional($detail->commande)->client_id !== $leClient->id) {
            return redirect()->route('client.ticketSAV')
                ->with('error', "Ce produit ne figure pas dans vos commandes.");
        }

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

        // Bornes alignées sur celles du formulaire : sans elles, une requête
        // envoyée directement passait avec un message d'un seul caractère.
        $request->validate([
            'objet'   => 'required|string|max:255',
            'message' => 'required|string|min:15|max:2000',
        ], [
            'objet.required'   => "Veuillez préciser l'objet du ticket.",
            'objet.max'        => "L'objet ne doit pas dépasser 255 caractères.",
            'message.required' => 'Veuillez décrire votre demande.',
            'message.min'      => 'Décrivez le problème en quelques mots (15 caractères au minimum).',
            'message.max'      => 'La description ne doit pas dépasser 2000 caractères.',
        ]);

        // Mêmes garde-fous que sur la demande de retour, et pour les mêmes
        // raisons : l'identifiant de la ligne vient de l'URL.
        if (optional($detail->commande)->client_id !== $client->id) {
            return redirect()->route('client.ticketSAV')
                ->with('error', "Ce produit ne figure pas dans vos commandes.");
        }

        // Le service après-vente porte sur une marchandise reçue : la page
        // l'annonce, mais rien ne l'imposait au traitement.
        if ($detail->etat_livraison !== 'LIVREE') {
            return redirect()->route('client.ticketSAV')
                ->with('error', "Un ticket ne peut être ouvert que sur un produit qui vous a été livré.");
        }

        if ($detail->ticket) {
            return redirect()->route('client.mesTicketsSAV')
                ->with('success', "Un ticket existe déjà pour ce produit ; suivez-le ci-dessous.");
        }

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

        // La demande d'annulation est chargée avec les commandes : sans cela,
        // la vue interrogerait la base une fois par ligne du tableau.
        $commandes = Commande::with('derniereDemandeAnnulation')
            ->where('client_id', $client->id)->where('statut', Help::$STATUT_ACTIF)->orderBy('created_at','desc')->get();
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
            'types' => TypeVehicule::all(),
            // Avance disponible (point 19) et ce qu'elle implique pour le client.
            'soldeAvance' => \App\Services\Avances::soldeDisponible($client),
            'mentionAvance' => \App\Services\Avances::MENTION,
            // Ce que le client doit encore régler en agence (10/09/2026).
            'creditsEnAgence' => \App\Services\Avances::creditsEnAgenceDetail($client),
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
                    // $nomPrenom = $user->client->display_name;


            $config = Configuration::first();

            $client = Client::where('user_id',Auth::user()->id)->first();

            // Adresse de livraison (un enregistrement d'adresse est inoffensif s'il n'aboutit pas).
            if (session('0')['ville'] ?? null) {
                $ville = Ville::where('id', session('0')['ville'] ?? null)->first();

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
                    + (session('0')['tva'] ?? 0) + (session('0')['cout_livraison'] ?? 0)
                    + (session('0')['tva_transport'] ?? 0)));
            // AIRSI (10/09/2026) : HT net de remise + TVA ; compté dans le TTC dû.
            $airsiLocation = \Help::airsiPour($client,
                max(0, (float) session('totalLocation') - round(session('remise') ?? 0)) + (float) (session('0')['tva'] ?? 0),
                max(0, (float) session('totalLocation') - round(session('remise') ?? 0)), (float) Client::tva($client),
                (float) (session('0')['cout_livraison'] ?? 0),
                (float) (session('0')['tva_transport'] ?? 0) > 0 ? (float) Client::tvaTransport($client) : 0.0);
            $montantTTC += $airsiLocation;

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
                    // Le meme generateur que le mobile : un seul format de
                    // numero de location, dictable au telephone. Le brouillon
                    // porte deja son numero, donc le repli du modele ne
                    // s'appliquerait pas ici.
                    'numero'                => \Help::genererNumeroUnique('location'),
                    'client_id'             => $client->id,
                    'mode_paiement_id'      => session('mode_paiement'),
                    'adresse_livraison_id'  => $adresseId,
                    'montant_total'         => $total,
                    'cout_livraison_client' => session('0')['cout_livraison'] ?? 0,
                    'tva_transport'         => (float) (session('0')['tva_transport'] ?? 0),
                    'airsi'                 => $airsiLocation,
                    // Le choix « Me faire livrer / Retrait sur place » doit voyager dans le
                    // brouillon : la location est créée par le callback de paiement, qui est
                    // un appel serveur-à-serveur SANS session. Repli sur livrable.
                    'est_livrable'          => (session('0')['estLivrable'] ?? 'oui') == 'oui' ? 1 : 0,
                    'remise'                => round(session('remise') ?? 0),
                ],
                'details' => $details,
                'tva'     => intVal(round(session('0')['tva'] ?? (session('totalLocation') * Client::tva($client)))),
            ];

            // Hors ligne / en ligne selon le flag en_ligne du mode (et non id=1) :
            // « Paiement en agence » (en_ligne=0) reste un paiement hors ligne.
            // SEUL COMPTE LE CARACTÈRE DU MODE, exactement comme pour une commande :
            // « Le client à terme n'est plus exclu : s'il a choisi un mode en ligne,
            // il règle en ligne. » Les locations gardaient l'ancienne règle et
            // écartaient ces clients de la passerelle sans le leur dire.
            $modeLocObj = session('mode_paiement') ? ModePaiement::find(session('mode_paiement')) : null;

            // LE PLAFOND DU PAIEMENT EN LIGNE — ON LE DIT, ON NE L'ESCAMOTE PAS.
            //
            // Le test était ici, écrit à la main (`$montantTTC < 2000000`), et il
            // se contentait de SAUTER la passerelle : la location s'enregistrait
            // comme si de rien n'était, sans qu'un franc soit encaissé et sans
            // que le client comprenne pourquoi. Il rangeait de surcroît le
            // montant EXACT de 2 000 000 du mauvais côté, quand le message
            // affiché dit « supérieur à ».
            //
            // Le refus est posé avant la création de la location.
            if ($modeLocObj
                && !\App\Support\PlafondPaiementEnLigne::modeAutorise($modeLocObj, $montantTTC)) {
                return redirect()->route('client.choixDateProduitLocationTraitement')
                    ->with('error', \App\Support\PlafondPaiementEnLigne::refus());
            }

            $online = ($modeLocObj && $modeLocObj->en_ligne == 1);

            // Message d'échec de la passerelle, s'il y en a un : il doit atteindre
            // le client. Sans lui, choisir « Wave » aboutissait à une location
            // enregistrée SANS que rien n'explique pourquoi la passerelle ne
            // s'était pas ouverte — ni à l'écran, ni dans les journaux.
            $echecPaiementEnLigne = null;

            if ($online && !config('paysecure.url')) {
                // Passerelle non configurée : le circuit des commandes le dit au
                // client plutôt que de tenter un appel voué à l'échec. Les
                // locations partaient sans rien vérifier.
                $online = false;
                $echecPaiementEnLigne = "Le paiement en ligne n'est pas configuré sur ce serveur.";

                \Log::warning('enregistrementLocation - passerelle non configurée', [
                    'client_id' => $client->id,
                    'mode'      => $modeLocObj?->libelle,
                ]);
            }

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
                        'libelle_article' => "Paiement DALAKOUN",
                        'quantite'      => 1,
                        'montant'       => $montantTTC,
                        'lib_order'     => "Paiement location DALAKOUN",
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
                    // Panier conservé jusqu'à confirmation, comme pour une commande.
                    // Le cas est ici plus net encore : la location n'existe même pas
                    // tant que le paiement n'est pas confirmé. Le client qui fermait
                    // la page de paiement perdait donc son panier ET n'avait aucune
                    // location en contrepartie.
                    session()->put('panier_lie_au_paiement', $codePaiement);
                    return Redirect::away($ret['message']);   // location créée à la confirmation
                }

                // ÉCHEC D'INITIATION. La location est tout de même créée — les dates
                // et le panier ne sont pas perdus — mais elle n'est PAS payée, et le
                // client doit l'apprendre. Le circuit des commandes le dit depuis
                // toujours ; celui des locations se taisait, et la location
                // paraissait confirmée alors qu'aucun franc n'avait été encaissé.
                $echecPaiementEnLigne = $ret['message'] ?? 'Erreur inconnue';

                // Journalisé comme pour une commande : sans trace, la cause du refus
                // de la passerelle — clé, plafond, injoignable — reste introuvable.
                \Log::warning('enregistrementLocation - initierPaiement échoué', [
                    'code'      => $ret['code'] ?? null,
                    'message'   => $echecPaiementEnLigne,
                    'client_id' => $client->id,
                    'mode'      => $modeLocObj?->libelle,
                    'montant'   => $montantTTC,
                ]);
            }

            // PLAFOND DE CRÉDIT — LA VOIE QUI Y ÉCHAPPAIT.
            //
            // Constaté en production le 29/08/2026 : le client 55, à terme
            // depuis 00 h 25 avec un plafond de 5 000 F, a passé trois
            // locations à 00 h 30, 00 h 35 et 00 h 42 — 22 000, 104 500 et
            // 104 500 F. Aucune n'a été refusée.
            //
            // Le contrôle existait pourtant sur les autres voies : le devis, la
            // vente en agence, et l'API mobile. Il manquait ICI, et c'est
            // précisément la voie des règlements HORS LIGNE — donc celle des
            // clients à terme, comme le dit le commentaire ci-dessous. Une
            // quatrième copie du contrôle existait bien dans
            // `enregistrementDeLocation`, mais cette méthode n'est appelée de
            // nulle part : elle donnait l'illusion d'une protection.
            //
            // Le montant retenu est `$montantTTC`, celui réellement dû — le
            // même que l'écran de paiement affiche au client.
            //
            // Un règlement EN LIGNE n'a pas besoin de ce contrôle : il n'engage
            // aucun crédit, l'argent entre avant la location. C'est pourquoi il
            // n'est pas posé dans le rappel de la passerelle.
            //
            // Une avance disponible couvre d'abord la location : seul le
            // reliquat engage le crédit — même règle que la vente (point 19).
            // Un mode en ligne ne touche pas à l'avance.
            $montantACredit = $online
                ? (float) $montantTTC
                : max(0, (float) $montantTTC - \App\Services\Avances::soldeDisponible($client));
            if ($refus = $this->refusPlafondCredit($client, $montantACredit)) {
                return redirect()->route('client.monPanier')->with('error', $refus);
            }

            // Création DIRECTE : modes hors-ligne (à terme, paiement=1, > 2M) OU échec init en ligne.
            $location = Location::creerDepuisDonnees($payload, 1);
            Cart::destroy();

            // AVANCE DU CLIENT (10/09/2026) : une location réglée « en agence »
            // s'impute d'elle-même sur les avances disponibles, comme une vente.
            // Un mode en ligne n'y touche pas, même si la passerelle a échoué.
            $messageAvance = null;
            if (!$online && $location) {
                $imputationAvance = \App\Services\Avances::imputerSurLocation($location, Auth::id());
                $messageAvance = \App\Services\Avances::messageImputation($imputationAvance, Help::$LOCATION) ?: null;
                $location = Location::find($location->id) ?: $location;
            }

            return view('orders.recapLocation',[
                'location' => $location,
                'config'   => Configuration::first(),
                'echecPaiementEnLigne' => $echecPaiementEnLigne,
                'messageAvance' => $messageAvance,
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

    /**
     * PLAFOND DE CRÉDIT — client à terme.
     *
     * Renvoie le message de refus si la commande dépasse le crédit encore
     * disponible, ou null si elle peut passer.
     *
     * Le plafond accordé à l'approbation n'était opposé à RIEN : il ne servait
     * qu'à l'affichage — liste des clients à terme, page du compte, tableau de
     * bord, e-mail d'accord. Un client plafonné à 5 000 pouvait commander 6 500
     * en paiement différé, et la commande passait.
     *
     * SEULES les commandes prises à crédit consomment le plafond. Un règlement
     * en ligne est encaissé immédiatement : il n'engage aucun crédit, et
     * l'appelant doit alors sauter ce contrôle (cf. panierCommande). C'est à
     * l'appelant d'en décider, car lui seul sait si la passerelle sera
     * réellement sollicitée.
     *
     * Regroupé ici parce que QUATRE méthodes créent une commande —
     * panierCommande, validationReference, devisCommande et recapCommande. Ne
     * garder le contrôle qu'à un seul endroit laissait trois portes ouvertes.
     */
    private function refusPlafondCredit(?Client $client, float $montantCommande): ?string
    {
        if (!$client || !$client->client_a_terme) {
            return null;
        }

        $disponible = $client->plafondDisponible();

        // null = aucun plafond accordé : on ne fait pas respecter une limite qui
        // n'existe pas, et on ne bloque pas une commande sur une donnée absente.
        if ($disponible === null || $montantCommande <= $disponible) {
            return null;
        }

        $format = fn ($m) => number_format($m, 0, ',', ' ') . ' FCFA';

        return sprintf(
            "Cette commande de %s dépasse votre crédit disponible. "
            . "Plafond accordé : %s. Déjà engagé : %s. Reste disponible : %s. "
            . "Réglez une facture en cours ou réduisez votre commande pour continuer.",
            $format($montantCommande),
            $format((float) $client->plafond_credit),
            $format($client->encoursCredit()),
            $format($disponible)
        );
    }

    /**
     * Où renvoyer le client quand sa commande est refusée.
     *
     * SURTOUT PAS back() : le bouton « Valider la commande » de la page de
     * récapitulatif est un LIEN (GET) dont le référent est
     * /recapitulatif-commande-venant-dun-devis-{id}, une route déclarée en POST
     * seulement. back() y renvoyait en GET et produisait un « 405 Method Not
     * Allowed » à la place du message d'erreur.
     *
     * On vise donc une page réellement accessible en GET : l'écran de choix du
     * mode de paiement du devis concerné, où le client peut ajuster sa commande,
     * ou le panier à défaut.
     */
    /**
     * UNE COMMANDE SANS ARTICLE N'EST PAS UNE COMMANDE.
     *
     * Les lignes d'une commande sont RECOPIÉES de son devis. Quand le devis
     * n'en porte aucune, la commande était tout de même enregistrée : avec son
     * montant, son numéro, sa place dans l'espace client et dans l'application
     * — et RIEN dedans. Son écran de détail s'ouvrait blanc, son bon
     * s'imprimait vide, et personne ne pouvait dire ce qui avait été acheté.
     *
     * Un devis se retrouve sans ligne lorsque le panier de SESSION a expiré à
     * l'instant où il a été créé : ses totaux, eux, survivent en session — d'où
     * un montant sans contrepartie.
     *
     * Les TROIS points de création de commande du site passent par ici : ils ne
     * peuvent donc plus diverger.
     */
    /**
     * UNE LOCATION NE SE VEND PAS.
     *
     * Le menu du panier propose « Commander » quel que soit son contenu : il
     * mène droit au parcours de VENTE, même quand le panier ne contient que du
     * matériel de location. La page « Mon panier », elle, aiguille correctement
     * (monPanier() renvoie vers le parcours de location) — mais le raccourci du
     * menu court-circuitait cet aiguillage.
     *
     * Le matériel devenait alors une ligne de COMMANDE. Le client était engagé
     * sur un achat au lieu d'une location : pas de dates, pas de caution, pas
     * de retour de matériel — et l'écran de détail de l'application s'ouvrait
     * blanc, la lecture écartant ces lignes.
     *
     * Constaté sur la commande 286453 : 5 « Mini-pelle de chantier », 495 000 F.
     *
     * L'aiguillage se fait ICI, au serveur : le raccourci du menu n'est pas le
     * seul chemin (lien mis en favori, reprise d'un devis, second onglet).
     */
    private function porteDuMaterielDeLocation(?Devis $devis): bool
    {
        foreach (Cart::content() as $article) {
            if ($article->options->type_affaire === \Help::$LOCATION) {
                return true;
            }
        }

        if (!$devis || !$devis->id) {
            return false;
        }

        return DetailDevis::join('produit', 'produit.id', '=', 'detail_devis.produit_id')
            ->where('detail_devis.devis_id', $devis->id)
            ->where('detail_devis.statut', \Help::$STATUT_ACTIF)
            ->where('produit.type_affaire', \Help::$LOCATION)
            ->exists();
    }

    /**
     * LA COMMANDE EN COURS N'A-T-ELLE PLUS AUCUN ARTICLE ?
     *
     * Deux origines, deux endroits où regarder :
     *
     *   · une commande née d'un DEVIS enregistré : ses articles sont les lignes
     *     du devis ;
     *   · une commande née du PANIER : ses articles sont dans le panier, et
     *     aucun devis n'est créé pour elle. Répondre « sans article » dans ce
     *     cas refuserait toute commande directe.
     */
    private function devisSansArticle(?Devis $devis): bool
    {
        if ($devis && $devis->id) {
            return DetailDevis::where('devis_id', $devis->id)
                ->where('statut', \Help::$STATUT_ACTIF)
                ->count() === 0;
        }

        return Cart::count() === 0;
    }

    private function retourApresRefusCommande(?Devis $devis)
    {
        if ($devis && $devis->id) {
            return redirect()->route('devis.modePaiement', $devis);
        }

        return redirect()->route('client.monPanier');
    }

    /** Montant net d'un devis : HT + TVA + livraison - remise. */
    /**
     * Montant net d'une location au moment où elle est créée depuis le panier.
     *
     * Même formule qu'à la facturation (OrdersController::genererFactureLocation)
     * et que Location::montantAPayer() : HT − remise, puis TVA et livraison. La
     * TVA et la livraison sont lues dans la session, seule source disponible à cet
     * instant — la location n'existe pas encore.
     */
    private function montantNetLocation(float $totalHt, float $remise): float
    {
        $tva = (float) (session('0')['tva']
            ?? ($totalHt * Client::tva(Client::where('user_id', Auth::id())->first())));

        $livraison    = (float) (session('0')['cout_livraison'] ?? 0);
        $tvaTransport = (float) (session('0')['tva_transport'] ?? 0);

        return max(0, $totalHt - $remise) + $tva + $livraison + $tvaTransport;
    }

    /**
     * Montant engagé par un devis, pour le contrôle du plafond de crédit.
     *
     * Délègue au modèle : la colonne `montant` ne veut pas dire la même chose
     * selon que le devis vient du site (HT) ou de l'application (total déjà net).
     * L'addition faite ici comptait donc la TVA deux fois pour un devis mobile,
     * et pouvait refuser une commande qui tenait pourtant dans le plafond.
     */
    private function montantNetDevis(Devis $devis): float
    {
        if ($devis->id) {
            return $devis->montantAPayer();
        }

        // COMMANDE DIRECTE : le devis n'est qu'un porteur, jamais enregistré.
        //
        // montantAPayer() reconstitue le HT depuis les LIGNES du devis — il n'y
        // en a aucune ici, et le montant retenu serait zéro. Le contrôle du
        // plafond de crédit laisserait alors passer n'importe quelle commande
        // à terme. On additionne donc les valeurs que le porteur transporte,
        // avec la même formule : HT + TVA + livraison − remise.
        return max(0, (float) ($devis->montant ?? 0)
            + (float) ($devis->tva ?? 0)
            + (float) ($devis->cout_livraison ?? 0)
            - (float) ($devis->cout_reduction ?? 0));
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
                // 1re requête (double-clic, préchargement du lien GET, refresh…).
                // On renvoie vers la page de CONFIRMATION de la dernière commande du
                // client (qui propose « Continuer vos achats »), et NON vers la liste
                // des commandes.
                if (!$devis->id && Cart::content()->isEmpty()) {
                    $clientCourant = Client::where('user_id', Auth::user()->id)->first();
                    $derniereCommande = $clientCourant
                        ? Commande::where('client_id', $clientCourant->id)->latest('id')->first()
                        : null;
                    if ($derniereCommande) {
                        return redirect()->route('client.commandeValidee', $derniereCommande->numero)
                            ->with('info', 'Votre commande a déjà été enregistrée.');
                    }
                    return redirect()->route('client.commande')
                        ->with('info', 'Votre commande a déjà été enregistrée.');
                }

                // Même garde pour le flux DEVIS, où le panier est vide et ne peut donc
                // rien signaler. « Valider la commande » est un lien GET : retour
                // arrière, rafraîchissement, double-clic ou simple reprise de l'onglet
                // le rejouent. Or commande.numero est UNIQUE et reprend celui du devis
                // — la seconde validation partait en violation de contrainte, donc 500
                // et page blanche, alors que la commande était bel et bien enregistrée.
                if ($devis->id) {
                    $dejaCommande = Commande::withTrashed()
                        ->where(function ($q) use ($devis) {
                            $q->where('devis_id', $devis->id)
                                ->orWhere('numero', $devis->numero);
                        })
                        ->latest('id')
                        ->first();

                    if ($dejaCommande && !$dejaCommande->trashed()) {
                        return redirect()->route('client.commandeValidee', $dejaCommande->numero)
                            ->with('info', 'Votre commande a déjà été enregistrée.');
                    }

                    // Commande supprimée : son numéro occupe toujours l'index unique,
                    // la recréer échouerait. On le dit plutôt que de laisser un 500.
                    if ($dejaCommande) {
                        return redirect()->route('client.commande')->with(
                            'info',
                            "Ce devis a déjà été transformé en commande, et cette "
                            . "commande a été annulée. Contactez-nous pour la rétablir "
                            . "ou passez une nouvelle commande."
                        );
                    }
                }

                // AIGUILLAGE AVANT TOUTE ÉCRITURE.
                //
                // Placé plus bas, il aurait laissé derrière lui un devis créé
                // depuis le panier et un code promo déjà consommé.
                if ($this->porteDuMaterielDeLocation($devis)) {
                    return redirect()->route('client.panierLocation')->with('error',
                        "Ce matériel se LOUE, il ne s'achète pas. Nous vous avons ramené au "
                        . "parcours de location : choisissez vos dates, puis validez.");
                }

                // on initialise les variables qui peuvent changer de valeur selon que nous sommes dans une modification de devis ou pas
                $date_livraison = session('date_livraison');
                $mode_paiement = session('mode_paiement');

                // LE PLAFOND DU PAIEMENT EN LIGNE — AVANT TOUTE ÉCRITURE.
                //
                // Le client lisait « Pour tout montant supérieur à 2 000 000 fcfa
                // le paiement doit se faire par virement bancaire, en agence ou en
                // plusieurs commandes », puis partait quand même vers la passerelle :
                // ce parcours-ci ne vérifiait rien, la ligne était commentée.
                // Constaté le 04/09/2026 sur une commande de 6 502 000 fcfa.
                //
                // Le refus est posé ICI, avant la création du devis et de la
                // commande : plus bas, il aurait laissé derrière lui une affaire
                // que personne n'a demandée.
                // montantTTC porte désormais TOUT ce qui est dû, transport compris
                // (voir infoLivraison) : on ne lui rajoute plus rien.
                $montantDuPlafond = $devis->id
                    ? (($devis->montant + $devis->cout_livraison + $devis->tva + (float) ($devis->tva_transport ?? 0)) - $devis->cout_reduction)
                    : (float) (session('0')['montantTTC'] ?? 0);

                // On ne refuse QUE le mode en ligne au-dessus du plafond. Une
                // commande sans mode enregistré — le règlement en agence, le
                // client à terme — n'a rien à voir avec ce plafond et doit
                // continuer de passer.
                $modeChoisi = $mode_paiement ? ModePaiement::find($mode_paiement) : null;

                if ($modeChoisi
                    && !\App\Support\PlafondPaiementEnLigne::modeAutorise($modeChoisi, $montantDuPlafond)) {
                    return redirect()->route('client.modeDePaiement')
                        ->with('error', \App\Support\PlafondPaiementEnLigne::refus());
                }
                $numero = NULL;
                $remise = 0;
                $type_livraison = session('type_livraison');
                $cout_livraison = 0;
                $montantTva = 0;
                $tvaTransport = 0;


                $user = Auth::user();

                $nomPrenom = $user->client->display_name;



                $etat = Help::listeStatutCommande();

                $client = Client::where('user_id',$user->id)->first();


                if($devis->id == null){

                    // 12/09/2026 : sans l'étape « adresse et livraison » (session '0'),
                    // la page faisait une erreur 500 ; on y renvoie le client.
                    if (!is_array(session('0'))) {
                        return redirect()->route('client.commandeAdresse')
                            ->with('error', "Renseignez d'abord l'adresse et le mode de livraison de votre commande.");
                    }

                    $ville = Ville::where('id', session('0')['ville'] ?? null)->first();
                    $cout_livraison = session('0')? session('0')['cout_livraison'] : 0;
                    $livrable = session('0')['estLivrable'] == 'oui' ? 1 : 0;
                    $montantTva = session('0')['tva'] ?? 0;
                    $tvaTransport = (float) (session('0')['tva_transport'] ?? 0);
                    // dd($date_livraison,$ville,$mode_paiement,$type_livraison,$cout_livraison,$livrable,$montantTva);
                    // dd(session('type'));

                    $adresse_id = null;

                    if ((session('0')['ville'] ?? null) != null) {

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
                        'client_id' => $client->id,
                        'date_livraison' => session('date_livraison'),
                        'adresse_livraison_id' => $adresse_id,
                        'type_livraison_id' => $type_livraison,
                        'mode_paiement_id' =>$mode_paiement,

                        'tva' => $montantTva,
                        'cout_reduction' => session('remise'),
                        'cout_livraison' => session('0')['cout_livraison'],
                        'tva_transport' => $tvaTransport,
                        // AIRSI (10/09/2026) : HT net de remise + TVA.
                        'airsi' => \Help::airsiPour($client, max(0, (float) session('0')['montantHT'] - (float) (session('remise') ?? 0)) + (float) $montantTva,
                            max(0, (float) session('0')['montantHT'] - (float) (session('remise') ?? 0)), (float) Client::tva($client),
                            (float) $cout_livraison, (float) $tvaTransport > 0 ? (float) Client::tvaTransport($client) : 0.0),
                        // 'montant' => session('0')['montantTTC'] - session('0')['tva'], //$totalPlusTva,
                        // 'montant' => session('0')['montantTTC'] , //$totalPlusTva,
                        'montant' => session('0')['montantHT'] , //$totalPlusTva,
                        'montant_ht' => session('0')['montantHT'],
                        'service' => session('type') == 'commande' ? 1 : 2,
                    ];

                    // UNE COMMANDE NE CRÉE PAS DE DEVIS.
                    //
                    // Ce devis était ENREGISTRÉ, uniquement pour porter les
                    // informations du panier jusqu'à la commande. Conséquence :
                    // chaque vente ajoutait une ligne dans « Liste des devis »
                    // et dans « Mes devis » côté client, alors que le client
                    // n'en avait demandé aucun — il existe pour cela un
                    // parcours dédié, « Demander un devis ».
                    //
                    // Il reste donc un simple PORTEUR, en mémoire, jamais écrit
                    // en base. Comme il n'a pas d'identifiant, les branches
                    // « sans devis » déjà présentes plus bas prennent le relais :
                    // les lignes de la commande se construisent directement
                    // depuis le panier, et rien ne va dans la table des devis.
                    //
                    // Le numéro, lui, doit être unique sur la COMMANDE — c'est
                    // désormais sa création qui porte la reprise en cas de
                    // collision (voir Commande::create plus bas).
                    $devis = new Devis($dataDevis);

                    if(session('reduction_id')){
                        $reduction = Reduction::find(session('reduction_id'));

                        // Le code promo est consommé par le CLIENT, pas par un
                        // devis : il n'y en a plus. La colonne reste libre —
                        // elle sert encore au parcours « Demander un devis ».
                        $reduction->update([
                            'est_utilise' => 1,
                            'client_id' => $client->id,
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

                    // Les lignes du devis ne sont plus écrites non plus : celles
                    // de la commande se construisent depuis le panier, plus bas.
                }else{

                    $adresse_id = $devis->adresse_livraison_id;

                    // Repli sur le devis quand la session ne porte plus ces valeurs.
                    // /panier-en-commande{devis} est un simple lien GET : rouvert
                    // depuis l'historique, un favori, un second onglet ou après
                    // expiration de la session, il arrivait ici sans date de
                    // livraison — et l'insertion échouait sur une contrainte NOT
                    // NULL, donc page blanche et 500. Le devis porte déjà ces
                    // informations : on les reprend plutôt que d'échouer.
                    $date_livraison = $date_livraison ?: $devis->date_livraison;
                    $type_livraison = $type_livraison ?: $devis->type_livraison_id;
                    $mode_paiement  = $mode_paiement  ?: $devis->mode_paiement_id;

                    // Remise = celle appliquée par le client sur la page de paiement
                    // (code promo / points, en session) si présente ; sinon la remise
                    // d'origine du devis. Sans ça, la réduction saisie au paiement était
                    // ignorée et le client payait plein tarif.
                    // Les montants du devis sont nullables, ceux de la commande ne le
                    // sont pas. Un devis SANS réduction porte cout_reduction à NULL, et
                    // l'insertion partait en « Column 'remise' cannot be null » : page
                    // blanche, commande perdue, alors que le devis était parfaitement
                    // valide. On ramène chaque montant à un nombre avant de s'en servir.
                    $remise = (session('reduction_id') || session('point_reduc'))
                        ? ceil((float) session('remise'))
                        : (float) ($devis->cout_reduction ?? 0);
                    // $type_livraison = $devis->type_livraison_id;
                    $cout_livraison = (float) ($devis->cout_livraison ?? 0);
                    $livrable = $devis->adresse_livraison_id != null ? 1 : 0;
                    $montantTva = (float) ($devis->tva ?? 0);
                    $tvaTransport = (float) ($devis->tva_transport ?? 0);

                }

                // Dernier filet : sans date de livraison, l'insertion échoue sur une
                // contrainte NOT NULL et le client reçoit une page blanche. On le
                // ramène là où il peut la saisir, avec une explication — un parcours
                // interrompu n'est pas une erreur serveur.
                if (!$date_livraison) {
                    return $this->retourApresRefusCommande($devis)->with(
                        'error',
                        "Votre commande n'a pas pu être validée : la date de livraison "
                        . "et le mode de paiement n'ont pas été retenus. Cela arrive "
                        . "lorsque la page est rouverte après un long moment. "
                        . "Renseignez-les à nouveau, puis validez."
                    );
                }

                // Le mode choisi est-il un mode EN LIGNE ? On se base sur le flag
                // en_ligne du mode (et NON sur un id codé en dur) : ainsi « Paiement en
                // agence », « Chèque », « Espèces » (en_ligne = 0) restent des paiements
                // HORS LIGNE et ne déclenchent pas la passerelle — le client peut donc
                // toujours payer hors ligne en choisissant « Paiement en agence ».
                $modeObjChoisi = $mode_paiement ? ModePaiement::find($mode_paiement) : null;
                $modeEstEnLigne = $modeObjChoisi && $modeObjChoisi->en_ligne == 1;

                // Une commande qui NÉCESSITE un paiement en ligne (client ordinaire +
                // mode en ligne + passerelle configurée) est créée « EN ATTENTE DE
                // PAIEMENT » : elle n'apparaît PAS dans la file de traitement du
                // gestionnaire tant que le paiement n'est pas confirmé (sinon on
                // traiterait une commande non payée). Le callback / la vérification
                // pull la passe en « EN ATTENTE » à la confirmation du paiement.
                // Un client à terme n'était JAMAIS envoyé à la passerelle, même en
                // choisissant Wave ou Orange Money : son choix était enregistré puis
                // ignoré, et la commande partait à crédit. Il paie désormais en ligne
                // comme un autre lorsqu'il retient un mode en ligne — c'est le sens
                // même de ces modes.
                $paiementEnLigneRequis = ($modeEstEnLigne && config('paysecure.url'));
                $etatInitial = $paiementEnLigneRequis ? Help::$COMMANDE_EN_ATTENTE_PAIEMENT : $etat[0];

                // Montant engagé, pour le seul contrôle du plafond. On passe par le
                // devis, qui porte la valeur de référence : additionner $devis->montant
                // et la TVA comptait celle-ci deux fois pour un devis venu du mobile,
                // où `montant` est déjà le total net.
                $montantCommande = $this->montantNetDevis($devis);

                // PLAFOND : seules les commandes réellement prises À CRÉDIT le
                // consomment. Un règlement en ligne est encaissé immédiatement, il
                // n'engage donc aucun crédit et n'a pas à être plafonné.
                //
                // La condition porte sur $paiementEnLigneRequis et non sur le seul
                // mode choisi : si la passerelle n'est pas configurée, la commande
                // est enregistrée SANS être payée — elle redevient du crédit, et le
                // plafond doit s'appliquer. Sans cette nuance, choisir « Wave » sur
                // une installation sans passerelle aurait suffi à contourner la
                // limite.
                // Une avance disponible couvre d'abord la commande : seul le
                // reliquat engage le crédit (point 19, réponse Q2 du 07/09/2026).
                // Un mode en ligne ne touche pas à l'avance (réponse Q5).
                $montantACredit = $modeEstEnLigne
                    ? $montantCommande
                    : max(0, $montantCommande - \App\Services\Avances::soldeDisponible($client));
                if (!$paiementEnLigneRequis && ($refus = $this->refusPlafondCredit($client, $montantACredit))) {
                    return $this->retourApresRefusCommande($devis)->with('error', $refus);
                }

                if ($this->devisSansArticle($devis)) {
                    return redirect()->route('client.monPanier')->with('error',
                        "Votre commande n'a pas pu être validée : elle ne contient plus aucun "
                    . "article. Cela arrive lorsque la page est restée ouverte trop "
                    . "longtemps. Reprenez vos articles dans le panier, puis validez "
                    . "de nouveau.");
                }

                // LE NUMÉRO DOIT ÊTRE UNIQUE SUR LA COMMANDE.
                //
                // Il était tiré à la création du DEVIS, où la reprise en cas de
                // collision était assurée. Le devis n'étant plus enregistré pour
                // une commande directe, c'est ici que le numéro naît — et
                // `commande.numero` porte un index UNIQUE. On reprend donc le
                // même mécanisme : en cas de doublon, un autre numéro est tiré.
                //
                // Une commande née d'un devis garde le numéro de ce devis :
                // les deux se lisent ensemble, et l'habitude est ancienne.
                $donneesCommande = [
                    // DATE DE LA COMMANDE, explicitement renseignée.
                    //
                    // Aucun des points de création du site ne l'écrivait : la colonne
                    // prenait alors sa valeur PAR DÉFAUT, un horodatage figé
                    // (2026-04-13 12:32:21) inscrit une fois pour toutes dans le
                    // schéma. Toutes les commandes du site portaient donc la même
                    // date. Le site n'y voyait rien — ses écrans affichent created_at
                    // sous le nom « date_commande » — mais l'application mobile, qui
                    // lit la vraie colonne, affichait le 13 avril 2026 pour toutes.
                    'date_commande' => now(),
                    'numero' => $devis->numero,
                    'etat_commande' => $etatInitial,
                    'devis_id' => $devis->id,
                    'client_id' => $client->id,
                    'date_livraison' => $date_livraison,
                    'adresse_livraison_id' => $adresse_id,
                    'mode_paiement_id' => $mode_paiement,
                    // Ces trois colonnes sont NOT NULL en base : on garantit un nombre,
                    // quelle que soit la façon dont on est arrivé jusqu'ici.
                    'montant_total' => (float) ($devis->montant ?? 0),
                    // Une remise est une somme en FCFA : elle s'écrit en francs
                    // entiers, comme partout ailleurs.
                    'remise' => \Help::arrondiFranc($remise ?? 0),
                    'type_livraison_id' => $type_livraison,
                    'cout_livraison_client' => (float) ($cout_livraison ?? 0),
                    // TVA sur le transport (point 5), figée avec la commande.
                    'tva_transport' => (float) ($tvaTransport ?? 0),
                    'est_livrable' => $livrable,
                ];

                // UN SEUL point de création, quel que soit l'origine du numéro :
                // celui du devis quand il y en a un, un numéro tiré et repris en
                // cas de collision sinon.
                $creerLaCommande = function ($numero) use ($donneesCommande) {
                    $donneesCommande['numero'] = $numero;

                    return Commande::create($donneesCommande);
                };

                $commande = $devis->id
                    ? $creerLaCommande($devis->numero)
                    : Help::creerAvecNumeroUnique($creerLaCommande);

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
                    // « COMMANDÉ » SEULEMENT QUAND LA COMMANDE TIENT.
                    //
                    // Le devis passait à 2 — « Commandé » sur l'écran des devis —
                    // avant même le départ vers la passerelle. Un client qui
                    // annulait son paiement laissait derrière lui un devis annoncé
                    // commandé alors que rien n'avait été réglé et que la commande,
                    // elle, restait « en attente de paiement ». Constaté le
                    // 04/09/2026 sur le devis n° 429808.
                    //
                    // Tant qu'un règlement en ligne est attendu, le devis reste
                    // « En attente ». marquerPaiementEffectue() le bascule quand le
                    // paiement est confirmé — par le retour du client comme par le
                    // callback ou la reprise planifiée.
                    if (!$paiementEnLigneRequis) {
                        $devis->update([
                            'statut' => 2
                        ]);
                    }

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
                    // Une TVA est une somme en FCFA : elle s'ecrit en francs entiers.
                    'montant' => \Help::arrondiFranc($montantTva),
                    'commande_id' => $commande->id,
                    'type_affaire' => 2
                ]);

                // AIRSI (10/09/2026) : figé sur la commande, sur le HT net de
                // remise + TVA, pour le client sans régime réel. Avant l'avance :
                // c'est le net à payer qui s'impute.
                $commande->update(['airsi' => \Help::airsiPour($client,
                    max(0, $commande->montantHT() - (float) ($commande->remise ?? 0)) + \Help::arrondiFranc($montantTva),
                    max(0, $commande->montantHT() - (float) ($commande->remise ?? 0)), (float) Client::tva($client),
                    (float) ($commande->cout_livraison_client ?? 0),
                    (float) ($commande->tva_transport ?? 0) > 0 ? (float) Client::tvaTransport($client) : 0.0)]);

                // AVANCE DU CLIENT (point 19, 07/09/2026) : une commande réglée
                // « en agence » s'impute d'elle-même sur les avances disponibles,
                // du dépôt le plus ancien au plus récent. Un mode en ligne n'y
                // touche pas (réponse Q5). Les lignes et la TVA viennent d'être
                // écrites : on recharge les relations avant de chiffrer le dû.
                $imputationAvance = ['impute' => 0.0, 'reste' => 0.0, 'recus' => []];
                if (!$modeEstEnLigne) {
                    $commande->unsetRelation('detailCommande');
                    $commande->unsetRelation('TvaCommande');
                    $imputationAvance = \App\Services\Avances::imputerSurCommande($commande, Auth::id());
                }

                // $paiement = new Paiement();
                // $paiement->client_id = $client->id;
                // $paiement->devis_id = $devis->id;
                // $paiement->code = Help::getCommandeNo();
                // $paiement->libelle = "Paiement commande de produit DALAKOUN";
                // $paiement->montant_total = $commande->montant_total + $commande->TvaCommande->montant + $commande->cout_livraison_client - $commande->remise;
                // $paiement->montant_restant = $commande->montant_total + $commande->TvaCommande->montant + $commande->cout_livraison_client - $commande->remise;
                // $paiement->statut = Help::$STATUT_INACTIF;
                // $paiement->service_id = $commande->id;
                // $paiement->service = Help::$COMMANDE;
                // $paiement->save();

                $ret = array();

                if($devis->id){
                    $total = ($devis->montant + $devis->cout_livraison + $devis->tva + (float) ($devis->tva_transport ?? 0)) - $devis->cout_reduction;
                }else{
                    // montantTTC dit TOUT ce qui est dû (cf. infoLivraison). L'ancienne
                    // formule lui rajoutait le transport ; or après une remise, il le
                    // portait déjà : un client qui utilisait un code promo avec une
                    // livraison partait vers la passerelle avec le transport compté
                    // deux fois.
                    $total = (float) (session('0')['montantTTC'] ?? 0);
                }

                // Journaliser pour diagnostic
                \Log::info('panierCommande - paiement', [
                    'client_a_terme' => $client->client_a_terme,
                    'total' => $total,
                    'mode_paiement' => $mode_paiement,
                ]);

                // Déclencher le paiement en ligne UNIQUEMENT pour un mode en ligne
                // (en_ligne = 1). Un mode hors ligne comme « Paiement en agence »
                // (en_ligne = 0) ne passe pas par la passerelle : la commande est
                // simplement enregistrée, le client paie ensuite en agence.
                // Le client à terme n'est plus exclu : s'il a choisi un mode en
                // ligne, il règle en ligne. Seul compte le caractère du mode.
                if ($modeEstEnLigne) {

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
                            'libelle_article' => "Paiement DALAKOUN",
                            'quantite' => 1,
                            // Total net depuis les LIGNES (cf. Commande::montantAPayer) : sûr
                            // quelle que soit l'origine (web: montant_total=HT, mobile: net).
                            'montant' => intVal($commande->montantAPayer()),
                            'lib_order' => "Paiement commande de produit DALAKOUN",
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
                        // Le panier n'est PAS vidé ici. Il l'était avant le départ vers
                        // la passerelle : un client qui fermait la page de paiement
                        // retrouvait son panier vide, comme si la commande avait été
                        // réglée, alors qu'aucun franc n'avait été encaissé.
                        //
                        // On note seulement QUEL paiement a été lancé depuis ce panier.
                        // verifiePaiement() ne le videra que si ce paiement-là est
                        // confirmé — et pas si le client règle entre-temps tout autre
                        // chose, une facture ou une location, depuis son espace.
                        session()->put('panier_lie_au_paiement', $codePaiement);
                        return Redirect::away($ret['message']);
                    } else {
                        // Même raison : rien n'a été payé, le panier reste intact pour
                        // que le client puisse reprendre sa commande.
                        return redirect()->route('client.commandeValidee', $commande->numero)
                            ->with('warning', 'Votre commande a été enregistrée mais le paiement en ligne a échoué (' . ($ret['message'] ?? 'Erreur inconnue') . '). Rendez-vous dans Mon Compte pour réessayer le paiement.');
                    }
                }
                // Relation avec withDefault() : jamais null même si mode_paiement_id l'est
                // (commande en agence / client à terme). L'ancien
                // ModePaiement::find(...)->value('description') fatalisait dans ce cas, et
                // renvoyait de toute façon la description de la 1re ligne de la table.
                $modePaiment = $commande->modePaiement->libelle ?: ($commande->modePaiement->description ?? '');

                // toastr()->success('Commande validée');
                // Montants depuis les LIGNES (cf. Commande::montantAPayer/montantHT).
                // Envoi NON bloquant : un échec d'email ne doit pas casser la commande.
                try {
                    Mail::send(new emailCommande(
                                    $commande,
                                    $commande->TvaCommande?->montant ?? 0,
                                    $commande->montantAPayer(),
                                    $commande->cout_livraison_client,
                                    $commande->remise,
                                    $modePaiment,
                                    $commande->montantHT()
                                ));
                } catch (\Throwable $e) {
                    \Log::warning('Email commande non envoyé: '.$e->getMessage());
                }
                // La proforma part au client en PDF (lot 81, 15/09/2026) ; une commande
                // payée en ligne recevra sa facture au paiement confirmé. Différé,
                // jamais bloquant, une seule fois par document.
                \App\Services\DocumentDeCommande::envoyerParCourriel($commande);
                Cart::destroy();
                $messageSucces = 'Votre commande a bien été enregistrée ! Rendez-vous dans la rubrique Mon Compte pour suivre votre commande';
                if (($imputationAvance['impute'] ?? 0) >= 1) {
                    $messageSucces .= '. ' . \App\Services\Avances::messageImputation($imputationAvance);
                }
                return redirect()->route('client.commandeValidee',$commande->numero)->with('success', $messageSucces);
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

        // Confirmation ACTIVE : le client peut revenir de PaySecure AVANT que le
        // callback serveur→serveur n'ait régularisé le paiement (course). On
        // interroge donc directement le statut auprès de PaySecure et on régularise
        // s'il est payé — sinon on afficherait « paiement non effectué » à tort à un
        // client qui a bien payé. marquerPaiementEffectue est idempotent.
        if (!empty(config('paysecure.status_url'))) {
            $pen = new PaiementEnLigne();
            if ($pen->interrogerStatutPaiement($codePaiement) === 'paye') {
                $pen->marquerPaiementEffectue($codePaiement);
                // Recharger le paiement (et donc ses lignes) après régularisation.
                $paiement = Paiement::find($paiement->id) ?? $paiement;
            }
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

            // POINTS DE FIDELITE — LE CANAL EN LIGNE DU SITE N'EN DONNAIT AUCUN.
            //
            // Les deux guichets creditent a la validation de l'encaissement, et
            // l'application mobile au retour de la passerelle. Le site, lui, ne
            // creditait rien : un client qui reglait 500 000 F par carte depuis
            // le site ne gagnait pas un point, quand le meme reglement au
            // guichet lui en rapportait 500. Le canal decidait de la recompense.
            //
            // La regle est celle de partout : Help::pointsPour, sur le montant
            // REELLEMENT encaisse. Le nombre attribue est inscrit sur le
            // reglement, sans quoi une annulation obligerait a deviner ce qu'il
            // faut reprendre.
            //
            // Le credit est pose ICI, une seule fois par reglement, avant
            // l'aiguillage par type de service : une vente, une location, un
            // transport ou une facture se valent au regard de la fidelite.
            //
            // Sans effet si la colonne n'existe pas encore en ligne : la
            // migration du 25/08 la cree, et le garde-fou evite une page
            // blanche sur une base en retard.
            if ($paiement->client_id
                && Schema::hasColumn('paiement', 'points_attribues')
                && (float) $paiement->points_attribues <= 0) {

                $points = Help::pointsPour((float) $paiement->montant_total);

                if ($points > 0) {
                    $leClient = Client::find($paiement->client_id);

                    if ($leClient) {
                        $leClient->update([
                            'point' => (float) $leClient->point + $points,
                        ]);
                        $paiement->update(['points_attribues' => $points]);
                    }
                }
            }

            // C'EST ICI que le panier est vidé, et nulle part ailleurs : le paiement
            // vient d'être confirmé. Il l'était auparavant AVANT même le départ vers
            // la passerelle, si bien qu'un client qui fermait la page de paiement
            // retrouvait un panier vide sans avoir rien réglé.
            //
            // Et seulement si c'est bien CE paiement qui a été lancé depuis le panier :
            // régler une facture ou une location déjà existante ne doit pas emporter
            // les articles que le client vient d'y déposer.
            if (session('panier_lie_au_paiement') === $codePaiement) {
                Cart::destroy();
                session()->forget('panier_lie_au_paiement');
            }

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
                            // Le reçu de paiement (PDF, modèle du guichet) remplace
                            // l'ancien courriel de confirmation sans pièce jointe.
                            \App\Services\RecuPaiement::envoyerParCourriel($paiement);
                        } catch (\Throwable $e) {
                            \Log::warning('Email paiement commande non envoyé: '.$e->getMessage());
                        }
                        // Commande payée en ligne : sa FACTURE part au client (lot 81, 15/09/2026),
                        // une seule fois quel que soit le chemin (retour, rappel, vérification).
                        \App\Services\DocumentDeCommande::envoyerParCourriel($commande);
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
                            \App\Services\RecuPaiement::envoyerParCourriel($paiement);
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
        $nomPdf = 'Fichier'.'-'.Auth::user()->client->display_name.'-'. date('YmdHis') .'.pdf'; // extension forcée : jamais l'extension d'origine (anti-upload de .php exécutable)
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


            if ((session('0')['ville'] ?? null) != null) {
                $ville = Ville::find(session('0')['ville'] ?? null);

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
                $nomPrenom = $user->client->display_name;
                $id = Auth::user()->id;
                $etat = Help::listeStatutCommande();

                // $ville = Ville::where('id', session('0')['ville'] ?? null)->first();

                $client = Client::where('user_id',$id)->first();




                // $adresseId = $adresse->id;
                $total = Cart::total();

                $promo = 0;
                $remise = ceil(session('remise')) ?? 0;

                if(session('type') == 'commande' || $devis->service == 'VENTE'){

                    $config = Configuration::first();


                    // UNE COMMANDE NE CREE PAS DE DEVIS.
                    //
                    // Sans devis d'origine, ce parcours en enregistrait un pour
                    // porter les donnees du panier jusqu'a la commande : une
                    // ligne de plus dans la liste des devis a chaque vente. Il
                    // reste un simple porteur, en memoire. Quand le client vient
                    // d'un VRAI devis, rien ne change.
                    if($devis->id == null){
                        $devis = new Devis([
                            'client_id' => $client->id,
                            'adresse_livraison_id' => session('date_livraison'),
                            'montant' => session('0')['montantTTC'] - session('0')['tva'],
                        ]);
                    }

                    if(session('reduction_id')){
                        $reduction = Reduction::find(session('reduction_id'));

                        $reduction->update([
                            'est_utilise' => 1,
                            'client_id' => $client->id,
                            'devis_id' => $devis->id
                        ]);
                    }


                    // dd( $promo);
                    // Plafond de crédit : même contrôle qu'en flux panier. Cette méthode
                    // crée aussi une commande, elle doit donc l'appliquer.
                    if ($refus = $this->refusPlafondCredit($client, $this->montantNetDevis($devis))) {
                        return $this->retourApresRefusCommande($devis)->with('error', $refus);
                    }

                    if ($this->porteDuMaterielDeLocation($devis)) {
                        return redirect()->route('client.panierLocation')->with('error',
                            "Ce matériel se LOUE, il ne s'achète pas. Nous vous avons ramené au "
                            . "parcours de location : choisissez vos dates, puis validez.");
                    }

                    if ($this->devisSansArticle($devis)) {
                        return redirect()->route('client.monPanier')->with('error',
                            "Votre commande n'a pas pu être validée : elle ne contient plus aucun "
                            . "article. Cela arrive lorsque la page est restée ouverte trop "
                            . "longtemps. Reprenez vos articles dans le panier, puis validez "
                            . "de nouveau.");
                    }

                    // Le numéro vient du devis quand il y en a un ; sinon la
                    // commande le tire elle-même, avec reprise en cas de
                    // collision — `commande.numero` porte un index UNIQUE.
                    $creerLaCommande = function ($numero) use (
                        $devis, $etat, $client, $adresse_id, $mode_paiement_id,
                        $remise, $date_livraison, $type_livraison, $cout_livraison, $livrable
                    ) {
                        return Commande::create([
                            // Date réelle de la commande : sans elle, la colonne retombe sur
                            // son horodatage figé par défaut (voir le premier appel).
                            'date_commande' => now(),
                            'numero' => $numero,
                            'etat_commande' => $etat[0],
                            'devis_id' => $devis->id,
                            'client_id' => $client->id,
                            'adresse_livraison_id' => $adresse_id ? $adresse_id : null,
                            'mode_paiement_id' => $mode_paiement_id,
                            'montant_total' => $devis->montant,
                            'remise' => \Help::arrondiFranc($remise),
                            'date_livraison' => $date_livraison,
                            'type_livraison_id' => $type_livraison,
                            'cout_livraison_client' => $cout_livraison,
                            'est_livrable' => $livrable,
                            // Type livraison
                        ]);
                    };

                    $commande = $devis->id
                        ? $creerLaCommande($devis->numero)
                        : Help::creerAvecNumeroUnique($creerLaCommande);



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


                    // LES ARTICLES VIENNENT DE LA OU ILS SONT.
                    //
                    // D'un vrai devis, ce sont ses lignes. D'une commande
                    // directe, le devis n'est plus qu'un porteur sans ligne :
                    // les articles se prennent alors dans le PANIER, sans quoi
                    // la commande naitrait vide.
                    if ($devis->id) {
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

                        // Le devis demande par le client passe « Commande ».
                        $devis->update([
                            'statut' => 2
                        ]);
                    } else {
                        foreach(Cart::content() as $produit){
                            $detailCommande = DetailCommande::create([
                                'produit_id' => $produit->id,
                                'commande_id' => $commande->id,
                                'qte' => $produit->qty,
                                'prix' => $produit->price,
                                'prix_fournisseur' => $produit->options->prix_fournisseur,
                                'cout_livraison' => $produit->options->cout_livraison,
                            ]);
                        }
                    }

                    $tva = TvaCommande::create([
                        'client_id' => $client->id,
                        'montant' => \Help::arrondiFranc($montantTva),
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

                    // Plafond de crédit : une location l'engage comme une commande.
                    // Le client repart avec le matériel et paiera plus tard.
                    if ($refus = $this->refusPlafondCredit($client, $this->montantNetLocation($total, $remise))) {
                        return $this->retourApresRefusCommande($devis)->with('error', $refus);
                    }

                    $location = [
                        // Le meme generateur que le mobile : un seul format
                        // de numero de location, dictable au telephone.
                        'numero' => \Help::genererNumeroUnique('location'),
                        'client_id' => $client->id,
                        'mode_paiement_id' => session('mode_paiement'),
                        'adresse_livraison_id' => isset($adresse) ? $adresse->id : null,
                        // Date de la location = instant où elle est passée, comme le fait
                        // déjà l'API. Sans cette ligne, la colonne prenait la valeur par
                        // défaut FIGÉE du schéma et toutes les locations du site
                        // s'affichaient au 13/04/2026 dans l'application mobile.
                        // (Les dates de début et de fin du matériel sont ailleurs :
                        //  detail_location.debut / detail_location.fin, par article.)
                        'date_location' => now(),
                        'montant_total' => $total,
                        'etat_location' => Help::$LOCATION_EN_ATTENTE,
                        'cout_livraison_client' => session('0')['cout_livraison'],
                        // « Retrait sur place » : aucun livreur n'interviendra, la page de
                        // validation gestionnaire s'adapte. Repli sur livrable.
                        'est_livrable' => (session('0')['estLivrable'] ?? 'oui') == 'oui' ? 1 : 0,
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
                    // AIRSI figé (10/09/2026) : HT net de remise + TVA.
                    $location->update(['airsi' => \Help::airsiPour($client,
                        max(0, (float) $total - (float) $remise) + (float) $laTva->montant,
                        max(0, (float) $total - (float) $remise), (float) Client::tva($client),
                        (float) (session('0')['cout_livraison'] ?? 0),
                        (float) (session('0')['tva_transport'] ?? 0) > 0 ? (float) Client::tvaTransport($client) : 0.0)]);

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
                    // Ligne morte : le return view() ci-dessus s'exécute toujours avant.
                    // La route 'client.locationValidee' n'existe plus (méthode absente).
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

        // Plafond de crédit : même contrôle qu'en flux panier. Cette méthode
        // crée aussi une commande, elle doit donc l'appliquer.
        if ($refus = $this->refusPlafondCredit($client, $this->montantNetDevis($devis))) {
            return $this->retourApresRefusCommande($devis)->with('error', $refus);
        }

        if ($this->porteDuMaterielDeLocation($devis)) {
            return redirect()->route('client.panierLocation')->with('error',
                "Ce matériel se LOUE, il ne s'achète pas. Nous vous avons ramené au "
                . "parcours de location : choisissez vos dates, puis validez.");
        }

        if ($this->devisSansArticle($devis)) {
            return redirect()->route('client.monPanier')->with('error',
                "Votre commande n'a pas pu être validée : elle ne contient plus aucun "
                . "article. Cela arrive lorsque la page est restée ouverte trop "
                . "longtemps. Reprenez vos articles dans le panier, puis validez "
                . "de nouveau.");
        }

        $commande = Commande::create([
            // Date réelle de la commande : sans elle, la colonne retombe sur
            // son horodatage figé par défaut (voir le premier appel).
            'date_commande' => now(),
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

        $nomPrenom = $client->display_name;

        // La signature de emailCommande est (commande, tva, total, fraisLivraison, remise,
        // modepaiement, ht) : l'ancien appel à 6 arguments (email, nom, ...) provoquait un
        // ArgumentCountError fatal ALORS QUE la commande venait d'être créée -> le client
        // voyait une page cassée et une commande « fantôme » restait en base.
        // Envoi NON bloquant, comme les autres appels de ce contrôleur.
        try {
            Mail::send(new emailCommande(
                $commande,
                $commande->TvaCommande?->montant ?? 0,
                $commande->montantAPayer(),
                $commande->cout_livraison_client,
                $commande->remise,
                $commande->modePaiement->libelle ?: ($commande->modePaiement->description ?? ''),
                $commande->montantHT()
            ));
        } catch (\Throwable $e) {
            \Log::warning('Email commande (devis) non envoyé: '.$e->getMessage());
        }
        // La proforma part au client en PDF (lot 81, 15/09/2026) ; différé, jamais bloquant.
        \App\Services\DocumentDeCommande::envoyerParCourriel($commande);

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
                'fichier' => \App\Support\BonDeCommandeJoint::regle(false),
                'numero_bon' => 'required|max:255',
            ],
            \App\Support\BonDeCommandeJoint::messages() + [
                'numero_bon.required' => 'Le numéro de bon est requis',
            ]);
            // Le numéro est retenu MÊME SANS pièce jointe (09/09/2026) : il est
            // figé sur la location et reporté devant chaque désignation.
            session()->put(['numero_bon_commande' => $request->numero_bon]);

            if ($request->hasFile('fichier')) {

                $destination = Storage::disk('public')->path('temp_pdfs'); // racine reelle du disque (cf. config/filesystems.php)
                // L'extension vient de la LISTE BLANCHE, jamais du nom recu.
                $nomPdf = \App\Support\BonDeCommandeJoint::nomDeFichier(
                    $request->file('fichier'), Auth::user()->client->display_name);
                $request->file('fichier')->move($destination, $nomPdf);
                session()->put([
                    'cheminFichier' => 'temp_pdfs/'.$nomPdf,
                    'numero_bon_commande' => $request->numero_bon,
                    'fichier' => $nomPdf,
                ]);

            }
            // LE MODE CHOISI EST TOUJOURS RETENU, client à terme compris.
            //
            // Il n'était enregistré que pour les autres. L'écran proposant le
            // sélecteur à tout le monde, un client à terme qui choisissait
            // « Wave » voyait son choix accepté puis oublié : plus loin, aucun
            // mode n'était trouvé, la passerelle n'était même pas tentée, et la
            // location était enregistrée sans un mot d'explication.
            session()->put([
                'mode_paiement' => $request->mode
            ]);

        }else{

            // LE MODE CHOISI EST TOUJOURS RETENU, client à terme compris.
            //
            // Il n'était enregistré que pour les autres. L'écran proposant le
            // sélecteur à tout le monde, un client à terme qui choisissait
            // « Wave » voyait son choix accepté puis oublié : plus loin, aucun
            // mode n'était trouvé, la passerelle n'était même pas tentée, et la
            // location était enregistrée sans un mot d'explication.
            session()->put([
                'mode_paiement' => $request->mode
            ]);
        }

        // dd(session());


        $mode = ModePaiement::find($request->mode);
        $ville = Ville::find(session('0')['ville'] ?? null);
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
                'fichier' => \App\Support\BonDeCommandeJoint::regle(),
                'numero_bon' => 'required|max:255',
            ],
            \App\Support\BonDeCommandeJoint::messages() + [
                'numero_bon.required' => 'Le numéro de bon est requis',
            ]);
            // Le numéro est retenu MÊME SANS pièce jointe (09/09/2026) : il est
            // figé sur la location et reporté devant chaque désignation.
            session()->put(['numero_bon_commande' => $request->numero_bon]);

            if ($request->hasFile('fichier')) {

                $destination = Storage::disk('public')->path('temp_pdfs'); // racine reelle du disque (cf. config/filesystems.php)
                // L'extension vient de la LISTE BLANCHE, jamais du nom recu.
                $nomPdf = \App\Support\BonDeCommandeJoint::nomDeFichier(
                    $request->file('fichier'), Auth::user()->client->display_name);
                $request->file('fichier')->move($destination, $nomPdf);
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
        $ville = Ville::find(session('0')['ville'] ?? null);
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
        if(!Auth::user()){
            return redirect()->route('client.login');
        }

        // Panier vide : sans cette garde, un devis sans aucune ligne était créé
        // (rechargement de la page, retour arrière, lien ouvert deux fois) et
        // venait encombrer « Mes devis » avec un montant nul.
        if (Cart::content()->isEmpty()) {
            return redirect()->route('client.panier')
                ->with('error', "Votre panier est vide : il n'y a rien à mettre en devis.");
        }

        $devis = $this->panierEnDevis(Auth::user()->id);

        // Étape de livraison non franchie ou session expirée. Auparavant la
        // méthode lisait directement session('0')['cout_livraison'] : sur une
        // session perdue, PHP signale un accès à un index sur une valeur nulle,
        // que Laravel transforme en exception — donc une page d'erreur 500,
        // alors que le client n'avait rien fait de fautif.
        if (!$devis) {
            return redirect()->route('client.panier')
                ->with('error', "Vos informations de livraison ont expiré. Reprenez depuis le panier, puis validez à nouveau.");
        }

        return redirect()->route('client.devisValide',$devis)->with('success','Votre dévis a été enregistré');
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

                $request->validate(
                    ['fichier' => \App\Support\BonDeCommandeJoint::regle()],
                    \App\Support\BonDeCommandeJoint::messages()
                );

                $destination = Storage::disk('public')->path('temp_pdfs'); // racine reelle du disque (cf. config/filesystems.php)
                // L'extension vient de la LISTE BLANCHE, jamais du nom recu.
                $nomPdf = \App\Support\BonDeCommandeJoint::nomDeFichier(
                    $request->file('fichier'), Auth::user()->client->display_name);
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
            'remise' => \Help::arrondiFranc($remise),
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

        // Un compte connecté sans fiche client (fiche jamais créée, ou supprimée)
        // faisait tomber la page en erreur 500 : $client->id et
        // $client->client_a_terme étaient lus sur null, et la vue construit une URL
        // à partir de $client->id. On sort proprement plutôt que d'exposer une
        // page blanche.
        if($client == null){
            return redirect()->route('client.monCompte')
                ->with('error', "Votre fiche client est introuvable. Contactez-nous pour la régulariser.");
        }

        // La liste est calculée par PaiementsDuClient (lot 89, 16/09/2026) : la même
        // règle sert à la page web et à l'application, par la route interne.
        $lignes = \App\Services\PaiementsDuClient::lignes($client, $etat);

        return view('client.listePaiement',[
            'lignes' => $lignes,
            'etat' => $etat,
            'categories' => Categorie::all(),
            'produits' => Produit::all(),
            // Le client était rechargé une seconde fois depuis la base alors qu'il
            // est déjà en main : même requête, deux fois par affichage.
            'client' => $client,
            'moyens' => ModePaiement::listePourClient()
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
                // LE NUMÉRO DE BON DE COMMANDE INTERNE EST OBLIGATOIRE (09/09/2026).
                // L'écran l'exige déjà ; ce contrôle-ci fait foi. Le numéro est
                // retenu même sans pièce jointe : il est reporté devant chaque
                // désignation de la proforma, puis de la facture.
                $request->validate(
                    [
                        'numero_bon' => 'required|max:255',
                        'fichier'    => \App\Support\BonDeCommandeJoint::regle(false),
                    ],
                    \App\Support\BonDeCommandeJoint::messages()
                        + ['numero_bon.required' => 'Le numéro de bon de commande interne est obligatoire.']
                );
                session()->put(['numero_bon_commande' => $request->numero_bon]);
                if ($request->hasFile('fichier')) {
                    $destination = Storage::disk('public')->path('temp_pdfs'); // racine reelle du disque (cf. config/filesystems.php)
                    // L'extension vient de la LISTE BLANCHE, jamais du nom recu.
                    $nomPdf = \App\Support\BonDeCommandeJoint::nomDeFichier(
                        $request->file('fichier'), Auth::user()->client->display_name);
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

            // Même précaution que dans panierEnCommande() : sans fiche client,
            // $client->id plus bas est une erreur fatale. L'appelant redirige.
            if (!$client) {
                return null;
            }

            $config = Configuration::first();

            $total = Cart::total();
            // $promo = null;

            // Les informations de livraison sont lues UNE fois, et jamais
            // supposées présentes : sur une session expirée, session('0')
            // vaut null et chaque session('0')['...'] provoquait une erreur
            // fatale au lieu d'un simple retour au panier.
            $livraison = is_array(session('0')) ? session('0') : [];

            $cout_livraison = 0;
            $remise = 0;
            $adresse_livraison_id = null;
            if(!empty($livraison['cout_livraison'])){
                $cout_livraison = $livraison['cout_livraison'];

                $ville = Ville::find($livraison['ville'] ?? null);

                // Ville inconnue : AdresseLivraison exige ville_id et pays_id.
                // Le devis reste créé, mais sans adresse de livraison — mieux
                // vaut un devis à compléter qu'une page d'erreur.
                if ($ville) {
                    $adresse_livraison = AdresseLivraison::create([
                        'client_id' => $client->id,
                        'ville_id' => $ville->id,
                        'pays_id' => $ville->pays_id,
                        'affichage' => $livraison['infoSup'] ?? null,
                        'longitude' => $livraison['long'] ?? null,
                        'latitude' => $livraison['lat'] ?? null,
                    ]);
                    $adresse_livraison_id = $adresse_livraison->id;
                }

            }

            if(session('remise')){
                $remise = session('remise');
            }

                $dataDevis = [
                    'client_id' => $client->id,
                    'montant' => $total,
                    'tva' => $livraison['tva'] ?? null,
                    'cout_livraison' => $cout_livraison,
                    'tva_transport' => (float) ($livraison['tva_transport'] ?? 0),
                    'mode_paiement' => session('mode'),
                    'mode_paiement_id' => session('mode'),
                    'cout_reduction' => $remise,
                    'adresse_livraison_id' => $adresse_livraison_id,
                    'montant_ht' => Cart::total(),
                    'type_livraison_id' => session('type_livraison_id'),
                    'service' => 1,
                    'date_livraison' => session('date_livraison'),
                    // Numéro de bon de commande interne (09/09/2026), reporté sur le devis.
                    'numero_bon_commande' => session('numero_bon_commande'),
                ];

                $devis = Help::creerAvecNumeroUnique(function ($numero) use ($dataDevis) {
                    $dataDevis['numero'] = $numero;
                    return Devis::create($dataDevis);
                });

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

        // Session d'adresse expirée / incomplète (retour arrière, reconnexion) :
        // $ville->pays->id fatalisait ici, et AdresseLivraison exige pays_id/ville_id
        // NOT NULL. Ce code est atteint depuis la CONNEXION -> erreur 500 au login.
        if (!$ville || !$client) {
            return null;
        }





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
                    'client_id' => $client->id,
                    'adresse_livraison_id' => $adresseId,
                    'montant' => $totalPlusTva,
                ];

                // UNE COMMANDE NE CREE PAS DE DEVIS — ici comme sur le parcours
                // principal. Ce devis n'etait qu'un porteur de donnees, mais il
                // etait enregistre : chaque vente ajoutait une ligne dans la
                // liste des devis, que le client n'avait jamais demandee.
                $devis = new Devis($dataDevis);

                if(session('reduction_id')){
                    $reduction = Reduction::find(session('reduction_id'));

            $promo = $reduction->taux_reduction;

            $total = Cart::total() - (Cart::total() * $reduction->taux_reduction)/100;

                    // Le code promo est consomme par le CLIENT, pas par un devis.
                    $reduction->update([
                        'est_utilise' => 1,
                        'client_id' => $client->id,
                    ]);
                }

                // Les lignes de la commande se construisent depuis le PANIER,
                // plus bas : il n'y a plus de ligne de devis pour les porter.



                // session()->forget('');

                // dd($devis->montant);

                // Plafond de crédit : même contrôle qu'en flux panier. Cette méthode
                // crée aussi une commande, elle doit donc l'appliquer.
                if ($refus = $this->refusPlafondCredit($client, $this->montantNetDevis($devis))) {
                    return $this->retourApresRefusCommande($devis)->with('error', $refus);
                }

                // Appelée depuis la CONNEXION : personne ne lit sa valeur de
                // retour, on s'abstient simplement de créer la commande.
                if ($this->devisSansArticle($devis)
                    || $this->porteDuMaterielDeLocation($devis)) {
                    return null;
                }

                // Le numéro venait du devis, qui n'est plus enregistré : c'est
                // maintenant la commande qui le tire, avec reprise en cas de
                // collision — `commande.numero` porte un index UNIQUE.
                $commande = Help::creerAvecNumeroUnique(function ($numero) use (
                    $devis, $etat, $client, $adresseId, $promo
                ) {
                    return Commande::create([
                        // Date réelle de la commande : sans elle, la colonne retombe sur
                        // son horodatage figé par défaut (voir le premier appel).
                        'date_commande' => now(),
                        'numero' => $numero,
                        'etat_commande' => $etat[0],
                        'devis_id' => $devis->id,
                        'client_id' => $client->id,
                        'adresse_livraison_id' => $adresseId,
                        'mode_paiement_id' => session('mode'),
                        'montant_total' => $devis->montant,
                        'remise' => \Help::arrondiFranc($promo),
                        'est_livrable' => session('0')['estLivrable'] == 'oui' ? 1 : 0,
                    ]);
                });

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

                foreach(Cart::content() as $produit){
                    $detailCommande = DetailCommande::create([
                        'produit_id' => $produit->id,
                        'commande_id' => $commandeId,
                        'qte' => $produit->qty,
                        'prix' => $produit->price,
                        'prix_fournisseur' => $produit->options->prix_fournisseur
                    ]);
                }

                $tva = TvaCommande::create([
                    'client_id' => $client->id,
                    'montant' => \Help::arrondiFranc($laTva),
                    'commande_id' => $commande->id,
                    'type_affaire' => 2
                ]);

                // *************************************

                $paiement = new Paiement();
                $paiement->client_id = $client->id;
                $paiement->devis_id = $devis->id;
                $paiement->code = $commande->numero;
                $paiement->libelle = "Paiement commande de produit DALAKOUN";
                // montant_total stocke le HT côté web : la dette doit être le NET dû
                // (HT + TVA + livraison - remise), sinon le client est sous-facturé.
                $paiement->montant_total = $commande->montantAPayer();
                $paiement->montant_restant = 0;
                $paiement->statut = Help::$STATUT_INACTIF;
                $paiement->save();

                $ret = array();

                // LE PLAFOND VAUT AUSSI ICI.
                //
                // Ce parcours-ci est celui du panier rempli AVANT de se
                // connecter : à la connexion, la commande part directement vers
                // la passerelle, sans que le client ait choisi de mode et sans
                // qu'aucun plafond soit vérifié. Un panier de plus de deux
                // millions y échappait à la règle annoncée sur toutes les autres
                // pages. Au-delà, la commande reste enregistrée — elle se règle
                // en agence, comme le dit le message.
                $plafondFranchi = \App\Support\PlafondPaiementEnLigne::depasse(
                    $commande->montantAPayer()
                );

                if ($client->client_a_terme == false && !$plafondFranchi) {
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
                            'libelle_article' => "Paiement DALAKOUN",
                            'quantite' => 1,
                            // Montant NET à débiter (TVA + livraison - remise incluses) :
                            // montant_total ne contient que le HT côté web.
                            'montant' => intVal($commande->montantAPayer()),
                            'lib_order' => "Paiement commande de produit DALAKOUN",
                            'Url_Retour' => route('client.monPanier'), //route("ouvreApp", ['codePaiement' => $codePaiement]),
                            'Url_Callback' => route('callBackPaiement'),
                        ],
                        $codePaiement,
                        $client,
                        $paiement->id,
                        // Montant NET (cf. ci-dessus) et non le HT.
                        $commande->montantAPayer(),
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

                    // Signature à 7 arguments (cf. app/Mail/emailCommande.php) : l'ancien
                    // appel à 6 arguments fatalisait ici, et ce code est atteint depuis la
                    // CONNEXION du client -> erreur 500 au login. Envoi non bloquant.
                    try {
                        Mail::send(new emailCommande(
                            $commande,
                            $commande->TvaCommande?->montant ?? 0,
                            $commande->montantAPayer(),
                            $commande->cout_livraison_client,
                            $commande->remise,
                            $commande->modePaiement->libelle ?: ($commande->modePaiement->description ?? ''),
                            $commande->montantHT()
                        ));
                    } catch (\Throwable $e) {
                        \Log::warning('Email commande (panier) non envoyé: '.$e->getMessage());
                    }
                    // La proforma part au client en PDF (lot 81, 15/09/2026) ; différé, jamais bloquant.
                    \App\Services\DocumentDeCommande::envoyerParCourriel($commande);
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

                // Plafond de crédit : une location l'engage comme une commande. Ici
                // $total porte déjà la TVA et aucune remise n'est enregistrée : le
                // montant engagé est donc le total plus la livraison.
                $engageLocation = (float) $total
                    + (float) (session('0') ? session('0')['cout_livraison_client'] : 0);

                if ($refus = $this->refusPlafondCredit($client, $engageLocation)) {
                    return redirect()->route('client.monPanier')->with('error', $refus);
                }

                $location = [
                    // Le meme generateur que le mobile : un seul format
                    // de numero de location, dictable au telephone.
                    'numero' => \Help::genererNumeroUnique('location'),
                    'client_id' => $client->id,
                    'mode_paiement_id' => session('mode_paiement'),
                    'adresse_livraison_id' => $adresse->id,
                    // Voir le commentaire de l'autre point de création : sans cette
                    // ligne, la location hérite de la date figée du schéma.
                    'date_location' => now(),
                    'montant_total' => $total,
                    'etat_location' => Help::$LOCATION_EN_ATTENTE,
                    'cout_livraison_client' => session('0')? session('0')['cout_livraison_client'] : 0,
                    // « Retrait sur place » : aucun livreur n'interviendra. Repli sur livrable.
                    'est_livrable' => (session('0')['estLivrable'] ?? 'oui') == 'oui' ? 1 : 0,
                    // Numéro de bon de commande interne (09/09/2026), comme sur une vente.
                    'numero_bon_commande' => session('numero_bon_commande'),
                    // 'remise' =>
                ];

                $location = Location::create($location);

                // La pièce jointe du bon rejoint bl_client, rattachée à la LOCATION
                // (09/09/2026) — même rangement que pour une vente.
                if (session('cheminFichier')) {
                    $sourcePath = session('cheminFichier');
                    $destinationPath = 'lesBons/' . basename($sourcePath);
                    if (Storage::disk('public')->exists($sourcePath)) {
                        Storage::disk('public')->move($sourcePath, $destinationPath);
                    }
                    BlClient::create([
                        'numero'      => session('numero_bon_commande'),
                        'client_id'   => $client->id,
                        'fichier'     => $destinationPath,
                        'location_id' => $location->id,
                    ]);
                }
                // Purge : un numéro resté en session serait repris par l'affaire suivante.
                session()->forget(['cheminFichier', 'numero_bon_commande', 'fichier']);

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
