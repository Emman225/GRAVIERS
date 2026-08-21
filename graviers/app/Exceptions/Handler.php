<?php

namespace App\Exceptions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // Une adresse inexistante affiche la page 404 — y compris pour un visiteur.
        //
        // Le code renvoyait ici TOUT visiteur non connecté vers une page de
        // connexion, avec le message « Page introuvable ou session expirée.
        // Veuillez vous reconnecter. » Deux défauts :
        //
        // 1. Un lien mort ou une adresse mal tapée sur le site public demandait au
        //    visiteur de se connecter, alors qu'aucun compte n'y changerait rien :
        //    l'adresse n'existe pas. Depuis que le blog et la demande de livraison
        //    sont ouverts au public, la zone concernée s'est encore élargie.
        // 2. La page de connexion était choisie sur une table de préfixes d'URL à
        //    tenir à jour à la main, où « /demande-de-livraison » menait à la
        //    connexion ADMIN alors que la page est celle des clients.
        //
        // Reste UN cas où la redirection garde son sens, conservé ci-dessous : une
        // route protégée dont l'identifiant ne correspond à aucun enregistrement.
        // La liaison de modèle s'exécute AVANT « auth.type », si bien qu'un visiteur
        // reçoit une 404 sans jamais qu'on lui propose de se connecter.
        //
        // La page de connexion est déduite des middlewares de la route elle-même,
        // et non plus d'une liste de préfixes : la route a été trouvée (seule la
        // liaison a échoué), donc l'information est disponible et toujours juste.
        $this->renderable(function (NotFoundHttpException $e, Request $request) {

            // API : comportement JSON standard.
            if ($request->expectsJson()) {
                return null;
            }

            // Utilisateur connecté : une 404 reste une 404.
            if (Auth::check()) {
                return null;
            }

            // Adresse qui ne correspond à AUCUNE route : vraie 404.
            $route = $request->route();
            if ($route === null) {
                return null;
            }

            // 404 provoquée par autre chose qu'un enregistrement absent : vraie 404.
            if (!$e->getPrevious() instanceof ModelNotFoundException) {
                return null;
            }

            $connexion = $this->routeDeConnexionPour($route->gatherMiddleware());

            // Route publique : rien à proposer, c'est une vraie 404.
            if ($connexion === null) {
                return null;
            }

            // redirect()->guest() mémorise l'URL demandée pour redirect()->intended().
            return redirect()
                ->guest(route($connexion))
                ->with('failToken', 'Votre session a expiré. Veuillez vous reconnecter.');
        });
    }

    /**
     * Page de connexion correspondant aux middlewares d'une route.
     *
     * Renvoie null si la route n'exige aucune authentification.
     * Même correspondance que App\Http\Middleware\RedirectToProperLogin.
     */
    private function routeDeConnexionPour(array $middlewares): ?string
    {
        foreach ($middlewares as $middleware) {

            if ($middleware === 'auth' || str_starts_with($middleware, 'auth:')) {
                return 'client.login';
            }

            if (!str_starts_with($middleware, 'auth.type:')
                && !str_starts_with($middleware, 'authorizedAuthUser.type:')) {
                continue;
            }

            // « auth.type:Admin,Gestionnaire » -> premier type attendu.
            $types = explode(',', substr($middleware, strpos($middleware, ':') + 1));

            return match (strtolower(trim($types[0]))) {
                'fournisseur' => 'sellers.login',
                'apporteur'   => 'apporteur.login',
                'livreur'     => 'livreur.login',
                'admin', 'gestionnaire' => 'show.login',
                default       => 'client.login',
            };
        }

        return null;
    }
}
