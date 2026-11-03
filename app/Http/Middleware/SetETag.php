<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API v1: ETag für Kataloge (If-None-Match → 304 Not Modified).
 * Der ETag ist der Hash der Antwort des jeweiligen Benutzers (Kategorien sind benutzerabhängig).
 */
class SetETag
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!$request->isMethod('GET') || $response->getStatusCode() !== 200) {
            return $response;
        }

        $response->setEtag(hash('sha256', (string) $response->getContent()));
        $response->headers->set('Cache-Control', 'private, no-cache');

        // Setzt bei passendem If-None-Match Status 304 und leert den Body
        $response->isNotModified($request);

        return $response;
    }
}
