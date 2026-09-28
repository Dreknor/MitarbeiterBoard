<?php

namespace App\View\Composers;

use App\Services\Api\PaedAppService;
use Illuminate\View\View;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Mein Profil → Tab „Pädagogen-App“: QR-Code „App verbinden“ und Liste der App-Geräte.
 */
class PaedAppProfileComposer
{
    public function __construct(private PaedAppService $app)
    {
    }

    public function compose(View $view): void
    {
        $user = auth()->user();
        $connectUrl = $this->app->connectUrl();

        $view->with([
            'paedAppServerUrl' => $this->app->serverUrl(),
            'paedAppConnectUrl' => $connectUrl,
            'paedAppQrCode' => QrCode::size(200)->margin(1)->generate($connectUrl),
            'paedAppDevices' => $user
                ? $user->tokens()->orderByDesc('last_used_at')->orderByDesc('id')->get()
                : collect(),
        ]);
    }
}
