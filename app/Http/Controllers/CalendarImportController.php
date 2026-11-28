<?php

namespace App\Http\Controllers;

use App\Models\OxCalendar;
use App\Services\Calendar\IcsImportService;
use App\Services\Calendar\TerminVerbundService;
use App\Services\OxCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * ICS-Import: Datei hochladen → Vorschau mit Auswahl & Hinweisen → Import nach OX.
 */
class CalendarImportController extends Controller
{
    public function __construct(
        protected IcsImportService $import,
        protected OxCalendarService $ox,
    ) {
    }

    /**
     * Datei entgegennehmen, analysieren und zur Vorschau weiterleiten.
     */
    public function vorschau(Request $request)
    {
        $request->validate([
            'datei' => 'required|file|max:5120',
        ], [
            'datei.required' => 'Bitte eine .ics-Datei auswählen.',
            'datei.max'      => 'Die Datei darf höchstens 5 MB groß sein.',
        ]);

        $datei = $request->file('datei');
        if (strtolower($datei->getClientOriginalExtension()) !== 'ics') {
            throw ValidationException::withMessages(['datei' => 'Bitte eine Datei mit der Endung .ics hochladen.']);
        }

        try {
            $eintraege = $this->import->analysieren((string) file_get_contents($datei->getPathname()));
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['datei' => $e->getMessage()]);
        }

        $token = $this->import->vorschauSpeichern(auth()->user(), $datei->getClientOriginalName(), $eintraege);

        return redirect()->route('calendar.import.show', $token);
    }

    /**
     * Vorschau: Überblick über alle Termine der Datei, Auswahl und Hinweise.
     */
    public function show(string $token)
    {
        $user     = auth()->user();
        $vorschau = $this->import->vorschauLaden($user, $token);

        if (!$vorschau) {
            return redirect()->route('calendar.index')->with([
                'type'    => 'warning',
                'Meldung' => 'Die Import-Vorschau ist abgelaufen. Bitte die Datei erneut hochladen.',
            ]);
        }

        $kalender  = $this->schreibbareKalender();
        $eintraege = $this->import->duplikateMarkieren($vorschau['eintraege'], $kalender);

        return view('calendar.import', [
            'token'       => $token,
            'dateiname'   => $vorschau['dateiname'],
            'eintraege'   => $eintraege,
            'kalender'    => $kalender,
            'gesperrt'    => $this->gesperrteKalender(),
            'maxAuswahl'  => IcsImportService::MAX_AUSWAHL,
        ]);
    }

    /**
     * Ausgewählte Termine importieren.
     */
    public function store(Request $request, string $token)
    {
        $user     = auth()->user();
        $vorschau = $this->import->vorschauLaden($user, $token);

        if (!$vorschau) {
            return redirect()->route('calendar.index')->with([
                'type'    => 'warning',
                'Meldung' => 'Die Import-Vorschau ist abgelaufen. Bitte die Datei erneut hochladen.',
            ]);
        }

        $validated = $request->validate([
            'kalender_ids'   => 'required|array|min:1|max:20',
            'kalender_ids.*' => 'integer|distinct|exists:ox_calendars,id',
            'auswahl'        => 'required|array|min:1|max:' . IcsImportService::MAX_AUSWAHL,
            'auswahl.*'      => 'integer|min:0',
            'hinweis_alle'   => 'nullable|string|max:1000',
            'hinweise'       => 'nullable|array',
            'hinweise.*'     => 'nullable|string|max:1000',
        ], [
            'kalender_ids.required' => 'Bitte mindestens einen Zielkalender auswählen.',
            'auswahl.required'      => 'Bitte mindestens einen Termin für den Import auswählen.',
            'auswahl.max'           => 'Pro Import können höchstens :max Termine übernommen werden.',
        ]);

        $kalender = OxCalendar::whereIn('id', $validated['kalender_ids'])->with('groups')->get();
        foreach ($kalender as $kal) {
            if ($this->ox->gesperrtDurchOx($user, $kal)) {
                throw ValidationException::withMessages([
                    'kalender_ids' => '„' . $kal->name . '“: ' . OxCalendar::OX_SPERRE_HINWEIS,
                ]);
            }
            if (!$this->ox->canWriteCalendar($user, $kal)) {
                abort(403, 'Keine Schreibberechtigung für den Kalender "' . $kal->name . '".');
            }
        }

        // Viele CalDAV-PUTs in einem Request – Laufzeit großzügig bemessen
        @set_time_limit(600);

        $ergebnis = $this->import->importieren(
            $user,
            $vorschau['eintraege'],
            $validated['auswahl'],
            $validated['hinweise'] ?? [],
            $validated['hinweis_alle'] ?? null,
            $kalender
        );

        $sperrText = $this->sperrText($ergebnis['gesperrt']);

        if ($ergebnis['importiert'] === 0) {
            return redirect()->back()->withInput()->with([
                'type'    => 'danger',
                'Meldung' => 'Es konnte kein Termin importiert werden. '
                    . trim($sperrText . ' ' . TerminVerbundService::fehlerText($ergebnis['fehlgeschlagen'])),
            ]);
        }

        $this->import->vorschauVerwerfen($user, $token);

        $text = $ergebnis['importiert'] . ' Termin(e) aus "' . $vorschau['dateiname'] . '" importiert'
            . ' (' . $kalender->pluck('name')->implode(', ') . ').';

        if (!empty($ergebnis['fehlgeschlagen']) || $sperrText !== '') {
            $nicht = TerminVerbundService::fehlerText($ergebnis['fehlgeschlagen']);

            return redirect()->route('calendar.index')->with([
                'type'    => 'warning',
                'Meldung' => trim($text . ' ' . $sperrText . ($nicht !== '' ? ' Nicht übernommen: ' . $nicht : '')),
            ]);
        }

        return redirect()->route('calendar.index')->with(['type' => 'success', 'Meldung' => $text]);
    }

    /**
     * Ein Satz pro Kalender, den OX während des Imports gesperrt hat.
     *
     * @param  array<int, string>  $namen
     */
    protected function sperrText(array $namen): string
    {
        if ($namen === []) {
            return '';
        }

        $liste = collect($namen)->map(fn ($n) => '„' . $n . '“')->implode(', ');

        return 'In ' . $liste . ' wurde nichts eingetragen: ' . OxCalendar::OX_SPERRE_HINWEIS;
    }

    /**
     * Kalender, in die die Person schreiben dürfte, die OX aber nur lesen lässt.
     *
     * @return Collection<int, OxCalendar>
     */
    protected function gesperrteKalender(): Collection
    {
        $user = auth()->user();

        return $this->ox->sichtbareKalender($user)
            ->filter(fn (OxCalendar $cal) => $this->ox->gesperrtDurchOx($user, $cal))
            ->values();
    }

    /**
     * @return Collection<int, OxCalendar>
     */
    protected function schreibbareKalender(): Collection
    {
        $user = auth()->user();

        return $this->ox->sichtbareKalender($user)
            ->filter(fn (OxCalendar $cal) => $this->ox->canWriteCalendar($user, $cal))
            ->values();
    }
}
