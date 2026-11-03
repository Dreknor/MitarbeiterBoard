<?php

namespace App\Http\Controllers;

use App\Services\Api\ApiTokenService;
use Illuminate\Http\Request;

/**
 * Mein Profil → Pädagogen-App: eigene App-Geräte und Anwendungstokens (Sanctum-Tokens) abmelden.
 */
class PaedAppDeviceController extends Controller
{
    public function destroy(Request $request, ApiTokenService $tokens, int $token)
    {
        // IDOR-Schutz: ausschließlich Tokens des angemeldeten Benutzers
        $model = $request->user()->tokens()->whereKey($token)->first();

        if (!$model) {
            return redirectBack('warning', 'Das Gerät wurde nicht gefunden.');
        }

        $isApiToken = $tokens->isApiToken($model);
        $model->delete();

        // Zurück auf die aufrufende Seite (Mein Profil oder Profil bearbeiten)
        return $isApiToken
            ? redirectBack('success', 'Das Anwendungstoken wurde widerrufen.', '#api-tokens')
            : redirectBack('success', 'Das Gerät wurde abgemeldet.');
    }
}
