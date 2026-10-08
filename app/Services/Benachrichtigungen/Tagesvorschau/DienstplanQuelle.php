<?php

namespace App\Services\Benachrichtigungen\Tagesvorschau;

use App\Models\personal\RosterEvents;
use App\Models\TagesvorschauEinstellung;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Einträge aus veröffentlichten Dienstplänen (Hort/Betreuung).
 */
class DienstplanQuelle extends BasisQuelle
{
    public function bereich(): string
    {
        return 'dienstplan';
    }

    public function label(): string
    {
        return 'Dienstplan';
    }

    public function icon(): string
    {
        return 'fa-calendar-week';
    }

    public function sichtbarFuer(User $user): bool
    {
        return RosterEvents::where('employe_id', $user->id)->exists();
    }

    public function eintraege(User $user, Carbon $tag, TagesvorschauEinstellung $einstellung): Collection
    {
        return RosterEvents::query()
            ->where('employe_id', $user->id)
            ->whereDate('date', $tag)
            ->whereHas('roster', fn ($q) => $q->where('published', true))
            ->orderBy('start')
            ->get()
            ->map(fn (RosterEvents $event) => new TagesvorschauEintrag(
                titel: (string) $event->event,
                zeit: $event->start ? $event->start->format('H:i').($event->end ? '–'.$event->end->format('H:i') : '') : null,
                url: route('roster.mine'),
            ));
    }
}
