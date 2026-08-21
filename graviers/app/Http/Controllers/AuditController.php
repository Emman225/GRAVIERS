<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Models\TypeUser;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Le journal des opérations du back-office.
 *
 * L'accès est déjà refusé aux autres profils par le middleware
 * `admin.seulement` posé sur la route ; la vérification n'est pas répétée ici,
 * pour qu'il n'y ait qu'un seul endroit à corriger si la règle change.
 */
class AuditController extends Controller
{
    /** Au-delà, la page devient illisible et lente à charger. */
    private const PAR_PAGE = 300;

    public function index(Request $request)
    {
        $requete = Audit::with(['utilisateur', 'typeUtilisateur']);

        // --- filtre : utilisateur ---
        if ($request->filled('user_id')) {
            $requete->where('user_id', (int) $request->input('user_id'));
        }

        // --- filtre : type d'action ---
        // Le libellé porte le verbe en tête (« Validation — commande ») : on
        // filtre donc sur son début, ce qui suffit à distinguer les familles.
        if ($request->filled('action')) {
            $requete->where('action', 'like', $request->input('action') . '%');
        }

        // --- filtre : période ---
        // whereDate compare la seule date : une borne de fin au 18/08 doit
        // inclure les opérations du 18/08 à 23 h, pas s'arrêter à minuit.
        if ($request->filled('du')) {
            $requete->whereDate('created_at', '>=', $request->input('du'));
        }
        if ($request->filled('au')) {
            $requete->whereDate('created_at', '<=', $request->input('au'));
        }

        // --- recherche libre ---
        if ($request->filled('recherche')) {
            $terme = '%' . $request->input('recherche') . '%';
            $requete->where(function ($q) use ($terme) {
                $q->where('nom_utilisateur', 'like', $terme)
                  ->orWhere('action', 'like', $terme)
                  ->orWhere('url', 'like', $terme)
                  ->orWhere('route_name', 'like', $terme)
                  ->orWhere('adresse_ip', 'like', $terme);
            });
        }

        $audits = $requete->orderByDesc('created_at')->orderByDesc('id')
            ->limit(self::PAR_PAGE)->get();

        return view('admin.audit.index', [
            'audits'       => $audits,
            'utilisateurs' => $this->utilisateursAyantAgi(),
            'actions'      => $this->famillesDActions(),
            'filtres'      => $request->only(['user_id', 'action', 'du', 'au', 'recherche']),
            'limite'       => self::PAR_PAGE,
        ]);
    }

    /**
     * Les comptes qui apparaissent dans le journal.
     *
     * On lit la table `audits` plutôt que `users` : proposer dans le filtre
     * des comptes qui n'ont jamais rien fait n'aide personne, et un compte
     * supprimé doit rester sélectionnable tant que ses traces existent.
     *
     * Pas de `SELECT *` avec `GROUP BY` : MySQL 8 refuse les colonnes hors
     * agrégat. On ne sélectionne que ce qui est groupé.
     */
    private function utilisateursAyantAgi()
    {
        return Audit::query()
            ->select('user_id', 'nom_utilisateur')
            ->whereNotNull('user_id')
            ->groupBy('user_id', 'nom_utilisateur')
            ->orderBy('nom_utilisateur')
            ->get();
    }

    /** Les verbes réellement présents, tirés du début des libellés. */
    private function famillesDActions(): array
    {
        return Audit::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->map(fn ($libelle) => trim(explode('—', (string) $libelle)[0]))
            ->unique()
            ->filter()
            ->values()
            ->all();
    }
}
