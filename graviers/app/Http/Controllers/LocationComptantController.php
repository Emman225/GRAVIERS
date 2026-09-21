<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\LignePaiement;
use App\Models\Location;
use App\Models\ModePaiement;
use App\Models\Paiement;
use App\Traits\DoubleValidationPaiement;
use Help;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Encaissement en agence des LOCATIONS.
 *
 * Décalque de CommandeComptantController et de DemandeLivraisonComptantController.
 *
 * Le règlement d'une location se saisissait jusqu'ici depuis la fiche de la
 * location (PaiementController::paiementLocationTraitement), sans guichet, sans
 * agence, sans reçu, et surtout SANS SECONDE SIGNATURE : le paiement naissait
 * directement au statut validé. C'était le seul flux d'encaissement du
 * back-office dans ce cas — les ventes, les demandes de livraison, les créances
 * à terme et les dettes passent tous par la double validation.
 *
 * Mêmes règles que pour les ventes :
 *   · l'agence est celle du caissier connecté, jamais choisie dans un formulaire ;
 *   · l'encaissement naît au statut 2 et n'existe comptablement qu'après une
 *     SECONDE validation, par un administrateur différent de son auteur ;
 *   · un encaissement ne peut pas dépasser le reste à payer.
 */
class LocationComptantController extends Controller
{
    use DoubleValidationPaiement;
    use \App\Traits\PreuveDeReglementPartenaire;

    public function encaissements(Request $request)
    {
        $paiements = Paiement::with(['client', 'client.user', 'agence', 'caissier', 'initiateur', 'validateur'])
            ->where('service', Help::$LOCATION)
            ->whereIn('statut', [1, 2])
            ->whereNotNull('agence_id')
            ->whereNotNull('caissier_id')
            ->orderByDesc('created_at')
            ->get();

        $userId   = Auth::id();
        $estAdmin = in_array((int) (Auth::user()?->type_user_id ?? 0), [1, 2], true);

        $lignes = $paiements->map(function (Paiement $p) use ($userId, $estAdmin) {
            $location = $p->service_id ? Location::find($p->service_id) : null;

            $ligne = LignePaiement::where('paiement_id', $p->id)->first();
            $mode  = null;
            if ($ligne) {
                $modeObj = ModePaiement::find($ligne->mode_paiement_id);
                $mode    = $ligne->moyen_paiement ?: ($modeObj?->libelle);
            }

            $enAttente   = (int) $p->statut === 2;
            $peutValider = $enAttente && $estAdmin && (int) $p->user_valide_id !== (int) $userId;

            // Pourquoi le bouton n'est pas là. L'écran se contentait d'« En
            // attente d'un autre admin », ce qui ne distingue pas les deux
            // situations : celui qui a saisi l'encaissement ne peut pas le
            // valider, et un gestionnaire ne le peut pas non plus, quel qu'il
            // soit. Sans cette précision, on cherche un bouton qui ne viendra
            // jamais.
            $raison = null;
            if ($enAttente && !$peutValider) {
                if (!$estAdmin) {
                    $raison = "Validation réservée à un administrateur — votre compte est d'un autre profil.";
                } elseif ((int) $p->user_valide_id === (int) $userId) {
                    $raison = "Vous avez saisi cet encaissement : un AUTRE administrateur doit le valider.";
                } else {
                    $raison = "En attente d'un autre administrateur.";
                }
            }

            return (object) [
                'paiement_id'       => $p->id,
                // Traçabilité : qui a saisi l'enregistrement, qui l'a contrôlé.
                'initie_par'       => $p->initie_par,
                'valide_par'       => $p->valide_par,
                'date_encaissement' => $p->created_at,
                'numero_location'   => $location?->numero ?? '-',
                'client_nom'        => $p->client?->display_name ?? '-',
                'agence_code'       => $p->agence?->code ?? '-',
                'agence_nom'        => $p->agence?->nom ?? '-',
                'montant_encaisse'  => (float) $p->montant_total,
                'mode_paiement'     => $mode ?: '-',
                'caissier'          => $p->caissier?->nom_prenoms ?? '-',
                'numero_recu'       => $p->numero_recu ?? $p->code,
                // Le champ « Notes / Observations » du formulaire (libellé du règlement).
                'observations'      => $p->libelle,
                'en_attente'        => $enAttente,
                'peut_valider'      => $peutValider,
                // Point 20 (09/09/2026, étendu le 10/09/2026) : « À payer » après la
                // 2e validation, preuve, « Effectuée ». Le TROISIÈME administrateur :
                // celui qui a finalisé, sinon celui qui a joint la preuve.
                'troisieme_par'     => \Help::compteAvecIdentifiant($p->agentEffectuee ?? $p->agentPreuve),
                'etat_reglement'    => $p->etat_reglement,
                'libelle_etat'      => $p->libelleReglement(),
                'a_preuve'          => !empty($p->preuve_paiement),
                'peut_joindre'      => $p->peutJoindrePreuve() && $p->troisiemeAdministrateur(Auth::user()),
                'peut_finaliser'    => $p->peutFinaliser() && $p->troisiemeAdministrateur(Auth::user()),
                'attend_troisieme'  => ($p->peutJoindrePreuve() || $p->peutFinaliser()) && !$p->troisiemeAdministrateur(Auth::user()),
                'raison_blocage'    => $raison,
                'saisi_par'         => $p->caissier?->nom_prenoms ?? '-',
            ];
        });

        $totalEncaisse = $lignes->where('en_attente', false)->sum('montant_encaisse');

        // Locations encore dues. UNE SEULE exclusion : celles réglées EN LIGNE,
        // encaissées par la passerelle et non au guichet.
        //
        // Contrairement aux ventes, les CLIENTS À TERME ne sont PAS écartés. Les
        // écrans de créances à terme sont bâtis sur les factures de COMMANDE
        // (CreanceClientTermeController) et ne connaissent pas les locations : les
        // exclure ici les priverait de tout moyen de règlement une fois l'écran
        // de paiement historique retiré. Ils sont donc encaissés au même guichet,
        // signalés comme tels dans la liste.
        $locationsNonSoldees = Location::with(['client', 'modeDePaiement', 'detailLocation'])
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->filter(fn (Location $l) => $l->encaissableAuGuichet() && $l->montantEncaissable() > 0)
            ->map(function (Location $l) {
                return (object) [
                    'id'            => $l->id,
                    'numero'        => $l->numero,
                    'client_id'     => $l->client_id,
                    'date'          => $l->date_location ?? $l->created_at,
                    'client_nom'    => $l->client?->display_name ?? '-',
                    'client_aterme' => (int) ($l->client?->client_a_terme ?? 0) === 1,
                    'materiel'      => $l->detailLocation
                        ->map(fn ($d) => $d->produit?->nom)
                        ->filter()
                        ->implode(', ') ?: '—',
                    'total_a_payer' => $l->montantAPayer(),
                    'reste'         => $l->montantRestantDu(),
                    'encaissable'   => $l->montantEncaissable(),
                    'etat'          => $l->etatLibelle(),
                ];
            })
            ->values();

        return view('admin.comptant.encaissementsLocation', [
            'lignes'              => $lignes,
            'totalEncaisse'       => $totalEncaisse,
            'monAgence'           => Auth::user()?->agence,
            'modesPaiement'       => ModePaiement::listePourAgent(),
            'locationsNonSoldees' => $locationsNonSoldees,
            // Filtre du guichet : liste des clients (ordinaires ET à terme, tous
            // deux encaissés ici) avec recherche par n° de compte, nom, courriel.
            'clientsPourFiltre'   => self::clientsDuGuichet(),
        ]);
    }

    /** Clients ordinaires et à terme, pour le filtre de la fenêtre d'encaissement. */
    public static function clientsDuGuichet()
    {
        return AvanceClientController::clientsPourFiltre(false)
            ->concat(AvanceClientController::clientsPourFiltre(true))
            ->sortBy('nom', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * Historique des encaissements d'une location, pour le popup de la caisse.
     *
     * Le guichet des ventes affiche ce tableau dès qu'une commande est choisie ;
     * celui des locations ne le proposait pas, si bien qu'un règlement en
     * plusieurs tranches ne laissait aucune trace visible au moment d'encaisser
     * la suivante : impossible de savoir ce qui avait déjà été payé, quand, par
     * qui, ni de retrouver le reçu correspondant.
     *
     * On y ajoute les tranches EN ATTENTE de seconde validation, absentes du
     * tableau des ventes. Elles expliquent l'écart entre le reste dû et le reste
     * encaissable, que le caissier voit sans comprendre autrement.
     */
    public function locationHistorique($numero)
    {
        $location = Location::with(['client'])->where('numero', $numero)->first();

        if (!$location) {
            return response()->json(['error' => 'Location introuvable'], 404);
        }

        $paiements = Paiement::with(['agence', 'caissier'])
            ->where('service', Help::$LOCATION)
            ->where('service_id', $location->id)
            ->whereIn('statut', [1, 2])
            ->orderBy('created_at')
            ->get();

        $historique = $paiements->map(function (Paiement $p, $idx) use ($paiements) {
            $ligne = LignePaiement::where('paiement_id', $p->id)->first();
            $mode  = null;
            if ($ligne) {
                $modeObj = ModePaiement::find($ligne->mode_paiement_id);
                $mode    = $ligne->moyen_paiement ?: ($modeObj?->libelle);
            }

            $enAttente = (int) $p->statut === 2;

            return [
                'tranche'      => ($idx + 1) . '/' . $paiements->count(),
                'date'         => $p->created_at?->format('d/m/Y H:i'),
                'montant'      => (float) $p->montant_total,
                'mode'         => $mode ?? '-',
                'agence_code'  => $p->agence?->code ?? '-',
                'caissier'     => $p->caissier?->nom_prenoms ?? '-',
                'numero_recu'  => $p->numero_recu ?? $p->code,
                'en_attente'   => $enAttente,
                // Le reçu n'existe qu'une fois l'encaissement validé.
                'recu_url'     => $enAttente ? null : route('show.recu', $p->id),
                'recu_pdf_url' => $enAttente ? null : route('show.recuPdf', $p->id),
            ];
        });

        return response()->json([
            'location' => [
                'numero'          => $location->numero,
                'client_nom'      => $location->client?->display_name ?? '-',
                'total_a_payer'   => $location->montantAPayer(),
                'total_paye'      => $location->montantPayeComptant(),
                'reste_a_payer'   => $location->montantRestantDu(),
                'en_attente'      => $location->montantEnAttenteValidation(),
                'encaissable'     => $location->montantEncaissable(),
                'tranche_num'     => $paiements->count() + 1,
            ],
            'historique' => $historique,
        ]);
    }

    public function storeEncaissement(Request $request)
    {
        $validated = $request->validate([
            // UNE OU PLUSIEURS locations du même client (10/09/2026), comme au
            // guichet des ventes : le montant s'impute de la plus ancienne à la
            // plus récente. `numero_location` (une seule) reste accepté.
            'numeros_location'   => 'nullable|array',
            'numeros_location.*' => 'string|exists:location,numero',
            'numero_location'    => 'nullable|string|exists:location,numero',
            'mode_paiement_id'   => 'required|integer|exists:mode_paiement,id',
            'montant'            => 'required|numeric|min:1',
            'date_encaissement'  => 'nullable|date',
            'reference'          => 'nullable|string|max:80',
            'notes'             => 'required|string|max:500',
            'surplus_en_avance' => 'nullable|boolean',
        ], [
            'montant.min'    => 'Le montant encaissé doit être supérieur à zéro.',
            'notes.required' => 'Le champ Notes / Observations est obligatoire : il est repris dans la colonne « Notes » du journal.',
        ]);

        $agenceId = Auth::user()?->agence_id;
        if (!$agenceId) {
            return back()->withInput()->with('erreur_caisse',
                "Vous n'êtes rattaché à aucune agence : un administrateur doit vous affecter à un guichet avant que vous puissiez encaisser.");
        }

        $numeros = array_values(array_unique(array_filter(array_merge(
            (array) ($validated['numeros_location'] ?? []),
            [$validated['numero_location'] ?? null]
        ))));
        if (empty($numeros)) {
            return back()->withInput()->with('erreur_caisse', 'Cochez au moins une location à encaisser.');
        }

        $locations = Location::whereIn('numero', $numeros)->get()
            ->sortBy(fn (Location $l) => ($l->date_location ?? $l->created_at) . '-' . $l->id)
            ->values();

        if ($locations->pluck('client_id')->unique()->count() > 1) {
            return back()->withInput()->with('erreur_caisse',
                "Les locations cochées appartiennent à des clients différents : un encaissement se fait pour un seul client à la fois.");
        }

        foreach ($locations as $location) {
            if (!$location->encaissableAuGuichet()) {
                return back()->withInput()->with('erreur_caisse',
                    "La location {$location->numero} est réglée en ligne : elle ne s'encaisse pas au guichet.");
            }
            if ($location->montantEncaissable() <= 0) {
                $enAttente = $location->montantEnAttenteValidation();

                return back()->withInput()->with('erreur_caisse', $enAttente > 0
                    ? "La location {$location->numero} est intégralement couverte par des encaissements en attente de validation ("
                      . number_format($enAttente, 0, ',', ' ') . " FCFA). Faites-les valider par un second administrateur."
                    : "La location {$location->numero} est déjà soldée.");
            }
        }

        $restes  = $locations->mapWithKeys(fn (Location $l) => [$l->id => Help::arrondiFranc($l->montantEncaissable())]);
        $plafond = $restes->sum();

        // SURPLUS → AVANCE (10/09/2026), comme au guichet des ventes : l'excédent
        // devient une avance du client, si le caissier l'a dit ; sinon refus.
        $surplus = Help::arrondiFranc(max(0, (float) $validated['montant'] - $plafond));
        if ($surplus >= 1 && !$request->boolean('surplus_en_avance')) {
            return back()->withInput()->with('erreur_caisse',
                "Le montant saisi ({$validated['montant']}) dépasse le reste à payer ({$plafond}). "
                . "Pour enregistrer l'excédent de {$surplus} FCFA comme avance du client, cochez « Enregistrer le surplus comme avance ».");
        }

        $modePaiement = ModePaiement::find($validated['mode_paiement_id']);
        $caissier     = Auth::user();

        DB::beginTransaction();
        try {
            $restant = (float) $validated['montant'];
            $recus   = [];
            foreach ($locations as $location) {
                $part = min((float) $restes[$location->id], $restant);
                if ($part <= 0) {
                    continue;
                }

                $year = date('Y');
                $lastNum = (int) Paiement::where('numero_recu', 'like', "RC-{$year}-%")
                    ->selectRaw('MAX(CAST(SUBSTRING(numero_recu, 9) AS UNSIGNED)) AS n')
                    ->value('n');
                $numeroRecu = sprintf('RC-%s-%03d', $year, $lastNum + 1);

                $paiement = Paiement::create(array_merge([
                    'client_id'       => $location->client_id,
                    'code'            => 'PAY-' . strtoupper(substr(md5(uniqid()), 0, 8)),
                    'libelle'         => $validated['notes'] ?? ('Encaissement agence - location ' . $location->numero),
                    'montant_total'   => $part,
                    'montant_restant' => 0,
                    'statut'          => 2, // en attente de la seconde validation
                    'service'         => Help::$LOCATION,
                    'service_id'      => $location->id,
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
                    'montant'          => $part,
                    'statut'           => 2, // aligné sur le paiement parent
                    'user_id'          => $caissier?->id,
                    'code_paiement'    => $paiement->code,
                    'service'          => Help::$LOCATION,
                    'service_id'       => $location->id,
                    'created_at'       => $validated['date_encaissement'] ?? now(),
                    'updated_at'       => now(),
                ]);

                $restant -= $part;
                $recus[]  = $numeroRecu;
            }

            if ($surplus >= 1) {
                $clientSurplus = \App\Models\Client::find($locations->first()->client_id);
                if ($clientSurplus) {
                    $avance = \App\Services\Avances::deposer($clientSurplus, $surplus, array_merge([
                        'mode_paiement_id' => $validated['mode_paiement_id'],
                        'reference'        => $validated['reference'] ?? null,
                        'libelle'          => 'Surplus de l\'encaissement ' . implode(', ', $recus),
                        'date_depot'       => $validated['date_encaissement'] ?? now(),
                        'origine'          => 'SURPLUS',
                        'origine_recu'     => implode(', ', $recus),
                    ], $this->initierValidation()), $caissier, $agenceId);
                    $recus[] = $avance->numero_recu . ' (avance)';
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->with('erreur_caisse', 'Erreur enregistrement : ' . $e->getMessage());
        }

        return redirect()
            ->route('show.encaissements.locations')
            ->with('success', (count($recus) > 1 ? 'Encaissements ' : 'Encaissement ') . implode(', ', $recus)
                . (count($recus) > 1 ? ' créés' : ' créé') . '. En attente de validation par un autre administrateur.');
    }

    /**
     * Seconde validation : seul un administrateur différent de l'auteur peut
     * rendre l'encaissement effectif. Tant qu'il ne l'est pas, il ne compte pas
     * dans le montant payé de la location.
     */
    public function validerEncaissement($paiementId)
    {
        $paiement = Paiement::find($paiementId);

        $result = $this->validerPaiement($paiement);
        if (!$result['ok']) {
            return back()->with('erreur_caisse', $result['message']);
        }

        LignePaiement::where('paiement_id', $paiement->id)->update(['statut' => 1]);
        // Validé deux fois : la preuve du versement reste à joindre, puis un
        // troisième administrateur finalise (point 20, 09/09/2026).
        $paiement->update(['statut' => 1, 'etat_reglement' => \App\Models\DemandePaiement::A_PAYER]);

        // Drapeau de la location, commission de l'apporteur et points du
        // client : une seule règle, partagée avec l'imputation des avances
        // (App\Services\ReglementValide::appliquerLocation).
        $location = $paiement->service_id ? Location::find($paiement->service_id) : null;
        if ($location) {
            \App\Services\ReglementValide::appliquerLocation($location, $paiement);
        }

        return back()->with('success', "Encaissement {$paiement->numero_recu} validé. Le reçu est maintenant disponible.");
    }

    /**
     * Commission de l'apporteur qui a parrainé le client.
     *
     * Reprise à l'identique de l'écran de paiement historique, retiré au profit
     * de ce guichet : sans elle, plus aucune location ne rémunérerait son
     * apporteur. Elle est portée ici, à la VALIDATION, et non à la saisie — un
     * encaissement non validé n'existe pas comptablement.
     *
     * Le TAUX dépend de la taille de l'affaire (montant total de la location),
     * mais la commission ne porte que sur la TRANCHE encaissée maintenant : une
     * location réglée en trois fois ne doit pas la payer trois fois.
     */
    private function crediterApporteur(Location $location, float $montantTranche): void
    {
        \App\Services\ReglementValide::crediterApporteurLocation($location, $montantTranche);
    }
    public function preuve($paiementId, Request $request)
    {
        return $this->joindrePreuveReglement(Paiement::find($paiementId), $request);
    }

    public function voirPreuve($paiementId)
    {
        return $this->voirPreuveReglement(Paiement::find($paiementId));
    }

    public function effectuer($paiementId)
    {
        return $this->effectuerReglement(Paiement::find($paiementId));
    }
}
