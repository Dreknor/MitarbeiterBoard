<?php

namespace App\Services\Benachrichtigungen\Tagesvorschau;

use App\Models\TagesvorschauEinstellung;
use App\Models\User;
use App\Models\Vertretung;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Eigene Vertretungen am Zieltag.
 */
class VertretungenQuelle extends BasisQuelle
{
    public function bereich(): string
    {
        return 'vertretungen';
    }

    public function label(): string
    {
        return 'Meine Vertretungen';
    }

    public function icon(): string
    {
        return 'fa-exchange-alt';
    }

    public function eintraege(User $user, Carbon $tag, TagesvorschauEinstellung $einstellung): Collection
    {
        return Vertretung::query()
            ->where('users_id', $user->id)
            ->whereDate('date', $tag)
            ->with('klasse')
            ->orderBy('stunde')
            ->get()
            ->map(fn (Vertretung $v) => new TagesvorschauEintrag(
                titel: trim(($v->klasse?->name ?? '').' '.($v->neuFach ?: $v->altFach)),
                zeit: $v->stunde.'. Std.',
                details: $v->comment ?: null,
                url: url('/'),
                hervorheben: true,
            ));
    }
}
