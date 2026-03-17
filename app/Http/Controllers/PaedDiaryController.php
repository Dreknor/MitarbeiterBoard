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
use App\Models\PaedDiaryEntryPause;
use App\Exports\PaedDiaryExport;
use App\Exports\PaedDiarySchuelerExport;
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
     * Zeigt die Hauptansicht des Pädagogischen Tagebuchs für eine Klasse des angemeldeten Benutzers.
     * Wählt entweder die angeforderte Klasse oder die erste verfügbare Klasse.
     * Falls Gruppen vorhanden sind, wird die erste Gruppe als Standard geladen.
     *
     * @param Request $request
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $klassen = $user->paed_klassen()->withCount('schueler')->orderBy('name')->get();
        if ($klassen->isEmpty()) {
            return redirect()->back()->with(['type' => 'warning', 'Meldung' => 'Keine Klassen zugewiesen.']);
        }

        $groups = Auth::user()->paed_diary_class_groups()->with('klassen:id,name')->get();

        $klasse = null;
        $selectedGroup = null;

        // Prüfen ob eine spezifische Gruppe oder Klasse angefragt wurde
        if ($request->filled('group')) {
            $selectedGroup = $groups->firstWhere('id', (int)$request->get('group'));
            if ($selectedGroup) {
                // Erste Klasse der Gruppe als Fallback-Klasse verwenden
                $klasse = $klassen->firstWhere('id', $selectedGroup->klassen->first()?->id) ?? $klassen->first();
            }
        } elseif ($request->filled('klasse')) {
            $klasse = $klassen->firstWhere('id', (int)$request->get('klasse'));
        }

        // Falls nichts spezifisch angefragt wurde, Standard-Verhalten anwenden
        if (!$klasse && !$selectedGroup) {
            // Falls Gruppen vorhanden sind, erste Gruppe als Standard verwenden
            if ($groups->isNotEmpty()) {
                $selectedGroup = $groups->first();
                // Erste Klasse der Gruppe als Fallback-Klasse verwenden
                $klasse = $klassen->firstWhere('id', $selectedGroup->klassen->first()?->id) ?? $klassen->first();
            } else {
                // Falls keine Gruppen vorhanden, erste Klasse verwenden
                $klasse = $klassen->first();
            }
        }

        // Fallback falls immer noch keine Klasse gefunden wurde
        if (!$klasse) {
            $klasse = $klassen->first();
        }

        return view('paedDiary.index', [
            'klassen' => $klassen,
            'klasse' => $klasse,
            'groups' => $groups,
            'selectedGroup' => $selectedGroup,
        ]);
    }

    /**
     * Erzeugt einen Cache-Key für eine Klassen-Woche.
     *
     * @param int $klasseId
     * @param \Carbon\Carbon $weekStart Wochenbeginn (Montag)
     * @return string
     */
    private function weekCacheKey($klasseId, Carbon $weekStart)
    {
        return 'paed_week_' . $klasseId . '_' . $weekStart->format('Ymd');
    }

    /**
     * Löscht den Wochen-Cache für das Datum (wird auf Wochenbeginn normalisiert).
     *
     * @param int $klasseId
     * @param \Carbon\Carbon $date Beliebiges Datum innerhalb der Woche
     * @return void
     */
    private function forgetWeekCache($klasseId, Carbon $date)
    {
        $start = $date->copy()->startOfWeek();
        Cache::forget($this->weekCacheKey($klasseId, $start));
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
        $periodEnd = $weekStart->copy()->addDays(4);
        $period = CarbonPeriod::create($weekStart, $periodEnd);
        $days = collect();
        $ferienDates = [];

        foreach ($period as $date) {
            $ferienInfo = is_ferien($date);
            $isFerienTag = !is_null($ferienInfo);

            // Debug-Logging
            if ($isFerienTag) {
                Log::info('Ferien gefunden für Datum: ' . $date->toDateString(), [
                    'ferienInfo' => $ferienInfo,
                    'is_object' => is_object($ferienInfo),
                    'is_array' => is_array($ferienInfo)
                ]);
            }

            // Sicherer Zugriff auf ferien_name - kann Objekt oder Array sein
            $ferienName = null;
            if ($isFerienTag) {
                if (is_object($ferienInfo)) {
                    $ferienName = $ferienInfo->name ?? $ferienInfo->slug ?? 'Ferien';
                } elseif (is_array($ferienInfo)) {
                    $ferienName = $ferienInfo['name'] ?? $ferienInfo['slug'] ?? 'Ferien';
                } else {
                    $ferienName = 'Ferien';
                }
            }

            $days->push([
                'date' => $date->toDateString(),
                'label' => $date->format('D d.m.'),
                'is_ferien' => $isFerienTag,
                'ferien_name' => $ferienName
            ]);

            if ($isFerienTag) {
                $ferienDates[] = $date->toDateString();
            }
        }

        // Schüler aller Klassen laden
        $schueler = \App\Models\Schueler::whereIn('klasse_id', $klassen->pluck('id'))->with('grading_stage')->orderBy('klasse_id')->orderBy('vorname')->orderBy('nachname')->get(['id', 'vorname', 'nachname', 'grading_stage_id', 'klasse_id']);

        // Spalten aller Klassen vereinigt
        $columns = PaedDiaryColumn::whereIn('klasse_id', $klassen->pluck('id'))
            ->where(function ($q) use ($weekStart) {
                $q->whereNull('deactivated_from')->orWhere('deactivated_from', '>', $weekStart->toDateString());
            })
            ->orderBy('klasse_id')->orderBy('sort_order')->get();

        // Einträge der aktuellen Woche laden
        $currentWeekEntries = PaedDiaryEntry::with(['schueler:id', 'user:id,name', 'category:id,name'])
            ->whereIn('klasse_id', $klassen->pluck('id'))
            ->whereBetween('datum', [$weekStart->toDateString(), $periodEnd->toDateString()])
            ->where('dossier_only', false)
            ->get();

        // Zusätzlich alle offenen Einträge aus vorherigen Wochen laden
        $previousOpenEntries = PaedDiaryEntry::with(['schueler:id', 'user:id,name', 'category:id,name'])
            ->whereIn('klasse_id', $klassen->pluck('id'))
            ->where('datum', '<', $weekStart->toDateString())
            ->whereNull('completed_at')
            ->get();

        // Beide Collections zusammenführen
        $entries = $currentWeekEntries->merge($previousOpenEntries);

        // Auto-Pausierung: Offene Einträge während der Ferien pausieren
        if (!empty($ferienDates)) {
            foreach ($entries as $entry) {
                if (is_null($entry->completed_at)) {
                    foreach ($entry->schueler as $schueler_item) {
                        foreach ($ferienDates as $ferienDate) {
                            // Prüfen ob bereits eine Pause existiert
                            $existingPause = PaedDiaryEntryPause::where('paed_diary_entry_id', $entry->id)
                                ->where('schueler_id', $schueler_item->id)
                                ->where('date', $ferienDate)
                                ->first();

                            if (!$existingPause) {
                                // Neue Pause für Ferien-Tag erstellen
                                PaedDiaryEntryPause::create([
                                    'paed_diary_entry_id' => $entry->id,
                                    'schueler_id' => $schueler_item->id,
                                    'date' => $ferienDate,
                                    'reason' => 'Ferien'
                                ]);
                            }
                        }
                    }
                }
            }
        }

        $entryData = $entries->map(fn($e) => [
            'id' => $e->id, 'date' => $e->datum->toDateString(), 'content' => $e->content,
            'schueler_ids' => $e->schueler->pluck('id'), 'user' => $e->user?->name,
            'completed_at' => $e->completed_at,
            'klasse_id' => $e->klasse_id,
            'category_id' => $e->category_id,
            'category' => (is_object($e->category)) ? $e->category->name : null
        ]);

        // Alle offenen Notizen laden (unabhängig vom Datum, auch aus vorhergehenden Wochen)
        $allOpenEntries = PaedDiaryEntry::with(['schueler:id', 'user:id,name', 'category:id,name'])
            ->whereIn('klasse_id', $klassen->pluck('id'))
            ->whereNull('completed_at')
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
            'category' => (is_object($e->category)) ? $e->category->name : null
        ])->values();
        $columnValues = PaedDiaryColumnValue::whereIn('paed_diary_column_id', $columns->pluck('id'))
            ->whereBetween('datum', [$weekStart->toDateString(), $periodEnd->toDateString()])
            ->get();
        $valuesGrouped = [];
        foreach ($columnValues as $v) {
            $valuesGrouped[$v->paed_diary_column_id][$v->schueler_id][$v->datum->toDateString()] = $v->value;
        }
        $tasks = PaedDiaryTask::whereIn('klasse_id', $klassen->pluck('id'))->open()->with('schueler:id,vorname,nachname')->get()->map(fn($t) => [
            'id' => $t->id, 'schueler_id' => $t->schueler_id, 'title' => $t->title,
            'description' => $t->description,
            'due_date' => $t->due_date?->toDateString(), 'highlighted' => $t->highlighted, 'klasse_id' => $t->klasse_id,
        ]);
        // Pausen für Tage der Woche laden (inkl. neu erstellte Ferien-Pausen)
        $pauseRecords = PaedDiaryEntryPause::whereIn('paed_diary_entry_id', $entries->pluck('id'))
            ->whereBetween('date', [$weekStart->toDateString(), $periodEnd->toDateString()])
            ->get(['paed_diary_entry_id','schueler_id','date']);
        $pauses = $pauseRecords->map(fn($p)=>[
            'entry_id'=>$p->paed_diary_entry_id,
            'schueler_id'=>$p->schueler_id,
            'date'=>$p->date->toDateString(),
        ]);

        // Kategorien (global + user-spezifisch)
        $categories = [];
        try{
            $categories = PaedDiaryCategory::where(function($q) use ($user){ $q->whereNull('user_id')->orWhere('user_id', $user->id); })->orderBy('name')->get()->map(fn($c)=>['id'=>$c->id,'name'=>$c->name]);
        }catch(\Throwable $_){ $categories = []; }

        // Benutzereinstellung für Kategorieanzeige
        $showColumnCategories = (bool) $user->show_column_categories;

        return response()->json([
            'is_group' => $isGroup,
            'group' => $isGroup ? ['id' => $group->id, 'name' => $group->name] : null,
            'days' => $days,
            'schueler' => $schueler->map(fn($s) => [
                'id' => $s->id, 'name' => $s->vorname . ' ' . $s->nachname,
                'klasse_id' => $s->klasse_id,
                'klasse_name' => $klassen->firstWhere('id', $s->klasse_id)?->name,
                'stage' => $s->grading_stage ? ['id' => $s->grading_stage->id, 'name' => $s->grading_stage->name, 'symbol' => $s->grading_stage->symbol, 'sort_order' => $s->grading_stage->sort_order, 'image_url' => $s->grading_stage->image_url] : null
            ]),
            'klassen' => $klassen->map(fn($k) => ['id' => $k->id, 'name' => $k->name, 'kuerzel' => $k->kuerzel, 'color' => $k->color]),
            'can_manage_grading' => Auth::user()->can('manage grading systems'),
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
        ]);
    }

    /**
     * Liefert alle Einträge einer Zelle (Schüler + Datum) zur Anzeige im Detail.
     *
     * @param Request $request {klasse_id, schueler_id, date}
     * @return \Illuminate\Http\JsonResponse
     */
    public function cellEntries(Request $request)
    {
        $data = $request->validate([
            'klasse_id' => ['required', 'integer', 'exists:klassen,id'],
            'schueler_id' => ['required', 'integer', 'exists:schueler,id'],
            'date' => ['required', 'date']
        ]);
        $user = Auth::user();
        $klasse = $user->paed_klassen()->where('klassen.id', $data['klasse_id'])->firstOrFail();
        $schueler = Schueler::where('id', $data['schueler_id'])->where('klasse_id', $klasse->id)->firstOrFail();
        $entries = PaedDiaryEntry::with(['user:id,name', 'schueler:id'])
            ->where('klasse_id', $klasse->id)
            ->whereDate('datum', Carbon::parse($data['date'])->toDateString())
            ->whereHas('schueler', fn($q) => $q->where('schueler.id', $schueler->id))
            ->where('dossier_only', false)
            ->orderByDesc('id')
            ->get()
            ->map(fn($e) => [
                'id' => $e->id, 'content' => $e->content, 'author' => $e->user?->name, 'count_schueler' => $e->schueler->count()
            ]);
        return response()->json(['entries' => $entries]);
    }

    /**
     * Exportiert die Wochen-Daten einer Klasse als Excel-Datei.
     *
     * @param Request $request {klasse_id, week_start?}
     * @return StreamedResponse
     */
    public function exportExcel(Request $request)
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
        $klassen = collect();
        $group = null;
        $klasse = null;
        if ($isGroup) {
            $group = PaedDiaryClassGroup::with('klassen:id,name,kuerzel')
                ->where('id', $request->group_id)->where('user_id', $user->id)->firstOrFail();
            $userKlassenIds = $user->paed_klassen()->pluck('klassen.id');
            $klassen = $group->klassen->whereIn('id', $userKlassenIds)->values();
            if ($klassen->isEmpty()) return response()->json(['message' => 'Keine Klassen in Gruppe verfügbar'], 422);
        } else {
            $klasse = $user->paed_klassen()->where('klassen.id', $request->klasse_id)->firstOrFail();
            $klassen = collect([$klasse]);
        }
        $weekStart = $request->filled('week_start') ? Carbon::parse($request->week_start)->startOfWeek() : Carbon::now()->startOfWeek();
        $weekEnd = $weekStart->copy()->addDays(4);
        $entries = PaedDiaryEntry::with(['user:id,name', 'schueler.grading_stage'])
            ->whereIn('klasse_id', $klassen->pluck('id'))
            ->whereBetween('datum', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->orderBy('datum')
            ->get();
        $rows = [];
        foreach ($entries as $entry) {
            foreach ($entry->schueler as $s) {
                $kObj = $klassen->firstWhere('id', $entry->klasse_id);
                $rows[] = [
                    'Klasse' => $kObj ? $kObj->name : '',
                    'Kürzel' => $kObj ? $kObj->kuerzel : '',
                    'Datum' => $entry->datum->format('Y-m-d'),
                    'Schüler' => $s->vorname . ' ' . $s->nachname,
                    'Autor' => $entry->user?->name,
                    'Notiz' => preg_replace('/\s+/', ' ', trim($entry->content)),
                    'Stufe' => $s->grading_stage?->name ?? ''
                ];
            }
        }
        $filename = $isGroup
            ? 'paed_tagebuch_gruppe_' . $group->name . '_' . $weekStart->format('Ymd') . '.xlsx'
            : 'paed_tagebuch_' . ($klasse->kuerzel ?? 'klasse') . '_' . $weekStart->format('Ymd') . '.xlsx';
        return Excel::download(new PaedDiaryExport($rows), $filename);
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
            'content' => ['required', 'string'],
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

        // Determine category to use (prioritize new_category)
        $categoryId = null;
        if (!empty($validated['new_category'])) {
            $name = trim($validated['new_category']);
            if ($name !== '') {
                $cat = PaedDiaryCategory::firstOrCreate(['name'=>$name,'user_id'=>$user->id], ['name'=>$name,'user_id'=>$user->id]);
                $categoryId = $cat->id;
            }
        } elseif (!empty($validated['category_id'])) {
            // ensure category is either global or belongs to user
            $cat = PaedDiaryCategory::where('id', $validated['category_id'])->where(function($q) use ($user){ $q->whereNull('user_id')->orWhere('user_id',$user->id); })->first();
            if ($cat) $categoryId = $cat->id;
        }

        if ($isGroup) {
            $group = PaedDiaryClassGroup::with('klassen:id')->where('id', $request->group_id)->where('user_id', $user->id)->firstOrFail();
            $userKlassenIds = $user->paed_klassen()->pluck('klassen.id');
            foreach ($group->klassen->whereIn('id', $userKlassenIds) as $klasse) {
                $schuelerIds = Schueler::whereIn('id', $validated['schueler_ids'])
                    ->where('klasse_id', $klasse->id)->pluck('id')->all();
                if (empty($schuelerIds)) continue;
                $entry = PaedDiaryEntry::create([
                    'klasse_id' => $klasse->id,
                    'user_id' => $user->id,
                    'datum' => $dateObj->toDateString(),
                    'content' => trim($validated['content']),
                    'completed_at' => $request->has('completed') ? Carbon::now() : null,
                    'category_id' => $categoryId
                ]);
                $entry->schueler()->sync($schuelerIds);
                $this->forgetWeekCache($klasse->id, $dateObj);
                $idsCreated[] = $entry->id;
            }
            return response()->json(['success' => true, 'entry_ids' => $idsCreated]);
        }
        // Robustere Behandlung: Ermittle die tatsächlich existierenden Schüler und gruppiere sie nach Klasse.
        $sentIds = array_values(array_unique($validated['schueler_ids'] ?? []));
        $allowedClassIds = $user->paed_klassen()->pluck('klassen.id')->toArray();

        $sentSchueler = Schueler::whereIn('id', $sentIds)->get(['id', 'klasse_id', 'vorname', 'nachname']);
        // Filter: nur Schüler aus Klassen, auf die der User Zugriff hat
        $validSchueler = $sentSchueler->filter(fn($s) => in_array($s->klasse_id, $allowedClassIds));
        if ($validSchueler->isEmpty()) {
            Log::warning('storeEntry: keine gültigen Schüler (keine Zugriffsklasse oder nicht vorhanden)', ['user_id' => $user->id ?? null, 'sent_ids' => $sentIds, 'allowed_class_ids' => $allowedClassIds]);
            $invalidNames = $sentSchueler->map(fn($s) => $s->vorname . ' ' . $s->nachname)->all();
            return response()->json(['message' => 'Keine gültigen Schüler', 'invalid_ids' => $sentIds, 'invalid_names' => $invalidNames], 422);
        }

        // Gruppieren nach klasse_id und für jede Klasse einen Eintrag anlegen
        $byClass = $validSchueler->groupBy('klasse_id');
        $createdEntryIds = [];
        $ignored = array_values(array_diff($sentIds, $validSchueler->pluck('id')->all()));

        foreach ($byClass as $klasseId => $students) {
            try {
                $entry = PaedDiaryEntry::create([
                    'klasse_id' => $klasseId,
                    'user_id' => $user->id,
                    'datum' => $dateObj->toDateString(),
                    'content' => trim($validated['content']),
                    'completed_at' => $request->has('completed') ? Carbon::now() : null,
                    'category_id' => $categoryId,
                    'dossier_only' => $request->has('dossier_only') ? true : false,
                ]);
                $entry->schueler()->sync($students->pluck('id')->all());
                $this->forgetWeekCache($klasseId, $dateObj);
                $createdEntryIds[] = $entry->id;
            } catch (\Throwable $e) {
                Log::error('storeEntry: Fehler beim Erstellen eines Eintrags für klasse_id ' . $klasseId . ': ' . $e->getMessage(), ['exception' => $e]);
            }
        }

        $response = ['success' => true, 'entry_ids' => $createdEntryIds];
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
            'klasse_id' => ['required', 'integer', 'exists:klassen,id'],
            'date' => ['required', 'date'],
            'content' => ['required', 'string'],
            'schueler_ids' => ['required', 'array', 'min:1'],
            'schueler_ids.*' => ['integer', 'exists:schueler,id'],
            'completed' => ['nullable'],
            'category_id' => ['nullable','integer','exists:paed_diary_categories,id'],
            'new_category' => ['nullable','string','max:100'],
            'dossier_only' => ['nullable','boolean'],
        ]);
        $user = Auth::user();
        if ($entry->klasse_id != $validated['klasse_id']) {
            abort(403);
        }
        $klasse = $user->paed_klassen()->where('klassen.id', $validated['klasse_id'])->firstOrFail();
        $oldDate = $entry->datum->copy();
        $newDate = \Carbon\Carbon::parse($validated['date']);
        $validSchueler = \App\Models\Schueler::whereIn('id', $validated['schueler_ids'])->where('klasse_id', $klasse->id)->pluck('id')->all();
        if (empty($validSchueler)) {
            return response()->json(['message' => 'Keine gültigen Schüler'], 422);
        }

        // Category handling (new or existing)
        $categoryId = null;
        if (!empty($validated['new_category'])) {
            $name = trim($validated['new_category']);
            if ($name !== '') {
                $cat = PaedDiaryCategory::firstOrCreate(['name'=>$name,'user_id'=>$user->id], ['name'=>$name,'user_id'=>$user->id]);
                $categoryId = $cat->id;
            }
        } elseif (!empty($validated['category_id'])) {
            $cat = PaedDiaryCategory::where('id', $validated['category_id'])->where(function($q) use ($user){ $q->whereNull('user_id')->orWhere('user_id',$user->id); })->first();
            if ($cat) $categoryId = $cat->id;
        }

        $wasCompleted = (bool)$entry->completed_at;
        $completedAt = $entry->completed_at;
        if ($request->has('completed')) {
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
                'dossier_only' => $request->has('dossier_only') ? true : false,
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
        $this->forgetWeekCache($klasse->id, $oldDate);
        if (!$oldDate->isSameWeek($newDate)) {
            $this->forgetWeekCache($klasse->id, $newDate);
        }
        return response()->json(['success' => true]);
    }

    /**
     * Löscht einen Tagebuch-Eintrag (inkl. Pivot-Einträge) und invalidiert den Cache.
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
        $user = Auth::user();
        if ($request->filled('klasse_id') && $entry->klasse_id != $data['klasse_id']) {
            abort(403);
        }
        $klasse = $user->paed_klassen()->where('klassen.id', $entry->klasse_id)->firstOrFail();
        $date = $entry->datum->copy();
        $entry->schueler()->detach();
        $entry->delete();
        $this->forgetWeekCache($klasse->id, $date);
        return response()->json(['success' => true]);
    }

    /**
     * Legt eine neue Zusatz-Spalte (Column) für die Klasse oder Gruppe an.
     * Unterstützt auch Gruppenmodus (group_id).
     *
     * @param Request $request {klasse_id, group_id, name, type?}
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeColumn(Request $request)
    {
        $data = $request->validate([
            'klasse_id' => ['nullable', 'integer', 'exists:klassen,id'],
            'group_id' => ['nullable', 'integer', 'exists:paed_diary_class_groups,id'],
            'name' => ['required', 'string', 'max:50'],
            'type' => ['nullable', 'in:text,boolean,number'],
            'category' => ['nullable', 'string', 'max:50']
        ]);
        if (!$request->filled('klasse_id') && !$request->filled('group_id'))
            return response()->json(['message' => 'klasse_id oder group_id erforderlich'], 422);
        $user = Auth::user();
        $baseSlug = Str::slug($data['name']);
        if (empty($baseSlug) ) {
            $baseSlug = $data['name'];
        }

        $type = $data['type'] ?? 'text';
        $category = $data['category'] ?? null;
        if ($request->filled('group_id')) {
            $group = PaedDiaryClassGroup::with('klassen:id')->where('id', $request->group_id)->where('user_id', $user->id)->firstOrFail();
            $userKlassenIds = $user->paed_klassen()->pluck('klassen.id');
            $created = [];
            foreach ($group->klassen->whereIn('id', $userKlassenIds) as $klasse) {
                // Generiere einen eindeutigen Slug, falls schon einer existiert
                $slug = $this->generateUniqueSlug($baseSlug, $klasse->id);
                $sort = (int)PaedDiaryColumn::where('klasse_id', $klasse->id)->max('sort_order') + 1;
                $colData = ['klasse_id' => $klasse->id, 'name' => $data['name'], 'slug' => $slug, 'type' => $type, 'sort_order' => $sort];
                if ($category) $colData['category'] = $category;
                $col = PaedDiaryColumn::create($colData);
                $this->forgetWeekCache($klasse->id, Carbon::now());
                $created[] = ['id' => $col->id, 'klasse_id' => $klasse->id, 'name' => $col->name, 'category' => $col->category ?? null];
            }
            return response()->json(['success' => true, 'columns' => $created]);
        }
        $klasse = $user->paed_klassen()->where('klassen.id', $data['klasse_id'])->firstOrFail();
        // Generiere einen eindeutigen Slug, falls schon einer existiert
        $slug = $this->generateUniqueSlug($baseSlug, $klasse->id);
        $sort = (int)PaedDiaryColumn::where('klasse_id', $klasse->id)->max('sort_order') + 1;
        $colData = ['klasse_id' => $klasse->id, 'name' => $data['name'], 'slug' => $slug, 'type' => $type, 'sort_order' => $sort];
        if ($category) $colData['category'] = $category;
        $col = PaedDiaryColumn::create($colData);
        $this->forgetWeekCache($klasse->id, Carbon::now());
        return response()->json(['success' => true, 'column' => ['id' => $col->id, 'name' => $col->name, 'category' => $col->category ?? null]]);
    }

    /**
     * Aktualisiert die Kategorie einer bestehenden Spalte.
     */
    public function updateColumnCategory(PaedDiaryColumn $column, Request $request)
    {
        $data = $request->validate([
            'category' => ['nullable', 'string', 'max:50']
        ]);
        $user = Auth::user();
        $klasse = $user->paed_klassen()->where('klassen.id', $column->klasse_id)->firstOrFail();
        $column->category = $data['category'] ?? null;
        $column->save();
        $this->forgetWeekCache($klasse->id, Carbon::now());
        return response()->json(['success' => true, 'category' => $column->category]);
    }

    /**
     * Speichert oder aktualisiert den Wert einer Zusatz-Spalte für einen Schüler an einem Datum.
     *
     * @param Request $request {column_id, schueler_id, date, value?}
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeColumnValue(Request $request)
    {
        $data = $request->validate([
            'column_id' => ['required', 'integer', 'exists:paed_diary_columns,id'],
            'schueler_id' => ['required', 'integer', 'exists:schueler,id'],
            'date' => ['required', 'date'],
            'value' => ['nullable', 'string', 'max:255']
        ]);
        $column = PaedDiaryColumn::findOrFail($data['column_id']);
        $user = Auth::user();
        $klasse = $user->paed_klassen()->where('klassen.id', $column->klasse_id)->firstOrFail();
        $schueler = Schueler::where('id', $data['schueler_id'])->where('klasse_id', $klasse->id)->firstOrFail();
        $dateObj = Carbon::parse($data['date']);
        PaedDiaryColumnValue::updateOrCreate([
            'paed_diary_column_id' => $column->id,
            'schueler_id' => $schueler->id,
            'datum' => $dateObj->toDateString()
        ], ['value' => $data['value']]);
        $this->forgetWeekCache($klasse->id, $dateObj);
        return response()->json(['success' => true]);
    }

    /**
     * Erzeugt eine neue Aufgabe (Task) für einen Schüler oder mehrere Schüler.
     *
     * @param Request $request {klasse_id, group_id, schueler_id, schueler_ids[], title, description?, due_date?, highlighted?}
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeTask(Request $request)
    {
        $data = $request->validate([
            'klasse_id' => ['nullable', 'integer', 'exists:klassen,id'],
            'group_id' => ['nullable', 'integer', 'exists:paed_diary_class_groups,id'],
            'schueler_id' => ['nullable', 'integer', 'exists:schueler,id'],
            'schueler_ids' => ['nullable', 'array', 'min:1'],
            'schueler_ids.*' => ['integer', 'exists:schueler,id'],
            'title' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'highlighted' => ['nullable', 'boolean']
        ]);
        if (!$request->filled('klasse_id') && !$request->filled('group_id'))
            return response()->json(['message' => 'klasse_id oder group_id erforderlich'], 422);

        $user = Auth::user();
        $highlighted = $data['highlighted'] ?? true;
        $created = [];

        // Normalisiere übergebene Schüler-IDs
        $schuelerIds = [];
        if (!empty($data['schueler_ids'])) {
            $schuelerIds = array_values(array_unique($data['schueler_ids']));
        } elseif (!empty($data['schueler_id'])) {
            $schuelerIds = [$data['schueler_id']];
        }

        // Hilfsdaten: erlaubte Klassen des Users
        $allowedClassIds = $user->paed_klassen()->pluck('klassen.id')->toArray();

        // Gruppenmodus: für jede Klasse der Gruppe Aufgaben anlegen
        if ($request->filled('group_id')) {
            $group = PaedDiaryClassGroup::with('klassen:id')->where('id', $request->group_id)->where('user_id', $user->id)->firstOrFail();
            $userKlassenIds = $user->paed_klassen()->pluck('klassen.id');

            foreach ($group->klassen->whereIn('id', $userKlassenIds) as $klasse) {
                // Wenn konkrete Schüler-IDs übergeben wurden: filtere auf Schüler dieser Klasse
                if (!empty($schuelerIds)) {
                    $ids = Schueler::whereIn('id', $schuelerIds)->where('klasse_id', $klasse->id)->pluck('id')->all();
                } else {
                    // Keine spezifischen Schüler: ganze Klasse
                    $ids = Schueler::where('klasse_id', $klasse->id)->pluck('id')->all();
                }

                if (empty($ids)) continue;

                foreach ($ids as $sid) {
                    $task = PaedDiaryTask::create([
                        'klasse_id' => $klasse->id,
                        'schueler_id' => $sid,
                        'title' => $data['title'],
                        'description' => $data['description'] ?? null,
                        'due_date' => $data['due_date'] ?? null,
                        'status' => 'open',
                        'highlighted' => $highlighted,
                        'created_by' => $user->id
                    ]);

                    $created[] = [
                        'id' => $task->id,
                        'schueler_id' => $task->schueler_id,
                        'title' => $task->title,
                        'due_date' => $task->due_date?->toDateString(),
                        'highlighted' => $task->highlighted,
                        'klasse_id' => $task->klasse_id
                    ];
                }

                $this->forgetWeekCache($klasse->id, Carbon::now());
            }

            return response()->json(['success' => true, 'tasks' => $created]);
        }

        // Einzel- oder Mehrschüler-Modus ohne Gruppe
        if ($request->filled('klasse_id')) {
            $klasse = $user->paed_klassen()->where('klassen.id', $data['klasse_id'])->firstOrFail();

            if (!empty($schuelerIds)) {
                $ids = Schueler::whereIn('id', $schuelerIds)->where('klasse_id', $klasse->id)->pluck('id')->all();
            } else {
                // Keine spezifischen Schüler: ganze Klasse
                $ids = Schueler::where('klasse_id', $klasse->id)->pluck('id')->all();
            }

            if (empty($ids)) {
                return response()->json(['message' => 'Keine gültigen Schüler'], 422);
            }

            foreach ($ids as $sid) {
                $task = PaedDiaryTask::create([
                    'klasse_id' => $klasse->id,
                    'schueler_id' => $sid,
                    'title' => $data['title'],
                    'description' => $data['description'] ?? null,
                    'due_date' => $data['due_date'] ?? null,
                    'status' => 'open',
                    'highlighted' => $highlighted,
                    'created_by' => $user->id
                ]);

                $created[] = [
                    'id' => $task->id,
                    'schueler_id' => $task->schueler_id,
                    'title' => $task->title,
                    'due_date' => $task->due_date?->toDateString(),
                    'highlighted' => $task->highlighted,
                    'klasse_id' => $task->klasse_id
                ];
            }

            $this->forgetWeekCache($klasse->id, Carbon::now());

            return response()->json(['success' => true, 'tasks' => $created]);
        }

        // Falls nur schueler_ids übergeben wurden (ohne klasse_id), erstelle Aufgaben für gültige Schüler unabhängig von Klasse
        if (!empty($schuelerIds)) {
            $sentSchueler = Schueler::whereIn('id', $schuelerIds)->get(['id', 'klasse_id']);
            $validSchueler = $sentSchueler->filter(fn($s) => in_array($s->klasse_id, $allowedClassIds));

            if ($validSchueler->isEmpty()) {
                return response()->json(['message' => 'Keine gültigen Schüler'], 422);
            }

            foreach ($validSchueler as $s) {
                $task = PaedDiaryTask::create([
                    'klasse_id' => $s->klasse_id,
                    'schueler_id' => $s->id,
                    'title' => $data['title'],
                    'description' => $data['description'] ?? null,
                    'due_date' => $data['due_date'] ?? null,
                    'status' => 'open',
                    'highlighted' => $highlighted,
                    'created_by' => $user->id
                ]);

                $created[] = [
                    'id' => $task->id,
                    'schueler_id' => $task->schueler_id,
                    'title' => $task->title,
                    'due_date' => $task->due_date?->toDateString(),
                    'highlighted' => $task->highlighted,
                    'klasse_id' => $task->klasse_id
                ];

                $this->forgetWeekCache($s->klasse_id, Carbon::now());
            }

            return response()->json(['success' => true, 'tasks' => $created]);
        }

        return response()->json(['message' => 'Keine Schüler angegeben'], 422);
    }

    /**
     * Schließt (finalisiert) eine bestehende Aufgabe.
     *
     * @param PaedDiaryTask $task
     * @return \Illuminate\Http\JsonResponse
     */
    public function closeTask(PaedDiaryTask $task)
    {
        $user = Auth::user();
        $user->paed_klassen()->where('klassen.id', $task->klasse_id)->firstOrFail();
        $task->update(['status' => 'closed', 'highlighted' => false, 'closed_at' => now()]);
        $this->forgetWeekCache($task->klasse_id, Carbon::now());
        return response()->json(['success' => true]);
    }

    /**
     * Aktualisiert eine bestehende Aufgabe.
     *
     * @param Request $request
     * @param PaedDiaryTask $task
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateTask(Request $request, PaedDiaryTask $task)
    {
        $user = Auth::user();
        $user->paed_klassen()->where('klassen.id', $task->klasse_id)->firstOrFail();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'highlighted' => ['nullable', 'boolean']
        ]);

        $task->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'highlighted' => $data['highlighted'] ?? $task->highlighted
        ]);

        $this->forgetWeekCache($task->klasse_id, Carbon::now());

        return response()->json([
            'success' => true,
            'task' => [
                'id' => $task->id,
                'schueler_id' => $task->schueler_id,
                'title' => $task->title,
                'description' => $task->description,
                'due_date' => $task->due_date?->toDateString(),
                'highlighted' => $task->highlighted,
                'klasse_id' => $task->klasse_id
            ]
        ]);
    }

    /**
     * Löscht eine Zusatz-Spalte (Column) und invalidiert den Cache.
     *
     * @param PaedDiaryColumn $column
     * @param Request $request {klasse_id}
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroyColumn(PaedDiaryColumn $column, Request $request)
    {
        $data = $request->validate([
            'klasse_id' => ['required', 'integer', 'exists:klassen,id']
        ]);
        $user = Auth::user();
        if ($column->klasse_id != $data['klasse_id']) {
            abort(403);
        }
        $klasse = $user->paed_klassen()->where('klassen.id', $column->klasse_id)->firstOrFail();
        try {
            $weekStart = $request->filled('week_start')
                ? Carbon::parse($request->week_start)->startOfWeek()
                : Carbon::now()->startOfWeek();

            // Soft deactivate: Set deactivated_from if not already set or earlier than weekStart
            if (is_null($column->deactivated_from) || $column->deactivated_from->gt($weekStart)) {
                $column->deactivated_from = $weekStart->toDateString();
                $column->save();
            }

            // Clear cache for the class and week
            $this->forgetWeekCache($klasse->id, $weekStart);

            return response()->json(['success' => true]);
        } catch (Exception $e) {
            // Log the error for debugging
            Log::error('Error in destroyColumn: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'An error occurred while deactivating the column.'], 500);
        }
    }

    /**
     * Liefert alle (auch deaktivierte) Spalten einer Klasse zur Verwaltung.
     *
     * @param Request $request {klasse_id}
     * @return \Illuminate\Http\JsonResponse
     */
    public function columnsAll(Request $request)
    {
        $data = $request->validate([
            'klasse_id' => ['required', 'integer', 'exists:klassen,id']
        ]);
        $user = Auth::user();
        $klasse = $user->paed_klassen()->where('klassen.id', $data['klasse_id'])->firstOrFail();
        $cols = PaedDiaryColumn::where('klasse_id', $klasse->id)->orderBy('sort_order')->get()->map(fn($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'type' => $c->type,
            'sort_order' => $c->sort_order,
            'deactivated_from' => $c->deactivated_from?->toDateString(),
            'category' => $c->category ?? null
        ]);
        return response()->json(['columns' => $cols]);
    }

    /**
     * Reaktiviert eine zuvor deaktivierte Spalte.
     *
     * @param PaedDiaryColumn $column
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function restoreColumn(PaedDiaryColumn $column, Request $request)
    {
        $user = Auth::user();
        $klasse = $user->paed_klassen()->where('klassen.id', $column->klasse_id)->firstOrFail();
        if ($column->deactivated_from) {
            $column->deactivated_from = null;
            $column->save();
            $this->forgetWeekCache($klasse->id, Carbon::now());
        }
        return response()->json(['success' => true]);
    }

    /**
     * Zeigt die Einzelansicht eines Schülers im Pädagogischen Tagebuch.
     *
     * @param Schueler $schueler
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function schuelerView(Schueler $schueler, Request $request)
    {
        $user = Auth::user();
        $klasse = $user->paed_klassen()->where('klassen.id', $schueler->klasse_id)->firstOrFail();

        // Graduierungs-Dokumentationen laden
        $sessions = \App\Models\GradingDocumentationSession::where('klasse_id', $klasse->id)
            ->where(function($q) use ($schueler) {
                $q->where('type', 'group')
                  ->orWhere(function($q2) use ($schueler) {
                      $q2->where('type', 'individual')
                         ->where('schueler_id', $schueler->id);
                  });
            })
            ->whereNotNull('completed_at')
            ->with([
                'gradingSystem',
                'gradingSystem.questions' => function($q) {
                    $q->orderBy('sort_order');
                },
                'user',
                'studentAnswers' => function($q) use ($schueler) {
                    $q->where('schueler_id', $schueler->id);
                },
                'teacherAssessments' => function($q) use ($schueler) {
                    $q->where('schueler_id', $schueler->id);
                }
            ])
            ->orderBy('completed_at', 'desc')
            ->get();

        return view('paedDiary.schueler', [
            'schueler' => $schueler,
            'klasse' => $klasse,
            'gradingSessions' => $sessions,
        ]);
    }

    /**
     * Liefert Daten (Einträge, Spalten, Werte, Aufgaben) für einen Schüler in einem Datumsbereich.
     *
     * @param Schueler $schueler
     * @param Request $request {date_from, date_to}
     * @return \Illuminate\Http\JsonResponse
     */
    public function schuelerData(Schueler $schueler, Request $request)
    {
        $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from']
        ]);

        try {

            $user = Auth::user();
            $klasse = $user->paed_klassen()->where('klassen.id', $schueler->klasse_id)->firstOrFail();

            $dateFrom = Carbon::parse($request->date_from);
            $dateTo = Carbon::parse($request->date_to);

            // Einträge für den Schüler laden
            $entries = PaedDiaryEntry::with(['user:id,name', 'category:id,name'])
                ->where('klasse_id', $klasse->id)
                ->whereBetween('datum', [$dateFrom->toDateString(), $dateTo->toDateString()])
                ->whereHas('schueler', fn($q) => $q->where('schueler.id', $schueler->id))
                ->orderBy('datum')
                ->get()
                ->map(fn($e) => [
                    'id' => $e->id,
                    'date' => $e->datum->toDateString(),
                    'content' => $e->content,
                    'user' => $e->user?->name,
                    'formatted_date' => $e->datum->format('d.m.Y'),
                    'category' => ((is_object($e->category))? $e->category->name : ''),
                    'category_id' => $e->category_id,
                    'dossier_only' => $e->dossier_only,

                ]);

            // Aktuelle Stage und Historie (mit Bild/Sort-Order und menschlichem Namen des Änderers)
            $schueler->load('grading_stage', 'grading_history.stage', 'grading_history.previous_stage', 'grading_history.changed_by_user');
            $currentStage = $schueler->grading_stage ? [
                'id' => $schueler->grading_stage->id,
                'name' => $schueler->grading_stage->name,
                'symbol' => $schueler->grading_stage->symbol,
                'sort_order' => $schueler->grading_stage->sort_order,
                'image_url' => $schueler->grading_stage->image_url ?? null
            ] : null;
            $history = $schueler->grading_history->map(function ($h) {
                $at = null;
                try {
                    if ($h->created_at) {
                        $at = Carbon::parse($h->created_at)->toDateTimeString();
                    }
                } catch (\Throwable $_) {
                    $at = (string)$h->created_at;
                }
                return [
                    'at' => $at,
                    'stage_id' => $h->grading_stage_id,
                    'stage_name' => $h->stage?->name,
                    'previous_stage_id' => $h->previous_grading_stage_id,
                    'previous_stage_name' => $h->previous_stage?->name,
                    'changed_by' => $h->changed_by,
                    'changed_by_name' => $h->changed_by_user?->name ?? null
                ];
            });

            // Spalten für die Klasse laden
            $columns = PaedDiaryColumn::where('klasse_id', $klasse->id)
                ->orderBy('sort_order')
                ->get()
                ->map(function($c) use ($dateTo) {
                    // Ein Feld `deactivated_from` an die Frontend-Antwort anhängen
                    // und ein berechnetes `active`-Flag setzen (aktiv wenn kein deactivated_from gesetzt ist
                    // oder das Deaktivierungsdatum nach dem angefragten Enddatum liegt).
                    $deactivated = $c->deactivated_from ? $c->deactivated_from->toDateString() : null;
                    $isActive = true;
                    if ($c->deactivated_from) {
                        // Vergleiche mit dem angefragten Enddatum
                        try {
                            $isActive = $c->deactivated_from->gt($dateTo);
                        } catch (\Throwable $_) {
                            $isActive = true;
                        }
                    }
                    return [
                        'id' => $c->id,
                        'name' => $c->name,
                        'type' => $c->type,
                        'category' => $c->category,
                        'deactivated_from' => $deactivated,
                        'active' => $isActive
                    ];
                });

            // Spaltenwerte für den Schüler laden und nach YYYY-MM-DD gruppieren
            $columnValues = PaedDiaryColumnValue::whereIn('paed_diary_column_id', $columns->pluck('id'))
                ->where('schueler_id', $schueler->id)
                ->whereBetween('datum', [$dateFrom->toDateString(), $dateTo->toDateString()])
                ->get()
                ->groupBy(function ($v) {
                    try {
                        return Carbon::parse($v->datum)->toDateString();
                    } catch (\Throwable $_) {
                        return (string)$v->datum;
                    }
                })
                ->map(function ($dayValues) {
                    // key by column id so frontend can access by column id
                    return $dayValues->keyBy('paed_diary_column_id');
                });

            // Aufgaben für den Schüler laden
            $tasks = PaedDiaryTask::where('klasse_id', $klasse->id)
                ->where('schueler_id', $schueler->id)
                ->whereBetween('created_at', [$dateFrom, $dateTo->addDay()])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(fn($t) => [
                    'id' => $t->id,
                    'title' => $t->title,
                    'description' => $t->description,
                    'due_date' => $t->due_date?->format('d.m.Y'),
                    'status' => $t->status,
                    'highlighted' => $t->highlighted,
                    'created_at' => $t->created_at->format('d.m.Y H:i')
                ]);

            $categories = PaedDiaryCategory::all(['id', 'name']);

            return response()->json([
                'entries' => $entries,
                'current_stage' => $currentStage,
                'stage_history' => $history,
                'columns' => $columns,
                'column_values' => $columnValues,
                'tasks' => $tasks,
                'period' => [
                    'from' => $dateFrom->format('d.m.Y'),
                    'to' => $dateTo->format('d.m.Y')
                ],
                'categories' => $categories
            ]);

        } catch (\Throwable $e) {
            // Log the error via the normal logger (no custom debug file)
            Log::error('schuelerData exception: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Interner Serverfehler'], 500);
        }
    }

    /**
     * Exportiert die Schüler-Daten (Einträge, Spalten, Aufgaben) als Excel-Datei für einen Zeitraum.
     *
     * @param Schueler $schueler
     * @param Request $request {date_from, date_to}
     * @return StreamedResponse
     */
    public function exportSchuelerWord(Schueler $schueler, Request $request)
    {
        $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from']
        ]);

        $user = Auth::user();
        $klasse = $user->paed_klassen()->where('klassen.id', $schueler->klasse_id)->firstOrFail();

        $dateFrom = Carbon::parse($request->date_from);
        $dateTo = Carbon::parse($request->date_to);

        // Einträge für den Schüler laden (transformiert wie in schuelerData)
        $entries = PaedDiaryEntry::with(['user:id,name'])
            ->where('klasse_id', $klasse->id)
            ->whereBetween('datum', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->whereHas('schueler', fn($q) => $q->where('schueler.id', $schueler->id))
            ->orderBy('datum')
            ->get()
            ->map(fn($e) => [
                'id' => $e->id,
                'date' => $e->datum->toDateString(),
                'content' => $e->content,
                'user' => $e->user?->name,
                'formatted_date' => $e->datum->format('d.m.Y')
            ]);

        // Spalten für die Klasse laden (transformiert)
        $columns = PaedDiaryColumn::where('klasse_id', $klasse->id)
            ->orderBy('sort_order')
            ->get()
            ->map(function($c) use ($dateTo) {
                // Ein Feld `deactivated_from` an die Frontend-Antwort anhängen
                // und ein berechnetes `active`-Flag setzen (aktiv wenn kein deactivated_from gesetzt ist
                // oder das Deaktivierungsdatum nach dem angefragten Enddatum liegt).
                $deactivated = $c->deactivated_from ? $c->deactivated_from->toDateString() : null;
                $isActive = true;
                if ($c->deactivated_from) {
                    // Vergleiche mit dem angefragten Enddatum
                    try {
                        $isActive = $c->deactivated_from->gt($dateTo);
                    } catch (\Throwable $_) {
                        $isActive = true;
                    }
                }
                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'type' => $c->type,
                    'category' => $c->category,
                    'deactivated_from' => $deactivated,
                    'active' => $isActive
                ];
            });

        // Spaltenwerte für den Schüler laden (transformiert) und nach YYYY-MM-DD gruppiert
        $columnValues = PaedDiaryColumnValue::whereIn('paed_diary_column_id', $columns->pluck('id'))
            ->where('schueler_id', $schueler->id)
            ->whereBetween('datum', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->get()
            ->groupBy(function ($v) {
                try {
                    return Carbon::parse($v->datum)->toDateString();
                } catch (\Throwable $_) {
                    return (string)$v->datum;
                }
            })
            ->map(function ($dayValues) {
                return $dayValues->keyBy('paed_diary_column_id');
            });

        // Aufgaben für den Schüler laden (transformiert)
        $tasks = PaedDiaryTask::where('klasse_id', $klasse->id)
            ->where('schueler_id', $schueler->id)
            ->whereBetween('created_at', [$dateFrom, $dateTo->copy()->addDay()])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'description' => $t->description,
                'due_date' => $t->due_date?->format('d.m.Y'),
                'status' => $t->status,
                'highlighted' => $t->highlighted,
                'created_at' => $t->created_at->format('d.m.Y H:i')
            ]);

        // Graduierungsdokumentation Sessions für den Schüler laden (alle, ohne Datumsfilter)
        $gradingSessions = \App\Models\GradingDocumentationSession::where('klasse_id', $klasse->id)
            ->where(function($q) use ($schueler) {
                $q->where('schueler_id', $schueler->id)
                  ->orWhere(function($q2) use ($schueler) {
                      $q2->where('type', 'group')
                         ->whereHas('studentAnswers', fn($q3) => $q3->where('schueler_id', $schueler->id));
                  });
            })
            ->whereNotNull('completed_at')
            ->with([
                'gradingSystem',
                'gradingSystem.questions' => function($q) {
                    $q->where('active', true)->orderBy('sort_order');
                },
                'user',
                'studentAnswers' => function($q) use ($schueler) {
                    $q->where('schueler_id', $schueler->id)->with('question');
                },
                'teacherAssessments' => function($q) use ($schueler) {
                    $q->where('schueler_id', $schueler->id)->with('question');
                }
            ])
            ->orderBy('completed_at', 'asc')
            ->get();

        return Excel::download(
            new PaedDiarySchuelerExport($schueler, $entries, $columns, $columnValues, $tasks, $gradingSessions, $dateFrom, $dateTo),
            'paed_tagebuch_' . $schueler->vorname . '_' . $schueler->nachname . '_' . $dateFrom->format('Ymd') . '_' . $dateTo->format('Ymd') . '.xlsx'
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

        $previous = $schueler->grading_stage_id;
        $newStage = null;
        if (!empty($data['grading_stage_id'])) {
            $newStage = GradingStage::findOrFail($data['grading_stage_id']);
            // Validieren: Stage gehört zum System der Klasse (falls gesetzt)
            if ($klasse->grading_system_id && $newStage->grading_system_id != $klasse->grading_system_id) {
                return response()->json(['message' => 'Stage gehört nicht zum System der Klasse'], 422);
            }
        }

        // Update Schüler and create history + optional diary entry in a transaction
        $paedEntryId = $data['paed_diary_entry_id'] ?? null;
        DB::beginTransaction();
        try {
            // Update student
            $schueler->grading_stage_id = $data['grading_stage_id'] ?? null;
            $schueler->save();

            // If no paed_diary_entry_id provided, create an automatic diary entry describing the change
            if (empty($paedEntryId)) {
                $prevStageName = null;
                if (!empty($previous)) {
                    $prev = GradingStage::find($previous);
                    $prevStageName = $prev?->name;
                }
                $newStageName = $newStage?->name ?? null;
                $userId = $user->id ?? null;
                $studentName = $schueler->vorname . ' ' . $schueler->nachname;
                $parts = [];
                if ($prevStageName) $parts[] = 'von "' . $prevStageName . '"';
                if ($newStageName) $parts[] = 'auf "' . $newStageName . '"';
                $changeText = 'Stufe geändert ' . ($parts ? implode(' ', $parts) : '') . ' für ' . $studentName . '.';

                $entry = PaedDiaryEntry::create([
                    'klasse_id' => $klasse->id,
                    'user_id' => $userId,
                    'datum' => now(),
                    'content' => $changeText,
                    'completed_at' => Carbon::now()
                ]);
                // attach the student
                $entry->schueler()->sync([$schueler->id]);
                $paedEntryId = $entry->id;
            }

            // History anlegen und mit dem Tagebucheintrag verknüpfen
            SchuelerGradingHistory::create([
                'schueler_id' => $schueler->id,
                'grading_system_id' => $klasse->grading_system_id,
                'grading_stage_id' => $data['grading_stage_id'] ?? null,
                'previous_grading_stage_id' => $previous,
                'changed_by' => $user->id,
                'paed_diary_entry_id' => $paedEntryId,
                'created_at' => now()
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('changeSchuelerStage failed: ' . $e->getMessage());
            return response()->json(['message' => 'Fehler beim Anlegen des Tagebucheintrags'], 500);
        }

        // Cache invalideren
        $this->forgetWeekCache($klasse->id, Carbon::now());

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

            return response()->json(['message' => $e], 500);
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
        ]);
        $user = Auth::user();
        // Zugriff auf Klasse des Eintrags prüfen (unabhängig von gesendeter klasse_id)
        $user->paed_klassen()->where('klassen.id', $entry->klasse_id)->firstOrFail();
        if ($entry->completed_at) {
            return response()->json(['success' => true]);
        }
        DB::beginTransaction();
        try {
            // Wenn ein completed_at Datum übergeben wurde, dieses verwenden (z.B. wenn aus einer Zelle abgeschlossen wird),
            // sonst das aktuelle Datum/time.
            if ($request->filled('completed_at')) {
                $entry->completed_at = Carbon::parse($request->get('completed_at'))->startOfDay();
            } else {
                $entry->completed_at = Carbon::now();
            }
            $entry->save();
            $entry->load('schueler');
            $this->finalizeEntry($entry);
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
        // Angepasst: pausierte Tage pro Schüler berücksichtigen
        $klasseId = $entry->klasse_id;
        $start = \Carbon\Carbon::parse($entry->datum)->startOfDay();
        $completedDate = $entry->completed_at?->copy()->startOfDay();
        if(!$completedDate){
            // Wenn kein Abschlussdatum vorhanden, keine Finalisierung (Sicherheitsnetz)
            return;
        }
        if ($completedDate->lt($start)) {
            $completedDate = $start->copy();
        }
        $entry->loadMissing('schueler','pauses');
        $allStudentIds = $entry->schueler->pluck('id')->all();
        // Pausen gruppieren: [schueler_id][Y-m-d] => true
        $pauseMap = [];
        foreach($entry->pauses as $pause){
            $pauseMap[$pause->schueler_id][$pause->date->toDateString()] = true;
        }
        // Start-Tag: entferne pausierte Schüler am Starttag aus Pivot
        $startDateStr = $start->toDateString();
        $keepStartStudents = array_filter($allStudentIds, fn($sid)=> empty($pauseMap[$sid][$startDateStr]));
        if(count($keepStartStudents) !== count($allStudentIds)){
            $entry->schueler()->sync($keepStartStudents);
        }
        // Falls keine Schüler mehr übrig -> Eintrag löschen
        if(empty($keepStartStudents)){
            $entry->schueler()->detach();
            $entry->delete();
        }
        // Weitere Tage (exklusive Start) bis einschließlich completedDate
        for($d = $start->copy()->addDay(); $d->lte($completedDate); $d->addDay()){
            $dateStr = $d->toDateString();
            // Schüler ohne Pause an diesem Tag
            $activeStudents = array_filter($allStudentIds, function($sid) use ($pauseMap, $dateStr) { return empty($pauseMap[$sid][$dateStr]); });
            if(empty($activeStudents)) continue; // nichts einzutragen
            // Prüfen ob bereits ein Eintrag mit gleichem Inhalt für (alle) diese Schüler existiert
            $existing = PaedDiaryEntry::where('klasse_id',$klasseId)
                ->whereDate('datum',$dateStr)
                ->where('content',$entry->content)
                ->whereHas('schueler', function($q) use ($activeStudents){ $q->whereIn('schueler.id',$activeStudents); })
                ->first();
            if($existing){
                // sicherstellen dass alle activeStudents verknüpft sind
                $merged = array_unique(array_merge($existing->schueler()->pluck('schueler.id')->all(), $activeStudents));
                $existing->schueler()->sync($merged);
                continue;
            }
            $newEntry = PaedDiaryEntry::create([
                'klasse_id'=>$klasseId,
                'user_id'=>$entry->user_id,
                'datum'=>$dateStr,
                'content'=>$entry->content,
                'completed_at'=>$entry->completed_at,
            ]);
            $newEntry->schueler()->sync($activeStudents);
        }
        $this->forgetWeekCache($klasseId, $start);
        $this->forgetWeekCache($klasseId, $completedDate);
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
        $user = auth()->user();
        // Zugriff prüfen
        $user->paed_klassen()->where('klassen.id',$entry->klasse_id)->firstOrFail();
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
        $pause = PaedDiaryEntryPause::firstOrCreate([
            'paed_diary_entry_id'=>$entry->id,
            'schueler_id'=>$data['schueler_id'],
            'date'=>$date
        ]);
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
        $user = auth()->user();
        $user->paed_klassen()->where('klassen.id',$entry->klasse_id)->firstOrFail();
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
     * Liefert alle Klassengruppen des angemeldeten Users (inkl. Klassen) als JSON.
     */
    public function classGroups(Request $request)
    {
        $user = Auth::user();
        $groups = PaedDiaryClassGroup::with('klassen:id,name,kuerzel')->where('user_id',$user->id)->orderBy('name')->get()->map(function($g){
            return [
                'id'=>$g->id,
                'name'=>$g->name,
                'klassen'=>$g->klassen->map(fn($k)=>['id'=>$k->id,'name'=>$k->name,'kuerzel'=>$k->kuerzel])
            ];
        });
        return response()->json(['groups'=>$groups]);
    }

    /**
     * Legt eine neue Klassengruppe an (mindestens 2 Klassen) – nur Klassen die der Nutzer in paed_klassen hat.
     */
    public function storeClassGroup(Request $request)
    {
        $data = $request->validate([
            'name'=>['required','string','max:100'],
            'klasse_ids'=>['required','array','min:2'],
            'klasse_ids.*'=>['integer','exists:klassen,id']
        ]);
        $user = Auth::user();
        // Filter nur Klassen die dem User zugeordnet sind
        $allowedIds = $user->paed_klassen()->pluck('klassen.id')->toArray();
        $klasseIds = array_values(array_unique(array_intersect($data['klasse_ids'],$allowedIds)));
        if(count($klasseIds) < 2){
            return response()->json(['message'=>'Mindestens 2 gültige Klassen erforderlich'],422);
        }
        $group = PaedDiaryClassGroup::create(['user_id'=>$user->id,'name'=>trim($data['name'])]);
        $group->klassen()->sync($klasseIds);
        $group->load('klassen:id,name,kuerzel');
        return response()->json(['success'=>true,'group'=>[
            'id'=>$group->id,
            'name'=>$group->name,
            'klassen'=>$group->klassen->map(fn($k)=>['id'=>$k->id,'name'=>$k->name,'kuerzel'=>$k->kuerzel])
        ]]);
    }

    /**
     * Aktualisiert eine bestehende Klassengruppe (Name + Klassenliste).
     */
    public function updateClassGroup(PaedDiaryClassGroup $group, Request $request)
    {
        $user = Auth::user();
        abort_unless($group->user_id === $user->id,403);
        $data = $request->validate([
            'name'=>['required','string','max:100'],
            'klasse_ids'=>['required','array','min:2'],
            'klasse_ids.*'=>['integer','exists:klassen,id']
        ]);
        $allowedIds = $user->paed_klassen()->pluck('klassen.id')->toArray();
        $klasseIds = array_values(array_unique(array_intersect($data['klasse_ids'],$allowedIds)));
        if(count($klasseIds) < 2){
            return response()->json(['message'=>'Mindestens 2 gültige Klassen erforderlich'],422);
        }
        $group->update(['name'=>trim($data['name'])]);
        $group->klassen()->sync($klasseIds);
        $group->load('klassen:id,name,kuerzel');
        return response()->json(['success'=>true]);
    }

    /**
     * Löscht eine Klassengruppe.
     */
    public function destroyClassGroup(PaedDiaryClassGroup $group)
    {
        $user = Auth::user();
        abort_unless($group->user_id === $user->id,403);
        $group->klassen()->detach();
        $group->delete();
        return response()->json(['success'=>true]);
    }

    /**
     * Liefert Termine (inkl. berechneter Wiederholungen) für gegebenen Zeitraum / Klasse oder Gruppe.
     * GET Parameter: start_date, end_date, klasse_id?, group_id?
     */
    public function appointments(Request $request)
    {
        $data = $request->validate([
            'start_date'=>['required','date'],
            'end_date'=>['required','date','after_or_equal:start_date'],
            'klasse_id'=>['nullable','integer','exists:klassen,id'],
            'group_id'=>['nullable','integer','exists:paed_diary_class_groups,id']
        ]);
        if(!$request->filled('klasse_id') && !$request->filled('group_id')){
            return response()->json(['appointments'=>[]]); // leer – Frontend erwartet Liste
        }
        $user = Auth::user();
        $classIds = [];
        $groupId = null;
        if($request->filled('klasse_id')){
            $klasse = $user->paed_klassen()->where('klassen.id',$data['klasse_id'])->firstOrFail();
            $classIds = [$klasse->id];
        }
        if($request->filled('group_id')){
            $group = PaedDiaryClassGroup::where('id',$data['group_id'])->where('user_id',$user->id)->firstOrFail();
            $groupId = $group->id;
            $classIds = array_unique(array_merge($classIds,$group->klassen()->pluck('klassen.id')->toArray()));
        }
        if(empty($classIds)){
            return response()->json(['appointments'=>[]]);
        }
        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end   = Carbon::parse($data['end_date'])->endOfDay();

        // Termine auswählen, die mindestens eine der Klassen / die Gruppe / Schüler aus Klassen referenzieren
        $appointments = PaedDiaryAppointment::with(['klassen:id,name','groups:id,name','schueler:id,vorname,nachname,klasse_id'])
            ->where(function($q) use ($classIds,$groupId){
                $q->whereHas('klassen', function($qq) use ($classIds){ $qq->whereIn('klassen.id',$classIds); })
                  ->orWhereHas('schueler', function($qq) use ($classIds){ $qq->whereIn('schueler.klasse_id',$classIds); });
                if($groupId){
                    $q->orWhereHas('groups', function($qq) use ($groupId){ $qq->where('paed_diary_class_group_id',$groupId); });
                }
            })
            ->where(function($q) use ($end){
                // grober Filter: start_date <= Enddatum Zeitraum (Wiederholungen werden später berechnet)
                $q->whereDate('start_date','<=',$end->toDateString());
            })
            ->get();

        $out = [];
        foreach($appointments as $app){
            $occ = $app->getOccurrencesInRange($start->copy(), $end->copy());
            if(empty($occ)) continue;
            $k = $app->klassen->map(fn($k)=>['id'=>$k->id,'name'=>$k->name]);
            $g = $app->groups->map(fn($gr)=>['id'=>$gr->id,'name'=>$gr->name]);
            $s = $app->schueler->map(fn($st)=>['id'=>$st->id,'name'=>$st->vorname.' '.$st->nachname,'klasse_id'=>$st->klasse_id]);
            foreach($occ as $o){
                $out[] = array_merge($o,[
                    'klassen'=>$k,
                    'groups'=>$g,
                    'schueler'=>$s,
                ]);
            }
        }
        // Sortieren nach Datum + Uhrzeit
        usort($out, function($a,$b){
            if($a['date'] === $b['date']) return strcmp(($a['start_time']??''), ($b['start_time']??''));
            return strcmp($a['date'],$b['date']);
        });
        return response()->json(['appointments'=>$out]);
    }

    /**
     * Legt einen neuen Termin an.
     */
    public function storeAppointment(Request $request)
    {
        $data = $request->validate([
            'title'=>['required','string','max:150'],
            'description'=>['nullable','string'],
            'start_date'=>['required','date'],
            'start_time'=>['nullable','date_format:H:i'],
            'end_time'=>['nullable','date_format:H:i','after_or_equal:start_time'],
            'is_recurring'=>['nullable','boolean'],
            'recurring_type'=>['nullable','in:daily,weekly,monthly'],
            'recurring_interval'=>['nullable','integer','min:1','max:365'],
            'recurring_end_date'=>['nullable','date','after_or_equal:start_date'],
            'klasse_ids'=>['array'],
            'klasse_ids.*'=>['integer','exists:klassen,id'],
            'group_ids'=>['array'],
            'group_ids.*'=>['integer','exists:paed_diary_class_groups,id'],
            'schueler_ids'=>['array'],
            'schueler_ids.*'=>['integer','exists:schueler,id']
        ]);
        $user = Auth::user();
        $isRecurring = (bool)($data['is_recurring'] ?? false);
        if($isRecurring){
            if(empty($data['recurring_type'])) return response()->json(['message'=>'recurring_type erforderlich'],422);
            if(empty($data['recurring_interval'])) $data['recurring_interval']=1;
        } else {
            // Für nicht wiederkehrende Termine: Typ & Enddatum löschen, Interval auf 1 (DB Default, kein NULL Insert)
            $data['recurring_type']=null;
            $data['recurring_interval']=1;
            $data['recurring_end_date']=null;
        }
        // Zeitfelder in passendes Format bringen (wir speichern als einfache Strings / oder Carbon?) – wir lassen Strings, Modell-Casts formatiert
        $appointment = PaedDiaryAppointment::create([
            'user_id'=>$user->id,
            'title'=>trim($data['title']),
            'description'=>$data['description'] ?? null,
            'start_date'=>Carbon::parse($data['start_date'])->toDateString(),
            'start_time'=>!empty($data['start_time'])? Carbon::parse($data['start_date'].' '.$data['start_time']) : null,
            'end_time'=>!empty($data['end_time'])? Carbon::parse($data['start_date'].' '.$data['end_time']) : null,
            'is_recurring'=>$isRecurring,
            'recurring_type'=>$data['recurring_type'] ?? null,
            'recurring_interval'=>$isRecurring ? ($data['recurring_interval'] ?? 1) : 1,
            'recurring_end_date'=>!empty($data['recurring_end_date'])? Carbon::parse($data['recurring_end_date'])->toDateString() : null,
            'is_paused'=>false,
        ]);
        $this->syncAppointmentRelations($appointment,$data,$user);
        return response()->json(['success'=>true,'appointment_id'=>$appointment->id]);
    }

    /**
     * Aktualisiert einen Termin.
     */
    public function updateAppointment(PaedDiaryAppointment $appointment, Request $request)
    {
        $user = Auth::user();
        abort_unless($appointment->user_id === $user->id,403);
        $data = $request->validate([
            'title'=>['required','string','max:150'],
            'description'=>['nullable','string'],
            'start_date'=>['required','date'],
            'start_time'=>['nullable','date_format:H:i'],
            'end_time'=>['nullable','date_format:H:i','after_or_equal:start_time'],
            'is_recurring'=>['nullable','boolean'],
            'recurring_type'=>['nullable','in:daily,weekly,monthly'],
            'recurring_interval'=>['nullable','integer','min:1','max:365'],
            'recurring_end_date'=>['nullable','date','after_or_equal:start_date'],
            'klasse_ids'=>['array'],
            'klasse_ids.*'=>['integer','exists:klassen,id'],
            'group_ids'=>['array'],
            'group_ids.*'=>['integer','exists:paed_diary_class_groups,id'],
            'schueler_ids'=>['array'],
            'schueler_ids.*'=>['integer','exists:schueler,id']
        ]);
        $isRecurring = (bool)($data['is_recurring'] ?? false);
        if($isRecurring){
            if(empty($data['recurring_type'])) return response()->json(['message'=>'recurring_type erforderlich'],422);
            if(empty($data['recurring_interval'])) $data['recurring_interval']=1;
        } else {
            $data['recurring_type']=null;
            $data['recurring_interval']=1;
            $data['recurring_end_date']=null;
            $appointment->is_paused=false; // beim Umschalten Reset
        }
        $appointment->update([
            'title'=>trim($data['title']),
            'description'=>$data['description'] ?? null,
            'start_date'=>Carbon::parse($data['start_date'])->toDateString(),
            'start_time'=>!empty($data['start_time'])? Carbon::parse($data['start_date'].' '.$data['start_time']) : null,
            'end_time'=>!empty($data['end_time'])? Carbon::parse($data['start_date'].' '.$data['end_time']) : null,
            'is_recurring'=>$isRecurring,
            'recurring_type'=>$data['recurring_type'] ?? null,
            'recurring_interval'=>$isRecurring ? ($data['recurring_interval'] ?? 1) : 1,
            'recurring_end_date'=>!empty($data['recurring_end_date'])? Carbon::parse($data['recurring_end_date'])->toDateString() : null,
        ]);
        $this->syncAppointmentRelations($appointment,$data,$user);
        return response()->json(['success'=>true]);
    }

    /**
     * Pausiert / reaktiviert einen wiederkehrenden Termin.
     */
    public function toggleAppointmentPause(PaedDiaryAppointment $appointment)
    {
        $user = Auth::user();
        abort_unless($appointment->user_id === $user->id,403);
        if(!$appointment->is_recurring){
            return response()->json(['message'=>'Nur für wiederkehrende Termine'],422);
        }
        $appointment->is_paused = !$appointment->is_paused;
        $appointment->save();
        return response()->json(['success'=>true,'is_paused'=>$appointment->is_paused]);
    }

    /**
     * Löscht einen Termin (und löst Pivot-Beziehungen).
     */
    public function destroyAppointment(PaedDiaryAppointment $appointment)
    {
        $user = Auth::user();
        abort_unless($appointment->user_id === $user->id,403);
        $appointment->klassen()->detach();
        $appointment->groups()->detach();
        $appointment->schueler()->detach();
        $appointment->delete();
        return response()->json(['success'=>true]);
    }

    /**
     * Hilfsfunktion zum Synchronisieren der Pivot-Relationen eines Termins.
     */
    private function syncAppointmentRelations(PaedDiaryAppointment $appointment, array $data, $user): void
    {
        // Klassen filtern: nur solche zu denen der User Zugriff hat
        $allowedClassIds = $user->paed_klassen()->pluck('klassen.id')->toArray();
        $klasseIds = array_filter($data['klasse_ids'] ?? [], fn($id) => in_array($id,$allowedClassIds));
        $appointment->klassen()->sync($klasseIds);

        // Gruppen: müssen dem User gehören
        $groupIds = array_filter($data['group_ids'] ?? [], function($gid) use ($user){ return PaedDiaryClassGroup::where('id',$gid)->where('user_id',$user->id)->exists(); });
        $appointment->groups()->sync($groupIds);

        // Schüler: nur aus erlaubten Klassen
        $schuelerIdsRaw = $data['schueler_ids'] ?? [];
        if($schuelerIdsRaw){
            $validSchueler = Schueler::whereIn('id',$schuelerIdsRaw)->whereIn('klasse_id',$allowedClassIds)->pluck('id')->toArray();
            $appointment->schueler()->sync($validSchueler);
        } else {
            $appointment->schueler()->sync([]);
        }
    }

    /**
     * Liefert alle Kategorien (global + user-spezifisch).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCategories(Request $request)
    {
        $user = Auth::user();
        $categories = PaedDiaryCategory::where(function($q) use ($user) {
            $q->whereNull('user_id')->orWhere('user_id', $user->id);
        })->orderBy('name')->get(['id', 'name', 'user_id']);

        return response()->json([
            'categories' => $categories->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'is_global' => is_null($c->user_id),
                'can_edit' => is_null($c->user_id) ? false : ($c->user_id === $user->id)
            ])
        ]);
    }

    /**
     * Benennt eine Kategorie um (nur user-spezifische Kategorien).
     *
     * @param PaedDiaryCategory $category
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function renameCategory(PaedDiaryCategory $category, Request $request)
    {
        $user = Auth::user();

        // Nur user-spezifische Kategorien können umbenannt werden
        if (is_null($category->user_id) || $category->user_id !== $user->id) {
            return response()->json(['message' => 'Keine Berechtigung'], 403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100']
        ]);

        $newName = trim($data['name']);

        // Prüfen ob Name bereits existiert (für diesen User)
        $exists = PaedDiaryCategory::where('name', $newName)
            ->where('user_id', $user->id)
            ->where('id', '!=', $category->id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Kategorie mit diesem Namen existiert bereits'], 422);
        }

        $category->name = $newName;
        $category->save();

        return response()->json(['success' => true, 'name' => $category->name]);
    }

    /**
     * Löscht eine Kategorie (nur user-spezifische Kategorien).
     * Einträge mit dieser Kategorie werden auf category_id=null gesetzt.
     *
     * @param PaedDiaryCategory $category
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteCategory(PaedDiaryCategory $category)
    {
        $user = Auth::user();

        // Nur user-spezifische Kategorien können gelöscht werden
        if (is_null($category->user_id) || $category->user_id !== $user->id) {
            return response()->json(['message' => 'Keine Berechtigung'], 403);
        }

        // Alle Einträge mit dieser Kategorie auf null setzen
        PaedDiaryEntry::where('category_id', $category->id)->update(['category_id' => null]);

        // Spalten mit dieser Kategorie leeren
        PaedDiaryColumn::where('category', $category->name)->update(['category' => null]);

        $category->delete();

        return response()->json(['success' => true]);
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
     * Generiert einen eindeutigen Slug für eine Spalte in einer Klasse.
     * Wenn der Slug bereits existiert, wird eine Nummer angehängt (z.B. "spaltenname-2").
     *
     * @param string $baseSlug Der Basis-Slug
     * @param int $klasseId Die ID der Klasse
     * @return string Der eindeutige Slug
     */
    private function generateUniqueSlug(string $baseSlug, int $klasseId): string
    {
        $slug = $baseSlug;
        $counter = 2;

        while (PaedDiaryColumn::where('klasse_id', $klasseId)->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
