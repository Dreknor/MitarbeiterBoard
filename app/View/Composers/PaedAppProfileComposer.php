<?php

namespace App\View\Composers;

use App\Services\Api\ApiTokenService;
use App\Services\Api\PaedAppService;
use Illuminate\View\View;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Mein Profil → Tab „Pädagogen-App“: QR-Code „App verbinden“, Liste der App-Geräte
 * und Anwendungstokens (API).
 */
class PaedAppProfileComposer
{
    public function __construct(private PaedAppService $app, private ApiTokenService $tokens)
    {
    }

    public function compose(View $view): void
    {
        $user = auth()->user();
        $connectUrl = $this->app->connectUrl();
        [$devices, $apiTokens] = $user ? $this->tokens->tokensFor($user) : [collect(), collect()];

        $view->with([
            'paedAppServerUrl' => $this->app->serverUrl(),
            'paedAppConnectUrl' => $connectUrl,
            'paedAppQrCode' => QrCode::size(200)->margin(1)->generate($connectUrl),
            'paedAppDevices' => $devices,
            'apiTokens' => $apiTokens,
            'apiTokenLaufzeiten' => ApiTokenService::API_TOKEN_LAUFZEITEN,
        ]);
    }
}
