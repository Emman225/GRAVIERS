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
            'gest' => $gest,
            // Réductions en attente de validation, indexées par commande. Chargées
            // en UNE requête : appeler Commande::reductionEnAttente() dans la
            // boucle d'affichage interrogerait la base à chaque ligne.
            'reductionsEnAttente' => Reduction::reductionsEnAttentePour($commandes),
            'tresorier' => $config->gestionnaire2,
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
        // L'écran, le PDF et le Word partagent la même préparation : c'est ce qui
        // garantit qu'ils affichent les mêmes lignes et les mêmes montants.
        return view('orders.BECommande', $this->donneesBonEnlevement($numero));
    }

    /**
     * Version PDF téléchargeable du bon d'enlèvement (page /orders-be/{numero}).
     */
    public function BECommandePdf($numero)
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(120);

        $donnees  = $this->donneesBonEnlevement($numero);
        $commande = $donnees['commande'];

        try {
            $pdf = \PDF::loadView('orders.BECommande-pdf', $donnees)
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

    /**
     * Version Word téléchargeable du bon d'enlèvement.
     *
     * Word ouvre nativement un document HTML : on lui sert donc la MÊME vue que
     * le PDF, avec le type de contenu qui lui indique de l'ouvrir. La mise en
     * page, l'en-tête et le logo sont ainsi rigoureusement ceux du PDF — un
     * document reconstitué depuis les tableaux de l'écran aurait divergé au
     * premier changement de l'un des deux.
     */
    public function BECommandeWord($numero)
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(120);

        $donnees  = $this->donneesBonEnlevement($numero);
        $commande = $donnees['commande'];
        $nom      = 'bon-commande-' . $commande->numero . '.doc';

        $html = view('orders.BECommande-pdf', $donnees)->render();

        // Le BOM garantit les accents à l'ouverture ; sans lui, Word retombe sur
        // son encodage régional et « enlèvement » devient illisible.
        return response("\u{FEFF}" . $html, 200, [
            'Content-Type'        => 'application/msword; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $nom . '"',
        ]);
    }

    /**
     * Données communes du bon d'enlèvement, partagées par l'écran, le PDF et le
     * Word — pour qu'aucun des trois ne se mette à afficher autre chose.
     */
    private function donneesBonEnlevement($numero): array
    {
        $commande = Commande::lireSurNumero($numero);
        $details  = DetailCommande::liste(null, $commande->id, $commande->client_id);

        foreach ($details as $d) {
            $d->livs = $commande->est_livrable
                ? Livraison::liste(null, $commande->client_id, $d->id)
                : Livraison::listeSansLivraison(null, $commande->client_id, $d->id);
        }

        // On rattache à chaque ligne son bon d'enlèvement, chargé en UNE requête.
        // L'écran a besoin de savoir si le bon est facturable et s'il a été servi
        // partiellement : ces deux règles vivent sur le modèle, et non dans la
        // vue, pour que le bouton affiché et l'action exécutée disent la même chose.
        $identifiants = collect($details)
            ->flatMap(fn ($d) => collect($d->livs)->pluck('id_enlevement'))
            ->filter()->unique()->values()->all();

        $bons = Enlevement::with('livraison.detailCommande.commande')
            ->whereIn('id', $identifiants)->get()->keyBy('id');

        foreach ($details as $d) {
            foreach ($d->livs as $liv) {
                $liv->bon = $bons->get($liv->id_enlevement);
            }
        }

        $montantPaye = LignePaiement::where('service_id', $commande->id)
            ->where('service', Help::$COMMANDE)
            ->where('statut', Help::$STATUT_ACTIF)
            ->sum('montant');

        // Total net depuis les LIGNES (cf. Commande::montantAPayer) : montant_total
        // porte le HT côté web et le net final côté mobile.
        $montantAPayer = $commande->montantAPayer();

        return [
            'commande'      => $commande,
            'details'       => $details,
            'restant'       => $montantAPayer - $montantPaye,
            'montantAPayer' => $montantAPayer,
            'config'        => Configuration::first(),
            // Combien de bons le bouton « tout facturer » emporterait. Compté ici
            // pour que le libellé annonce à l'avance ce qui va être facturé.
            'nbFacturables' => $bons->filter(fn (Enlevement $e) => $e->estFacturable())->count(),
        ];
    }

    public function genererFacture(Commande $commande, Request $request){


        $request->validate([
            'enlevements' => 'required|array|min:1'
        ],[
            'enlevements.min' => 'Veuillez selectionner au moins un enlèvement.',
            'enlevements.required' => 'Veuillez selectionner au moins un enlèvement.',
        ]);

        $this->creerFacturePourEnlevements($commande, $request->enlevements);

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
     * Facture DGI d'UN SEUL bon d'enlèvement.
     *
     * Le formulaire de la page permet déjà de facturer plusieurs bons d'un coup.
     * Il ne convient pas au bon servi PARTIELLEMENT : celui-là se facture pour
     * ce qu'il a réellement livré, sans attendre ni le reliquat ni les autres
     * lignes de la commande. D'où cette action, ligne par ligne.
     *
     * Le chemin de création est rigoureusement le même — même numérotation,
     * même calcul, même statut « en attente de certification ». Seule la
     * sélection change.
     */
    public function genererFactureEnlevement(Enlevement $enlevement)
    {
        $commande = $enlevement->livraison?->detailCommande?->commande;

        if (!$commande || !$commande->id) {
            return back()->with('error', "Ce bon n'est rattaché à aucune commande : il ne peut pas être facturé.");
        }

        // La règle d'éligibilité vit sur le modèle : l'écran et l'action la
        // partagent, sinon un bouton masqué resterait atteignable par l'URL.
        if (!$enlevement->estFacturable()) {
            $motif = $enlevement->facture_id !== null
                ? 'Ce bon est déjà rattaché à une facture.'
                : "Ce bon n'est pas encore facturable : la livraison doit être clôturée, "
                  . "ou le fournisseur doit avoir validé le retrait sur place.";

            return back()->with('error', $motif);
        }

        $facture = $this->creerFacturePourEnlevements($commande, [$enlevement->id]);

        return redirect()->route('orders.BECommande', ['numero' => $commande->numero])
            ->with('success', sprintf(
                'Facture %s générée pour le bon %s (%s servie). Elle apparaîtra dans '
                . '« Factures non validées » jusqu\'à validation auprès de la DGI.',
                $facture->numero,
                $enlevement->code_enleve ?? ('n° ' . $enlevement->id),
                rtrim(rtrim(number_format($enlevement->quantiteAPayer(), 2, ',', ' '), '0'), ',')
            ));
    }

    /**
     * Facture DGI de TOUS les bons facturables de la commande, en une fois.
     *
     * Le formulaire à cases oblige à cocher ligne par ligne : sur une commande
     * livrée en plusieurs fois, c'est long et l'on finit par en oublier un.
     * Cette action prend tout ce qui est facturable à l'instant, et rien
     * d'autre — un bon déjà facturé ou dont la livraison n'est pas close reste
     * de côté, exactement comme il resterait décoché.
     *
     * Le tout part dans UNE SEULE facture : c'est le sens de « d'un coup », et
     * cela évite d'émettre dix documents fiscaux là où un suffit.
     */
    public function genererFactureTotale(Commande $commande)
    {
        $facturables = $this->bonsFacturables($commande);

        if ($facturables->isEmpty()) {
            return back()->with('error', "Aucun bon d'enlèvement facturable sur cette commande.");
        }

        $facture = $this->creerFacturePourEnlevements($commande, $facturables->pluck('id')->all());

        return redirect()->route('orders.BECommande', ['numero' => $commande->numero])
            ->with('success', sprintf(
                'Facture %s générée pour %d bon(s) d\'enlèvement. Elle apparaîtra dans '
                . '« Factures non validées » jusqu\'à validation auprès de la DGI.',
                $facture->numero,
                $facturables->count()
            ));
    }

    /**
     * Les bons de la commande qui peuvent être facturés à cet instant.
     *
     * La règle d'éligibilité vit sur le modèle (Enlevement::estFacturable) : on
     * la lit, on ne la redit pas — sinon l'écran, le bouton unitaire et le
     * bouton global finiraient par ne plus s'accorder.
     */
    private function bonsFacturables(Commande $commande)
    {
        return Enlevement::with('livraison.detailCommande.commande')
            ->whereNull('deleted_at')
            ->whereHas('livraison.detailCommande', fn ($q) => $q->where('commande_id', $commande->id))
            ->get()
            ->filter(fn (Enlevement $e) => $e->estFacturable())
            ->values();
    }

    /**
     * Crée la facture couvrant les bons d'enlèvement désignés.
     *
     * Toute la génération (création, liaison des bons, calcul du montant) tient
     * dans UNE transaction : si une étape échoue, rien n'est persisté — on ne
     * laisse plus de facture « fantôme » à 0.
     *
     * @param  array<int|string>  $enlevementIds
     */
    private function creerFacturePourEnlevements(Commande $commande, array $enlevementIds): Facture
    {
        $config = Configuration::first();
        // Taux TVA appliqué à TOUTES les factures (point 9). Source : config, fallback 18%.
        $tauxTva = (float) ($config?->tva ?? 18) / 100;

        return DB::transaction(function () use ($commande, $enlevementIds, $tauxTva) {

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

            foreach ($enlevementIds as $env) {

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

            // Livraison et remise ne s'imputent que sur la 1re facture liée à la
            // commande. <=1 car on vient de créer la facture courante.
            $premiere = $commande->factures()->count() <= 1;
            $remiseImputee = $premiere ? (float) ($commande->remise ?? 0) : 0.0;
            $supplement = $premiere
                ? (float) ($commande->cout_livraison_client ?? 0) - $remiseImputee
                : 0.0;

            // La TVA s'assied sur le HT REMISE DÉDUITE, comme à la commande.
            //
            // Elle était calculée sur le HT BRUT : la remise était donc retaxée,
            // et la facture dépassait le dû de 18 % de la remise — la commande
            // 105 facturée 5 004 pour 4 874, la 107 facturée 182 pour 161. Le
            // client voyait alors un « versé en trop » qui n'existait pas.
            //
            // La remise se déduit ici AU PRORATA du HT facturé, alors qu'elle
            // s'impute en valeur sur la seule première facture. Retrancher la
            // remise entière de la première tranche ne suffit pas : sur la
            // commande 107, la remise (114) dépassait le HT du premier bon
            // (100), le surplus était perdu et la TVA totale retombait à 18 au
            // lieu de 15. Au prorata, la somme des TVA facturées retrouve
            // exactement celle enregistrée à la commande.
            $htCommande = (float) $commande->montantHT();
            $partRemise = $htCommande > 0
                ? min(1.0, (float) ($commande->remise ?? 0) / $htCommande)
                : 0.0;
            $montantTva = $montantHt * (1 - $partRemise) * $tauxTva;

            // Pas de plafonnement au reste dû ici : une commande sans TVA
            // enregistrée — le transport en est exonéré — a un « dû » inférieur
            // à la facture, qui applique 18 % au HT servi. Plafonner
            // tronquerait ces factures-là.
            $facture->update([
                'montant' => $montantHt + $montantTva + $supplement,
            ]);

            return $facture;
        });
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
            // La vue cherchait la réduction par $commande->devis?->reduction :
            // muette pour une commande sans devis (comptant, mobile).
            'reduction' => $commande->reductionEnAttente(),
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

            // Une demande en attente n'empêchait pas d'en saisir une seconde :
            // la page réaffichait un formulaire vierge sans rien indiquer, et
            // les demandes s'empilaient sur la même commande.
            if ($dejaDemandee = $commande->reductionEnAttente()) {
                return redirect()->back()->with(
                    'error',
                    sprintf(
                        "Une réduction de %s%% est déjà en attente de validation sur la commande %s (demandée par %s). Elle doit être validée ou abandonnée avant d'en saisir une autre.",
                        $dejaDemandee->taux_reduction,
                        $commande->numero,
                        $dejaDemandee->user?->nom_prenoms ?: 'un administrateur'
                    )
                );
            }

            $config = Configuration::first();

            // dd($request->remise);
            // $client = Client::find($commande->client_id);
            // dd($client);

            // $nouveauMontant = $commande->montant_total - $montantReduit;

            $reduction = [
                'code' => Help::ChaineAleatoire(6),
                'libelle' => "Initialisation réduction",
                // Les colonnes debut et fin sont OBLIGATOIRES en base et n'ont
                // aucune valeur par défaut. Laissées en commentaire, elles
                // faisaient échouer l'insertion sur tout serveur en mode SQL
                // strict — c'est le cas de la production — et le bouton
                // « Initialiser la réduction » répondait donc systématiquement
                // par une erreur 500.
                //
                // La fenêtre est celle du jour, comme le prévoyait le code
                // d'origine. Elle ne gêne pas la confirmation par le trésorier,
                // qui ne contrôle pas ces dates, et elle évite qu'une réduction
                // interne à une commande reste indéfiniment saisissable comme
                // code promo dans le panier d'un client (appliquerCodePromo()
                // accepte n'importe quelle ligne de la table reduction).
                'debut' => date('Y-m-d'),
                'fin' => date('Y-m-d'),
                'est_utilise' => false,
                'taux_reduction' => $request->remise,
                // client_id porte une CLÉ ÉTRANGÈRE vers la table client. On y
                // écrivait l'identifiant de l'ADMINISTRATEUR connecté, c'est-à-dire
                // un identifiant de la table users : dès que cet administrateur
                // n'était pas lui-même client — le cas courant — la base refusait
                // l'insertion (contrainte reduction_client_id_foreign) et la page
                // répondait par une erreur 500.
                //
                // La bonne répartition est celle que le reste du code attend déjà :
                //   client_id = le client qui bénéficie de la réduction,
                //               c'est ainsi que Reduction::liste() la retrouve ;
                //   user_id   = l'administrateur qui la demande, c'est la relation
                //               user() qu'affiche l'écran de confirmation sous
                //               « Demandeur de réduction » — cette ligne restait
                //               vide puisque la colonne n'était jamais renseignée.
                'client_id' => $commande->client_id,
                'user_id' => Auth::user()->id,
                'devis_id' => $commande->devis_id,
                // Rattachement DIRECT à la commande. Le seul lien était le devis :
                // une commande comptant ou venue de l'application n'en a pas, la
                // réduction naissait donc orpheline et l'écran de confirmation
                // affichait « Pas de demande de réduction », sans recours.
                'commande_id' => $commande->id,
            ];

            $reduction = Reduction::create($reduction);


            // L'avis au trésorier ne doit pas décider du sort de la réduction :
            // elle est déjà enregistrée. Une boîte mail injoignable rendait la
            // page en erreur alors que le traitement avait abouti.
            try {
                Mail::send(new emailReduction(
                    // Le nom annoncé au trésorier est celui du DEMANDEUR, donc
                    // celui de l'administrateur : il se lit désormais dans
                    // user_id, et non plus dans client_id.
                    User::find($reduction->user_id)->nom_prenoms,
                    $request->remise,
                    $commande,
                    intVal($montantInitial),
                    $config->email_tresorier
                ));
            } catch (\Throwable $e) {
                \Log::warning('Email initialisation de réduction non envoyé: ' . $e->getMessage());
            }

            /**
             * public string $nom_prenom,
               *                 public int $remise,
              *                  public Commande $commande,
               *                 public int $montant_initial,
               *                 public String $email
             */

            // Le message se contentait de « Réduction initialisée » : rien ne
            // disait que le montant dû par le client n'avait pas encore bougé,
            // ni qu'une seconde signature était attendue.
            $tresorier = $config?->gestionnaire2?->nom_prenoms;

            $message = $config?->gestionnaire2_id
                ? sprintf(
                    'Réduction de %s%% enregistrée sur la commande %s. Elle sera appliquée après validation par le trésorier%s : le montant dû par le client est inchangé jusque-là.',
                    $request->remise,
                    $commande->numero,
                    $tresorier ? ' ' . $tresorier : ''
                )
                : sprintf(
                    "Réduction de %s%% enregistrée sur la commande %s, mais AUCUN trésorier n'est configuré (second gestionnaire) : personne ne peut la valider, elle ne s'appliquera pas.",
                    $request->remise,
                    $commande->numero
                );

            return redirect()->route('orders.list')->with(
                $config?->gestionnaire2_id ? 'success' : 'warning',
                $message
            );

        // } catch (\Throwable $th) {
        //     return view('layout.errorCatchBack');
        // }
    }

    public function confirmationReductionTraitement(Commande $commande){

        // $commande->devis->reduction : sur une commande sans devis, la lecture
        // se faisait sur null et la page répondait par une erreur 500.
        $reduction = $commande->reductionEnAttente();

        if (!$reduction) {
            return redirect()->route('orders.list')
                ->with('error', "Aucune réduction en attente sur la commande {$commande->numero}.");
        }

        // Base de calcul : le MONTANT HT, celui qu'annonce l'écran de
        // confirmation. On appliquait le taux à la colonne montant_total, qui ne
        // contient pas la même chose selon l'origine de la commande : le HT pour
        // le site, le net final (TVA et livraison comprises) pour l'application.
        // Une remise de 10 % accordée sur une commande mobile était donc calculée
        // sur un montant supérieur à celui affiché au gestionnaire.
        $montantReduit = $commande->montantHT() * ($reduction->taux_reduction / 100);

        $commande->update([
            'remise' => $commande->remise + $montantReduit,
        ]);

        $reduction->est_utilise = true;
        $reduction->save();

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

        // Rotations imposées par la quantité au véhicule affecté, arrondies au
        // supérieur : un quart de chargement demande un déplacement complet.
        $nbrlivraison = Livreur::nombreDeVoyages(
            (float) $request->qte,
            (float) ($vehicule->capacite ?? 0),
            (float) ($conf->tonne_moyenne ?? 0)
        );

        // Tarification du livreur, MULTIPLIÉE PAR LE NOMBRE DE VOYAGES : trois
        // rotations, c'est trois déplacements — donc trois fois la part fixe et
        // trois fois le carburant. Le client, lui, paie déjà un forfait par
        // voyage ; le livreur n'en touchait qu'un seul. Repli sur le coût global
        // si sa tarification n'est pas configurée. On stocke la décomposition
        // (forfait_base / frais_km / distance_km) pour l'état "dette livreur".
        $tarif = $livreur->tarificationLivraison(
            (float) $distance,
            (float) ($distance * $conf->cout_liv_fixe * $nbrlivraison),
            $nbrlivraison
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

            // Tarification du livreur, décomposée, multipliée par les rotations.
            //
            // Le nombre de voyages se comptait ici sur « tonne_moyenne » (40),
            // une moyenne de configuration : avec un camion de 20 tonnes, 60
            // tonnes n'y valaient qu'un voyage et demi au lieu de trois. On
            // s'appuie désormais sur la capacité du VÉHICULE AFFECTÉ, comme
            // l'autre écran de traitement, la moyenne ne servant que de repli.
            $livreurObj  = Livreur::find($livreur[$key]);

            // Le formulaire de cet écran ne poste qu'un véhicule (champ au nom
            // simple), là où les autres champs sont indexés : on accepte les deux
            // formes plutôt que de supposer. Sans véhicule connu, on retombe sur
            // la moyenne de configuration.
            $vehiculeBrut = $request->vehicule;
            $idVehicule   = is_array($vehiculeBrut) ? ($vehiculeBrut[$key] ?? null) : $vehiculeBrut;
            $vehiculeObj  = $idVehicule ? Vehicule::find($idVehicule) : null;

            $nbrVoyages  = Livreur::nombreDeVoyages(
                (float) $qte[$key],
                (float) ($vehiculeObj->capacite ?? 0),
                (float) ($conf->tonne_moyenne ?? 0)
            );
            $coutGlobal  = (float) $distanceTraitement * (float) ($conf->cout_liv_fixe ?? 0) * (float) $nbrVoyages;
            $tarif = $livreurObj
                ? $livreurObj->tarificationLivraison((float) $distanceTraitement, $coutGlobal, $nbrVoyages)
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
                // Rupture : on enlève tout le stock disponible et il ne reste RIEN.
                // L'ancien calcul ($qte - $stock) laissait le MANQUANT (positif) comme
                // stock restant -> des tonnes inexistantes redevenaient vendables.
                $nouvelleQte = 0;
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
