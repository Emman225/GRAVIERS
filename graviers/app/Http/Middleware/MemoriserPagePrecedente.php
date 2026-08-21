<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Mémorise la PAGE PRÉCÉDENTE réellement visitée, pour le bouton « Retour ».
 *
 * Le bouton s'appuyait sur l'historique du navigateur : history.back(), et
 * history.go(-2) quand la page semblait s'être redirigée sur elle-même. Ce
 * second cas se déclenchait aussi sur un simple rechargement — le referrer est
 * alors identique à l'URL courante — et le retour sautait une page de trop.
 *
 * On tient donc la liste côté serveur : à chaque affichage de page, la
 * précédente devient l'ancienne courante, et seulement si l'adresse a
 * réellement changé. Un envoi de formulaire qui redirige sur la même page ne
 * décale rien, et le bouton ramène toujours exactement à la page d'avant.
 *
 * Ne retient que les affichages de PAGE : ni POST, ni requêtes AJAX, ni
 * fichiers (PDF, export), qui ne sont pas des destinations de retour.
 */
class MemoriserPagePrecedente
{
    public const CLE_COURANTE   = 'navigation_courante';
    public const CLE_PRECEDENTE = 'navigation_precedente';

    public function handle(Request $request, Closure $next)
    {
        if ($this->estUnAffichageDePage($request)) {
            $courante = $request->session()->get(self::CLE_COURANTE);
            $ici      = $request->fullUrl();

            if ($courante !== $ici) {
                if ($courante) {
                    $request->session()->put(self::CLE_PRECEDENTE, $courante);
                }
                $request->session()->put(self::CLE_COURANTE, $ici);
            }
        }

        return $next($request);
    }

    private function estUnAffichageDePage(Request $request): bool
    {
        if (!$request->isMethod('GET') || !$request->hasSession()) {
            return false;
        }

        if ($request->ajax() || $request->wantsJson() || $request->isJson()) {
            return false;
        }

        // Téléchargements et impressions : on y va, on en revient, mais ce ne
        // sont pas des pages où le bouton « Retour » doit ramener.
        foreach (['/pdf', '/telecharger', '/export'] as $suffixe) {
            if (str_contains($request->path(), $suffixe)) {
                return false;
            }
        }

        return true;
    }
}
