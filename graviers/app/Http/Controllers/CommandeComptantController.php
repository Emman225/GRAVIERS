<?php

namespace App\Http\Controllers;

use App\Models\Agence;
use App\Models\Client;
use App\Models\Commande;
use App\Models\Configuration;
use App\Models\LignePaiement;
use App\Models\ModePaiement;
use App\Models\Paiement;
use App\Models\User;
use App\Traits\DoubleValidationPaiement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CommandeComptantController extends Controller
{
    use DoubleValidationPaiement;

    /**
     * Liste des commandes comptant (clients ordinaires) avec calcul des
     * colonnes Excel : produit principal, HT, TVA, TTC, total à payer,
     * date limite, payé, reste, statut, observations.
     */
    public function commandes(Request $request)
    {
        $clientsOrdinaires = Client::where(function ($q) {
                $q->where('client_a_terme', 0)->orWhereNull('client_a_terme');
            })
            ->where('statut', 1)
            ->pluck('id');

        $commandes = Commande::with([
                'client', 'client.user', 'agence',
                'detailCommande', 'detailCommande.produit', 'factures',
            ])
            ->whereIn('client_id', $clientsOrdinaires)
            ->orderByDesc('date_commande')
            ->get();

        $config       = Configuration::first();
        $tauxTva      = (float) ($config?->tva ?? 18);
        $delaiAgence  = (int) ($config?->delai_max_paiement_agence ?? 3);
        $delaiAnnul   = (int) ($config?->delai_annulation_auto ?? 7);

        $lignes = $commandes->map(function (Commande $cmd) use ($tauxTva, $delaiAgence) {
            $client  = $cmd->client;
            $details = $cmd->detailCommande ?? collect();

            $produitPrincipal = '-';
            $quantite = 0;
            $puHt = 0;
            $montantHt = 0;
            if ($details->count() > 0) {
                $detailMax = $details->sortByDesc(fn($d) => (float) $d->qte * (float) $d->prix)->first();
                $produitPrincipal = $detailMax?->produit?->nom ?? '-';
                $quantite  = (float) $details->sum('qte');
                $puHt      = $details->count() === 1
                    ? (float) $details->first()->prix
                    : (float) $detailMax?->prix;
                $montantHt = (float) $details->sum(fn($d) => (float) $d->qte * (float) $d->prix);
            }

            // TVA réelle de la commande si enregistrée (tva_commande), sinon calcul
            // au taux courant. Total à payer via Commande::montantAPayer() : inclut
            // la REMISE (ignorée avant -> "Reste à payer" jamais soldé) et reste
            // cohérent quelle que soit l'origine web/mobile de la commande.
            $tva            = $cmd->TvaCommande ? (float) $cmd->TvaCommande->montant : $montantHt * ($tauxTva / 100);
            $montantTtc     = $montantHt + $tva;
            $fraisLivraison = (float) ($cmd->cout_livraison_client ?? 0);
            $totalAPayer    = $cmd->montantAPayer();

            // fallback si pas de détail : on retombe sur montant_total
            if ($montantHt == 0 && $cmd->montant_total > 0) {
                $totalAPayer = (float) $cmd->montant_total;
                $montantTtc  = $totalAPayer - $fraisLivraison;
                $montantHt   = $tauxTva > 0 ? $montantTtc / (1 + $tauxTva / 100) : $montantTtc;
                $tva         = $montantTtc - $montantHt;
            }

            $montantPaye = $cmd->montantPayeComptant();
            $reste       = max(0, $totalAPayer - $montantPaye);

            // Date limite : si non saisie, calculée = date_commande + delaiAgence
            $dateLimite = $cmd->date_limite_paiement
                ?? ($cmd->date_commande
                    ? \Carbon\Carbon::parse($cmd->date_commande)->addDays($delaiAgence)
                    : null);

            return (object) [
                'commande'           => $cmd,
                'numero_commande'    => $cmd->numero,
                'date_commande'      => $cmd->date_commande,
                'nom_client'         => $client ? trim($client->nom . ' ' . $client->prenom) : '-',
                'telephone'          => $client?->contact1,
                'email'              => $client?->user?->email ?? $client?->email,
                'agence_code'        => $cmd->agence?->code ?? '-',
                'agence_nom'         => $cmd->agence?->nom ?? '-',
                'produit_principal'  => $produitPrincipal,
                'quantite'           => $quantite,
                'pu_ht'              => $puHt,
                'montant_ht'         => $montantHt,
                'tva'                => $tva,
                'montant_ttc'        => $montantTtc,
                'frais_livraison'    => $fraisLivraison,
                'total_a_payer'      => $totalAPayer,
                'date_limite_paiement' => $dateLimite,
                'montant_paye'       => $montantPaye,
                'reste_a_payer'      => $reste,
                'statut'             => $cmd->statutComptant(),
                'observations'       => $cmd->note,
            ];
        });

        return view('admin.comptant.commandes', [
            'lignes'      => $lignes,
            'tauxTva'     => $tauxTva,
            'delaiAgence' => $delaiAgence,
            'delaiAnnul'  => $delaiAnnul,
        ]);
    }

    /**
     * Journal des encaissements en agence (paiements de commandes
     * de clients ordinaires).
     */
    public function encaissements(Request $request)
    {
        $clientsOrdinaires = Client::where(function ($q) {
                $q->where('client_a_terme', 0)->orWhereNull('client_a_terme');
            })
            ->where('statut', 1)
            ->pluck('id');

        // Inclure les paiements validés (statut=1) ET en attente de validation (statut=2).
        // Restreindre aux encaissements RÉELLEMENT EFFECTUÉS EN AGENCE : ceux qui ont été
        // initiés via le modal "Encaissement en agence" (storeEncaissement), donc avec
        // agence_id ET caissier_id renseignés. Les paiements issus du flow normal
        // (mobile money, web, etc.) qui ont un mode hors-ligne mais pas d'agence sont exclus.
        $paiements = Paiement::with(['client', 'client.user', 'agence', 'caissier'])
            ->whereIn('client_id', $clientsOrdinaires)
            ->whereIn('statut', [1, 2])
            ->whereNotNull('agence_id')
            ->whereNotNull('caissier_id')
            ->orderByDesc('created_at')
            ->get();

        $userId = Auth::id();
        $estAdmin = in_array((int) (Auth::user()?->type_user_id ?? 0), [1, 2], true);

        $lignes = $paiements->map(function (Paiement $p) use ($userId, $estAdmin) {
            $cmd = $p->service === 'COMMANDE' && $p->service_id
                ? Commande::find($p->service_id)
                : null;
            $ligne = LignePaiement::where('paiement_id', $p->id)->first();
            $mode  = null;
            if ($ligne) {
                $modeObj = ModePaiement::find($ligne->mode_paiement_id);
                $mode    = $ligne->moyen_paiement ?: ($modeObj?->libelle);
            }

            // Détermine si l'utilisateur courant peut valider ce paiement
            $enAttente   = (int) $p->statut === 2;
            $peutValider = $enAttente && $estAdmin && (int) $p->user_valide_id !== (int) $userId;

            return (object) [
                'paiement_id'       => $p->id,
                'date_encaissement' => $p->created_at,
                'numero_commande'   => $cmd?->numero ?? '-',
                'client_nom'        => $p->client ? trim($p->client->nom . ' ' . $p->client->prenom) : '-',
                'agence_code'       => $p->agence?->code ?? '-',
                'agence_nom'        => $p->agence?->nom ?? '-',
                'montant_encaisse'  => (float) $p->montant_total,
                'mode_paiement'     => $mode ?: '-',
                'caissier'          => $p->caissier?->nom_prenoms ?? '-',
                'numero_recu'       => $p->numero_recu ?? $p->code,
                'en_attente'        => $enAttente,
                'peut_valider'      => $peutValider,
            ];
        });

        // Le total encaissé ne compte que les paiements validés (pas les en-attente)
        $totalEncaisse = $lignes->where('en_attente', false)->sum('montant_encaisse');

        // Données pour le formulaire d'encaissement (modal)
        $agences      = Agence::where('statut', 1)->orderBy('nom')->get();
        $modesPaiement = ModePaiement::where('statut', 1)->orderBy('libelle')->get();
        $commandesNonSoldees = Commande::with(['client'])
            ->whereIn('client_id', $clientsOrdinaires)
            ->where('statut', '!=', 0)
            ->where(function ($q) {
                $q->whereNull('statut_comptant')
                  ->orWhereNotIn('statut_comptant', ['Annulée']);
            })
            ->orderByDesc('date_commande')
            ->limit(200)
            ->get()
            ->map(function (Commande $c) {
                $paye  = $c->montantPayeComptant();
                $reste = max(0, (float) $c->montant_total - $paye);
                return (object) [
                    'numero'        => $c->numero,
                    'client_nom'    => $c->client ? trim($c->client->nom . ' ' . $c->client->prenom) : '-',
                    'total_a_payer' => (float) $c->montant_total,
                    'reste'         => $reste,
                    'agence_id'     => $c->agence_id,
                ];
            })
            ->filter(fn($c) => $c->reste > 0)
            ->values();

        return view('admin.comptant.encaissements', [
            'lignes'              => $lignes,
            'totalEncaisse'       => $totalEncaisse,
            'agences'             => $agences,
            'modesPaiement'       => $modesPaiement,
            'commandesNonSoldees' => $commandesNonSoldees,
        ]);
    }

    /**
     * AJAX : retourne les détails d'une commande comptant + son historique
     * de paiements (pour pré-remplir le formulaire d'encaissement).
     */
    public function commandeHistorique(Request $request, $numero)
    {
        $cmd = Commande::with(['client', 'client.user', 'agence'])
            ->where('numero', $numero)
            ->first();
        if (!$cmd) {
            return response()->json(['error' => 'Commande introuvable'], 404);
        }

        $totalAPayer = (float) $cmd->montant_total;
        $totalPaye   = $cmd->montantPayeComptant();
        $reste       = max(0, $totalAPayer - $totalPaye);

        // Historique des paiements liés à cette commande
        $paiements = Paiement::with(['agence', 'caissier'])
            ->where(function ($q) use ($cmd) {
                $q->where(function ($qq) use ($cmd) {
                    $qq->where('service', 'COMMANDE')->where('service_id', $cmd->id);
                })->orWhereIn('facture_id', $cmd->factures()->pluck('id'));
            })
            ->where('statut', 1)
            ->orderBy('created_at')
            ->get();

        $historique = $paiements->map(function (Paiement $p, $idx) use ($paiements) {
            $ligne = LignePaiement::where('paiement_id', $p->id)->first();
            $mode  = null;
            if ($ligne) {
                $modeObj = ModePaiement::find($ligne->mode_paiement_id);
                $mode    = $ligne->moyen_paiement ?: ($modeObj?->libelle);
            }
            return [
                'tranche'      => ($idx + 1) . '/' . $paiements->count(),
                'date'         => $p->created_at?->format('d/m/Y H:i'),
                'montant'      => (float) $p->montant_total,
                'mode'         => $mode ?? '-',
                'agence_code'  => $p->agence?->code ?? '-',
                'caissier'     => $p->caissier?->nom_prenoms ?? '-',
                'numero_recu'  => $p->numero_recu ?? $p->code,
                'recu_url'     => route('show.comptant.recu', $p->id),
                'recu_pdf_url' => route('show.comptant.recuPdf', $p->id),
            ];
        });

        return response()->json([
            'commande' => [
                'numero'        => $cmd->numero,
                'date_commande' => optional($cmd->date_commande)->format('d/m/Y') ?? \Carbon\Carbon::parse($cmd->date_commande)->format('d/m/Y'),
                'client_nom'    => $cmd->client ? trim($cmd->client->nom . ' ' . $cmd->client->prenom) : '-',
                'client_id'     => $cmd->client_id,
                'agence_id'     => $cmd->agence_id,
                'agence_code'   => $cmd->agence?->code ?? '-',
                'total_a_payer' => $totalAPayer,
                'total_paye'    => $totalPaye,
                'reste_a_payer' => $reste,
                'tranche_num'   => $paiements->count() + 1,
            ],
            'historique' => $historique,
        ]);
    }

    /**
     * Enregistrement d'un encaissement en agence (peut se faire en plusieurs tranches).
     * Crée :
     *  - 1 ligne dans `paiement` (avec agence_id, caissier_id, numero_recu)
     *  - 1 ligne dans `ligne_paiement` (mode + référence)
     */
    public function storeEncaissement(Request $request)
    {
        $validated = $request->validate([
            'numero_commande' => 'required|string|exists:commande,numero',
            'agence_id'       => 'required|integer|exists:agence,id',
            'mode_paiement_id'=> 'required|integer|exists:mode_paiement,id',
            'montant'         => 'required|numeric|min:1',
            'date_encaissement' => 'nullable|date',
            'reference'       => 'nullable|string|max:80',
            'notes'           => 'nullable|string|max:500',
        ]);

        $cmd = Commande::where('numero', $validated['numero_commande'])->firstOrFail();
        $totalAPayer = (float) $cmd->montant_total;
        $totalPaye   = $cmd->montantPayeComptant();
        $reste       = max(0, $totalAPayer - $totalPaye);

        if ($validated['montant'] > $reste + 0.01) {
            return back()
                ->withInput()
                ->with('error', "Le montant saisi ({$validated['montant']}) dépasse le reste à payer ({$reste}).");
        }

        $modePaiement = ModePaiement::find($validated['mode_paiement_id']);
        $caissier     = Auth::user();

        DB::beginTransaction();
        try {
            // Numéro de reçu : RC-YYYY-XXX
            $year = date('Y');
            $lastNum = (int) Paiement::where('numero_recu', 'like', "RC-{$year}-%")
                ->selectRaw('MAX(CAST(SUBSTRING(numero_recu, 9) AS UNSIGNED)) AS n')
                ->value('n');
            $numeroRecu = sprintf('RC-%s-%03d', $year, $lastNum + 1);

            $paiement = Paiement::create(array_merge([
                'client_id'      => $cmd->client_id,
                'code'           => 'PAY-' . strtoupper(substr(md5(uniqid()), 0, 8)),
                'libelle'        => $validated['notes'] ?? ('Encaissement agence - ' . $cmd->numero),
                'montant_total'  => $validated['montant'],
                'montant_restant'=> 0,
                // statut=2 = en attente de la 2e validation
                // statut=1 sera mis lors de la validation par un admin différent
                'statut'         => 2,
                'service'        => 'COMMANDE',
                'service_id'     => $cmd->id,
                'agence_id'      => $validated['agence_id'],
                'caissier_id'    => $caissier?->id,
                'numero_recu'    => $numeroRecu,
                'created_at'     => $validated['date_encaissement'] ?? now(),
                'updated_at'     => now(),
            ], $this->initierValidation()));

            LignePaiement::create([
                'paiement_id'      => $paiement->id,
                'mode_paiement_id' => $validated['mode_paiement_id'],
                'reference'        => $validated['reference'] ?? null,
                'moyen_paiement'   => $modePaiement?->libelle,
                'date_paiement'    => $validated['date_encaissement'] ?? now(),
                'montant'          => $validated['montant'],
                // statut=2 aligné sur le paiement parent
                'statut'           => 2,
                'user_id'          => $caissier?->id,
                'code_paiement'    => $paiement->code,
                'service'          => 'COMMANDE',
                'service_id'       => $cmd->id,
                'created_at'       => $validated['date_encaissement'] ?? now(),
                'updated_at'       => now(),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Erreur enregistrement : ' . $e->getMessage());
        }

        return redirect()
            ->route('show.comptant.encaissements')
            ->with('success', "Encaissement {$numeroRecu} créé. En attente de validation par un autre administrateur.");
    }

    /**
     * 2e validation d'un encaissement en agence : seul un admin ≠ créateur
     * peut le rendre actif. Tant qu'il n'est pas validé, l'encaissement
     * n'est pas comptabilisé dans le montant payé de la commande.
     */
    public function validerEncaissement($paiementId)
    {
        $paiement = Paiement::find($paiementId);

        $result = $this->validerPaiement($paiement);
        if (!$result['ok']) {
            return back()->with('error', $result['message']);
        }

        // Activer aussi les lignes de paiement liées
        LignePaiement::where('paiement_id', $paiement->id)->update(['statut' => 1]);

        // Activer le paiement (statut=1)
        $paiement->update(['statut' => 1]);

        return back()->with('success', "Encaissement {$paiement->numero_recu} validé. Le reçu est maintenant disponible.");
    }

    /**
     * Affichage HTML du reçu (imprimable via window.print).
     */
    public function recu($paiementId)
    {
        $paiement = Paiement::with(['client', 'client.user', 'agence', 'caissier'])
            ->findOrFail($paiementId);
        $data = $this->buildRecuData($paiement);
        return view('admin.comptant.recu', $data);
    }

    /**
     * Téléchargement PDF du reçu.
     */
    public function recuPdf($paiementId)
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(120);

        $paiement = Paiement::with(['client', 'client.user', 'agence', 'caissier'])
            ->findOrFail($paiementId);
        $data = $this->buildRecuData($paiement);
        $data['pdfMode'] = true;

        try {
            $pdf = \PDF::loadView('admin.comptant.recu-pdf', $data)
                ->setPaper('A5', 'portrait')
                ->setOptions([
                    'isRemoteEnabled' => false,
                    'isHtml5ParserEnabled' => true,
                    'defaultFont' => 'DejaVu Sans',
                ]);
            $filename = 'recu-' . ($paiement->numero_recu ?? $paiement->id) . '.pdf';
            return $pdf->download($filename);
        } catch (\Throwable $e) {
            \Log::error('Erreur génération PDF reçu : ' . $e->getMessage(), [
                'paiement_id' => $paiementId,
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->with('error', 'Impossible de générer le PDF : ' . $e->getMessage());
        }
    }

    /**
     * Prépare les données partagées entre vue HTML et PDF du reçu.
     */
    private function buildRecuData(Paiement $paiement): array
    {
        $cmd = $paiement->service === 'COMMANDE' && $paiement->service_id
            ? Commande::find($paiement->service_id)
            : null;

        // Calcul de la tranche
        $allPaiements = Paiement::where(function ($q) use ($cmd, $paiement) {
                if ($cmd) {
                    $q->where(function ($qq) use ($cmd) {
                        $qq->where('service', 'COMMANDE')->where('service_id', $cmd->id);
                    });
                } else {
                    $q->where('id', $paiement->id);
                }
            })
            ->where('statut', 1)
            ->orderBy('created_at')
            ->get();

        $trancheNum = $allPaiements->search(fn($p) => $p->id === $paiement->id);
        $trancheNum = $trancheNum === false ? 1 : ($trancheNum + 1);
        $trancheTotal = $allPaiements->count();

        $totalAPayer = $cmd ? (float) $cmd->montant_total : (float) $paiement->montant_total;
        $totalPaye   = $cmd ? $cmd->montantPayeComptant() : (float) $paiement->montant_total;
        $reste       = max(0, $totalAPayer - $totalPaye);

        $ligne = LignePaiement::where('paiement_id', $paiement->id)->first();
        $mode  = null;
        if ($ligne) {
            $modeObj = ModePaiement::find($ligne->mode_paiement_id);
            $mode    = $ligne->moyen_paiement ?: ($modeObj?->libelle);
        }

        return [
            'paiement'      => $paiement,
            'commande'      => $cmd,
            'config'        => Configuration::first(),
            'trancheNum'    => $trancheNum,
            'trancheTotal'  => $trancheTotal,
            'totalAPayer'   => $totalAPayer,
            'totalPaye'     => $totalPaye,
            'reste'         => $reste,
            'mode'          => $mode ?? '-',
            'reference'     => $ligne?->reference,
            'pdfMode'       => false,
        ];
    }

    /**
     * Synthèse des commandes comptant : indicateurs globaux + répartition
     * par agence selon la feuille Excel "Synthèse".
     */
    public function synthese(Request $request)
    {
        $clientsOrdinaires = Client::where(function ($q) {
                $q->where('client_a_terme', 0)->orWhereNull('client_a_terme');
            })
            ->where('statut', 1)
            ->pluck('id');

        $commandes = Commande::with(['agence', 'detailCommande'])
            ->whereIn('client_id', $clientsOrdinaires)
            ->get();

        $totalCommande = 0.0;
        $totalEncaisse = 0.0;

        $countEnAttente   = 0;
        $countEnRetard    = 0;
        $countPayees      = 0;
        $countLivrees     = 0;
        $countAnnulees    = 0;
        $countPartielles  = 0;

        foreach ($commandes as $cmd) {
            $totalCommande += (float) $cmd->montant_total;
            $totalEncaisse += $cmd->montantPayeComptant();
            switch ($cmd->statutComptant()) {
                case 'En attente paiement': $countEnAttente++; break;
                case 'En retard':           $countEnRetard++; break;
                case 'Payée':               $countPayees++; break;
                case 'Livrée':              $countLivrees++; break;
                case 'Annulée':             $countAnnulees++; break;
                case 'Partiellement payée': $countPartielles++; break;
            }
        }

        $creanceTotale    = max(0, $totalCommande - $totalEncaisse);
        $tauxConversion   = $commandes->count() > 0
            ? ($countPayees / $commandes->count()) * 100
            : 0;

        // Répartition par agence
        // Les commandes (créées depuis le front-end client) n'ont généralement
        // pas d'agence_id. L'information d'agence vient des paiements
        // (encaissements en agence). On agrège donc via les paiements
        // tout en gardant les commandes éventuellement assignées directement.
        $paiementsCommandes = Paiement::where('service', 'COMMANDE')
            ->whereNotNull('agence_id')
            ->where('statut', 1)
            ->whereIn('client_id', $clientsOrdinaires)
            ->get(['agence_id', 'service_id', 'montant_total']);

        $repartitionAgence = Agence::all()->map(function (Agence $ag) use ($commandes, $paiementsCommandes) {
            // Commandes assignées directement à cette agence
            $cmdAssignees = $commandes->where('agence_id', $ag->id);

            // Commandes ayant au moins un paiement dans cette agence
            $cmdIdsViaPaiement = $paiementsCommandes->where('agence_id', $ag->id)
                ->pluck('service_id')->unique();
            $cmdViaPaiement = $commandes->whereIn('id', $cmdIdsViaPaiement);

            // Union (commandes distinctes par id)
            $cmdsAgence = $cmdAssignees->merge($cmdViaPaiement)->unique('id');

            if ($cmdsAgence->isEmpty()) {
                return null;
            }

            $totalCommandeAgence = (float) $cmdsAgence->sum('montant_total');
            $totalEncaisseAgence = (float) $paiementsCommandes
                ->where('agence_id', $ag->id)
                ->sum('montant_total');
            $totalPayeGlobal = (float) $cmdsAgence->sum(fn($c) => $c->montantPayeComptant());

            return (object) [
                'agence'         => $ag,
                'nb_commandes'   => $cmdsAgence->count(),
                'total_encaisse' => $totalEncaisseAgence,
                'reste_du'       => max(0, $totalCommandeAgence - $totalPayeGlobal),
            ];
        })->filter()->values();

        $config = Configuration::first();

        return view('admin.comptant.synthese', [
            'nombreCommandes'   => $commandes->count(),
            'totalCommande'     => $totalCommande,
            'totalEncaisse'     => $totalEncaisse,
            'creanceTotale'     => $creanceTotale,
            'countEnAttente'    => $countEnAttente,
            'countEnRetard'     => $countEnRetard,
            'countPayees'       => $countPayees,
            'countLivrees'      => $countLivrees,
            'countAnnulees'     => $countAnnulees,
            'countPartielles'   => $countPartielles,
            'tauxConversion'    => $tauxConversion,
            'repartitionAgence' => $repartitionAgence,
            'config'            => $config,
        ]);
    }
}
