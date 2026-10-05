<?php

namespace App\Services\Personal\Zeit;

use App\Models\Absence;
use App\Models\Group;
use App\Models\personal\Holiday;
use App\Models\personal\Roster;
use App\Models\personal\RosterChange;
use App\Models\personal\RosterEvents;
use App\Models\personal\WorkingTime;
use App\Models\User;
use App\Notifications\Personal\ZeitwirtschaftNotification;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Dienstplanung: Anlegen/Kopieren, Abwesenheits-Markierungen, Konflikte,
 * Veröffentlichung und Änderungsmitteilungen.
 */
class RosterService
{
    public function __construct(private readonly ArbeitszeitService $arbeitszeit)
    {
    }

    // =========================================================================
    // Anlegen / Kopieren
    // =========================================================================

    /**
     * Neuen Dienstplan (oder Vorlage) anlegen, optional aus einer Vorlage/einem bestehenden Plan kopieren.
     */
    public function anlegen(Group $abteilung, Carbon $start, string $typ, ?string $kommentar = null, ?Roster $vorlage = null): Roster
    {
        return DB::transaction(function () use ($abteilung, $start, $typ, $kommentar, $vorlage) {
            $roster = Roster::create([
                'department_id' => $abteilung->id,
                'start_date' => $start->copy()->startOfWeek()->startOfDay(),
                'type' => $typ,
                'comment' => $kommentar,
                'published' => false,
            ]);

            $mitarbeitende = $this->mitarbeitende($roster);

            if ($vorlage !== null) {
                $this->kopiereInhalte($vorlage, $roster, $mitarbeitende);
            }

            if ($roster->type !== 'template') {
                $this->abwesendeAusplanen($roster, $mitarbeitende);
                $this->abwesenheitenFuerRoster($roster);
            }

            return $roster;
        });
    }

    /**
     * Plan in weitere Wochen kopieren (z. B. Vorlage auf mehrere Wochen anwenden).
     *
     * @param Carbon[] $wochen Montage der Zielwochen
     * @return Collection<int, Roster>
     */
    public function inWochenKopieren(Roster $quelle, array $wochen, ?string $kommentar = null): Collection
    {
        $abteilung = $quelle->department;
        $neu = collect();

        foreach ($wochen as $woche) {
            $montag = $woche->copy()->startOfWeek();
            $vorhanden = Roster::where('department_id', $abteilung->id)
                ->where('type', 'normal')
                ->whereDate('start_date', $montag->toDateString())
                ->exists();
            if ($vorhanden) {
                continue;
            }
            $neu->push($this->anlegen($abteilung, $montag, 'normal', $kommentar ?? $quelle->comment, $quelle));
        }

        return $neu;
    }

    /**
     * @return Collection<int, User>
     */
    public function mitarbeitende(Roster $roster): Collection
    {
        return $roster->department->activeEmployes($roster->start_date->copy()->startOfDay(), $roster->weekEnd())
            ->sortBy('name')->values();
    }

    private function kopiereInhalte(Roster $quelle, Roster $ziel, Collection $mitarbeitende): void
    {
        $versatz = fn (Carbon $datum) => $ziel->start_date->copy()->startOfDay()
            ->addDays($quelle->start_date->copy()->startOfDay()->diffInDays($datum->copy()->startOfDay()));
        $ids = $mitarbeitende->pluck('id');

        foreach ($quelle->events()->where(fn ($q) => $q->whereNull('source')->orWhere('source', '!=', RosterEvents::SOURCE_ABWESENHEIT))->get() as $event) {
            $tag = $versatz($event->date);
            if ($ziel->type !== 'template' && is_holiday($tag)) {
                continue;
            }
            $kopie = $event->replicate(['ox_termin_id']);
            $kopie->roster_id = $ziel->id;
            $kopie->date = $tag->toDateString();
            if ($kopie->employe_id !== null && !$ids->contains($kopie->employe_id)) {
                $kopie->employe_id = null;
            }
            $kopie->save();
        }

        foreach ($quelle->working_times as $zeit) {
            if (!$ids->contains($zeit->employe_id)) {
                continue;
            }
            $tag = $versatz($zeit->date);
            if ($ziel->type !== 'template' && is_holiday($tag)) {
                continue;
            }
            $kopie = $zeit->replicate();
            $kopie->roster_id = $ziel->id;
            $kopie->googleCalendarId = null;
            $kopie->date = $tag->toDateString();
            $kopie->save();
        }
    }

    /**
     * Beim Anlegen: Wer an einem Tag abwesend ist, bekommt aus der Vorlage keine Arbeitszeit,
     * seine Termine landen in der Merkliste. (Spätere Abwesenheiten werden nur als Konflikt markiert.)
     */
    private function abwesendeAusplanen(Roster $roster, Collection $mitarbeitende): void
    {
        $abwesenheiten = $this->abwesenheitenJeTag($mitarbeitende->pluck('id'), $roster->start_date, $roster->weekEnd());

        foreach ($abwesenheiten as $employeId => $tage) {
            foreach (array_keys($tage) as $datum) {
                $roster->working_times()->where('employe_id', $employeId)->whereDate('date', $datum)->delete();
                $roster->events()->where('employe_id', $employeId)->whereDate('date', $datum)->whereNull('source')->update(['employe_id' => null]);
            }
        }
    }

    // =========================================================================
    // Abwesenheiten (Urlaub, krank …) als Markierung im Plan
    // =========================================================================

    /**
     * Markierungen in allen betroffenen Plänen eines Mitarbeiters abgleichen.
     */
    public function abwesenheitenAbgleichen(User $employe, CarbonInterface $von, CarbonInterface $bis): void
    {
        $rosters = Roster::query()
            ->where('type', 'normal')
            ->whereDate('start_date', '<=', $bis->toDateString())
            ->whereDate('start_date', '>=', Carbon::parse($von)->startOfWeek()->toDateString())
            ->whereHas('department.employments', fn ($q) => $q->where('employe_id', $employe->id))
            ->get();

        foreach ($rosters as $roster) {
            $this->abwesenheitenFuerRoster($roster, $employe);
        }
    }

    /**
     * Abwesenheits-Markierungen eines Plans (optional nur für eine Person) auf den Sollzustand bringen.
     * Genehmigter Urlaub und Abwesenheiten werden als eigene Einträge "Urlaub", "krank" … gezeigt;
     * geplante Arbeitszeiten bleiben unangetastet und werden stattdessen als Konflikt markiert.
     */
    public function abwesenheitenFuerRoster(Roster $roster, ?User $nur = null): void
    {
        if ($roster->type === 'template') {
            return;
        }

        [$fensterStart, $fensterEnde] = $roster->department->rosterDayWindow();
        $mitarbeitende = $nur ? collect([$nur]) : $this->mitarbeitende($roster);
        $abwesenheiten = $this->abwesenheitenJeTag($mitarbeitende->pluck('id'), $roster->start_date, $roster->weekEnd());

        foreach ($mitarbeitende as $employe) {
            $vorhanden = $roster->events()
                ->where('employe_id', $employe->id)
                ->where('source', RosterEvents::SOURCE_ABWESENHEIT)
                ->get();

            $soll = [];
            foreach ($roster->days() as $tag) {
                $grund = $abwesenheiten[$employe->id][$tag->toDateString()] ?? null;
                if ($grund === null && ($feiertag = is_holiday($tag))) {
                    $grund = $feiertag['title'];
                }
                if ($grund !== null) {
                    $soll[$tag->toDateString()] = $grund;
                }
            }

            foreach ($vorhanden as $markierung) {
                $datum = $markierung->date->toDateString();
                if (($soll[$datum] ?? null) === $markierung->event) {
                    unset($soll[$datum]);
                } else {
                    $markierung->delete();
                }
            }

            // Altbestand: früher ohne Kennzeichnung angelegte Einträge ("Urlaub", Feiertagsname) übernehmen
            $alt = $soll === [] ? collect() : $roster->events()
                ->where('employe_id', $employe->id)
                ->whereNull('source')
                ->whereIn('event', array_values($soll))
                ->get();
            foreach ($alt as $eintrag) {
                $datum = $eintrag->date->toDateString();
                if (($soll[$datum] ?? null) === $eintrag->event) {
                    $eintrag->source = RosterEvents::SOURCE_ABWESENHEIT;
                    $eintrag->saveQuietly();
                    unset($soll[$datum]);
                }
            }

            foreach ($soll as $datum => $grund) {
                RosterEvents::create([
                    'roster_id' => $roster->id,
                    'employe_id' => $employe->id,
                    'date' => $datum,
                    'start' => $fensterStart.':00',
                    'end' => $fensterEnde.':00',
                    'event' => $grund,
                    'source' => RosterEvents::SOURCE_ABWESENHEIT,
                ]);
            }
        }
    }

    /**
     * [employe_id][Y-m-d] => Grund (nur genehmigter Urlaub und eingetragene Abwesenheiten).
     */
    public function abwesenheitenJeTag(Collection $employeIds, CarbonInterface $von, CarbonInterface $bis): array
    {
        $ergebnis = [];
        $von = Carbon::parse($von)->startOfDay();
        $bis = Carbon::parse($bis)->startOfDay();

        $urlaube = Holiday::query()->genehmigt()
            ->whereIn('employe_id', $employeIds)
            ->ueberschneidet($von->toDateString(), $bis->toDateString())
            ->get();

        foreach ($urlaube as $urlaub) {
            for ($tag = $urlaub->start_date->copy()->max($von)->copy(); $tag->lte($urlaub->end_date) && $tag->lte($bis); $tag->addDay()) {
                $ergebnis[$urlaub->employe_id][$tag->toDateString()] = $urlaub->half_day ? 'Urlaub (½)' : 'Urlaub';
            }
        }

        $abwesenheiten = Absence::query()
            ->whereIn('users_id', $employeIds)
            ->whereNull('holiday_id')
            ->whereDate('start', '<=', $bis->toDateString())
            ->whereDate('end', '>=', $von->toDateString())
            ->get();

        foreach ($abwesenheiten as $abwesenheit) {
            for ($tag = $abwesenheit->start->copy()->max($von)->copy(); $tag->lte($abwesenheit->end) && $tag->lte($bis); $tag->addDay()) {
                $ergebnis[$abwesenheit->users_id][$tag->toDateString()] ??= Str::limit($abwesenheit->reason, 40, '');
            }
        }

        return $ergebnis;
    }

    // =========================================================================
    // Konflikte & Kennzahlen für das Raster
    // =========================================================================

    /**
     * Konflikte je Person und Tag: [employe_id][Y-m-d] => string[]
     */
    public function konflikte(Roster $roster, Collection $mitarbeitende, Collection $arbeitszeiten, Collection $termine): array
    {
        if ($roster->type === 'template') {
            return [];
        }

        $konflikte = [];
        $abwesenheiten = $this->abwesenheitenJeTag($mitarbeitende->pluck('id'), $roster->start_date, $roster->weekEnd());

        foreach ($mitarbeitende as $employe) {
            foreach ($roster->days() as $tag) {
                $datum = $tag->toDateString();
                $zeit = $arbeitszeiten->first(fn ($w) => $w->employe_id === $employe->id && $w->date->toDateString() === $datum);
                $geplant = $termine->filter(fn ($e) => $e->employe_id === $employe->id && $e->date->toDateString() === $datum && !$e->is_abwesenheit);
                $hatDienst = ($zeit?->start !== null) || $geplant->isNotEmpty();

                if (!$hatDienst) {
                    continue;
                }

                if (isset($abwesenheiten[$employe->id][$datum])) {
                    $konflikte[$employe->id][$datum][] = $abwesenheiten[$employe->id][$datum].' – trotzdem eingeplant';
                }

                if ($this->arbeitszeit->vertraegeAm($employe, $tag)->where('department_id', $roster->department_id)->isEmpty()) {
                    $konflikte[$employe->id][$datum][] = 'Kein gültiger Vertrag in dieser Abteilung';
                }

                if ($zeit?->start !== null && $zeit->end !== null) {
                    foreach ($geplant as $termin) {
                        if ($termin->start->format('H:i') < $zeit->start->format('H:i') || $termin->end->format('H:i') > $zeit->end->format('H:i')) {
                            $konflikte[$employe->id][$datum][] = '„'.$termin->event.'“ liegt außerhalb der Arbeitszeit';
                        }
                    }
                    if ($zeit->needs_break($termine)) {
                        $konflikte[$employe->id][$datum][] = 'Pause fehlt (über 6 Stunden)';
                    }
                    if ($zeit->start->diffInMinutes($zeit->end) > 10 * 60) {
                        $konflikte[$employe->id][$datum][] = 'Mehr als 10 Stunden geplant';
                    }
                } elseif ($geplant->isNotEmpty()) {
                    $konflikte[$employe->id][$datum][] = 'Termine ohne eingetragene Arbeitszeit';
                }
            }
        }

        return $konflikte;
    }

    /**
     * Geplante Wochenstunden (abzüglich Pausen) und Vertragsstunden je Person.
     *
     * @return array<int, array{geplant: float, vertrag: float}>
     */
    public function wochenstunden(Roster $roster, Collection $mitarbeitende, Collection $arbeitszeiten, Collection $termine): array
    {
        $ergebnis = [];
        foreach ($mitarbeitende as $employe) {
            $minuten = $arbeitszeiten->where('employe_id', $employe->id)->sum(fn ($w) => $w->duration ?? 0);
            $pausen = $termine->where('employe_id', $employe->id)
                ->filter(fn ($e) => Str::contains(Str::lower($e->event), 'pause'))
                ->sum('duration');

            $vertrag = 0.0;
            foreach ($this->arbeitszeit->vertraegeAm($employe, $roster->start_date->copy()->addDays(2))->where('department_id', $roster->department_id) as $v) {
                $vertrag += $this->arbeitszeit->wochenSollSekunden($v) / 3600;
            }

            $ergebnis[$employe->id] = [
                'geplant' => round(max(0, $minuten - $pausen) / 60, 2),
                'vertrag' => round($vertrag, 2),
            ];
        }

        return $ergebnis;
    }

    // =========================================================================
    // Veröffentlichung & Änderungsmitteilungen
    // =========================================================================

    public function veroeffentlichen(Roster $roster, User $actor, bool $benachrichtigen = true): void
    {
        $roster->update(['published' => true, 'published_at' => now(), 'published_by' => $actor->id]);
        $roster->aenderungen()->whereNull('notified_at')->update(['notified_at' => now()]);

        if (!$benachrichtigen) {
            return;
        }

        $woche = $this->wochenLabel($roster);
        foreach ($this->betroffene($roster) as $employe) {
            $this->senden($employe, new ZeitwirtschaftNotification(
                'roster_published',
                'Dienstplan veröffentlicht: '.$woche,
                ['Der Dienstplan '.$roster->department->name.' für die Woche '.$woche.' ist veröffentlicht.'],
                route('roster.mine', ['woche' => $roster->start_date->toDateString()]),
                'Meinen Dienstplan ansehen'
            ));
        }
    }

    public function zurueckziehen(Roster $roster): void
    {
        $roster->update(['published' => false]);
    }

    /**
     * Änderung an einem veröffentlichten Plan protokollieren (wird von den Observern aufgerufen).
     */
    public function protokollieren(int $rosterId, ?int $employeId, ?CarbonInterface $datum, string $beschreibung): void
    {
        $roster = Roster::find($rosterId);
        if ($roster === null || !$roster->published || $roster->type === 'template') {
            return;
        }

        RosterChange::create([
            'roster_id' => $rosterId,
            'employe_id' => $employeId,
            'date' => $datum?->toDateString(),
            'description' => Str::limit($beschreibung, 250),
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Offene Änderungen den betroffenen Personen mitteilen.
     */
    public function aenderungenMitteilen(Roster $roster): int
    {
        $offen = $roster->aenderungen()->whereNull('notified_at')->with('employe')->get();
        $woche = $this->wochenLabel($roster);
        $anzahl = 0;

        foreach ($offen->whereNotNull('employe_id')->groupBy('employe_id') as $aenderungen) {
            $employe = $aenderungen->first()->employe;
            if ($employe === null) {
                continue;
            }
            $zeilen = $aenderungen->sortBy('date')->map(fn (RosterChange $c) => ($c->date ? $c->date->locale('de')->isoFormat('dd, DD.MM.').': ' : '').$c->description)->values()->all();

            $this->senden($employe, new ZeitwirtschaftNotification(
                'roster_changed',
                'Dienstplan geändert: '.$woche,
                array_merge(['Dein Dienstplan ('.$roster->department->name.') wurde geändert:'], $zeilen),
                route('roster.mine', ['woche' => $roster->start_date->toDateString()]),
                'Meinen Dienstplan ansehen'
            ));
            $anzahl++;
        }

        $roster->aenderungen()->whereNull('notified_at')->update(['notified_at' => now()]);
        $this->snapshotErstellen($roster);

        return $anzahl;
    }

    /**
     * Stand des Plans (Dienste + manuelle Termine) als Referenz für "Änderungen rückgängig machen".
     */
    public function snapshotErstellen(Roster $roster): void
    {
        $snapshot = [
            'working_times' => $roster->working_times()->get()->map(fn (WorkingTime $w) => [
                'employe_id' => $w->employe_id,
                'date' => $w->date->toDateString(),
                'start' => $w->getRawOriginal('start'),
                'end' => $w->getRawOriginal('end'),
                'function' => $w->function,
            ])->all(),
            'events' => $roster->events()->whereNull('source')->get()->map(fn (RosterEvents $e) => [
                'employe_id' => $e->employe_id,
                'date' => $e->date->toDateString(),
                'start' => $e->getRawOriginal('start'),
                'end' => $e->getRawOriginal('end'),
                'event' => $e->event,
                'ox_termin_id' => $e->ox_termin_id,
            ])->all(),
        ];

        $roster->forceFill(['published_snapshot' => json_encode($snapshot)])->save();
    }

    public function kannZuruecksetzen(Roster $roster): bool
    {
        return $roster->published && $roster->published_snapshot !== null;
    }

    /**
     * Plan auf den Stand der Veröffentlichung (bzw. letzten Mitteilung) zurücksetzen.
     */
    public function aenderungenZuruecksetzen(Roster $roster): void
    {
        $snapshot = json_decode((string) $roster->published_snapshot, true);
        abort_unless(is_array($snapshot), 422, 'Kein Stand der Veröffentlichung vorhanden.');

        DB::transaction(function () use ($roster, $snapshot) {
            $roster->working_times()->get()->each->delete();
            $roster->events()->whereNull('source')->get()->each->delete();

            foreach ($snapshot['working_times'] ?? [] as $w) {
                WorkingTime::create($w + ['roster_id' => $roster->id]);
            }
            foreach ($snapshot['events'] ?? [] as $e) {
                RosterEvents::create($e + ['roster_id' => $roster->id]);
            }

            $roster->aenderungen()->whereNull('notified_at')->delete();
        });
    }

    /**
     * @return Collection<int, User>
     */
    public function betroffene(Roster $roster): Collection
    {
        $ids = $roster->working_times()->pluck('employe_id')
            ->merge($roster->events()->whereNotNull('employe_id')->pluck('employe_id'))
            ->unique();

        return $this->mitarbeitende($roster)->filter(fn (User $u) => $ids->contains($u->id))->values();
    }

    // =========================================================================
    // Mein Dienstplan / ICS
    // =========================================================================

    /**
     * Veröffentlichte Dienste und Termine einer Person im Zeitraum.
     *
     * @return array{zeiten: Collection<int, WorkingTime>, termine: Collection<int, RosterEvents>}
     */
    public function persoenlich(User $user, CarbonInterface $von, CarbonInterface $bis): array
    {
        $veroeffentlicht = fn ($q) => $q->where('published', true)->where('type', '!=', 'template');

        return [
            'zeiten' => WorkingTime::query()
                ->where('employe_id', $user->id)
                ->whereDate('date', '>=', $von->toDateString())
                ->whereDate('date', '<=', $bis->toDateString())
                ->whereHas('roster', $veroeffentlicht)
                ->with('roster.department')
                ->orderBy('date')
                ->get(),
            'termine' => RosterEvents::query()
                ->where('employe_id', $user->id)
                ->whereDate('date', '>=', $von->toDateString())
                ->whereDate('date', '<=', $bis->toDateString())
                ->whereHas('roster', $veroeffentlicht)
                ->orderBy('date')->orderBy('start')
                ->get(),
        ];
    }

    public function icsFeed(User $user): string
    {
        $daten = $this->persoenlich($user, now()->subWeeks(4), now()->addWeeks(16));
        $jetzt = now()->utc()->format('Ymd\THis\Z');
        $zeilen = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//MitarbeiterBoard//Dienstplan//DE',
            'CALSCALE:GREGORIAN',
            'X-WR-CALNAME:'.self::icsText('Dienstplan '.$user->name),
            'X-WR-TIMEZONE:Europe/Berlin',
        ];

        foreach ($daten['zeiten'] as $zeit) {
            if ($zeit->start === null || $zeit->end === null) {
                continue;
            }
            $zeilen = array_merge($zeilen, [
                'BEGIN:VEVENT',
                'UID:mb-dienst-'.$zeit->id.'@'.parse_url(config('app.url'), PHP_URL_HOST),
                'DTSTAMP:'.$jetzt,
                'DTSTART;TZID=Europe/Berlin:'.$zeit->start->format('Ymd\THis'),
                'DTEND;TZID=Europe/Berlin:'.$zeit->end->format('Ymd\THis'),
                'SUMMARY:'.self::icsText('Dienst'.($zeit->function ? ': '.$zeit->function : '')),
                'DESCRIPTION:'.self::icsText($zeit->roster?->department?->name ?? ''),
                'END:VEVENT',
            ]);
        }

        foreach ($daten['termine'] as $termin) {
            if ($termin->start === null || $termin->end === null) {
                continue;
            }
            $zeilen = array_merge($zeilen, [
                'BEGIN:VEVENT',
                'UID:mb-termin-'.$termin->id.'@'.parse_url(config('app.url'), PHP_URL_HOST),
                'DTSTAMP:'.$jetzt,
                'DTSTART;TZID=Europe/Berlin:'.$termin->start->format('Ymd\THis'),
                'DTEND;TZID=Europe/Berlin:'.$termin->end->format('Ymd\THis'),
                'SUMMARY:'.self::icsText($termin->event),
                'END:VEVENT',
            ]);
        }

        $zeilen[] = 'END:VCALENDAR';

        return implode("\r\n", $zeilen)."\r\n";
    }

    public static function icsText(string $text): string
    {
        return str_replace(["\\", ';', ',', "\r\n", "\n"], ["\\\\", '\\;', '\\,', '\\n', '\\n'], $text);
    }

    public function wochenLabel(Roster $roster): string
    {
        return 'KW '.$roster->start_date->isoWeek().' ('.$roster->start_date->format('d.m.').'–'.$roster->weekEnd()->format('d.m.Y').')';
    }

    private function senden(User $user, ZeitwirtschaftNotification $notification): void
    {
        try {
            $user->notify($notification);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
