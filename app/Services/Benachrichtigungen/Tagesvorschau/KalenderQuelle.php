<?php

namespace App\Services\Benachrichtigungen\Tagesvorschau;

use App\Models\OxTermin;
use App\Models\TagesvorschauEinstellung;
use App\Models\User;
use App\Services\OxCalendarService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Termine aus den Kalendern, die die Person für die Tagesübersicht ausgewählt hat,
 * optional ergänzt um Termine, zu denen sie eingeladen ist.
 *
 * Es werden nur Kalender berücksichtigt, die die Person laut
 * OxCalendarService::sichtbareKalender() sehen darf – ein später entzogener
 * Kalender wird still übersprungen.
 */
class KalenderQuelle extends BasisQuelle
{
    public function __construct(private readonly OxCalendarService $kalender)
    {
    }

    public function bereich(): string
    {
        return 'kalender';
    }

    public function label(): string
    {
        return 'Termine';
    }

    public function icon(): string
    {
        return 'fa-calendar-alt';
    }

    public function sichtbarFuer(User $user): bool
    {
        return $user->can('view calendar') || $this->kalender->sichtbareKalender($user)->isNotEmpty();
    }

    public function eintraege(User $user, Carbon $tag, TagesvorschauEinstellung $einstellung): Collection
    {
        $sichtbar = $this->kalender->sichtbareKalender($user)->pluck('id');
        $kalenderIds = $sichtbar->intersect(collect($einstellung->kalender_ids ?? [])->map(fn ($id) => (int) $id))->values();

        $tagesbeginn = $tag->copy()->startOfDay();
        $tagesende = $tag->copy()->endOfDay();

        $basis = OxTermin::query()
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'CANCELLED'))
            ->where(function ($q) use ($kalenderIds, $einstellung, $user) {
                $q->whereIn('ox_calendar_id', $kalenderIds);

                if ($einstellung->eingeladene_termine && filled($user->email)) {
                    $q->orWhereHas('teilnehmer', fn ($t) => $t->where('email', $user->email));
                }
            })
            ->with('kalender');

        // Einzeltermine, die den Tag berühren (auch mehrtägige)
        $einzeln = (clone $basis)
            ->whereNull('rrule')
            ->where('beginn', '<=', $tagesende)
            ->where(fn ($q) => $q->where('ende', '>=', $tagesbeginn)->orWhere(fn ($q) => $q->whereNull('ende')->where('beginn', '>=', $tagesbeginn)))
            ->get()
            ->map(fn (OxTermin $t) => $this->alsTermin($t, $t->beginn, $t->ende));

        // Serientermine auflösen
        $serien = (clone $basis)
            ->whereNotNull('rrule')
            ->where('beginn', '<=', $tagesende)
            ->get()
            ->flatMap(function (OxTermin $t) use ($tagesbeginn, $tagesende) {
                return collect($this->kalender->expandRruleTermine($t, $tagesbeginn, $tagesende))
                    ->map(fn (array $occ) => $this->alsTermin($t, $occ['beginn'], $occ['ende'] ?? null));
            });

        return $einzeln->concat($serien)
            // Kopien eines Terminverbunds (mehrere Kalender) nur einmal
            ->unique(fn (array $t) => $t['schluessel'])
            ->sortBy(fn (array $t) => ($t['ganztaegig'] ? '0' : '1').$t['beginn']->format('H:i'))
            ->values()
            ->map(fn (array $t) => new TagesvorschauEintrag(
                titel: $t['titel'],
                zeit: $t['ganztaegig'] ? 'ganztägig' : $t['beginn']->format('H:i').($t['ende'] ? '–'.$t['ende']->format('H:i') : ''),
                details: $t['details'],
                url: route('calendar.index'),
            ));
    }

    private function alsTermin(OxTermin $termin, Carbon $beginn, ?Carbon $ende): array
    {
        // Serien-Auflösung liefert ggf. UTC – für die Anzeige in App-Zeitzone umrechnen
        $beginn = $beginn->copy()->setTimezone(config('app.timezone'));
        $ende = $ende?->copy()->setTimezone(config('app.timezone'));

        return [
            'schluessel' => ($termin->verbund_uid ?: 'id_'.$termin->id).'_'.$beginn->format('YmdHi'),
            'titel'      => (string) $termin->titel,
            'beginn'     => $beginn,
            'ende'       => $ende,
            'ganztaegig' => (bool) $termin->ganztaegig,
            'details'    => collect([$termin->ort, $termin->kalender?->name])->filter()->implode(' · ') ?: null,
        ];
    }
}
