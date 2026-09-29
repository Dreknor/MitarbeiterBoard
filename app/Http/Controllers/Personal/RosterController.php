<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use App\Http\Requests\personal\createRosterRequest;
use App\Mail\SendRosterMail;
use App\Models\Group;
use App\Models\personal\Roster;
use App\Models\personal\RosterEvents;
use App\Models\personal\WorkingTime;
use App\Models\User;
use App\Services\AutoRosterPlanner;
use App\Services\NextcloudTalkService;
use App\Services\Personal\Zeit\RosterService;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Dienstplanung. Rechte: RosterPolicy (Abteilungsbezug), Logik: RosterService.
 */
class RosterController extends Controller
{
    public function __construct(private readonly RosterService $rosters)
    {
    }

    public function index(Request $request)
    {
        $actor = $request->user();

        $abteilungen = Group::where('needsRoster', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (Group $g) => $actor->can('manageDepartment', [Roster::class, $g]))
            ->values();

        $abteilungen->load([
            'rosters' => fn ($q) => $q->withCount(['aenderungen as offene_aenderungen' => fn ($c) => $c->whereNull('notified_at')])
                ->orderByDesc('start_date'),
            'roster_checks',
        ]);

        return view('personal.rosters.index', [
            'departments' => $abteilungen,
            'aktuelleWoche' => Carbon::now()->startOfWeek(),
        ]);
    }

    public function create(Request $request, Group $department)
    {
        $this->authorize('manageDepartment', [Roster::class, $department]);

        return view('personal.rosters.create', [
            'department' => $department,
            'templates' => $department->rosters()->where('type', 'template')->orderByDesc('start_date')->get(),
            'vorlagenPlaene' => $department->rosters()->where('type', 'normal')->orderByDesc('start_date')->limit(8)->get(),
            'vorschlag' => $this->naechsteFreieWoche($department),
        ]);
    }

    public function store(createRosterRequest $request)
    {
        $abteilung = Group::findOrFail($request->department_id);
        $this->authorize('manageDepartment', [Roster::class, $abteilung]);

        $vorlage = null;
        if ($request->filled('used_template')) {
            $vorlage = Roster::findOrFail($request->used_template);
            abort_unless((int) $vorlage->department_id === (int) $abteilung->id, 422, 'Die Vorlage gehört zu einer anderen Abteilung.');
        }

        $start = Carbon::parse($request->start_date)->startOfWeek();
        $roster = $this->rosters->anlegen($abteilung, $start, $request->type, $request->comment, $vorlage);

        $weitere = [];
        foreach ((array) $request->input('weitere_wochen', []) as $woche) {
            $weitere[] = Carbon::parse($woche);
        }
        if ($weitere !== [] && $roster->type === 'normal') {
            $kopien = $this->rosters->inWochenKopieren($roster, $weitere);
        }

        $meldung = 'Dienstplan für '.$this->rosters->wochenLabel($roster).' wurde erstellt'
            .(isset($kopien) && $kopien->isNotEmpty() ? ' (+ '.$kopien->count().' weitere Woche(n))' : '').'.';

        return redirect()->route('roster.show', $roster->id)->with(['type' => 'success', 'Meldung' => $meldung]);
    }

    public function show(Roster $roster)
    {
        $this->authorize('manage', $roster);

        $roster->load(['department.roster_checks', 'news']);
        [$fensterStart, $fensterEnde] = $roster->department->rosterDayWindow();

        return view('personal.rosters.editRoster', [
            'roster' => $roster,
            'department' => $roster->department,
            'employes' => $this->rosters->mitarbeitende($roster),
            'days' => $roster->days(),
            'raster' => $this->rasterDaten($roster),
            'fenster' => ['start' => $fensterStart, 'end' => $fensterEnde],
            'offeneAenderungen' => $roster->aenderungen()->whereNull('notified_at')->count(),
            'aenderungen' => $roster->aenderungen()->with('employe')->limit(30)->get(),
            'eventNamen' => RosterEvents::query()
                ->whereHas('roster', fn ($q) => $q->where('department_id', $roster->department_id))
                ->whereNull('source')
                ->where('created_at', '>=', now()->subMonths(6))
                ->select('event')->groupBy('event')->orderByRaw('count(*) desc')->limit(40)->pluck('event'),
            'vorherige' => Roster::where('department_id', $roster->department_id)->where('type', $roster->type)
                ->whereDate('start_date', '<', $roster->start_date)->orderByDesc('start_date')->first(),
            'naechste' => Roster::where('department_id', $roster->department_id)->where('type', $roster->type)
                ->whereDate('start_date', '>', $roster->start_date)->orderBy('start_date')->first(),
        ]);
    }

    /**
     * Aktueller Stand des Rasters als JSON (nach Änderungen im Editor).
     */
    public function data(Roster $roster)
    {
        $this->authorize('manage', $roster);

        return response()->json($this->rasterDaten($roster) + [
            'offeneAenderungen' => $roster->aenderungen()->whereNull('notified_at')->count(),
        ]);
    }

    /**
     * @return array{events: array, zeiten: array, konflikte: array, stunden: array, checks: array}
     */
    private function rasterDaten(Roster $roster): array
    {
        $roster->unsetRelation('working_times')->unsetRelation('events')->load(['working_times', 'events', 'department.roster_checks']);
        $mitarbeitende = $this->rosters->mitarbeitende($roster);
        $arbeitszeiten = $roster->working_times;
        $termine = $roster->events;

        return [
            'events' => $termine->map(fn (RosterEvents $e) => [
                'id' => $e->id,
                'employe_id' => $e->employe_id,
                'date' => $e->date->toDateString(),
                'start' => $e->start?->format('H:i'),
                'end' => $e->end?->format('H:i'),
                'event' => $e->event,
                'abwesend' => $e->is_abwesenheit,
                'pause' => Str::contains(Str::lower($e->event), 'pause'),
            ])->values()->all(),
            'zeiten' => $arbeitszeiten->map(fn (WorkingTime $w) => [
                'id' => $w->id,
                'employe_id' => $w->employe_id,
                'date' => $w->date->toDateString(),
                'start' => $w->start?->format('H:i'),
                'end' => $w->end?->format('H:i'),
                'function' => $w->function,
            ])->values()->all(),
            'konflikte' => (object) $this->rosters->konflikte($roster, $mitarbeitende, $arbeitszeiten, $termine),
            'stunden' => (object) $this->rosters->wochenstunden($roster, $mitarbeitende, $arbeitszeiten, $termine),
            'checks' => (object) $this->checks($roster, $arbeitszeiten, $termine),
        ];
    }

    public function destroy(Roster $roster)
    {
        $this->authorize('manage', $roster);

        if ($roster->published && $roster->start_date->copy()->endOfWeek()->isPast()) {
            return redirectBack('warning', 'Vergangene, veröffentlichte Dienstpläne können nicht gelöscht werden.');
        }

        $roster->delete();

        return redirect()->route('roster.index')->with(['type' => 'warning', 'Meldung' => 'Dienstplan gelöscht.']);
    }

    public function publish(Request $request, Roster $roster)
    {
        $this->authorize('manage', $roster);
        abort_if($roster->is_template, 422);

        $this->rosters->veroeffentlichen($roster, $request->user(), $request->boolean('notify', true));

        return redirectBack('success', 'Dienstplan veröffentlicht'.($request->boolean('notify', true) ? ' – die eingeplanten Mitarbeitenden werden benachrichtigt.' : '.'));
    }

    public function unpublish(Roster $roster)
    {
        $this->authorize('manage', $roster);
        $this->rosters->zurueckziehen($roster);

        return redirectBack('warning', 'Veröffentlichung zurückgezogen. Der Plan ist wieder ein Entwurf.');
    }

    public function notifyChanges(Roster $roster)
    {
        $this->authorize('manage', $roster);
        $anzahl = $this->rosters->aenderungenMitteilen($roster);

        return redirectBack('success', $anzahl > 0 ? 'Änderungen an '.$anzahl.' Person(en) mitgeteilt.' : 'Keine offenen Änderungen für einzelne Personen.');
    }

    public function copy(Request $request, Roster $roster)
    {
        $this->authorize('manage', $roster);
        $data = $request->validate([
            'wochen' => ['required', 'array', 'min:1', 'max:26'],
            'wochen.*' => ['required', 'date'],
        ]);

        $kopien = $this->rosters->inWochenKopieren($roster, array_map(fn ($w) => Carbon::parse($w), $data['wochen']));

        return redirectBack($kopien->isNotEmpty() ? 'success' : 'warning', $kopien->isNotEmpty()
            ? $kopien->count().' Woche(n) angelegt. Bereits vorhandene Wochen wurden übersprungen.'
            : 'Alle gewählten Wochen haben bereits einen Dienstplan.');
    }

    public function updateSettings(Request $request, Group $department)
    {
        $this->authorize('manageDepartment', [Roster::class, $department]);
        $data = $request->validate([
            'roster_day_start' => ['required', 'date_format:H:i'],
            'roster_day_end' => ['required', 'date_format:H:i', 'after:roster_day_start'],
        ]);

        $department->update($data);

        return redirectBack('success', 'Tagesfenster für '.$department->name.' gespeichert.');
    }

    // ---- Export ----

    public function exportPDF(Roster $roster)
    {
        $this->authorize('view', $roster);

        return $this->createPDF($roster)->stream($roster->start_date->format('Y_m_d').'_dienstplan_Stand_'.now()->format('Y_m_d_H_i').'.pdf');
    }

    public function exportPdfEmploye(Request $request, Roster $roster, User $employe)
    {
        abort_unless($request->user()->can('manage', $roster) || ($request->user()->id === $employe->id && $request->user()->can('view', $roster)), 403);

        return $this->createPDFEmploye($roster, $employe)->stream(
            $roster->start_date->format('Y_m_d').'_dienstplan_'.Str::slug($employe->name).'.pdf'
        );
    }

    public function sendRosterMail(Request $request, Roster $roster)
    {
        $this->authorize('manage', $roster);

        $gesamt = $this->createPDF($roster)->output();
        $absender = $request->user()->name;
        $anzahl = 0;

        foreach ($this->rosters->mitarbeitende($roster) as $employe) {
            if (!$employe->email) {
                continue;
            }
            $dateien = [
                'dienstplan_'.$roster->start_date->format('Y_m_d').'.pdf' => $gesamt,
                'dienstplan_'.Str::slug($employe->name).'.pdf' => $this->createPDFEmploye($roster, $employe)->output(),
            ];
            Mail::to($employe->email)->queue(new SendRosterMail($employe->vorname, $employe->nachname, $roster->start_date->format('d.m.Y'), $absender, $dateien));
            $anzahl++;
        }

        return redirectBack('success', 'Dienstplan an '.$anzahl.' Person(en) versendet.');
    }

    public function sendRosterToNextcloudTalk(Roster $roster)
    {
        $this->authorize('manage', $roster);

        $nextcloud = new NextcloudTalkService();
        if (!$nextcloud->isEnabled()) {
            return redirectBack('warning', 'Nextcloud Talk ist nicht aktiviert oder nicht konfiguriert.');
        }

        $chatToken = config('nextcloud.roster_chat_token');
        if (empty($chatToken)) {
            return redirectBack('warning', 'Kein Nextcloud Talk Chat-Token konfiguriert.');
        }

        $stand = now()->format('Y_m_d_H_i_s');
        $pfad = storage_path('app/tmp/dienstplan_'.$roster->id.'_'.Str::random(12).'.pdf');
        File::ensureDirectoryExists(dirname($pfad));

        try {
            $this->createPDF($roster)->save($pfad, true);
            $nachricht = sprintf("📅 **Dienstplan %s**\n\nWoche vom %s bis %s\n", $roster->department->name, $roster->start_date->format('d.m.Y'), $roster->weekEnd()->format('d.m.Y'));
            $erfolg = $nextcloud->uploadAndShare($chatToken, $pfad, '/Dienstpläne/'.$roster->start_date->format('Y_m_d').'_dienstplan_Stand_'.$stand.'.pdf', $nachricht);
        } finally {
            File::delete($pfad);
        }

        return $erfolg
            ? redirectBack('success', 'Dienstplan wurde an Nextcloud Talk gesendet.')
            : redirectBack('danger', 'Fehler beim Senden an Nextcloud Talk. Bitte Logs prüfen.');
    }

    public function createPDF(Roster $roster)
    {
        return PDF::loadView('personal.rosters.pdf.pdf', [
            'roster' => $roster,
            'employes' => $this->rosters->mitarbeitende($roster),
            'working_times' => $roster->working_times,
            'events' => $roster->events,
            'department' => $roster->department,
        ])->setOptions([
            'encoding' => 'utf-8',
            'margin-top' => '8',
            'page-size' => 'A3',
            'orientation' => 'Landscape',
        ]);
    }

    public function createPDFEmploye(Roster $roster, User $employe)
    {
        return PDF::loadView('personal.rosters.pdf.pdfEmploye', [
            'roster' => $roster,
            'working_times' => $roster->working_times()->where('employe_id', $employe->id)->get(),
            'events' => $roster->events()->where('employe_id', $employe->id)->get(),
            'employe' => $employe,
        ])->setOptions([
            'encoding' => 'utf-8',
            'margin-top' => '10',
            'margin-bottom' => '10',
            'page-size' => 'A4',
            'orientation' => 'Landscape',
        ]);
    }

    // ---- Mein Dienstplan ----

    public function mine(Request $request)
    {
        $user = $request->user();
        $woche = $request->filled('woche') ? Carbon::parse($request->woche)->startOfWeek() : Carbon::now()->startOfWeek();
        $bis = $woche->copy()->addWeeks(2)->endOfWeek();

        $daten = $this->rosters->persoenlich($user, $woche, $bis);
        $abwesenheiten = $this->rosters->abwesenheitenJeTag(collect([$user->id]), $woche, $bis)[$user->id] ?? [];

        $tage = [];
        for ($tag = $woche->copy(); $tag->lte($bis); $tag->addDay()) {
            $datum = $tag->toDateString();
            $tage[] = [
                'date' => $tag->copy(),
                'zeiten' => $daten['zeiten']->filter(fn ($z) => $z->date->toDateString() === $datum)->values(),
                'termine' => $daten['termine']->filter(fn ($t) => $t->date->toDateString() === $datum && !$t->is_abwesenheit)->values(),
                'abwesenheit' => $abwesenheiten[$datum] ?? null,
                'feiertag' => is_holiday($tag)['title'] ?? null,
            ];
        }

        return view('personal.rosters.mine', [
            'woche' => $woche,
            'tage' => $tage,
            'feedUrl' => $user->roster_feed_token ? route('roster.feed', $user->roster_feed_token) : null,
            'plaene' => Roster::query()
                ->where('published', true)->where('type', 'normal')
                ->whereDate('start_date', '>=', $woche->toDateString())->whereDate('start_date', '<=', $bis->toDateString())
                ->whereIn('department_id', $user->groups_rel->pluck('id'))
                ->with('department')->get(),
        ]);
    }

    public function feedToken(Request $request)
    {
        $user = $request->user();
        $user->forceFill(['roster_feed_token' => $request->boolean('revoke') ? null : Str::random(48)])->save();

        return redirectBack('success', $request->boolean('revoke') ? 'Kalender-Abo deaktiviert.' : 'Neuer Abo-Link erstellt. Alte Links funktionieren nicht mehr.');
    }

    public function feed(string $token)
    {
        $user = User::where('roster_feed_token', $token)->first();
        abort_if($user === null || strlen($token) < 32, 404);

        return response($this->rosters->icsFeed($user), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="dienstplan.ics"',
            'Cache-Control' => 'private, max-age=900',
        ]);
    }

    // ---- Auto-Umplanung (unverändert, mit Abteilungsprüfung) ----

    public function autoPlan(Request $request, Roster $roster)
    {
        $this->authorize('manage', $roster);

        $simulate = array_map('intval', (array) $request->get('simulate_absent', []));
        $simulatePerDay = [];
        foreach ((array) $request->get('simulate_absent_day', []) as $day => $ids) {
            $simulatePerDay[$day] = array_map('intval', (array) $ids);
        }
        $days = $roster->days();

        $previousSuggestions = Cache::get('roster_auto_plan_suggestions_'.$roster->id, []);
        $previousByEvent = [];
        foreach ($previousSuggestions as $ps) {
            if (isset($ps['event_id'])) {
                $previousByEvent[$ps['event_id']] = $ps;
            }
        }

        $result = (new AutoRosterPlanner())->suggest($roster, $simulate, $simulatePerDay);
        $suggestions = $result['suggestions'];

        foreach ($suggestions as &$s) {
            $prev = $previousByEvent[$s['event_id']] ?? null;
            if (!$prev) {
                $s['is_new'] = true;
                continue;
            }
            $vergleich = fn ($x) => [
                $x['action'] ?? null,
                $x['to']['id'] ?? null,
                array_intersect_key($x['adjust_working_time'] ?? [], array_flip(['working_time_id', 'new_start', 'new_end', 'added_minutes'])),
                array_intersect_key($x['add_break'] ?? [], array_flip(['start', 'end', 'employe_id'])),
                array_intersect_key($x['requirement'] ?? [], array_flip(['function', 'start', 'end', 'adjust', 'adjusted'])),
            ];
            if ($vergleich($prev) !== $vergleich($s)) {
                $s['is_changed'] = true;
            }
        }
        unset($s);

        Cache::put('roster_auto_plan_suggestions_'.$roster->id, $suggestions, 600);
        Cache::put('roster_auto_plan_simulate_'.$roster->id, $simulate, 600);

        return view('personal.rosters.auto_plan', [
            'roster' => $roster,
            'suggestions' => $suggestions,
            'summary' => $result['summary'],
            'employes' => $this->rosters->mitarbeitende($roster),
            'simulate' => $simulate,
            'simulate_per_day' => $simulatePerDay,
            'hasUndo' => Cache::has('roster_auto_plan_last_apply_'.$roster->id),
            'days' => $days,
            'requirements' => $roster->department->roster_task_requirements()->orderBy('event_name')->get(),
            'hasDiff' => !empty($previousSuggestions),
        ]);
    }

    public function applyAutoPlan(Request $request, Roster $roster)
    {
        $this->authorize('manage', $roster);

        $cacheKey = 'roster_auto_plan_suggestions_'.$roster->id;
        $suggestions = Cache::get($cacheKey);
        if (!$suggestions) {
            return redirectBack('warning', 'Keine Vorschläge vorhanden oder abgelaufen.');
        }

        $selected = array_map('intval', (array) $request->get('selected'));
        $breakSelected = array_map('intval', (array) $request->get('break_selected'));
        $changes = ['events' => [], 'working_times' => [], 'break_events' => []];

        foreach ($suggestions as $s) {
            if (!in_array($s['index'], $selected, true)) {
                continue;
            }

            $event = RosterEvents::where('roster_id', $roster->id)->find($s['event_id']);
            if ($event === null) {
                continue;
            }

            if (($s['action'] ?? null) === 'reassign' && !empty($s['to']['id'])) {
                $changes['events'][] = ['event_id' => $event->id, 'old_employe_id' => $event->employe_id];
                $event->update(['employe_id' => $s['to']['id']]);

                if (!empty($s['adjust_working_time'])) {
                    $wt = WorkingTime::where('roster_id', $roster->id)->find($s['adjust_working_time']['working_time_id']);
                    if ($wt) {
                        $update = [];
                        $old = ['working_time_id' => $wt->id, 'old_start' => $wt->start?->format('H:i'), 'old_end' => $wt->end?->format('H:i')];
                        if (!empty($s['adjust_working_time']['new_start']) && $s['adjust_working_time']['new_start'] !== $wt->start?->format('H:i')) {
                            $update['start'] = $s['adjust_working_time']['new_start'].':00';
                        }
                        if (!empty($s['adjust_working_time']['new_end']) && $s['adjust_working_time']['new_end'] !== $wt->end?->format('H:i')) {
                            $update['end'] = $s['adjust_working_time']['new_end'].':00';
                        }
                        if ($update) {
                            $wt->update($update);
                            $changes['working_times'][] = $old;
                        }
                    }
                }

                if (!empty($s['add_break']) && in_array($s['index'], $breakSelected, true)) {
                    $bd = $s['add_break'];
                    $pause = RosterEvents::create([
                        'roster_id' => $roster->id,
                        'employe_id' => $bd['employe_id'],
                        'date' => $bd['date'],
                        'start' => $bd['start'].':00',
                        'end' => $bd['end'].':00',
                        'event' => $bd['event'],
                    ]);
                    $changes['break_events'][] = $pause->id;
                }
            } elseif (($s['action'] ?? null) === 'unassign') {
                $changes['events'][] = ['event_id' => $event->id, 'old_employe_id' => $event->employe_id];
                $event->update(['employe_id' => null]);
            }
        }

        Cache::forget($cacheKey);
        Cache::put('roster_auto_plan_last_apply_'.$roster->id, $changes, 3600);

        return redirect()->route('roster.autoPlan', $roster->id)->with(['type' => 'success', 'Meldung' => 'Änderungen angewendet. Du kannst sie rückgängig machen.']);
    }

    public function undoAutoPlan(Roster $roster)
    {
        $this->authorize('manage', $roster);

        $changes = Cache::pull('roster_auto_plan_last_apply_'.$roster->id);
        if (!$changes) {
            return redirectBack('warning', 'Nichts zum Rückgängigmachen gefunden.');
        }

        foreach ($changes['events'] as $c) {
            RosterEvents::where('roster_id', $roster->id)->find($c['event_id'])?->update(['employe_id' => $c['old_employe_id']]);
        }
        foreach ($changes['working_times'] as $c) {
            $wt = WorkingTime::where('roster_id', $roster->id)->find($c['working_time_id']);
            $upd = array_filter(['start' => $c['old_start'] ? $c['old_start'].':00' : null, 'end' => $c['old_end'] ? $c['old_end'].':00' : null]);
            if ($wt && $upd) {
                $wt->update($upd);
            }
        }
        foreach ($changes['break_events'] as $id) {
            RosterEvents::where('roster_id', $roster->id)->find($id)?->delete();
        }

        return redirect()->route('roster.autoPlan', $roster->id)->with(['type' => 'success', 'Meldung' => 'Auto-Umplanung rückgängig gemacht.']);
    }

    // =========================================================================

    private function naechsteFreieWoche(Group $abteilung): Carbon
    {
        $woche = Carbon::now()->next(Carbon::MONDAY);
        $belegt = $abteilung->rosters()->where('type', 'normal')->pluck('start_date')->map(fn ($d) => Carbon::parse($d)->toDateString());

        for ($i = 0; $i < 52 && $belegt->contains($woche->toDateString()); $i++) {
            $woche->addWeek();
        }

        return $woche;
    }

    /**
     * Abteilungs-Checks (z. B. "mind. 2 Personen ab 7:00") je Tag auswerten.
     *
     * @return array<string, array<string, bool>>
     */
    private function checks(Roster $roster, $arbeitszeiten, $termine): array
    {
        $ergebnis = [];
        foreach ($roster->days() as $tag) {
            $ergebnis[$tag->toDateString()] = [];
        }

        foreach ($roster->department->roster_checks->sortBy('weekday') as $check) {
            $tag = $roster->start_date->copy()->startOfDay()->addDays($check->weekday);
            $datum = $tag->toDateString();

            if ($check->type === WorkingTime::class) {
                if ($check->field_name === 'function') {
                    $treffer = $arbeitszeiten->filter(fn ($w) => $w->date->toDateString() === $datum && $w->function == $check->value);
                } else {
                    try {
                        $ziel = Carbon::createFromFormat('Y-m-d H:i', $datum.' '.substr((string) $check->value, 0, 5));
                    } catch (\Throwable) {
                        continue;
                    }
                    $feld = in_array($check->field_name, ['start', 'end'], true) ? $check->field_name : 'start';
                    $treffer = $arbeitszeiten->filter(function ($w) use ($datum, $feld, $check, $ziel) {
                        if ($w->date->toDateString() !== $datum || $w->{$feld} === null) {
                            return false;
                        }

                        return match ($check->operator) {
                            '<=' => $w->{$feld}->lte($ziel),
                            '<' => $w->{$feld}->lt($ziel),
                            '=' => $w->{$feld}->eq($ziel),
                            '>=' => $w->{$feld}->gte($ziel),
                            '>' => $w->{$feld}->gt($ziel),
                            default => false,
                        };
                    });
                }
            } elseif ($check->type === RosterEvents::class) {
                $treffer = $termine->filter(fn ($e) => $e->date->toDateString() === $datum && $e->event == $check->value);
            } else {
                continue;
            }

            $ergebnis[$datum][$check->check_name] = $treffer->count() >= $check->needs;
        }

        return $ergebnis;
    }
}
