<?php

namespace App\Http\Controllers;

use App\Models\PourcentageDalakoun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Le pourcentage que DALAKOUN ajoute au prix d'achat pour faire son prix de vente.
 *
 * Le taux ne s'applique qu'après une seconde validation, par un administrateur
 * différent de celui qui l'a saisi — la règle déjà en vigueur sur les
 * règlements. Un tarif engage tout le catalogue.
 */
class PourcentageDalakounController extends Controller
{
    /** Le taux en vigueur, et l'historique de tous les autres. */
    public function index()
    {
        // Migration pas encore lancée : on le DIT, au lieu de rendre une page
        // blanche que personne ne sait interpréter.
        if (!PourcentageDalakoun::tableExiste()) {
            return view('admin.pourcentageDalakoun', [
                'enVigueur'    => null,
                'taux'         => collect(),
                'produits'     => collect(),
                'derogations'  => collect(),
                'tableAbsente' => true,
            ]);
        }

        return view('admin.pourcentageDalakoun', [
            'enVigueur'    => PourcentageDalakoun::enVigueur(),
            'taux'         => PourcentageDalakoun::with(['initiateur', 'validateur', 'produit'])
                ->orderByDesc('id')
                ->get(),
            'produits'     => \App\Models\Produit::where('statut', \Help::$STATUT_ACTIF)
                ->orderBy('nom')
                ->get(['id', 'nom', 'pourcentage_dalakoun']),
            // Les produits qui échappent aujourd'hui au taux général.
            'derogations'  => \App\Models\Produit::whereNotNull('pourcentage_dalakoun')
                ->where('statut', \Help::$STATUT_ACTIF)
                ->orderBy('nom')
                ->get(['id', 'nom', 'pourcentage_dalakoun']),
            'tableAbsente' => false,
        ]);
    }

    /**
     * Saisie d'un nouveau taux. Il n'entre PAS en vigueur ici : il attend son
     * second validateur.
     */
    public function store(Request $request)
    {
        // Un retrait de dérogation se saisit sans taux : c'est la demande de
        // revenir au taux général.
        $estUnRetrait = $request->filled('produit_id') && $request->boolean('retrait');

        $request->validate([
            'produit_id' => 'nullable|integer|exists:produit,id',
            'taux'       => ($estUnRetrait ? 'nullable' : 'required') . '|numeric|min:0|max:1000',
            'motif'      => 'nullable|string|max:255',
        ], [
            'produit_id.exists' => 'Ce produit est introuvable.',
            'taux.required' => 'Le pourcentage est obligatoire.',
            'taux.numeric'  => 'Le pourcentage doit être un nombre.',
            'taux.min'      => 'Le pourcentage ne peut pas être négatif.',
            'taux.max'      => 'Le pourcentage semble hors de proportion : 1000 % au maximum.',
        ]);

        if (!PourcentageDalakoun::tableExiste()) {
            return back()->with('pourcentage_erreur',
                'La table des pourcentages n\'existe pas encore sur ce serveur : lancez la migration.');
        }

        $produitId = $request->filled('produit_id') ? (int) $request->produit_id : null;

        // Une décision déjà en attente serait une seconde décision concurrente.
        // La règle vaut PAR PÉRIMÈTRE : une dérogation sur le sable n'empêche
        // pas d'en proposer une sur le ciment, ni de réviser le taux général.
        $enAttente = PourcentageDalakoun::where('statut', PourcentageDalakoun::EN_ATTENTE)
            ->whereNull('user_valide2_id')
            ->when($produitId, fn ($q) => $q->where('produit_id', $produitId))
            ->when(!$produitId, fn ($q) => $q->whereNull('produit_id'))
            ->first();

        if ($enAttente) {
            return back()->with('pourcentage_erreur', $produitId
                ? 'Une décision attend déjà sa validation sur ce produit. Validez-la ou refusez-la avant d\'en proposer une autre.'
                : 'Un pourcentage de ' . rtrim(rtrim(number_format($enAttente->taux, 2, ',', ' '), '0'), ',')
                    . ' % attend déjà sa validation. Validez-le ou refusez-le avant d\'en proposer un autre.');
        }

        PourcentageDalakoun::create([
            'produit_id'        => $produitId,
            'taux'              => $estUnRetrait ? null : (float) $request->taux,
            'motif'             => $request->motif,
            'user_valide_id'    => Auth::id(),
            'date_validation_1' => now(),
            'statut'            => PourcentageDalakoun::EN_ATTENTE,
        ]);

        return back()->with('pourcentage_succes', $produitId
            ? 'Dérogation enregistrée. Elle prendra effet après validation par un second administrateur.'
            : 'Pourcentage enregistré. Il entrera en vigueur après validation par un second administrateur.');
    }

    /**
     * Seconde validation : le taux entre en vigueur.
     *
     * Trois refus possibles, dans cet ordre : déjà validé, validateur non
     * administrateur, ou validateur identique à celui qui a saisi.
     */
    public function valider(PourcentageDalakoun $pourcentage)
    {
        if (!$pourcentage->attendUneSecondeValidation()) {
            return back()->with('pourcentage_erreur', 'Ce pourcentage a déjà été traité.');
        }

        $user = Auth::user();

        if (!$user) {
            return back()->with('pourcentage_erreur', 'Vous devez être connecté pour valider.');
        }

        if (!in_array((int) $user->type_user_id, [\Help::$USER_SA, \Help::$USER_ADMIN], true)) {
            return back()->with('pourcentage_erreur',
                'Seul un administrateur peut valider un pourcentage.');
        }

        if ((int) $pourcentage->user_valide_id === (int) $user->id) {
            return back()->with('pourcentage_erreur',
                'Vous ne pouvez pas valider le pourcentage que vous avez saisi. Un second administrateur doit le contrôler.');
        }

        // Dernier verrou, par la même méthode que celle qui décide de montrer
        // ou non le bouton : l'écran et le serveur ne peuvent pas diverger.
        if (!$pourcentage->peutEtreValidePar($user)) {
            return back()->with('pourcentage_erreur', 'Vous ne pouvez pas valider ce pourcentage.');
        }

        $pourcentage->update([
            'user_valide2_id'   => $user->id,
            'date_validation_2' => now(),
            'statut'            => PourcentageDalakoun::APPLIQUE,
        ]);

        if ($pourcentage->estUneDerogation()) {
            // Un seul produit concerné : son prix se refait tout de suite.
            $pourcentage->appliquerAuProduit();

            return back()->with('pourcentage_succes', $pourcentage->estUnRetraitDeDerogation()
                ? 'Dérogation retirée. Ce produit suit de nouveau le taux général.'
                : 'Dérogation validée. Le prix de ce produit a été recalculé.');
        }

        return back()->with('pourcentage_succes',
            'Pourcentage validé. Il fait désormais le prix de vente du catalogue. '
            . 'Lancez « php artisan produit:recalculer-prix --apply » pour reporter le nouveau taux sur les produits déjà en ligne.');
    }

    /** Refus : le taux est écarté et ne s'appliquera jamais. */
    public function refuser(PourcentageDalakoun $pourcentage)
    {
        if (!$pourcentage->attendUneSecondeValidation()) {
            return back()->with('pourcentage_erreur', 'Ce pourcentage a déjà été traité.');
        }

        $user = Auth::user();

        if (!$user || !in_array((int) $user->type_user_id, [\Help::$USER_SA, \Help::$USER_ADMIN], true)) {
            return back()->with('pourcentage_erreur',
                'Seul un administrateur peut refuser un pourcentage.');
        }

        // Même règle que pour la validation : pas sur son propre taux.
        if (!$pourcentage->peutEtreRefusePar($user)) {
            return back()->with('pourcentage_erreur',
                'Vous ne pouvez pas refuser le pourcentage que vous avez saisi. Un second administrateur doit le traiter.');
        }

        $pourcentage->update([
            'user_valide2_id'   => $user->id,
            'date_validation_2' => now(),
            'statut'            => PourcentageDalakoun::REFUSE,
        ]);

        return back()->with('pourcentage_succes', 'Pourcentage refusé. Le taux en vigueur reste inchangé.');
    }
}
