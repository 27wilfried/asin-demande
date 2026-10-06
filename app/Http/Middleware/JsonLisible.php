<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renvoie le JSON avec les accents en clair (« Déposée » plutôt que « Déposée »),
 * pour que les messages de l'API restent lisibles, y compris dans un terminal avec curl.
 */
class JsonLisible
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof JsonResponse) {
            $response->setEncodingOptions(
                $response->getEncodingOptions() | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }

        return $response;
    }
}
