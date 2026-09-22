<?php

namespace App\View\Composers;

use App\Enums\DocumentStatus;
use App\Models\personal\PersonalDocument;
use Illuminate\View\View;

class ExpiringDocumentsComposer
{
    public function compose(View $view): void
    {
        if (! auth()->user()?->can('manage personal_documents')) return;

        $count = PersonalDocument::whereNotNull('expiry_date')
            ->where('status', DocumentStatus::Aktuell->value)
            ->whereDate('expiry_date', '<=', now()->addDays(30))
            ->whereDate('expiry_date', '>=', now())
            ->count();

        $view->with('expiringDocumentsCount', $count);
    }
}

