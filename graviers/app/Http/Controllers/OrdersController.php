<?php

namespace App\Http\Controllers;


use PDF;
use Help;
use App\Models\User;
use App\Models\Devis;
use App\Models\Client;
use App\Models\Facture;
use App\Models\Livreur;
use App\Models\Produit;
use App\Models\Commande;
use App\Models\Paiement;
use App\Models\Vehicule;
use App\Models\Livraison;
use App\Models\Reduction;
use App\Models\Enlevement;
use App\Models\Location;
use Illuminate\Support\Str;
use App\Mail\emailReduction;
use App\Models\StockProduit;
use Illuminate\Http\Request;
use App\Models\Configuration;
use App\Models\CoutLivraison;
use App\Services\FneService;

use App\Models\LignePaiement;
use App\Models\DetailCommande;
use App\Mail\receptionCodeLivraison;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use App\Mail\receptionCodeEnlevement;

class OrdersController extends Controller
{
    //
    public function ordersList()
    {
        $commandes = Commande::liste(null, [Help::$COMMANDE_EN_ATTENTE, Help::$COMMANDE_EN_TRAITEMENT]);
        // dd($commandes);
        $gest = null;
        $config = Configuration::first();

        if($config->gestionnaire1_id == Auth::user()->id){
            $gest = 1;
        }elseif($config->gestionnaire2_id == Auth::user()->id){
            $gest = 2;
        }

        return view('orders.orders-list', [
            'commandes' => $commandes,
            'gest' => $gest
        ]);
    }

    public function fichierBlClient(\App\Models\BlClient $bl, string $mode = 'inline')
    {
        if (empty($bl->fichier)) {
            return redirect()->route('orders.list')->with('error', "Aucun fichier client n'est associé à ce bon de commande.");
        }
        $absolute = Client::resolveStoragePath($bl->fichier);
        if (!$absolute) {
            return redirect()->route('orders.list')->with('error', "Le fichier client est introuvable sur le serveur (chemin enregistré: {$bl->fichier}).");
        }

        $original = basename($absolute);
        $ext = pathinfo($original, PATHINFO_EXTENSION);
        $numero = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) ($bl->numero ?: $bl->id));
        $downloadName = 'bon-client-' . $numero . ($ext ? '.' . $ext : '');

        $disposition = $mode === 'download' ? 'attachment' : 'inline';
        return response()->file($absolute, [
            'Content-Disposition' => $disposition . '; filename="' . $downloadName . '"',
        ]);
    }

    public function listeDesDevis(){
        return view('orders.listeDevis', [
            'devis' => Devis::where('deleted_at', null)->orderBy('created_at','desc')->get()
        ]);
    }

    public function detailDevis(Devis $devis){
        return view('orders.detailDevis',[
            'devis' => $devis
        ]);
    }

    public function commandesTraitees()
    {
        $commandes = Commande::liste(null, [Help::$COMMANDE_TERMINE]);

        return view('orders.commandeTraite', [
            'commandes' => $commandes
        ]);
    }

    public function BECommande($numero)
    {
        $commande = Commande::lireSurNumero($numero);
        $details = DetailCommande::liste(null, $commande->id, $commande->client_id);


        // Récuperer les livraison liées à la commande selon le type [avec_livraison, sans_livraison]

        if($commande->est_livrable){
            foreach ($details as $d) {
                $d->livs = Livraison::liste(null, $commande->client_id, $d->id);
            }

            $montantPaye = LignePaiement::where('service_id', $commande->id)
                ->where('service', Help::$COMMANDE)
                ->where('statut', Help::$STATUT_ACTIF)
                ->sum('montant');

        }else{
            foreach ($details as $d) {
                $d->livs = Livraison::listeSansLivraison(null, $commande->client_id, $d->id);
            }

            $montantPaye = LignePaiement::where('service_id', $commande->id)
                                    ->where('service', Help::$COMMANDE)
                                    ->where('statut', Help::$STATUT_ACTIF)
                                    ->sum('montant');
        }

        // Total net calculé depuis les LIGNES (cf. Commande::montantAPayer) :
        // montant_total n'a pas la même sémantique web (HT) / mobile (net final),
        // l'ancienne formule double-comptait TVA/livraison/remise pour les
        // commandes passées depuis le mobile -> badge "Paiement en cours" à tort.
        $montantAPayer = $commande->montantAPayer();

        // dd($montantAPayer, $montantPaye, $montantAPayer - $montantPaye);

        return view('orders.BECommande', [
            'commande' => $commande,
            'details' => $details,
            'restant' => $montantAPayer - $montantPaye,
            'montantAPayer' => $montantAPayer,
        ]);
    }

    /**
     * Version PDF téléchargeable du bon d'enlèvement (page /orders-be/{numero}).
     */
    public function BECommandePdf($numero)
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(120);

        $commande = Commande::lireSurNumero($numero);
        $details = DetailCommande::liste(null, $commande->id, $commande->client_id);

        if ($commande->est_livrable) {
            foreach ($details as $d) {
                $d->livs = Livraison::liste(null, $commande->client_id, $d->id);
            }
        } else {
            foreach ($details as $d) {
                $d->livs = Livraison::listeSansLivraison(null, $commande->client_id, $d->id);
            }
        }

        $montantPaye = LignePaiement::where('service_id', $commande->id)
            ->where('service', Help::$COMMANDE)
            ->where('statut', Help::$STATUT_ACTIF)
            ->sum('montant');

        // Même convention que BECommande : total net depuis les lignes.
        $montantAPayer = $commande->montantAPayer();

        try {
            $pdf = \PDF::loadView('orders.BECommande-pdf', [
                'commande'      => $commande,
                'details'       => $details,
                'restant'       => $montantAPayer - $montantPaye,
                'montantAPayer' => $montantAPayer,
                'config'        => Configuration::first(),
            ])
                ->setPaper('A4', 'landscape')
                ->setOptions([
                    'isRemoteEnabled' => false,
                    'isHtml5ParserEnabled' => true,
                    'defaultFont' => 'DejaVu Sans',
                ]);

            return $pdf->download('bon-commande-' . $commande->numero . '.pdf');
        } catch (\Throwable $e) {
            \Log::error('Erreur génération PDF BECommande : ' . $e->getMessage(), [
                'numero' => $numero,
                'trace'  => $e->getTraceAsString(),
            ]);
            return back()->with('error', 'Impossible de générer le PDF : ' . $e->getMessage());
        }
    }

    public function genererFacture(Commande $commande, Request $request){


        $request->validate([
            'enlevements' => 'required|array|min:1'
        ],[
            'enlevements.min' => 'Veuillez selectionner au moins un enlèvement.',
            'enlevements.required' => 'Veuillez selectionner au moins un enlèvement.',
        ]);

        $config = Configuration::first();
        // Taux TVA appliqué à TOUTES les factures (point 9). Source : config, fallback 18%.
        $tauxTva = (float) ($config?->tva ?? 18) / 100;

        // Toute la génération (création facture + liaison des enlèvements + calcul
        // du montant) est encapsulée dans UNE transaction : si une étape échoue,
        // rien n'est persisté -> on ne laisse plus de facture « fantôme » à 0.
        $facture = DB::transaction(function () use ($commande, $request, $tauxTva) {

            $facture = Facture::create([
                'numero' => Help::genererNumeroUnique('facture'),
                'numero_fne' => FneService::genererNumeroFne(),
                'user_id' => Auth::id(),
                'statut' => 2,
                'service' => Help::$COMMANDE,
                'service_id' => $commande->id,
                'client_id' => $commande->client_id,
                'fne_status' => 'pending',
            ]);

            $montantHt = 0;

            foreach ($request->enlevements as $env) {

                $enleve = Enlevement::find($env);
                if (!$enleve) {
                    continue;
                }

                // Ligne de commande liée (via la livraison). Si une donnée est incomplète
                // (enlèvement orphelin), on l'ignore au lieu de provoquer une erreur 500.
                $detail = optional($enleve->livraison)->detailCommande;
                if (!$detail) {
                    continue;
                }

                $enleve->update([
                    'facture_id' => $facture->id
                ]);

                // Quantité facturée : quantité servie si renseignée, sinon quantité de
                // l'enlèvement, sinon quantité commandée. Prix = prix de la ligne de commande.
                $qte = (float) ($enleve->qte_servi ?? $enleve->qte ?? $detail->qte ?? 0);
                $montantHt += $qte * (float) ($detail->prix ?? 0);
            }

            // TVA 18% appliquée sur le HT facturé (point 9).
            $montantTva = $montantHt * $tauxTva;

            // Supplément : livraison + remise n'est appliqué que pour la 1re facture liée à la commande.
            // La TVA, elle, est toujours recalculée à 18% du HT facturé (peu importe le nombre de factures).
            $supplement = 0;
            if ($commande->factures->count() <= 1) { // <=1 car on vient de créer la facture courante
                $supplement = $commande->cout_livraison_client - $commande->remise;
            }

            $facture->update([
                'montant' => $montantHt + $montantTva + $supplement,
            ]);

            return $facture;
        });

        // La certification FNE n'est PLUS déclenchée automatiquement ici.
        // La facture est créée avec fne_status = 'pending' et apparaît dans
        // la sidebar "Factures & Bons d'enlèvement" → "Factures non validées".
        // Le gestionnaire la valide manuellement (bouton « Valider ») qui
        // déclenchera alors FneService::signInvoice() et la fera basculer
        // dans "Factures validées".
        return redirect()->route('orders.BECommande', ['numero' => $commande->numero])
            ->with('success', 'Facture générée. Elle apparaîtra dans « Factures non validées » jusqu\'à validation auprès de la DGI.');
    }

    /**
     * Liste des factures non encore certifiées par la DGI (FNE).
     * (statut différent de 'certified' : pending / disabled / failed / null)
     */
    public function facturesNonValidees()
    {
        $factures = Facture::with(['client', 'commande', 'location', 'user'])
            ->where(function ($q) {
                $q->whereNull('fne_status')
                  ->orWhere('fne_status', '!=', 'certified');
            })
            ->orderByDesc('created_at')
            ->get();

        return view('orders.facturesNonValidees', [
            'factures' => $factures,
        ]);
    }

    /**
     * Liste des factures certifiées officiellement par la DGI (FNE).
     */
    public function facturesValidees()
    {
        $factures = Facture::with(['client', 'commande', 'location', 'user'])
            ->where('fne_status', 'certified')
            ->orderByDesc('fne_certified_at')
            ->get();

        return view('orders.facturesValidees', [
            'factures' => $factures,
        ]);
    }

    /**
     * Action « Valider » : déclenche la certification FNE auprès de la DGI
     * pour une facture créée précédemment (fne_status != 'certified').
     */
    public function validerFactureFne(Facture $facture)
    {
        $enlevementIds = Enlevement::where('facture_id', $facture->id)->pluck('id')->toArray();

        $result = FneService::signInvoice($facture, $enlevementIds);

        if ($result['success']) {
            return redirect()->route('orders.facturesValidees')
                ->with('success', 'Facture validée par la DGI - Réf : ' . ($result['response']['reference'] ?? ''));
        }

        return redirect()->route('orders.facturesNonValidees')
            ->with('warning', $result['message']);
    }

    /**
     * Génère et retourne le PDF de documentation de l'intégration FNE.
     */
    public function documentationFne(Request $request){

        $pdf = PDF::loadView('document.fne_integration_doc')
            ->setPaper('a4', 'portrait');

        $filename = 'documentation-fne-graviers-' . now()->format('Ymd') . '.pdf';

        if ($request->query('download') === '1') {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    /**
     * Relance la certification FNE d'une facture précédemment échouée
     * (ou créée pendant que la clé API n'était pas encore disponible).
     */
    public function recertifierFacture(Facture $facture){

        // On retrouve les enlèvements liés à cette facture pour reconstruire le payload.
        $enlevementIds = Enlevement::where('facture_id', $facture->id)->pluck('id')->toArray();

        $result = FneService::signInvoice($facture, $enlevementIds);

        $commande = $facture->commande;

        if ($result['success']) {
            return redirect()->route('orders.BECommande', ['numero' => $commande->numero])
                ->with('success', 'Facture certifiée par la DGI - Réf : ' . ($result['response']['reference'] ?? ''));
        }

        return redirect()->route('orders.BECommande', ['numero' => $commande->numero])
            ->with('warning', $result['message']);
    }

    /**
     * Génère une facture FNE pour une LOCATION (équivalent de genererFacture pour
     * les ventes). Crée une Facture(service=LOCATION, fne_status='pending') qui
     * apparaît dans « Factures non validées » jusqu'à validation DGI via
     * validerFactureFne (réutilisée telle quelle : signInvoice branche sur le
     * service et construit le payload depuis les lignes de location).
     */
    public function genererFactureLocation(Location $location){

        // Une seule facture par location : on évite les doublons.
        $existante = Facture::where('service', Help::$LOCATION)
            ->where('service_id', $location->id)
            ->first();
        if ($existante) {
            return back()->with('warning', 'Une facture a déjà été générée pour cette location.');
        }

        $tva       = (float) ($location->tvaLocation->montant ?? 0);   // TVA nette
        $livraison = (float) ($location->cout_livraison_client ?? 0);
        $remise    = (float) ($location->remise ?? 0);
        // Montant net cohérent avec le montant payé : HT - remise + TVA nette + livraison.
        $montant   = max(0, (float) $location->montant_total - $remise) + $tva + $livraison;

        Facture::create([
            'numero'     => Help::genererNumeroUnique('facture'),
            'numero_fne' => FneService::genererNumeroFne(),
            'user_id'    => Auth::id(),
            'montant'    => $montant,
            'statut'     => 2,
            'service'    => Help::$LOCATION,
            'service_id' => $location->id,
            'client_id'  => $location->client_id,
            'fne_status' => 'pending',
        ]);

        return back()->with('success', 'Facture de location générée. Elle apparaît dans « Factures non validées » jusqu\'à validation auprès de la DGI.');
    }

    /**
     * PDF de la facture d'une location (bouton « voir/télécharger » des listes FNE).
     * Réutilise la vue orders.recapLocation (document FNE « Facture de location »)
     * en y injectant les données FNE de la facture (numéro/QR officiels si certifiée).
     */
    public function factureLocation(Facture $facture, $action = 'voir'){

        $location = Location::with('detailLocation.produit.uniteProduit', 'tvaLocation', 'adresseLivraison', 'client')
            ->find($facture->service_id);

        $data = array_merge(
            [
                'location'  => $location,
                'facture'   => $facture,
                'config'    => Configuration::first(),
                'livraison' => 1,
            ],
            FneService::getDonneesFne($facture, $facture->client)
        );

        // Document FNE « Facture de location » (même modèle que la facture de vente,
        // sans les boutons de la page client).
        $pdf = PDF::loadView('document.factureLocation', $data);

        $nom = 'Facture_Location_' . $facture->numero . '.pdf';
        return $action === 'telecharger' ? $pdf->download($nom) : $pdf->stream($nom);
    }

    public function clientATerme()
    {

        $commandes = Commande::where('statut', 1)->orderBy('created_at', 'desc')->get();

        return view('orders.commandeClientaTerme', [
            'commandes' => $commandes
        ]);
    }

    public function reduction(Commande $commande)
    {
        return view('orders.reduction', [
            'commande' => $commande,
            'conf' => Configuration::first(),
        ]);
    }

    public function reductionTraitement(Commande $commande, Request $request)
    {

        // try {
            //code...

            $montantInitial = $commande->montant_total;

            if ($request->remise <= 0 || $request->remise == null) {
                return redirect()->back()->with('error', 'Montant ' . $request->montant . ' incorrect');
            }

            $config = Configuration::first();

            // dd($request->remise);
            // $client = Client::find($commande->client_id);
            // dd($client);

            // $nouveauMontant = $commande->montant_total - $montantReduit;

            $reduction = [
                'code' => Help::ChaineAleatoire(6),
                'libelle' => "Initialilisation réduction",
                // 'debut' => date("Y-m-d H:i:s"),
                // 'fin' => date("Y-m-d H:i:s"),
                'est_utilise' => false,
                'taux_reduction' => $request->remise,
                'client_id' => Auth::user()->id,
                'devis_id' => $commande->devis_id
            ];

            $reduction = Reduction::create($reduction);





            Mail::send(new emailReduction(
                User::find($reduction->client_id)->nom_prenoms,//$client->nom . ' ' . $client->prenoms,
                $request->remise,
                $commande,
                intVal($montantInitial),
                $config->email_tresorier
            ));

            /**
             * public string $nom_prenom,
               *                 public int $remise,
              *                  public Commande $commande,
               *                 public int $montant_initial,
               *                 public String $email
             */

            return redirect()->route('orders.list')->with('success', 'Réduction initiualisée');

        // } catch (\Throwable $th) {
        //     return view('layout.errorCatchBack');
        // }
    }

    public function confirmationReductionTraitement(Commande $commande){

        $reduction = $commande->devis->reduction;

        $montantReduit =  $commande->montant_total * ($reduction->taux_reduction/100);
        $commande->update([
                'remise' => $commande->remise + $montantReduit,
                // 'montant_total' => $commande->montant_total - $montantReduit,
            ]);


        $reduction->est_utilise = true;
        $reduction->update();

        return redirect()->route('orders.list')->with('success','Réduction appliquée');
    }

    public function traitementItem(Commande $commande, Produit $produit, Request $request)
    {

        $conf = Configuration::first();


        if ($request->date < today()->format('Y-m-d')) {
            return redirect()->route('orders.traitement.post', $commande)->with('error', 'Date invalide ');
        }

        $stock = StockProduit::lireCle($request->fournisseur, $produit->id);

        if ($request->qte > $stock->qte) {
            return redirect()->route('orders.traitement.post', $commande)->with('error', 'Le fournisseur sellectionné n’a pas assez de stock pour traiter cette commande');
        } else {

            $nouvelleQte =  $stock->qte - $request->qte;
            $qteEnlevee = $request->qte;
        }



        $detailCommande = $commande->detailCommande->where('produit_id', $request->produit)->first();
        // calcule de la distance
        $distance = Help::distance(
                        $commande->adresseLivraison->longitude,
                        $commande->adresseLivraison->latitude,
                        $commande->adresseLivraison->ville->region->long,
                        $commande->adresseLivraison->ville->region->lat,
                    );

        /**
         * la distance X le cout fixe X le nombre de voyage ( NOMBRE DE VOYAGE= quantité commandée / 40tonnes)
         */

         $vehicule = Vehicule::find($request->vehicule);

        $livreur = Livreur::find($request->livreur);

        $nbrlivraison = $request->qte / $vehicule->capacite;

        // Tarification du livreur (base ou km), appliquée telle quelle ; repli sur le
        // coût global (distance × coût fixe × voyages) si non configurée. On stocke la
        // décomposition (forfait_base / frais_km / distance_km) pour l'état "dette livreur".
        $tarif = $livreur->tarificationLivraison(
            (float) $distance,
            (float) ($distance * $conf->cout_liv_fixe * $nbrlivraison)
        );

        $livraison = Livraison::create([
            'numero' => uniqid(),
            'livreur_id' => $livreur->id,
            'vehicule_id' => $request->vehicule,
            'client_id' => $commande->client_id,
            'cout_livraison' => $tarif['total'],
            'forfait_base'   => $tarif['forfait_base'],
            'frais_km'       => $tarif['frais_km'],
            'distance_km'    => round((float) $distance, 2),
            'adresse_livraison_id' => $commande->adresse_livraison_id ,//$commande->adresse_livraison_id,
            'date_livraison' => $request->date,
            'qte' => $request->qte,
            'etat_livraison' => Help::$LIVRAISON_EN_ATTENTE,
            'detail_commande_id' => $detailCommande->id,
            'statut' => Help::$STATUT_ACTIF,
            'gestionnaire_id' => Auth::id(),
            'provenance' => Help::$COMMANDE,
            'type_livraison_id' => $commande->type_livraison_id,
            'date_affectation' => date('Y-m-d H:i:s'),
            'accepte' => 2,
            'livre_par' => 1
        ]);

        $detailCommande->update([
            'etat_livraison' => Help::$LIVRAISON_EN_TRAITEMENT
        ]);

        $commande->update([
            'etat_commande' => Help::$COMMANDE_EN_TRAITEMENT
        ]);

        $codeEnlevement = $this->generateCode();
        Enlevement::create([
            'fournisseur_id' => $request->fournisseur,
            'livraison_id' => $livraison->id,
            'produit_id' => $request->produit,
            'qte' => $qteEnlevee,
            'prix_fournisseur' => $request->prix_fournisseur,
            'livreur_id' => $request->livreur,
            'code_enleve' => $codeEnlevement,
            'gestionnaire_id' => Auth::id(),
            'statut' => Help::$STATUT_ACTIF,
        ]);

        // $vehicule = Vehicule::find($request->vehicule);

        $stock->update([
            'qte' => $nouvelleQte
        ]);


        Mail::send(new receptionCodeLivraison($livraison, $commande, $commande->client, $produit));

        return redirect()->route('orders.traitement', $commande)->with('success', 'Produit traitée');
    }
    public function traitementItemSansLivraison(Commande $commande, Produit $produit, Request $request){

        // dd($commande->detailCommande->where('produit_id', $request->produit)->first());
        // dd(Auth::user()->id);

        if ($request->date < today()->format('Y-m-d')) {
            return redirect()->route('orders.traitement.sansLivraison', $commande)->with('error', 'Date invalide ');
        }

        $stock = StockProduit::lireCle($request->fournisseur, $produit->id);

        if ($request->qte > $stock->qte) {
            return redirect()->route('orders.traitement.sansLivraison', $commande)->with('error', 'Le fournisseur sellectionné n’a pas assez de stock pour traiter cette commande');
        } else {

            $nouvelleQte =  $stock->qte - $request->qte;
            $qteEnlevee = $request->qte;
        }



        $detailCommande = $commande->detailCommande->where('produit_id', $request->produit)->first();

        $livraison = Livraison::create([
            'numero' => uniqid(),
            'livreur_id' => null,
            'vehicule_id' => null,
            'client_id' => $commande->client_id,
            'cout_livraison' => 0,
            'adresse_livraison_id' => null,
            'date_livraison' => $request->date,
            'qte' => $request->qte,
            'etat_livraison' => Help::$LIVRAISON_EN_TRAITEMENT,
            'detail_commande_id' => $detailCommande->id,
            'statut' => Help::$STATUT_ACTIF,
            'gestionnaire_id' => Auth::user()->id,
            'provenance' => Help::$COMMANDE,
            'type_livraison_id' => $commande->type_livraison_id,
            'accepte' => 1,
            'livre_par' => 2,
        ]);

        $detailCommande->update([
            'etat_livraison' => Help::$LIVRAISON_EN_TRAITEMENT
        ]);

        $commande->update([
            'etat_commande' => Help::$COMMANDE_EN_TRAITEMENT
        ]);

        $codeEnlevement = $this->generateCode();
        $enlevement = Enlevement::create([
            'fournisseur_id' => $request->fournisseur,
            'livraison_id' => $livraison->id,
            'produit_id' => $request->produit,
            'qte' => $qteEnlevee,
            'prix_fournisseur' => $request->prix_fournisseur,
            'livreur_id' => null,
            'code_enleve' => $codeEnlevement,
            'gestionnaire_id' => Auth::id(),
            'statut' => Help::$STATUT_ACTIF,
        ]);

        $stock->update([
            'qte' => $nouvelleQte
        ]);
        $url = "https://www.google.com/maps?q={$enlevement->fournisseur->latitude},{$enlevement->fournisseur->longitude}";

        Mail::send(new receptionCodeEnlevement($enlevement, $commande, $commande->client, $produit, $url));

        // Mail::send(new receptionCodeLivraison($livraison, $commande, $commande->client, $produit));

        return redirect()->route('orders.traitement.sansLivraison', $commande)->with('success', 'Produit traitée');
    }

    public function afficherVehicule($id)
    {
        $vehicule = Vehicule::liste($id);

        return response()->json($vehicule);
    }

    public function traitementPage(Commande $commande)
    {


        $paiementId = Paiement::where('client_id', '=', $commande->client_id)->value('id');


        // recuperation de la ligne de paiement concernée
        $data['lignePaiement'] = LignePaiement::where('paiement_id', '=', $paiementId)->first();

        $data['commande'] = $commande;
        $data['livreurs'] = Livreur::liste();
        //paiement
        $lastPaye = Paiement::liste($commande->devis_id, $commande->client_id);
        $data['paiement'] = $lastPaye->first();

        return view('orders.traitement', $data);
    }

    public function traitementSansLivraison(Commande $commande){
        $paiementId = Paiement::where('client_id', '=', $commande->client_id)->value('id');


        // recuperation de la ligne de paiement concernée
        $data['lignePaiement'] = LignePaiement::where('paiement_id', '=', $paiementId)->first();

        $data['commande'] = $commande;
        $data['livreurs'] = Livreur::liste();
        //paiement
        $lastPaye = Paiement::liste($commande->devis_id, $commande->client_id);
        $data['paiement'] = $lastPaye->first();

        return view('orders.traitementSansLivraison',$data);
    }

    // Traitement de l'affection
    public function traitement(Request $request, Commande $commande)
    {


        $detailCommande = DetailCommande::where('commande_id', '=', $commande->id)->where('statut', Help::$STATUT_ACTIF)->get();

        // dd($detailCommande);


        foreach ($detailCommande as $detail) {
            $details[] = $detail->qte;
        }


        $produit = $request->produit;
        $fournisseur = $request->fournisseur;
        $qte = $request->qte;
        $livreur = $request->livreur;
        $date = $request->date;

        // Distance (commande -> région) pour calculer le gain du livreur. Calculée
        // une fois (même adresse pour tous les items). Guard si l'adresse est absente.
        $conf = Configuration::first();
        $distanceTraitement = 0;
        $adr = $commande->adresseLivraison;
        if ($adr && $adr->ville && $adr->ville->region) {
            $distanceTraitement = Help::distance(
                $adr->longitude, $adr->latitude,
                $adr->ville->region->long, $adr->ville->region->lat
            );
        }

        $listCodeLivraison = [];
        foreach ($produit as $key => $unProduit) {
            $list = $detailCommande[$key]->id;


            if ($date[$key] < today()) {

                return redirect()->route('orders.traitement.post', $commande)->with('error', 'Date invalide  ' . $key + 1);
            }
            # code...

            // Tarification du livreur (base/km), décomposée, repli sur le coût global.
            $livreurObj  = Livreur::find($livreur[$key]);
            $nbrVoyages  = $qte[$key] / max(1, (float) ($conf->tonne_moyenne ?? 40));
            $coutGlobal  = (float) $distanceTraitement * (float) ($conf->cout_liv_fixe ?? 0) * (float) $nbrVoyages;
            $tarif = $livreurObj
                ? $livreurObj->tarificationLivraison((float) $distanceTraitement, $coutGlobal)
                : ['forfait_base' => $coutGlobal, 'frais_km' => 0.0, 'total' => $coutGlobal];

            $livraison = Livraison::create([
                'numero' => uniqid(),
                'livreur_id' => $livreur[$key],
                'client_id' => $commande->client_id,
                'adresse_livraison_id' => $commande->adresse_livraison_id,
                'date_livraison' => $date[$key],
                'qte' => $qte[$key],
                'cout_livraison' => $tarif['total'],
                'forfait_base'   => $tarif['forfait_base'],
                'frais_km'       => $tarif['frais_km'],
                'distance_km'    => round((float) $distanceTraitement, 2),
                'etat_livraison' => Help::$LIVRAISON_EN_ATTENTE,
                'detail_commande_id' => $detailCommande[$key]->id,
                'statut' => Help::$STATUT_ACTIF,
                'accepte' => 2,
                'livre_par' => 1
            ]);

            // array_push($listCodeLivraison,$livraison->numero);

            $lesDetails = DetailCommande::where('id', $detailCommande[$key]->id)->first();

            $lesDetails->update([
                'etat_livraison' => 2
            ]);

            $livraisonId = $livraison->id;

            $codeEnlevement = $this->generateCode();


            $stock = StockProduit::where('produit_id', $unProduit)->where('fournisseur_id', $fournisseur[$key])->first();
            if ($qte[$key] > $stock->qte) {

                $nouvelleQte = $qte[$key] - $stock->qte;
                $qteEnlevee = $stock->qte;
            } else {

                $nouvelleQte =  $stock->qte - $qte[$key];
                $qteEnlevee = $qte[$key];
            }
            if ($nouvelleQte < 0) {
                $nouvelleQte = 0;
            }

            $stock->update([
                'qte' => $nouvelleQte
            ]);

            // dd($unProduit,$fournisseur[$key],$stock->qte, $qte[$key]);
            // die;


            Enlevement::create([
                'fournisseur_id' => $fournisseur[$key],
                'livraison_id' => $livraisonId,
                'produit_id' => $produit[$key],
                'qte' => $qteEnlevee,
                'livreur_id' => $livreur[$key],
                'code_enleve' => $codeEnlevement,
                'gestionnaire_id' => Auth::id(), // agent ayant créé l'enlèvement
            ]);
        }



        // Mail::send(new receptionCodeLivraison($commande, $commande->client));



        // \Mckenziearts\Notify\Facades\LaravelNotify::success('Traitement éffectuté');
        return redirect()->route('orders.list')->with('success', 'Commande traitée');
    }

    public function ordersDetails($numero)
    {
        // Recuperation de l'identifiant du client lié à la commande
        $commande = Commande::where('numero', '=', $numero)->first();
        return view('orders.orders-details', [
            'commande' => $commande,
        ]);
    }

    public function oderItemPage($idDetailCommande)
    {

        // Récuperation de l'identifiant du produit
        $produitId = DetailCommande::where('id', '=', $idDetailCommande)->value('produit_id');


        //Récuperation des fournisseur liés au produit selectionner
        $produit = Produit::where('id', '=', $produitId)->first();

        $livreur = Livreur::all();

        return view('orders.item', [
            'produits' => $produit,
            'livreurs' => $livreur,
            'idDetailCommande' => $idDetailCommande
        ]);
    }

    public function oderItem($idDetailCommande, Request $request)
    {
        // Récuperation de l'identifiant de la commande
        $idCommande = DetailCommande::where('id', '=', $idDetailCommande)->value('commande_id');

        // Récuperation de l'identifiant du client
        $idClient = Commande::where('id', '=', $idCommande)->value('client_id');

        // Récuperation de l'identifiant de l'adresse de livraison
        $idAdresse = Commande::where('id', '=', $idCommande)->value('adresse_livraison_id');

        $detail = DetailCommande::where('id', '=', $idDetailCommande)->first();

        $livraisonData = [
            'numero' => uniqid(5),
            'livreur_id' => $request->livreur,
            'client_id' => $idClient,
            'commande_id' => $idCommande,
            'adresse_livraison_id' => $idAdresse,
            'date_livraison' => now()->addDay(3),
            'accepte' => 2,
            'livre_par' => 1
        ];

        $livraison = Livraison::create($livraisonData);
        $idLivraison = $livraison->id;

        $enlevementData = [
            'fournisseur_id' => $request->fournisseur,
            'livraison_id' => $idLivraison,
            'qte' => $detail->qte,
            'matricule_vehicule' => '5784SC01',
            'produit_id' => $detail->produit_id
        ];

        $enlevement = Enlevement::create($enlevementData);

        $detail->update([
            'statut' => 'assigné'
        ]);
        return redirect()->route('orders.details', $idCommande);
    }

    /** Private function  */
    private function generateCode()
    {

        $gCode = Help::ChaineAleatoire(5);
        $deja = Enlevement::where('code_enleve',  $gCode)->first();
        if (! is_null($deja)) {
            $this->generateCode();
        } else {
            return Str::start($gCode, 'ENV');
        }
    }
}
