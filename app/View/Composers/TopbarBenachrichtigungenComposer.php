<?php

namespace App\View\Composers;

use Illuminate\View\View;

/**
 * Zähler ungelesener Benachrichtigungen für die Glocke in der Topbar.
 * Die Einträge selbst lädt das Dropdown erst beim Öffnen (benachrichtigungen.neueste).
 */
class TopbarBenachrichtigungenComposer
{
    public function compose(View $view): void
    {
        $view->with('ungeleseneBenachrichtigungen', auth()->check()
            ? auth()->user()->unreadNotifications()->count()
            : 0);
    }
}
