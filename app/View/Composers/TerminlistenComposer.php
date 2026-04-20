<?php

namespace App\View\Composers;

use App\Models\Liste;
use Illuminate\View\View;

class TerminlistenComposer
{
    public function compose(View $view): void
    {
        $user = auth()->user();
        if (!$user || !$user->can('see terminlisten')) {
            $view->with('offeneTerminlisten', collect());
            return;
        }

        $groupIds = $user->groups()->pluck('groups.id');

        // Aktive Terminlisten in den Gruppen des Users, bei denen der User noch keinen Termin gewählt hat
        $listen = Liste::where('active', true)
            ->where(function ($q) use ($user, $groupIds) {
                $q->where('visible_for_all', true)
                  ->orWhereHas('groups', fn($g) => $g->whereIn('groups.id', $groupIds));
            })
            ->where(function ($q) {
                $q->whereNull('ende')
                  ->orWhere('ende', '>=', now());
            })
            ->whereDoesntHave('eintragungen', function ($q) use ($user) {
                $q->where('reserviert_fuer', $user->id);
            })
            ->orderBy('ende')
            ->limit(5)
            ->get();

        $view->with('offeneTerminlisten', $listen);
    }
}

