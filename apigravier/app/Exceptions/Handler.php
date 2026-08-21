<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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

        // Une validation qui échoue doit répondre en JSON, comme tout le reste.
        //
        // Les contrôleurs appellent validate() AVANT leur bloc try — 68 fois sur 69.
        // L'exception n'est donc pas rattrapée, et Laravel répond alors par une
        // REDIRECTION HTML, puisque l'application n'envoie pas d'en-tête Accept.
        // jsonDecode échouait côté mobile, et l'écran affichait « Une erreur s'est
        // produite veuillez réessayer plus tard » — sans jamais nommer le champ en
        // cause. Toutes nos pannes se ressemblaient donc, quelle qu'en soit
        // l'origine, et il fallait fouiller la base pour deviner.
        //
        // On rend la forme que les contrôleurs emploient déjà dans leurs propres
        // catch : code 501 et le message des erreurs concaténé. L'application sait
        // l'afficher, elle teste « code != 200 » puis montre le message.
        $this->renderable(function (ValidationException $e, Request $request) {
            // Préfixes déclarés dans RouteServiceProvider — les trois applications.
            $routeMobile = $request->is('mon_gravier/*')
                || $request->is('mon_gravier_livreur/*')
                || $request->is('mon_gravier_apporteur/*');

            if (!$routeMobile && !$request->expectsJson()) {
                return null;
            }

            return response()->json([
                'code'    => 501,
                'message' => collect($e->errors())->flatten()->implode(" \n "),
            ]);
        });
    }
}
