<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * API v1: Erzwingt JSON-Antworten (auch bei Fehlern wie 401/404/422),
 * selbst wenn der Client keinen "Accept: application/json"-Header sendet.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next)
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
