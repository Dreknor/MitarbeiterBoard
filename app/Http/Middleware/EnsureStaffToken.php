<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Api\ApiTokenService;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * API v1: Lehrkraft-Routen sind nur mit einem Benutzer-Token erreichbar.
 * Schüler-Tokens (GradingStudentDevice) werden mit 403 abgewiesen.
 *
 * Zusätzlich wird die Laufzeit von App-Tokens gleitend verlängert (max. ein DB-Schreibzugriff pro Tag).
 */
class EnsureStaffToken
{
    public function __construct(private ApiTokenService $tokens)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user instanceof User) {
            return response()->json(['message' => 'Dieses Token ist für diesen Endpunkt nicht zugelassen.'], 403);
        }

        $token = $user->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $this->tokens->extend($token);
        }

        return $next($request);
    }
}
