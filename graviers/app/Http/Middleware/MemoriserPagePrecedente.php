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
 *
 * LA DÉCISION SE PREND SUR LA RÉPONSE, PAS SEULEMENT SUR L'ADRESSE.
 *
 * Signalé le 07/09/2026 : sur « Détail livreur », le bouton « Retour »
 * ouvrait la pièce d'identité du livreur. L'image s'ouvre dans un autre
 * onglet par une adresse en `/piece/recto/inline`, que rien ici n'écartait :
 * elle devenait la « page courante », puis la « page précédente » de l'écran
 * suivant. Même chose pour les documents d'un client ou d'un fournisseur, le
 * bon de commande joint, et pour toute route GET qui REDIRIGE (une validation
 * par lien, par exemple) — revenir dessus aurait rejoué l'action.
 *
 * On mémorise donc AVANT le rendu, pour que la page en cours connaisse sa
 * précédente, et on ANNULE après coup si ce qui est sorti n'est pas une page
 * HTML complète : fichier, image, flux, redirection.
 */
class MemoriserPagePrecedente
{
    public const CLE_COURANTE   = 'navigation_courante';
    public const CLE_PRECEDENTE = 'navigation_precedente';

    public function handle(Request $request, Closure $next)
    {
        if (!$this->estUnAffichageDePage($request)) {
            return $next($request);
        }

        $session   = $request->session();
        $avant     = [$session->get(self::CLE_COURANTE), $session->get(self::CLE_PRECEDENTE)];
        $courante  = $avant[0];
        $ici       = $request->fullUrl();

        if ($courante !== $ici) {
            if ($courante) {
                $session->put(self::CLE_PRECEDENTE, $courante);
            }
            $session->put(self::CLE_COURANTE, $ici);
        }

        $reponse = $next($request);

        if (!$this->estUnePageHtml($reponse)) {
            // Ce n'était pas une page : on remet la navigation comme avant.
            $session->put(self::CLE_COURANTE, $avant[0]);
            $session->put(self::CLE_PRECEDENTE, $avant[1]);
        }

        return $reponse;
    }

    /**
     * Une page HTML complète, servie avec succès. Tout le reste — image,
     * PDF, fichier, flux, redirection, erreur — n'est pas un endroit où
     * « Retour » doit ramener.
     */
    private function estUnePageHtml($reponse): bool
    {
        if (!$reponse instanceof \Symfony\Component\HttpFoundation\Response) {
            return false;
        }

        if ($reponse instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse
            || $reponse instanceof \Symfony\Component\HttpFoundation\StreamedResponse) {
            return false;
        }

        if ($reponse->isRedirection() || !$reponse->isSuccessful()) {
            return false;
        }

        $type = (string) $reponse->headers->get('Content-Type', '');

        return $type === '' || str_starts_with(strtolower($type), 'text/html');
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
        foreach (['/pdf', '/telecharger', '/export', '/inline', '/download', '/piece/', '/document/', '/imprime'] as $suffixe) {
            if (str_contains($request->path(), $suffixe)) {
                return false;
            }
        }

        return true;
    }
}
