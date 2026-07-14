<?php

namespace App\Http\Controllers;

use App\Models\Configuration;
use App\Models\Livraison;
use App\Models\Livreur;
use App\Models\ModePaiement;
use App\Models\PaiementLivreur;
use App\Traits\DoubleValidationPaiement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DetteLivreurController extends Controller
{
    use DoubleValidationPaiement;

    /**
     * Liste des livraisons avec calcul du total dû livreur, du montant payé,
     * du reste à payer et du statut paiement livreur.
     */
    public function livraisons(Request $request)
    {
        $livraisons = Livraison::with([
                'livreur', 'livreur.user',
                'detailCommande', 'detailCommande.commande', 'detailCommande.commande.client',
                'detailCommande.commande.client.user',
                'detailCommande.produit',
            ])
            ->orderByDesc('date_livraison')
            ->get();

        $lignes = $livraisons->map(function (Livraison $l) {
            $livreur     = $l->livreur;
            $detailCmd   = $l->detailCommande;
            $cmd         = $detailCmd?->commande;
            $clientFinal = $cmd?->client;
            $produit     = $detailCmd?->produit;

            $codeLvr = $livreur?->code ?? ($livreur ? 'LVR-' . str_pad($livreur->id, 3, '0', STR_PAD_LEFT) : '-');
            $codeLiv = $l->numero ?? ('LIV-' . str_pad($l->id, 4, '0', STR_PAD_LEFT));

            $typeCmd = $clientFinal && (int) $clientFinal->client_a_terme === 1 ? 'Terme' : 'Comptant';

            $forfait     = (float) ($l->forfait_base ?? 0);
            $fraisKm     = (float) ($l->frais_km ?? 0);
            $totalDu     = $l->totalDuLivreur();
            $montantPaye = $l->montantPayeLivreur();
            $reste       = $l->resteAPayerLivreur();

            return (object) [
                'livraison'        => $l,
                'numero_liv'       => $codeLiv,
                'date_livraison'   => $l->date_livraison,
                'code_livreur'     => $codeLvr,
                'nom_livreur'      => $livreur?->user?->nom_prenoms ?? '-',
                'numero_commande'  => $cmd?->numero ?? '-',
                'client_final'     => $clientFinal ? trim($clientFinal->nom . ' ' . $clientFinal->prenom) : '-',
                'type_commande'    => $typeCmd,
                'adresse'          => optional(\App\Models\AdresseLivraison::find($l->adresse_livraison_id))->affichage ?? '-',
                'distance_km'      => $l->distance_km,
                'quantite_livree'  => $l->qte ? (rtrim(rtrim(number_format($l->qte, 2, ',', ' '), '0'), ',') . ' ' . ($produit?->unite ?? '')) : '-',
                'forfait_base'     => $forfait,
                'frais_km'         => $fraisKm,
                'total_du'         => $totalDu,
                'montant_paye'     => $montantPaye,
                'reste_a_payer'    => $reste,
                'statut'           => $l->statutPaiementLivreurCalcule(),
                'date_paiement'    => $l->date_paiement_livreur,
                'observations'     => $l->note_livreur,
            ];
        });

        return view('admin.livreur.livraisons', [
            'lignes' => $lignes,
        ]);
    }

    /**
     * Journal des paiements livreurs.
     */
    public function paiements(Request $request)
    {
        // LECTURE SEULE : journal des paiements livreur = demandes de paiement faites
        // depuis l'app mobile (Système A, source de vérité), validées en double par les
        // admins. Le Système B (paiement_livreur) est neutralisé (anti double-paiement).
        $paiements = \App\Models\DemandePaiement::with(['user', 'modePaiement', 'userValide'])
            ->join('users', 'users.id', '=', 'demande_paiement.user_id')
            ->where('users.type_user_id', 8) // livreurs
            ->whereNull('demande_paiement.deleted_at')
            ->orderByDesc('demande_paiement.created_at')
            ->select('demande_paiement.*')
            ->get();

        $totalPaye = (float) $paiements->where('paye', 1)->sum('montant');

        return view('admin.livreur.paiements', [
            'paiements' => $paiements,
            'totalPaye' => $totalPaye,
        ]);
    }

    public function livraisonHistorique(Request $request, $id)
    {
        $l = Livraison::with(['livreur', 'livreur.user'])->findOrFail($id);
        $paiements = PaiementLivreur::with('modePaiement')
            ->where('livraison_id', $l->id)->where('statut', 1)
            ->orderBy('date_paiement')->get();

        $historique = $paiements->map(function (PaiementLivreur $p, $i) use ($paiements) {
            return [
                'tranche'      => ($i + 1) . '/' . $paiements->count(),
                'date'         => optional($p->date_paiement)->format('d/m/Y'),
                'montant'      => (float) $p->montant,
                'mode'         => $p->modePaiement?->libelle ?? '-',
                'reference'    => $p->reference,
                'recu_url'     => route('show.livreurs.recu', $p->id),
                'recu_pdf_url' => route('show.livreurs.recuPdf', $p->id),
            ];
        });

        $codeLiv = $l->numero ?? ('LIV-' . str_pad($l->id, 4, '0', STR_PAD_LEFT));
        $codeLvr = $l->livreur?->code ?? ('LVR-' . str_pad($l->livreur?->id ?? 0, 3, '0', STR_PAD_LEFT));

        return response()->json([
            'livraison' => [
                'id'             => $l->id,
                'numero_liv'     => $codeLiv,
                'date'           => optional($l->date_livraison)->format('d/m/Y'),
                'livreur_nom'    => $l->livreur?->user?->nom_prenoms ?? '-',
                'code_livreur'   => $codeLvr,
                'livreur_id'     => $l->livreur_id,
                'total_du'       => $l->totalDuLivreur(),
                'montant_paye'   => $l->montantPayeLivreur(),
                'reste_a_payer'  => $l->resteAPayerLivreur(),
            ],
            'historique' => $historique,
        ]);
    }

    public function storePaiement(Request $request)
    {
        // SOURCE DE VÉRITÉ = circuit app mobile (solde + demande de paiement validée
        // par un administrateur). Pour éviter tout DOUBLE PAIEMENT, l'enregistrement
        // manuel d'un paiement depuis l'écran "dette livreur" est désactivé.
        // Les paiements se font désormais uniquement via « Demandes de paiement ».
        return redirect()->route('show.livreurs.paiements')->with(
            'error',
            "Le paiement des livreurs est géré via les demandes de paiement de l'application mobile "
            . "(menu « Demandes de paiement », double validation administrateur). L'enregistrement manuel "
            . "ici est désactivé pour empêcher les doubles paiements."
        );

        // --- Code historique désactivé (conservé pour référence) ---
        $validated = $request->validate([
            'livraison_id'     => 'required|integer|exists:livraison,id',
            'mode_paiement_id' => 'required|integer|exists:mode_paiement,id',
            'montant'          => 'required|numeric|min:1',
            'date_paiement'    => 'nullable|date',
            'reference'        => 'nullable|string|max:80',
            'notes'            => 'nullable|string|max:500',
        ]);

        $l = Livraison::findOrFail($validated['livraison_id']);
        $reste = $l->resteAPayerLivreur();
        if ($validated['montant'] > $reste + 0.01) {
            return back()->withInput()
                ->with('error', "Le montant ({$validated['montant']}) dépasse le reste à payer ({$reste}).");
        }

        $user = Auth::user();
        DB::beginTransaction();
        try {
            $p = PaiementLivreur::create(array_merge([
                'date_paiement'    => $validated['date_paiement'] ?? now()->toDateString(),
                'livraison_id'     => $l->id,
                'livreur_id'       => $l->livreur_id,
                'montant'          => $validated['montant'],
                'mode_paiement_id' => $validated['mode_paiement_id'],
                'reference'        => $validated['reference'] ?? null,
                'notes'            => $validated['notes'] ?? null,
                'user_id'          => $user?->id,
                // statut=2 = en attente de la 2e validation
                'statut'           => 2,
            ], $this->initierValidation()));

            // Le statut "Payée" de la livraison sera mis à jour après la 2e validation

            DB::commit();
        } catch (\Throwable $ex) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Erreur enregistrement : ' . $ex->getMessage());
        }

        return redirect()->route('show.livreurs.paiements')
            ->with('success', "Paiement livreur créé. En attente de validation par un autre administrateur.");
    }

    /**
     * 2e validation d'un paiement livreur.
     */
    public function validerPaiementLivreur($id)
    {
        // Désactivé : voir storePaiement(). Les paiements livreur passent par le
        // circuit "demandes de paiement" de l'app mobile (source de vérité unique).
        return back()->with(
            'error',
            "Validation désactivée : les paiements des livreurs sont gérés via les demandes "
            . "de paiement de l'application mobile."
        );

        $p = PaiementLivreur::find($id);

        $result = $this->validerPaiement($p);
        if (!$result['ok']) {
            return back()->with('error', $result['message']);
        }

        $p->update(['statut' => 1]);

        $l = Livraison::find($p->livraison_id);
        if ($l && $l->resteAPayerLivreur() <= 0.01) {
            $l->statut_paiement_livreur = 'Payée';
            $l->date_paiement_livreur = $p->date_paiement;
            $l->save();
        }

        return back()->with('success', "Paiement livreur validé.");
    }

    public function recu($id)
    {
        $p = PaiementLivreur::with(['livreur', 'livreur.user', 'livraison', 'modePaiement', 'user'])
            ->findOrFail($id);
        $data = $this->buildRecuData($p);
        return view('admin.shared.recu-paiement', $data);
    }

    public function recuPdf($id)
    {
        $p = PaiementLivreur::with(['livreur', 'livreur.user', 'livraison', 'modePaiement', 'user'])
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
        return $pdf->download('recu-livreur-' . str_pad($p->id, 4, '0', STR_PAD_LEFT) . '.pdf');
    }

    private function buildRecuData(PaiementLivreur $p): array
    {
        $l = $p->livraison;
        $livreur = $p->livreur;
        $codeLiv = $l?->numero ?? ('LIV-' . str_pad($l?->id ?? 0, 4, '0', STR_PAD_LEFT));
        $codeLvr = $livreur?->code ?? ('LVR-' . str_pad($livreur?->id ?? 0, 3, '0', STR_PAD_LEFT));

        $year = optional($p->created_at)->format('Y') ?? date('Y');
        $numeroRecu = sprintf('PL-%s-%04d', $year, $p->id);

        $allPaiements = PaiementLivreur::where('livraison_id', $l?->id)
            ->where('statut', 1)->orderBy('date_paiement')->get();
        $trancheNum = $allPaiements->search(fn($x) => $x->id === $p->id);
        $trancheNum = $trancheNum === false ? 1 : ($trancheNum + 1);
        $trancheTotal = $allPaiements->count();

        $totalDu  = $l ? $l->totalDuLivreur() : (float) $p->montant;
        $totalPaye = $l ? $l->montantPayeLivreur() : (float) $p->montant;
        $reste     = max(0, $totalDu - $totalPaye);

        return [
            'titre'              => 'BORDEREAU DE PAIEMENT',
            'sousTitre'          => 'Paiement livreur',
            'numeroRecu'         => $numeroRecu,
            'datePaiement'       => $p->date_paiement,
            'beneficiaireRole'   => 'Livreur',
            'beneficiaireNom'    => $livreur?->user?->nom_prenoms ?? '-',
            'beneficiaireContact'=> $livreur?->user?->contact,
            'modePaiement'       => $p->modePaiement?->libelle ?? '-',
            'reference'          => $p->reference,
            'caissier'           => $p->user?->nom_prenoms ?? '-',
            'libelle'            => $p->notes,
            'montant'            => (float) $p->montant,
            'montantLabel'       => 'Montant payé',
            'contexteInfos'      => [
                'Code Livreur' => $codeLvr,
                'N° Livraison' => $codeLiv,
                'Distance'     => $l?->distance_km ? rtrim(rtrim(number_format($l->distance_km, 2, ',', ' '), '0'), ',') . ' km' : null,
            ],
            'resumeFinancier'    => [
                'totalLabel' => 'Total dû livreur',
                'total'      => $totalDu,
                'paye'       => $totalPaye,
                'reste'      => $reste,
            ],
            'trancheNum'         => $trancheNum,
            'trancheTotal'       => $trancheTotal,
            'retourUrl'          => route('show.livreurs.paiements'),
            'pdfUrl'             => route('show.livreurs.recuPdf', $p->id),
            'couleurPrincipale'  => '#1c57a3',
            'signatureGauche'    => 'Signature Trésorier',
            'signatureDroite'    => 'Signature Livreur',
            'config'             => Configuration::first(),
            'pdfMode'            => false,
        ];
    }

    /**
     * Synthèse des dettes livreurs : indicateurs globaux + dettes par livreur.
     */
    public function synthese(Request $request)
    {
        $livraisons = Livraison::with(['livreur'])->get();

        $totalFrais = 0.0;
        $totalPaye  = 0.0;

        $countLivEffectuees   = 0;
        $countValideesAPayer  = 0;
        $countContestation    = 0;
        $countPayees          = 0;
        $countAnnulees        = 0;

        foreach ($livraisons as $l) {
            $totalFrais += $l->totalDuLivreur();
            $totalPaye  += $l->montantPayeLivreur();

            switch ($l->statutPaiementLivreurCalcule()) {
                case 'Livraison effectuée': $countLivEffectuees++; break;
                case 'Validée à payer':     $countValideesAPayer++; break;
                case 'En contestation':     $countContestation++; break;
                case 'Payée':               $countPayees++; break;
                case 'Annulée':             $countAnnulees++; break;
            }
        }

        $dettesTotales = max(0, $totalFrais - $totalPaye);

        // Dettes par livreur
        $dettesParLivreur = Livreur::with('user')->get()->map(function (Livreur $lvr) use ($livraisons) {
            $livs       = $livraisons->where('livreur_id', $lvr->id);
            $totalDu    = $livs->sum(fn($l) => $l->totalDuLivreur());
            $totalPay   = $livs->sum(fn($l) => $l->montantPayeLivreur());
            $reste      = max(0, $totalDu - $totalPay);
            $codeLvr    = $lvr->code ?? 'LVR-' . str_pad($lvr->id, 3, '0', STR_PAD_LEFT);
            return (object) [
                'livreur'        => $lvr,
                'code'           => $codeLvr,
                'nom'            => $lvr->user?->nom_prenoms ?? '-',
                'nb_livraisons'  => $livs->count(),
                'total_du'       => (float) $totalDu,
                'total_paye'     => (float) $totalPay,
                'reste_du'       => (float) $reste,
            ];
        })->sortByDesc('reste_du')->values();

        $config = Configuration::first();

        return view('admin.livreur.synthese', [
            'nombreLivraisons'    => $livraisons->count(),
            'totalFrais'          => $totalFrais,
            'totalPaye'           => $totalPaye,
            'dettesTotales'       => $dettesTotales,
            'countLivEffectuees'  => $countLivEffectuees,
            'countValideesAPayer' => $countValideesAPayer,
            'countContestation'   => $countContestation,
            'countPayees'         => $countPayees,
            'countAnnulees'       => $countAnnulees,
            'dettesParLivreur'    => $dettesParLivreur,
            'config'              => $config,
        ]);
    }
}
