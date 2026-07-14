<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class autorizedAuthUser
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$type): Response
    {
        // dd(Auth::check(), !in_array(Auth::user()->type_user->nom, $type));
        if(Auth::check() && !in_array(Auth::user()->type_user->nom, $type)) {

            return redirect()->route('errors.403')->withErrors([
                'access' => 'Vous êtes déjà connecté en tant que '.Auth::user()->type_user->nom.'. Veuillez vous déconnecter pour changer de session.',
                'type' => Auth::user()->type_user->nom,
            ]);

            abort(403, 'il y a déjà une session '.Auth::user()->type_user->nom.' en cours. Veuillez vous y déconnecter pour accéder à cette page.');
        }
        return $next($request);
    }
}
