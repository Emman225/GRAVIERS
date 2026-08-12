<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Response;

class AuthUserMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = "access";
        $type = "type";
        if ($request->hasHeader($key)) {
            // $request->header() SANS argument renvoie chaque en-tête sous forme
            // de TABLEAU de valeurs : $headers['type'] valait ["4"], jamais "4".
            // La comparaison au type de l'utilisateur était donc toujours fausse
            // et ce filtre refusait tout jeton, même valide. On lit chaque
            // en-tête individuellement, ce qui rend bien une chaîne.
            try {
                $idUsr = Crypt::decrypt($request->header($key));
                $user = User::lire($idUsr);
                if ($user->id > 0 && (string) $user->type_user_id === (string) $request->header($type)) {
                    return $next($request);
                }
            } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                // Token invalide ou corrompu
            }
        }
        return response()->json(['code'=>503, 'message'=>"Requete non autorisée"], 403);
    }
}
