<?php

namespace App\Observers;

use App\Models\PaedDiaryTask;
use App\Services\Wochenplan\WpStatusService;

/**
 * Tagebuch → Wochenplan: Wird eine verknüpfte Tagebuch-Aufgabe erledigt oder wieder geöffnet,
 * zieht der Status der Wochenplan-Aufgabe nach (nur mit Tagebuch-Anbindung, siehe WpStatusService).
 */
class PaedDiaryTaskWochenplanObserver
{
    public function updated(PaedDiaryTask $task): void
    {
        if ($task->wasChanged('status')) {
            app(WpStatusService::class)->tagebuchAufgabeGeaendert($task);
        }
    }
}
