<?php

namespace App\View\Composers;

use App\Models\Meeting;
use Illuminate\View\View;

class MeetingsComposer
{
    public function compose(View $view): void
    {
        $user = auth()->user();
        if (!$user) {
            $view->with('naechsteMeetings', collect());
            return;
        }

        // Gruppen-Meetings und freie Meetings, zu denen der Nutzer eingeladen ist
        $meetings = Meeting::query()
            ->visibleTo($user)
            ->upcoming()
            ->where('date', '<=', now()->addDays(21))
            ->where('cancelled', false)
            ->with(['group'])
            ->withCount('themes')
            ->limit(5)
            ->get();

        $view->with('naechsteMeetings', $meetings);
    }
}



