<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Réserve un écran au superadministrateur et à l'administrateur.
 *
 * Le contrôle porte sur `type_user_id`, c'est-à-dire exactement le test qui
 * décide de l'affichage du menu : les deux ne peuvent pas diverger. Masquer
 * une entrée de menu n'est pas une protection — un gestionnaire qui tape
 * l'adresse doit être refusé ici.
 */
class ReserveAuxAdministrateurs
{
    public function handle(Request $request, Closure $next): Response
    {
        $utilisateur = Auth::user();

        if (!$utilisateur) {
            return redirect()->route('show.login');
        }

        $autorises = [\Help::$USER_SA, \Help::$USER_ADMIN];

        if (!in_array((int) $utilisateur->type_user_id, array_map('intval', $autorises), true)) {
            abort(403, "Cet écran est réservé à l'administration.");
        }

        return $next($request);
    }
}
