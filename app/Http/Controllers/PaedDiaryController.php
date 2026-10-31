<?php
namespace App\Http\Controllers;

use App\Models\GradingStage;
use App\Models\PaedDiaryCategory;
use App\Models\SchuelerGradingHistory;
use App\Models\Klasse;
use App\Models\PaedDiaryColumn;
use App\Models\PaedDiaryColumnValue;
use App\Models\PaedDiaryEntry;
use App\Models\PaedDiaryTask;
use App\Models\PaedDiaryAppointment;
use App\Models\Schueler;
use App\Models\PaedDiaryClassGroup;
use App\Models\PaedDiaryClassDayPause;
use App\Models\PaedDiaryEntryPause;
use App\Models\PaedDiaryGoal;
use App\Exports\PaedDiaryExport;
use App\Exports\PaedDiarySchuelerExport;
use App\Exports\PaedDiaryWeekTableExport;
use App\Models\PaedDiarySchuelerAbsence;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Exception;

class PaedDiaryController extends Controller
{
    /**
     * Pädagogisches Tagebuch v2 (Blade + Alpine.js Frontend).
     * Identische Datenvorbereitung wie index(), rendert aber die v2-View.
     *
     * @param Request $request
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function indexV2(Request $request)
    {
        $user = Auth::user();
        $klassen = $user->paed_klassen()->withCount('schueler')->orderBy('name')->get();
        if ($klassen->isEmpty()) {
            return redirect()->back()->with(['type' => 'warning', 'Meldung' => 'Keine Klassen zugewiesen.']);
        }

        $groups = Auth::user()->paed_diary_class_groups()->with('klassen:id,name')->get();

        $klasse = null;
        $selectedGroup = null;

        if ($request->filled('group')) {
            $selectedGroup = $groups->firstWhere('id', (int)$request->get('group'));
            if ($selectedGroup) {
                $klasse = $klassen->firstWhere('id', $selectedGroup->klassen->first()?->id) ?? $klassen->first();
            }
        } elseif ($request->filled('klasse')) {
            $klasse = $klassen->firstWhere('id', (int)$request->get('klasse'));
        }

        if (!$klasse && !$selectedGroup) {
            if ($groups->isNotEmpty()) {
                $selectedGroup = $groups->first();
                $klasse = $klassen->firstWhere('id', $selectedGroup->klassen->first()?->id) ?? $klassen->first();
            } else {
                $klasse = $klassen->first();
            }
        }

        if (!$klasse) {
            $klasse = $klassen->first();
        }

        // Bisherige und neue Ansicht laufen parallel; die Person wählt selbst (paed_diary_darstellung)
        $darstellung = $user->paedDiaryDarstellung();

        return view($darstellung['ansicht'] === 'neu' ? 'paedDiary.neu.index' : 'paedDiary.v2.index', [
            'klassen' => $klassen,
            'klasse' => $klasse,
            'groups' => $groups,
            'selectedGroup' => $selectedGroup,
            'darstellung' => $darstellung,
        ]);
    }

    /**
     * POST /paed-diary/settings/darstellung – persönliche Darstellung speichern (einzelne Werte).
     * Formular-Aufrufe (Ansicht wechseln, Hinweis ausblenden) kehren zur vorherigen Seite zurück.
     */
    public function updateDarstellung(Request $request)
    {
        $data = $request->validate([
            'ansicht' => ['sometimes', 'in:bisher,neu'],
            'layout' => ['sometimes', 'in:klassisch,kompakt,karten'],
            'schrift' => ['sometimes', 'in:standard,gross'],
            'kategorie_farben' => ['sometimes', 'boolean'],
            'pausierte' => ['sometimes', 'boolean'],
            'bewertung' => ['sometimes', 'in:schuelerweise,matrix'],
            'schueler_zeitraum' => ['sometimes', 'in:30tage,schuljahr'],
            'hinweis_ausgeblendet' => ['sometimes', 'boolean'],
            'tour_gesehen' => ['sometimes', 'boolean'],
        ]);

        $user = Auth::user();
        foreach (['kategorie_farben', 'pausierte', 'hinweis_ausgeblendet', 'tour_gesehen'] as $schalter) {
            if (array_key_exists($schalter, $data)) {
                $data[$schalter] = $request->boolean($schalter);
            }
        }
        // Wer die neue Ansicht einmal gewählt hat, braucht den Hinweis darauf nicht mehr
        if (($data['ansicht'] ?? null) === 'neu') {
            $data['hinweis_ausgeblendet'] = true;
        }

        $user->paed_diary_darstellung = array_merge($user->paedDiaryDarstellung(), $data);
        $user->save();

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'darstellung' => $user->paedDiaryDarstellung()]);
        }

        $meldung = match ($data['ansicht'] ?? null) {
            'neu' => 'Die neue Ansicht ist aktiv. Über „Bisherige Ansicht“ kannst du jederzeit zurückwechseln.',
            'bisher' => 'Die bisherige Ansicht ist wieder aktiv.',
            default => null,
        };

        return $meldung ? redirectBack('success', $meldung) : redirect()->back();
    }

    /**
     * Liefert alle relevanten Wochen-Daten (Tage, Schüler, Einträge, Spalten, Spaltenwerte, offene Aufgaben).
     * Wird via AJAX vom Frontend abgefragt.
     *
     * @param Request $request {klasse_id:int, week_start?:date}
     * @return \Illuminate\Http\JsonResponse
     */
    public function weekData(Request $request)
    {
        $request->validate([
            'klasse_id' => ['nullable', 'integer', 'exists:klassen,id'],
            'group_id' => ['nullable', 'integer', 'exists:paed_diary_class_groups,id'],
            'week_start' => ['nullable', 'date']
        ]);
        if (!$request->filled('klasse_id') && !$request->filled('group_id')) {
            return response()->json(['message' => 'klasse_id oder group_id erforderlich'], 422);
        }
        $user = Auth::user();
        $isGroup = $request->filled('group_id');
        $group = null;
        $klasse = null;
        $klassen = collect();
        if ($isGroup) {
            $group = PaedDiaryClassGroup::with('klassen:id,name,kuerzel,color')->where('id', $request->group_id)->where('user_id', $user->id)->firstOrFail();
            $userKlassenIds = $user->paed_klassen()->pluck('klassen.id');
            $klassen = $group->klassen->whereIn('id', $userKlassenIds)->values();
            if ($klassen->isEmpty()) {
                return response()->json(['message' => 'Keine Klassen in Gruppe verfügbar'], 422);
            }
        } else {
            $klasse = $user->paed_klassen()->where('klassen.id', $request->klasse_id)->firstOrFail();
            $klassen = collect([$klasse]);
        }
        $weekStart = $request->filled('week_start') ? Carbon::parse($request->week_start)->startOfWeek() : Carbon::now()->startOfWeek();

        // Laden inkl. Ferien-/Tagespausen-Automatik – gemeinsam mit API v1 (Pädagogen-App)
        $calendar = app(\App\Services\PaedDiaryCalendarService::class);
        $week = $calendar->loadWeek($klassen, $weekStart);
        $periodEnd = $week['week_end'];
        $schueler = $week['schueler'];
        $schuelerIds = $schueler->pluck('id');
        $columns = $week['columns'];
        $entries = $week['entries'];
        $classDayPauseRecords = $week['class_day_pauses'];

        $entryData = $entries->map(fn($e) => [
            'id' => $e->id, 'date' => $e->datum->toDateString(), 'content' => $e->content,
            'schueler_ids' => $e->schueler->pluck('id'), 'user' => $e->user?->name,
            'completed_at' => $e->completed_at,
            'klasse_id' => $e->klasse_id,
            'category_id' => $e->category_id,
            'category' => (is_object($e->category)) ? $e->category->name : null,
            'category_color' => $e->category?->color,
            'dossier_only' => (bool) $e->dossier_only,
        ]);

        // Alle offenen Notizen laden (unabhängig vom Datum, auch aus vorhergehenden Wochen/Schuljahren).
        // Ebenfalls schülerbasiert statt klasse_id-basiert (Begründung s. PaedDiaryCalendarService).
        $allOpenEntries = PaedDiaryEntry::with(['schueler:id', 'user:id,name', 'category:id,name,color'])
            ->whereHas('schueler', fn($q) => $q->whereIn('schueler.id', $schuelerIds))
            ->whereNull('completed_at')
            ->where('dossier_only', false)
            ->where('datum', '<=', $periodEnd->toDateString()) // Nur Notizen bis zum Ende der aktuellen Woche
            ->get();
        $openEntries = $allOpenEntries->map(fn($e) => [
            'id' => $e->id,
            'schueler_ids' => $e->schueler->pluck('id'),
            'date' => $e->datum->toDateString(),
            'content' => $e->content,
            'user' => $e->user?->name,
            'klasse_id' => $e->klasse_id,
            'category_id' => $e->category_id,
            'category' => (is_object($e->category)) ? $e->category->name : null,
            'category_color' => $e->category?->color
        ])->values();
        // Spaltenwerte wurden bereits schülerbasiert geladen (inkl. Historie) - hier nur gruppieren.
        $valuesGrouped = [];
        foreach ($week['column_values'] as $v) {
            $valuesGrouped[$v->paed_diary_column_id][$v->schueler_id][$v->datum->toDateString()] = $v->value;
        }
        $tasks = $week['tasks']->map(fn($t) => [
            'id' => $t->id, 'schueler_id' => $t->schueler_id, 'title' => $t->title,
            'description' => $t->description,
            'due_date' => $t->due_date?->toDateString(), 'highlighted' => $t->highlighted, 'klasse_id' => $t->klasse_id,
        ]);
        $pauses = $week['pauses']->map(fn($p)=>[
            'entry_id'=>$p->paed_diary_entry_id,
            'schueler_id'=>$p->schueler_id,
            'date'=>$p->date->toDateString(),
        ]);

        // Kategorien (global + user-spezifisch)
        $categories = [];
        try{
            $categories = PaedDiaryCategory::where(function($q) use ($user){ $q->whereNull('user_id')->orWhere('user_id', $user->id); })->orderBy('name')->get()->map(fn($c)=>['id'=>$c->id,'name'=>$c->name,'color'=>$c->color]);
        }catch(\Throwable $_){ $categories = []; }

        // Benutzereinstellung für Kategorieanzeige
        $showColumnCategories = (bool) $user->show_column_categories;

        // Ausgeblendete Kategorien des Users laden (TODO 8)
        $hiddenCategoryIds = [];
        try {
            $hiddenCategoryIds = $user->hiddenPaedDiaryCategories()
                ->pluck('paed_diary_categories.id')
                ->toArray();
        } catch (\Throwable $_) {
            $hiddenCategoryIds = [];
        }

        $absenceAlerts = $calendar->absenceAlertsFor($schueler->pluck('id')->all());

        return response()->json([
            'is_group' => $isGroup,
            'group' => $isGroup ? ['id' => $group->id, 'name' => $group->name] : null,
            'days' => $week['days'],
            'schueler' => $schueler->map(fn($s) => [
                'id' => $s->id, 'name' => $s->vorname . ' ' . $s->nachname,
                'klasse_id' => $s->klasse_id,
                'klasse_name' => $klassen->firstWhere('id', $s->klasse_id)?->name,
                'stage' => $s->grading_stage ? ['id' => $s->grading_stage->id, 'name' => $s->grading_stage->name, 'symbol' => $s->grading_stage->symbol, 'sort_order' => $s->grading_stage->sort_order, 'image_url' => $s->grading_stage->image_url] : null,
                'absence_alerts' => $absenceAlerts[$s->id] ?? [],
            ]),
            'klassen' => $klassen->map(fn($k) => ['id' => $k->id, 'name' => $k->name, 'kuerzel' => $k->kuerzel, 'color' => $k->color]),
            'can_manage_grading' => Auth::user()->canChangeGradingStage(),
            'entries' => $entryData,
            'open_entries' => $openEntries,
            'columns' => $columns->map(fn($c) => [
                'id' => $c->id, 'name' => $c->name, 'slug' => $c->slug, 'type' => $c->type, 'klasse_id' => $c->klasse_id, 'deactivated_from' => $c->deactivated_from?->toDateString(),
                'category' => $c->category ?? null
            ]),
            'column_values' => $valuesGrouped,
            'tasks' => $tasks,
            'pauses' => $pauses,
            'categories' => $categories,
            'show_column_categories' => $showColumnCategories,
            'hidden_category_ids'    => $hiddenCategoryIds,
            'absences'               => $week['absences']->map(fn($a) => [
                'id'          => $a->id,
                'schueler_id' => $a->schueler_id,
                'datum'       => $a->datum->toDateString(),
            ]),
            'class_day_pauses'       => $classDayPauseRecords->map(fn($p) => [
                'klasse_id' => $p->klasse_id,
                'date'      => $p->date->toDateString(),
                'reason'    => $p->reason,
            ]),
        ]);
    }

    /**
     * Exportiert die Wochen-Daten als Wochentabellen-Format (Schüler × Wochentage).
     *
     * @param Request $request {klasse_id, week_start?}
     * @return StreamedResponse
     */
    public function exportExcel(Request $request)
    {
        $request->validate([
            'klasse_id'  => ['nullable', 'integer', 'exists:klassen,id'],
            'group_id'   => ['nullable', 'integer', 'exists:paed_diary_class_groups,id'],
            'week_start' => ['nullable', 'date'],
        ]);
        if (!$request->filled('klasse_id') && !$request->filled('group_id')) {
            return response()->json(['message' => 'klasse_id oder group_id erforderlich'], 422);
        }
        $user    = Auth::user();
        $isGroup = $request->filled('group_id');
        $klassen = collect();
        $group   = null;
        $klasse  = null;

        if ($isGroup) {
            $group        = PaedDiaryClassGroup::with('klassen:id,name,kuerzel')
                ->where('id', $request->group_id)->where('user_id', $user->id)->firstOrFail();
            $userKlassenIds = $user->paed_klassen()->pluck('klassen.id');
            $klassen      = $group->klassen->whereIn('id', $userKlassenIds)->values();
            if ($klassen->isEmpty()) {
                return response()->json(['message' => 'Keine Klassen in Gruppe verfügbar'], 422);
            }
        } else {
            $klasse  = $user->paed_klassen()->where('klassen.id', $request->klasse_id)->firstOrFail();
            $klassen = collect([$klasse]);
        }

        $weekStart = $request->filled('week_start')
            ? Carbon::parse($request->week_start)->startOfWeek()
            : Carbon::now()->startOfWeek();
        $weekEnd = $weekStart->copy()->addDays(4);

        $kw       = $weekStart->weekOfYear;
        $year     = $weekStart->year;
        $filename = $isGroup
            ? "PaedTagebuch_Gruppe_{$group->name}_KW{$kw}_{$year}.xlsx"
            : "PaedTagebuch_KW{$kw}_{$year}.xlsx";

        return Excel::download(new PaedDiaryWeekTableExport($klassen, $weekStart, $weekEnd), $filename);
    }

    /**
     * Speichert einen neuen Tagebuch-Eintrag (Notiz) für ausgewählte Schüler.
     *
     * @param Request $request {klasse_id, date, content, schueler_ids[]}
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeEntry(Request $request)
    {
        $validated = $request->validate([
            'klasse_id' => ['nullable', 'integer', 'exists:klassen,id'],
            'group_id' => ['nullable', 'integer', 'exists:paed_diary_class_groups,id'],
            'date' => ['required', 'date'],
            'content' => ['required', 'string', 'max:20000'],
            'schueler_ids' => ['required', 'array', 'min:1'],
            'schueler_ids.*' => ['integer', 'exists:schueler,id'],
            'completed' => ['nullable'],
            'category_id' => ['nullable','integer','exists:paed_diary_categories,id'],
            'new_category' => ['nullable','string','max:100'],
            'dossier_only' => ['nullable','boolean'],
        ]);
        if (!$request->filled('klasse_id') && !$request->filled('group_id')) {
            return response()->json(['message' => 'klasse_id oder group_id erforderlich'], 422);
        }
        $user = Auth::user();
        $isGroup = $request->filled('group_id');
        $dateObj = Carbon::parse($validated['date']);
        $idsCreated = [];

        $categoryId = $this->kategorieAusRequest($validated, $user);
        if ($categoryId === false) {
            return response()->json(['message' => 'Diese Kategorie steht dir nicht zur Verfügung.', 'errors' => ['category_id' => ['Diese Kategorie steht dir nicht zur Verfügung.']]], 422);
        }

        $isDossierOnly = $request->boolean('dossier_only');
        $completed = $request->boolean('completed');
        $sentIds = array_values(array_unique($validated['schueler_ids'] ?? []));

        if ($isGroup) {
            // Kopplung: nur Klassen der Gruppe, auf die der Benutzer Zugriff hat
            $group = PaedDiaryClassGroup::with('klassen:id')->where('id', $request->group_id)->where('user_id', $user->id)->firstOrFail();
            $allowedClassIds = $group->klassen->pluck('id')->intersect($user->paed_klassen()->pluck('klassen.id'))->all();
        } else {
            $allowedClassIds = $user->paed_klassen()->pluck('klassen.id')->all();
        }

        $sentSchueler = Schueler::whereIn('id', $sentIds)->get(['id', 'klasse_id', 'vorname', 'nachname']);
        $validSchueler = $sentSchueler->filter(fn ($s) => in_array($s->klasse_id, $allowedClassIds));
        if ($validSchueler->isEmpty()) {
            $invalidNames = $sentSchueler->map(fn ($s) => $s->vorname . ' ' . $s->nachname)->all();
            return response()->json(['message' => 'Keine gültigen Schüler', 'invalid_ids' => $sentIds, 'invalid_names' => $invalidNames], 422);
        }

        // Für jede Klasse einen Eintrag – gemeinsame Logik mit API v1 (inkl. Termin-Pausen),
        // alles oder nichts, damit keine halb gespeicherten Notizen entstehen.
        $service = app(\App\Services\PaedDiaryEntryService::class);
        try {
            $createdEntryIds = DB::transaction(fn () => $validSchueler->groupBy('klasse_id')->map(
                fn ($students, $klasseId) => $service->create(
                    (int) $klasseId,
                    $students->pluck('id')->all(),
                    $user,
                    $dateObj,
                    $validated['content'],
                    $completed,
                    $categoryId,
                    $isDossierOnly
                )->id
            )->values()->all());
        } catch (\Throwable $e) {
            Log::error('storeEntry: Eintrag konnte nicht gespeichert werden: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Die Notiz konnte nicht gespeichert werden.'], 500);
        }

        $response = ['success' => true, 'entry_ids' => $createdEntryIds];
        $ignored = array_values(array_diff($sentIds, $validSchueler->pluck('id')->all()));
        if (!empty($ignored)) $response['ignored_schueler_ids'] = $ignored;
        return response()->json($response);
    }

    /**
     * Aktualisiert einen bestehenden Tagebuch-Eintrag.
     *
     * @param PaedDiaryEntry $entry Route-Model-Binding
     * @param Request $request {klasse_id, date, content, schueler_ids[]}
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateEntry(PaedDiaryEntry $entry, Request $request)
    {

        $validated = $request->validate([
            'klasse_id' => ['nullable', 'integer', 'exists:klassen,id'],
            'date' => ['required', 'date'],
            'content' => ['required', 'string', 'max:20000'],
            'schueler_ids' => ['required', 'array', 'min:1'],
            'schueler_ids.*' => ['integer', 'exists:schueler,id'],
            'completed' => ['nullable'],
            'category_id' => ['nullable','integer','exists:paed_diary_categories,id'],
            'new_category' => ['nullable','string','max:100'],
            'dossier_only' => ['nullable','boolean'],
        ]);
        $user = Auth::user();
        // Wie API v1: PaedDiaryPolicy (Klasse des Eintrags oder aktuelle Klasse eines Schülers,
        // vertrauliche Einträge fremder Autoren nur mit Sonderrecht). Die gesendete klasse_id wird
        // nicht verglichen – im Kopplungsmodus ist das immer die erste Klasse der Gruppe.
        $this->authorize('update', $entry);
        $newDate = \Carbon\Carbon::parse($validated['date']);
        // Zulässig sind Schüler, auf deren (aktuelle) Klasse der Benutzer Zugriff hat.
        $validSchueler = \App\Models\Schueler::whereIn('id', $validated['schueler_ids'])
            ->get()
            ->filter(fn ($s) => $user->can('update', $s))
            ->pluck('id')
            ->all();
        if (empty($validSchueler)) {
            return response()->json(['message' => 'Keine gültigen Schüler'], 422);
        }

        $categoryId = $this->kategorieAusRequest($validated, $user);
        if ($categoryId === false) {
            return response()->json(['message' => 'Diese Kategorie steht dir nicht zur Verfügung.', 'errors' => ['category_id' => ['Diese Kategorie steht dir nicht zur Verfügung.']]], 422);
        }

        $wasCompleted = (bool)$entry->completed_at;
        $isDossierOnly = $request->boolean('dossier_only');
        $completedAt = $entry->completed_at;
        if ($isDossierOnly || $request->boolean('completed')) {
            if (!$entry->completed_at) {
                $completedAt = \Carbon\Carbon::now();
            }
        } else {
            $completedAt = null; // Re-Open
        }
        \DB::beginTransaction();
        try {
            $entry->update([
                'datum' => $newDate->toDateString(),
                'content' => trim($validated['content']),
                'completed_at' => $completedAt,
                'category_id' => $categoryId,
                'dossier_only' => $isDossierOnly,
            ]);
            $entry->schueler()->sync($validSchueler);
            // Falls gerade von offen -> abgeschlossen gewechselt: Klon-Logik anwenden
            if (!$wasCompleted && $completedAt) {
                // frisch laden (Relationen aktualisieren)
                $entry->load('schueler');
                $this->finalizeEntry($entry);
            }
            \DB::commit();
        } catch (\Throwable $e) {
            \DB::rollBack();
            return response()->json(['message' => 'Fehler beim Aktualisieren'], 500);
        }
        return response()->json(['success' => true]);
    }

    /**
     * Löscht einen Tagebuch-Eintrag (inkl. Pivot-Einträge).
     *
     * @param PaedDiaryEntry $entry
     * @param Request $request {klasse_id}
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroyEntry(PaedDiaryEntry $entry, Request $request)
    {

        $data = $request->validate([
            'klasse_id' => ['nullable', 'integer', 'exists:klassen,id']
        ]);
        // Wie API v1: Löschen nur durch Autor oder Benutzer mit Sonderrechten (PaedDiaryPolicy::delete)
        $this->authorize('delete', $entry);
        $date = $entry->datum->copy();
        $entry->schueler()->detach();
        $entry->delete();
        return response()->json(['success' => true]);
    }

    /**
     * Legt eine neue Zusatz-Spalte (Column) für die Klasse oder Gruppe an.
     * Unterstützt auch Gruppenmodus (group_id).
     *
     * @param Request $request {klasse_id, group_id, name, type?}
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * POST /paed-diary/absence
     * Setzt oder hebt die Abwesenheit eines Schülers für einen Tag auf (Toggle).
     * Beim Setzen: offene Notizen des Schülers für den Tag werden pausiert.
     * Beim Aufheben: automatisch erzeugte Pausen werden entfernt.
     */
    public function toggleAbsence(Request $request)
    {
        $request->validate([
            'schueler_id' => ['required', 'exists:schueler,id'],
            'klasse_id'   => ['nullable', 'exists:klassen,id'],
            'datum'       => ['required', 'date'],
        ]);

        // Wie API v1: Zugriff über StudentPolicy, Klasse immer vom Schüler (gesendete klasse_id wird ignoriert)
        $schueler = Schueler::findOrFail($request->schueler_id);
        $this->authorize('update', $schueler);
        $klasseId = (int) $schueler->klasse_id;

        $datumString = Carbon::parse($request->datum)->toDateString();

        $existing = PaedDiarySchuelerAbsence::where('schueler_id', $schueler->id)
            ->where('klasse_id', $klasseId)
            ->whereDate('datum', $datumString)
            ->first();

        // Pausen-Logik in PaedDiaryCalendarService (gemeinsam genutzt mit API v1)
        $calendar = app(\App\Services\PaedDiaryCalendarService::class);

        if ($existing) {
            $result = $calendar->setAbsence((int) $existing->schueler_id, (int) $existing->klasse_id, $datumString, false, Auth::id());
            return response()->json([
                'success'          => true,
                'absent'           => false,
                'removed_entry_ids' => $result['removed_entry_ids'],
                'schueler_id'      => $existing->schueler_id,
                'datum'            => $existing->datum->toDateString(),
            ]);
        }

        $result = $calendar->setAbsence((int) $schueler->id, $klasseId, $datumString, true, Auth::id());
        return response()->json([
            'success' => true,
            'absent'  => true,
            'pauses'  => $result['pauses'],
        ]);
    }

    /**
     * Zeigt die Schülerakte im Pädagogischen Tagebuch.
     */
    public function schuelerView(Schueler $schueler, Request $request)
    {
        // Wie API/Dossier: StudentPolicy (Klassenzugriff, "view all students", Admin)
        $this->authorize('view', $schueler);
        $user = Auth::user();
        $klasse = $schueler->klasse;
        abort_unless($klasse, 404, 'Der Schüler ist keiner Klasse zugeordnet.');

        $service = app(\App\Services\PaedDiarySchuelerService::class);
        $sessions = $service->reflexionen($schueler);

        // Letzte Graduierung (jüngste abgeschlossene Reflexions-Session)
        $lastGraduationAt = $sessions->first()?->completed_at;

        // Seit wann besteht die aktuelle Stufe? -> jüngster Historien-Eintrag, der zur aktuellen Stufe geführt hat
        $currentStageSince = $schueler->grading_history()
            ->where('grading_stage_id', $schueler->grading_stage_id)
            ->value('created_at');

        // Diagnose-Sitzungen und aktuelle Diagnose-Ziele nur laden, wenn der Nutzer das Recht dazu hat.
        $diagnosticSessions = collect();
        $currentDiagnosticGoals = collect();
        $canViewDiagnostics = $user->can('view diagnostics');

        if ($canViewDiagnostics) {
            $diagnosticSessions = \App\Models\DiagnosticSession::where('schueler_id', $schueler->id)
                ->with(['area:id,name', 'user:id,name'])
                ->orderByDesc('session_date')
                ->orderByDesc('id')
                ->get();
            $currentDiagnosticGoals = app(\App\Services\Api\StudentDataService::class)->currentCriterionGoals($schueler);
        }

        // "Zurück" führt zur Wochenansicht, aus der man kam (inkl. Klasse/Kopplung)
        $previous = url()->previous();
        $zurueckUrl = str_starts_with($previous, route('paedDiary.index')) && !str_contains($previous, '/schueler/')
            ? $previous
            : route('paedDiary.index', ['klasse' => $klasse->id]);

        $darstellung = $user->paedDiaryDarstellung();

        return view($darstellung['ansicht'] === 'neu' ? 'paedDiary.neu.schueler' : 'paedDiary.schueler', [
            'darstellung' => $darstellung,
            'schueler' => $schueler,
            'klasse' => $klasse,
            'gradingSessions' => $sessions,
            'reflexionen' => $service->reflexionenAlsDaten($sessions),
            'lastGraduationAt' => $lastGraduationAt,
            'currentStageSince' => $currentStageSince,
            'canViewDiagnostics' => $canViewDiagnostics,
            'diagnosticSessions' => $diagnosticSessions,
            'currentDiagnosticGoals' => $currentDiagnosticGoals,
            'zurueckUrl' => $zurueckUrl,
        ]);
    }

    /**
     * Liefert Daten (Einträge, Spalten, Werte, Aufgaben, Ziele) der Schülerakte für einen Zeitraum.
     * In der Schülerakte sind alle Einträge sichtbar, auch vertrauliche.
     */
    public function schuelerData(Schueler $schueler, Request $request)
    {
        $this->authorize('view', $schueler);
        [$dateFrom, $dateTo] = $this->schuelerZeitraum($request);

        $daten = app(\App\Services\PaedDiarySchuelerService::class)->daten($schueler, $dateFrom, $dateTo, Auth::user());

        return response()->json($daten);
    }

    /**
     * Validiert den Zeitraum der Schülerakte (höchstens zwei Jahre).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function schuelerZeitraum(Request $request): array
    {
        $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ]);

        $dateFrom = Carbon::parse($request->date_from)->startOfDay();
        $dateTo = Carbon::parse($request->date_to)->endOfDay();
        if ($dateFrom->diffInDays($dateTo) > \App\Services\PaedDiarySchuelerService::MAX_TAGE) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'date_to' => 'Bitte einen Zeitraum von höchstens zwei Jahren wählen.',
            ]);
        }

        return [$dateFrom, $dateTo];
    }

    /**
     * Erfasst ein neues Ziel ("Ziel an dem ich arbeiten möchte") für einen Schüler.
     * Ältere Ziele bleiben als Historie erhalten (keine Update/Überschreibung).
     *
     * @param Schueler $schueler
     * @param Request $request {goal_text}
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeGoal(Schueler $schueler, Request $request)
    {
        $validated = $request->validate([
            'goal_text' => ['required', 'string', 'max:2000'],
        ]);

        $user = Auth::user();
        $this->authorize('update', $schueler);

        $goal = PaedDiaryGoal::create([
            'schueler_id' => $schueler->id,
            'user_id' => $user->id,
            'goal_text' => trim($validated['goal_text']),
        ]);

        return response()->json([
            'success' => true,
            'goal' => [
                'id' => $goal->id,
                'goal_text' => $goal->goal_text,
                'created_at' => $goal->created_at?->toDateTimeString(),
                'formatted_created_at' => $goal->created_at?->format('d.m.Y H:i'),
                'user' => $user->name,
                'achieved_at' => null,
                'formatted_achieved_at' => null,
                'achieved_by' => null,
            ]
        ]);
    }

    /**
     * Markiert ein Ziel als erreicht (bzw. macht dies rückgängig).
     *
     * @param PaedDiaryGoal $goal
     * @param Request $request {achieved: bool}
     * @return \Illuminate\Http\JsonResponse
     */
    public function achieveGoal(PaedDiaryGoal $goal, Request $request)
    {
        $user = Auth::user();
        $schueler = $goal->schueler;
        abort_unless($schueler, 404);
        $this->authorize('update', $schueler);

        $achieved = $request->boolean('achieved', true);
        if ($achieved) {
            $goal->achieved_at = Carbon::now();
            $goal->achieved_by = $user->id;
        } else {
            $goal->achieved_at = null;
            $goal->achieved_by = null;
        }
        $goal->save();

        return response()->json([
            'success' => true,
            'achieved_at' => $goal->achieved_at?->toDateTimeString(),
            'formatted_achieved_at' => $goal->achieved_at?->format('d.m.Y H:i'),
            'achieved_by' => $achieved ? $user->name : null,
        ]);
    }

    /**
     * Exportiert die Schülerakte (Einträge, Spalten, Aufgaben, Reflexionen) als Excel-Datei.
     * Kategorie- und Suchfilter der Ansicht werden übernommen.
     */
    public function exportSchuelerExcel(Schueler $schueler, Request $request)
    {
        $this->authorize('view', $schueler);
        [$dateFrom, $dateTo] = $this->schuelerZeitraum($request);
        $request->validate([
            'category' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:200'],
        ]);

        $service = app(\App\Services\PaedDiarySchuelerService::class);
        $entries = $service->eintraege($schueler, $dateFrom, $dateTo)
            ->when($request->filled('category'), function ($entries) use ($request) {
                $kategorie = (string) $request->input('category');
                // Filterwerte der Ansicht: Kategorie-ID oder "none" (ohne Kategorie)
                return $entries->filter(fn ($e) => $kategorie === 'none'
                    ? empty($e['category_id'])
                    : (string) $e['category_id'] === $kategorie);
            })
            ->when($request->filled('search'), function ($entries) use ($request) {
                $suche = mb_strtolower(trim((string) $request->input('search')));
                // wie die Suche der Ansicht: Notiz, Autor und Kategorie
                return $entries->filter(fn ($e) => str_contains(mb_strtolower($e['content'] . ' ' . $e['user'] . ' ' . $e['category']), $suche));
            })
            ->values();
        [$columns, $columnValues] = $service->spalten($schueler, $dateFrom, $dateTo);
        $tasks = $service->aufgaben($schueler, $dateFrom, $dateTo);
        $gradingSessions = $service->reflexionen($schueler, $dateFrom, $dateTo)->sortBy('completed_at')->values();

        return Excel::download(
            new PaedDiarySchuelerExport($schueler, $entries, $columns, $columnValues, $tasks, $gradingSessions, $dateFrom, $dateTo),
            'paed_tagebuch_' . Str::slug($schueler->vorname . '_' . $schueler->nachname, '_') . '_' . $dateFrom->format('Ymd') . '_' . $dateTo->format('Ymd') . '.xlsx'
        );
    }

    /**
     * Ändert die Stage eines Schülers, legt einen History-Eintrag an und verknüpft optional einen Tagebucheintrag.
     * Erwartet: schueler_id, grading_stage_id, optional paed_diary_entry_id
     */
    public function changeSchuelerStage(Request $request)
    {
        $data = $request->validate([
            'schueler_id' => ['required', 'integer', 'exists:schueler,id'],
            'grading_stage_id' => ['nullable', 'integer', 'exists:grading_stages,id'],
            'paed_diary_entry_id' => ['nullable', 'integer', 'exists:paed_diary_entries,id']
        ]);
        $user = Auth::user();
        $schueler = Schueler::findOrFail($data['schueler_id']);
        $klasse = $user->paed_klassen()->where('klassen.id', $schueler->klasse_id)->firstOrFail();

        $newStage = null;
        if (!empty($data['grading_stage_id'])) {
            $newStage = GradingStage::findOrFail($data['grading_stage_id']);
            // Validieren: Stage gehört zum System der Klasse (falls gesetzt)
            if ($klasse->grading_system_id && $newStage->grading_system_id != $klasse->grading_system_id) {
                return response()->json(['message' => 'Stage gehört nicht zum System der Klasse'], 422);
            }
        }

        // Stufenwechsel inkl. Historie und automatischem Tagebucheintrag (gemeinsamer Service mit API v1)
        try {
            app(\App\Services\GradingStageService::class)->changeStage(
                $schueler,
                $newStage,
                $user,
                $klasse,
                $data['paed_diary_entry_id'] ?? null
            );
        } catch (\Throwable $e) {
            Log::error('changeSchuelerStage failed: ' . $e->getMessage());
            return response()->json(['message' => 'Fehler beim Anlegen des Tagebucheintrags'], 500);
        }

        return response()->json(['success' => true, 'new_stage' => $newStage ? ['id' => $newStage->id, 'name' => $newStage->name, 'symbol' => $newStage->symbol, 'image_url' => $newStage->image_url] : null]);
    }

    /**
     * Liefert die verfügbaren Stufen für eine Klasse (für Frontend-Auswahl)
     *
     * @param Klasse $klasse
     * @return \Illuminate\Http\JsonResponse
     */
    public function getClassStages(Klasse $klasse)
    {
        $user = Auth::user();
        try {
            $user->paed_klassen()->where('klassen.id', $klasse->id)->firstOrFail();

            if (!$klasse->grading_system_id) {
                return response()->json(['stages' => []]);
            }
            $stages = GradingStage::where('grading_system_id', $klasse->grading_system_id)->orderBy('sort_order')->get();
            $data = $stages->map(fn($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'symbol' => $s->symbol,
                'sort_order' => $s->sort_order,
                'image_url' => $s->image_url ?? null
            ]);
            return response()->json(['stages' => $data]);
        } catch (\Throwable $e) {
            Log::debug('Fehler getStages: ', [
                'message' => $e->getMessage(),
                'klasse'  => $user->paed_klassen()->where('klassen.id', $klasse->id)->first(),
                'stages' => GradingStage::where('grading_system_id', $klasse->grading_system_id)->orderBy('sort_order')->get(),
            ]);

            return response()->json(['message' => 'Stufen konnten nicht geladen werden.'], 500);
        }

    }

    /**
     * Gibt die Schüler einer Klasse für die Dokumentation zurück
     *
     * @param Klasse $klasse
     * @return \Illuminate\Http\JsonResponse
     */
    public function getClassSchueler(Klasse $klasse)
    {
        $user = Auth::user();
        // Prüfen, ob Nutzer Zugriff auf die Klasse hat
        $user->paed_klassen()->where('klassen.id', $klasse->id)->firstOrFail();

        $schueler = $klasse->schueler()
            ->orderBy('nachname')
            ->orderBy('vorname')
            ->get(['id', 'vorname', 'nachname', 'klasse_id']);

        return response()->json(['schueler' => $schueler]);
    }

    /**
     * Setzt einen Eintrag auf abgeschlossen und kopiert den Inhalt auf alle Tage zwischen Erstellung und Abschluss.
     * @param PaedDiaryEntry $entry
     * @param Request $request {klasse_id, completed_at}
     * @return \Illuminate\Http\JsonResponse
     */
    public function completeEntry(PaedDiaryEntry $entry, Request $request)
    {
        $request->validate([
            'klasse_id' => ['nullable', 'integer', 'exists:klassen,id'],
            'completed_at' => ['nullable', 'date'],
            // Optional: nur diesen Schüler abschließen, wenn der Eintrag mehreren Schülern zugeordnet ist.
            'schueler_id' => ['nullable', 'integer', 'exists:schueler,id'],
        ]);
        // Zugriff über PaedDiaryPolicy – greift auch bei Einträgen aus dem Vorjahr (alte klasse_id)
        $this->authorize('update', $entry);
        if ($entry->completed_at) {
            return response()->json(['success' => true]);
        }

        // Wenn ein completed_at Datum übergeben wurde, dieses verwenden (z.B. wenn aus einer Zelle abgeschlossen wird),
        // sonst das aktuelle Datum/time.
        $completedAt = $request->filled('completed_at')
            ? Carbon::parse($request->get('completed_at'))->startOfDay()
            : Carbon::now();

        DB::beginTransaction();
        try {
            // Logik in PaedDiaryEntryService ausgelagert (gemeinsam genutzt mit API v1)
            $schuelerId = $request->filled('schueler_id') ? (int) $request->get('schueler_id') : null;
            app(\App\Services\PaedDiaryEntryService::class)->complete($entry, $completedAt, $schuelerId);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('completeEntry failed: ' . $e->getMessage());
            return response()->json(['message' => 'Fehler beim Abschließen des Eintrags'], 500);
        }
        return response()->json(['success' => true]);
    }

    /**
     * Interne Hilfsfunktion: Klont einen frisch abgeschlossenen Eintrag (bereits completed_at gesetzt)
     * auf alle fehlenden Tage zwischen Eintragsdatum und heutigem Datum (inkl.).
     * Vermeidet Duplikate für dieselben Schüler am jeweiligen Tag.
     *
     * @param PaedDiaryEntry $entry
     * @return void
     */
    private function finalizeEntry(PaedDiaryEntry $entry): void
    {
        // Logik in PaedDiaryEntryService ausgelagert (gemeinsam genutzt mit API v1)
        app(\App\Services\PaedDiaryEntryService::class)->finalize($entry);
    }

    /**
     * Pausiert alle offenen Einträge aller Schüler einer Klasse/Gruppe für einen Tag.
     * Wird bei besonderen Veranstaltungen o.ä. eingesetzt.
     *
     * POST /paed-diary/day/pause
     */
    public function pauseClassDay(Request $request)
    {
        $data = $request->validate([
            'klasse_id' => ['nullable', 'integer', 'exists:klassen,id'],
            'group_id'  => ['nullable', 'integer', 'exists:paed_diary_class_groups,id'],
            'date'      => ['required', 'date'],
            'reason'    => ['nullable', 'string', 'max:100'],
        ]);

        if (!$request->filled('klasse_id') && !$request->filled('group_id')) {
            return response()->json(['message' => 'klasse_id oder group_id erforderlich'], 422);
        }

        // Migration noch nicht gelaufen?
        if (!\Illuminate\Support\Facades\Schema::hasTable('paed_diary_class_day_pauses')) {
            return response()->json(['message' => 'Migration noch nicht ausgeführt. Bitte php artisan migrate aufrufen.'], 503);
        }

        $user    = Auth::user();
        $dateStr = Carbon::parse($data['date'])->toDateString();
        $reason  = trim($data['reason'] ?? '') ?: 'Veranstaltung';

        $klassen = collect();
        if ($request->filled('group_id')) {
            $group   = PaedDiaryClassGroup::with('klassen:id')->where('id', $request->group_id)->where('user_id', $user->id)->firstOrFail();
            $userKlassenIds = $user->paed_klassen()->pluck('klassen.id');
            $klassen = $group->klassen->whereIn('id', $userKlassenIds)->values();
        } else {
            $klasse  = $user->paed_klassen()->where('klassen.id', $data['klasse_id'])->firstOrFail();
            $klassen = collect([$klasse]);
        }

        // Logik in PaedDiaryCalendarService (gemeinsam genutzt mit API v1)
        $created = app(\App\Services\PaedDiaryCalendarService::class)->pauseClassDay($klassen, $dateStr, $reason, $user->id);

        return response()->json(['success' => true, 'class_day_pauses' => $created]);
    }

    /**
     * Hebt die manuelle Tages-Pause einer Klasse/Gruppe für einen Tag auf.
     * Entfernt nur Pausen mit reason = 'Veranstaltung' (nicht Ferien- oder individuelle Pausen).
     *
     * POST /paed-diary/day/unpause
     */
    public function unpauseClassDay(Request $request)
    {
        $data = $request->validate([
            'klasse_id' => ['nullable', 'integer', 'exists:klassen,id'],
            'group_id'  => ['nullable', 'integer', 'exists:paed_diary_class_groups,id'],
            'date'      => ['required', 'date'],
        ]);

        if (!$request->filled('klasse_id') && !$request->filled('group_id')) {
            return response()->json(['message' => 'klasse_id oder group_id erforderlich'], 422);
        }

        if (!\Illuminate\Support\Facades\Schema::hasTable('paed_diary_class_day_pauses')) {
            return response()->json(['success' => true]); // Tabelle existiert noch nicht – nichts zu tun
        }

        $user    = Auth::user();
        $dateStr = Carbon::parse($data['date'])->toDateString();

        $klassen = collect();
        if ($request->filled('group_id')) {
            $group   = PaedDiaryClassGroup::with('klassen:id')->where('id', $request->group_id)->where('user_id', $user->id)->firstOrFail();
            $userKlassenIds = $user->paed_klassen()->pluck('klassen.id');
            $klassen = $group->klassen->whereIn('id', $userKlassenIds)->values();
        } else {
            $klasse  = $user->paed_klassen()->where('klassen.id', $data['klasse_id'])->firstOrFail();
            $klassen = collect([$klasse]);
        }

        // Logik in PaedDiaryCalendarService (gemeinsam genutzt mit API v1)
        app(\App\Services\PaedDiaryCalendarService::class)->unpauseClassDay($klassen, $dateStr);

        return response()->json(['success' => true]);
    }

    /**
     * Pausiert eine offene Notiz für einen bestimmten Schüler an einem Tag (wird in der Wochenansicht ausgeblendet).
     */
    public function pauseEntryDay(PaedDiaryEntry $entry, Request $request)
    {
        $data = $request->validate([
            'schueler_id'=>['required','integer','exists:schueler,id'],
            'date'=>['required','date']
        ]);
        $this->authorize('update', $entry);
        if($entry->completed_at){
            return response()->json(['message'=>'Eintrag bereits abgeschlossen'],422);
        }
        // Schüler gehört zum Eintrag?
        $isAttached = $entry->schueler()->where('schueler.id',$data['schueler_id'])->exists();
        if(!$isAttached){
            return response()->json(['message'=>'Schüler gehört nicht zum Eintrag'],422);
        }
        $date = \Carbon\Carbon::parse($data['date'])->toDateString();
        // Nur Tage ab Startdatum erlaubt
        if($date < $entry->datum->toDateString()){
            return response()->json(['message'=>'Datum liegt vor dem Startdatum der Notiz'],422);
        }
        $pause = app(\App\Services\PaedDiaryCalendarService::class)->ensureEntryPause([
            'paed_diary_entry_id'=>$entry->id,
            'schueler_id'=>$data['schueler_id'],
            'date'=>$date
        ], ['reason' => \App\Services\PaedDiaryCalendarService::GRUND_MANUELL]);
        return response()->json(['success'=>true,'pause_id'=>$pause->id]);
    }

    /**
     * Entfernt die Pause (zeigt die Notiz an diesem Tag wieder an).
     */
    public function unpauseEntryDay(PaedDiaryEntry $entry, Request $request)
    {
        $data = $request->validate([
            'schueler_id'=>['required','integer','exists:schueler,id'],
            'date'=>['required','date']
        ]);
        $this->authorize('update', $entry);
        if($entry->completed_at){
            return response()->json(['message'=>'Eintrag bereits abgeschlossen'],422);
        }
        PaedDiaryEntryPause::where('paed_diary_entry_id',$entry->id)
            ->where('schueler_id',$data['schueler_id'])
            ->whereDate('date',\Carbon\Carbon::parse($data['date'])->toDateString())
            ->delete();
        return response()->json(['success'=>true]);
    }

    /**
     * Speichert oder aktualisiert die Benutzereinstellung für die Anzeige von Spalten-Kategorien.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateShowCategoriesSetting(Request $request)
    {
        try {
            $data = $request->validate([
                'show_column_categories' => ['required', 'boolean']
            ]);

            $user = Auth::user();

            $user->show_column_categories = $data['show_column_categories'];
            $user->save();

            return response()->json([
                'success' => true,
                'show_column_categories' => $user->show_column_categories
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed for updateShowCategoriesSetting', [
                'errors' => $e->errors(),
                'request_data' => $request->all()
            ]);
            return response()->json([
                'message' => 'Validierungsfehler',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error updating show categories setting', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);
            return response()->json(['message' => 'Ein Fehler ist aufgetreten'], 500);
        }
    }

    /**
     * Kategorie aus new_category bzw. category_id: Ein neuer Name nutzt eine gleichnamige globale
     * oder eigene Kategorie, statt ein Duplikat anzulegen. Fremde private Kategorien → false.
     *
     * @return int|null|false
     */
    private function kategorieAusRequest(array $validated, $user): int|null|false
    {
        $sichtbar = fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $user->id);

        if (!empty($validated['new_category']) && trim($validated['new_category']) !== '') {
            $name = trim($validated['new_category']);
            $vorhanden = PaedDiaryCategory::where('name', $name)->where($sichtbar)->orderByRaw('user_id is null desc')->first();

            return ($vorhanden ?? PaedDiaryCategory::create(['name' => $name, 'user_id' => $user->id]))->id;
        }

        if (!empty($validated['category_id'])) {
            return PaedDiaryCategory::whereKey($validated['category_id'])->where($sichtbar)->value('id') ?? false;
        }

        return null;
    }
}
