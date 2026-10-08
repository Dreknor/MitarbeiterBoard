<?php

namespace App\Services\Benachrichtigungen\Tagesvorschau;

use App\Models\Absence;
use App\Models\TagesvorschauEinstellung;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Wer ist am Zieltag abwesend? Ersetzt den bisherigen täglichen Abwesenheitsbericht
 * (früher users.absence_abo_daily). Opt-in: muss in den Einstellungen ausgewählt werden.
 */
class AbwesenheitenQuelle extends BasisQuelle
{
    public function bereich(): string
    {
        return 'abwesenheiten';
    }

    public function label(): string
    {
        return 'Abwesenheiten im Kollegium';
    }

    public function icon(): string
    {
        return 'fa-user-clock';
    }

    public function sichtbarFuer(User $user): bool
    {
        return $user->can('view absences');
    }

    public function standardAktiv(): bool
    {
        return false;
    }

    public function eintraege(User $user, Carbon $tag, TagesvorschauEinstellung $einstellung): Collection
    {
        return Absence::query()
            ->whereDate('start', '<=', $tag)
            ->whereDate('end', '>=', $tag)
            ->with('user')
            ->get()
            ->filter(fn (Absence $a) => $a->user !== null)
            ->sortBy(fn (Absence $a) => $a->user->name)
            ->values()
            ->map(fn (Absence $a) => new TagesvorschauEintrag(
                titel: $a->user->name,
                zeit: $a->start->isSameDay($a->end) ? null : 'bis '.$a->end->format('d.m.'),
                url: url('absences'),
            ));
    }
}
