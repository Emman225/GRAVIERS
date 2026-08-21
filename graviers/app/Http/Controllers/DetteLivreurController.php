<?php

namespace App\Http\Controllers;

use App\Models\Configuration;
use Help;
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
                'client_final'     => $clientFinal?->display_name ?? '-',
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

        // Total réellement payé aux livreurs = demandes de paiement VALIDÉES (source de
        // vérité du circuit mobile, cf. paiements()). L'ancien total sommait
        // PaiementLivreur (Système B, neutralisé) : toujours 0 — la page affichait
        // « Total payé : 0 » alors que le livreur avait déjà encaissé.
        $totalPayeDemandes = (float) \App\Models\DemandePaiement::join('users', 'users.id', '=', 'demande_paiement.user_id')
            ->where('users.type_user_id', 8) // livreurs
            ->where('demande_paiement.paye', 1)
            ->whereNull('demande_paiement.deleted_at')
            ->sum('demande_paiement.montant');

        return view('admin.livreur.livraisons', [
            'lignes' => $lignes,
            'totalPayeDemandes' => $totalPayeDemandes,
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

        // --- De quoi valider une demande depuis CET écran ---
        // Elles n'étaient que listées : il fallait partir sur l'écran dédié
        // pour les traiter. Les deux pointent la même route, et c'est le
        // contrôleur qui garde la règle — une demande finalisée est refusée, et
        // le 1er validateur ne peut pas être le 2e. Deux portes d'entrée ne
        // peuvent donc pas valider deux fois.
        $moi = Auth::id();
        $jeSuisAdmin = in_array((int) (Auth::user()?->type_user_id ?? 0), [Help::$USER_SA, Help::$USER_ADMIN], true);

        $paiements = $paiements->map(function ($d) use ($moi, $jeSuisAdmin) {
            $etat = match ((int) ($d->paye ?? 0)) {
                1       => ['libelle' => 'Payé',   'couleur' => 'success'],
                2       => ['libelle' => 'Refusé', 'couleur' => 'danger'],
                default => ['libelle' => 'En attente', 'couleur' => 'warning'],
            };

            return (object) [
                'id'            => $d->id,
                'livreur_nom'   => $d->user?->nom_prenoms ?? '-',
                'numero'        => $d->numero ?: 'DP-' . str_pad($d->id, 4, '0', STR_PAD_LEFT),
                'montant'       => (float) $d->montant,
                'mode'          => $d->modePaiement?->libelle ?? '-',
                'numero_compte' => $d->numero_compte,
                'date'          => $d->created_at,
                'etat'          => $etat['libelle'],
                'couleur_etat'  => $etat['couleur'],
                // Les deux validateurs, nom et identifiant : la colonne disait
                // qu'une demande était validée sans dire par qui.
                'valide_par_1'  => $this->nomEtLogin($d->user_valide_id),
                'valide_par_2'  => $this->nomEtLogin($d->user_valide2_id),
                // Règles d'affichage des boutons, recopiées de l'écran dédié.
                'finalisee'      => (bool) ($d->user_valide_id && $d->user_valide2_id),
                'attend_1re'     => is_null($d->user_valide_id),
                'attend_2e'      => (bool) ($d->user_valide_id && !$d->user_valide2_id),
                'est_initiateur' => (int) $d->user_valide_id === (int) $moi,
                'peut_valider'   => $jeSuisAdmin,
            ];
        });

        // --- Le journal des REGLEMENTS, et de quoi en enregistrer un ---
        // L'ecran ne montrait que les demandes. Les reglements saisis sur une
        // course n'y figuraient nulle part, et l'enregistrement manuel avait
        // ete desactive faute de pouvoir empecher un double paiement. La
        // demande validee soldant desormais ses courses, ce n'est plus le cas.
        $userId   = Auth::id();
        $estAdmin = in_array((int) (Auth::user()?->type_user_id ?? 0), [1, 2], true);

        $reglements = PaiementLivreur::with(['livreur.user', 'livraison', 'modePaiement', 'initiateur', 'validateur'])
            ->whereIn('statut', [1, 2])
            ->orderByDesc('date_paiement')
            ->get()
            ->map(function (PaiementLivreur $p) use ($userId, $estAdmin) {
                $livreur   = $p->livreur;
                $codeLvr   = $livreur?->code ?? ($livreur ? 'LVR-' . str_pad($livreur->id, 3, '0', STR_PAD_LEFT) : '-');
                $codeLiv   = $p->livraison?->numero ?? ($p->livraison ? 'LIV-' . str_pad($p->livraison->id, 4, '0', STR_PAD_LEFT) : '-');

                $enAttente   = (int) $p->statut === 2;
                $peutValider = $enAttente && $estAdmin && (int) $p->user_valide_id !== (int) $userId;

                return (object) [
                    'paiement_id'   => $p->id,
                    'initie_par'    => $p->initie_par,
                    'valide_par'    => $p->valide_par,
                    'date_paiement' => $p->date_paiement,
                    'numero_liv'    => $codeLiv,
                    'code_livreur'  => $codeLvr,
                    'livreur_nom'   => $livreur?->user?->nom_prenoms ?? '-',
                    'montant'       => (float) $p->montant,
                    'mode_paiement' => $p->modePaiement?->libelle ?? '-',
                    'reference'     => $p->reference,
                    'notes'         => $p->notes,
                    'en_attente'    => $enAttente,
                    'peut_valider'  => $peutValider,
                    // Un reglement issu d'une demande n'a pas ete saisi ici :
                    // le dire evite de le prendre pour une double saisie.
                    'vient_demande' => !is_null($p->demande_paiement_id),
                ];
            });

        // Les courses encore dues, pour le formulaire d'enregistrement.
        $coursesNonSoldees = Livraison::with(['livreur.user'])
            ->whereNotNull('livreur_id')
            ->where('etat_livraison', Help::$LIVRAISON_LIVREE)
            ->whereNull('deleted_at')
            ->orderByDesc('date_livraison')
            ->limit(300)
            ->get()
            ->map(function (Livraison $l) {
                $codeLvr = $l->livreur?->code ?? ('LVR-' . str_pad($l->livreur_id ?? 0, 3, '0', STR_PAD_LEFT));
                return (object) [
                    'id'           => $l->id,
                    'livreur_id'   => $l->livreur_id,
                    'livreur_nom'  => $l->livreur?->user?->nom_prenoms ?? '-',
                    'code_livreur' => $codeLvr,
                    'numero_liv'   => $l->numero ?? ('LIV-' . str_pad($l->id, 4, '0', STR_PAD_LEFT)),
                    'date'         => $l->date_livraison,
                    'total_du'     => $l->totalDuLivreur(),
                    'reste'        => $l->resteAPayerLivreur(),
                ];
            })
            ->filter(fn ($c) => $c->reste > 0)
            ->values();

        return view('admin.livreur.paiements', [
            'paiements'         => $paiements,
            'totalPaye'         => $totalPaye,
            'reglements'        => $reglements,
            'totalReglements'   => $reglements->where('en_attente', false)->sum('montant'),
            // L'agent qui enregistre EST en agence : « Paiement en agence »
            // n'est pas un instrument de paiement.
            'modesPaiement'     => ModePaiement::listePourAgent(),
            'coursesNonSoldees' => $coursesNonSoldees,
        ]);
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
        // L'enregistrement manuel avait été DÉSACTIVÉ ici pour empêcher un
        // double paiement : la demande de paiement validée ne laissait aucune
        // trace dans `paiement_livreur`, si bien qu'une course payée par ce
        // chemin restait due et pouvait être réglée une seconde fois.
        //
        // La cause est traitée : une demande validée solde désormais les
        // courses du livreur (Livreur::imputerDemandeSurLesCourses), et le
        // reste à payer d'une course dit donc la vérité. La saisie manuelle
        // peut reprendre, comme pour les fournisseurs et les apporteurs.
        // Plusieurs courses du MÊME livreur peuvent être réglées en une seule
        // opération, comme pour les apporteurs et les fournisseurs.
        $validated = $request->validate([
            'livraison_ids'    => 'required|array|min:1',
            'livraison_ids.*'  => 'integer|exists:livraison,id',
            'mode_paiement_id' => 'required|integer|exists:mode_paiement,id',
            'montant'          => 'required|numeric|min:1',
            'date_paiement'    => 'nullable|date',
            'reference'        => 'nullable|string|max:80',
            'notes'            => 'nullable|string|max:500',
        ]);
        // L'agence vient de la personne connectée, jamais d'un choix : un
        // décaissement doit sortir de la caisse où il a réellement été fait.
        $agenceId = Auth::user()?->agence_id;
        if (!$agenceId) {
            return back()->withInput()->with('error',
                "Vous n'êtes rattaché à aucune agence : un administrateur doit vous affecter à un guichet avant que vous puissiez régler une dette.");
        }


        $courses = Livraison::whereIn('id', $validated['livraison_ids'])->get();

        // Toutes les courses doivent être celles d'un SEUL livreur : un
        // règlement est versé à une personne, et le bordereau est nominatif.
        if ($courses->pluck('livreur_id')->unique()->count() > 1) {
            return back()->withInput()->with('error',
                "Les courses sélectionnées appartiennent à des livreurs différents : un paiement ne concerne qu'un seul livreur.");
        }

        $sommeRestes = round($courses->sum(fn (Livraison $l) => $l->resteAPayerLivreur()), 2);

        if ($courses->count() === 1) {
            // Une seule course : le règlement par tranches reste possible.
            if ($validated['montant'] > $sommeRestes + 0.01) {
                return back()->withInput()->with('error',
                    "Le montant ({$validated['montant']}) dépasse le reste à payer ({$sommeRestes}).");
            }
        } else {
            // Plusieurs courses : le montant doit couvrir l'intégralité des
            // restes. Répartir une tranche partielle entre plusieurs courses
            // serait arbitraire.
            if (abs($validated['montant'] - $sommeRestes) > 0.01) {
                return back()->withInput()->with('error',
                    "Pour un paiement portant sur plusieurs courses, le montant doit être égal à la somme des restes ({$sommeRestes}).");
            }
        }

        $user = Auth::user();
        DB::beginTransaction();
        try {
            foreach ($courses as $course) {
                $montantLigne = $courses->count() === 1
                    ? $validated['montant']
                    : round($course->resteAPayerLivreur(), 2);

                PaiementLivreur::create(array_merge([
                    'date_paiement'    => $validated['date_paiement'] ?? now()->toDateString(),
                    'livraison_id'     => $course->id,
                    'livreur_id'       => $course->livreur_id,
                    'montant'          => $montantLigne,
                    'mode_paiement_id' => $validated['mode_paiement_id'],
                    'reference'        => $validated['reference'] ?? null,
                    'notes'            => $validated['notes'] ?? null,
                    'user_id'          => $user?->id,
                    'agence_id'        => $agenceId,
                    // statut = 2 : en attente de la 2e validation.
                    'statut'           => 2,
                ], $this->initierValidation()));
            }

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
        // La validation avait été coupée en même temps que l'enregistrement,
        // pour la même raison : la demande de paiement validée n'écrivait rien
        // dans `paiement_livreur`, si bien qu'une course payée par ce chemin
        // restait due et pouvait être réglée une seconde fois.
        //
        // La cause est traitée — une demande validée solde désormais les
        // courses du livreur — et l'enregistrement a été rouvert. Le laisser
        // sans validation créait des paiements bloqués à l'état « en attente
        // d'un autre admin », que personne ne pouvait plus valider.
        $p = PaiementLivreur::find($id);

        if (!$p) {
            return back()->with('error', 'Paiement introuvable.');
        }

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
        $p = PaiementLivreur::with(['livreur', 'livreur.user', 'livraison', 'modePaiement', 'user', 'agence'])
            ->findOrFail($id);
        $data = $this->buildRecuData($p);
        return view('admin.shared.recu-paiement', $data);
    }

    public function recuPdf($id)
    {
        $p = PaiementLivreur::with(['livreur', 'livreur.user', 'livraison', 'modePaiement', 'user', 'agence'])
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
            // Le gabarit du reçu prévoyait déjà cette ligne ; la valeur ne lui
            // était jamais fournie. Le bénéficiaire repart maintenant avec un
            // reçu qui indique de quelle caisse le règlement est sorti.
            'agenceLabel'        => $p->agence?->nom,
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
