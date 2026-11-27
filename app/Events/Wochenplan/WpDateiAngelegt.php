<?php

namespace App\Events\Wochenplan;

use App\Models\Wochenplan\WpDatei;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Ein Arbeitsblatt wurde von einer Person an einen Plan gehängt (Upload oder verknüpfte
 * Nextcloud-Datei). Ausgelöst im {@see \App\Services\Wochenplan\WpAblageService};
 * die Angepassten Arbeitsblätter hören darauf, der Wochenplan bleibt ohne harte Abhängigkeit.
 */
class WpDateiAngelegt
{
    use Dispatchable;

    public function __construct(public WpDatei $datei)
    {
    }
}
