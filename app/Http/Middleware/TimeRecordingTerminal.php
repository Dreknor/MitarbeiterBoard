<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bindet das Zeiterfassungs-Terminal an registrierte Geräte.
 *
 * Ist das Setting "time_recording_terminal_token" gesetzt, muss das Gerät einmalig
 * /time_recording/start?token=<Token> aufrufen. Danach trägt es ein verschlüsseltes
 * Cookie und darf das Terminal nutzen. Ohne Setting bleibt das Terminal offen
 * (Rate-Limit und PIN-Sperre greifen trotzdem).
 */
class TimeRecordingTerminal
{
    public const COOKIE = 'time_recording_device';

    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) settings('time_recording_terminal_token');

        if ($token === '') {
            return $next($request);
        }

        if ($request->filled('token') && hash_equals($token, (string) $request->query('token'))) {
            return redirect()->route('time_recording.start')
                ->withCookie(cookie()->forever(self::COOKIE, hash('sha256', $token)));
        }

        if (!hash_equals(hash('sha256', $token), (string) $request->cookie(self::COOKIE))) {
            abort(403, 'Dieses Gerät ist nicht als Zeiterfassungs-Terminal freigeschaltet.');
        }

        return $next($request);
    }
}
