<?php

namespace App\Http\Middleware;

use App\Models\Audit;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * LE JOURNAL DES APPELS DE L'API COMPTABLE (module « Écritures comptables »,
 * phase 4, lot 121).
 *
 * `TraceLesOperations` ne trace que les écritures du back-office (POST, PUT,
 * PATCH, DELETE) : une API de lecture n'y laisserait aucune trace. Ici,
 * TOUT appel — y compris une simple consultation — est journalisé dans le
 * même carnet (`Audit::log()`), avec la réponse obtenue, sa durée et le jeton
 * qui a servi. Posé APRÈS `admin.seulement.api` : un appel sans jeton valide
 * n'atteint jamais cette classe.
 */
class JournalerAppelApiComptable
{
    public function handle(Request $request, Closure $next): Response
    {
        $depart = microtime(true);
        $reponse = $next($request);

        try {
            $jeton = $request->user()?->currentAccessToken();
            $duree = round((microtime(true) - $depart) * 1000);

            Audit::log('Appel API comptable — ' . $request->method() . ' ' . $request->path(), array_filter([
                'jeton'        => $jeton?->name,
                'code_reponse' => $reponse->getStatusCode(),
                'duree_ms'     => $duree,
                'parametres'   => Audit::nettoyer($request->query()) ?: null,
            ], fn ($v) => $v !== null), $request);
        } catch (\Throwable $e) {
            // Audit::log() se protège déjà ; un appel API ne doit jamais
            // échouer à cause de son propre journal.
        }

        return $reponse;
    }
}
