<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreZeitrasterRequest;
use App\Http\Requests\UpdateZeitrasterRequest;
use App\Models\LessonTime;
use App\Models\Zeitraster;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ZeitrasterController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:manage zeitraster');
    }

    /**
     * Liste aller Zeitraster mit Zählung von Klassen und LessonTimes.
     */
    public function index(): View
    {
        $zeitraster = Zeitraster::withCount(['klassen', 'lessonTimes'])
            ->orderByDesc('ist_standard')
            ->orderBy('name')
            ->get();

        return view('zeitraster.index', compact('zeitraster'));
    }

    /**
     * Formular: Neues Zeitraster anlegen.
     */
    public function create(): View
    {
        return view('zeitraster.create');
    }

    /**
     * Neues Zeitraster speichern inkl. LessonTimes.
     */
    public function store(StoreZeitrasterRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $zeitraster = Zeitraster::create([
            'name'         => $data['name'],
            'beschreibung' => $data['beschreibung'] ?? null,
            'ist_standard' => false,
        ]);

        $this->syncLessonTimes($zeitraster, $data['stunden'] ?? []);

        if (!empty($data['ist_standard'])) {
            $zeitraster->markAlsStandard();
        }

        return redirectBack('success', 'Zeitraster "' . $zeitraster->name . '" wurde angelegt.');
    }

    /**
     * Formular: Zeitraster bearbeiten.
     */
    public function edit(Zeitraster $zeitraster): View
    {
        $zeitraster->load('lessonTimes');
        return view('zeitraster.edit', compact('zeitraster'));
    }

    /**
     * Zeitraster aktualisieren inkl. LessonTimes ersetzen.
     */
    public function update(UpdateZeitrasterRequest $request, Zeitraster $zeitraster): RedirectResponse
    {
        $data = $request->validated();

        $zeitraster->update([
            'name'         => $data['name'],
            'beschreibung' => $data['beschreibung'] ?? null,
        ]);

        $this->syncLessonTimes($zeitraster, $data['stunden'] ?? []);

        if (!empty($data['ist_standard'])) {
            $zeitraster->markAlsStandard();
        }

        return redirectBack('success', 'Zeitraster "' . $zeitraster->name . '" wurde aktualisiert.');
    }

    /**
     * Zeitraster löschen (nur wenn nicht Standard und keine Klassen zugeordnet).
     */
    public function destroy(Zeitraster $zeitraster): RedirectResponse
    {
        if ($zeitraster->ist_standard) {
            return redirectBack('warning', 'Das Standard-Zeitraster kann nicht gelöscht werden.');
        }

        if ($zeitraster->klassen()->exists()) {
            return redirectBack('warning',
                'Das Zeitraster "' . $zeitraster->name . '" kann nicht gelöscht werden, '
                . 'da noch Klassen zugeordnet sind.'
            );
        }

        $name = $zeitraster->name;
        $zeitraster->lessonTimes()->delete();
        $zeitraster->delete();

        return redirectBack('success', 'Zeitraster "' . $name . '" wurde gelöscht.');
    }

    /**
     * Zeitraster als Standard setzen.
     */
    public function markStandard(Zeitraster $zeitraster): RedirectResponse
    {
        $zeitraster->markAlsStandard();
        return redirectBack('success', '"' . $zeitraster->name . '" wurde als Standard-Zeitraster gesetzt.');
    }

    // ─── Hilfsmethoden ───────────────────────────────────────────────────────

    /**
     * Löscht alle LessonTimes des Zeitrasters und legt neue an.
     */
    private function syncLessonTimes(Zeitraster $zeitraster, array $stunden): void
    {
        $zeitraster->lessonTimes()->delete();

        foreach ($stunden as $stunde) {
            if (empty($stunde['period']) || empty($stunde['start']) || empty($stunde['end'])) {
                continue;
            }

            LessonTime::create([
                'zeitraster_id' => $zeitraster->id,
                'period'        => (int) $stunde['period'],
                'start'         => $stunde['start'],
                'end'           => $stunde['end'],
                'week'          => ($stunde['week'] ?? '') ?: null,
            ]);
        }
    }
}

