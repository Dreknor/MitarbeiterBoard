<?php

namespace App\View\Composers;

use App\Models\Monitoring\MonitoringDienst;
use App\Models\Monitoring\MonitoringEreignis;
use App\Services\Monitoring\MonitoringService;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * Reiter „Monitoring“ in den Einstellungen (settings/Monitoring).
 */
class MonitoringEinstellungenComposer
{
    public function __construct(private MonitoringService $monitoring)
    {
    }

    public function compose(View $view): void
    {
        $view->with([
            'monRollen' => Role::where('guard_name', 'web')->orderBy('name')->get(['id', 'name']),
            'monRollenSichtbar' => $this->monitoring->rollenSichtbar(),
            'monRollenBenachrichtigen' => $this->monitoring->rollenBenachrichtigen(),
            'monHeartbeatMinuten' => $this->monitoring->heartbeatMinuten(),
            'monTokenEingerichtet' => $this->monitoring->tokenEingerichtet(),
            'monTokenErstellt' => settings('monitoring_token_erstellt'),
            'monDienste' => MonitoringDienst::orderBy('sortierung')->orderBy('id')->get(),
            'monEreignisse' => MonitoringEreignis::with('dienst')->latest('id')->limit(20)->get(),
            'monEndpunkt' => url('api/monitoring/heartbeat'),
        ]);
    }
}
