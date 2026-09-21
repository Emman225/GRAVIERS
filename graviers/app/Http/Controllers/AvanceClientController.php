<?php

namespace App\Http\Controllers;

// Help vit dans l'espace de noms RACINE : sans cet import, « Help:: » se
// chercherait dans App\Http\Controllers (voir CommandeComptantController).
use Help;
use App\Models\AvanceClient;
use App\Models\Client;
use App\Models\ModePaiement;
use App\Models\MouvementAvance;
use App\Services\Avances;
use App\Traits\DoubleValidationPaiement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * LES AVANCES DES CLIENTS (point 19 du cahier du 07/09/2026).
 *
 * Page « Avances clients » (liste, historique des mouvements, nouveau dépôt),
 * dépôt depuis les deux guichets (case « Dépôt d'avance »), seconde
 * validation, reçu de dépôt, et envoi du reçu au client.
 */
class AvanceClientController extends Controller
{
    use DoubleValidationPaiement;

    public function index(Request $request)
    {
        $avances = AvanceClient::with(['client', 'client.user', 'agence', 'caissier', 'initiateur', 'validateur'])
            ->orderByDesc('date_depot')
            ->orderByDesc('id')
            ->get();

        $userId   = Auth::id();
        $estAdmin = in_array((int) (Auth::user()?->type_user_id ?? 0), [1, 2], true);

        $lignes = $avances->map(function (AvanceClient $a) use ($userId, $estAdmin) {
            $enAttente = (int) $a->statut === AvanceClient::EN_ATTENTE;

            return (object) [
                'avance'       => $a,
                'id'           => $a->id,
                'date'         => $a->date_depot ?? $a->created_at,
                'numero_recu'  => $a->numero_recu,
                'client_id'    => $a->client_id,
                'client_nom'   => $a->client?->display_name ?? '-',
                'client_terme' => (int) ($a->client?->client_a_terme ?? 0) === 1,
                'montant'      => (float) $a->montant,
                'consomme'     => (float) $a->montant_consomme,
                'solde'        => $a->solde(),
                'statut'       => $a->libelleStatut(),
                'origine'      => $a->origine === 'SURPLUS'
                    ? 'Surplus' . ($a->origine_recu ? ' (' . $a->origine_recu . ')' : '')
                    : 'Dépôt',
                'mode'         => $a->moyen_paiement ?: '-',
                // La note saisie au dépôt (« Notes » du formulaire), affichée dans la liste (09/09/2026).
                'notes'        => $a->libelle ?: '-',
                'agence_code'  => $a->agence?->code ?? '-',
                'agence_nom'   => $a->agence?->nom ?? '-',
                'caissier'     => $a->caissier?->nom_prenoms ?? '-',
                'initie_par'   => $a->initie_par,
                'valide_par'   => $a->valide_par,
                'en_attente'   => $enAttente,
                'peut_valider' => $enAttente && $estAdmin && (int) $a->user_valide_id !== (int) $userId,
                'recu_envoye'  => $a->recu_envoye_le,
            ];
        });

        $mouvements = MouvementAvance::with(['client', 'avance', 'commande', 'paiement', 'auteur'])
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        // RESTE DE L'AVANCE APRÈS CHAQUE MOUVEMENT (10/09/2026) : le solde
        // courant de chaque dépôt, rejoué du plus ancien au plus récent
        // (+ dépôt, − déduction), puis affiché sur la ligne du mouvement.
        $soldes = [];
        foreach ($mouvements->sortBy('id') as $m) {
            $cle = (int) $m->avance_client_id;
            $soldes[$cle] = ($soldes[$cle] ?? 0.0)
                + ($m->type === MouvementAvance::DEPOT ? (float) $m->montant : -(float) $m->montant);
            $m->reste_apres = max(0, $soldes[$cle]);
        }

        $totalDisponible = (float) $lignes->where('en_attente', false)->sum('solde');
        $totalEnAttente  = (float) $lignes->where('en_attente', true)->sum('montant');

        return view('admin.avances.index', [
            'lignes'          => $lignes,
            'mouvements'      => $mouvements,
            'totalDisponible' => $totalDisponible,
            'totalEnAttente'  => $totalEnAttente,
            'clients'         => self::clientsPourDepot(),
            'modesPaiement'   => ModePaiement::listePourAgent(),
            'monAgence'       => Auth::user()?->agence,
            'mention'         => Avances::MENTION,
        ]);
    }

    /**
     * Les clients actifs, ordinaires et à terme, pour le menu de dépôt.
     *
     * Chaque entrée porte tout ce qu'un caissier peut taper pour retrouver un
     * client (10/09/2026) : numéro de compte (user_id), nom, prénom, courriel,
     * téléphone. Le libellé les réunit, et la liste avec recherche (select2)
     * filtre sur n'importe lequel.
     */
    public static function clientsPourDepot()
    {
        return Client::with('user')
            ->where('statut', 1)
            ->get()
            ->map(fn (Client $c) => (object) [
                'id'      => $c->id,
                'compte'  => $c->user_id,
                'nom'     => $c->display_name,
                'prenom'  => trim((string) $c->prenom),
                'email'   => $c->user?->email ?: ($c->email ?: ''),
                'contact' => $c->contact1,
                'terme'   => (int) $c->client_a_terme === 1,
            ])
            ->sortBy('nom', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * Les clients d'un guichet, pour la liste déroulante qui filtre les
     * affaires à encaisser : ordinaires (false) ou à terme (true). Chaque
     * entrée porte ce qu'un caissier cherche — numéro de compte (user_id),
     * nom ou raison sociale, courriel, téléphone — dans un seul libellé, pour
     * que la recherche de la liste trouve sur n'importe lequel.
     */
    public static function clientsPourFiltre(bool $aTerme)
    {
        return Client::with('user')
            ->where('statut', 1)
            ->where(function ($q) use ($aTerme) {
                if ($aTerme) {
                    $q->where('client_a_terme', 1);
                } else {
                    $q->where('client_a_terme', 0)->orWhereNull('client_a_terme');
                }
            })
            ->get()
            ->map(fn (Client $c) => (object) [
                'id'      => $c->id,
                'compte'  => $c->user_id,
                'nom'     => $c->display_name,
                'email'   => $c->user?->email ?: ($c->email ?: ''),
                'contact' => $c->contact1 ?: '',
            ])
            ->sortBy('nom', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * AJAX : solde d'avance d'un client et affaires qui empêchent un dépôt.
     * Sert aux guichets (récapitulatif) et au formulaire de dépôt.
     */
    public function soldeClient(Client $client)
    {
        return response()->json([
            'client_id' => $client->id,
            'client'    => $client->display_name,
            'solde'     => Avances::soldeDisponible($client),
            'affaires'  => Avances::affairesNonSoldees($client)->values()->all(),
        ]);
    }

    /**
     * Dépôt d'une avance, en attente de la seconde validation. Refusé tant
     * que le client a une affaire non soldée : il la règle d'abord, et le
     * surplus versé devient l'avance (réponse Q3 du 07/09/2026).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id'        => 'required|integer|exists:client,id',
            'montant'          => 'required|numeric|min:1',
            'mode_paiement_id' => 'required|integer|exists:mode_paiement,id',
            'date_depot'       => 'nullable|date',
            // Les deux guichets postent leur propre champ de date.
            'date_encaissement' => 'nullable|date',
            'date_paiement'    => 'nullable|date',
            'reference'        => 'nullable|string|max:80',
            // Obligatoire (09/09/2026) : la note explique le dépôt et figure dans la liste.
            'notes'            => 'required|string|max:500',
            'retour'           => 'nullable|string|max:255',
        ], [
            'notes.required' => "Le champ Notes est obligatoire : indiquez l'objet du dépôt.",
        ]);
        $dateDepot = $validated['date_depot'] ?? $validated['date_encaissement'] ?? $validated['date_paiement'] ?? now();

        // L'agence vient de la personne connectée, jamais du formulaire.
        $agenceId = Auth::user()?->agence_id;
        if (!$agenceId) {
            return back()->withInput()->with('error',
                "Vous n'êtes rattaché à aucune agence : un administrateur doit vous affecter à un guichet avant que vous puissiez encaisser.");
        }

        $client = Client::find($validated['client_id']);
        if (!$client || (int) $client->statut !== 1) {
            return back()->withInput()->with('error', 'Ce client est introuvable ou inactif.');
        }

        $affaires = Avances::affairesNonSoldees($client);
        if ($affaires->isNotEmpty()) {
            return back()->withInput()->with('error',
                "Ce client ne peut pas déposer d'avance : il a une affaire non soldée ("
                . $affaires->implode(', ') . "). Encaissez d'abord ce qu'il doit ; "
                . "le surplus versé sera enregistré comme avance.");
        }

        $avance = Avances::deposer($client, (float) $validated['montant'], array_merge([
            'mode_paiement_id' => $validated['mode_paiement_id'],
            'reference'        => $validated['reference'] ?? null,
            'libelle'          => $validated['notes'] ?? null,
            'date_depot'       => $dateDepot,
            'origine'          => 'DEPOT',
        ], $this->initierValidation()), Auth::user(), $agenceId);

        $retour = $validated['retour'] ?? null;
        $cible  = $retour && str_starts_with($retour, url('/')) ? $retour : route('show.avances.index');

        return redirect()->to($cible)->with('success',
            'Avance ' . $avance->numero_recu . ' enregistrée pour ' . $client->display_name
            . ' (' . Help::formatNombre($avance->montant, true) . '). '
            . 'En attente de validation par un autre administrateur.');
    }

    /** Seconde validation : l'avance devient disponible. */
    public function valider($id)
    {
        $avance = AvanceClient::find($id);

        $result = $this->validerPaiement($avance);
        if (!$result['ok']) {
            return back()->with('error', $result['message']);
        }

        Avances::activer($avance, Auth::id());

        return back()->with('success', 'Avance ' . $avance->numero_recu . ' validée : '
            . Help::formatNombre($avance->montant, true) . ' disponibles pour '
            . ($avance->client?->display_name ?? 'le client')
            . '. Le reçu lui a été envoyé, avec la mention « non remboursable ».');
    }

    public function recu($id)
    {
        $avance = AvanceClient::with(['client', 'agence', 'caissier'])->findOrFail($id);

        return view('admin.shared.recu-paiement', Avances::donneesRecu($avance));
    }

    public function recuPdf($id)
    {
        $avance = AvanceClient::with(['client', 'agence', 'caissier'])->findOrFail($id);

        return Avances::pdfRecu($avance)->download('recu-avance-' . ($avance->numero_recu ?? $avance->id) . '.pdf');
    }

    /** « Informer le client » : renvoie le reçu, mention comprise. */
    public function envoyerRecu($id)
    {
        $avance = AvanceClient::with(['client', 'client.user'])->findOrFail($id);

        if ((int) $avance->statut !== AvanceClient::DISPONIBLE) {
            return back()->with('error', 'Le reçu part après la seconde validation de l\'avance.');
        }

        if (!Avances::envoyerRecu($avance, true)) {
            return back()->with('error', 'Ce client n\'a pas d\'adresse de courriel : le reçu ne peut pas lui être envoyé. Remettez-lui le reçu imprimé.');
        }

        return back()->with('success', 'Le reçu d\'avance ' . $avance->numero_recu
            . ' est envoyé à ' . ($avance->client?->display_name ?? 'le client')
            . ', avec la mention « non remboursable, à utiliser ».');
    }
}
