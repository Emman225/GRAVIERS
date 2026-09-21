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
use App\Models\DemandeLivraison;
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

    public function commandesTraitees(Request $request)
    {
        $commandes = Commande::liste(null, [Help::$COMMANDE_TERMINE]);

        // FILTRE ENTRE DEUX DATES.
        //
        // Aucune borne par défaut : l'écran a toujours montré tout l'historique,
        // et en restreindre l'affichage sans qu'on l'ait demandé ferait croire à
        // des commandes disparues. Le filtre s'applique donc seulement si on le
        // remplit — et une seule des deux bornes suffit.
        $du = $request->input('du') ?: null;
        $au = $request->input('au') ?: null;

        if ($du || $au) {
            $commandes = collect($commandes)->filter(function ($commande) use ($du, $au) {
                $date = $commande->date_commande ?? $commande->created_at;

                if (!$date) {
                    return false;
                }

                $jour = \Carbon\Carbon::parse($date)->format('Y-m-d');

                return (!$du || $jour >= $du) && (!$au || $jour <= $au);
            })->values();
        }

        return view('orders.commandeTraite', [
            'commandes' => $commandes,
            'du'        => $du,
            'au'        => $au,
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
            $livs = $commande->est_livrable
                ? Livraison::liste(null, $commande->client_id, $d->id)
                : Livraison::listeSansLivraison(null, $commande->client_id, $d->id);

            // DU PLUS ANCIEN AU PLUS RÉCENT.
            //
            // `Livraison::liste()` trie du plus récent au plus ancien — l'ordre
            // qui convient à une liste de travail, où l'on traite d'abord ce qui
            // vient d'arriver. Ici on lit l'HISTOIRE d'une commande : les
            // enlèvements se suivent dans l'ordre où ils ont eu lieu.
            //
            // Le tri se fait ici et non dans le modèle : `liste()` sert aussi
            // les écrans de suivi des livreurs, où l'ordre inverse est le bon.
            $d->livs = collect($livs)->sortBy('id')->values();
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

        // CE QUI EST ENCAISSÉ MAIS PAS ENCORE VALIDÉ.
        //
        // Un règlement de client à terme est enregistré au statut 2 : il attend
        // la seconde validation, et ne compte donc pas encore dans le payé.
        // L'écran affichait alors « Aucun paiement effectué » — ce qui est faux
        // pour le client, qui a versé, et pour le caissier, qui a encaissé.
        //
        // On distingue les deux : rien reçu, ou reçu mais pas encore contrôlé.
        $montantEnAttente = (float) LignePaiement::where('service_id', $commande->id)
            ->where('service', Help::$COMMANDE)
            ->where('statut', 2)
            ->sum('montant');

        // Total net depuis les LIGNES (cf. Commande::montantAPayer) : montant_total
        // porte le HT côté web et le net final côté mobile.
        $montantAPayer = $commande->montantAPayer();

        return [
            'commande'      => $commande,
            'details'       => $details,
            'restant'       => $montantAPayer - $montantPaye,
            'montantAPayer' => $montantAPayer,
            'montantEnAttente' => $montantEnAttente,
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

            // LE MONTANT D'ABORD, LA FACTURE ENSUITE.
            //
            // Constaté le 10/09/2026 sur la commande 692741 (1 427 800 F) : le
            // client avait une avance de 912 385 F, imputée à la commande ; cette
            // imputation avait émis une facture sur règlement de 912 385 F. À
            // l'enlèvement, cette méthode facturait TOUTE la quantité servie :
            // 1 427 800 F de plus — la commande était facturée 2 340 185 F, et le
            // guichet des créances réclamait 1 427 800 F là où 515 415 F
            // restaient dus. Ce qui a déjà été facturé sur règlement ne se
            // refacture pas : la facture d'enlèvement couvre le RESTE À FACTURER
            // de la commande (dû − déjà facturé), jamais plus.
            $enlevements = collect($enlevementIds)
                ->map(fn ($env) => Enlevement::find($env))
                ->filter(fn ($e) => $e && optional($e->livraison)->detailCommande)
                ->values();

            $montantHt = 0;
            foreach ($enlevements as $enleve) {
                $detail = $enleve->livraison->detailCommande;
                $qte = (float) ($enleve->qte_servi ?? $enleve->qte ?? $detail->qte ?? 0);
                $montantHt += $qte * (float) ($detail->prix ?? 0);
            }

            $facturesExistantes = Facture::where('service', Help::$COMMANDE)
                ->where('service_id', $commande->id)
                ->orderBy('created_at')->orderBy('id')
                ->get();

            $premiere = $facturesExistantes->isEmpty();
            $remiseImputee = $premiere ? (float) ($commande->remise ?? 0) : 0.0;
            $tvaTransportImputee = $premiere ? (float) ($commande->tva_transport ?? 0) : 0.0;
            $supplement = $premiere
                ? (float) ($commande->cout_livraison_client ?? 0) + $tvaTransportImputee - $remiseImputee
                : 0.0;

            $htCommande = (float) $commande->montantHT();
            $partRemise = $htCommande > 0
                ? min(1.0, (float) ($commande->remise ?? 0) / $htCommande)
                : 0.0;
            $montantTva = $montantHt * (1 - $partRemise) * $tauxTva;
            // L'AIRSI de la commande est porté par la PREMIÈRE facture, comme la
            // remise et le transport (10/09/2026).
            $airsiImpute = $premiere ? (float) ($commande->airsi ?? 0) : 0.0;
            $montant    = \Help::arrondiFranc($montantHt + $montantTva + $supplement + $airsiImpute);

            // Plafond : le reste à facturer sur la commande (dû − déjà facturé,
            // factures sur règlement comprises). Une commande n'est jamais
            // facturée au-delà de ce qu'elle doit.
            if ($facturesExistantes->isNotEmpty()) {
                $resteAFacturer = \Help::arrondiFranc(max(0,
                    \App\Services\FacturationCommande::montantDu($commande)
                    - \App\Services\FacturationCommande::montantDejaFacture($commande)));

                if ($resteAFacturer < 1) {
                    // Tout est déjà facturé (le client avait réglé d'avance la
                    // totalité) : le bon rejoint la dernière facture de la
                    // commande, aucune facture nouvelle — surtout pas à 0.
                    $porteuse = $facturesExistantes->last();
                    foreach ($enlevements as $enleve) {
                        $enleve->update(['facture_id' => $porteuse->id]);
                    }

                    return $porteuse;
                }

                $montant = min($montant, $resteAFacturer);
            }

            $facture = Facture::create([
                'numero' => Help::genererNumeroUnique('facture'),
                'numero_fne' => FneService::genererNumeroFne(),
                'user_id' => Auth::id(),
                'statut' => 2,
                'service' => Help::$COMMANDE,
                'service_id' => $commande->id,
                'client_id' => $commande->client_id,
                'fne_status' => 'pending',
                'montant' => $montant,
                'tva_transport_applique' => $tvaTransportImputee,
                'airsi_applique' => $airsiImpute,
            ]);

            foreach ($enlevements as $enleve) {
                $enleve->update(['facture_id' => $facture->id]);
            }

            // Les règlements déjà validés sur la commande (avance imputée,
            // encaissement en agence) rejoignent cette facture : elle naît
            // « payée » à hauteur de ce qui l'est, une seule facture DGI par
            // enlèvement (10/09/2026).
            \App\Services\FacturationCommande::rattacherLesReglements($commande, $facture);

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
            // La facture certifiée part au client (lot 93), après la réponse.
            \App\Services\CourrielFactureFne::envoyer($facture);

            return redirect()->route('orders.facturesValidees')
                ->with('success', 'Facture validée par la DGI - Réf : ' . ($result['response']['reference'] ?? '') . ' — envoyée au client par courriel.'
                    . FneService::mentionStickers($result['response'] ?? []));
        }

        return redirect()->route('orders.facturesNonValidees')
            ->with('warning', $result['message']);
    }

    /** FACTURE D'AVOIR (lot 92, 16/09/2026) : le formulaire, sur une facture certifiée. */
    public function nouvelAvoir(Facture $facture)
    {
        if ($facture->estUnAvoir() || !$facture->isCertifiedFne()) {
            return redirect()->route('orders.facturesValidees')
                ->with('warning', "Un avoir ne s'établit que sur une facture certifiée par la DGI.");
        }
        if (!Facture::avoirsDisponibles()) {
            return redirect()->route('orders.facturesValidees')
                ->with('warning', "La base n'est pas à jour pour les factures d'avoir : lancez « php artisan migrate --force » dans public_html/graviers (migration 2026_09_16_100000), puis réessayez.");
        }

        return view('orders.factureAvoir', [
            'facture'  => $facture,
            'articles' => $facture->articlesCertifies(),
            'avoirs'   => $facture->avoirs,
        ]);
    }

    /** L'avoir est certifié par la DGI, puis enregistré ; sans certification, rien n'est créé. */
    public function emettreAvoir(Facture $facture, Request $request)
    {
        $request->validate([
            'motif'     => 'required|string|max:255',
            'quantites' => 'required|array',
        ], [
            'motif.required'     => "Le motif de l'avoir est obligatoire.",
            'quantites.required' => 'Indiquez au moins une quantité à créditer.',
        ]);

        if (!Facture::avoirsDisponibles()) {
            return back()->withInput()->with('warning', "La base n'est pas à jour pour les factures d'avoir : lancez « php artisan migrate --force » dans public_html/graviers, puis réessayez.");
        }
        $resultat = \App\Services\FactureAvoir::emettre($facture, (array) $request->input('quantites', []),
            (string) $request->input('motif'), Auth::id());

        if ($resultat['success']) {
            return redirect()->route('orders.facturesValidees')->with('success', $resultat['message']);
        }

        return back()->withInput()->with('warning', $resultat['message']);
    }

    /** Le document « Facture d'avoir ». */
    public function factureAvoir(Facture $facture, $action = 'voir')
    {
        if (!$facture->estUnAvoir()) {
            abort(404);
        }
        if ($r = \App\Services\DocumentDgi::reponse($facture, (string) $action)) {
            return $r;
        }
        $pdf = \App\Services\FactureAvoir::pdf($facture);
        $nom = 'facture-avoir-' . $facture->numero . '.pdf';

        return $action === 'telecharger' ? $pdf->download($nom) : $pdf->stream($nom);
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
            \App\Services\CourrielFactureFne::envoyer($facture);

            return redirect()->route('orders.BECommande', ['numero' => $commande->numero])
                ->with('success', 'Facture certifiée par la DGI - Réf : ' . ($result['response']['reference'] ?? '') . ' — envoyée au client par courriel.');
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

        // LE MONTANT DE LA FACTURE EST CELUI QUE LE DOCUMENT IMPRIME.
        //
        // Il partait de `location.montant_total`, auquel on RAJOUTAIT la TVA et
        // la livraison. Or cette colonne les contient déjà : la facture
        // U260000000002 annonçait 67 840 à payer sous des lignes qui en
        // totalisaient 29 960 — TVA et livraison comptées deux fois.
        //
        // Le HT se relit donc depuis les LIGNES, seule source qui ne prête pas à
        // interprétation, et la TVA se recalcule sur ce HT après remise. La
        // formule est mot pour mot celle du gabarit (document/factureLocation) :
        // le PDF, le montant stocké et le TTC du code-barres ne peuvent plus se
        // contredire.
        $livraison = (float) ($location->cout_livraison_client ?? 0);
        $remise    = (float) ($location->remise ?? 0);
        $ht        = (float) $location->detailLocation->sum('prix');
        $tauxTva   = (float) (Configuration::first()->tva ?? 0);
        $tva       = \Help::arrondiFranc(max(0, $ht - $remise) * ($tauxTva / 100));
        // AIRSI figé sur la location (10/09/2026) : porté par sa facture.
        $airsi     = (float) ($location->airsi ?? 0);
        $montant   = max(0, $ht - $remise) + $tva + $livraison + $airsi;

        Facture::create([
            'numero'     => Help::genererNumeroUnique('facture'),
            'numero_fne' => FneService::genererNumeroFne(),
            'user_id'    => Auth::id(),
            // Le franc n'a pas de centimes : une facture qui en porte bloque
            // son encaissement (le champ Montant est à pas de 1).
            'montant'    => \Help::arrondiFranc($montant),
            'airsi_applique' => $airsi,
            'statut'     => 2,
            'service'    => Help::$LOCATION,
            'service_id' => $location->id,
            'client_id'  => $location->client_id,
            'fne_status' => 'pending',
        ]);

        return back()->with('success', 'Facture de location générée. Elle apparaît dans « Factures non validées » jusqu\'à validation auprès de la DGI.');
    }

    /**
     * FACTURE D'UNE DEMANDE DE LIVRAISON.
     *
     * Aucune facture n'était jamais émise pour un transport : le code n'en créait
     * que pour les commandes et les locations. Or le solde d'un client compare
     * ses règlements à ses FACTURES — un transport payé restait donc
     * indéfiniment affiché comme « réglé d'avance », de l'argent que le client
     * croyait avoir à son crédit alors qu'il avait acheté un service rendu.
     *
     * CERTIFIÉE PAR LA DGI, comme la vente et la location. La certification ne
     * savait construire que ces deux-là ; le transport a maintenant son propre
     * message, qui déclare LA COURSE et non la marchandise — le client n'achète
     * pas les produits transportés, il achète leur acheminement.
     *
     * La facture est créée en attente : elle rejoint « Factures non validées »
     * jusqu'à ce qu'un gestionnaire déclenche la certification.
     */
    public function genererFactureLivraison(DemandeLivraison $demande)
    {
        // Une seule facture par demande : on évite les doublons.
        $existante = Facture::where('service', Help::$LIVRAISON)
            ->where('service_id', $demande->id)
            ->first();

        if ($existante) {
            return back()->with('warning', 'Une facture a déjà été générée pour cette demande de livraison.');
        }

        // Le montant NET : ce que le client doit réellement, remise déduite et
        // TVA ajoutée. Le filtre type_affaire est indispensable — une demande,
        // une commande et une location peuvent porter le même identifiant.
        $tva = (float) \App\Models\TvaCommande::where('commande_id', $demande->id)
            ->where('type_affaire', Help::$LIVRAISON)
            ->whereNull('deleted_at')
            ->sum('montant');

        // AIRSI figé sur la demande (10/09/2026) : porté par sa facture.
        $airsi   = (float) ($demande->airsi ?? 0);
        $montant = max(0, (float) ($demande->montantTotal ?? 0) - (float) ($demande->remise ?? 0)) + $tva + $airsi;

        if ($montant <= 0) {
            return back()->with('error', 'Cette demande de livraison ne porte aucun montant : rien à facturer.');
        }

        Facture::create([
            'numero'     => Help::genererNumeroUnique('facture'),
            'numero_fne' => FneService::genererNumeroFne(),
            'user_id'    => Auth::id(),
            // Le franc n'a pas de centimes : une facture qui en porte bloque
            // son encaissement (le champ Montant est à pas de 1).
            'montant'    => \Help::arrondiFranc($montant),
            'airsi_applique' => $airsi,
            'statut'     => 2,
            'service'    => Help::$LIVRAISON,
            'service_id' => $demande->id,
            'client_id'  => $demande->client_id,
            'fne_status' => 'pending',
        ]);

        return back()->with('success',
            'Facture de transport générée (' . Help::formatNombre($montant, true) . '). '
            . 'Elle apparaît dans « Factures non validées » jusqu\'à validation auprès de la DGI.');
    }

    /**
     * PDF de la facture d'un transport.
     *
     * Meme modele que la facture de location — en-tete, totaux, mise en page —
     * mais la prestation facturee est la COURSE, pas la marchandise : le client
     * n achete pas les produits transportes, ils sont a lui.
     */
    public function factureLivraison(Facture $facture, $action = 'voir')
    {
        if ($r = \App\Services\DocumentDgi::reponse($facture, (string) $action)) {
            return $r;
        }
        if ($facture->estUnAvoir()) {
            return $this->factureAvoir($facture, $action);
        }
        $demande = DemandeLivraison::with(['detailLivraison', 'priseEnCharge', 'destination', 'client'])
            ->find($facture->service_id);

        if (!$demande) {
            return back()->with('error', 'La demande de livraison de cette facture est introuvable.');
        }

        // Les donnees d en-tete du modele FNE : identite de l entreprise, bloc
        // client, numero et date. Sans elles, le document sortait avec un
        // « Facture de transport Nº » sans numero, et sans les mentions
        // legales de l entreprise — la piece n identifiait ni son emetteur ni
        // elle-meme.
        //
        // getDonneesFne retombe sur facture->numero a defaut de reference DGI :
        // c est exactement ce qu il faut pour une piece interne, qui n en a pas.
        $pdf = PDF::loadView('document.factureLivraison', array_merge(
            [
                'demande' => $demande,
                'facture' => $facture,
                'config'  => Configuration::first(),
            ],
            FneService::getDonneesFne($facture, $facture->client)
        ));

        $nom = 'Facture_Transport_' . $facture->numero . '.pdf';

        return $action === 'telecharger' ? $pdf->download($nom) : $pdf->stream($nom);
    }

    /**
     * PDF de la facture d'une location (bouton « voir/télécharger » des listes FNE).
     * Réutilise la vue orders.recapLocation (document FNE « Facture de location »)
     * en y injectant les données FNE de la facture (numéro/QR officiels si certifiée).
     */
    public function factureLocation(Facture $facture, $action = 'voir'){
        if ($r = \App\Services\DocumentDgi::reponse($facture, (string) $action)) {
            return $r;
        }
        if ($facture->estUnAvoir()) {
            return $this->factureAvoir($facture, $action);
        }

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
            'remise' => \Help::arrondiFranc($commande->remise + $montantReduit),
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

        // UN BON SANS PRIX D'ACHAT VAUDRAIT UN DÛ DE ZÉRO.
        //
        // Le prix vient maintenant de la ligne de stock du fournisseur retenu.
        // Si elle est à zéro — fournisseur rattaché par erreur, tarif jamais
        // saisi — le bon partirait avec un dû nul, et le fournisseur ne serait
        // jamais payé pour cette livraison. Mieux vaut le dire avant.
        if (!$request->filled('prix_fournisseur') && (float) ($stock->prix ?? 0) <= 0) {
            return redirect()->route('orders.traitement.post', $commande)
                ->with('error', 'Ce fournisseur n\'a pas de prix d\'achat pour ce produit : le bon vaudrait un dû de 0. Renseignez son prix sur la fiche du produit.');
        }
        // ET ON NE PART PAS SERVIR À PERTE SANS LE SAVOIR.
        //
        // Le prix facturé au client est figé à la commande ; celui du
        // fournisseur retenu vit sur sa ligne de stock. Les deux sont
        // INDÉPENDANTS, et rien ne les comparait : un fournisseur à 7 500 F
        // sur un article facturé 150 F passait sans un mot, et la perte
        // n'apparaissait qu'au récapitulatif des ventes, la marchandise déjà
        // partie.
        //
        // On le dit AVANT d'émettre le bon, avec les deux chiffres et ce qu'il
        // y a à corriger. Le gestionnaire garde la main : prix négocié saisi à
        // la main, autre fournisseur, ou prix catalogue revu.
        $ligneServie = $commande->detailCommande
            ->where('produit_id', $request->produit)
            ->first();

        $prixAchat = (float) ($request->filled('prix_fournisseur')
            ? $request->prix_fournisseur
            : ($stock->prix ?? 0));
        $prixVente = (float) ($ligneServie->prix ?? 0);

        if ($prixVente > 0 && $prixAchat >= $prixVente) {
            return redirect()->route('orders.traitement.post', $commande)
                ->with('error', sprintf(
                    "Vente à perte : ce fournisseur demande %s F l'unité pour "
                    . "un article facturé %s F au client. Choisissez un autre "
                    . "fournisseur, saisissez le prix négocié, ou corrigez le "
                    . "prix d'achat sur sa fiche de stock.",
                    number_format($prixAchat, 0, ',', ' '),
                    number_format($prixVente, 0, ',', ' ')
                ));
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
            (float) ($conf->tonne_moyenne ?? 0),
            $produit->unite_produit_id ?? null
        );

        // Tarification du livreur, MULTIPLIÉE PAR LE NOMBRE DE VOYAGES : trois
        // rotations, c'est trois déplacements — donc trois fois la part fixe et
        // trois fois le carburant. Le client, lui, paie déjà un forfait par
        // voyage ; le livreur n'en touchait qu'un seul. Repli sur le coût global
        // si sa tarification n'est pas configurée. On stocke la décomposition
        // (forfait_base / frais_km / distance_km) pour l'état "dette livreur".
        $tarif = $livreur->tarifLivraison(
            $produit->unite_produit_id ?? null,
            (float) $request->qte,
            (float) $distance,
            (float) ($distance * $conf->cout_liv_fixe * $nbrlivraison),
            $nbrlivraison
        );

        $livraison = Livraison::create([
            'numero' => \Help::genererNumeroUnique('livraison'),
            'livreur_id' => $livreur->id,
            'vehicule_id' => $request->vehicule,
            'client_id' => $commande->client_id,
            'cout_livraison' => $tarif['total'],
            'forfait_base'   => $tarif['forfait_base'],
            'frais_km'       => $tarif['frais_km'],
            'source_tarif'   => $tarif['source'] ?? null,
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
            // LE PRIX D'ACHAT VIENT DU FOURNISSEUR RETENU, il ne se saisit plus.
            //
            // Le champ était pré-rempli avec `produit.prix_fournisseur`, qui vaut
            // zéro sur la quasi-totalité du catalogue : le gestionnaire le
            // retapait donc de mémoire à chaque bon, et aucun des quatorze bons
            // émis ne portait le tarif réellement conclu avec son fournisseur.
            //
            // On lit désormais le tarif de CE fournisseur pour CE produit. Le
            // champ reste accepté s'il est transmis — une négociation
            // ponctuelle garde sa place.
            'prix_fournisseur' => $request->filled('prix_fournisseur')
                ? $request->prix_fournisseur
                : \App\Models\StockProduit::where('produit_id', $request->produit)
                    ->where('fournisseur_id', $request->fournisseur)
                    ->where('statut', Help::$STATUT_ACTIF)
                    ->whereNull('deleted_at')
                    ->value('prix'),
            'livreur_id' => $request->livreur,
            'code_enleve' => $codeEnlevement,
            'gestionnaire_id' => Auth::id(),
            'statut' => Help::$STATUT_ACTIF,
        ]);

        // $vehicule = Vehicule::find($request->vehicule);

        $stock->update([
            'qte' => $nouvelleQte
        ]);


        // L'ENVOI DU CODE NE DOIT PAS POUVOIR CASSER L'AFFECTATION.
        //
        // La livraison, le bon d'enlèvement et le décrément de stock viennent
        // d'être écrits, hors transaction. Une exception ici — serveur de
        // messagerie injoignable, quota d'envoi de l'hébergeur atteint, adresse
        // du client invalide — renvoyait une page d'erreur ALORS QUE TOUT ÉTAIT
        // DÉJÀ ENREGISTRÉ. Le gestionnaire voyait l'affectation faite et le
        // client ne recevait jamais son code : c'est le cas signalé le
        // 24/08/2026, à la seconde affectation d'une même commande.
        //
        // Désormais l'échec est TRACÉ et DIT. Le message emprunte une clé
        // propre : Flasher capte « error » et « warning » pour les rejouer en
        // notification fugace, et l'avertissement se perdrait.
        $codeEnvoye = true;

        try {
            Mail::send(new receptionCodeLivraison($livraison, $commande, $commande->client, $produit));
        } catch (\Throwable $e) {
            $codeEnvoye = false;
            \Log::error("Code de livraison non envoyé — commande {$commande->numero} : " . $e->getMessage());
        }

        return redirect()->route('orders.traitement', $commande)
            ->with('success', 'Produit traité')
            ->with('code_non_envoye', $codeEnvoye ? null : (
                "Le produit est bien traité, mais le code n'a PAS pu être envoyé au client. "
                . "Communiquez-le-lui directement : il figure sur cette page, dans « Codes à "
                . "communiquer au client »."
            ));
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

        // UN BON SANS PRIX D'ACHAT VAUDRAIT UN DÛ DE ZÉRO.
        //
        // Le prix vient maintenant de la ligne de stock du fournisseur retenu.
        // Si elle est à zéro — fournisseur rattaché par erreur, tarif jamais
        // saisi — le bon partirait avec un dû nul, et le fournisseur ne serait
        // jamais payé pour cette livraison. Mieux vaut le dire avant.
        if (!$request->filled('prix_fournisseur') && (float) ($stock->prix ?? 0) <= 0) {
            return redirect()->route('orders.traitement.sansLivraison', $commande)
                ->with('error', 'Ce fournisseur n\'a pas de prix d\'achat pour ce produit : le bon vaudrait un dû de 0. Renseignez son prix sur la fiche du produit.');
        }
        // ET ON NE PART PAS SERVIR À PERTE SANS LE SAVOIR.
        //
        // Le prix facturé au client est figé à la commande ; celui du
        // fournisseur retenu vit sur sa ligne de stock. Les deux sont
        // INDÉPENDANTS, et rien ne les comparait : un fournisseur à 7 500 F
        // sur un article facturé 150 F passait sans un mot, et la perte
        // n'apparaissait qu'au récapitulatif des ventes, la marchandise déjà
        // partie.
        //
        // On le dit AVANT d'émettre le bon, avec les deux chiffres et ce qu'il
        // y a à corriger. Le gestionnaire garde la main : prix négocié saisi à
        // la main, autre fournisseur, ou prix catalogue revu.
        $ligneServie = $commande->detailCommande
            ->where('produit_id', $request->produit)
            ->first();

        $prixAchat = (float) ($request->filled('prix_fournisseur')
            ? $request->prix_fournisseur
            : ($stock->prix ?? 0));
        $prixVente = (float) ($ligneServie->prix ?? 0);

        if ($prixVente > 0 && $prixAchat >= $prixVente) {
            return redirect()->route('orders.traitement.sansLivraison', $commande)
                ->with('error', sprintf(
                    "Vente à perte : ce fournisseur demande %s F l'unité pour "
                    . "un article facturé %s F au client. Choisissez un autre "
                    . "fournisseur, saisissez le prix négocié, ou corrigez le "
                    . "prix d'achat sur sa fiche de stock.",
                    number_format($prixAchat, 0, ',', ' '),
                    number_format($prixVente, 0, ',', ' ')
                ));
        }




        $detailCommande = $commande->detailCommande->where('produit_id', $request->produit)->first();

        $livraison = Livraison::create([
            'numero' => \Help::genererNumeroUnique('livraison'),
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
            // LE PRIX D'ACHAT VIENT DU FOURNISSEUR RETENU, il ne se saisit plus.
            //
            // Le champ était pré-rempli avec `produit.prix_fournisseur`, qui vaut
            // zéro sur la quasi-totalité du catalogue : le gestionnaire le
            // retapait donc de mémoire à chaque bon, et aucun des quatorze bons
            // émis ne portait le tarif réellement conclu avec son fournisseur.
            //
            // On lit désormais le tarif de CE fournisseur pour CE produit. Le
            // champ reste accepté s'il est transmis — une négociation
            // ponctuelle garde sa place.
            'prix_fournisseur' => $request->filled('prix_fournisseur')
                ? $request->prix_fournisseur
                : \App\Models\StockProduit::where('produit_id', $request->produit)
                    ->where('fournisseur_id', $request->fournisseur)
                    ->where('statut', Help::$STATUT_ACTIF)
                    ->whereNull('deleted_at')
                    ->value('prix'),
            'livreur_id' => null,
            'code_enleve' => $codeEnlevement,
            'gestionnaire_id' => Auth::id(),
            'statut' => Help::$STATUT_ACTIF,
        ]);

        $stock->update([
            'qte' => $nouvelleQte
        ]);
        $url = "https://www.google.com/maps?q={$enlevement->fournisseur->latitude},{$enlevement->fournisseur->longitude}";

        // L'ENVOI DU CODE NE DOIT PAS POUVOIR CASSER L'AFFECTATION.
        //
        // La livraison, le bon d'enlèvement et le décrément de stock viennent
        // d'être écrits, hors transaction. Une exception ici — serveur de
        // messagerie injoignable, quota d'envoi de l'hébergeur atteint, adresse
        // du client invalide — renvoyait une page d'erreur ALORS QUE TOUT ÉTAIT
        // DÉJÀ ENREGISTRÉ. Le gestionnaire voyait l'affectation faite et le
        // client ne recevait jamais son code : c'est le cas signalé le
        // 24/08/2026, à la seconde affectation d'une même commande.
        //
        // Désormais l'échec est TRACÉ et DIT. Le message emprunte une clé
        // propre : Flasher capte « error » et « warning » pour les rejouer en
        // notification fugace, et l'avertissement se perdrait.
        $codeEnvoye = true;

        try {
            Mail::send(new receptionCodeEnlevement($enlevement, $commande, $commande->client, $produit, $url));
        } catch (\Throwable $e) {
            $codeEnvoye = false;
            \Log::error("Code d'enlèvement non envoyé — commande {$commande->numero} : " . $e->getMessage());
        }

        // Mail::send(new receptionCodeLivraison($livraison, $commande, $commande->client, $produit));

        return redirect()->route('orders.traitement.sansLivraison', $commande)
            ->with('success', 'Produit traité')
            ->with('code_non_envoye', $codeEnvoye ? null : (
                "Le produit est bien traité, mais le code d'enlèvement n'a PAS pu être envoyé "
                . "au client. Communiquez-le-lui directement : il figure sur la fiche de la "
                . "commande, dans « Codes à communiquer au client »."
            ));
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
                (float) ($conf->tonne_moyenne ?? 0),
                optional(Produit::find($detailCommande[$key]->produit_id))->unite_produit_id
            );
            $coutGlobal  = (float) $distanceTraitement * (float) ($conf->cout_liv_fixe ?? 0) * (float) $nbrVoyages;
            $tarif = $livreurObj
                ? $livreurObj->tarifLivraison(
                    optional(Produit::find($detailCommande[$key]->produit_id))->unite_produit_id,
                    (float) $qte[$key],
                    (float) $distanceTraitement,
                    $coutGlobal,
                    $nbrVoyages
                )
                : ['forfait_base' => $coutGlobal, 'frais_km' => 0.0, 'total' => $coutGlobal];

            $livraison = Livraison::create([
                'numero' => \Help::genererNumeroUnique('livraison'),
                'livreur_id' => $livreur[$key],
                'client_id' => $commande->client_id,
                'adresse_livraison_id' => $commande->adresse_livraison_id,
                'date_livraison' => $date[$key],
                'qte' => $qte[$key],
                'cout_livraison' => $tarif['total'],
                'forfait_base'   => $tarif['forfait_base'],
                'frais_km'       => $tarif['frais_km'],
                'source_tarif'   => $tarif['source'] ?? null,
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
            'numero' => \Help::genererNumeroUnique('livraison'),
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
