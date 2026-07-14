<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectToProperLogin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$type): Response
    {
        // Cas 1 : utilisateur non authentifié (session perdue / jamais connecté)
        // → on redirige vers la page de connexion correspondante au PREMIER type attendu,
        //   en préservant l'URL initialement demandée (redirect()->intended() après login).
        if (! Auth::check()) {
            return $this->redirectToLogin($request, $type[0] ?? null);
        }

        // Cas 2 : utilisateur authentifié mais avec un mauvais rôle → 403 légitime.
        $types        = config('constantes.type_user_id'); // ex: ['Client'=>1,'Admin'=>2,'Gestionnaire'=>3,...]
        $type_attendu = [];
        foreach ($type as $t) {
            $type_attendu[] = $types[ucfirst(strtolower($t))] ?? null;
        }

        if (! $type_attendu || ! in_array(Auth::user()->type_user_id, $type_attendu, true)) {
            abort(403, 'Accès refusé');
        }

        return $next($request);
    }

    /**
     * Redirige un visiteur non-authentifié vers la page de login correspondant au type attendu.
     * Insensible à la casse. Préserve l'URL demandée et affiche un message « session expirée ».
     */
    private function redirectToLogin(Request $request, ?string $primaryType): Response
    {
        $route = match (strtolower((string) $primaryType)) {
            'client'                  => 'client.login',
            'fournisseur'             => 'sellers.login',
            'apporteur'               => 'apporteur.login',
            'livreur'                 => 'livreur.login',
            'admin', 'gestionnaire'   => 'show.login',
            default                   => 'show.login',
        };

        // redirect()->guest() mémorise l'URL demandée pour redirect()->intended() après login.
        return redirect()
            ->guest(route($route))
            ->with('failToken', 'Votre session a expiré. Veuillez vous reconnecter.');
    }
}
