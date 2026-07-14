<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     * Choisit la page de login selon le préfixe de l'URL demandée pour ne pas
     * envoyer un admin (par exemple) vers la page de connexion client.
     */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson()) {
            return null;
        }

        $path = '/' . ltrim($request->path(), '/');

        // Préfixes propres aux profils non-clients
        if (str_starts_with($path, '/seller')) {
            return route('sellers.login');
        }
        if (str_starts_with($path, '/livreur')) {
            return route('livreur.login');
        }
        if (str_starts_with($path, '/apporteur')) {
            return route('apporteur.login');
        }

        // URLs purement back-office (administration / gestionnaire)
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
            '/agences',
            '/configuration-prix',
            '/orders-list',
            '/products-list',
            '/products-add',
            '/products-edit',
            '/liste-agent',
            '/gestionnaire',
        ];
        foreach ($adminPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return route('show.login');
            }
        }

        // Par défaut : zone client (panier, mon-compte, devis, etc.)
        session()->flash('failToken', 'Vous devez être connecté pour effectuer cette action.');
        return route('client.login');
    }
}
