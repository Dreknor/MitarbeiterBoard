<?php

namespace App\Http\Controllers\Personal;

use App\Enums\AnomalyRuleType;
use App\Http\Controllers\Controller;
use App\Http\Requests\personal\createTimesheetDayRequest;
use App\Http\Requests\updateTimesheetDayRequest;
use App\Mail\SendMonthlyTimesheetMail;
use App\Models\personal\Timesheet;
use App\Models\personal\TimesheetAnomaly;
use App\Models\personal\TimesheetDays;
use App\Models\User;
use App\Notifications\Push;
use App\Services\Personal\TimeValidationService;
use App\Services\Personal\Zeit\TimesheetService;
use App\Services\Personal\Zeit\ZeitZugriff;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Arbeitszeitnachweise. Rechte: TimesheetPolicy, Logik: TimesheetService.
 * Routen mit {user}/{timesheet} sind per scopeBindings() verknüpft – ein fremder
 * Nachweis kann nicht über die eigene Benutzer-ID angesprochen werden.
 */
class TimesheetController extends Controller
{
    public function __construct(
        private readonly TimesheetService $timesheets,
        private readonly TimeValidationService $validationService,
        private readonly ZeitZugriff $zugriff,
    ) {
    }

    public function index(Request $request)
    {
        $actor = $request->user();

        $mitarbeitende = User::whereHas('employments')
            ->with(['employments', 'timesheets' => fn ($q) => $q->orderByDesc('year')->orderByDesc('month')])
            ->orderBy('name')
            ->get()
            ->filter(fn (User $u) => $u->id !== $actor->id && $u->can('has timesheet') && $this->zugriff->verwaltetNachweiseVon($actor, $u))
            ->values();

        if ($mitarbeitende->isEmpty()) {
            abort_unless($actor->can('has timesheet'), 403);

            return redirect()->route('timesheets.show', $actor->id);
        }

        $vormonat = now()->subMonth();

        return view('personal.timesheets.selectEmploye', [
            'employes' => $mitarbeitende,
            'vormonat' => $vormonat,
            'eigener' => $actor->can('has timesheet'),
        ]);
    }

    public function show(Request $request, User $user, $date = null)
    {
        $this->authorize('viewEmploye', [Timesheet::class, $user]);

        if ($user->employments()->count() < 1 && $user->timesheets()->count() < 1) {
            return redirectBack('warning', 'Für '.$user->name.' ist keine Anstellung eingetragen.');
        }

        $monat = $date ? Carbon::createFromFormat('Y-m-d', $date.'-01')->startOfDay() : Carbon::today()->startOfMonth();
        if ($monat->gt(Carbon::today()->startOfMonth()->addMonth())) {
            return redirect()->route('timesheets.show', [$user->id, Carbon::today()->format('Y-m')]);
        }

        $timesheet = $this->timesheets->forMonth($user, $monat);
        if (!$timesheet->is_locked) {
            $this->timesheets->bisherigBefuellen($timesheet);
            $this->timesheets->syncMonat($timesheet);
            if ($request->user()->can('edit', $timesheet)) {
                $this->timesheets->planAutomatisch($timesheet);
            }
            $this->timesheets->recalculate($timesheet, false);
            $timesheet->refresh();
        }

        $ersterMonat = $user->employments()->min('start');
        $monate = [];
        for ($m = Carbon::today()->startOfMonth()->addMonth(); $ersterMonat && $m->gte(Carbon::parse($ersterMonat)->startOfMonth()) && count($monate) < 120; $m->subMonth()) {
            $monate[] = $m->copy();
        }

        $fehlend = TimesheetAnomaly::forEmploye($user->id)
            ->forPeriod($monat->month, $monat->year)
            ->where('rule_type', AnomalyRuleType::MissingClockOut->value)
            ->whereDate('date', '<=', Carbon::today()->toDateString())
            ->unresolved()
            ->orderBy('date')
            ->get();

        return view('personal.timesheets.timesheet', [
            'employe' => $user,
            'timesheet' => $timesheet,
            'timesheet_old' => $this->timesheets->vorgaenger($timesheet),
            'zeilen' => $this->timesheets->monatsZeilen($timesheet),
            'month' => $monat,
            'monate' => $monate,
            'missingEntries' => $fehlend,
            'abwesenheitsGruende' => config('config.abwesenheiten_arbeitszeit', []),
            'istEigener' => $request->user()->id === $user->id,
            'eingefroren' => $this->timesheets->istEingefroren($timesheet) && !$timesheet->is_locked,
            'altesModell' => $this->timesheets->istHistorisch($timesheet),
        ]);
    }

    public function addDay(User $user, Timesheet $timesheet, string $date)
    {
        $this->authorize('edit', $timesheet);
        $tag = $this->tag($timesheet, $date);

        if ($tag->gt(Carbon::today())) {
            return redirectBack('warning', 'Arbeitszeiten können nicht für zukünftige Tage eingetragen werden.');
        }

        return view('personal.timesheets.addDay', [
            'day' => $tag,
            'user' => $user,
            'timesheet' => $timesheet,
            'suggestion' => $this->timesheets->planFuerZeitraum($user, $tag, $tag)->get($tag->toDateString()),
        ]);
    }

    public function storeDay(createTimesheetDayRequest $request, User $user, Timesheet $timesheet, string $date)
    {
        $this->authorize('edit', $timesheet);
        $tag = $this->tag($timesheet, $date);

        $this->timesheets->buchen($timesheet, $tag, $request->validated());
        $this->clearMissingClockOutAnomaly($timesheet, $tag);

        return $this->zurueck($user, $tag, 'Arbeitszeit gespeichert.');
    }

    public function addFromAbsence(Request $request, User $user, Timesheet $timesheet, string $date)
    {
        $this->authorize('edit', $timesheet);
        $tag = $this->tag($timesheet, $date);
        $data = $request->validate(['absence' => ['required', 'string', 'max:60']]);

        $this->timesheets->gutschreiben($timesheet, $tag, $data['absence']);
        $this->clearMissingClockOutAnomaly($timesheet, $tag);

        return $this->zurueck($user, $tag, $data['absence'].' eingetragen.');
    }

    public function applyRosterSuggestion(User $user, Timesheet $timesheet, string $date)
    {
        $this->authorize('edit', $timesheet);
        $tag = $this->tag($timesheet, $date);

        $zeile = $this->timesheets->planUebernehmen($timesheet, $tag);
        if ($zeile === null) {
            return redirectBack('warning', 'Für diesen Tag liegen keine Dienstplanzeiten vor.');
        }
        $this->clearMissingClockOutAnomaly($timesheet, $tag);

        return $this->zurueck($user, $tag, 'Dienstplanzeiten übernommen ('.$zeile->start->format('H:i').'–'.$zeile->end->format('H:i').' Uhr).');
    }

    public function applyRosterMonth(User $user, Timesheet $timesheet)
    {
        $this->authorize('edit', $timesheet);
        $anzahl = $this->timesheets->planUebernehmenMonat($timesheet);

        return redirectBack($anzahl > 0 ? 'success' : 'info', $anzahl > 0
            ? $anzahl.' Tag(e) aus dem Dienstplan übernommen.'
            : 'Keine offenen Tage mit Dienstplanzeiten gefunden.');
    }

    public function editDay(TimesheetDays $timesheetDay)
    {
        $this->authorize('edit', $timesheetDay->timesheet);
        abort_if($timesheetDay->is_credit, 404);

        return view('personal.timesheets.editDay', [
            'timesheet_day' => $timesheetDay,
            'timesheet' => $timesheetDay->timesheet,
            'day' => $timesheetDay->date,
        ]);
    }

    public function updateDay(updateTimesheetDayRequest $request, TimesheetDays $timesheetDay)
    {
        $timesheet = $timesheetDay->timesheet;
        $this->authorize('edit', $timesheet);
        abort_if($timesheetDay->is_credit, 404);

        $this->timesheets->aendern($timesheetDay, $request->validated());

        return $this->zurueck($timesheet->employe, $timesheetDay->date, 'Eintrag aktualisiert.');
    }

    public function deleteDay(TimesheetDays $timesheetDay)
    {
        $timesheet = $timesheetDay->timesheet;
        $this->authorize('edit', $timesheet);

        $tag = $timesheetDay->date->copy();
        $this->timesheets->loeschen($timesheetDay);
        $this->clearMissingClockOutAnomaly($timesheet, $tag);

        return $this->zurueck($timesheet->employe, $tag, 'Eintrag gelöscht.');
    }

    public function updateSheet(Request $request, User $user, Timesheet $timesheet)
    {
        $this->authorize('edit', $timesheet);

        $this->timesheets->syncMonat($timesheet);
        $this->timesheets->recalculate($timesheet, true, true);
        $this->validationService->runForEmployee($user, $timesheet->monthStart(), $request->user(), false);

        return redirectBack('success', 'Nachweis neu berechnet und geprüft.');
    }

    public function updateTimesheets(Request $request, User $user)
    {
        abort_unless($this->zugriff->verwaltetNachweiseVon($request->user(), $user), 403);

        // Ausdrückliche Neuberechnung aller offenen Monate der Reihe nach (wie bisher)
        foreach ($user->timesheets()->whereNull('locked_at')->orderBy('year')->orderBy('month')->get() as $ts) {
            if ($ts->monthStart()->gt(now()->endOfMonth())) {
                break;
            }
            $this->timesheets->recalculate($ts, false, true);
        }

        return redirectBack('success', 'Stundenkonto neu berechnet.');
    }

    // ---- Workflow ----

    public function submit(Request $request, User $user, Timesheet $timesheet)
    {
        $this->authorize('submit', $timesheet);
        $this->timesheets->einreichen($timesheet, $request->user());

        return redirectBack('success', 'Nachweis eingereicht. Die prüfende Person wird benachrichtigt.');
    }

    public function lock(Request $request, User $user, Timesheet $timesheet)
    {
        $this->authorize('lock', $timesheet);
        $this->timesheets->abschliessen($timesheet, $request->user());

        return redirectBack('success', 'Nachweis bestätigt und abgeschlossen.');
    }

    public function returnToEmploye(Request $request, User $user, Timesheet $timesheet)
    {
        $this->authorize('returnToEmploye', $timesheet);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->timesheets->zurueckgeben($timesheet, $request->user(), $data['reason']);

        return redirectBack('success', 'Nachweis zur Korrektur zurückgegeben.');
    }

    public function unlock(User $user, Timesheet $timesheet)
    {
        $this->authorize('unlock', $timesheet);
        $this->timesheets->entsperren($timesheet);

        return redirectBack('success', 'Sperre aufgehoben – der Nachweis kann wieder bearbeitet werden.');
    }

    // ---- Übersicht & Export ----

    public function overviewTimesheetsUser(User $user)
    {
        $this->authorize('viewEmploye', [Timesheet::class, $user]);

        return view('personal.timesheets.overview', [
            'user' => $user,
            'timesheets' => $user->timesheets()->with('lockedBy')->orderByDesc('year')->orderByDesc('month')->get(),
        ]);
    }

    public function export(User $user, Timesheet $timesheet)
    {
        $this->authorize('view', $timesheet);

        if ($timesheet->monthStart()->gt(Carbon::today())) {
            return redirectBack('warning', 'Dieser Monat liegt in der Zukunft.');
        }

        return $this->pdf($timesheet)->download($this->dateiname($timesheet));
    }

    /**
     * Monatliche Mail mit dem Nachweis des Vormonats (Scheduler).
     */
    public function timesheet_mail()
    {
        $monat = Carbon::now()->subMonth()->startOfMonth();

        foreach (User::whereHas('timesheets', fn ($q) => $q->where('year', $monat->year)->where('month', $monat->month))->get() as $user) {
            if (!$user->can('has timesheet') || !$user->employe_data?->mail_timesheet) {
                continue;
            }

            $timesheet = $user->timesheets()->where('year', $monat->year)->where('month', $monat->month)->first();
            $pfad = storage_path('app/tmp/azn_'.$user->id.'_'.Str::random(16).'.pdf');

            try {
                File::ensureDirectoryExists(dirname($pfad));
                $this->pdf($timesheet)->save($pfad, true);

                $mail = Mail::to($user->email);
                $superior = $user->superior;
                if ($superior?->email) {
                    $mail->cc($superior->email);
                }
                $mail->send(new SendMonthlyTimesheetMail($user, $monat, $pfad));
            } catch (\Throwable $e) {
                Log::error('Fehler beim Versenden des Arbeitszeitnachweises', ['user' => $user->id, 'exception' => $e->getMessage()]);
                User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->first()?->notify(new Push('Fehler beim Versenden des Arbeitszeitnachweises', 'Arbeitszeitnachweis für '.$user->name.' ('.$monat->format('m/Y').') konnte nicht versendet werden.', 'system'));
            } finally {
                File::delete($pfad);
            }
        }
    }

    // =========================================================================

    private function pdf(Timesheet $timesheet)
    {
        return PDF::loadView('personal.timesheets.pdf', [
            'timesheet' => $timesheet,
            'timesheet_old' => $this->timesheets->vorgaenger($timesheet),
            'zeilen' => $this->timesheets->monatsZeilen($timesheet, false),
            'employe' => $timesheet->employe,
            'month' => $timesheet->monthStart(),
        ]);
    }

    private function dateiname(Timesheet $timesheet): string
    {
        return 'AZN_'.Str::slug($timesheet->employe->familienname ?? $timesheet->employe->name).'_'.$timesheet->year.'_'.str_pad((string) $timesheet->month, 2, '0', STR_PAD_LEFT).'.pdf';
    }

    private function tag(Timesheet $timesheet, string $date): Carbon
    {
        try {
            $tag = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (\Throwable) {
            abort(404);
        }

        abort_if($tag->year !== (int) $timesheet->year || $tag->month !== (int) $timesheet->month, 404);

        return $tag;
    }

    private function zurueck(User $user, Carbon $tag, string $meldung)
    {
        return redirect(route('timesheets.show', [$user->id, $tag->format('Y-m')]).'#tag-'.$tag->toDateString())
            ->with(['type' => 'success', 'Meldung' => $meldung]);
    }

    private function clearMissingClockOutAnomaly(Timesheet $timesheet, Carbon $day): void
    {
        TimesheetAnomaly::forEmploye($timesheet->employe_id)
            ->forPeriod($day->month, $day->year)
            ->whereDate('date', $day->toDateString())
            ->where('rule_type', AnomalyRuleType::MissingClockOut->value)
            ->unresolved()
            ->delete();
    }
}
