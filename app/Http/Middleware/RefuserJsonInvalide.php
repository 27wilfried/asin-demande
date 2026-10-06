<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuse un corps de requête annoncé en JSON mais mal formé (HTTP 400), avec un message clair.
 *
 * Sans ce contrôle, Laravel ignore le corps illisible et répond « Le NPI est obligatoire »,
 * ce qui égare sur la vraie cause (souvent des guillemets mal échappés dans un terminal).
 */
class RefuserJsonInvalide
{
    public function handle(Request $request, Closure $next): Response
    {
        $contenu = $request->getContent();

        if ($request->isJson() && trim($contenu) !== '') {
            json_decode($contenu);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'message' => "Le corps de la requête n'est pas un JSON valide : vérifiez les guillemets, les virgules et les accolades.",
                ], 400);
            }
        }

        return $next($request);
    }
}
