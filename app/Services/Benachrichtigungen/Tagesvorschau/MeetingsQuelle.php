<?php

namespace App\Services\Benachrichtigungen\Tagesvorschau;

use App\Models\Meeting;
use App\Models\TagesvorschauEinstellung;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Meetings, an denen die Person teilnimmt (Gruppe, Einladung oder Rolle).
 */
class MeetingsQuelle extends BasisQuelle
{
    public function bereich(): string
    {
        return 'meetings';
    }

    public function label(): string
    {
        return 'Meetings';
    }

    public function icon(): string
    {
        return 'fa-users';
    }

    public function eintraege(User $user, Carbon $tag, TagesvorschauEinstellung $einstellung): Collection
    {
        return Meeting::query()
            ->visibleTo($user)
            ->whereDate('date', $tag)
            ->where('cancelled', false)
            ->with('group')
            ->orderBy('start_time')
            ->get()
            ->map(fn (Meeting $meeting) => new TagesvorschauEintrag(
                titel: $meeting->title ?: $meeting->contextLabel(),
                zeit: $meeting->getRawOriginal('start_time') ? $meeting->start_time : null,
                details: collect([$meeting->title ? $meeting->contextLabel() : null, $meeting->location])->filter()->implode(' · ') ?: null,
                url: route('meetings.show', $meeting),
            ));
    }
}
