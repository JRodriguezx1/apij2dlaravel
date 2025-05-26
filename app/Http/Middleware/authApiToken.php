<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class authApiToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if($request->header('Authorization')){
            $token = trim(str_replace('Bearer', '', $request->header('Authorization')));
            $user = User::where('api_token', $token);
            if($user->first()){
                auth()->login($user->first());
                return $next($request);
            }
            return response()->json(['error' => 'Token invalido', 'token'=>$token, 401]);
        }
        return response()->json(['error' => 'Token o Autoriazacion no proporcionado', 401]);
    }
}
