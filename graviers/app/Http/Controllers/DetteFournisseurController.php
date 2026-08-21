<?php

namespace App\Http\Controllers;

use App\Models\DemandePaiement;
use App\Models\Configuration;
use Help;
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

            // La quantité affichée est celle qu'on FACTURE : la quantité servie
            // dès qu'elle est connue. Sans cela, la colonne Quantité multipliée
            // par le prix unitaire ne retombait plus sur le Montant HT de la
            // ligne d'à côté sur tout bon servi partiellement.
            $qte       = $e->quantiteAPayer();
            $puAchat   = (float) $e->prix_fournisseur;
            // Le TTC vient du MODÈLE (arrondi au franc), pas d'un recalcul local :
            // sinon la colonne « Total achats TTC » additionnait des centimes non
            // arrondis pendant que « Reste à payer », lui, venait du modèle arrondi.
            // D'où l'écart d'un franc constaté entre les deux totaux (413 / 414).
            // La TVA est déduite du TTC retenu, pour que HT + TVA = TTC à l'affichage.
            $montantHt = $e->montantHt();
            $ttc       = $e->montantDu();
            $tva       = $ttc - $montantHt;

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
                'client_final'      => $clientFinal?->display_name ?? '-',
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
        $paiements = PaiementFournisseur::with(['fournisseur', 'enlevement', 'modePaiement', 'initiateur', 'validateur'])
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
                // Traçabilité : qui a saisi l'enregistrement, qui l'a contrôlé.
                'initie_par'      => $p->initie_par,
                'valide_par'      => $p->valide_par,
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
        // L'agent qui enregistre ce paiement EST en agence : « Paiement en agence »
        // n'est pas un instrument de paiement. On propose Espèces, Chèque,
        // Virement, mobile money… comme sur les écrans d'encaissement.
        $modesPaiement = ModePaiement::listePourAgent();
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
                    // Identifiant nécessaire pour regrouper les bons par fournisseur
                    // dans le formulaire (sélection du fournisseur puis de ses bons).
                    'fournisseur_id'  => $e->fournisseur_id,
                    'fournisseur_nom' => $e->fournisseur?->nom_prenoms ?? '-',
                    'code_fournisseur'=> $codeFrn,
                    'produit'         => $e->produit?->nom ?? '-',
                    'montant_ttc'     => $e->montantDu(),
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
            'demandesFournisseurs'=> $this->demandesInitieesParLesFournisseurs(),
        ]);
    }

    /**
     * Les demandes de paiement que les FOURNISSEURS ont eux-mêmes initiées.
     *
     * Elles vivent dans `demande_paiement`, pas dans `paiement_fournisseur` :
     * un règlement porte sur un bon précis, une demande porte sur un montant.
     * Elles n'apparaissaient donc nulle part sur cet écran, alors que c'est
     * ici que l'administrateur suit ce qu'il doit aux fournisseurs — il ne
     * voyait que ce que l'entreprise avait initié de son côté.
     *
     * Le montant est déjà retenu sur le solde du fournisseur dès l'envoi de la
     * demande : elle est réservée, pas encore versée.
     *
     * La double validation se fait ici comme sur son écran dédié : les deux
     * pointent la même route, et c'est le contrôleur qui garde la règle — une
     * demande finalisée est refusée, et le 1er validateur ne peut pas être le
     * 2e. Deux portes d'entrée ne peuvent donc pas valider deux fois.
     */
    private function demandesInitieesParLesFournisseurs()
    {
        $utilisateur = Auth::id();

        // La validation est réservée aux administrateurs, comme sur l'écran
        // dédié : le gestionnaire consulte, il ne décide pas.
        $estAdmin = in_array((int) (Auth::user()?->type_user_id ?? 0), [Help::$USER_SA, Help::$USER_ADMIN], true);

        return DemandePaiement::query()
            ->join('users', 'users.id', '=', 'demande_paiement.user_id')
            ->where('users.type_user_id', Help::$USER_FOURNISSEUR)
            ->whereNull('demande_paiement.deleted_at')
            ->orderByDesc('demande_paiement.created_at')
            ->select('demande_paiement.*')
            ->with(['modePaiement', 'user'])
            ->limit(200)
            ->get()
            ->map(function (DemandePaiement $d) use ($utilisateur, $estAdmin) {
                $fournisseur = Fournisseur::where('user_id', $d->user_id)->first();

                // 0 ou NULL = en attente, 1 = acceptée, 2 = refusée.
                $etat = match ((int) ($d->paye ?? 0)) {
                    1       => ['libelle' => 'Acceptée', 'couleur' => 'success'],
                    2       => ['libelle' => 'Refusée',  'couleur' => 'danger'],
                    default => ['libelle' => 'En attente', 'couleur' => 'warning'],
                };

                return (object) [
                    'id'               => $d->id,
                    'date'             => $d->created_at,
                    'fournisseur_nom'  => $fournisseur?->nom_prenoms
                        ?: ($d->user?->nom_prenoms ?? '-'),
                    'code_fournisseur' => $fournisseur?->code
                        ?? ($fournisseur ? 'FRN-' . str_pad($fournisseur->id, 3, '0', STR_PAD_LEFT) : '-'),
                    'montant'          => (float) $d->montant,
                    'mode_paiement'    => $d->modePaiement?->libelle ?? '-',
                    'numero_compte'    => $d->numero_compte,
                    // Qui a demandé : ici le fournisseur lui-même, et non un
                    // agent de l'entreprise. C'est toute la différence avec les
                    // règlements listés en dessous.
                    'initie_par'       => trim(($d->user?->nom_prenoms ?? '-')
                        . ' (' . ($d->user?->login ?? $d->user?->id ?? '-') . ')'),
                    // Les deux administrateurs qui ont validé, nom et
                    // identifiant : sans eux, la colonne « Validation »
                    // disait qu'une demande était validée sans dire par qui.
                    'valide_par_1'     => $this->nomEtLogin($d->user_valide_id),
                    'valide_par_2'     => $this->nomEtLogin($d->user_valide2_id),
                    'etat'             => $etat['libelle'],
                    'couleur_etat'     => $etat['couleur'],

                    // --- Double validation : mêmes règles que l'écran dédié ---
                    // Elles sont recopiées ici pour l'AFFICHAGE seulement ; c'est
                    // le contrôleur de validation qui les fait respecter.
                    'finalisee'        => (bool) ($d->user_valide_id && $d->user_valide2_id),
                    'attend_1re'       => is_null($d->user_valide_id),
                    'attend_2e'        => (bool) ($d->user_valide_id && !$d->user_valide2_id),
                    // Le 1er validateur ne peut pas être le 2e : on ne lui
                    // propose pas un bouton que le serveur refusera.
                    'est_initiateur'   => (int) $d->user_valide_id === (int) $utilisateur,
                    'peut_valider'     => $estAdmin,
                ];
            });
    }

    /**
     * « NOM (identifiant) » d'un compte, ou « — » s'il n'y en a pas.
     *
     * Même présentation que les colonnes « Initié par » / « Validé par » des
     * règlements, pour que les deux tableaux se lisent de la même façon.
     */
    private function nomEtLogin($userId): string
    {
        if (!$userId) {
            return '—';
        }

        $user = \App\Models\User::find($userId);

        if (!$user) {
            return '—';
        }

        return trim(($user->nom_prenoms ?: '-') . ' (' . ($user->login ?: $user->id) . ')');
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
                'montant_ttc'     => $e->montantDu(),
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
        // Multi-bons : plusieurs bons d'enlèvement du MÊME fournisseur peuvent être
        // réglés en une seule opération (montant = somme des restes, chacun soldé).
        // Un seul bon coché = comportement historique (tranche partielle autorisée).
        // Même fonctionnement que l'écran des paiements de commission apporteur.
        // L'ancien formulaire n'acceptait qu'un bon à la fois : régler dix bons d'un
        // même fournisseur demandait dix saisies.
        $validated = $request->validate([
            'enlevement_ids'   => 'required|array|min:1',
            'enlevement_ids.*' => 'integer|exists:enlevement,id',
            'mode_paiement_id' => 'required|integer|exists:mode_paiement,id',
            'montant'          => 'required|numeric|min:1',
            'date_paiement'    => 'nullable|date',
            'reference'        => 'nullable|string|max:80',
            'notes'            => 'nullable|string|max:500',
        ], [
            'enlevement_ids.required' => "Veuillez cocher au moins un bon d'enlèvement.",
        ]);
        // L'agence vient de la personne connectée, jamais d'un choix : un
        // décaissement doit sortir de la caisse où il a réellement été fait.
        $agenceId = Auth::user()?->agence_id;
        if (!$agenceId) {
            return back()->withInput()->with('error',
                "Vous n'êtes rattaché à aucune agence : un administrateur doit vous affecter à un guichet avant que vous puissiez régler une dette.");
        }


        $enlevements = Enlevement::whereIn('id', $validated['enlevement_ids'])->get();

        // Un règlement est versé à UN fournisseur : le bordereau est nominatif.
        if ($enlevements->pluck('fournisseur_id')->unique()->count() > 1) {
            return back()->withInput()->with('error',
                "Les bons sélectionnés appartiennent à des fournisseurs différents : un paiement ne concerne qu'un seul fournisseur.");
        }

        $sommeRestes = round($enlevements->sum(fn($e) => $e->resteAPayer()), 2);

        if ($sommeRestes <= 0) {
            return back()->withInput()->with('error', "Les bons sélectionnés sont déjà soldés.");
        }

        if ($enlevements->count() === 1) {
            // Tranche partielle autorisée sur un bon unique.
            if ($validated['montant'] > $sommeRestes + 0.01) {
                return back()->withInput()
                    ->with('error', "Le montant ({$validated['montant']}) dépasse le reste à payer ({$sommeRestes}).");
            }
        } else {
            // Plusieurs bons : le montant doit régler l'intégralité des restes
            // (répartir une tranche partielle entre plusieurs bons serait ambigu).
            if (abs($validated['montant'] - $sommeRestes) > 0.01) {
                return back()->withInput()->with('error',
                    "Pour un paiement multi-bons, le montant doit être égal à la somme des restes ({$sommeRestes}).");
            }
        }

        $user = Auth::user();
        DB::beginTransaction();
        try {
            foreach ($enlevements as $e) {
                $montantLigne = $enlevements->count() === 1
                    ? $validated['montant']
                    : round($e->resteAPayer(), 2);

                PaiementFournisseur::create(array_merge([
                    'date_paiement'    => $validated['date_paiement'] ?? now()->toDateString(),
                    'enlevement_id'    => $e->id,
                    'fournisseur_id'   => $e->fournisseur_id,
                    'montant'          => $montantLigne,
                    'mode_paiement_id' => $validated['mode_paiement_id'],
                    'reference'        => $validated['reference'] ?? null,
                    'notes'            => $validated['notes'] ?? null,
                    'user_id'          => $user?->id,
                'agence_id'        => $agenceId,
                    // statut=2 = en attente de la 2e validation
                    'statut'           => 2,
                ], $this->initierValidation()));
            }

            // Le statut "Payée" de l'enlèvement sera mis à jour après la 2e validation

            DB::commit();
        } catch (\Throwable $ex) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Erreur enregistrement : ' . $ex->getMessage());
        }

        $nb = $enlevements->count();
        return redirect()->route('show.fournisseurs.paiements')
            ->with('success', $nb > 1
                ? "$nb paiements fournisseur créés. En attente de validation par un autre administrateur."
                : "Paiement fournisseur créé. En attente de validation par un autre administrateur.");
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

        // Le solde du fournisseur représente ce que l'entreprise lui doit, TVA
        // comprise depuis la décision de gestion du 05/08/2026 : il est crédité en
        // TTC à la validation du bon (SellerController). Les deux suivis portent
        // donc désormais sur la MÊME base, et le règlement peut le débiter sans
        // retirer la TVA en trop.
        $fournisseur = Fournisseur::find($p->fournisseur_id);
        if ($fournisseur) {
            $fournisseur->update([
                'solde' => max(0, (float) $fournisseur->solde - (float) $p->montant),
            ]);
        }

        return back()->with('success', "Paiement fournisseur validé.");
    }

    public function recu($id)
    {
        $p = PaiementFournisseur::with(['fournisseur', 'enlevement', 'enlevement.produit', 'modePaiement', 'user', 'agence'])
            ->findOrFail($id);
        $data = $this->buildRecuData($p);
        return view('admin.shared.recu-paiement', $data);
    }

    public function recuPdf($id)
    {
        $p = PaiementFournisseur::with(['fournisseur', 'enlevement', 'enlevement.produit', 'modePaiement', 'user', 'agence'])
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

        $totalDu  = $e ? $e->montantDu() : (float) $p->montant;
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
            // Le gabarit du reçu prévoyait déjà cette ligne ; la valeur ne lui
            // était jamais fournie. Le bénéficiaire repart maintenant avec un
            // reçu qui indique de quelle caisse le règlement est sorti.
            'agenceLabel'        => $p->agence?->nom,
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
            $ttc   = $e->montantDu();
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
            $totalAch  = $enlevs->sum(fn($e) => $e->montantDu());
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
