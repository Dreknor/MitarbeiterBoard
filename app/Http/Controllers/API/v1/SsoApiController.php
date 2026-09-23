<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Auth\KeycloakLoginController;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\v1\ExchangeSsoCodeRequest;
use App\Http\Requests\API\v1\StartSsoRequest;
use App\Models\User;
use App\Services\Api\ApiTokenService;
use App\Services\Api\AppSsoService;
use App\Services\Api\PaedAppService;
use Illuminate\Http\JsonResponse;

/**
 * API v1: SSO-Login der Pädagogen-App über den bestehenden Keycloak-Login des Backends.
 * Ablauf siehe AppSsoService.
 */
class SsoApiController extends Controller
{
    public function __construct(
        private AppSsoService $sso,
        private ApiTokenService $tokens
    ) {
    }

    /**
     * GET /api/v1/auth/sso/start – wird von der App im System-Browser geöffnet.
     */
    public function start(StartSsoRequest $request, PaedAppService $app)
    {
        if (!$app->ssoEnabled()) {
            return response()->json(['message' => 'Die Anmeldung mit Schulkonto ist auf diesem Server nicht eingerichtet.'], 404);
        }

        $this->sso->startFlow(
            $request->input('redirect_uri'),
            $request->input('code_challenge'),
            $request->input('state')
        );

        return app(KeycloakLoginController::class)->redirectToKeycloak();
    }

    /**
     * POST /api/v1/auth/sso/exchange – Einmal-Code + code_verifier gegen App-Token tauschen.
     */
    public function exchange(ExchangeSsoCodeRequest $request): JsonResponse
    {
        $result = $this->sso->redeem($request->input('code'), $request->input('code_verifier'));

        if (is_string($result)) {
            $message = $result === 'code'
                ? 'Der Code ist ungültig, abgelaufen oder wurde bereits verwendet.'
                : 'Der code_verifier passt nicht zur code_challenge.';

            return response()->json([
                'message' => 'Die übermittelten Daten sind ungültig.',
                'errors' => [$result => [$message]],
            ], 422);
        }

        $user = User::find($result['user_id']);
        if (!$user) {
            return response()->json([
                'message' => 'Die übermittelten Daten sind ungültig.',
                'errors' => ['code' => ['Der Code ist ungültig, abgelaufen oder wurde bereits verwendet.']],
            ], 422);
        }

        if (!$this->tokens->hasAppAccess($user)) {
            return response()->json(['message' => 'Keine Berechtigung für die Pädagogen-App.'], 403);
        }

        return response()->json($this->tokens->issue($user, $request->input('device_name'), 'sso'), 201);
    }
}
