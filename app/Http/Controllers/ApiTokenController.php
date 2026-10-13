<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreApiTokenRequest;
use App\Services\Api\ApiTokenService;

/**
 * Mein Profil → Anwendungstokens: eigene API-Tokens (Sanctum) erstellen.
 * Widerrufen erfolgt wie bei App-Geräten über PaedAppDeviceController::destroy().
 */
class ApiTokenController extends Controller
{
    public function store(StoreApiTokenRequest $request, ApiTokenService $tokens)
    {
        $token = $tokens->issueApiToken(
            $request->user(),
            $request->validated('name'),
            (int) $request->validated('laufzeit')
        );

        // Klartext nur einmalig anzeigen (Flash, nächster Request)
        return redirectBack('success', 'Das Anwendungstoken wurde erstellt. Kopieren Sie es jetzt – es wird nur einmal angezeigt.', '#api-tokens')
            ->with('apiTokenPlain', $token->plainTextToken)
            ->with('apiTokenName', $token->accessToken->name);
    }
}
