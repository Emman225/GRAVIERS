<?php

namespace App\Http\Controllers;

use App\Models\Configuration;
use App\Models\Enlevement;
use App\Models\Fournisseur;
use App\Models\ModePaiement;
use App\Models\PaiementFournisseur;
use App\Traits\DoubleValidationPaiement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DetteFournisseurController extends Controller
{
    use DoubleValidationPaiement;

    /**
     * Liste des enlèvements (achats fournisseurs) avec calcul du statut dette,
     * du montant payé, du reste à payer et des jours de retard.
     */
    public function enlevements(Request $request)
    {
        $enlevements = Enlevement::with(['fournisseur', 'produit', 'livraison.detailCommande.commande.client'])
            ->orderByDesc('created_at')
            ->get();

        $config  = Configuration::first();
        $tauxTva = (float) ($config?->tva ?? 18);

        $lignes = $enlevements->map(function (Enlevement $e) use ($tauxTva) {
            $fournisseur = $e->fournisseur;
            $produit     = $e->produit;
            $cmd         = $e->livraison?->detailCommande?->commande;
            $clientFinal = $cmd?->client;

            $codeFrn   = $fournisseur?->code ?? ($fournisseur ? 'FRN-' . str_pad($fournisseur->id, 3, '0', STR_PAD_LEFT) : '-');
            $codeBe    = $e->code_enleve ?? ('BE-' . str_pad($e->id, 4, '0', STR_PAD_LEFT));

            $qte       = (float) $e->qte;
            $puAchat   = (float) $e->prix_fournisseur;
            $montantHt = $e->montantHt();
            $tva       = $montantHt * ($tauxTva / 100);
            $ttc       = $montantHt + $tva;

            // Date d'échéance par défaut = date_enlevement + delai_paiement du fournisseur
            $dateEcheance = $e->date_echeance;
            if (!$dateEcheance && $e->created_at && $fournisseur?->delai_paiement) {
                $dateEcheance = \Carbon\Carbon::parse($e->created_at)->addDays((int) $fournisseur->delai_paiement);
            }

            return (object) [
                'enlevement'        => $e,
                'numero_be'         => $codeBe,
                'date_enlevement'   => $e->created_at,
                'code_fournisseur'  => $codeFrn,
                'fournisseur_nom'   => $fournisseur?->nom_prenoms ?? '-',
                'numero_commande'   => $cmd?->numero ?? '-',
                'client_final'      => $clientFinal ? trim($clientFinal->nom . ' ' . $clientFinal->prenom) : '-',
                'produit'           => $produit?->nom ?? '-',
                'quantite'          => $qte,
                'pu_achat'          => $puAchat,
                'montant_ht'        => $montantHt,
                'tva'               => $tva,
                'montant_ttc'       => $ttc,
                'date_echeance'     => $dateEcheance,
                'montant_paye'      => $e->montantPaye(),
                'reste_a_payer'     => $e->resteAPayer(),
                'jours_retard'      => $e->joursRetard(),
                'statut_dette'      => $e->statutDetteCalcule(),
                'observations'      => $e->observations,
            ];
        });

        return view('admin.fournisseur.enlevements', [
            'lignes'  => $lignes,
            'tauxTva' => $tauxTva,
        ]);
    }

    /**
     * Journal des paiements fournisseurs.
     */
    public function paiements(Request $request)
    {
        $paiements = PaiementFournisseur::with(['fournisseur', 'enlevement', 'modePaiement'])
            ->whereIn('statut', [1, 2])
            ->orderByDesc('date_paiement')
            ->get();

        $userId = Auth::id();
        $estAdmin = in_array((int) (Auth::user()?->type_user_id ?? 0), [1, 2], true);

        $lignes = $paiements->map(function (PaiementFournisseur $p) use ($userId, $estAdmin) {
            $fournisseur = $p->fournisseur;
            $codeFrn = $fournisseur?->code ?? ($fournisseur ? 'FRN-' . str_pad($fournisseur->id, 3, '0', STR_PAD_LEFT) : '-');
            $codeBe  = $p->enlevement?->code_enleve ?? ($p->enlevement ? 'BE-' . str_pad($p->enlevement->id, 4, '0', STR_PAD_LEFT) : '-');

            $enAttente   = (int) $p->statut === 2;
            $peutValider = $enAttente && $estAdmin && (int) $p->user_valide_id !== (int) $userId;

            return (object) [
                'paiement_id'      => $p->id,
                'date_paiement'    => $p->date_paiement,
                'numero_be'        => $codeBe,
                'code_fournisseur' => $codeFrn,
                'fournisseur_nom'  => $fournisseur?->nom_prenoms ?? '-',
                'montant'          => (float) $p->montant,
                'mode_paiement'    => $p->modePaiement?->libelle ?? '-',
                'reference'        => $p->reference,
                'notes'            => $p->notes,
                'en_attente'       => $enAttente,
                'peut_valider'     => $peutValider,
            ];
        });

        // Le total ne compte que les paiements validés (pas les en-attente)
        $totalPaye = $lignes->where('en_attente', false)->sum('montant');

        // Données pour modal d'enregistrement
        $modesPaiement = ModePaiement::where('statut', 1)->orderBy('libelle')->get();
        $enlevementsNonSoldes = Enlevement::with(['fournisseur', 'produit'])
            ->orderByDesc('created_at')
            ->limit(300)
            ->get()
            ->map(function (Enlevement $e) {
                $codeBe  = $e->code_enleve ?? ('BE-' . str_pad($e->id, 4, '0', STR_PAD_LEFT));
                $codeFrn = $e->fournisseur?->code ?? ('FRN-' . str_pad($e->fournisseur?->id ?? 0, 3, '0', STR_PAD_LEFT));
                return (object) [
                    'id'              => $e->id,
                    'code_be'         => $codeBe,
                    'fournisseur_nom' => $e->fournisseur?->nom_prenoms ?? '-',
                    'code_fournisseur'=> $codeFrn,
                    'produit'         => $e->produit?->nom ?? '-',
                    'montant_ttc'     => $e->montantTtc(),
                    'reste'           => $e->resteAPayer(),
                ];
            })
            ->filter(fn($x) => $x->reste > 0)
            ->values();

        return view('admin.fournisseur.paiements', [
            'lignes'              => $lignes,
            'totalPaye'           => $totalPaye,
            'modesPaiement'       => $modesPaiement,
            'enlevementsNonSoldes'=> $enlevementsNonSoldes,
        ]);
    }

    /**
     * AJAX : détails d'un enlèvement + historique de paiements.
     */
    public function enlevementHistorique(Request $request, $id)
    {
        $e = Enlevement::with(['fournisseur', 'produit'])->findOrFail($id);
        $paiements = PaiementFournisseur::with('modePaiement')
            ->where('enlevement_id', $e->id)
            ->where('statut', 1)
            ->orderBy('date_paiement')
            ->get();

        $historique = $paiements->map(function (PaiementFournisseur $p, $i) use ($paiements) {
            return [
                'tranche'      => ($i + 1) . '/' . $paiements->count(),
                'date'         => optional($p->date_paiement)->format('d/m/Y'),
                'montant'      => (float) $p->montant,
                'mode'         => $p->modePaiement?->libelle ?? '-',
                'reference'    => $p->reference,
                'recu_url'     => route('show.fournisseurs.recu', $p->id),
                'recu_pdf_url' => route('show.fournisseurs.recuPdf', $p->id),
            ];
        });

        $codeBe = $e->code_enleve ?? ('BE-' . str_pad($e->id, 4, '0', STR_PAD_LEFT));
        $codeFrn = $e->fournisseur?->code ?? ('FRN-' . str_pad($e->fournisseur?->id ?? 0, 3, '0', STR_PAD_LEFT));

        return response()->json([
            'enlevement' => [
                'id'              => $e->id,
                'numero_be'       => $codeBe,
                'date'            => optional($e->created_at)->format('d/m/Y'),
                'fournisseur_nom' => $e->fournisseur?->nom_prenoms ?? '-',
                'code_fournisseur'=> $codeFrn,
                'fournisseur_id'  => $e->fournisseur_id,
                'produit'         => $e->produit?->nom ?? '-',
                'montant_ttc'     => $e->montantTtc(),
                'montant_paye'    => $e->montantPaye(),
                'reste_a_payer'   => $e->resteAPayer(),
                'tranche_num'     => $paiements->count() + 1,
            ],
            'historique' => $historique,
        ]);
    }

    /**
     * Enregistrement d'un paiement fournisseur (multi-tranches possible).
     */
    public function storePaiement(Request $request)
    {
        $validated = $request->validate([
            'enlevement_id'    => 'required|integer|exists:enlevement,id',
            'mode_paiement_id' => 'required|integer|exists:mode_paiement,id',
            'montant'          => 'required|numeric|min:1',
            'date_paiement'    => 'nullable|date',
            'reference'        => 'nullable|string|max:80',
            'notes'            => 'nullable|string|max:500',
        ]);

        $e = Enlevement::findOrFail($validated['enlevement_id']);
        $reste = $e->resteAPayer();
        if ($validated['montant'] > $reste + 0.01) {
            return back()->withInput()
                ->with('error', "Le montant ({$validated['montant']}) dépasse le reste à payer ({$reste}).");
        }

        $user = Auth::user();
        DB::beginTransaction();
        try {
            $p = PaiementFournisseur::create(array_merge([
                'date_paiement'    => $validated['date_paiement'] ?? now()->toDateString(),
                'enlevement_id'    => $e->id,
                'fournisseur_id'   => $e->fournisseur_id,
                'montant'          => $validated['montant'],
                'mode_paiement_id' => $validated['mode_paiement_id'],
                'reference'        => $validated['reference'] ?? null,
                'notes'            => $validated['notes'] ?? null,
                'user_id'          => $user?->id,
                // statut=2 = en attente de la 2e validation
                'statut'           => 2,
            ], $this->initierValidation()));

            // Le statut "Payée" de l'enlèvement sera mis à jour après la 2e validation

            DB::commit();
        } catch (\Throwable $ex) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Erreur enregistrement : ' . $ex->getMessage());
        }

        return redirect()->route('show.fournisseurs.paiements')
            ->with('success', "Paiement fournisseur créé. En attente de validation par un autre administrateur.");
    }

    /**
     * 2e validation d'un paiement fournisseur.
     */
    public function validerPaiementFournisseur($id)
    {
        $p = PaiementFournisseur::find($id);

        $result = $this->validerPaiement($p);
        if (!$result['ok']) {
            return back()->with('error', $result['message']);
        }

        $p->update(['statut' => 1]);

        // Vérifier si l'enlèvement est soldé maintenant
        $e = Enlevement::find($p->enlevement_id);
        if ($e && $e->resteAPayer() <= 0.01) {
            $e->statut_dette = 'Payée';
            $e->save();
        }

        return back()->with('success', "Paiement fournisseur validé.");
    }

    public function recu($id)
    {
        $p = PaiementFournisseur::with(['fournisseur', 'enlevement', 'enlevement.produit', 'modePaiement', 'user'])
            ->findOrFail($id);
        $data = $this->buildRecuData($p);
        return view('admin.shared.recu-paiement', $data);
    }

    public function recuPdf($id)
    {
        $p = PaiementFournisseur::with(['fournisseur', 'enlevement', 'enlevement.produit', 'modePaiement', 'user'])
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
        $filename = 'recu-fournisseur-' . str_pad($p->id, 4, '0', STR_PAD_LEFT) . '.pdf';
        return $pdf->download($filename);
    }

    private function buildRecuData(PaiementFournisseur $p): array
    {
        $e = $p->enlevement;
        $fournisseur = $p->fournisseur;
        $codeBe  = $e?->code_enleve ?? ('BE-' . str_pad($e?->id ?? 0, 4, '0', STR_PAD_LEFT));
        $codeFrn = $fournisseur?->code ?? ('FRN-' . str_pad($fournisseur?->id ?? 0, 3, '0', STR_PAD_LEFT));

        // Numéro reçu auto-généré format PF-YYYY-XXX (à partir de l'id)
        $year = optional($p->created_at)->format('Y') ?? date('Y');
        $numeroRecu = sprintf('PF-%s-%04d', $year, $p->id);

        // Tranche
        $allPaiements = PaiementFournisseur::where('enlevement_id', $e?->id)
            ->where('statut', 1)->orderBy('date_paiement')->get();
        $trancheNum = $allPaiements->search(fn($x) => $x->id === $p->id);
        $trancheNum = $trancheNum === false ? 1 : ($trancheNum + 1);
        $trancheTotal = $allPaiements->count();

        $totalDu  = $e ? $e->montantTtc() : (float) $p->montant;
        $totalPaye = $e ? $e->montantPaye() : (float) $p->montant;
        $reste     = max(0, $totalDu - $totalPaye);

        return [
            'titre'              => 'BORDEREAU DE PAIEMENT',
            'sousTitre'          => 'Paiement fournisseur',
            'numeroRecu'         => $numeroRecu,
            'datePaiement'       => $p->date_paiement,
            'beneficiaireRole'   => 'Fournisseur',
            'beneficiaireNom'    => $fournisseur?->nom_prenoms ?? '-',
            'beneficiaireContact'=> $fournisseur?->contact1,
            'modePaiement'       => $p->modePaiement?->libelle ?? '-',
            'reference'          => $p->reference,
            'caissier'           => $p->user?->nom_prenoms ?? '-',
            'libelle'            => $p->notes,
            'montant'            => (float) $p->montant,
            'montantLabel'       => 'Montant payé',
            'contexteInfos'      => [
                'Code Fournisseur'  => $codeFrn,
                'N° Bon Enlèvement' => $codeBe,
                'Produit'           => $e?->produit?->nom ?? '-',
            ],
            'resumeFinancier'    => [
                'totalLabel' => 'Total enlèvement TTC',
                'total'      => $totalDu,
                'paye'       => $totalPaye,
                'reste'      => $reste,
            ],
            'trancheNum'         => $trancheNum,
            'trancheTotal'       => $trancheTotal,
            'retourUrl'          => route('show.fournisseurs.paiements'),
            'pdfUrl'             => route('show.fournisseurs.recuPdf', $p->id),
            'couleurPrincipale'  => '#1c57a3',
            'signatureGauche'    => 'Signature Trésorier',
            'signatureDroite'    => 'Signature Fournisseur',
            'config'             => Configuration::first(),
            'pdfMode'            => false,
        ];
    }

    /**
     * Synthèse des dettes fournisseurs : indicateurs globaux + dettes par fournisseur.
     */
    public function synthese(Request $request)
    {
        $enlevements = Enlevement::with(['fournisseur'])->get();

        $totalAchat   = 0.0;
        $totalPaye    = 0.0;

        $resteAPayer       = 0.0;
        $resteEcheueImpayee = 0.0;
        $restePartielle    = 0.0;
        $sommeRetards      = 0;
        $nbEnlevRetard     = 0;

        foreach ($enlevements as $e) {
            $ttc   = $e->montantTtc();
            $paye  = $e->montantPaye();
            $reste = max(0, $ttc - $paye);
            $totalAchat += $ttc;
            $totalPaye  += $paye;

            $statut = $e->statutDetteCalcule();
            switch ($statut) {
                case 'À payer':
                    $resteAPayer += $reste;
                    break;
                case 'Échue impayée':
                    $resteEcheueImpayee += $reste;
                    break;
                case 'Partiellement payée':
                    $restePartielle += $reste;
                    break;
            }

            $jr = $e->joursRetard();
            if ($jr > 0) {
                $sommeRetards += $jr;
                $nbEnlevRetard++;
            }
        }

        $dettesTotales = max(0, $totalAchat - $totalPaye);
        $retardMoyen   = $nbEnlevRetard > 0 ? (int) round($sommeRetards / $nbEnlevRetard) : 0;

        // Dettes par fournisseur
        $dettesParFourn = Fournisseur::all()->map(function (Fournisseur $f) use ($enlevements) {
            $enlevs    = $enlevements->where('fournisseur_id', $f->id);
            $totalAch  = $enlevs->sum(fn($e) => $e->montantTtc());
            $totalPay  = $enlevs->sum(fn($e) => $e->montantPaye());
            $reste     = max(0, $totalAch - $totalPay);
            $codeFrn   = $f->code ?? 'FRN-' . str_pad($f->id, 3, '0', STR_PAD_LEFT);
            return (object) [
                'fournisseur'   => $f,
                'code'          => $codeFrn,
                'nom'           => $f->nom_prenoms,
                'total_achete'  => (float) $totalAch,
                'total_paye'    => (float) $totalPay,
                'reste_du'      => (float) $reste,
            ];
        })->filter(fn($l) => $l->total_achete > 0)->sortByDesc('reste_du')->values();

        $config = Configuration::first();

        return view('admin.fournisseur.synthese', [
            'nombreEnlevements'    => $enlevements->count(),
            'totalAchat'           => $totalAchat,
            'totalPaye'            => $totalPaye,
            'dettesTotales'        => $dettesTotales,
            'resteAPayer'          => $resteAPayer,
            'resteEcheueImpayee'   => $resteEcheueImpayee,
            'restePartielle'       => $restePartielle,
            'retardMoyen'          => $retardMoyen,
            'dettesParFourn'       => $dettesParFourn,
            'config'               => $config,
        ]);
    }
}
