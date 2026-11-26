<?php

namespace App\View\Composers;

use App\Services\Monitoring\MonitoringService;
use Illuminate\View\View;

/**
 * Statusleiste oberhalb der Dashboard-Karten – nur für die Rollen aus
 * der Einstellung `monitoring_rollen_sichtbar`.
 */
class MonitoringStatusComposer
{
    public function __construct(private MonitoringService $monitoring)
    {
    }

    public function compose(View $view): void
    {
        $sichtbar = $this->monitoring->sichtbarFuer(auth()->user());

        $view->with([
            'monSichtbar' => $sichtbar,
            'monAnzeige' => $sichtbar ? $this->monitoring->anzeige() : collect(),
            'monVerwalten' => $sichtbar && (bool) auth()->user()?->can('edit settings'),
        ]);
    }
}
