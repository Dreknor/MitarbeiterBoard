<?php

namespace App\View\Composers;

use App\Enums\SyncStatus;
use App\Models\personal\PersonalDocument;
use Illuminate\View\View;

class SyncFehlerComposer
{
    public function compose(View $view): void
    {
        if (! auth()->user()?->can('manage personal_documents')) return;

        $fehler = PersonalDocument::where('sync_status', SyncStatus::SyncFehler->value)->count();

        $view->with('ncSyncFehlerCount', $fehler);
    }
}

