<?php

namespace App\Services\Benachrichtigungen\Tagesvorschau;

use App\Models\personal\Holiday;
use App\Models\TagesvorschauEinstellung;
use App\Models\User;
use App\Services\Personal\Zeit\ZeitZugriff;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Offene Urlaubsanträge, über die die Person entscheiden darf.
 */
class GenehmigungenQuelle extends BasisQuelle
{
    public function bereich(): string
    {
        return 'genehmigungen';
    }

    public function label(): string
    {
        return 'Offene Urlaubsanträge';
    }

    public function icon(): string
    {
        return 'fa-umbrella-beach';
    }

    public function sichtbarFuer(User $user): bool
    {
        return $user->can('approve holidays');
    }

    public function eintraege(User $user, Carbon $tag, TagesvorschauEinstellung $einstellung): Collection
    {
        // Wie UrlaubCardComposer: nur Anträge aus der eigenen Vorgesetztenkette
        $zugriff = app(ZeitZugriff::class);

        return Holiday::query()->offen()
            ->with('employe')
            ->where('employe_id', '!=', $user->id)
            ->orderBy('start_date')
            ->get()
            ->filter(fn (Holiday $h) => $h->employe !== null && $zugriff->darfUrlaubGenehmigen($user, $h->employe))
            ->values()
            ->map(fn (Holiday $h) => new TagesvorschauEintrag(
                titel: $h->employe->name,
                zeit: $h->start_date->format('d.m.').'–'.$h->end_date->format('d.m.Y'),
                url: route('holidays.manage'),
            ));
    }
}
