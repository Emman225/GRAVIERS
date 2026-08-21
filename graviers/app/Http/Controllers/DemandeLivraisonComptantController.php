<?php

namespace App\Http\Controllers;

use App\Models\DemandeLivraison;
use App\Models\LignePaiement;
use App\Models\ModePaiement;
use App\Models\Paiement;
use App\Traits\DoubleValidationPaiement;
use Help;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Encaissement en agence des DEMANDES DE LIVRAISON.
 *
 * Décalque de CommandeComptantController, qui ne connaît que les commandes :
 * son écran filtre en dur sur service = 'COMMANDE'. Une demande de livraison
 * réglée en agence n'était donc encaissable nulle part — aucun règlement
 * n'existait, et rien ne permettait de savoir si le client avait payé avant
 * qu'un camion ne parte.
 *
 * Mêmes règles que pour les ventes :
 *   · l'agence est celle du caissier connecté, jamais choisie dans un formulaire ;
 *   · l'encaissement naît au statut 2 et n'existe comptablement qu'après une
 *     SECONDE validation, par un administrateur différent de son auteur ;
 *   · un encaissement ne peut pas dépasser le reste à payer.
 */
class DemandeLivraisonComptantController extends Controller
{
    use DoubleValidationPaiement;

    public function encaissements(Request $request)
    {
        $paiements = Paiement::with(['client', 'client.user', 'agence', 'caissier', 'initiateur', 'validateur'])
            ->where('service', Help::$LIVRAISON)
            ->whereIn('statut', [1, 2])
            ->whereNotNull('agence_id')
            ->whereNotNull('caissier_id')
            ->orderByDesc('created_at')
            ->get();

        $userId   = Auth::id();
        $estAdmin = in_array((int) (Auth::user()?->type_user_id ?? 0), [1, 2], true);

        $lignes = $paiements->map(function (Paiement $p) use ($userId, $estAdmin) {
            $demande = $p->service_id ? DemandeLivraison::find($p->service_id) : null;

            $ligne = LignePaiement::where('paiement_id', $p->id)->first();
            $mode  = null;
            if ($ligne) {
                $modeObj = ModePaiement::find($ligne->mode_paiement_id);
                $mode    = $ligne->moyen_paiement ?: ($modeObj?->libelle);
            }

            $enAttente   = (int) $p->statut === 2;
            $peutValider = $enAttente && $estAdmin && (int) $p->user_valide_id !== (int) $userId;

            return (object) [
                'paiement_id'       => $p->id,
                // Traçabilité : qui a saisi l'enregistrement, qui l'a contrôlé.
                'initie_par'       => $p->initie_par,
                'valide_par'       => $p->valide_par,
                'date_encaissement' => $p->created_at,
                'numero_demande'    => $demande?->numero ?? '-',
                'client_nom'        => $p->client?->display_name ?? '-',
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

        $totalEncaisse = $lignes->where('en_attente', false)->sum('montant_encaisse');

        // Demandes encore dues. UNE SEULE exclusion : celles réglées EN LIGNE,
        // encaissées par la passerelle et non au guichet.
        //
        // Les CLIENTS À TERME étaient écartés, par symétrie avec la caisse des
        // ventes. Sauf qu'un client à terme dispose, pour ses COMMANDES, d'une
        // ligne de crédit matérialisée par des factures et suivie dans les
        // créances — rien de tel n'existe pour une demande de livraison : aucune
        // facture n'est jamais créée pour ce service, et les écrans de créances
        // ne connaissent que les commandes. Sa demande partait donc livrée, et
        // la somme n'apparaissait NULLE PART.
        //
        // Ils sont donc encaissés à ce même guichet, signalés comme tels. Le
        // garde-fou du traitement continue de les exonérer d'un paiement
        // préalable : le camion part, et ce guichet sert à recouvrer ensuite.
        $demandesNonSoldees = DemandeLivraison::with(['client', 'modeDePaiement'])
            ->where('statut', Help::$STATUT_ACTIF)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->filter(fn (DemandeLivraison $d) => $d->reglementEnAgence() && $d->montantEncaissable() > 0)
            ->map(function (DemandeLivraison $d) {
                return (object) [
                    'numero'        => $d->numero,
                    'client_nom'    => $d->client?->display_name ?? '-',
                    'client_aterme' => (int) ($d->client?->client_a_terme ?? 0) === 1,
                    'trajet'        => trim(($d->priseEnCharge?->affichage ?? '—') . ' → ' . ($d->destination?->affichage ?? '—')),
                    'total_a_payer' => $d->montantAPayer(),
                    'reste'         => $d->montantRestantDu(),
                    'encaissable'   => $d->montantEncaissable(),
                    'etat'          => $d->etat_commande,
                ];
            })
            ->values();

        return view('admin.comptant.encaissementsLivraison', [
            'lignes'             => $lignes,
            'totalEncaisse'      => $totalEncaisse,
            'monAgence'          => Auth::user()?->agence,
            'modesPaiement'      => ModePaiement::listePourAgent(),
            'demandesNonSoldees' => $demandesNonSoldees,
        ]);
    }

    public function storeEncaissement(Request $request)
    {
        $validated = $request->validate([
            'numero_demande'    => 'required|string|exists:demande_livraison,numero',
            'mode_paiement_id'  => 'required|integer|exists:mode_paiement,id',
            'montant'           => 'required|numeric|min:1',
            'date_encaissement' => 'nullable|date',
            'reference'         => 'nullable|string|max:80',
            'notes'             => 'nullable|string|max:500',
        ], [
            'numero_demande.required' => 'Veuillez choisir la demande de livraison à encaisser.',
            'montant.min'             => 'Le montant encaissé doit être supérieur à zéro.',
        ]);

        // L'agence vient de la personne connectée, jamais du formulaire.
        $agenceId = Auth::user()?->agence_id;
        if (!$agenceId) {
            return back()->withInput()->with('erreur_caisse',
                "Vous n'êtes rattaché à aucune agence : un administrateur doit vous affecter à un guichet avant que vous puissiez encaisser.");
        }

        $demande = DemandeLivraison::where('numero', $validated['numero_demande'])->firstOrFail();

        if (!$demande->reglementEnAgence()) {
            return back()->withInput()->with('erreur_caisse',
                "Cette demande est réglée en ligne : elle ne s'encaisse pas au guichet.");
        }

        // Pas de refus pour les clients à terme : voir le commentaire de
        // encaissements(). Les demandes de livraison n'ont pas d'écran de créance.

        // Plafond calculé sur l'ENCAISSABLE, pas sur le reste dû : un
        // encaissement déjà saisi mais pas encore validé occupe la place, sinon
        // le guichet pourrait enregistrer deux fois le montant total.
        $reste = $demande->montantEncaissable();

        if ($reste <= 0) {
            $enAttente = $demande->montantEnAttenteValidation();

            return back()->withInput()->with('erreur_caisse', $enAttente > 0
                ? "Cette demande est intégralement couverte par des encaissements en attente de validation ("
                  . number_format($enAttente, 0, ',', ' ') . " FCFA). Faites-les valider par un second administrateur."
                : "Cette demande est déjà soldée.");
        }

        if ($validated['montant'] > $reste + 0.01) {
            return back()->withInput()->with('erreur_caisse',
                "Le montant saisi ({$validated['montant']}) dépasse le reste à payer ({$reste}).");
        }

        $modePaiement = ModePaiement::find($validated['mode_paiement_id']);
        $caissier     = Auth::user();

        DB::beginTransaction();
        try {
            // Numéro de reçu partagé avec les ventes : une seule série RC-YYYY-XXX
            // pour toute la caisse, sinon deux encaissements du même jour
            // porteraient le même numéro.
            $year = date('Y');
            $lastNum = (int) Paiement::where('numero_recu', 'like', "RC-{$year}-%")
                ->selectRaw('MAX(CAST(SUBSTRING(numero_recu, 9) AS UNSIGNED)) AS n')
                ->value('n');
            $numeroRecu = sprintf('RC-%s-%03d', $year, $lastNum + 1);

            $paiement = Paiement::create(array_merge([
                'client_id'       => $demande->client_id,
                'code'            => 'PAY-' . strtoupper(substr(md5(uniqid()), 0, 8)),
                'libelle'         => $validated['notes'] ?? ('Encaissement agence - demande de livraison ' . $demande->numero),
                'montant_total'   => $validated['montant'],
                'montant_restant' => 0,
                'statut'          => 2, // en attente de la seconde validation
                'service'         => Help::$LIVRAISON,
                'service_id'      => $demande->id,
                'agence_id'       => $agenceId,
                'caissier_id'     => $caissier?->id,
                'numero_recu'     => $numeroRecu,
                'created_at'      => $validated['date_encaissement'] ?? now(),
                'updated_at'      => now(),
            ], $this->initierValidation()));

            LignePaiement::create([
                'paiement_id'      => $paiement->id,
                'mode_paiement_id' => $validated['mode_paiement_id'],
                'reference'        => $validated['reference'] ?? null,
                'moyen_paiement'   => $modePaiement?->libelle,
                'date_paiement'    => $validated['date_encaissement'] ?? now(),
                'montant'          => $validated['montant'],
                'statut'           => 2, // aligné sur le paiement parent
                'user_id'          => $caissier?->id,
                'code_paiement'    => $paiement->code,
                'service'          => Help::$LIVRAISON,
                'service_id'       => $demande->id,
                'created_at'       => $validated['date_encaissement'] ?? now(),
                'updated_at'       => now(),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->with('erreur_caisse', 'Erreur enregistrement : ' . $e->getMessage());
        }

        return redirect()
            ->route('show.comptant.livraisons.encaissements')
            ->with('success', "Encaissement {$numeroRecu} créé. En attente de validation par un autre administrateur.");
    }

    /**
     * Seconde validation : seul un administrateur différent de l'auteur peut
     * rendre l'encaissement effectif. Tant qu'il ne l'est pas, il ne compte pas
     * dans le montant payé — et la demande reste bloquée au traitement.
     */
    public function validerEncaissement($paiementId)
    {
        $paiement = Paiement::find($paiementId);

        $result = $this->validerPaiement($paiement);
        if (!$result['ok']) {
            return back()->with('erreur_caisse', $result['message']);
        }

        LignePaiement::where('paiement_id', $paiement->id)->update(['statut' => 1]);
        $paiement->update(['statut' => 1]);

        return back()->with('success', "Encaissement {$paiement->numero_recu} validé. Le reçu est maintenant disponible.");
    }
}
