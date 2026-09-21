<?php

namespace App\Http\Controllers;


use PDF;
use Help;
use App\Models\User;
use App\Models\Produit;
use App\Models\Commande;
use App\Models\TypeUser;
use App\Models\Categorie;
use App\Models\Enlevement;
use App\Models\Livraison;
use App\Models\DetailCommande;
use App\Models\Fournisseur;
use App\Models\ModePaiement;
use App\Models\StockProduit;
use Illuminate\Http\Request;
use App\Models\Configuration;
use App\Models\DemandePaiement;
use App\Models\PaiementFournisseur;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\sellerRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use \Cviebrock\EloquentSluggable\Services\SlugService;


class SellerController extends Controller
{

    public function home()
    {
        $this->verificationStock();
        $fournisseur = Fournisseur::where('user_id', Auth::user()->id)->first();
        $idFrs       = $fournisseur->id;

        $now          = \Carbon\Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $today        = $now->copy()->startOfDay();

        $bons = Enlevement::where('fournisseur_id', $idFrs)
            ->with(['produit', 'livraison.vehicule'])
            ->orderByDesc('created_at')
            ->get();

        $totalBons       = $bons->count();
        $bonsEnAttente   = $bons->whereNull('fournisseur_validation')->count();
        $bonsValides     = $bons->whereNotNull('fournisseur_validation')->count();
        $bonsAujourdhui  = $bons->filter(fn($b) => optional($b->created_at)->isSameDay($today))->count();
        $bonsCeMois      = $bons->filter(fn($b) => optional($b->created_at) >= $startOfMonth)->count();

        $totalProduits   = $fournisseur->produits()->count();
        $totalQuantites  = (float) $fournisseur->produits()->sum('stock_produit.qte');

        // Top 5 produits servis (par quantité servie)
        $topProduits = $bons->groupBy('produit_id')
            ->map(function ($items) {
                $first = $items->first();
                return (object) [
                    'produit'   => $first?->produit,
                    'nb_bons'   => $items->count(),
                    'total_qte' => (float) $items->sum(fn($e) => (float) ($e->qte_servi ?? $e->qte ?? 0)),
                ];
            })
            ->sortByDesc('total_qte')
            ->take(5)
            ->values();

        // DIX LIGNES NE SE CHERCHENT PAS, ET NE SE PAGINENT PAS.
        //
        // Le tableau porte desormais une recherche et une pagination : sur dix
        // lignes deja toutes visibles, l'une comme l'autre ne servaient a rien.
        // Meme choix que le tableau de bord de l'administrateur. « Voir tout »
        // mene toujours a la liste complete des bons.
        $dernieresLivraisons = $bons->take(50);

        return view('fournisseur.dashboard', [
            'fournisseur'         => $fournisseur,
            'bons'                => $bons,
            'totalBons'           => $totalBons,
            'bonsEnAttente'       => $bonsEnAttente,
            'bonsValides'         => $bonsValides,
            'bonsAujourdhui'      => $bonsAujourdhui,
            'bonsCeMois'          => $bonsCeMois,
            'totalProduits'       => $totalProduits,
            'totalQuantites'      => $totalQuantites,
            'topProduits'         => $topProduits,
            'dernieresLivraisons' => $dernieresLivraisons,
        ]);
    }

    //Afficher la liste des fournisseurs





    public function livraisons()
    {
        return view('fournisseur.livraison');
    }


    public function bons()
    {
        $this->verificationStock();

        // Récuperation de l'identifiant du fournisseur courant
        $fournisseur = Fournisseur::where('user_id', '=', Auth::user()->id)->first();
        // dd($idLivreur);

        // Récuperation des bons d'enlèvement liès aux livraisons
        $enlevement = Enlevement::where('fournisseur_id', '=', $fournisseur->id)->where('qte_servi', null)->orderByDesc('created_at')->get();
        // dd($enlevement);

        $tab = [];
        foreach ($fournisseur->produits as $produit) {
            // dd($produit);
            $env = [
                $produit->nom => $enlevement->where('produit_id', $produit->id)->count(),
            ];
            array_push($tab, $env);
        }

        // dd($tab);



        // dd($fournisseur->produits);

        return view('fournisseur.bon', [
            'enlevements' => $enlevement,
            'fournisseur' => $fournisseur,


        ]);
    }


    public function accepte()
    {
        $this->verificationStock();

        // Récuperation de l'identifiant du fournisseur courant
        $fournisseur = Fournisseur::where('user_id', '=', Auth::user()->id)->first();
        // dd($idLivreur);

        // Récuperation des bons d'enlèvement liès au fournisseur courant déjà servi
        $enlevement = Enlevement::where('fournisseur_id', '=', $fournisseur->id)->where('qte_servi', '!=', null)->orderByDesc('updated_at')->get();
        // dd($enlevement);
        $tab = [];

        foreach ($fournisseur->produits as $produit) {
            // dd($produit);
            $env = [
                $produit->nom => $enlevement->where('produit_id', $produit->id)->count(),
            ];
            array_push($tab, $env);
        }
        // dd($tab);

        return view('fournisseur.accepte', [
            'enlevements' => $enlevement,
            'fournisseur' => $fournisseur,
        ]);
    }

    /**
     * PLANNING LIVRAISON (lot 83, 15/09/2026) : les bons prévus, par jour et par
     * produit, 31 jours à partir de la date choisie (aujourd'hui par défaut).
     * Transposition de la feuille PLANNING du classeur du client.
     */
    public function planningLivraison(Request $request)
    {
        $fournisseur = Fournisseur::where('user_id', Auth::id())->firstOrFail();
        $debut = $this->dateDeDebut($request, now());
        $grille = \App\Services\PlanningFournisseur::grille(
            $fournisseur, \App\Services\PlanningFournisseur::bonsPrevus($fournisseur), $debut, false
        );

        return view('fournisseur.planningLivraison', ['fournisseur' => $fournisseur, 'grille' => $grille]);
    }

    /** HISTORIQUE (lot 83) : la même grille pour les bons enlevés ; 30 jours en arrière par défaut. */
    public function historiqueEnlevements(Request $request)
    {
        $fournisseur = Fournisseur::where('user_id', Auth::id())->firstOrFail();
        $debut = $this->dateDeDebut($request, now()->subDays(\App\Services\PlanningFournisseur::JOURS - 1));
        $grille = \App\Services\PlanningFournisseur::grille(
            $fournisseur, \App\Services\PlanningFournisseur::bonsEnleves($fournisseur), $debut, true
        );

        return view('fournisseur.historiqueEnlevements', ['fournisseur' => $fournisseur, 'grille' => $grille]);
    }

    /** RÉCAP PRODUITS (lot 83) : par produit, total, enlevé, prévu, reste, % et nombre de bons. */
    public function recapProduits()
    {
        $fournisseur = Fournisseur::where('user_id', Auth::id())->firstOrFail();

        return view('fournisseur.recapProduits', [
            'fournisseur' => $fournisseur,
            'lignes'      => \App\Services\PlanningFournisseur::recap($fournisseur),
        ]);
    }

    /** La date « debut » de l'adresse, ou la valeur par défaut si elle est absente ou fausse. */
    private function dateDeDebut(Request $request, \Carbon\Carbon $defaut): \Carbon\Carbon
    {
        $saisie = trim((string) $request->query('debut', ''));
        if ($saisie === '') {
            return $defaut->startOfDay();
        }
        try {
            return \Carbon\Carbon::parse($saisie)->startOfDay();
        } catch (\Throwable $e) {
            return $defaut->startOfDay();
        }
    }

    public function refuse()
    {
        $this->verificationStock();

        // Récuperation de l'identifiant du fournisseur courant
        $idFournisseur = Fournisseur::where('user_id', '=', Auth::user()->id)->value('id');
        // dd($idLivreur);

        // Récuperation des bons d'enlèvement liès aux livraisons
        $enlevement = Enlevement::where('fournisseur_id', '=', $idFournisseur)->where('fournisseur_validation', '!=', null)->get();
        // dd($enlevement);

        return view('fournisseur.accepte', [
            'enlevements' => $enlevement
        ]);
    }
    public function formBon(Request $request)
    {
        $this->verificationStock();
        $idFournisseur = Fournisseur::where('user_id', '=', Auth::user()->id)->value('id');
        $enlevement = Enlevement::where('fournisseur_id', '=', $idFournisseur)->get();
        $bon = Enlevement::where('code_enleve', $request->code)->where('fournisseur_id', $idFournisseur)->first();

        if ($bon == null) {

            return redirect()->route('sellers.bons', [
                'enlevements' => $enlevement
            ])->with('fail', 'Code invalide');

        } else {
            // dd('valide');

            return redirect()->route('sellers.bon.detail', $request->code);
        }

    }

    public function bonDetail($code)
    {
        $this->verificationStock();
        $idFournisseur = Fournisseur::where('user_id', '=', Auth::user()->id)->value('id');
        $enlevement = Enlevement::where('fournisseur_id', '=', $idFournisseur)->get();
        $bon = Enlevement::where('code_enleve', $code)->where('fournisseur_id', $idFournisseur)->first();
        if ($bon) {
            $produit = StockProduit::where('produit_id', $bon->produit_id)->where('fournisseur_id', $bon->fournisseur_id)->first();
        } else {
            $produit = new Produit;
        }

        return view('fournisseur.detailBon', [
            'produit' => $produit,
            'bon' => $bon
        ]);
    }

    public function bonImprime($code)
    {
        $this->verificationStock();

        $data['idFournisseur'] = Fournisseur::where('user_id', '=', Auth::user()->id)->value('id');
        $data['enlevement'] = Enlevement::where('fournisseur_id', '=', $data['idFournisseur'])->get();
        $data['bon'] = Enlevement::where('code_enleve', $code)->where('fournisseur_id', $data['idFournisseur'])->first();
        $data['produit'] = StockProduit::where('produit_id', $data['bon']->produit_id)->where('fournisseur_id', $data['bon']->fournisseur_id)->first();
        // dd($code);
        // return view('fournisseur.bonImprime',$data);

        $pdf = PDF::loadView('fournisseur.bonImprime', $data)->download();
        // return $pdf;

        return $pdf;

        // notify()->success('Laravel Notify is awesome!');
        // return redirect()->route('paye.create',$numero);
    }

    public function afficheBon($code)
    {
        $this->verificationStock();

        $data['idFournisseur'] = Fournisseur::where('user_id', '=', Auth::user()->id)->value('id');
        $data['enlevement'] = Enlevement::where('fournisseur_id', '=', $data['idFournisseur'])->get();
        $data['bon'] = Enlevement::where('code_enleve', $code)->where('fournisseur_id', $data['idFournisseur'])->first();
        $data['produit'] = StockProduit::where('produit_id', $data['bon']->produit_id)->where('fournisseur_id', $data['bon']->fournisseur_id)->first();
        // dd($code);
        // return view('fournisseur.bonImprime',$data);

        $pdf = PDF::loadView('fournisseur.bonImprime', $data)->stream();
        // return $pdf;

        return $pdf;

    }


    /**
     * CRÉDITE LE FOURNISSEUR POUR CE QU'IL A RÉELLEMENT SERVI.
     *
     * Extrait de bonValidation() le 28/08/2026, pour que la VENTE et la
     * LOCATION appliquent la même règle : deux copies auraient fini par
     * diverger, et le solde plafonne les demandes de paiement du fournisseur.
     *
     * Le solde était crédité TTC pour tout le monde. Or le client paie trois
     * choses qui n'ont pas le même destinataire : le produit revient au
     * fournisseur, la TVA à l'État, le transport à l'entreprise. Le fournisseur
     * non assujetti ne touche donc que le coût du produit — le créditer TTC
     * l'autorisait à réclamer 18 % de plus que son dû.
     *
     * Le prix retenu est celui INSCRIT SUR LE BON, et non le prix catalogue :
     * c'est le tarif réellement convenu. Pour une location, il porte déjà le
     * nombre de jours. Repli sur le stock pour les anciens bons sans prix.
     */
    private function crediterFournisseur(Fournisseur $fournisseur, Enlevement $bon, float $qteServi): void
    {
        $duStock = StockProduit::where('produit_id', $bon->produit_id)
            ->where('fournisseur_id', $bon->fournisseur_id)
            ->first();

        $prixUnitaire = (float) ($bon->prix_fournisseur ?? 0) > 0
            ? (float) $bon->prix_fournisseur
            : (float) ($duStock->prix ?? 0);

        // Même taux et même arrondi que le modèle, pour que le solde et la
        // dette affichée ne divergent jamais d'un franc.
        $tauxTva   = (float) (\App\Models\Configuration::first()?->tva ?? 18);
        $montantHt = $qteServi * $prixUnitaire;

        $montantCredite = $fournisseur->assujetti_tva
            ? round($montantHt + ($montantHt * $tauxTva / 100))
            : round($montantHt);

        $fournisseur->update([
            'solde' => (float) $fournisseur->solde + $montantCredite,
        ]);
    }

    public function bonValidation(Request $request, $code)
    {
        $this->verificationStock();

        // La quantité servie est décimale (tonnes : 0.5, 1.5…). Aucune validation
        // n'existait : une valeur vide ou 0 passait telle quelle et l'enlèvement
        // comptait ensuite pour ZÉRO dans le suivi de traitement de la commande.
        $request->validate([
            'qteServi' => 'required|numeric|min:0.1',
        ], [
            'qteServi.required' => 'Veuillez saisir la quantité servie.',
            'qteServi.numeric'  => 'La quantité servie doit être un nombre (utilisez le point pour les décimales).',
            'qteServi.min'      => 'La quantité servie doit être supérieure à 0.',
        ]);

        $fournisseur = Fournisseur::where('user_id', '=', Auth::user()->id)->first();

        $bon = Enlevement::where('code_enleve', $code)->where('fournisseur_id', $fournisseur->id)->first();

        // Contrôle AVANT toute utilisation de $bon (l'ancien code lisait
        // $bon->livraison->id avant ce test : crash 500 sur code invalide).
        if ($bon == null) {
            return back()->with('error', 'Code invalide');
        }

        // IDEMPOTENCE : un bon déjà validé ne doit pas être revalidable — chaque
        // re-soumission RE-CRÉDITAIT le solde du fournisseur (et re-touchait le stock).
        if ($bon->fournisseur_validation !== null || $bon->qte_servi !== null) {
            return back()->with('error', 'Ce bon a déjà été validé.');
        }

        // La quantité servie ne peut pas dépasser celle du bon (le max du formulaire
        // ne suffit pas : il est contournable).
        if ((float) $request->qteServi > (float) $bon->qte) {
            return back()->with('error', 'La quantité servie (' . $request->qteServi . ') dépasse celle du bon (' . $bon->qte . ').');
        }

        if ($bon->livraison == null) {
            return back()->with('error', "Ce bon n'est plus rattaché à une livraison valide. Contactez le gestionnaire.");
        }

        // ================= LES BONS DE LOCATION ONT LEUR PROPRE CHEMIN =================
        //
        // Constaté le 28/08/2026 : le fournisseur validant le bon d'une location
        // recevait « Ce bon n'est plus rattaché à une livraison valide ».
        //
        // Toute la suite de cette méthode est écrite pour les VENTES : elle lit
        // `livraison->detailCommande`, puis joint `commande` et
        // `detail_commande`. Or, pour une course de LOCATION,
        // `livraison.detail_commande_id` porte l'identifiant d'un
        // `detail_location` — la convention est posée à la création de la course.
        // La relation ne trouvait donc rien, et le contrôle renvoyait le message
        // ci-dessus.
        //
        // ⚠ ET LE DANGER ÉTAIT PIRE QUE LE REFUS. Si un `detail_commande`
        //   portait par hasard le même identifiant, la relation aurait rendu la
        //   ligne d'UNE AUTRE COMMANDE : le contrôle serait passé, et la
        //   validation aurait écrit l'état de livraison et la quantité servie
        //   sur la commande d'un autre client. Le refus nous a protégés par
        //   chance, pas par construction.
        //
        // La location suit donc son propre chemin, court et explicite.
        if ($bon->livraison->provenance === Help::$LOCATION) {
            $ligneLocation = \App\Models\DetailLocation::find($bon->livraison->detail_commande_id);

            if ($ligneLocation == null) {
                return back()->with('error', "Ce bon n'est plus rattaché à une location valide. Contactez le gestionnaire.");
            }

            // 2 = récupération par le client : la validation du bon vaut remise
            // du matériel. Sinon, un livreur l'achemine et clôturera lui-même.
            $retraitParClient = $bon->livraison->livre_par == 2;

            $bon->livraison->update([
                'etat_livraison' => $retraitParClient ? Help::$LIVRAISON_LIVREE : Help::$LIVRAISON_EN_COURS,
            ]);

            $bon->update([
                'fournisseur_validation' => date('Y-m-d H:i:s'),
                'qte_servi'              => $request->qteServi,
            ]);
            // Matériel retiré par le client lui-même : la course est livrée, le
            // bon de livraison lui part (lot 84, 15/09/2026).
            if ($retraitParClient) {
                \App\Services\BonDeLivraisonClient::envoyerApresLivraison($bon->livraison);
            }

            // LE STOCK N'EST PAS TOUCHÉ ICI, et c'est voulu : contrairement à la
            // vente, la validation d'une location NE DÉCRÉMENTE PAS le stock du
            // fournisseur. Restituer la part non servie, comme le fait la vente
            // plus bas, gonflerait donc le stock d'une quantité jamais retirée.
            $this->crediterFournisseur($fournisseur, $bon, (float) $request->qteServi);

            // L'état de la LOCATION ne se décide pas ici : elle passe EN COURS à
            // sa validation par le gestionnaire, et TERMINE au retour du
            // matériel. Y toucher court-circuiterait « Retour du matériel ».
            return redirect()->route('sellers.bon.detail', $code);
        }
        // =============================================================================

        // Bon dont la livraison a disparu : la requête ci-dessous concaténait « null »
        // puis lisait [0] d'un résultat vide -> erreur 500 au moment de valider le bon.
        if ($bon->livraison->detailCommande == null) {
            return back()->with('error', "Ce bon n'est plus rattaché à une livraison valide. Contactez le gestionnaire.");
        }

        $lignesCommande = DB::select("select c.* from commande c, detail_commande d, livraison l where c.id = d.`commande_id` and d.`id`=l.`detail_commande_id` and l.id=" . $bon->livraison->id);
        if (empty($lignesCommande)) {
            return back()->with('error', "La commande liée à ce bon est introuvable. Contactez le gestionnaire.");
        }
        $commande = $lignesCommande[0];

        // livre_par : 1 = LIVREUR, 2 = CLIENT (récupération par le client).
        // Récupération par le client : la validation du bon vaut LIVRAISON EFFECTUÉE.
        // Livraison par un livreur : la marchandise quitte le fournisseur, la
        // livraison passe EN COURS ; c'est le livreur qui la clôturera.
        // Avant : on écrivait les nombres 3 et 4 dans une colonne qui contient des
        // LIBELLÉS ("EN ATTENTE", "EN COURS LIVRAISON", "LIVREE"). La livraison
        // n'était donc jamais reconnue comme livrée, la fiche commande affichait
        // « Non livrée » et les grands livres l'ignoraient.
        $retraitParClient = $bon->livraison->livre_par == 2;

        $ligneCommande = $bon->livraison->detailCommande;
        $ligneCommande->update([
            'etat_livraison' => $retraitParClient ? Help::$LIVRAISON_LIVREE : Help::$LIVRAISON_EN_COURS,
            'qte_livree' => $retraitParClient
                ? min((float) $ligneCommande->qte, (float) ($ligneCommande->qte_livree ?? 0) + (float) $request->qteServi)
                : (float) ($ligneCommande->qte_livree ?? 0),
        ]);

        $bon->livraison->update([
            'etat_livraison' => $retraitParClient ? Help::$LIVRAISON_LIVREE : Help::$LIVRAISON_EN_COURS,
        ]);
        $stock = StockProduit::where('fournisseur_id', $fournisseur->id)->where('produit_id', $bon->produit_id)->first();

        // $stock->update([
        //     'qte' => $request->qteServi
        // ]);


        $bon->update([
            'fournisseur_validation' => date('Y-m-d H:i:s'),
            'qte_servi' => $request->qteServi,
        ]);
        // Marchandise retirée par le client lui-même : la course est livrée, le
        // bon de livraison lui part (lot 84, 15/09/2026).
        if ($retraitParClient) {
            \App\Services\BonDeLivraisonClient::envoyerApresLivraison($bon->livraison);
        }



        $produit = StockProduit::where('produit_id', $bon->produit_id)->where('fournisseur_id', $bon->fournisseur_id)->first();

        // STOCK : NE PLUS re-décrémenter ici. La quantité du bon a DÉJÀ été retirée
        // du stock au traitement par le gestionnaire (traitementItem /
        // traitementItemSansLivraison / traitement en masse) : décrémenter une
        // seconde fois faisait perdre le double au stock affiché. On RESTITUE au
        // contraire la part réservée mais non servie (qte du bon - qte servie).
        $nonServi = max(0, (float) $bon->qte - (float) $request->qteServi);
        if ($nonServi > 0) {
            $produit->update([
                'qte' => $produit->qte + $nonServi
            ]);
        }

        $this->crediterFournisseur($fournisseur, $bon, (float) $request->qteServi);


        if ($bon->livraison->livre_par == 2) { // 2 = récupération par le client
            // la livraison liée à la commande
            $commande = DB::select("select c.* from commande c, detail_commande d, livraison l where c.id = d.`commande_id` and d.`id`=l.`detail_commande_id` and l.id=" . $bon->livraison->id)[0];
            $totalQteALivrer = Commande::find($commande->id)->detailCommande->sum('qte');


            // CE QUI A ÉTÉ SERVI, ET NON CE QUI A ÉTÉ DEMANDÉ.
            //
            // On additionnait la quantité des livraisons, c'est-à-dire la
            // quantité DEMANDÉE. Or dès qu'un fournisseur sert moins que le bon,
            // un reliquat est créé : la somme des quantités demandées dépasse
            // alors celle de la commande. Une commande de 4 tonnes servie en
            // trois bons (2 demandées / 1 servie, puis 1, puis 2) donnait 5 au
            // lieu de 4 — l'égalité était fausse, la commande ne quittait jamais
            // la liste des commandes en attente de traitement, alors que le
            // client avait tout reçu.
            //
            // Comparaison en >= et non en == : une égalité stricte entre deux
            // décimaux rate aussi la cible au moindre arrondi.
            $qteServie = Livraison::with('enlevement')
                ->whereIn(
                    'detail_commande_id',
                    DetailCommande::where('commande_id', $commande->id)->pluck('id')
                )
                ->where('etat_livraison', Help::$LIVRAISON_LIVREE)
                ->get()
                ->sum(fn ($uneLivraison) => $uneLivraison->enlevement
                    ? $uneLivraison->enlevement->quantiteAPayer()
                    : (float) $uneLivraison->qte);

            if ($qteServie >= (float) $totalQteALivrer) {

                // Finaliser la commande : etat_commande contient un LIBELLÉ, pas un
                // numéro. L'ancien « = 3 » empêchait la commande d'apparaître dans la
                // liste des commandes traitées.
                DB::update('update commande set etat_commande = ? where id = ?', [Help::$COMMANDE_TERMINE, $commande->id]);
            }
        }

        // dd($request->qteServi);

        return redirect()->route('sellers.bon.detail', $code);
    }

    // Afficher le formulaire d'enregistrement d'un fournisseur


    /**
     * L'historique COMPLET de ce que le fournisseur a touché.
     *
     * L'entreprise le paie par deux chemins : la demande qu'il initie lui-même,
     * et le règlement qu'un administrateur saisit sur un de ses bons. Cet écran
     * ne montrait que le premier — le fournisseur ne voyait donc nulle part les
     * sommes qu'on lui avait versées de notre propre initiative, et son
     * historique ne retombait pas sur ce qu'il avait réellement reçu.
     *
     * Les deux sources sont réunies en un seul tableau, du plus récent au plus
     * ancien, chacune portant son origine.
     */
    public function listePaiements()
    {
        $fournisseur = Fournisseur::where('user_id', Auth::user()->id)->first();

        $demandes = DemandePaiement::where('user_id', Auth::user()->id)
            ->with('modePaiement')
            ->orderByDesc('created_at')
            ->get();

        // Les règlements saisis par l'entreprise. Ceux qui découlent d'une
        // demande sont EXCLUS : ils portent `demande_paiement_id` et sont déjà
        // dans la liste ci-dessus, sous leur demande. Sans cette exclusion, le
        // même versement apparaîtrait deux fois et l'historique doublerait.
        $reglements = collect();

        if ($fournisseur) {
            $reglements = PaiementFournisseur::with(['modePaiement', 'enlevement'])
                ->where('fournisseur_id', $fournisseur->id)
                ->where('statut', 1)
                ->whereNull('demande_paiement_id')
                ->orderByDesc('date_paiement')
                ->get();
        }

        $mouvements = $demandes
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
            ])
            ->concat(
                $reglements->map(fn (PaiementFournisseur $p) => (object) [
                    'reference'     => $p->reference ?: ('#' . $p->id),
                    'date'          => $p->date_paiement ?? $p->created_at,
                    'montant'       => (float) $p->montant,
                    'statut'        => 1, // un règlement enregistré est un versement fait
                    // Point 20 (09/09/2026) : « À payer » puis « Effectuée », comme une demande.
                    'etat_reglement' => $p->etat_reglement,
                    'libelle_etat'   => $p->libelleReglement(),
                    'mode'          => $p->modePaiement?->libelle,
                    'date_paiement' => $p->date_paiement ?? $p->created_at,
                    'origine'       => "L'entreprise",
                    'detail'        => $p->enlevement?->code_enleve
                        ? 'Bon ' . $p->enlevement->code_enleve
                        : 'Règlement de bon',
                ])
            )
            ->sortByDesc(fn ($m) => $m->date)
            ->values();

        $recus = $mouvements->where('statut', 1);
        $attente = $mouvements->where('statut', 0);

        return view('fournisseur.listeDesPaiements', [
            'fournisseur'      => $fournisseur,
            'mouvements'       => $mouvements,
            'config'           => Configuration::first(),
            'totalMouvements'  => $mouvements->count(),
            'totalPayees'      => $recus->count(),
            'totalNonPayees'   => $attente->count(),
            'montantPaye'      => (float) $recus->sum('montant'),
            'montantEnAttente' => (float) $attente->sum('montant'),
        ]);
    }

    public function loginPage()
    {
        return view('fournisseur.login');
    }

    // TRAITEMENT DE L'AUTHENTIFICATION
    public function validLogin(Request $request)
    {
        // dd('jsjs');
        // Login OU e-mail, comme l'application mobile — voir le commentaire
        // détaillé dans LivreurController::login(). Le OU est entre parenthèses
        // pour que le filtre sur le type reste appliqué aux deux branches.
        $identifiant = $request->login;
        $user = User::where(function ($q) use ($identifiant) {
                $q->where('login', $identifiant)->orWhere('email', $identifiant);
            })
            ->where('type_user_id', 5)
            ->first();

        if (!$user) {
            // dd('pas trouvé');
            return redirect()->route('sellers.login')->with('fail', 'mot de passe ou login incorrect 1');

        } else {
            // dd('trouvé');

            if (Help::HashVerifier($request->password, $user->password)) {
                if($user->statut == 2){
                    return back()->with('block', "Vous ne pouvez pas vous connecter pour le moment. Veuillez contacter l'administrateur pour plus d'information");
                }
                $request->session()->regenerate();
                Auth::login($user);
                return redirect()->route('sellers.home');
            } else {
                return redirect()->route('sellers.login')->with('fail', 'mot de passe ou login incorrect 2');

            }
        }

    }

    /**
     * L'écran de demande de paiement du fournisseur.
     *
     * Il avait le sien : un simple encadré avec trois champs, sans historique
     * ni état des demandes en cours. Le livreur et l'apporteur, eux, disposent
     * déjà d'un écran complet — solde, demandes en attente, demandes payées,
     * historique — dont le contrôleur traite DÉJÀ le profil fournisseur
     * (UserController::demandeDepaiePage, cas 5).
     *
     * On y renvoie plutôt que d'entretenir deux écrans et deux traitements du
     * même acte : le second débitait le solde de son côté, sans jamais donner
     * au fournisseur la moindre trace de ce qu'il avait demandé.
     *
     * L'URL reste valable : un signet ou un lien du menu continue de marcher.
     */
    public function demandeDepaieFournisseur()
    {
        return redirect()->route('show.demandeDepaiePage');
    }

    public function demandeDepaieFournisseurTraitement(Request $request)
    {
        if ($request->montant == 0) {
            return redirect()->route('sellers.demandeDepaieFournisseur')->with('error', '0fcfa n\'est pas autorisé comme montant');
        }

        //livreur
        $user = Auth::user();
        if ($request->montant > $user->getFournisseur->solde) {

            return redirect()->route('sellers.demandeDepaieFournisseur')->with('error', 'Veuillez entrer un montant inférieur ou égale à votre solde');
        }

        // LE DÉBIT ET LA DEMANDE, OU NI L'UN NI L'AUTRE.
        //
        // Le solde était débité d'abord, la demande créée ensuite. Si la
        // création échouait — une colonne absente en base a suffi — le
        // fournisseur repartait avec un solde amputé et aucune demande en
        // face : l'argent disparaissait de son tableau de bord sans que
        // personne ne puisse le lui verser.
        try {
            DB::transaction(function () use ($user, $request) {
                $fournisseur = $user->getFournisseur;

                $fournisseur->update([
                    'solde' => (float) $fournisseur->solde - (float) $request->montant,
                ]);

                DemandePaiement::create([
                    'montant'          => $request->montant,
                    'numero_compte'    => $request->numero,
                    'user_id'          => $user->id,
                    'mode_paiement_id' => $request->modePaie,
                    // Le solde vient d'être débité ci-dessus : la 2e validation
                    // ne re-débite pas.
                    'solde_debite_initiation' => 1,
                ]);
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Demande de paiement fournisseur impossible', [
                'user_id' => $user->id,
                'montant' => $request->montant,
                'erreur'  => $e->getMessage(),
            ]);

            return redirect()->route('sellers.demandeDepaieFournisseur')->with('error',
                "Votre demande n'a pas pu être enregistrée. Votre solde n'a pas été modifié. "
                . "Signalez-le à l'administrateur.");
        }

        return redirect()->route('sellers.demandeDepaieFournisseur')->with('success', 'Votre demande a été envoyée');
    }



    public function stock()
    {
        $this->verificationStock();
        // dd(Auth::user());

        // récuperation de l'id du fournisseur en fonction de id user
        $idFournisseur = Fournisseur::where('user_id', '=', Auth::user()->id)->first()->id;

        $fournisseur = Fournisseur::find($idFournisseur);

        return view(
            'fournisseur.stock',
            [
                'fournisseur' => $fournisseur
            ]
        );
    }

    public function addProducts()
    {

        $categories = Categorie::all();
        return view('fournisseur.add-products', [
            'categories' => $categories
        ]);
    }

    public function profile($id)
    {
        $this->verificationStock();

        $fournisseur = Fournisseur::find($id);
        $produits = $fournisseur->produits;
        $enlevement = Enlevement::where('fournisseur_id', $id)->where('statut', 2)->get();
        // dd($enlevement);

        return view('fournisseur.profile', [
            'fournisseur' => $fournisseur,
            'produits' => $produits,
            'enlevements' => $enlevement
        ]);
    }

    // Formulaire de modification d'un produit du stock
    public function editProducts($id)
    {
        $this->verificationStock();

        $produit = Produit::find($id);

        $idFournisseur = Fournisseur::where('user_id', '=', Auth::user()->id)->first()->id;
        $stocks = StockProduit::where('fournisseur_id', '=', $idFournisseur)
            ->where('produit_id', '=', $id)->first();


        return view('fournisseur.edit-products', [
            'produits' => $produit,
            'stock' => $stocks
        ]);
    }

    // Modification d'un produit du stock d'un fournisseur
    public function update(Request $request, $produit)
    {

        $request->validate([
            'seuil'    => 'required|integer|min:0|max:9999999',
            'quantite' => 'required|integer|min:0|max:9999999',
            'prix'     => 'required|numeric|min:0',
        ], [
            'seuil.required'    => 'Ce champs est obligatoire',
            'seuil.integer'     => 'Veuillez saisir un nombre entier',
            'seuil.max'         => 'La valeur du seuil ne peut pas dépasser 9 999 999',
            'quantite.required' => 'Ce champs est obligatoire',
            'quantite.integer'  => 'Veuillez saisir un nombre entier',
            'quantite.max'      => 'La quantité ne peut pas dépasser 9 999 999',
            'prix.required'     => 'Ce champs est obligatoire',
            'prix.numeric'      => 'Veuillez saisir un nombre',
        ]);

        try {
            // On recupère l'id du fournisseur courant
            $idFournisseur = Fournisseur::where('user_id', '=', Auth::user()->id)->first()->id;
            $stock = StockProduit::where('fournisseur_id', '=', $idFournisseur)
                ->where('produit_id', '=', $produit)->first();

            if ($stock) {
                $stock->prix = $request->prix;
                $stock->qte = $request->quantite;
                $stock->seuil_alert = $request->seuil;
                $stock->save();
            }

            session()->forget([
                'finDeStock',
                'produit_id'
            ]);
            
            $this->verificationStock();

            return redirect()->route('sellers.edit', $produit)->with('succes', 'Modification effectuée');
            
        } catch (\Exception $e) {
            \Log::error('Erreur update produit: ' . $e->getMessage() . ' - Ligne: ' . $e->getLine());
            return back()->with('error', 'Une erreur serveur est survenue : ' . $e->getMessage());
        }

    }



    public function parametreFournisseur()
    {
        return view('fournisseur.parametre', [
            'frs' => Fournisseur::where('user_id', Auth::user()->id)->first()
        ]);
    }

    public function FournisseurUpdate(Request $request)
    {

        $user = Auth::user();
        // dd(Hash::check($request->oldPassWord, $user->password));
        $frs = Fournisseur::where('user_id', $user->id)->first();

        if (User::where('login', $request->login)->first() && $request->login != $user->login) {
            return redirect()->route('sellers.parametreFournisseur')->with('loginExiste', 'Cet login est déjà utilisé');
        }
        if (User::where('email', $request->email)->first() && $request->email != $user->email) {
            return redirect()->route('sellers.parametreFournisseur')->with('emailExiste', 'Cet login est déjà utilisé');
        }



        if ($request->oldPassWord != null) {
            if ($request->newPassWord != null) {

                if ($request->newPassWord == $request->confirmPassWord) {

                    if (Help::HashVerifier($request->oldPassWord, $user->password)) {

                        Auth::user()->update([
                            'password' => Help::HashPassword($request->newPassWord)
                        ]);

                    } else {

                        return redirect()->route('sellers.parametreFournisseur')->with('errorPassword', 'Mauvais mot de passe');
                    }
                } else {
                    return redirect()->route('sellers.parametreFournisseur')->with('passDifferent', 'Les deux mots de passe ne correspondent pas');
                }
            }

        } else {
            if ($request->newPassWord != null) {
                return redirect()->route('sellers.parametreFournisseur')->with('avant', 'Remplissez le champs ANCIEN MOT DE PASSE SVP');
            }
        }


        $frs->update([
            'nom' => $request->nom,
            'prenom' => $request->prenom,
            'contact1' => $request->contact1,
            'contact2' => $request->contact2,
            'adresse_geo' => $request->adresse_geo,
            'adresse_postale' => $request->adresse_postale,

        ]);

        $user->update([
            'email' => $request->email,
            'login' => $request->login,
        ]);


        return redirect()->route('sellers.parametreFournisseur')->with('success', 'Les changement ont été appliqués');
    }

    private function verificationStock()
    {
        $fournisseur = Fournisseur::where('user_id', Auth::user()->id)->first();
        $produits = [];
        // dd($fournisseur);
        foreach ($fournisseur->produits as $produit) {

            if ($produit->pivot->qte < $produit->pivot->seuil_alert) {
                array_push($produits, $produit->id);
            }
        }

        if (count($produits) > 0) {
            session()->put([
                'finDeStock' => true,
                'produits' => $produits
            ]);
            # code...
        } else {
            session()->forget([
                'finDeStock',
                'produits'
            ]);
        }

    }
}
