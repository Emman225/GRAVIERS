<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Commande;
use App\Models\Configuration;
use App\Models\Facture;
use App\Models\Paiement;
use Illuminate\Http\Request;

/**
 * Récapitulatif global des créances clients (Terme + Comptant).
 * Consolide les données des fichiers :
 *  - 01_Suivi_Creances_Clients_Terme   (factures clients à terme)
 *  - 02_Suivi_Creances_Clients_Comptant (commandes comptant en agence)
 */
class RecapCreancesController extends Controller
{
    /**
     * Tableau de bord global : KPI, répartition, créances échues, top débiteurs.
     */
    public function dashboard(Request $request)
    {
        // ===== 1. Données Clients à Terme (factures) =====
        $clientsTermeIds = Client::where('client_a_terme', 1)->where('statut', 1)->pluck('id');
        $factures        = Facture::with('paiements')->whereIn('client_id', $clientsTermeIds)->get();

        $totalTerme         = 0.0;
        $payeTerme          = 0.0;
        $resteTerme         = 0.0;
        $echuTerme          = 0.0;
        $aEchoirTerme       = 0.0;
        $clientsTermeUniques = [];

        foreach ($factures as $f) {
            $paye  = (float) $f->paiements->where('statut', 1)->sum('montant_total');
            $total = (float) $f->montant;
            $reste = max(0, $total - $paye);
            $totalTerme += $total;
            $payeTerme  += $paye;
            $resteTerme += $reste;

            $statut = $f->statutCreance();
            if (in_array($statut, ['Échue partielle', 'Échue impayée'])) {
                $echuTerme += $reste;
            } elseif ($statut === 'À échoir') {
                $aEchoirTerme += $reste;
            }
            if ($f->client_id) $clientsTermeUniques[$f->client_id] = true;
        }

        // ===== 2. Données Clients Comptant (commandes) =====
        $clientsComptantIds = Client::where(function ($q) {
                $q->where('client_a_terme', 0)->orWhereNull('client_a_terme');
            })->where('statut', 1)->pluck('id');
        $commandes = Commande::whereIn('client_id', $clientsComptantIds)->get();

        $totalComptant     = 0.0;
        $payeComptant      = 0.0;
        $resteComptant     = 0.0;
        $echuComptant      = 0.0;
        $aEchoirComptant   = 0.0;
        $clientsCptUniques = [];

        foreach ($commandes as $c) {
            $paye  = $c->montantPayeComptant();
            $total = (float) $c->montant_total;
            $reste = max(0, $total - $paye);
            $totalComptant += $total;
            $payeComptant  += $paye;
            $resteComptant += $reste;

            $statut = $c->statutComptant();
            if (in_array($statut, ['En retard', 'Partiellement payée'])) {
                $echuComptant += $reste;
            } elseif ($statut === 'En attente paiement') {
                $aEchoirComptant += $reste;
            }
            if ($c->client_id) $clientsCptUniques[$c->client_id] = true;
        }

        // ===== 3. KPI globaux =====
        $totalCreances    = $resteTerme + $resteComptant;
        $echuTotal        = $echuTerme + $echuComptant;
        $aEchoirTotal     = $aEchoirTerme + $aEchoirComptant;
        $encaisseCeMois   = $this->totalEncaisseCeMois($clientsTermeIds, $clientsComptantIds);

        // ===== 4. Répartition par type =====
        $repartition = collect([
            (object) [
                'type'           => 'Clients à Terme (Factures)',
                'montant_total'  => $resteTerme,
                'nb_clients'     => count($clientsTermeUniques),
                'nb_documents'   => $factures->count(),
                'echu'           => $echuTerme,
                'a_echoir'       => $aEchoirTerme,
                'pct'            => $totalCreances > 0 ? ($resteTerme / $totalCreances) * 100 : 0,
                'detail_route'   => route('show.recapCreances.detailTerme'),
                'detail_label'   => 'Voir Détail Terme',
            ],
            (object) [
                'type'           => 'Clients Comptant (Agence)',
                'montant_total'  => $resteComptant,
                'nb_clients'     => count($clientsCptUniques),
                'nb_documents'   => $commandes->count(),
                'echu'           => $echuComptant,
                'a_echoir'       => $aEchoirComptant,
                'pct'            => $totalCreances > 0 ? ($resteComptant / $totalCreances) * 100 : 0,
                'detail_route'   => route('show.recapCreances.detailComptant'),
                'detail_label'   => 'Voir Détail Comptant',
            ],
        ]);

        // ===== 5. Créances échues impayées (priorité relance) =====
        $creancesEchues = $this->buildCreancesEchues($factures, $commandes);

        // ===== 6. Top 5 clients débiteurs =====
        $topDebiteurs = $this->buildTopDebiteurs($factures, $commandes);

        return view('admin.recapCreances.dashboard', [
            'totalCreances'   => $totalCreances,
            'echuTotal'       => $echuTotal,
            'aEchoirTotal'    => $aEchoirTotal,
            'encaisseCeMois'  => $encaisseCeMois,
            'totalGlobal'     => $resteTerme + $resteComptant,
            'totalNbClients'  => count($clientsTermeUniques) + count($clientsCptUniques),
            'totalNbDocs'     => $factures->count() + $commandes->count(),
            'repartition'     => $repartition,
            'creancesEchues'  => $creancesEchues,
            'topDebiteurs'    => $topDebiteurs,
        ]);
    }

    /**
     * Détail des créances Clients à Terme (factures).
     */
    public function detailTerme(Request $request)
    {
        $clientsTermeIds = Client::where('client_a_terme', 1)->where('statut', 1)->pluck('id');
        $factures = Facture::with(['commande', 'commande.client', 'commande.client.user', 'paiements'])
            ->whereIn('client_id', $clientsTermeIds)
            ->orderByDesc('created_at')
            ->get();

        $lignes = $factures->map(function (Facture $f) {
            $client = $f->commande?->client;
            $paye   = (float) $f->paiements->where('statut', 1)->sum('montant_total');
            $total  = (float) $f->montant;
            $reste  = max(0, $total - $paye);

            return (object) [
                'numero'       => $f->numero,
                'date_facture' => $f->created_at,
                'client_nom'   => $client ? trim($client->nom . ' ' . $client->prenom) : '-',
                'telephone'    => $client?->contact1 ?? $client?->user?->contact ?? '-',
                'montant_ttc'  => $total,
                'paye'         => $paye,
                'reste_du'     => $reste,
                'echeance'     => $f->date_echeance,
                'statut'       => $f->statutCreance(),
                'jours_retard' => $f->joursRetard(),
            ];
        });

        return view('admin.recapCreances.detailTerme', [
            'lignes' => $lignes,
        ]);
    }

    /**
     * Détail des créances Clients Comptant (commandes en agence).
     */
    public function detailComptant(Request $request)
    {
        $clientsComptantIds = Client::where(function ($q) {
                $q->where('client_a_terme', 0)->orWhereNull('client_a_terme');
            })->where('statut', 1)->pluck('id');

        $commandes = Commande::with(['client', 'client.user', 'agence'])
            ->whereIn('client_id', $clientsComptantIds)
            ->orderByDesc('date_commande')
            ->get();

        $lignes = $commandes->map(function (Commande $c) {
            $client  = $c->client;
            $paye    = $c->montantPayeComptant();
            $total   = (float) $c->montant_total;
            $reste   = max(0, $total - $paye);
            $statut  = $c->statutComptant();
            // Le libellé Excel utilise "Encaissée" pour Payée
            $statutLabel = $statut === 'Payée' ? 'Encaissée' :
                           ($statut === 'Partiellement payée' ? 'Partielle' :
                           ($statut === 'En retard' ? 'Échu' : $statut));

            return (object) [
                'numero'       => $c->numero,
                'date'         => $c->date_commande,
                'client_nom'   => $client ? trim($client->nom . ' ' . $client->prenom) : '-',
                'telephone'    => $client?->contact1 ?? $client?->user?->contact ?? '-',
                'agence'       => $c->agence?->nom ?? ($c->agence?->code ?? '-'),
                'montant_ttc'  => $total,
                'encaisse'     => $paye,
                'reste_du'     => $reste,
                'statut'       => $statutLabel,
            ];
        });

        return view('admin.recapCreances.detailComptant', [
            'lignes' => $lignes,
        ]);
    }

    // =====================================================================
    // Helpers privés
    // =====================================================================

    private function totalEncaisseCeMois($clientsTermeIds, $clientsComptantIds): float
    {
        $debutMois = now()->startOfMonth();
        $finMois   = now()->endOfMonth();
        $tousIds   = $clientsTermeIds->merge($clientsComptantIds)->unique();

        return (float) Paiement::whereIn('client_id', $tousIds)
            ->where('statut', 1)
            ->whereBetween('created_at', [$debutMois, $finMois])
            ->sum('montant_total');
    }

    /**
     * Liste des factures/commandes échues avec reste à payer > 0,
     * triées par jours de retard décroissant.
     */
    private function buildCreancesEchues($factures, $commandes)
    {
        $items = collect();

        foreach ($factures as $f) {
            $paye  = (float) $f->paiements->where('statut', 1)->sum('montant_total');
            $total = (float) $f->montant;
            $reste = max(0, $total - $paye);
            $statut = $f->statutCreance();
            $jr    = $f->joursRetard();
            if ($reste > 0 && $jr > 0) {
                $client = $f->commande?->client;
                $items->push((object) [
                    'ref'          => $f->numero,
                    'client_nom'   => $client ? trim($client->nom . ' ' . $client->prenom) : '-',
                    'type'         => 'Terme',
                    'date_emission'=> $f->created_at,
                    'reste'        => $reste,
                    'statut'       => $statut,
                    'jours_retard' => $jr,
                    'action'       => $this->actionRecommandee($jr),
                ]);
            }
        }

        foreach ($commandes as $c) {
            $paye  = $c->montantPayeComptant();
            $total = (float) $c->montant_total;
            $reste = max(0, $total - $paye);
            if ($reste > 0 && $c->date_limite_paiement) {
                $jr = \Carbon\Carbon::today()->diffInDays(\Carbon\Carbon::parse($c->date_limite_paiement), false);
                $jr = $jr < 0 ? abs($jr) : 0;
                if ($jr > 0) {
                    $client = $c->client;
                    $items->push((object) [
                        'ref'          => $c->numero,
                        'client_nom'   => $client ? trim($client->nom . ' ' . $client->prenom) : '-',
                        'type'         => 'Comptant',
                        'date_emission'=> $c->date_commande,
                        'reste'        => $reste,
                        'statut'       => $c->statutComptant(),
                        'jours_retard' => $jr,
                        'action'       => $this->actionRecommandee($jr),
                    ]);
                }
            }
        }

        return $items->sortByDesc('jours_retard')->values();
    }

    private function actionRecommandee(int $joursRetard): string
    {
        if ($joursRetard >= 90) return 'Mise en demeure';
        if ($joursRetard >= 60) return 'Relance niveau 3';
        if ($joursRetard >= 30) return 'Relance niveau 2';
        if ($joursRetard >= 7)  return 'Relance niveau 1';
        return 'Appeler';
    }

    /**
     * Top 5 clients ayant le plus gros reste à payer (toutes créances confondues).
     */
    private function buildTopDebiteurs($factures, $commandes)
    {
        $debiteurs = [];

        foreach ($factures as $f) {
            $client = $f->commande?->client;
            if (!$client) continue;
            $reste = max(0, (float) $f->montant - (float) $f->paiements->where('statut', 1)->sum('montant_total'));
            if ($reste <= 0) continue;
            if (!isset($debiteurs[$client->id])) {
                $debiteurs[$client->id] = (object) [
                    'client'     => $client,
                    'nom'        => trim($client->nom . ' ' . $client->prenom),
                    'tel'        => $client->contact1 ?? $client->user?->contact ?? '-',
                    'type'       => $client->client_a_terme ? 'Terme' : 'Comptant',
                    'total_du'   => 0.0,
                    'nb_docs'    => 0,
                    'plus_ancien'=> $f->created_at,
                ];
            }
            $debiteurs[$client->id]->total_du += $reste;
            $debiteurs[$client->id]->nb_docs++;
            if ($f->created_at && (!$debiteurs[$client->id]->plus_ancien || $f->created_at < $debiteurs[$client->id]->plus_ancien)) {
                $debiteurs[$client->id]->plus_ancien = $f->created_at;
            }
        }

        foreach ($commandes as $c) {
            $client = $c->client;
            if (!$client) continue;
            $reste = max(0, (float) $c->montant_total - $c->montantPayeComptant());
            if ($reste <= 0) continue;
            if (!isset($debiteurs[$client->id])) {
                $debiteurs[$client->id] = (object) [
                    'client'     => $client,
                    'nom'        => trim($client->nom . ' ' . $client->prenom),
                    'tel'        => $client->contact1 ?? $client->user?->contact ?? '-',
                    'type'       => $client->client_a_terme ? 'Terme' : 'Comptant',
                    'total_du'   => 0.0,
                    'nb_docs'    => 0,
                    'plus_ancien'=> $c->date_commande,
                ];
            }
            $debiteurs[$client->id]->total_du += $reste;
            $debiteurs[$client->id]->nb_docs++;
            if ($c->date_commande && (!$debiteurs[$client->id]->plus_ancien || $c->date_commande < $debiteurs[$client->id]->plus_ancien)) {
                $debiteurs[$client->id]->plus_ancien = $c->date_commande;
            }
        }

        $top = collect($debiteurs)->sortByDesc('total_du')->take(5)->values();

        return $top->map(function ($d, $i) {
            $age = $d->plus_ancien ? \Carbon\Carbon::parse($d->plus_ancien)->diffInDays(now()) : 0;
            $d->rang   = $i + 1;
            $d->risque = $age >= 60 ? 'ÉLEVÉ' : ($age >= 30 ? 'MOYEN' : 'FAIBLE');
            return $d;
        });
    }
}
