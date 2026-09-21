<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\Configuration;
use App\Models\DemandeLivraison;
use App\Models\Livraison;
use App\Models\Location;
use App\Models\TvaCommande;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Les états comptables : ce que l'entreprise doit à l'État, et ce qu'elle gagne.
 *
 * Le taux de TVA se paramètre, la TVA se calcule sur chaque facture — mais rien
 * ne l'additionnait jusqu'ici. Un comptable ne pouvait pas répondre à la seule
 * question qui compte au moment de la déclaration : combien ai-je collecté sur
 * la période, et combien ai-je réellement encaissé ?
 */
class ComptabiliteController extends Controller
{
    /**
     * État de TVA collectée.
     *
     * DEUX CHIFFRES, ET ILS NE SE CONFONDENT PAS :
     *
     *  - la TVA FACTURÉE est celle inscrite sur les factures de la période ;
     *  - la TVA ENCAISSÉE est la part de cette taxe que le client a réellement
     *    payée. Sur une facture réglée à moitié, la moitié de la TVA est encore
     *    dans la poche du client.
     *
     * La seconde se déduit de la première au prorata du règlement : le client
     * ne paie pas « d'abord le produit puis la taxe », il verse un acompte sur
     * un tout. C'est la convention retenue partout dans l'écran, et elle est
     * rappelée à l'utilisateur.
     *
     * LE TRANSPORT N'EST PAS TAXÉ : aucune ligne de TVA n'est écrite pour lui
     * (cf. ClientController, création d'une demande de livraison). Il n'apparaît
     * donc pas ici, et c'est voulu.
     */
    public function tvaCollectee(Request $request)
    {
        $tauxConfig = (float) (Configuration::first()?->tva ?? 18);

        // Par défaut le mois en cours : c'est la maille de la déclaration.
        $du = $request->filled('du')
            ? Carbon::parse($request->input('du'))->startOfDay()
            : Carbon::today()->startOfMonth();
        $au = $request->filled('au')
            ? Carbon::parse($request->input('au'))->endOfDay()
            : Carbon::today()->endOfDay();

        $service = $request->input('service', 'TOUS');

        $lignes = TvaCommande::query()
            ->when($service !== 'TOUS', fn ($q) => $q->where('type_affaire', $service))
            ->orderBy('id')
            ->get()
            ->map(fn (TvaCommande $t) => $this->ligneDeTva($t, $tauxConfig))
            ->filter()
            // Le filtre de période porte sur la date du DOCUMENT, pas sur celle
            // de la ligne de taxe : c'est la date de facture qui fait foi.
            ->filter(fn ($l) => $l->date && $l->date->between($du, $au))
            ->sortByDesc('date')
            ->values();

        $totaux = (object) [
            'base_ht'       => $lignes->sum('base_ht'),
            'tva_facturee'  => $lignes->sum('tva_facturee'),
            'tva_encaissee' => $lignes->sum('tva_encaissee'),
            'net_a_payer'   => $lignes->sum('net_a_payer'),
            'encaisse'      => $lignes->sum('encaisse'),
        ];
        $totaux->tva_a_encaisser = max(0, $totaux->tva_facturee - $totaux->tva_encaissee);

        // Ventilation par service : la déclaration se ventile, et un écart se
        // localise plus vite quand on sait de quelle activité il vient.
        $parService = $lignes->groupBy('service')->map(fn ($g, $nom) => (object) [
            'service'       => $nom,
            'nb'            => $g->count(),
            'base_ht'       => $g->sum('base_ht'),
            'tva_facturee'  => $g->sum('tva_facturee'),
            'tva_encaissee' => $g->sum('tva_encaissee'),
        ])->sortByDesc('tva_facturee')->values();

        return view('admin.comptabilite.tvaCollectee', [
            'lignes'     => $lignes,
            'totaux'     => $totaux,
            'parService' => $parService,
            'tauxConfig' => $tauxConfig,
            'du'         => $du,
            'au'         => $au,
            'service'    => $service,
            'nbAnomalies'=> $lignes->where('anomalie', true)->count(),
        ]);
    }

    /**
     * Une ligne de l'état, construite à partir de l'écriture de TVA.
     *
     * L'écriture porte le montant de taxe et le type d'affaire ; le document
     * (vente, location, demande de livraison) porte la date, le client et la
     * base. Une écriture dont le document a disparu est écartée plutôt que
     * rendue avec des tirets : elle fausserait les totaux.
     */
    private function ligneDeTva(TvaCommande $t, float $tauxConfig): ?object
    {
        $tva = (float) $t->montant;

        switch ($t->type_affaire) {
            case \Help::$LOCATION:
                $doc = Location::with('client')->find($t->commande_id);
                if (!$doc) { return null; }
                // La remise d'une location s'applique AVANT la taxe.
                $baseHt  = max(0, (float) $doc->montant_total - (float) ($doc->remise ?? 0));
                $date    = $doc->date_location ?? $doc->created_at;
                $service = 'Location';
                // La TVA sur le transport (point 5) est collectée avec celle de
                // la marchandise : elle entre dans l'état, base comprise.
                if ((float) ($doc->tva_transport ?? 0) > 0) {
                    $tva    += (float) $doc->tva_transport;
                    $baseHt += (float) ($doc->cout_livraison_client ?? 0);
                }
                break;

            case \Help::$LIVRAISON:
                // Le transport est exonéré : aucune écriture ne devrait exister.
                // On la rend malgré tout si elle existe, pour qu'un régime qui
                // changerait un jour n'échappe pas silencieusement à l'état.
                $doc = DemandeLivraison::with('client')->find($t->commande_id);
                if (!$doc) { return null; }
                $baseHt  = (float) ($doc->montantTotal ?? 0) - (float) ($doc->remise ?? 0);
                $date    = $doc->created_at;
                $service = 'Demande de livraison';
                break;

            default: // VENTE
                $doc = Commande::with(['client', 'detailCommande'])->find($t->commande_id);
                if (!$doc) { return null; }
                // Vérifié sur les données réelles : la taxe d'une vente est
                // calculée sur le HT diminué de la remise, exactement comme
                // pour une location — même si montantAPayer() la retranche
                // dans un autre ordre, le net tombe juste.
                $baseHt  = max(0, $doc->montantHT() - (float) ($doc->remise ?? 0));
                $date    = $doc->date_commande ?? $doc->created_at;
                $service = 'Vente';
                if ((float) ($doc->tva_transport ?? 0) > 0) {
                    $tva    += (float) $doc->tva_transport;
                    $baseHt += (float) ($doc->cout_livraison_client ?? 0);
                }
        }

        $netAPayer = (float) $doc->montantAPayer();
        $encaisse  = (float) $doc->montantPayeComptant();

        // Part réglée, plafonnée à 1 : un trop-perçu ne crée pas de taxe.
        $part = $netAPayer > 0 ? min(1, $encaisse / $netAPayer) : 0.0;

        $taux = $baseHt > 0 ? $tva / $baseHt * 100 : 0.0;

        return (object) [
            'date'          => $date ? Carbon::parse($date) : null,
            'numero'        => $doc->numero ?? ('#' . $doc->id),
            'client'        => $doc->client?->display_name ?? '-',
            'service'       => $service,
            'base_ht'       => round($baseHt),
            'taux'          => $taux,
            'tva_facturee'  => round($tva),
            'net_a_payer'   => round($netAPayer),
            'encaisse'      => round($encaisse),
            'tva_encaissee' => round($tva * $part),
            'solde'         => $part >= 1 ? 'Soldée' : ($encaisse > 0 ? 'Partielle' : 'Impayée'),
            // Un taux qui s'écarte du taux paramétré signale une facture dont la
            // base a bougé après le calcul de la taxe. Signalé, jamais corrigé
            // en silence : l'écriture d'origine reste la référence comptable.
            'anomalie'      => $baseHt > 0 && abs($taux - $tauxConfig) > 0.5,
        ];
    }

    /**
     * État d'AIRSI collecté (10/09/2026).
     *
     * MÊME LECTURE QUE L'ÉTAT DE TVA, mêmes deux chiffres :
     *
     *  - l'AIRSI FACTURÉ est celui figé sur les affaires de la période ;
     *  - l'AIRSI ENCAISSÉ en est la part que le client a réellement réglée,
     *    au prorata du règlement.
     *
     * Il n'existe pas de table d'écritures pour l'AIRSI, à la différence de
     * la TVA (tva_commande) : le montant est figé sur le document lui-même à
     * sa création (colonnes commande.airsi, location.airsi,
     * demande_livraison.airsi — cf. Help::airsiPour). C'est donc le document
     * qui fait foi. Un client au réel (RNI, RSI) n'en porte aucun : il
     * n'apparaît pas ici, et c'est voulu.
     *
     * Une affaire annulée, ou dont le paiement en ligne n'a jamais abouti,
     * n'a donné lieu à aucune facture : elle est écartée, sinon son acompte
     * figurerait « à encaisser » pour une vente que personne ne servira.
     */
    public function airsiCollectee(Request $request)
    {
        $tauxConfig = \Help::tauxAirsi();

        $du = $request->filled('du')
            ? Carbon::parse($request->input('du'))->startOfDay()
            : Carbon::today()->startOfMonth();
        $au = $request->filled('au')
            ? Carbon::parse($request->input('au'))->endOfDay()
            : Carbon::today()->endOfDay();

        $service = $request->input('service', 'TOUS');

        $lignes = collect();

        if (in_array($service, ['TOUS', \Help::$VENTE], true)) {
            Commande::with(['client', 'detailCommande', 'TvaCommande'])
                ->where('airsi', '>', 0)->orderBy('id')->get()
                ->each(fn (Commande $c) => $lignes->push($this->ligneDAirsi($c, \Help::$VENTE, $tauxConfig)));
        }
        if (in_array($service, ['TOUS', \Help::$LOCATION], true)) {
            Location::with(['client', 'detailLocation', 'tvaLocation'])
                ->where('airsi', '>', 0)->orderBy('id')->get()
                ->each(fn (Location $l) => $lignes->push($this->ligneDAirsi($l, \Help::$LOCATION, $tauxConfig)));
        }
        if (in_array($service, ['TOUS', \Help::$LIVRAISON], true)) {
            DemandeLivraison::with('client')
                ->where('airsi', '>', 0)->orderBy('id')->get()
                ->each(fn (DemandeLivraison $d) => $lignes->push($this->ligneDAirsi($d, \Help::$LIVRAISON, $tauxConfig)));
        }

        // La période porte sur la date du DOCUMENT, comme pour la TVA.
        $lignes = $lignes->filter()
            ->filter(fn ($l) => $l->date && $l->date->between($du, $au))
            ->sortByDesc('date')
            ->values();

        $totaux = (object) [
            'base'            => $lignes->sum('base'),
            'airsi_facture'   => $lignes->sum('airsi_facture'),
            'airsi_encaisse'  => $lignes->sum('airsi_encaisse'),
            'net_a_payer'     => $lignes->sum('net_a_payer'),
            'encaisse'        => $lignes->sum('encaisse'),
        ];
        $totaux->airsi_a_encaisser = max(0, $totaux->airsi_facture - $totaux->airsi_encaisse);

        $parService = $lignes->groupBy('service')->map(fn ($g, $nom) => (object) [
            'service'        => $nom,
            'nb'             => $g->count(),
            'base'           => $g->sum('base'),
            'airsi_facture'  => $g->sum('airsi_facture'),
            'airsi_encaisse' => $g->sum('airsi_encaisse'),
        ])->sortByDesc('airsi_facture')->values();

        return view('admin.comptabilite.airsiCollectee', [
            'lignes'      => $lignes,
            'totaux'      => $totaux,
            'parService'  => $parService,
            'tauxConfig'  => $tauxConfig,
            'du'          => $du,
            'au'          => $au,
            'service'     => $service,
            'nbAnomalies' => $lignes->where('anomalie', true)->count(),
        ]);
    }

    /**
     * Une ligne de l'état d'AIRSI, construite depuis le document qui le porte.
     *
     * LA BASE est celle de Help::airsiPour au moment de la création : HT net
     * de remise + TVA de la marchandise pour une vente ou une location (le
     * transport n'y entre pas) ; transport + TVA du transport pour une
     * demande de livraison, qui ne facture que du transport.
     */
    private function ligneDAirsi($doc, string $type, float $tauxConfig): ?object
    {
        if (!$doc->affaireVivante()) {
            return null;
        }

        switch ($type) {
            case \Help::$LOCATION:
                $ht = (float) $doc->detailLocation->sum('prix');
                if ($ht <= 0) {
                    $ht = (float) $doc->montant_total;
                }
                $base    = max(0, $ht - (float) ($doc->remise ?? 0)) + (float) ($doc->tvaLocation->montant ?? 0);
                $date    = $doc->date_location ?? $doc->created_at;
                $service = 'Location';
                break;

            case \Help::$LIVRAISON:
                $tva = (float) TvaCommande::where('commande_id', $doc->id)
                    ->where('type_affaire', \Help::$LIVRAISON)
                    ->where('statut', \Help::$STATUT_ACTIF)
                    ->sum('montant');
                $base    = (float) $doc->montantTotal + $tva;
                $date    = $doc->created_at;
                $service = 'Demande de livraison';
                break;

            default: // VENTE
                $base    = max(0, $doc->montantHT() - (float) ($doc->remise ?? 0)) + (float) ($doc->TvaCommande->montant ?? 0);
                $date    = $doc->date_commande ?? $doc->created_at;
                $service = 'Vente';
        }

        $airsi     = (float) $doc->airsi;
        $netAPayer = (float) $doc->montantAPayer();
        $encaisse  = (float) $doc->montantPayeComptant();

        // Part réglée, plafonnée à 1 : un trop-perçu ne crée pas d'acompte.
        $part = $netAPayer > 0 ? min(1, $encaisse / $netAPayer) : 0.0;

        $taux = $base > 0 ? $airsi / $base * 100 : 0.0;

        return (object) [
            'date'           => $date ? Carbon::parse($date) : null,
            'numero'         => $doc->numero ?? ('#' . $doc->id),
            'client'         => $doc->client?->display_name ?? '-',
            'service'        => $service,
            'base'           => round($base),
            'taux'           => $taux,
            'airsi_facture'  => round($airsi),
            'net_a_payer'    => round($netAPayer),
            'encaisse'       => round($encaisse),
            'airsi_encaisse' => round($airsi * $part),
            'solde'          => $part >= 1 ? 'Soldée' : ($encaisse > 0 ? 'Partielle' : 'Impayée'),
            // Le montant figé reste la référence ; un écart se signale, il ne se
            // corrige pas en silence. Il se juge EN FRANCS : l'acompte est
            // arrondi au franc, et 8 F sur 150 F (5,33 %) n'est pas une anomalie.
            'anomalie'       => $base > 0 && abs($airsi - round($base * $tauxConfig / 100)) > 1,
        ];
    }

    /**
     * Bénéfices sur les livraisons.
     *
     * Ce qu'on FACTURE au client (grille tarifaire) et ce qu'on VERSE au
     * livreur (sa fiche) sont deux réglages indépendants. Rien ne les
     * confrontait : une course pouvait être vendue à perte sans que personne
     * ne le voie. Cet écran les met face à face, livraison par livraison.
     *
     * TROIS RÈGLES, TOUTES VOULUES :
     *
     *  - Le RETRAIT SUR PLACE est exclu. Le client vient chercher lui-même, il
     *    n'y a ni transport facturé ni livreur payé : ces lignes ne diraient
     *    rien de la rentabilité du transport et écraseraient les moyennes.
     *
     *  - Le transport facturé est réparti entre les livraisons d'un même
     *    document AU PRORATA DES QUANTITÉS. Le client paie un transport pour sa
     *    commande entière ; une commande livrée en trois fois doit voir ce coût
     *    réparti, sinon la première livraison porte tout le chiffre d'affaires
     *    et les deux suivantes apparaissent à perte.
     *
     *  - La répartition part du montant porté par le DOCUMENT, pas de la somme
     *    des coûts de ligne. Vérifié sur les données réelles : une commande
     *    facturée 65 F de transport avait ses lignes à zéro. Partir du document
     *    garantit que le total de l'écran est bien ce que le client a payé.
     */
    public function beneficesLivraisons(Request $request)
    {
        $du = $request->filled('du')
            ? Carbon::parse($request->input('du'))->startOfDay()
            : Carbon::today()->startOfMonth();
        $au = $request->filled('au')
            ? Carbon::parse($request->input('au'))->endOfDay()
            : Carbon::today()->endOfDay();

        $livraisons = Livraison::with([
                'livreur.user', 'client',
                'detailCommande.commande.client', 'detailCommande.commande.detailCommande',
                'detailLivraison.demandeLivraison.client',
            ])
            ->whereNull('deleted_at')
            ->get();

        // Le transport d'un document se répartit entre SES livraisons : il faut
        // donc les connaître toutes avant de chiffrer l'une d'elles.
        $parDocument = $livraisons->groupBy(fn (Livraison $l) => $this->cleDocument($l));

        $retraitsExclus = 0;

        $lignes = $livraisons
            ->map(function (Livraison $l) use ($parDocument, &$retraitsExclus) {
                $doc = $this->documentDeLaLivraison($l);
                if (!$doc) { return null; }

                if (!$doc->livrable) {
                    $retraitsExclus++;
                    return null;
                }

                $fratrie  = $parDocument->get($this->cleDocument($l)) ?? collect([$l]);
                $facture  = $this->quotePartTransport($l, $fratrie, $doc->transport);
                $verse    = $l->totalDuLivreur();
                $marge    = $facture - $verse;

                return (object) [
                    'date'      => Carbon::parse($l->date_livraison ?? $l->created_at),
                    'numero'    => $l->numero,
                    'service'   => $doc->service,
                    'document'  => $doc->numero,
                    'client'    => $doc->client,
                    'livreur'   => $l->livreur?->user?->nom_prenoms ?: '-',
                    'distance'  => (float) ($l->distance_km ?? 0),
                    'quantite'  => (float) ($l->qte ?? 0),
                    'facture'   => round($facture),
                    'verse'     => round($verse),
                    'marge'     => round($marge),
                    'taux'      => $facture > 0 ? $marge / $facture * 100 : null,
                    'etat'      => $l->etat_livraison,
                    'perte'     => $marge < 0,
                ];
            })
            ->filter()
            ->filter(fn ($l) => $l->date->between($du, $au))
            ->sortByDesc('date')
            ->values();

        $totaux = (object) [
            'nb'       => $lignes->count(),
            'facture'  => $lignes->sum('facture'),
            'verse'    => $lignes->sum('verse'),
        ];
        $totaux->marge = $totaux->facture - $totaux->verse;
        $totaux->taux  = $totaux->facture > 0 ? $totaux->marge / $totaux->facture * 100 : null;

        // Par livreur : c'est à cette maille qu'un tarif mal réglé se voit.
        $parLivreur = $lignes->groupBy('livreur')->map(fn ($g, $nom) => (object) [
            'livreur'  => $nom,
            'nb'       => $g->count(),
            'distance' => $g->sum('distance'),
            'facture'  => $g->sum('facture'),
            'verse'    => $g->sum('verse'),
            'marge'    => $g->sum('facture') - $g->sum('verse'),
        ])->sortBy('marge')->values();

        return view('admin.comptabilite.beneficesLivraisons', [
            'lignes'         => $lignes,
            'totaux'         => $totaux,
            'parLivreur'     => $parLivreur,
            'du'             => $du,
            'au'             => $au,
            'nbPertes'       => $lignes->where('perte', true)->count(),
            'retraitsExclus' => $retraitsExclus,
        ]);
    }

    /** Identifie le document d'où vient la livraison, pour regrouper la fratrie. */
    private function cleDocument(Livraison $l): string
    {
        $doc = $this->documentDeLaLivraison($l);

        return $doc ? $doc->service . '#' . $doc->id : 'orphelin#' . $l->id;
    }

    /**
     * Le document commercial derrière une livraison : son service, son numéro,
     * son client, le transport qu'il facture, et s'il est livré ou retiré.
     */
    private function documentDeLaLivraison(Livraison $l): ?object
    {
        switch ($l->provenance) {
            case \Help::$LIVRAISON:
                $dem = $l->detailLivraison?->demandeLivraison;
                if (!$dem) { return null; }
                return (object) [
                    'id'        => $dem->id,
                    'service'   => 'Demande de livraison',
                    'numero'    => $dem->numero ?? ('#' . $dem->id),
                    'client'    => $dem->client?->display_name ?? '-',
                    // Une demande de livraison NE FACTURE QUE du transport :
                    // son montant total est intégralement le chiffre d'affaires
                    // transport, il n'y a rien à en extraire.
                    'transport' => (float) ($dem->montantTotal ?? 0),
                    'livrable'  => true,
                ];

            case \Help::$LOCATION:
                // Une course de location porte l'id de sa LIGNE dans
                // detail_commande_id (voir Location::courses) ; detail_livraison_id
                // n'est renseigné que pour les demandes de livraison. Lu ici sur
                // la mauvaise colonne jusqu'au 10/09/2026 : les locations
                // manquaient à l'état des bénéfices.
                $loc = \App\Models\DetailLocation::with('location.client')->find($l->detail_commande_id)?->location;
                if (!$loc) { return null; }
                return (object) [
                    'id'        => $loc->id,
                    'service'   => 'Location',
                    'numero'    => $loc->numero ?? ('#' . $loc->id),
                    'client'    => $loc->client?->display_name ?? '-',
                    'transport' => (float) ($loc->cout_livraison_client ?? 0),
                    'livrable'  => (int) ($loc->est_livrable ?? 1) === 1,
                ];

            default: // COMMANDE
                $cmd = $l->detailCommande?->commande;
                if (!$cmd || !$cmd->id) { return null; }
                return (object) [
                    'id'        => $cmd->id,
                    'service'   => 'Vente',
                    'numero'    => $cmd->numero ?? ('#' . $cmd->id),
                    'client'    => $cmd->client?->display_name ?? '-',
                    'transport' => (float) ($cmd->cout_livraison_client ?? 0),
                    // est_livrable = 0 : le client vient retirer sur place.
                    'livrable'  => (int) ($cmd->est_livrable ?? 0) === 1,
                ];
        }
    }

    /**
     * Part du transport du document imputée à CETTE livraison, au prorata des
     * quantités transportées.
     *
     * Repli sur un partage à parts égales quand aucune quantité n'est
     * renseignée : mieux vaut répartir grossièrement que tout mettre sur la
     * première livraison venue.
     */
    private function quotePartTransport(Livraison $l, $fratrie, float $transport): float
    {
        if ($transport <= 0) { return 0.0; }

        $total = (float) $fratrie->sum(fn (Livraison $x) => (float) ($x->qte ?? 0));

        if ($total <= 0) {
            return $transport / max(1, $fratrie->count());
        }

        return $transport * ((float) ($l->qte ?? 0) / $total);
    }
}
