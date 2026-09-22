<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * LE PENDANT JSON DE `admin.seulement`, POUR L'API COMPTABLE.
 *
 * `ReserveAuxAdministrateurs` redirige vers la page de connexion — juste pour
 * une page web. Ici, la réponse doit rester du JSON : 401 sans jeton valide,
 * 403 si le compte n'est pas administrateur ou si le jeton n'a pas
 * l'aptitude demandée.
 *
 * Un jeton porte plus longtemps qu'une session : un compte désactivé après
 * coup ne doit plus pouvoir s'en servir — contrôle que le web ne fait pas
 * (une session déjà ouverte survit à la désactivation), volontairement plus
 * strict ici.
 *
 *   Route::middleware('admin.seulement.api')                        // lecture
 *   Route::middleware('admin.seulement.api:comptabilite:ecriture')  // écriture
 */
class ReserveAuxAdministrateursApi
{
    public function handle(Request $request, Closure $next, ?string $aptitude = null): Response
    {
        $utilisateur = Auth::user();

        if (!$utilisateur) {
            return response()->json(['message' => 'Authentification requise : présentez un jeton API valide.'], 401);
        }

        if (!in_array((int) $utilisateur->type_user_id, [\Help::$USER_SA, \Help::$USER_ADMIN], true)
            || (int) $utilisateur->statut !== \Help::$STATUT_ACTIF) {
            return response()->json(['message' => "Cet accès est réservé à un administrateur actif."], 403);
        }

        $jeton = $utilisateur->currentAccessToken();
        if (!$jeton || !$jeton->can('comptabilite:lecture')) {
            return response()->json(['message' => "Ce jeton n'a pas l'aptitude « comptabilite:lecture »."], 403);
        }
        if ($aptitude && !$jeton->can($aptitude)) {
            return response()->json(['message' => "Ce jeton n'a pas l'aptitude « {$aptitude} »."], 403);
        }

        return $next($request);
    }
}
