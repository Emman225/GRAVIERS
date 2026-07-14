<?php

namespace App\Http\Controllers;

use App\Models\Apporteur;
use App\Models\CommissionApporteur;
use App\Models\Configuration;
use App\Models\Enlevement;
use App\Models\Fournisseur;
use App\Models\Livraison;
use App\Models\Livreur;
use App\Models\PaiementApporteur;
use App\Models\PaiementFournisseur;
use App\Models\PaiementLivreur;
use Illuminate\Http\Request;

class RecapGlobalDettesController extends Controller
{
    /**
     * Tableau de bord récapitulatif des dettes (3 catégories + indicateurs clés).
     * Reproduit la feuille Excel "Tableau de Bord".
     */
    public function tableauBord(Request $request)
    {
        // ====== FOURNISSEURS ======
        $enlevements = Enlevement::with('fournisseur')->get();
        $foNbOps    = $enlevements->count();
        $foEngage   = $enlevements->sum(fn($e) => $e->montantTtc());
        $foPaye     = $enlevements->sum(fn($e) => $e->montantPaye());
        $foReste    = max(0, $foEngage - $foPaye);
        // Dettes immédiates = échues impayées
        $foDetteImmediate = $enlevements->filter(fn($e) => $e->statutDetteCalcule() === 'Échue impayée')
            ->sum(fn($e) => $e->resteAPayer());
        // Échéances dans les 30 prochains jours
        $jourFin30 = \Carbon\Carbon::today()->addDays(30);
        $fo30j = $enlevements->filter(function ($e) use ($jourFin30) {
                if (!$e->date_echeance) return false;
                $ech = \Carbon\Carbon::parse($e->date_echeance);
                return $ech->lessThanOrEqualTo($jourFin30) && $e->resteAPayer() > 0;
            })
            ->sum(fn($e) => $e->resteAPayer());

        // ====== LIVREURS ======
        $livraisons = Livraison::all();
        $lvNbOps   = $livraisons->count();
        $lvEngage  = $livraisons->sum(fn($l) => $l->totalDuLivreur());
        $lvPaye    = $livraisons->sum(fn($l) => $l->montantPayeLivreur());
        $lvReste   = max(0, $lvEngage - $lvPaye);
        // Dettes immédiates = "Validée à payer"
        $lvDetteImmediate = $livraisons->filter(fn($l) => $l->statutPaiementLivreurCalcule() === 'Validée à payer')
            ->sum(fn($l) => $l->resteAPayerLivreur());
        // Trésorerie 30 jours = tout reste à payer (livreurs payés au cycle hebdo)
        $lv30j = $lvReste;

        // ====== APPORTEURS ======
        $commissions = CommissionApporteur::with('commande')->get();
        $apNbOps   = $commissions->count();
        $apEngage  = $commissions->sum(fn($c) => (float) $c->montant);
        $apPaye    = $commissions->sum(fn($c) => $c->montantPayeCommission());
        $apReste   = max(0, $apEngage - $apPaye);
        // Dettes immédiates = "Due" (client a payé)
        $apDetteImmediate = $commissions->filter(fn($c) => $c->statutCommissionCalcule() === 'Due')
            ->sum(fn($c) => $c->resteAPayerCommission());
        // 30 jours
        $ap30j = $commissions->filter(function ($c) use ($jourFin30) {
                if (!$c->date_echeance) return false;
                $ech = \Carbon\Carbon::parse($c->date_echeance);
                return $ech->lessThanOrEqualTo($jourFin30) && $c->resteAPayerCommission() > 0;
            })
            ->sum(fn($c) => $c->resteAPayerCommission());

        // ====== TOTAUX ======
        $totalNbOps   = $foNbOps + $lvNbOps + $apNbOps;
        $totalEngage  = $foEngage + $lvEngage + $apEngage;
        $totalPaye    = $foPaye + $lvPaye + $apPaye;
        $totalReste   = $foReste + $lvReste + $apReste;

        // % dette par catégorie
        $foPct = $totalReste > 0 ? ($foReste / $totalReste) * 100 : 0;
        $lvPct = $totalReste > 0 ? ($lvReste / $totalReste) * 100 : 0;
        $apPct = $totalReste > 0 ? ($apReste / $totalReste) * 100 : 0;

        // Indicateurs clés
        $detteImmediate    = $foDetteImmediate + $lvDetteImmediate + $apDetteImmediate;
        $tresorerie30j     = $fo30j + $lv30j + $ap30j;
        $ratioPayeEngage   = $totalEngage > 0 ? ($totalPaye / $totalEngage) * 100 : 0;
        $ratioResteEngage  = $totalEngage > 0 ? ($totalReste / $totalEngage) * 100 : 0;
        $detteMoyenne      = $totalNbOps > 0 ? $totalReste / $totalNbOps : 0;

        return view('admin.recapDettes.tableauBord', compact(
            'foNbOps', 'foEngage', 'foPaye', 'foReste', 'foPct',
            'lvNbOps', 'lvEngage', 'lvPaye', 'lvReste', 'lvPct',
            'apNbOps', 'apEngage', 'apPaye', 'apReste', 'apPct',
            'totalNbOps', 'totalEngage', 'totalPaye', 'totalReste',
            'detteImmediate', 'tresorerie30j',
            'ratioPayeEngage', 'ratioResteEngage', 'detteMoyenne'
        ));
    }

    /**
     * Détail des dettes fournisseurs (8 colonnes Excel "Détail Fournisseurs").
     */
    public function detailFournisseurs(Request $request)
    {
        $enlevements = Enlevement::with([
                'fournisseur', 'produit',
                'livraison.detailCommande.commande',
            ])
            ->orderByDesc('created_at')
            ->get();

        $lignes = $enlevements->map(function (Enlevement $e) {
            $cmd     = $e->livraison?->detailCommande?->commande;
            $codeBe  = $e->code_enleve ?? ('BE-' . str_pad($e->id, 4, '0', STR_PAD_LEFT));

            return (object) [
                'numero_be'        => $codeBe,
                'date'             => $e->created_at,
                'fournisseur_nom'  => $e->fournisseur?->nom_prenoms ?? '-',
                'numero_commande'  => $cmd?->numero ?? '-',
                'produit'          => $e->produit?->nom ?? '-',
                'montant_ttc'      => $e->montantTtc(),
                'reste_a_payer'    => $e->resteAPayer(),
                'statut'           => $e->statutDetteCalcule(),
            ];
        });

        return view('admin.recapDettes.detailFournisseurs', [
            'lignes' => $lignes,
        ]);
    }

    /**
     * Détail des dettes livreurs (8 colonnes Excel "Détail Livreurs").
     */
    public function detailLivreurs(Request $request)
    {
        $livraisons = Livraison::with([
                'livreur', 'livreur.user',
                'detailCommande', 'detailCommande.commande', 'detailCommande.commande.client',
            ])
            ->orderByDesc('date_livraison')
            ->get();

        $lignes = $livraisons->map(function (Livraison $l) {
            $cmd       = $l->detailCommande?->commande;
            $client    = $cmd?->client;
            $codeLiv   = $l->numero ?? ('LIV-' . str_pad($l->id, 4, '0', STR_PAD_LEFT));

            return (object) [
                'numero_liv'      => $codeLiv,
                'date'            => $l->date_livraison,
                'livreur_nom'     => $l->livreur?->user?->nom_prenoms ?? '-',
                'numero_commande' => $cmd?->numero ?? '-',
                'client_nom'      => $client ? trim($client->nom . ' ' . $client->prenom) : '-',
                'total_du'        => $l->totalDuLivreur(),
                'reste_a_payer'   => $l->resteAPayerLivreur(),
                'statut'          => $l->statutPaiementLivreurCalcule(),
            ];
        });

        return view('admin.recapDettes.detailLivreurs', [
            'lignes' => $lignes,
        ]);
    }

    /**
     * Détail des dettes apporteurs (8 colonnes Excel "Détail Apporteurs").
     */
    public function detailApporteurs(Request $request)
    {
        $commissions = CommissionApporteur::with([
                'apporteur', 'apporteur.user',
                'commande', 'commande.client',
            ])
            ->orderByDesc('created_at')
            ->get();

        $lignes = $commissions->map(function (CommissionApporteur $com) {
            $cmd     = $com->commande;
            $client  = $cmd?->client;
            $codeCom = $com->numero ?? ('COM-' . str_pad($com->id, 3, '0', STR_PAD_LEFT));

            return (object) [
                'numero_com'      => $codeCom,
                'date'            => $cmd?->date_commande ?? $com->created_at,
                'apporteur_nom'   => $com->apporteur?->user?->nom_prenoms ?? '-',
                'numero_commande' => $cmd?->numero ?? '-',
                'client_nom'      => $client ? trim($client->nom . ' ' . $client->prenom) : '-',
                'commission_calc' => (float) $com->montant,
                'reste_a_payer'   => $com->resteAPayerCommission(),
                'statut'          => $com->statutCommissionCalcule(),
            ];
        });

        return view('admin.recapDettes.detailApporteurs', [
            'lignes' => $lignes,
        ]);
    }
}
