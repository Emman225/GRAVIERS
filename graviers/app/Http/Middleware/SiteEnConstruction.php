<?php

namespace App\Http\Middleware;

use App\Models\Configuration;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * MODE « SITE EN CONSTRUCTION » (lot 114, 19/09/2026).
 *
 * Quand l'interrupteur de Paramètres est actif, un visiteur NON connecté ne voit que la page
 * « site en construction » ; toute personne connectée navigue normalement. Restent ouverts,
 * sans quoi plus personne ne pourrait entrer ni désactiver le mode : les pages de connexion de
 * tous les profils, la déconnexion, le mot de passe oublié, les confirmations de compte, le
 * retour du prestataire de paiement et le téléchargement des applications. L'API des
 * applications mobiles (routes/api.php) n'est pas concernée.
 */
class SiteEnConstruction
{
    /** Chemins accessibles sans être connecté pendant le mode. */
    private const OUVERTS = [
        'site-en-construction', 'Site-en-contruction',
        'login-account', 'logout',
        'client/login', 'client/login/*',
        'seller/login', 'apporteur/login', 'livreur/login',
        'demandeEmail', 'codeReset', 'passwordModify',
        'Page/confirmation/token', 'confirmation/*', 'apporteur/code/de/confirmation',
        'notify', 'telecharger-application/*',
        '_debugbar/*',
    ];

    public function handle(Request $request, Closure $next)
    {
        if (Auth::check() || !Configuration::siteEnConstruction()) {
            return $next($request);
        }

        if ($request->is(...self::OUVERTS)) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['message' => 'Site en construction. Connectez-vous pour continuer.'], 503);
        }

        // Après la connexion, retour à la page d'accueil (et non à la page demandée).
        $request->session()->put('url.intended', route('client.index'));

        return redirect()->route('siteEnConstruction');
    }
}
