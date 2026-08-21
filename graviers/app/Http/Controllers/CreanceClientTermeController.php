<?php

namespace App\Http\Controllers;

use Help;
use App\Models\Agence;
use App\Models\Client;
use App\Models\Facture;
use App\Models\Paiement;
use App\Models\LignePaiement;
use App\Models\ModePaiement;
use App\Models\Configuration;
use App\Models\RelanceClientTerme;
use App\Traits\DoubleValidationPaiement;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreanceClientTermeController extends Controller
{
    use DoubleValidationPaiement;

    /**
     * Liste les factures des clients à terme avec calcul du statut créance,
     * du montant payé, du reste à payer et des jours de retard.
     * Reproduit toutes les colonnes de la feuille Excel "Factures".
     */
    public function factures(Request $request)
    {
        // Suspendre un client n'efface pas ce qu'il doit : le filtre sur son
        // statut retirait sa créance de l'écran.
        $clientsTerme = Client::where('client_a_terme', 1)->pluck('id');

        $factures = Facture::with([
                // Le client porte le délai de paiement dont se déduit l'échéance.
                'client',
                'commande',
                'commande.client',
                'commande.client.user',
                'commande.detailCommande',
                'commande.detailCommande.produit',
                'paiements',
            ])
            ->whereIn('client_id', $clientsTerme)
            ->orderByDesc('created_at')
            ->get();

        $config       = Configuration::first();
        $seuilAlerte  = $config?->seuil_alerte_retard ?? 15;
        $tauxTva      = (float) ($config?->tva ?? 18);

        $lignes = $factures->map(function (Facture $f) use ($tauxTva) {
            $commande    = $f->commande;
            $client      = $commande?->client;

            $details = $commande ? $commande->detailCommande : collect();

            $produitPrincipal = '-';
            $quantite         = 0;
            $puHt             = 0;
            $montantHt        = 0;

            if ($details && $details->count() > 0) {
                $detailMax = $details->sortByDesc(fn($d) => (float) $d->qte * (float) $d->prix)->first();
                $produitPrincipal = $detailMax?->produit?->nom ?? '-';
                $quantite  = (float) $details->sum('qte');
                $puHt      = $details->count() === 1
                    ? (float) $details->first()->prix
                    : (float) $detailMax?->prix;
                $montantHt = (float) $details->sum(fn($d) => (float) $d->qte * (float) $d->prix);
            }

            // Frais et remise RÉELLEMENT imputés à cette facture, et non ceux de la
            // commande entière : une commande facturée en plusieurs fois ne porte pas
            // la même remise sur chaque facture.
            $fraisLivraison = (float) ($f->cout_livraison_applique ?? $commande?->cout_livraison_client ?? 0);
            $remise         = (float) ($f->remise_appliquee ?? $commande?->remise ?? 0);

            // Le dû est celui de la FACTURE, pas celui de la commande.
            //
            // Il était recalculé depuis toutes les lignes de la commande — quantité
            // COMMANDÉE × prix. Or une facture est émise sur les enlèvements
            // RÉELLEMENT servis : une livraison partielle, ou une commande facturée
            // en plusieurs fois, produisait une facture bien inférieure à ce total.
            // La créance affichée dépassait alors le montant réclamé au client, et un
            // reste subsistait quoi qu'il paie — il ne pouvait pas solder une dette
            // calculée sur autre chose que sa facture.
            // Même règle que l'état « Client à terme » et la balance âgée :
            // une seule méthode, pour qu'ils ne puissent plus se contredire.
            $totalAPayer = $f->totalAPayer();

            if ($totalAPayer > 0) {
                // On redéduit le détail depuis le total facturé pour que la ligne reste
                // cohérente : HT + TVA + livraison − remise = total à payer.
                $montantTtc = $totalAPayer - $fraisLivraison + $remise;
                $montantHt  = $tauxTva > 0 ? $montantTtc / (1 + $tauxTva / 100) : $montantTtc;
                $tva        = $montantTtc - $montantHt;
            } else {
                // Facture sans montant enregistré : on retombe sur les lignes de la
                // commande, seule source disponible.
                $tva         = $montantHt * ($tauxTva / 100);
                $montantTtc  = $montantHt + $tva;
                $totalAPayer = $montantTtc + $fraisLivraison - $remise;
            }

            $totalPaye   = $f->montantPaye();
            $reste       = max(0, $totalAPayer - $totalPaye);
            $joursRetard = $f->joursRetard();

            return (object) [
                'facture'           => $f,
                'date_facture'      => $f->created_at,
                'client'            => $client,
                'client_nom'        => $client?->display_name ?? '-',
                'code_client'       => $client?->id,
                'numero_commande'   => $commande?->numero ?? $f->service_id,
                'produit_principal' => $produitPrincipal,
                'quantite'          => $quantite,
                'pu_ht'             => $puHt,
                'montant_ht'        => $montantHt,
                'tva'               => $tva,
                'montant_ttc'       => $montantTtc,
                'frais_livraison'   => $fraisLivraison,
                'total_a_payer'     => $totalAPayer,
                'montant_paye'      => $totalPaye,
                'reste_a_payer'     => $reste,
                'date_echeance'     => $f->echeance(),
                'delai_jours'       => $client?->delai_paiement,
                'jours_retard'      => $joursRetard,
                'statut_creance'    => $f->statutCreance(),
                'observations'      => $f->observations,
            ];
        });

        // Filtre "à encaisser uniquement" (factures avec un reste à payer > 0).
        $aEncaisser = $request->boolean('a_encaisser');
        if ($aEncaisser) {
            $lignes = $lignes->filter(fn($l) => (float) $l->reste_a_payer > 0)->values();
        }

        return view('admin.clientTerme.factures', [
            'lignes'      => $lignes,
            'seuilAlerte' => $seuilAlerte,
            'tauxTva'     => $tauxTva,
            'aEncaisser'  => $aEncaisser,
        ]);
    }

    /**
     * Liste les paiements reçus pour les clients à terme.
     */
    public function paiements(Request $request)
    {
        $clientsTerme = Client::where('client_a_terme', 1)->where('statut', 1)->pluck('id');

        // Paiements encaissés (statut=1), plus ceux en attente d'une seconde
        // validation (statut=2).
        //
        // Mais « en attente » ne veut pas dire la même chose selon l'origine. La
        // passerelle crée le paiement AVANT que le client ne règle, en statut 2 :
        // s'il abandonne devant Orange Money, la ligne reste là. Elle apparaissait
        // ici avec un bouton « Valider » — un clic aurait crédité un encaissement
        // qui n'a jamais eu lieu.
        //
        // Un encaissement saisi par un agent porte toujours un numéro de reçu et
        // l'identifiant de son caissier ; un paiement initié par la passerelle n'a
        // ni l'un ni l'autre. On ne propose donc à la validation que ce qu'un
        // humain a réellement encaissé. Un paiement en ligne, lui, entre dans la
        // liste quand la passerelle le confirme — en statut 1.
        $paiements = Paiement::with(['client', 'client.user', 'initiateur', 'validateur'])
            ->whereIn('client_id', $clientsTerme)
            ->where(function ($q) {
                $q->where('statut', 1)
                    ->orWhere(function ($enAttente) {
                        $enAttente->where('statut', 2)
                            ->where(function ($saisiParUnAgent) {
                                $saisiParUnAgent->whereNotNull('caissier_id')
                                    ->orWhereNotNull('numero_recu');
                            });
                    });
            })
            ->orderByDesc('created_at')
            ->get();

        $userId = Auth::id();
        $estAdmin = in_array((int) (Auth::user()?->type_user_id ?? 0), [1, 2], true);

        $lignes = $paiements->map(function (Paiement $p) use ($userId, $estAdmin) {
            $facture = $p->facture_id ? Facture::find($p->facture_id) : null;
            $ligne   = LignePaiement::where('paiement_id', $p->id)->first();
            $mode    = null;
            if ($ligne) {
                $modeObj = ModePaiement::find($ligne->mode_paiement_id);
                $mode    = $ligne->moyen_paiement ?: ($modeObj?->libelle);
            }
            $enAttente   = (int) $p->statut === 2;
            $peutValider = $enAttente && $estAdmin && (int) $p->user_valide_id !== (int) $userId;
            return (object) [
                'paiement_id'           => $p->id,
                // Traçabilité : qui a saisi l'enregistrement, qui l'a contrôlé.
                'initie_par'           => $p->initie_par,
                'valide_par'           => $p->valide_par,
                'date_paiement'         => $p->created_at,
                'numero_facture'        => $facture?->numero ?? '-',
                'code_client'           => $p->client_id,
                'client_nom'            => $p->client?->display_name ?? '-',
                'montant_recu'          => (float) $p->montant_total,
                'mode_paiement'         => $mode ?: '-',
                'reference_transaction' => $ligne?->reference ?? $p->code,
                'notes'                 => $p->libelle,
                'en_attente'            => $enAttente,
                'peut_valider'          => $peutValider,
            ];
        });

        // Le total ne compte que les paiements validés
        $totalEncaisse = $lignes->where('en_attente', false)->sum('montant_recu');

        // Même règle que l'encaissement comptant : l'agent saisit l'instrument réel,
        // pas « en agence ».
        $modesPaiement = ModePaiement::listePourAgent();
        $facturesNonSoldees = Facture::with(['commande', 'commande.client', 'paiements'])
            ->whereIn('client_id', $clientsTerme)
            ->orderByDesc('created_at')
            ->limit(300)
            ->get()
            ->map(function (Facture $f) {
                $client = $f->commande?->client;
                $totalPaye = (float) $f->paiements->where('statut', 1)->sum('montant_total');
                $reste = max(0, (float) $f->montant - $totalPaye);
                return (object) [
                    'numero'       => $f->numero,
                    'client_nom'   => $client?->display_name ?? '-',
                    'total_a_payer'=> (float) $f->montant,
                    'reste'        => $reste,
                ];
            })
            ->filter(fn($x) => $x->reste > 0)
            ->values();

        return view('admin.clientTerme.paiements', [
            'lignes'             => $lignes,
            'totalEncaisse'      => $totalEncaisse,
            'modesPaiement'      => $modesPaiement,
            'facturesNonSoldees' => $facturesNonSoldees,
            // Agence de la personne connectée : l'encaissement lui est imputé
            // d'office. Elle n'est plus choisie dans une liste — voir
            // storePaiement() et le commentaire de la migration.
            'monAgence'          => Auth::user()?->agence,
        ]);
    }

    /**
     * Liste les relances enregistrées pour les clients à terme.
     * Inclut une section « À relancer aujourd'hui » basée sur configuration.delai_relance_standard.
     */
    public function relances(Request $request)
    {
        $config       = Configuration::first();
        $delaiRelance = (int) ($config?->delai_relance_standard ?? 7);

        $relances = RelanceClientTerme::with(['client', 'facture'])
            ->orderByDesc('date_relance')
            ->get();

        $lignes = $relances->map(function (RelanceClientTerme $r) {
            return (object) [
                'id'              => $r->id,
                'date_relance'    => $r->date_relance,
                'numero_facture'  => $r->facture?->numero ?? '-',
                'code_client'     => $r->client_id,
                'client_nom'      => $r->client?->display_name ?? '-',
                'type_relance'    => $r->type_relance,
                'niveau'          => $r->niveau,
                'reponse_client'  => $r->reponse_client,
                'action_suivante' => $r->action_suivante,
            ];
        });

        // Données pour modal de saisie : clients à terme + factures avec reste à payer
        $clientsListe = Client::where('client_a_terme', 1)->where('statut', 1)
            ->get()->map(function (Client $c) {
                return (object) [
                    'id'  => $c->id,
                    'nom' => $c->display_name,
                ];
            });
        $facturesListe = Facture::with('paiements')
            ->whereIn('client_id', Client::where('client_a_terme', 1)->pluck('id'))
            ->get()
            ->map(function (Facture $f) {
                $paye = (float) $f->paiements->where('statut', 1)->sum('montant_total');
                $reste = max(0, (float) $f->montant - $paye);
                return (object) [
                    'id'        => $f->id,
                    'numero'    => $f->numero,
                    'client_id' => $f->client_id,
                    'reste'     => $reste,
                ];
            })
            ->filter(fn($f) => $f->reste > 0)
            ->values();

        // Section « À relancer aujourd'hui » :
        //  - facture en retard de >= delai_relance_standard jours
        //  - reste à payer > 0
        //  - aucune relance enregistrée dans les delai_relance_standard derniers jours
        $aujourdHui = Carbon::today();
        $seuilDateRelance = (clone $aujourdHui)->subDays($delaiRelance);

        $clientsTermeIds = Client::where('client_a_terme', 1)->pluck('id');
        $facturesEnRetard = Facture::with(['client', 'paiements'])
            ->whereIn('client_id', $clientsTermeIds)
            ->whereNotNull('date_echeance')
            ->whereDate('date_echeance', '<=', (clone $aujourdHui)->subDays($delaiRelance))
            ->get();

        $aRelancer = $facturesEnRetard->filter(function (Facture $f) use ($seuilDateRelance) {
                if ($f->resteAPayer() <= 0) return false;
                $derniereRelance = RelanceClientTerme::where('facture_id', $f->id)
                    ->orderByDesc('date_relance')->first();
                return ! $derniereRelance
                    || Carbon::parse($derniereRelance->date_relance)->lessThan($seuilDateRelance);
            })
            ->map(function (Facture $f) {
                return (object) [
                    'facture_id'    => $f->id,
                    'numero'        => $f->numero,
                    'client_id'     => $f->client_id,
                    'client_nom'    => $f->client?->display_name ?? '-',
                    'date_echeance' => $f->date_echeance,
                    'jours_retard'  => $f->joursRetard(),
                    'reste'         => $f->resteAPayer(),
                ];
            })
            ->sortByDesc('jours_retard')
            ->values();

        return view('admin.clientTerme.relances', [
            'lignes'        => $lignes,
            'clientsListe'  => $clientsListe,
            'facturesListe' => $facturesListe,
            'aRelancer'     => $aRelancer,
            'delaiRelance'  => $delaiRelance,
        ]);
    }

    /**
     * Enregistrement d'une relance client à terme.
     */
    public function storeRelance(Request $request)
    {
        $validated = $request->validate([
            'date_relance'    => 'required|date',
            'client_id'       => 'required|integer|exists:client,id',
            'facture_id'      => 'nullable|integer|exists:facture,id',
            'type_relance'    => 'required|string|max:30',
            'niveau'          => 'required|string|max:30',
            'reponse_client'  => 'nullable|string|max:1000',
            'action_suivante' => 'nullable|string|max:1000',
        ]);

        RelanceClientTerme::create([
            'date_relance'    => $validated['date_relance'],
            'client_id'       => $validated['client_id'],
            'facture_id'      => $validated['facture_id'] ?? null,
            'type_relance'    => $validated['type_relance'],
            'niveau'          => $validated['niveau'],
            'reponse_client'  => $validated['reponse_client'] ?? null,
            'action_suivante' => $validated['action_suivante'] ?? null,
            'user_id'         => Auth::id(),
        ]);

        return redirect()->route('show.creancesTerme.relances')
            ->with('success', 'Relance enregistrée avec succès.');
    }

    /**
     * AJAX : Récupère les détails d'une facture + historique de paiements.
     */
    public function factureHistorique(Request $request, $numero)
    {
        $f = Facture::with(['commande', 'commande.client', 'paiements'])
            ->where('numero', $numero)
            ->first();
        if (!$f) {
            return response()->json(['error' => 'Facture introuvable'], 404);
        }

        $totalAPayer = (float) $f->montant;
        $totalPaye   = (float) $f->paiements->where('statut', 1)->sum('montant_total');
        $reste       = max(0, $totalAPayer - $totalPaye);

        $client = $f->commande?->client;

        $paiements = $f->paiements->where('statut', 1)->sortBy('created_at')->values();
        $historique = $paiements->map(function (Paiement $p, $i) use ($paiements) {
            $ligne = LignePaiement::where('paiement_id', $p->id)->first();
            $modeObj = $ligne ? ModePaiement::find($ligne->mode_paiement_id) : null;
            $mode = $ligne ? ($ligne->moyen_paiement ?: $modeObj?->libelle) : null;
            return [
                'tranche'      => ($i + 1) . '/' . $paiements->count(),
                'date'         => $p->created_at?->format('d/m/Y'),
                'montant'      => (float) $p->montant_total,
                'mode'         => $mode ?? '-',
                'reference'    => $ligne?->reference ?? $p->code,
                'recu_url'     => route('show.creancesTerme.recu', $p->id),
                'recu_pdf_url' => route('show.creancesTerme.recuPdf', $p->id),
            ];
        });

        return response()->json([
            'facture' => [
                'numero'        => $f->numero,
                'client_nom'    => $client?->display_name ?? '-',
                'client_id'     => $f->client_id,
                'total_a_payer' => $totalAPayer,
                'total_paye'    => $totalPaye,
                'reste_a_payer' => $reste,
            ],
            'historique' => $historique,
        ]);
    }

    /**
     * Enregistrement d'un encaissement de facture client à terme (multi-tranches).
     */
    public function storePaiement(Request $request)
    {
        $validated = $request->validate([
            'numero_facture'   => 'required|string|exists:facture,numero',
            'mode_paiement_id' => 'required|integer|exists:mode_paiement,id',
            'montant'          => 'required|numeric|min:1',
            'date_paiement'    => 'nullable|date',
            'reference'        => 'nullable|string|max:80',
            'notes'            => 'nullable|string|max:500',
        ]);

        // L'agence n'est plus CHOISIE : c'est celle de la personne connectée.
        //
        // Tant qu'elle était sélectionnée dans une liste, un caissier pouvait
        // imputer sa recette à un autre guichet que le sien, et la caisse d'une
        // agence se retrouvait créditée d'un versement qu'elle n'avait jamais
        // reçu. Refuser l'opération vaut mieux que d'inventer une agence par
        // défaut, qui fausserait la caisse en silence.
        $agenceId = Auth::user()?->agence_id;
        if (!$agenceId) {
            return back()->withInput()->with('error',
                "Vous n'êtes rattaché à aucune agence : un administrateur doit vous affecter à un guichet avant que vous puissiez encaisser.");
        }

        $f = Facture::where('numero', $validated['numero_facture'])->firstOrFail();
        $totalAPayer = (float) $f->montant;
        $totalPaye   = (float) Paiement::where('facture_id', $f->id)->where('statut', 1)->sum('montant_total');
        $reste       = max(0, $totalAPayer - $totalPaye);

        if ($validated['montant'] > $reste + 0.01) {
            return back()->withInput()
                ->with('error', "Le montant ({$validated['montant']}) dépasse le reste à payer ({$reste}).");
        }

        $modePaiement = ModePaiement::find($validated['mode_paiement_id']);
        $caissier     = Auth::user();

        DB::beginTransaction();
        try {
            // Numéro reçu RC-CT-YYYY-XXX
            $year = date('Y');
            $lastNum = (int) Paiement::where('numero_recu', 'like', "RC-CT-{$year}-%")
                ->selectRaw('MAX(CAST(SUBSTRING(numero_recu, 12) AS UNSIGNED)) AS n')
                ->value('n');
            $numeroRecu = sprintf('RC-CT-%s-%03d', $year, $lastNum + 1);

            $paiement = Paiement::create(array_merge([
                'client_id'      => $f->client_id ?? $f->commande?->client_id,
                'code'           => 'PCT-' . strtoupper(substr(md5(uniqid()), 0, 8)),
                'libelle'        => $validated['notes'] ?? ('Paiement facture ' . $f->numero),
                'montant_total'  => $validated['montant'],
                'montant_restant'=> 0,
                // statut=2 = en attente de la 2e validation
                'statut'         => 2,
                'service'        => 'COMMANDE',
                'service_id'     => $f->service_id,
                'facture_id'     => $f->id,
                'agence_id'      => $agenceId,
                'caissier_id'    => $caissier?->id,
                'numero_recu'    => $numeroRecu,
                'created_at'     => $validated['date_paiement'] ?? now(),
                'updated_at'     => now(),
            ], $this->initierValidation()));

            LignePaiement::create([
                'paiement_id'      => $paiement->id,
                'mode_paiement_id' => $validated['mode_paiement_id'],
                'reference'        => $validated['reference'] ?? null,
                'moyen_paiement'   => $modePaiement?->libelle,
                'date_paiement'    => $validated['date_paiement'] ?? now(),
                'montant'          => $validated['montant'],
                'statut'           => 2,
                'user_id'          => $caissier?->id,
                'code_paiement'    => $paiement->code,
                'service'          => 'COMMANDE',
                'service_id'       => $f->service_id,
                'created_at'       => $validated['date_paiement'] ?? now(),
                'updated_at'       => now(),
            ]);

            // Note : la mise à jour du statut "Soldée" se fera seulement après
            // la 2e validation. On NE modifie PAS la facture ici.

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Erreur enregistrement : ' . $e->getMessage());
        }

        return redirect()->route('show.creancesTerme.paiements')
            ->with('success', "Paiement {$numeroRecu} créé. En attente de validation par un autre administrateur.");
    }

    /**
     * 2e validation d'un paiement client à terme.
     */
    public function validerPaiementClient($paiementId)
    {
        $paiement = Paiement::find($paiementId);

        $result = $this->validerPaiement($paiement);
        if (!$result['ok']) {
            return back()->with('error', $result['message']);
        }

        LignePaiement::where('paiement_id', $paiement->id)->update(['statut' => 1]);
        $paiement->update(['statut' => 1]);

        // Vérifier si la facture est soldée maintenant que le paiement compte
        if ($paiement->facture_id) {
            $f = Facture::find($paiement->facture_id);
            if ($f) {
                $totalPayeFacture = Paiement::where('facture_id', $f->id)
                    ->where('statut', 1)->sum('montant_total');
                $totalAPayer = (float) $f->montant;
                if ($totalAPayer > 0 && $totalPayeFacture >= $totalAPayer - 0.01) {
                    $f->statut_creance = 'Soldée';
                    $f->save();
                }
            }
        }

        return back()->with('success', "Paiement {$paiement->numero_recu} validé. Le reçu est maintenant disponible.");
    }

    public function recu($paiementId)
    {
        $p = Paiement::with(['client', 'caissier', 'agence'])->findOrFail($paiementId);
        $data = $this->buildRecuData($p);
        return view('admin.shared.recu-paiement', $data);
    }

    public function recuPdf($paiementId)
    {
        $p = Paiement::with(['client', 'caissier', 'agence'])->findOrFail($paiementId);
        $data = $this->buildRecuData($p);
        $data['pdfMode'] = true;

        $pdf = \PDF::loadView('admin.shared.recu-paiement-pdf', $data)
            ->setPaper('A5', 'portrait')
            ->setOptions([
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => true,
                'defaultFont' => 'DejaVu Sans',
            ]);
        return $pdf->download('recu-client-terme-' . ($p->numero_recu ?? $p->id) . '.pdf');
    }

    private function buildRecuData(Paiement $p): array
    {
        $f = $p->facture_id ? Facture::find($p->facture_id) : null;
        $client = $p->client;

        $year = optional($p->created_at)->format('Y') ?? date('Y');
        $numeroRecu = $p->numero_recu ?? sprintf('RC-CT-%s-%04d', $year, $p->id);

        $allPaiements = Paiement::where('facture_id', $f?->id)
            ->where('statut', 1)->orderBy('created_at')->get();
        $trancheNum = $allPaiements->search(fn($x) => $x->id === $p->id);
        $trancheNum = $trancheNum === false ? 1 : ($trancheNum + 1);
        $trancheTotal = $allPaiements->count();

        $totalAPayer = $f ? (float) $f->montant : (float) $p->montant_total;
        $totalPaye   = $f ? (float) Paiement::where('facture_id', $f->id)->where('statut', 1)->sum('montant_total') : (float) $p->montant_total;
        $reste       = max(0, $totalAPayer - $totalPaye);

        $ligne = LignePaiement::where('paiement_id', $p->id)->first();
        $mode = null;
        if ($ligne) {
            $modeObj = ModePaiement::find($ligne->mode_paiement_id);
            $mode = $ligne->moyen_paiement ?: $modeObj?->libelle;
        }

        return [
            'titre'              => 'REÇU DE PAIEMENT',
            'sousTitre'          => 'Encaissement client à terme',
            'numeroRecu'         => $numeroRecu,
            'datePaiement'       => $p->created_at,
            'beneficiaireRole'   => 'Reçu de',
            'beneficiaireNom'    => $client?->display_name ?? '-',
            'beneficiaireContact'=> $client?->contact1,
            'modePaiement'       => $mode ?? '-',
            'reference'          => $ligne?->reference ?? $p->code,
            'caissier'           => $p->caissier?->nom_prenoms ?? '-',
            // Le gabarit du reçu prévoyait déjà d'afficher l'agence, mais la
            // valeur ne lui était jamais fournie : le bloc restait muet. Le
            // client repart maintenant avec un reçu qui indique le guichet.
            'agenceLabel'        => $p->agence?->nom,
            'libelle'            => $p->libelle,
            'montant'            => (float) $p->montant_total,
            'montantLabel'       => 'Montant encaissé',
            'contexteInfos'      => [
                'N° Facture'  => $f?->numero,
                'Date facture'=> optional($f?->created_at)->format('d/m/Y'),
            ],
            'resumeFinancier'    => [
                'totalLabel' => 'Total facture',
                'total'      => $totalAPayer,
                'paye'       => $totalPaye,
                'reste'      => $reste,
            ],
            'trancheNum'         => $trancheNum,
            'trancheTotal'       => $trancheTotal,
            'retourUrl'          => route('show.creancesTerme.paiements'),
            'pdfUrl'             => route('show.creancesTerme.recuPdf', $p->id),
            'couleurPrincipale'  => '#1c57a3',
            'signatureGauche'    => 'Signature Caissier',
            'signatureDroite'    => 'Signature Client',
            'config'             => Configuration::first(),
            'pdfMode'            => false,
        ];
    }

    /**
     * Synthèse des créances clients à terme : indicateurs globaux + top débiteurs.
     */
    public function synthese(Request $request)
    {
        $clientsTerme = Client::where('client_a_terme', 1)->where('statut', 1)->get();
        $clientIds    = $clientsTerme->pluck('id');

        $factures = Facture::whereIn('client_id', $clientIds)->with('paiements')->get();

        $totalFacture     = 0.0;
        $totalEncaisse    = 0.0;
        $resteEcheueImpayee  = 0.0;
        $resteEcheuePartielle = 0.0;
        $resteAEchoir     = 0.0;
        $sommeRetards     = 0;
        $nbFacturesRetard = 0;

        foreach ($factures as $f) {
            $paye  = (float) $f->paiements->where('statut', 1)->sum('montant_total');
            $total = (float) $f->montant;
            $reste = max(0, $total - $paye);
            $totalFacture  += $total;
            $totalEncaisse += $paye;

            $statut = $f->statutCreance();
            switch ($statut) {
                case 'Échue impayée':
                    $resteEcheueImpayee += $reste;
                    break;
                case 'Échue partielle':
                    $resteEcheuePartielle += $reste;
                    break;
                case 'À échoir':
                    $resteAEchoir += $reste;
                    break;
            }

            $jr = $f->joursRetard();
            if ($jr > 0) {
                $sommeRetards += $jr;
                $nbFacturesRetard++;
            }
        }

        $creanceTotale = max(0, $totalFacture - $totalEncaisse);
        $tauxRecouvrement = $totalFacture > 0 ? ($totalEncaisse / $totalFacture) * 100 : 0;
        $retardMoyen      = $nbFacturesRetard > 0 ? (int) round($sommeRetards / $nbFacturesRetard) : 0;

        // Top clients débiteurs
        $topDebiteurs = $clientsTerme->map(function ($client) {
            $totalFact = (float) Facture::where('client_id', $client->id)->sum('montant');
            $totalPaye = (float) Paiement::where('client_id', $client->id)
                ->where('statut', 1)->sum('montant_total');
            $solde = max(0, $totalFact - $totalPaye);
            return (object) [
                'client'      => $client,
                'code_client' => $client->id,
                'nom'         => $client->display_name,
                'reste_du'    => $solde,
            ];
        })->filter(fn ($l) => $l->reste_du > 0)
          ->sortByDesc('reste_du')
          ->values();

        $config = Configuration::first();

        return view('admin.clientTerme.synthese', [
            'nombreFactures'        => $factures->count(),
            'totalFacture'          => $totalFacture,
            'totalEncaisse'         => $totalEncaisse,
            'creanceTotale'         => $creanceTotale,
            'creanceEcheueImpayee'  => $resteEcheueImpayee,
            'creanceEcheuePartielle'=> $resteEcheuePartielle,
            'creanceAEchoir'        => $resteAEchoir,
            'tauxRecouvrement'      => $tauxRecouvrement,
            'retardMoyen'           => $retardMoyen,
            'topDebiteurs'          => $topDebiteurs,
            'config'                => $config,
        ]);
    }
}
