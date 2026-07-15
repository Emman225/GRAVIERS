<?php

namespace App\Http\Controllers;

use App\Models\Apporteur;
use App\Models\CommissionApporteur;
use App\Models\Configuration;
use App\Models\ModePaiement;
use App\Models\PaiementApporteur;
use App\Traits\DoubleValidationPaiement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DetteApporteurController extends Controller
{
    use DoubleValidationPaiement;

    /**
     * Liste des commissions des apporteurs avec calcul du statut commission,
     * du montant payé, du reste à payer et de la date d'échéance.
     */
    public function commissions(Request $request)
    {
        $commissions = CommissionApporteur::with([
                'apporteur', 'apporteur.user',
                'commande', 'commande.client',
            ])
            ->orderByDesc('created_at')
            ->get();

        $config       = Configuration::first();
        $delaiCom     = (int) ($config?->delai_paiement_commission ?? 15);
        $tauxStandard = (float) ($config?->taux_commission_standard ?? 3);

        $lignes = $commissions->map(function (CommissionApporteur $com, $idx) use ($delaiCom, $tauxStandard) {
            $apporteur = $com->apporteur;
            $cmd       = $com->commande;
            $client    = $cmd?->client;

            $codeApp  = preg_match('/^APP-/', (string) $apporteur?->code)
                ? $apporteur->code
                : ($apporteur ? 'APP-' . str_pad($apporteur->id, 3, '0', STR_PAD_LEFT) : '-');
            $codeCom  = $com->numero ?? ('COM-' . str_pad($com->id, 3, '0', STR_PAD_LEFT));

            $typeCmd = $client && (int) $client->client_a_terme === 1 ? 'Terme' : 'Comptant';

            $cmdTotal     = (float) ($cmd?->montant_total ?? 0);
            $cmdEncaisse  = $cmd ? $cmd->montantPayeComptant() : 0;
            $tauxBrut     = (float) ($apporteur?->pourcentage ?? 0);
            $taux         = $tauxBrut > 0 ? $tauxBrut : $tauxStandard;
            $commCalc     = (float) $com->montant;
            $commPayee    = $com->montantPayeCommission();
            $reste        = $com->resteAPayerCommission();

            // Date échéance par défaut = date_commande + delai_paiement_commission
            $dateEcheance = $com->date_echeance;
            if (!$dateEcheance && $cmd?->date_commande) {
                $dateEcheance = \Carbon\Carbon::parse($cmd->date_commande)->addDays($delaiCom);
            }

            // Date paiement effectif et mode (du dernier paiement)
            $dernierPaiement = PaiementApporteur::where('commission_id', $com->id)
                ->where('statut', 1)->orderByDesc('date_paiement')->first();
            $datePaiement = $dernierPaiement?->date_paiement;
            $modePaiement = $dernierPaiement
                ? ($dernierPaiement->modePaiement?->libelle ?? '-')
                : ($apporteur?->mode_paiement_prefere ?? '-');

            return (object) [
                'commission'        => $com,
                'numero_com'        => $codeCom,
                'date_commande'     => $cmd?->date_commande,
                'code_apporteur'    => $codeApp,
                'nom_apporteur'     => $apporteur?->user?->nom_prenoms ?? '-',
                'numero_commande'   => $cmd?->numero ?? '-',
                'type_commande'     => $typeCmd,
                'client_final'      => $client ? trim($client->nom . ' ' . $client->prenom) : '-',
                'montant_cmd_ttc'   => $cmdTotal,
                'montant_encaisse'  => (float) $cmdEncaisse,
                'taux_commission'   => (float) $taux,
                'commission_calc'   => $commCalc,
                'commission_payee'  => $commPayee,
                'reste_a_payer'     => $reste,
                'date_echeance'     => $dateEcheance,
                'date_paiement'     => $datePaiement,
                'mode_paiement'     => $modePaiement,
                'statut'            => $com->statutCommissionCalcule(),
                'observations'      => $com->observations,
            ];
        });

        return view('admin.apporteur.commissions', [
            'lignes'        => $lignes,
            'tauxStandard'  => $tauxStandard,
            'delaiCom'      => $delaiCom,
        ]);
    }

    /**
     * Journal des paiements de commissions aux apporteurs.
     */
    public function paiements(Request $request)
    {
        $paiements = PaiementApporteur::with(['apporteur', 'apporteur.user', 'commission', 'commission.commande', 'modePaiement'])
            ->whereIn('statut', [1, 2])
            ->orderByDesc('date_paiement')
            ->get();

        $userId = Auth::id();
        $estAdmin = in_array((int) (Auth::user()?->type_user_id ?? 0), [1, 2], true);

        $lignes = $paiements->map(function (PaiementApporteur $p) use ($userId, $estAdmin) {
            $apporteur = $p->apporteur;
            $codeApp = preg_match('/^APP-/', (string) $apporteur?->code)
                ? $apporteur->code
                : ($apporteur ? 'APP-' . str_pad($apporteur->id, 3, '0', STR_PAD_LEFT) : '-');
            $codeCom = $p->commission?->numero ?? ($p->commission ? 'COM-' . str_pad($p->commission->id, 3, '0', STR_PAD_LEFT) : '-');

            $enAttente   = (int) $p->statut === 2;
            $peutValider = $enAttente && $estAdmin && (int) $p->user_valide_id !== (int) $userId;

            return (object) [
                'paiement_id'      => $p->id,
                'date_paiement'    => $p->date_paiement,
                'numero_com'       => $codeCom,
                'numero_commande'  => $p->commission?->commande?->numero ?? '-',
                'code_apporteur'   => $codeApp,
                'nom_apporteur'    => $apporteur?->user?->nom_prenoms ?? '-',
                'montant'          => (float) $p->montant,
                'mode_paiement'    => $p->modePaiement?->libelle ?? '-',
                'reference'        => $p->reference,
                'notes'            => $p->notes,
                'en_attente'       => $enAttente,
                'peut_valider'     => $peutValider,
            ];
        });

        $totalPaye = $lignes->where('en_attente', false)->sum('montant');

        $modesPaiement = ModePaiement::liste();
        $commissionsDues = CommissionApporteur::with(['apporteur.user', 'commande'])
            ->orderByDesc('created_at')
            ->limit(300)
            ->get()
            ->map(function (CommissionApporteur $com) {
                $codeCom = $com->numero ?? ('COM-' . str_pad($com->id, 3, '0', STR_PAD_LEFT));
                $codeApp = preg_match('/^APP-/', (string) $com->apporteur?->code)
                    ? $com->apporteur->code
                    : 'APP-' . str_pad($com->apporteur?->id ?? 0, 3, '0', STR_PAD_LEFT);
                return (object) [
                    'id'             => $com->id,
                    'apporteur_id'   => $com->apporteur_id,
                    'code_com'       => $codeCom,
                    'apporteur_nom'  => $com->apporteur?->user?->nom_prenoms ?? '-',
                    'code_apporteur' => $codeApp,
                    // commande_id polymorphe : numéro de la LOCATION le cas échéant
                    'numero_cmd'     => $com->type_affaire === 'LOCATION'
                        ? ('LOC ' . (\App\Models\Location::find($com->commande_id)?->numero ?? '-'))
                        : ($com->commande?->numero ?? '-'),
                    'commission_calc'=> (float) $com->montant,
                    'reste'          => $com->resteAPayerCommission(),
                    'statut'         => $com->statutCommissionCalcule(),
                ];
            })
            // On ne propose que les commissions dues ou partiellement dues (pas en attente client, pas annulées, pas payées)
            ->filter(fn($x) => $x->reste > 0 && in_array($x->statut, ['Due', 'Partiellement due']))
            ->values();

        return view('admin.apporteur.paiements', [
            'lignes'           => $lignes,
            'totalPaye'        => $totalPaye,
            'modesPaiement'    => $modesPaiement,
            'commissionsDues'  => $commissionsDues,
        ]);
    }

    public function commissionHistorique(Request $request, $id)
    {
        $com = CommissionApporteur::with(['apporteur', 'apporteur.user', 'commande'])->findOrFail($id);
        $paiements = PaiementApporteur::with('modePaiement')
            ->where('commission_id', $com->id)->where('statut', 1)
            ->orderBy('date_paiement')->get();

        $historique = $paiements->map(function (PaiementApporteur $p, $i) use ($paiements) {
            return [
                'tranche'      => ($i + 1) . '/' . $paiements->count(),
                'date'         => optional($p->date_paiement)->format('d/m/Y'),
                'montant'      => (float) $p->montant,
                'mode'         => $p->modePaiement?->libelle ?? '-',
                'reference'    => $p->reference,
                'recu_url'     => route('show.apporteurs.recu', $p->id),
                'recu_pdf_url' => route('show.apporteurs.recuPdf', $p->id),
            ];
        });

        $codeCom = $com->numero ?? ('COM-' . str_pad($com->id, 3, '0', STR_PAD_LEFT));
        $codeApp = preg_match('/^APP-/', (string) $com->apporteur?->code)
            ? $com->apporteur->code
            : 'APP-' . str_pad($com->apporteur?->id ?? 0, 3, '0', STR_PAD_LEFT);

        return response()->json([
            'commission' => [
                'id'              => $com->id,
                'numero_com'      => $codeCom,
                'apporteur_nom'   => $com->apporteur?->user?->nom_prenoms ?? '-',
                'code_apporteur'  => $codeApp,
                'apporteur_id'    => $com->apporteur_id,
                'numero_commande' => $com->commande?->numero ?? '-',
                'commission_calc' => (float) $com->montant,
                'commission_payee'=> $com->montantPayeCommission(),
                'reste_a_payer'   => $com->resteAPayerCommission(),
                'statut'          => $com->statutCommissionCalcule(),
            ],
            'historique' => $historique,
        ]);
    }

    public function storePaiement(Request $request)
    {
        // Multi-commissions : plusieurs commissions du MÊME apporteur peuvent être
        // réglées en une opération (montant = somme des restes, chacune soldée).
        // Une seule commission cochée = comportement historique (tranche partielle OK).
        $validated = $request->validate([
            'commission_ids'   => 'required|array|min:1',
            'commission_ids.*' => 'integer|exists:commission_apporteur,id',
            'mode_paiement_id' => 'required|integer|exists:mode_paiement,id',
            'montant'          => 'required|numeric|min:1',
            'date_paiement'    => 'nullable|date',
            'reference'        => 'nullable|string|max:80',
            'notes'            => 'nullable|string|max:500',
        ]);

        $commissions = CommissionApporteur::whereIn('id', $validated['commission_ids'])->get();

        // Toutes les commissions doivent appartenir au même apporteur (un règlement
        // est versé à UN apporteur ; le bordereau est nominatif).
        if ($commissions->pluck('apporteur_id')->unique()->count() > 1) {
            return back()->withInput()->with('error',
                "Les commissions sélectionnées appartiennent à des apporteurs différents : un paiement ne concerne qu'un seul apporteur.");
        }

        // Règle métier : chaque commission n'est due QUE si le client a payé
        foreach ($commissions as $com) {
            if ($com->statutCommissionCalcule() === 'En attente paiement client') {
                return back()->withInput()->with('error',
                    "La commission #{$com->id} n'est pas encore due : le client n'a pas payé sa commande.");
            }
        }

        $sommeRestes = round($commissions->sum(fn($c) => $c->resteAPayerCommission()), 2);

        if ($commissions->count() === 1) {
            // Tranche partielle autorisée sur une commission unique.
            if ($validated['montant'] > $sommeRestes + 0.01) {
                return back()->withInput()
                    ->with('error', "Le montant ({$validated['montant']}) dépasse le reste à payer ({$sommeRestes}).");
            }
        } else {
            // Plusieurs commissions : le montant doit régler l'intégralité des restes
            // (l'affectation d'une tranche partielle à plusieurs commissions serait ambiguë).
            if (abs($validated['montant'] - $sommeRestes) > 0.01) {
                return back()->withInput()->with('error',
                    "Pour un paiement multi-commissions, le montant doit être égal à la somme des restes ({$sommeRestes}).");
            }
        }

        $user = Auth::user();
        DB::beginTransaction();
        try {
            foreach ($commissions as $com) {
                $montantLigne = $commissions->count() === 1
                    ? $validated['montant']
                    : round($com->resteAPayerCommission(), 2);

                PaiementApporteur::create(array_merge([
                    'date_paiement'    => $validated['date_paiement'] ?? now()->toDateString(),
                    'commission_id'    => $com->id,
                    'apporteur_id'     => $com->apporteur_id,
                    'montant'          => $montantLigne,
                    'mode_paiement_id' => $validated['mode_paiement_id'],
                    'reference'        => $validated['reference'] ?? null,
                    'notes'            => $validated['notes'] ?? null,
                    'user_id'          => $user?->id,
                    // statut=2 = en attente de la 2e validation
                    'statut'           => 2,
                ], $this->initierValidation()));
            }

            DB::commit();
        } catch (\Throwable $ex) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Erreur enregistrement : ' . $ex->getMessage());
        }

        $nb = $commissions->count();
        return redirect()->route('show.apporteurs.paiements')
            ->with('success', $nb > 1
                ? "$nb paiements de commission créés (un par commission). En attente de validation par un autre administrateur."
                : "Paiement commission créé. En attente de validation par un autre administrateur.");
    }

    /**
     * 2e validation d'un paiement apporteur.
     */
    public function validerPaiementApporteur($id)
    {
        $p = PaiementApporteur::find($id);

        $result = $this->validerPaiement($p);
        if (!$result['ok']) {
            return back()->with('error', $result['message']);
        }

        $p->update(['statut' => 1]);

        return back()->with('success', "Paiement commission validé.");
    }

    public function recu($id)
    {
        $p = PaiementApporteur::with(['apporteur', 'apporteur.user', 'commission', 'commission.commande', 'modePaiement', 'user'])
            ->findOrFail($id);
        $data = $this->buildRecuData($p);
        return view('admin.shared.recu-paiement', $data);
    }

    public function recuPdf($id)
    {
        $p = PaiementApporteur::with(['apporteur', 'apporteur.user', 'commission', 'commission.commande', 'modePaiement', 'user'])
            ->findOrFail($id);
        $data = $this->buildRecuData($p);
        $data['pdfMode'] = true;

        $pdf = \PDF::loadView('admin.shared.recu-paiement-pdf', $data)
            ->setPaper('A5', 'portrait')
            ->setOptions([
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
                'defaultFont' => 'DejaVu Sans',
            ]);
        return $pdf->download('recu-apporteur-' . str_pad($p->id, 4, '0', STR_PAD_LEFT) . '.pdf');
    }

    private function buildRecuData(PaiementApporteur $p): array
    {
        $com = $p->commission;
        $apporteur = $p->apporteur;
        $codeCom = $com?->numero ?? ('COM-' . str_pad($com?->id ?? 0, 3, '0', STR_PAD_LEFT));
        $codeApp = preg_match('/^APP-/', (string) $apporteur?->code)
            ? $apporteur->code
            : 'APP-' . str_pad($apporteur?->id ?? 0, 3, '0', STR_PAD_LEFT);

        $year = optional($p->created_at)->format('Y') ?? date('Y');
        $numeroRecu = sprintf('PA-%s-%04d', $year, $p->id);

        $allPaiements = PaiementApporteur::where('commission_id', $com?->id)
            ->where('statut', 1)->orderBy('date_paiement')->get();
        $trancheNum = $allPaiements->search(fn($x) => $x->id === $p->id);
        $trancheNum = $trancheNum === false ? 1 : ($trancheNum + 1);
        $trancheTotal = $allPaiements->count();

        $totalDu  = $com ? (float) $com->montant : (float) $p->montant;
        $totalPaye = $com ? $com->montantPayeCommission() : (float) $p->montant;
        $reste     = max(0, $totalDu - $totalPaye);

        return [
            'titre'              => 'BORDEREAU DE PAIEMENT',
            'sousTitre'          => 'Paiement commission apporteur',
            'numeroRecu'         => $numeroRecu,
            'datePaiement'       => $p->date_paiement,
            'beneficiaireRole'   => 'Apporteur',
            'beneficiaireNom'    => $apporteur?->user?->nom_prenoms ?? '-',
            'beneficiaireContact'=> $apporteur?->user?->contact,
            'modePaiement'       => $p->modePaiement?->libelle ?? '-',
            'reference'          => $p->reference,
            'caissier'           => $p->user?->nom_prenoms ?? '-',
            'libelle'            => $p->notes,
            'montant'            => (float) $p->montant,
            'montantLabel'       => 'Montant payé',
            'contexteInfos'      => [
                'Code Apporteur'     => $codeApp,
                'N° Commission'      => $codeCom,
                'N° Commande source' => $com?->commande?->numero ?? '-',
                'Coordonnées paiement' => $apporteur?->coordonnees_paiement,
            ],
            'resumeFinancier'    => [
                'totalLabel' => 'Commission calculée',
                'total'      => $totalDu,
                'paye'       => $totalPaye,
                'reste'      => $reste,
            ],
            'trancheNum'         => $trancheNum,
            'trancheTotal'       => $trancheTotal,
            'retourUrl'          => route('show.apporteurs.paiements'),
            'pdfUrl'             => route('show.apporteurs.recuPdf', $p->id),
            'couleurPrincipale'  => '#1c57a3',
            'signatureGauche'    => 'Signature Trésorier',
            'signatureDroite'    => 'Signature Apporteur',
            'config'             => Configuration::first(),
            'pdfMode'            => false,
        ];
    }

    /**
     * Synthèse des dettes apporteurs : indicateurs globaux + dettes par apporteur.
     */
    public function synthese(Request $request)
    {
        $commissions = CommissionApporteur::with(['apporteur', 'commande'])->get();

        $totalCalcule = 0.0;
        $totalPaye    = 0.0;

        $resteDue       = 0.0;
        $restePartielle = 0.0;
        $resteAttente   = 0.0;
        $countAnnulees  = 0;

        foreach ($commissions as $com) {
            $calc  = (float) $com->montant;
            $paye  = $com->montantPayeCommission();
            $reste = max(0, $calc - $paye);
            $totalCalcule += $calc;
            $totalPaye    += $paye;

            switch ($com->statutCommissionCalcule()) {
                case 'Due':
                    $resteDue += $reste;
                    break;
                case 'Partiellement due':
                    $restePartielle += $reste;
                    break;
                case 'En attente paiement client':
                    $resteAttente += $reste;
                    break;
                case 'Annulée':
                    $countAnnulees++;
                    break;
            }
        }

        $dettesTotales = max(0, $totalCalcule - $totalPaye);

        // Dettes par apporteur
        $dettesParApporteur = Apporteur::with('user')->get()->map(function (Apporteur $app) use ($commissions) {
            $coms      = $commissions->where('apporteur_id', $app->id);
            $totalCalc = $coms->sum(fn($c) => (float) $c->montant);
            $totalPay  = $coms->sum(fn($c) => $c->montantPayeCommission());
            $reste     = max(0, $totalCalc - $totalPay);
            $codeApp   = preg_match('/^APP-/', (string) $app->code)
                ? $app->code
                : 'APP-' . str_pad($app->id, 3, '0', STR_PAD_LEFT);
            return (object) [
                'apporteur'       => $app,
                'code'            => $codeApp,
                'nom'             => $app->user?->nom_prenoms ?? '-',
                'nb_commissions'  => $coms->count(),
                'total_calcule'   => (float) $totalCalc,
                'total_paye'      => (float) $totalPay,
                'reste_du'        => (float) $reste,
            ];
        })->sortByDesc('reste_du')->values();

        $config = Configuration::first();

        return view('admin.apporteur.synthese', [
            'nombreCommissions'   => $commissions->count(),
            'totalCalcule'        => $totalCalcule,
            'totalPaye'           => $totalPaye,
            'dettesTotales'       => $dettesTotales,
            'resteDue'            => $resteDue,
            'restePartielle'      => $restePartielle,
            'resteAttente'        => $resteAttente,
            'countAnnulees'       => $countAnnulees,
            'dettesParApporteur'  => $dettesParApporteur,
            'config'              => $config,
        ]);
    }
}
