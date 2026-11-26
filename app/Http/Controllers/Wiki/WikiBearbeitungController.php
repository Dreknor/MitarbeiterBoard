<?php

namespace App\Http\Controllers\Wiki;

use App\Exceptions\VersionKonfliktException;
use App\Http\Controllers\Controller;
use App\Http\Requests\WikiSeiteRequest;
use App\Models\WikiRevision;
use App\Models\WikiSeite;
use App\Models\WikiTag;
use App\Services\Wiki\WikiAnhangService;
use App\Services\Wiki\WikiDiff;
use App\Services\Wiki\WikiService;
use App\Services\Wiki\WikiZugriff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Wiki – Anlegen und Bearbeiten (Editor), Speichern mit optimistischer Sperre (409 + Vergleich),
 * Fassung wiederherstellen, Hinweis „wird gerade bearbeitet“, Tag-Vorschläge.
 */
class WikiBearbeitungController extends Controller
{
    public function __construct(private WikiService $wiki, private WikiAnhangService $anhaenge)
    {
    }

    public function create(Request $request): View
    {
        $this->authorize('create', WikiSeite::class);
        $titel = Str::limit(trim((string) $request->query('titel', '')), 150, '');
        $parent = $request->integer('parent') ?: null;
        if ($parent && ! isset(WikiZugriff::sichtbareIds($request->user())[$parent])) {
            $parent = null;
        }

        return view('wiki.bearbeiten', [
            'seite' => null,
            'titel' => $titel,
            'inhalt' => '',
            'parentId' => $parent,
            'tags' => [],
            'elternOptionen' => $this->wiki->elternOptionen($request->user()),
            'andere' => [],
            'dateien' => collect(),
        ]);
    }

    public function store(WikiSeiteRequest $request): JsonResponse|RedirectResponse
    {
        $this->authorize('create', WikiSeite::class);
        $daten = $request->daten();
        $this->elternPruefen($request, $daten['parent_id']);

        $seite = $this->wiki->anlegen($request->user(), $daten);

        return $this->gespeichert($request, $seite, 'Seite angelegt.', 201);
    }

    public function edit(Request $request, string $slug): View|RedirectResponse
    {
        [$seite] = $this->wiki->finde($slug);
        abort_unless($seite && ! $seite->trashed(), 404);
        $this->authorize('update', $seite);

        return view('wiki.bearbeiten', [
            'seite' => $seite,
            'titel' => $seite->titel,
            'inhalt' => $seite->inhalt_html,
            'parentId' => $seite->parent_id,
            'tags' => $seite->tags->pluck('name')->all(),
            'elternOptionen' => $seite->istStartseite() ? [] : $this->wiki->elternOptionen($request->user(), $seite),
            'andere' => $this->wiki->bearbeitungMelden($seite, $request->user()),
            'dateien' => $this->anhaenge->dateien($seite),
        ]);
    }

    public function update(WikiSeiteRequest $request, WikiSeite $seite, WikiDiff $diff): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $seite);
        $daten = $request->daten();
        if ($daten['parent_id'] !== $seite->parent_id) {
            $this->elternPruefen($request, $daten['parent_id']);
        }

        try {
            $seite = $this->wiki->speichern($seite, $request->user(), $daten, (int) $request->input('version'));
        } catch (VersionKonfliktException) {
            $aktuell = $seite->fresh()->load('geaendert');
            $unterschied = $diff->vergleich($aktuell->inhalt, $daten['inhalt']);

            return response()->json([
                'message' => 'Die Seite wurde zwischenzeitlich von '.$aktuell->geaendert->name.' geändert.',
                'konflikt' => [
                    'version' => $aktuell->version,
                    'titel' => $aktuell->titel,
                    'von' => $aktuell->geaendert->name,
                    'zeit' => $aktuell->bearbeitet_at?->format('d.m.Y H:i'),
                    'inhalt' => $aktuell->inhalt_html,
                    'diff' => $unterschied['html'],
                ],
            ], 409);
        }

        return $this->gespeichert($request, $seite, 'Seite gespeichert.');
    }

    public function wiederherstellen(Request $request, WikiSeite $seite, WikiRevision $revision): RedirectResponse
    {
        $this->authorize('update', $seite);
        abort_unless($revision->seite_id === $seite->id, 404);

        $this->wiki->wiederherstellen($seite, $revision, $request->user());

        return redirect()->to($seite->fresh()->url())->with(['type' => 'success', 'Meldung' => 'Fassung wiederhergestellt.']);
    }

    public function bearbeitung(Request $request, WikiSeite $seite): JsonResponse
    {
        $this->authorize('update', $seite);

        if ($request->boolean('ende')) {
            $this->wiki->bearbeitungBeenden($seite, $request->user());

            return response()->json(['andere' => []]);
        }

        return response()->json([
            'andere' => $this->wiki->bearbeitungMelden($seite, $request->user()),
            'version' => $seite->version,
        ]);
    }

    public function tagVorschlaege(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $tags = WikiTag::query()
            ->when($q !== '', fn ($t) => $t->where('name', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%'))
            ->withCount('seiten')
            ->orderByDesc('seiten_count')
            ->orderBy('name')
            ->limit(15)
            ->pluck('name');

        return response()->json(['tags' => $tags]);
    }

    // ------------------------------------------------------------------

    private function elternPruefen(Request $request, ?int $parentId): void
    {
        if ($parentId && ! isset(WikiZugriff::sichtbareIds($request->user())[$parentId])) {
            throw ValidationException::withMessages(['parent_id' => 'Die übergeordnete Seite ist nicht verfügbar.']);
        }
    }

    private function gespeichert(Request $request, WikiSeite $seite, string $meldung, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json([
                'message' => $meldung,
                'url' => $seite->url(),
                'id' => $seite->id,
                'version' => $seite->version,
                'slug' => $seite->slug,
            ], $status);
        }

        return redirect()->to($seite->url())->with(['type' => 'success', 'Meldung' => $meldung]);
    }
}
