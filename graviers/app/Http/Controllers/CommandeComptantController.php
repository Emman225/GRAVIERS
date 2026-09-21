<?php

namespace App\Http\Controllers;

// Help vit dans l'espace de noms RACINE : sans cet import, « Help:: » se
// chercherait dans App\Http\Controllers et la page tomberait en « Class not
// found » — à l'exécution seulement, donc en production.
use Help;
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
    use \App\Traits\PreuveDeReglementPartenaire;

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

        // L'AGENCE DE RETRAIT SE LIT SUR LE REGLEMENT, PAS SUR LA COMMANDE.
        //
        // La colonne `commande.agence_id` existe mais n'est ecrite NULLE PART :
        // le client ne choisit aucune agence a la commande. Elle valait donc
        // toujours NULL, et la colonne « Agence de retrait » restait vide.
        //
        // L'agence devient connue a l'ENCAISSEMENT : c'est celle du caissier,
        // renseignee par les trois guichets. On la reprend de la, et on garde
        // `commande.agence_id` en premier recours si elle venait a etre remplie.
        $agencesParCommande = Paiement::with('agence')
            ->where('service', Help::$COMMANDE)
            ->whereIn('service_id', $commandes->pluck('id'))
            ->whereNotNull('agence_id')
            ->orderBy('id')
            ->get()
            ->keyBy('service_id');

        // TROISIEME SOURCE : L'AGENCE DE L'AGENT QUI A EMIS LE BON.
        //
        // Les deux precedentes ne servent que pour une commande DEJA reglee.
        // Or cet ecran liste justement celles qui ATTENDENT leur reglement :
        // la colonne restait donc vide sur toutes ses lignes.
        //
        // L'agent qui traite la commande emet le bon d'enlevement et y laisse
        // son identifiant (`enlevement.gestionnaire_id`). Son agence est celle
        // ou la marchandise se retire : c'est la reponse attendue, et elle
        // existe des le traitement.
        $agencesParAgent = \App\Models\Enlevement::query()
            ->join('livraison', 'livraison.id', '=', 'enlevement.livraison_id')
            ->join('detail_commande', 'detail_commande.id', '=', 'livraison.detail_commande_id')
            ->join('users', 'users.id', '=', 'enlevement.gestionnaire_id')
            ->join('agence', 'agence.id', '=', 'users.agence_id')
            ->whereIn('detail_commande.commande_id', $commandes->pluck('id'))
            ->whereNull('enlevement.deleted_at')
            ->orderBy('enlevement.id')
            ->pluck('agence.nom', 'detail_commande.commande_id');

        $codesParAgent = \App\Models\Enlevement::query()
            ->join('livraison', 'livraison.id', '=', 'enlevement.livraison_id')
            ->join('detail_commande', 'detail_commande.id', '=', 'livraison.detail_commande_id')
            ->join('users', 'users.id', '=', 'enlevement.gestionnaire_id')
            ->join('agence', 'agence.id', '=', 'users.agence_id')
            ->whereIn('detail_commande.commande_id', $commandes->pluck('id'))
            ->whereNull('enlevement.deleted_at')
            ->orderBy('enlevement.id')
            ->pluck('agence.code', 'detail_commande.commande_id');

        $config       = Configuration::first();
        $tauxTva      = (float) ($config?->tva ?? 18);
        $delaiAgence  = (int) ($config?->delai_max_paiement_agence ?? 3);
        $delaiAnnul   = (int) ($config?->delai_annulation_auto ?? 7);

        $lignes = $commandes->map(function (Commande $cmd) use (
            $tauxTva, $delaiAgence, $agencesParCommande, $agencesParAgent, $codesParAgent) {
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
                'nom_client'         => $client?->display_name ?? '-',
                'telephone'          => $client?->contact1,
                'email'              => $client?->user?->email ?? $client?->email,
                // Voir la note sur l'agence de retrait, plus haut.
                // Trois sources, de la plus precise a la plus disponible :
                // la commande, puis le reglement, puis l'agent qui a traite.
                'agence_code'        => $cmd->agence?->code
                    ?? $agencesParCommande[$cmd->id]?->agence?->code
                    ?? ($codesParAgent[$cmd->id] ?? null) ?? '-',
                'agence_nom'         => $cmd->agence?->nom
                    ?? $agencesParCommande[$cmd->id]?->agence?->nom
                    ?? ($agencesParAgent[$cmd->id] ?? null) ?? '-',
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
        // CE GUICHET NE MONTRE QUE LES VENTES.
        //
        // Il ne filtrait PAS sur le service, à la différence des deux autres
        // guichets (locations, demandes de livraison) qui le font depuis
        // toujours. Un encaissement de LOCATION ou de LIVRAISON fait au guichet
        // pour un client ordinaire atterrissait donc ici : sans numéro de
        // commande — il n'y en a pas — et surtout compté dans « Total encaissé »
        // des ventes, qu'il gonflait d'un montant qui n'en est pas une.
        //
        // C'est l'origine des cases vides de la colonne « N° Commande ».
        $paiements = Paiement::with(['client', 'client.user', 'agence', 'caissier', 'initiateur', 'validateur'])
            ->where('service', Help::$COMMANDE)
            ->whereIn('client_id', $clientsOrdinaires)
            ->whereIn('statut', [1, 2])
            ->whereNotNull('agence_id')
            ->whereNotNull('caissier_id')
            ->orderByDesc('created_at')
            ->get();

        $userId = Auth::id();
        $estAdmin = in_array((int) (Auth::user()?->type_user_id ?? 0), [1, 2], true);

        $lignes = $paiements->map(function (Paiement $p) use ($userId, $estAdmin) {
            // `withTrashed` : une commande mise à la corbeille APRÈS son
            // encaissement laissait la ligne sans numéro. Le règlement, lui, a
            // bien eu lieu et son reçu porte ce numéro : le caissier doit
            // pouvoir le retrouver.
            $cmd = $p->service === 'COMMANDE' && $p->service_id
                ? Commande::withTrashed()->find($p->service_id)
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
                // Traçabilité : qui a saisi l'enregistrement, qui l'a contrôlé.
                'initie_par'       => $p->initie_par,
                'valide_par'       => $p->valide_par,
                'date_encaissement' => $p->created_at,
                // Jamais une case vide : si la commande reste introuvable, on
                // dit POURQUOI plutôt que de laisser le caissier deviner.
                'numero_commande'   => $cmd?->numero
                    ?: ($p->service_id ? 'Commande n° ' . $p->service_id . ' introuvable' : 'Non rattaché'),
                'commande_supprimee' => (bool) ($cmd?->trashed()),
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
                // Point 20 (09/09/2026) : « À payer » après la 2e validation, preuve, « Effectuée ».
                // Le TROISIÈME administrateur : celui qui a finalisé, sinon celui qui a joint la preuve.
                'troisieme_par'     => \Help::compteAvecIdentifiant($p->agentEffectuee ?? $p->agentPreuve),
                'etat_reglement'    => $p->etat_reglement,
                'libelle_etat'      => $p->libelleReglement(),
                'a_preuve'          => !empty($p->preuve_paiement),
                'peut_joindre'      => $p->peutJoindrePreuve() && $p->troisiemeAdministrateur(Auth::user()),
                'peut_finaliser'    => $p->peutFinaliser() && $p->troisiemeAdministrateur(Auth::user()),
                // Sécurité : un TROISIÈME administrateur téléverse et finalise ; les validateurs voient pourquoi ils ne peuvent pas.
                'attend_troisieme'  => ($p->peutJoindrePreuve() || $p->peutFinaliser()) && !$p->troisiemeAdministrateur(Auth::user()),
            ];
        });

        // Le total encaissé ne compte que les paiements validés (pas les en-attente)
        $totalEncaisse = $lignes->where('en_attente', false)->sum('montant_encaisse');

        // Données pour le formulaire d'encaissement (modal)
        $agences      = Agence::where('statut', 1)->orderBy('nom')->get();
        // Encaissement en agence par un caissier : « en agence » est le LIEU, déjà
        // porté par le champ Agence. Ce qu'il faut saisir ici, c'est l'instrument
        // réel (Espèces, Chèque, Virement, mobile money…).
        $modesPaiement = ModePaiement::listePourAgent();
        $soldesAvance  = \App\Models\AvanceClient::soldesParClient();
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
                // Total NET dû = montantAPayer() (HT depuis les lignes + TVA + livraison
                // - remise). L'ancien calcul prenait montant_total brut : il ignorait la
                // TVA et la livraison, donc l'agence encaissait MOINS que le dû (350 au
                // lieu de 478 fcfa sur la commande 548944), et le reste à payer affiché
                // divergeait de l'écran « Solder la commande ».
                return (object) [
                    'numero'        => $c->numero,
                    'client_id'     => $c->client_id,
                    'client_nom'    => $c->client?->display_name ?? '-',
                    'date'          => $c->date_commande ?? $c->created_at,
                    'total_a_payer' => $c->montantAPayer(),
                    'reste'         => $c->montantRestantDu(),
                    'agence_id'     => $c->agence_id,
                    // Avance disponible du client (point 19) : information
                    // pour le caissier, l'imputation est automatique.
                    'solde_avance'  => (float) ($soldesAvance[$c->client_id] ?? 0),
                    // L'état de l'affaire décide, au même titre que le reste dû.
                    // Une commande ANNULÉE, ou dont le paiement en ligne n'a
                    // jamais abouti, ne doit pas être proposée au caissier :
                    // elle n'est pas non plus dans la file du gestionnaire.
                    'vivante'       => $c->affaireVivante(),
                ];
            })
            ->filter(fn($c) => $c->reste > 0 && $c->vivante)
            ->values();

        return view('admin.comptant.encaissements', [
            'lignes'              => $lignes,
            'totalEncaisse'       => $totalEncaisse,
            // Agence de la personne connectée : l'encaissement lui est imputé
            // d'office, il n'est plus choisi dans une liste.
            'monAgence'           => Auth::user()?->agence,
            'modesPaiement'       => $modesPaiement,
            'commandesNonSoldees' => $commandesNonSoldees,
            // Dépôt d'avance depuis ce guichet (point 19).
            'clientsPourAvance'   => AvanceClientController::clientsPourDepot(),
            // Le filtre du guichet est une liste des clients ORDINAIRES, où l'on
            // cherche par numéro de compte, nom, prénom ou courriel (08/09/2026).
            'clientsPourFiltre'   => AvanceClientController::clientsPourFiltre(false),
            'mentionAvance'       => \App\Services\Avances::MENTION,
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

        // Total NET dû (TVA + livraison - remise incluses), cf. Commande::montantAPayer().
        $totalAPayer = $cmd->montantAPayer();
        $totalPaye   = $cmd->montantPayeComptant();
        $reste       = $cmd->montantRestantDu();

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
                'recu_url'     => route('show.recu', $p->id),
                'recu_pdf_url' => route('show.recuPdf', $p->id),
            ];
        });

        return response()->json([
            'commande' => [
                'numero'        => $cmd->numero,
                'date_commande' => optional($cmd->date_commande)->format('d/m/Y') ?? \Carbon\Carbon::parse($cmd->date_commande)->format('d/m/Y'),
                'client_nom'    => $cmd->client?->display_name ?? '-',
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
        // PLUSIEURS COMMANDES EN UN SEUL ENCAISSEMENT (point 22, 07/09/2026).
        //
        // Le formulaire coche une ou plusieurs commandes (numeros_commande[]) ;
        // l'ancien champ unique reste accepté. Le montant saisi est imputé
        // commande par commande, de la plus ancienne à la plus récente, sans
        // jamais dépasser le reste dû de chacune — et chaque commande reçoit
        // SON règlement et SON reçu, comme si elle avait été encaissée seule.
        $validated = $request->validate([
            'numero_commande'    => 'nullable|string|exists:commande,numero',
            'numeros_commande'   => 'nullable|array',
            'numeros_commande.*' => 'string|exists:commande,numero',
            'mode_paiement_id'=> 'required|integer|exists:mode_paiement,id',
            'montant'         => 'required|numeric|min:1',
            'date_encaissement' => 'nullable|date',
            'reference'       => 'nullable|string|max:80',
            // Obligatoire depuis le 08/09/2026 : le journal porte une colonne
            // « Observations », et un encaissement sans motif ne s'y lit pas.
            'notes'           => 'required|string|max:500',
            'surplus_en_avance' => 'nullable|boolean',
        ]);

        // L'agence vient de la personne connectée, jamais du formulaire : voir
        // le commentaire de la migration users.agence_id.
        $agenceId = Auth::user()?->agence_id;
        if (!$agenceId) {
            return back()->withInput()->with('error',
                "Vous n'êtes rattaché à aucune agence : un administrateur doit vous affecter à un guichet avant que vous puissiez encaisser.");
        }

        $numeros = array_values(array_unique(array_filter(array_merge(
            (array) ($validated['numeros_commande'] ?? []),
            [$validated['numero_commande'] ?? null]
        ))));
        if (empty($numeros)) {
            return back()->withInput()->with('error', 'Cochez au moins une commande à encaisser.');
        }

        // De la plus ancienne à la plus récente : c'est l'ordre d'imputation.
        $commandes = Commande::whereIn('numero', $numeros)->get()
            ->sortBy(fn (Commande $c) => ($c->date_commande ?? $c->created_at) . '-' . $c->id)
            ->values();

        // Un encaissement vaut pour UN client : le reçu est à son nom.
        if ($commandes->pluck('client_id')->unique()->count() > 1) {
            return back()->withInput()->with('error',
                "Les commandes cochées appartiennent à des clients différents : "
                . "un encaissement se fait pour un seul client à la fois.");
        }

        // L'ÉTAT SE CONTRÔLE ICI AUSSI, PAS SEULEMENT DANS LA LISTE.
        //
        // Retirer une commande du menu ne la rend pas inencaissable : le
        // formulaire poste des NUMÉROS, et rien n'empêche d'en poster un autre —
        // un ancien onglet resté ouvert suffit. Le refus doit donc vivre là où
        // l'argent s'enregistre.
        foreach ($commandes as $cmd) {
            if (!$cmd->affaireVivante()) {
                return back()->withInput()->with('error',
                    "La commande {$cmd->numero} est « {$cmd->etat_commande} » : elle "
                    . "ne peut pas être encaissée. Une commande annulée, ou dont le "
                    . "paiement en ligne n'a jamais abouti, n'est pas dans la file "
                    . "de traitement.");
            }
        }

        // Le plafond est la somme des restes AFFICHÉS, arrondis au franc :
        // sans cela, l'écran proposait 28 813 et le serveur refusait 28 813.
        $restes  = $commandes->mapWithKeys(fn (Commande $c) => [$c->id => Help::arrondiFranc($c->montantRestantDu())]);
        $plafond = $restes->sum();

        // LE SURPLUS DEVIENT UNE AVANCE (point 19, réponse Q3 du 07/09/2026).
        //
        // Un client qui verse plus que ce qu'il doit ne dépose pas une avance
        // à part : c'est l'excédent de CET encaissement qui en devient une,
        // avec sa propre double validation et son reçu RA. Il faut le dire
        // explicitement (case « surplus en avance ») : une faute de frappe ne
        // doit pas créer un avoir en silence.
        $surplus = Help::arrondiFranc(max(0, (float) $validated['montant'] - $plafond));
        if ($surplus >= 1 && !$request->boolean('surplus_en_avance')) {
            return back()
                ->withInput()
                ->with('error', "Le montant saisi ({$validated['montant']}) dépasse le reste à payer ({$plafond}). "
                    . "Pour enregistrer l'excédent de {$surplus} FCFA comme avance du client, cochez « Enregistrer le surplus comme avance ».");
        }

        $modePaiement = ModePaiement::find($validated['mode_paiement_id']);
        $caissier     = Auth::user();

        DB::beginTransaction();
        try {
          $restant = (float) $validated['montant'];
          $recus   = [];
          foreach ($commandes as $cmd) {
            $part = min((float) $restes[$cmd->id], $restant);
            if ($part <= 0) {
                continue;
            }

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
                'montant_total'  => $part,
                'montant_restant'=> 0,
                // statut=2 = en attente de la 2e validation
                // statut=1 sera mis lors de la validation par un admin différent
                'statut'         => 2,
                'service'        => 'COMMANDE',
                'service_id'     => $cmd->id,
                'agence_id'      => $agenceId,
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
                'montant'          => $part,
                // statut=2 aligné sur le paiement parent
                'statut'           => 2,
                'user_id'          => $caissier?->id,
                'code_paiement'    => $paiement->code,
                'service'          => 'COMMANDE',
                'service_id'       => $cmd->id,
                'created_at'       => $validated['date_encaissement'] ?? now(),
                'updated_at'       => now(),
            ]);

            $restant -= $part;
            $recus[]  = $numeroRecu;
          }

          if ($surplus >= 1) {
              $avance = \App\Services\Avances::deposer($commandes->first()->client, $surplus, array_merge([
                  'mode_paiement_id' => $validated['mode_paiement_id'],
                  'reference'        => $validated['reference'] ?? null,
                  'libelle'          => 'Surplus de l\'encaissement ' . implode(', ', $recus),
                  'date_depot'       => $validated['date_encaissement'] ?? now(),
                  'origine'          => 'SURPLUS',
                  'origine_recu'     => implode(', ', $recus),
              ], $this->initierValidation()), $caissier, $agenceId);
              $recus[] = $avance->numero_recu . ' (avance)';
          }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Erreur enregistrement : ' . $e->getMessage());
        }

        return redirect()
            ->route('show.comptant.encaissements')
            ->with('success', (count($recus) > 1 ? 'Encaissements ' : 'Encaissement ') . implode(', ', $recus)
                . (count($recus) > 1 ? ' créés' : ' créé') . '. En attente de validation par un autre administrateur.');
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

        // Activer le paiement (statut=1). Validé deux fois : la preuve du
        // versement reste à joindre (point 20, 09/09/2026).
        $paiement->update(['statut' => 1, 'etat_reglement' => \App\Models\DemandePaiement::A_PAYER]);

        $commande = $paiement->service_id ? Commande::find($paiement->service_id) : null;

        if ($commande) {
            // Commission de l'apporteur et points du client : une seule règle,
            // partagée avec l'imputation des avances (App\Services\Avances).
            \App\Services\ReglementValide::appliquer($commande, $paiement);
        }

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
     * « Envoyer par courriel » depuis la page du reçu (11/09/2026) : envoi
     * IMMÉDIAT et forcé (même si un envoi est déjà daté), avec l'erreur réelle
     * affichée au guichet si le courriel ne part pas. Sert à renvoyer un reçu
     * au client et à voir pourquoi un envoi automatique n'est pas arrivé.
     */
    public function envoyerRecu($paiementId)
    {
        $paiement = Paiement::with(['client', 'client.user'])->findOrFail($paiementId);
        $client   = $paiement->client;
        $email    = $client?->user?->email ?: ($client?->email ?: null);

        if (!\App\Services\RecuPaiement::envoyerParCourriel($paiement, true, true)) {
            return back()->with('error', 'Ce client n\'a pas d\'adresse de courriel : le reçu ne peut pas lui être envoyé. Remettez-lui le reçu imprimé.');
        }
        if ($erreur = \App\Services\RecuPaiement::$derniereErreur) {
            return back()->with('error', 'Envoi impossible à ' . $email . ' : ' . $erreur);
        }

        return back()->with('success', 'Le reçu ' . ($paiement->fresh()->numero_recu ?? '') . ' est envoyé à ' . $email . '.');
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
        // Les données vivent dans App\Services\RecuPaiement : le même reçu
        // sert au guichet, au client et au courriel après un paiement en ligne.
        return \App\Services\RecuPaiement::donnees($paiement);
    }

    /** @deprecated remplacé par RecuPaiement::donnees — conservé pour mémoire. */
    private function ancienBuildRecuData(Paiement $paiement): array
    {
        // UN REÇU DOIT NOMMER CE QU'ON PAIE.
        //
        // Seule une COMMANDE était résolue. Pour une location ou un transport,
        // le reçu affichait « N° Commande : - » et repliait ses totaux sur le
        // montant versé — « Total 67 840, payé 67 840, reste 0 » sur une
        // location qui n'en devait que 55 920. Le document ne permettait ni de
        // savoir ce qui était réglé, ni de voir qu'il restait quelque chose.
        //
        // Les trois modèles exposent montantAPayer(), montantPayeComptant() et
        // montantRestantDu() : le reçu les interroge de la même façon.
        $cmd = null;
        $libelleOperation = 'N° Commande';

        if ($paiement->service_id) {
            if ($paiement->service === 'COMMANDE') {
                $cmd = Commande::find($paiement->service_id);
            } elseif ($paiement->service === Help::$LOCATION) {
                $cmd = \App\Models\Location::find($paiement->service_id);
                $libelleOperation = 'N° Location';
            } elseif ($paiement->service === Help::$LIVRAISON) {
                $cmd = \App\Models\DemandeLivraison::find($paiement->service_id);
                $libelleOperation = 'N° Demande de livraison';
            }
        }

        // Calcul de la tranche
        $allPaiements = Paiement::where(function ($q) use ($cmd, $paiement) {
                if ($cmd) {
                    $q->where(function ($qq) use ($cmd, $paiement) {
                        $qq->where('service', $paiement->service)->where('service_id', $cmd->id);
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

        // Total NET dû (HT depuis les lignes + TVA + livraison - remise), cf.
        // Commande::montantAPayer(). Le reçu affichait montant_total BRUT : il annonçait
        // donc un « Total commande » différent de celui du formulaire d'encaissement
        // (350 au lieu de 478 fcfa), et un reste à payer sous-évalué.
        $totalAPayer = $cmd ? $cmd->montantAPayer() : (float) $paiement->montant_total;
        $totalPaye   = $cmd ? $cmd->montantPayeComptant() : (float) $paiement->montant_total;
        $reste       = $cmd ? $cmd->montantRestantDu() : max(0, $totalAPayer - $totalPaye);

        $ligne = LignePaiement::where('paiement_id', $paiement->id)->first();
        $mode  = null;
        if ($ligne) {
            $modeObj = ModePaiement::find($ligne->mode_paiement_id);
            $mode    = $ligne->moyen_paiement ?: ($modeObj?->libelle);
        }

        return [
            'paiement'      => $paiement,
            'commande'      => $cmd,
            'libelleOperation' => $libelleOperation,
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
            // Total NET dû (TVA + livraison - remise), cf. Commande::montantAPayer() :
            // sur montant_total brut, la synthèse sous-évaluait le chiffre d'affaires
            // et le reste à recouvrer.
            $totalCommande += $cmd->montantAPayer();
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

            // Total NET des commandes de l'agence, recalculé depuis les lignes :
            // montant_total contient le HT côté site et le NET côté mobile, la
            // synthèse par agence mélangeait donc deux conventions.
            $totalCommandeAgence = (float) $cmdsAgence->sum(fn($c) => $c->montantAPayer());
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

    // Point 20 (09/09/2026) : preuve du versement, puis « Effectuée ».
    public function preuve($paiementId, \Illuminate\Http\Request $request)
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
