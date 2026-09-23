<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Services\Api\PaedAppService;
use Illuminate\Http\JsonResponse;

/**
 * API v1: Öffentliche Instanz-Info für die Serverwahl der App (ohne Authentifizierung).
 * Enthält bewusst keine personenbezogenen Daten.
 */
class InstanceApiController extends Controller
{
    public function __construct(private PaedAppService $app)
    {
    }

    /**
     * GET /api/v1/instance
     */
    public function show(): JsonResponse
    {
        return response()->json([
            'name' => $this->app->schoolName(),
            'logo_url' => $this->app->logoUrl(),
            'primary_color' => config('paed_app.primary_color'),
            'api_version' => config('paed_app.api_version'),
            'min_app_version' => config('paed_app.min_app_version'),
            'auth' => [
                'password' => $this->app->passwordLoginEnabled(),
                'sso' => $this->app->ssoEnabled(),
                'sso_label' => config('paed_app.sso_label'),
            ],
        ]);
    }
}
