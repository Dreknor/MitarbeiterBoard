<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Mein Profil → Pädagogen-App: eigene App-Geräte (Sanctum-Tokens) abmelden.
 */
class PaedAppDeviceController extends Controller
{
    public function destroy(Request $request, int $token)
    {
        // IDOR-Schutz: ausschließlich Tokens des angemeldeten Benutzers
        $deleted = $request->user()->tokens()->whereKey($token)->delete();

        // Zurück auf die aufrufende Seite (Mein Profil oder Profil bearbeiten)
        return redirectBack(
            $deleted ? 'success' : 'warning',
            $deleted ? 'Das Gerät wurde abgemeldet.' : 'Das Gerät wurde nicht gefunden.'
        );
    }
}
