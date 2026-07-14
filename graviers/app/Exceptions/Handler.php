<?php

namespace App\Exceptions;

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

        // 404 sur un visiteur non-authentifié → rediriger vers la page de login appropriée
        // au lieu d'afficher la page "404 NOT FOUND" générique.
        $this->renderable(function (NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson()) {
                return null; // comportement JSON standard pour API
            }
            if (Auth::check()) {
                return null; // utilisateur authentifié → laisser la 404 normale
            }

            $route = $this->loginRouteForPath('/' . ltrim($request->path(), '/'));

            return redirect()
                ->guest(route($route))
                ->with('failToken', 'Page introuvable ou session expirée. Veuillez vous reconnecter.');
        });
    }

    /**
     * Déduit la route de login appropriée selon le préfixe de l'URL demandée.
     * Reproduit la logique de App\Http\Middleware\Authenticate::redirectTo().
     */
    private function loginRouteForPath(string $path): string
    {
        if (str_starts_with($path, '/seller'))    return 'sellers.login';
        if (str_starts_with($path, '/livreur'))   return 'livreur.login';
        if (str_starts_with($path, '/apporteur')) return 'apporteur.login';

        $adminPrefixes = [
            '/demande-de-livraison',
            '/liste-demande-de-livraison',
            '/detail-demande-livraison',
            '/traite-livraison-page',
            '/selecion-vehicule',
            '/parametre',
            '/recap-creances',
            '/recap-dettes',
            '/clients-terme',
            '/comptant',
            '/dette-fournisseurs',
            '/dette-livreurs',
            '/dette-apporteurs',
            '/fournisseurs',
            '/livreurs',
            '/apporteurs',
            '/agences',
            '/configuration-prix',
            '/statuts-metier',
            '/types-vehicules-livreurs',
            '/orders-list',
            '/products',
            '/list-client',
            '/liste-agent',
            '/liste-gestionnaire',
            '/grand-livre',
            '/recap',
            '/CA-',
            '/Balance',
            '/gestionnaire',
            '/dashboard',
        ];
        foreach ($adminPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return 'show.login';
            }
        }

        // Par défaut : zone client (panier, mon-compte, devis, etc.)
        return 'client.login';
    }
}
