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
            $headers = $request->header();
            try {
                $idUsr = Crypt::decrypt($headers[$key]);
                $user = User::lire($idUsr);
                if ($user->id > 0 && $user->type_user_id == $headers[$type]) {
                    return $next($request);
                }
            } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                // Token invalide ou corrompu
            }
        }
        return response()->json(['code'=>503, 'message'=>"Requete non autorisée"], 403);
    }
}
