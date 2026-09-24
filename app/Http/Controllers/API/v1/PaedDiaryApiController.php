<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\v1\BulkStorePaedDiaryRequest;
use App\Http\Requests\API\v1\IndexPaedDiaryEntriesRequest;
use App\Http\Requests\API\v1\StorePaedDiaryEntryRequest;
use App\Http\Requests\API\v1\UpdatePaedDiaryEntryRequest;
use App\Http\Resources\API\v1\PaedDiaryCategoryResource;
use App\Http\Resources\API\v1\PaedDiaryEntryResource;
use App\Models\Klasse;
use App\Models\PaedDiaryCategory;
use App\Models\PaedDiaryEntry;
use App\Models\Schueler;
use App\Models\User;
use App\Services\Api\PaedDiarySearchService;
use App\Services\Api\StudentDataService;
use App\Services\PaedDiaryEntryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * API v1 – Bereich 2: Pädagogisches Tagebuch.
 *
 * Ein Tagebucheintrag gehört zu genau einer Klasse und kann mehreren Schülern zugeordnet sein
 * (Pivot paed_diary_entry_schueler) – identisch zum Web-Frontend. Bulk-Einträge erzeugen daher
 * einen Eintrag pro betroffener Klasse.
 */
class PaedDiaryApiController extends Controller
{
    public function __construct(
        private StudentDataService $data,
        private PaedDiaryEntryService $entries,
        private PaedDiarySearchService $search
    ) {
    }

    /**
     * GET /api/v1/paed-diary/categories – globale und eigene Kategorien inkl. Farbcodierung.
     */
    public function categories(Request $request)
    {
        $user = $request->user();

        $categories = PaedDiaryCategory::forUser($user->id)
            ->withExists(['hiddenByUsers as is_hidden' => fn ($q) => $q->where('users.id', $user->id)])
            ->orderBy('name')
            ->get();

        return PaedDiaryCategoryResource::collection($categories);
    }

    /**
     * GET /api/v1/students/{student}/paed-diary/entries – chronologisch (neueste zuerst), paginiert.
     * Mit `search` Volltextsuche im Eintragstext (siehe PaedDiarySearchService).
     */
    public function studentEntries(IndexPaedDiaryEntriesRequest $request, Schueler $schueler)
    {
        $this->authorize('view', $schueler);

        $query = $this->filtered($this->data->diaryEntriesQuery($schueler, $request->user()), $request);

        return $this->paginated($query, $request);
    }

    /**
     * GET /api/v1/classes/{class}/paed-diary/entries – Klassen-Feed: alle Einträge der Schüler
     * einer Klasse (auch von Kolleg*innen), neueste zuerst. Vertrauliche Einträge nur gemäß Rechten.
     * Filter wie beim Schüler plus `author=own|others`; ohne Zeitraum werden die letzten 14 Tage geliefert.
     */
    public function classEntries(IndexPaedDiaryEntriesRequest $request, Klasse $klasse)
    {
        $this->authorize('viewClass', [Schueler::class, $klasse]);
        $user = $request->user();

        // Schülerbasiert (wie die Wochenansicht), damit Einträge nach Klassenwechseln erhalten bleiben.
        $query = PaedDiaryEntry::query()
            ->where(fn ($q) => $q
                ->where('paed_diary_entries.klasse_id', $klasse->id)
                ->orWhereHas('schueler', fn ($s) => $s->where('schueler.klasse_id', $klasse->id)))
            ->confidentialFilter($user)
            ->with(['category:id,name,color', 'user:id,name', 'schueler:schueler.id,vorname,nachname,klasse_id'])
            ->when($request->input('author') === 'own', fn ($q) => $q->where('user_id', $user->id))
            ->when($request->input('author') === 'others', fn ($q) => $q->where('user_id', '!=', $user->id))
            ->orderByDesc('datum')
            ->orderByDesc('id');

        if (!$request->filled('from_date') && !$request->filled('to_date') && !$request->filled('search')
            && !$request->filled('updated_since')) {
            $query->where('datum', '>=', now()->subDays(13)->toDateString());
        }

        return $this->paginated($this->filtered($query, $request), $request, withStudents: true);
    }

    /**
     * POST /api/v1/paed-diary/entries – Einzeleintrag.
     */
    public function store(StorePaedDiaryEntryRequest $request): JsonResponse
    {
        $user = $request->user();
        $schueler = Schueler::findOrFail($request->integer('schueler_id'));
        $this->authorize('update', $schueler);

        if (!$schueler->klasse_id) {
            return $this->unprocessable('schueler_id', 'Der Schüler ist keiner Klasse zugeordnet.');
        }

        $categoryId = $this->resolveCategoryId($request->input('category_id'), $user);
        if ($categoryId === false) {
            return $this->unprocessable('category_id', 'Die Kategorie ist für diesen Benutzer nicht verfügbar.');
        }

        $entry = DB::transaction(fn () => $this->createEntry(
            (int) $schueler->klasse_id,
            [$schueler->id],
            $request,
            $user,
            $categoryId
        ));

        return (new PaedDiaryEntryResource($this->loadEntry($entry)))->response()->setStatusCode(201);
    }

    /**
     * POST /api/v1/paed-diary/bulk-entries – Gruppen-/Klasseneintrag für mehrere Schüler.
     * Alle Schüler müssen für den Benutzer zugänglich sein (sonst 403, nichts wird gespeichert).
     */
    public function bulkStore(BulkStorePaedDiaryRequest $request): JsonResponse
    {
        $user = $request->user();
        $ids = collect($request->input('schueler_ids'))->map(fn ($id) => (int) $id)->unique()->values();
        $students = Schueler::whereIn('id', $ids)->get(['id', 'klasse_id']);

        $allowedClassIds = $user->canAccessAllStudents() ? null : $user->paedKlassenIds();
        $forbidden = $students->filter(fn ($s) => !$s->klasse_id
            || ($allowedClassIds !== null && !$allowedClassIds->contains((int) $s->klasse_id)))
            ->pluck('id')->values();

        if ($forbidden->isNotEmpty()) {
            return response()->json([
                'message' => 'Keine Berechtigung für einzelne Schüler.',
                'forbidden_schueler_ids' => $forbidden,
            ], 403);
        }

        $categoryId = $this->resolveCategoryId($request->input('category_id'), $user);
        if ($categoryId === false) {
            return $this->unprocessable('category_id', 'Die Kategorie ist für diesen Benutzer nicht verfügbar.');
        }

        $created = DB::transaction(function () use ($students, $request, $user, $categoryId) {
            return $students->groupBy('klasse_id')->map(fn ($group, $klasseId) => $this->createEntry(
                (int) $klasseId,
                $group->pluck('id')->all(),
                $request,
                $user,
                $categoryId
            ))->values();
        });

        $created = PaedDiaryEntry::with(['category:id,name,color', 'user:id,name', 'schueler:schueler.id'])
            ->whereIn('id', $created->pluck('id'))
            ->get();

        return PaedDiaryEntryResource::collection($created)
            ->additional(['meta' => [
                'entries_created' => $created->count(),
                'students_count' => $students->count(),
            ]])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/v1/paed-diary/entries/{id}
     */
    public function show(PaedDiaryEntry $entry)
    {
        $this->authorize('view', $entry);

        return new PaedDiaryEntryResource($this->loadEntry($entry));
    }

    /**
     * PUT /api/v1/paed-diary/entries/{id} – Teilaktualisierung möglich.
     */
    public function update(UpdatePaedDiaryEntryRequest $request, PaedDiaryEntry $entry)
    {
        $this->authorize('update', $entry);
        $user = $request->user();

        if ($this->data->isStale($entry, $request->input('expected_updated_at'))) {
            return $this->conflict(new PaedDiaryEntryResource($this->loadEntry($entry)));
        }

        $attributes = [];
        if ($request->has('content')) {
            $attributes['content'] = trim((string) $request->input('content'));
        }
        if ($request->has('entry_date')) {
            $attributes['datum'] = $request->input('entry_date');
        }
        if ($request->has('category_id')) {
            $categoryId = $this->resolveCategoryId($request->input('category_id'), $user);
            if ($categoryId === false) {
                return $this->unprocessable('category_id', 'Die Kategorie ist für diesen Benutzer nicht verfügbar.');
            }
            $attributes['category_id'] = $categoryId;
        }

        $dossierOnly = $request->has('is_dossier_only') ? $request->boolean('is_dossier_only') : (bool) $entry->dossier_only;
        $attributes['dossier_only'] = $dossierOnly;

        $wasCompleted = $entry->completed_at !== null;
        $completed = $request->has('is_completed') ? $request->boolean('is_completed') : $wasCompleted;
        // Wie im Web-Frontend: vertrauliche Einträge sind immer abgeschlossen
        $completed = $completed || $dossierOnly;
        $attributes['completed_at'] = $completed ? ($entry->completed_at ?? Carbon::now()) : null;

        $studentIds = null;
        if ($request->has('schueler_ids')) {
            $studentIds = collect($request->input('schueler_ids'))->map(fn ($id) => (int) $id)->unique();
            $valid = Schueler::whereIn('id', $studentIds)->where('klasse_id', $entry->klasse_id)->pluck('id');
            if ($valid->count() !== $studentIds->count()) {
                return $this->unprocessable('schueler_ids', 'Alle Schüler müssen der Klasse des Eintrags angehören.');
            }
        }

        $oldDate = $entry->datum->copy();

        DB::transaction(function () use ($entry, $attributes, $studentIds, $wasCompleted, $completed) {
            $entry->update($attributes);
            if ($studentIds !== null) {
                $entry->schueler()->sync($studentIds->all());
            }
            if (!$wasCompleted && $completed) {
                $entry->load('schueler');
                $this->entries->finalize($entry);
            }
        });

        $this->entries->forgetWeekCache($entry->klasse_id, $oldDate);
        $this->entries->forgetWeekCache($entry->klasse_id, Carbon::parse($entry->datum));

        // finalize() kann den Eintrag löschen, falls alle Schüler am Starttag pausiert waren
        if (!PaedDiaryEntry::whereKey($entry->id)->exists()) {
            return response()->json(null, 204);
        }

        return new PaedDiaryEntryResource($this->loadEntry($entry->fresh()));
    }

    /**
     * DELETE /api/v1/paed-diary/entries/{id}
     */
    public function destroy(PaedDiaryEntry $entry): JsonResponse
    {
        $this->authorize('delete', $entry);

        $date = $entry->datum->copy();
        $klasseId = $entry->klasse_id;

        DB::transaction(function () use ($entry) {
            $entry->schueler()->detach();
            $entry->delete();
        });

        $this->entries->forgetWeekCache($klasseId, $date);

        return response()->json(null, 204);
    }

    // ── Hilfsmethoden ────────────────────────────────────────────────────

    /**
     * Gemeinsame Filter der Eintragslisten (Zeitraum, Kategorie, Delta-Abfrage).
     */
    private function filtered($query, IndexPaedDiaryEntriesRequest $request)
    {
        return $query
            ->when($request->filled('from_date'), fn ($q) => $q->where('datum', '>=', $request->input('from_date')))
            ->when($request->filled('to_date'), fn ($q) => $q->where('datum', '<=', $request->input('to_date') . ' 23:59:59'))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('updated_since'), fn ($q) => $q->where(
                'paed_diary_entries.updated_at', '>=', $this->data->sinceTimestamp($request->input('updated_since'))
            ));
    }

    /**
     * Seitenweise Ausgabe; mit `search` über die Volltextsuche (Text ist verschlüsselt gespeichert).
     */
    private function paginated($query, IndexPaedDiaryEntriesRequest $request, bool $withStudents = false)
    {
        $perPage = (int) $request->input('per_page', 25);
        $meta = [];

        if ($request->filled('search')) {
            [$entries, $meta] = $this->search->paginate($query, (string) $request->input('search'), $perPage, max(1, $request->integer('page', 1)));
        } else {
            $entries = $query->paginate($perPage)->withQueryString();
        }

        if ($withStudents) {
            // Schülernamen für den Feed (nur Vorname + Initial, wie in der App üblich)
            $entries->getCollection()->each(fn (PaedDiaryEntry $e) => $e->setAttribute('students_brief', $e->schueler->map(fn ($s) => [
                'id' => (int) $s->id,
                'firstname' => $s->vorname,
                'lastname_initial' => $s->nachname ? mb_substr($s->nachname, 0, 1) . '.' : null,
            ])->values()));
        }

        return PaedDiaryEntryResource::collection($entries)->additional(['meta' => $meta]);
    }

    private function createEntry(int $klasseId, array $schuelerIds, Request $request, User $user, ?int $categoryId): PaedDiaryEntry
    {
        $date = Carbon::parse($request->input('entry_date'));
        $dossierOnly = $request->boolean('is_dossier_only');
        // API-Standard: Beobachtungen sind abgeschlossen; offene Notizen (is_completed=false)
        // laufen wie im Web-Frontend über mehrere Tage weiter. Vertrauliche Einträge sind immer abgeschlossen.
        $completed = $dossierOnly || ($request->has('is_completed') ? $request->boolean('is_completed') : true);

        $entry = PaedDiaryEntry::create([
            'klasse_id' => $klasseId,
            'user_id' => $user->id,
            'datum' => $date->toDateString(),
            'content' => trim((string) $request->input('content')),
            'completed_at' => $completed ? Carbon::now() : null,
            'category_id' => $categoryId,
            'dossier_only' => $dossierOnly,
        ]);
        $entry->schueler()->sync($schuelerIds);

        $this->entries->forgetWeekCache($klasseId, $date);

        return $entry;
    }

    /**
     * @return int|null|false  ID, null (keine Kategorie) oder false (nicht erlaubt)
     */
    private function resolveCategoryId($categoryId, User $user): int|null|false
    {
        if ($categoryId === null || $categoryId === '') {
            return null;
        }

        $exists = PaedDiaryCategory::whereKey($categoryId)->forUser($user->id)->exists();

        return $exists ? (int) $categoryId : false;
    }

    private function loadEntry(PaedDiaryEntry $entry): PaedDiaryEntry
    {
        return $entry->load(['category:id,name,color', 'user:id,name', 'schueler:schueler.id']);
    }

    private function conflict(PaedDiaryEntryResource $current): JsonResponse
    {
        return response()->json([
            'message' => 'Der Eintrag wurde zwischenzeitlich geändert.',
            'data' => $current->resolve(request()),
        ], 409);
    }

    private function unprocessable(string $field, string $message): JsonResponse
    {
        return response()->json([
            'message' => 'Die übermittelten Daten sind ungültig.',
            'errors' => [$field => [$message]],
        ], 422);
    }
}
