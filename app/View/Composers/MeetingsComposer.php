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

        // User::groups() liefert eine Collection (gecacht) – nicht den
        // Relationship-Query-Builder. Daher darf hier nicht per Dot-Notation
        // 'groups.id' geplucked werden, sondern direkt 'id'.
        $groupIds = $user->groups()->pluck('id')->filter()->all();

        if (empty($groupIds)) {
            $view->with('naechsteMeetings', collect());
            return;
        }

        $meetings = Meeting::whereIn('group_id', $groupIds)
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



