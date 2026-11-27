<?php

namespace App\Http\Controllers\Ergebnisboards;

use App\Exceptions\VersionKonfliktException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ergebnisboards\ErgebnisboardRequest;
use App\Http\Requests\Ergebnisboards\ErgebnisKarteRequest;
use App\Models\Ergebnisboard;
use App\Models\ErgebnisKarte;
use App\Models\Meeting;
use App\Models\Theme;
use App\Models\User;
use App\Services\Ergebnisboards\ErgebnisboardAbstimmungService;
use App\Services\Ergebnisboards\ErgebnisboardAufgabenService;
use App\Services\Ergebnisboards\ErgebnisboardService;
use App\Services\Ergebnisboards\ErgebnisboardVerlaufService;
use App\Support\ErgebnisboardVorlagen;
use App\Support\PdfLayout;
use Barryvdh\DomPDF\Facade\Pdf;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Ergebnisboards: Liste, Anlegen, Board-Seite und alle JSON-Aktionen der Alpine-Oberfläche.
 * Regeln liegen in ErgebnisboardService, Zugriff in ErgebnisboardPolicy.
 */
class ErgebnisboardController extends Controller
{
    /** Im Browser anzeigbare Anhänge; alles andere wird als Download ausgeliefert. */
    private const INLINE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'text/plain'];

    public function __construct(private ErgebnisboardService $service)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Ergebnisboard::class);
        $user = $request->user();
        $archiv = $request->boolean('archiv');
        $q = trim((string) $request->input('q'));

        $boards = Ergebnisboard::query()
            ->sichtbarFuer($user)
            ->when($archiv, fn ($b) => $b->whereNotNull('archiviert_at'), fn ($b) => $b->whereNull('archiviert_at'))
            ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w->where('titel', 'like', '%'.$q.'%')->orWhere('untertitel', 'like', '%'.$q.'%')))
            ->with(['ersteller', 'meeting'])
            ->withCount('karten')
            ->latest('updated_at')
            ->paginate(24)
            ->withQueryString();

        return view('ergebnisboards.index', [
            'boards' => $boards,
            'archiv' => $archiv,
            'q' => $q,
            'vorlagen' => ErgebnisboardVorlagen::alle(),
            'darfAnlegen' => $user->can('create', Ergebnisboard::class),
            'darfVorlagen' => $user->can('manageTemplates', Ergebnisboard::class),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Ergebnisboard::class);
        $user = $request->user();

        $meeting = $request->filled('meeting') ? Meeting::find($request->integer('meeting')) : null;
        if ($meeting && ! $user->can('view', $meeting)) {
            $meeting = null;
        }

        return view('ergebnisboards.create', [
            'vorlagen' => ErgebnisboardVorlagen::alle(),
            'gewaehlt' => $request->input('vorlage', 'aufwand_ertrag'),
            'meetings' => $this->meetingOptionen($user),
            'themen' => $this->themenOptionen($user),
            'meeting' => $meeting,
            'theme' => $request->filled('theme') ? Theme::find($request->integer('theme')) : null,
            'optionen' => $this->service->zugriffsOptionen(),
            'auswahl' => [],
        ]);
    }

    public function store(ErgebnisboardRequest $request): RedirectResponse
    {
        $this->authorize('create', Ergebnisboard::class);
        $daten = $request->daten();
        $this->verknuepfungenPruefen($request->user(), $daten);

        $board = $this->service->anlegen($daten, $request->user());

        return redirect()->route('ergebnisboards.show', $board)->with([
            'type' => 'success',
            'Meldung' => 'Board angelegt.',
        ]);
    }

    public function show(Request $request, Ergebnisboard $board): View
    {
        $this->authorize('view', $board);
        $user = $request->user();

        return view('ergebnisboards.show', [
            'board' => $board,
            'daten' => $this->service->toArray($board, $user),
            'personen' => $this->service->personenMitZugriff($board)
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'kuerzel' => $this->service->kuerzel($u->name)])->values()->all(),
            'farben' => ErgebnisboardVorlagen::FARBEN,
            'farbschemata' => ErgebnisboardVorlagen::FARBSCHEMATA,
            'optionen' => $user->can('manage', $board) ? $this->service->zugriffsOptionen() : null,
            'auswahl' => $user->can('manage', $board) ? $this->service->zugriffsAuswahl($board) : [],
            'meetings' => $user->can('update', $board) ? $this->meetingOptionen($user) : [],
            'themen' => $user->can('update', $board) ? $this->themenOptionen($user) : [],
            'darfDuplizieren' => $user->can('duplicate', $board),
            'teilenUrl' => app(\App\Services\Posts\PostService::class)->darfPosten($user)
                ? route('posts.create', ['ergebnisboard' => $board->id]) : null,
            'userId' => $user->id,
            'maxDateiKb' => ErgebnisboardService::MAX_DATEI_KB,
        ]);
    }

    /** GET – aktueller Stand als JSON (Neuladen nach Konflikt, Polling). */
    public function daten(Request $request, Ergebnisboard $board): JsonResponse
    {
        $this->authorize('view', $board);

        return $this->antwort($board, $request->user());
    }

    /** PUT – Kopf, Achsen/Legende, Farbschema, Verknüpfungen. */
    public function update(ErgebnisboardRequest $request, Ergebnisboard $board): JsonResponse
    {
        $this->authorize('update', $board);
        $daten = $request->daten();
        unset($daten['zugriffe']);
        $this->verknuepfungenPruefen($request->user(), $daten, $board);

        return $this->mitKonflikt($board, $request->user(), fn () => $this->service->aktualisieren($board, $daten, $request->version()));
    }

    /** PUT – Felder (Bereiche) umbenennen, einfärben, hinzufügen, entfernen, umsortieren. */
    public function bereiche(Request $request, Ergebnisboard $board): JsonResponse
    {
        $this->authorize('update', $board);
        $daten = $request->validate([
            'bereiche' => ['required', 'array', 'min:1', 'max:40'],
            'bereiche.*.schluessel' => ['nullable', 'string', 'max:64'],
            'bereiche.*.titel' => ['required', 'string', 'max:80'],
            'bereiche.*.hinweis' => ['nullable', 'string', 'max:200'],
            'bereiche.*.badge' => ['nullable', 'string', 'max:60'],
            'bereiche.*.farbe' => ['nullable', 'string', 'in:'.implode(',', array_keys(ErgebnisboardVorlagen::FARBEN))],
            'bereiche.*.raster' => ['nullable', 'integer', 'in:1,2'],
            'bereiche.*.eltern' => ['nullable', 'string', 'max:64'],
            'bereiche.*.status' => ['nullable', 'string', 'in:'.implode(',', ErgebnisboardVorlagen::STATUS)],
            'version' => ['nullable', 'integer'],
        ]);

        return $this->mitKonflikt($board, $request->user(), fn () => $this->service->bereicheSpeichern(
            $board, $daten['bereiche'], isset($daten['version']) ? (int) $daten['version'] : null
        ));
    }

    /** POST – Zugriffe ersetzen. */
    public function zugriffe(Request $request, Ergebnisboard $board): JsonResponse
    {
        $this->authorize('manage', $board);
        $daten = $request->validate([
            'zugriffe' => ['present', 'array', 'max:200'],
            'zugriffe.*.typ' => ['required', 'in:user,group,role'],
            'zugriffe.*.id' => ['required', 'integer', 'min:1'],
            'zugriffe.*.recht' => ['required', 'in:lesen,schreiben,verwalten'],
            'meeting_lesen' => ['sometimes', 'boolean'],
        ]);

        $this->service->zugriffeSpeichern($board, $daten['zugriffe']);
        if (array_key_exists('meeting_lesen', $daten)) {
            $this->service->aktualisieren($board, ['meeting_lesen' => $daten['meeting_lesen']]);
        }

        return response()->json([
            'message' => 'Zugriffe gespeichert.',
            'data' => $this->service->toArray($board->fresh(), $request->user()),
            'auswahl' => $this->service->zugriffsAuswahl($board->fresh()),
            'personen' => $this->personen($board->fresh()),
        ]);
    }

    public function duplicate(Request $request, Ergebnisboard $board): RedirectResponse
    {
        $this->authorize('duplicate', $board);
        $kopie = $this->service->duplizieren($board, $request->user());

        return redirect()->route('ergebnisboards.show', $kopie)->with([
            'type' => 'success',
            'Meldung' => 'Kopie angelegt – Aufgaben, Anhänge und Zuständige wurden nicht übernommen.',
        ]);
    }

    public function archive(Request $request, Ergebnisboard $board): RedirectResponse
    {
        $this->authorize('manage', $board);
        $archivieren = $request->boolean('archivieren', true);
        $this->service->archivieren($board, $archivieren);

        return redirect()->route('ergebnisboards.show', $board)->with([
            'type' => 'success',
            'Meldung' => $archivieren ? 'Board archiviert.' : 'Board wiederhergestellt.',
        ]);
    }

    public function destroy(Ergebnisboard $board): RedirectResponse
    {
        $this->authorize('manage', $board);
        $this->service->loeschen($board);

        return redirect()->route('ergebnisboards.index')->with(['type' => 'success', 'Meldung' => 'Board gelöscht.']);
    }

    public function pdf(Request $request, Ergebnisboard $board, PdfLayout $layout): Response
    {
        $this->authorize('view', $board);

        $pdf = Pdf::loadView('pdf.ergebnisboard', [
            'board' => $board,
            'daten' => $this->service->toArray($board, $request->user()),
            'tabelle' => $this->pdfTabelle($board),
            'farben' => ErgebnisboardVorlagen::FARBEN,
            'kopf' => $layout->schulkopf(),
            'stand' => now(),
        ])->setPaper('a4', 'landscape');

        return response($layout->rendern($pdf), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.Str::slug($board->titel, '-', 'de').'.pdf"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    // ------------------------------------------------------------------
    // Karten
    // ------------------------------------------------------------------

    public function karteStore(ErgebnisKarteRequest $request, Ergebnisboard $board): JsonResponse
    {
        $this->authorize('update', $board);

        return $this->mitKonflikt($board, $request->user(), fn () => $this->service->karteSpeichern($board, null, $request->daten(), $request->version(), $request->user()), 201);
    }

    public function karteUpdate(ErgebnisKarteRequest $request, Ergebnisboard $board, ErgebnisKarte $karte): JsonResponse
    {
        $this->authorize('update', $board);

        return $this->mitKonflikt($board, $request->user(), fn () => $this->service->karteSpeichern($board, $karte, $request->daten(), $request->version(), $request->user()));
    }

    public function karteDestroy(Request $request, Ergebnisboard $board, ErgebnisKarte $karte): JsonResponse
    {
        $this->authorize('update', $board);
        $version = $request->filled('version') ? $request->integer('version') : null;

        return $this->mitKonflikt($board, $request->user(), fn () => $this->service->karteLoeschen($board, $karte, $version));
    }

    // ------------------------------------------------------------------
    // Abstimmung („Punkte kleben“)
    // ------------------------------------------------------------------

    /** PUT – Abstimmung starten/beenden/ausblenden, Punkte je Person, verdeckt (Schreibrecht). */
    public function abstimmung(Request $request, Ergebnisboard $board, ErgebnisboardAbstimmungService $abstimmung): JsonResponse
    {
        $this->authorize('update', $board);
        $daten = $request->validate([
            'status' => ['sometimes', 'in:'.implode(',', Ergebnisboard::ABSTIMMUNG_STATUS)],
            'punkte' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'verdeckt' => ['sometimes', 'boolean'],
        ], [], ['punkte' => 'Punkte je Person']);
        $abstimmung->einstellen($board, $daten);

        return $this->antwort($board->fresh(), $request->user());
    }

    public function abstimmungZuruecksetzen(Request $request, Ergebnisboard $board, ErgebnisboardAbstimmungService $abstimmung): JsonResponse
    {
        $this->authorize('update', $board);
        $abstimmung->zuruecksetzen($board);

        return $this->antwort($board->fresh(), $request->user(), 'Alle Punkte wurden zurückgesetzt.');
    }

    /** POST – eigenen Punkt setzen (+1) oder zurücknehmen (−1); ändert die Board-Version nicht. */
    public function stimme(Request $request, Ergebnisboard $board, ErgebnisKarte $karte, ErgebnisboardAbstimmungService $abstimmung): JsonResponse
    {
        $this->authorize('view', $board);
        $daten = $request->validate(['richtung' => ['required', 'integer', 'in:1,-1']]);
        $abstimmung->stimmen($board, $karte, $request->user(), (int) $daten['richtung']);

        return response()->json(['abstimmung' => $abstimmung->stand($board->fresh(), $request->user())]);
    }

    /** POST – gelöschte Karte zurückholen (Schreibrecht). */
    public function karteWiederherstellen(Request $request, Ergebnisboard $board, ErgebnisKarte $geloeschteKarte): JsonResponse
    {
        $this->authorize('update', $board);
        $this->service->karteWiederherstellen($board, $geloeschteKarte);

        return $this->antwort($board->fresh(), $request->user(), 'Karte wiederhergestellt.');
    }

    /** GET – Änderungsverlauf (neueste zuerst, 50 je Seite; `vor` = letzte geladene ID). */
    public function verlauf(Request $request, Ergebnisboard $board, ErgebnisboardVerlaufService $verlauf): JsonResponse
    {
        $this->authorize('view', $board);
        $daten = $request->validate([
            'filter' => ['nullable', 'in:alle,board,karten'],
            'karte' => ['nullable', 'integer'],
            'vor' => ['nullable', 'integer'],
        ]);

        return response()->json($verlauf->liste(
            $board,
            $daten['filter'] ?? 'alle',
            isset($daten['karte']) ? (int) $daten['karte'] : null,
            isset($daten['vor']) ? (int) $daten['vor'] : null,
        ) + ['darf_wiederherstellen' => $request->user()->can('update', $board)]);
    }

    public function sortierung(Request $request, Ergebnisboard $board): JsonResponse
    {
        $this->authorize('update', $board);
        $daten = $request->validate([
            'ordnung' => ['required', 'array'],
            'ordnung.*' => ['array'],
            'ordnung.*.*' => ['integer'],
            'version' => ['nullable', 'integer'],
        ]);

        return $this->mitKonflikt($board, $request->user(), fn () => $this->service->sortieren(
            $board, $daten['ordnung'], isset($daten['version']) ? (int) $daten['version'] : null
        ));
    }

    // ------------------------------------------------------------------
    // Aufgaben (Karte → Aufgabe im verknüpften Thema)
    // ------------------------------------------------------------------

    public function aufgabeStore(Request $request, Ergebnisboard $board, ErgebnisKarte $karte, ErgebnisboardAufgabenService $aufgaben): JsonResponse
    {
        $this->authorize('update', $board);
        $daten = $request->validate(['datum' => ['nullable', 'date', 'after_or_equal:today']]);
        $aufgaben->alsAufgabe($board, $karte, $request->user(), $daten['datum'] ?? null);

        return $this->antwort($board->fresh(), $request->user(), 'Aufgabe angelegt.', 201);
    }

    /** Zuständige (oder Personen mit Schreibrecht für ihren Anteil) erledigen die Aufgabe. */
    public function aufgabeErledigen(Request $request, Ergebnisboard $board, ErgebnisKarte $karte, ErgebnisboardAufgabenService $aufgaben): JsonResponse
    {
        $this->authorize('view', $board);
        $ok = $aufgaben->erledigen($karte, $request->user());

        return $this->antwort($board->fresh(), $request->user(), $ok ? 'Als erledigt markiert.' : 'Für Sie war hier nichts mehr offen.');
    }

    // ------------------------------------------------------------------
    // Anhänge
    // ------------------------------------------------------------------

    public function dateiStore(Request $request, Ergebnisboard $board, ErgebnisKarte $karte): JsonResponse
    {
        $this->authorize('update', $board);
        $request->validate([
            'dateien' => ['required', 'array', 'max:10'],
            'dateien.*' => ['file', 'max:'.ErgebnisboardService::MAX_DATEI_KB,
                'mimes:pdf,jpg,jpeg,png,gif,webp,txt,csv,doc,docx,xls,xlsx,ppt,pptx,odt,ods,odp,zip'],
        ], [], ['dateien.*' => 'Datei']);

        foreach ($request->file('dateien') as $datei) {
            $this->service->anhangHinzufuegen($board, $karte, $datei);
        }

        return $this->antwort($board->fresh(), $request->user(), 'Datei hochgeladen.', 201);
    }

    public function dateiShow(Ergebnisboard $board, ErgebnisKarte $karte, Media $media): BinaryFileResponse
    {
        $this->authorize('view', $board);
        abort_unless($media->model_type === ErgebnisKarte::class && (int) $media->model_id === (int) $karte->id, 404);

        $pfad = $media->getPath();
        abort_unless(is_file($pfad), 404, 'Datei nicht gefunden');

        $inline = in_array($media->mime_type, self::INLINE_MIME_TYPES, true);
        $antwort = response()->file($pfad, [
            'Content-Type' => $inline ? $media->mime_type : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $antwort->setContentDisposition($inline ? 'inline' : 'attachment', $media->file_name, Str::ascii($media->file_name));

        return $antwort;
    }

    public function dateiDestroy(Request $request, Ergebnisboard $board, ErgebnisKarte $karte, Media $media): JsonResponse
    {
        $this->authorize('update', $board);
        abort_unless($media->model_type === ErgebnisKarte::class && (int) $media->model_id === (int) $karte->id, 404);

        $this->service->anhangLoeschen($board, $media);

        return $this->antwort($board->fresh(), $request->user(), 'Datei entfernt.');
    }

    // ------------------------------------------------------------------
    // intern
    // ------------------------------------------------------------------

    private function antwort(Ergebnisboard $board, User $user, ?string $meldung = null, int $status = 200): JsonResponse
    {
        return response()->json(array_filter([
            'message' => $meldung,
            'data' => $this->service->toArray($board, $user),
        ], fn ($v) => $v !== null), $status);
    }

    private function mitKonflikt(Ergebnisboard $board, User $user, Closure $aktion, int $status = 200): JsonResponse
    {
        try {
            $aktion();
        } catch (VersionKonfliktException) {
            return response()->json([
                'message' => 'Das Board wurde zwischenzeitlich von jemand anderem geändert. Der aktuelle Stand wurde geladen.',
                'data' => $this->service->toArray($board->fresh(), $user),
            ], 409);
        }

        return $this->antwort($board->fresh(), $user, null, $status);
    }

    private function personen(Ergebnisboard $board): array
    {
        return $this->service->personenMitZugriff($board)
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'kuerzel' => $this->service->kuerzel($u->name)])->values()->all();
    }

    /** Sitzung nur verknüpfen, wenn sie sichtbar ist; Thema nur aus eigenen Gruppen oder der Sitzung. */
    private function verknuepfungenPruefen(User $user, array $daten, ?Ergebnisboard $board = null): void
    {
        if (! empty($daten['meeting_id']) && (int) $daten['meeting_id'] !== (int) $board?->meeting_id) {
            $meeting = Meeting::find($daten['meeting_id']);
            if (! $meeting || ! $user->can('view', $meeting)) {
                throw ValidationException::withMessages(['meeting_id' => 'Diese Sitzung können Sie nicht verknüpfen.']);
            }
        }
        if (! empty($daten['theme_id']) && (int) $daten['theme_id'] !== (int) $board?->theme_id) {
            if (! collect($this->themenOptionen($user))->pluck('id')->contains((int) $daten['theme_id'])) {
                throw ValidationException::withMessages(['theme_id' => 'Dieses Thema können Sie nicht verknüpfen.']);
            }
        }
    }

    /** @return array<int, array{id: int, titel: string}> */
    private function meetingOptionen(User $user): array
    {
        return Meeting::query()->visibleTo($user)
            ->where('date', '>=', now()->subMonths(6)->toDateString())
            ->orderByDesc('date')->limit(100)->get()
            ->map(fn (Meeting $m) => ['id' => $m->id, 'titel' => $this->service->meetingTitel($m)])->values()->all();
    }

    /** Offene Themen der eigenen Gruppen (Anker für Aufgaben aus Karten). */
    private function themenOptionen(User $user): array
    {
        $groupIds = $user->groups()->pluck('id')->filter()->all();

        return Theme::query()
            ->whereIn('group_id', $groupIds)
            ->where('completed', false)
            ->with('group:id,name')
            ->orderByDesc('date')->limit(200)->get()
            ->map(fn (Theme $t) => ['id' => $t->id, 'titel' => $t->theme.($t->group ? ' – '.$t->group->name : '')])->values()->all();
    }

    /**
     * Raster als Tabelle für DomPDF (kein CSS-Grid): Zellen mit colspan/rowspan.
     *
     * @return array<int, array<int, array{bereich: array, colspan: int, rowspan: int}|null>>
     */
    private function pdfTabelle(Ergebnisboard $board): array
    {
        $spalten = ErgebnisboardVorlagen::spaltenAnzahl($board->spalten);
        $haupt = array_values(array_filter($board->bereiche, fn ($b) => ($b['eltern'] ?? null) === null));
        $zeilen = max(1, ...array_map(fn ($b) => $b['zeile'] + $b['zeilen'] - 1, $haupt));
        $belegt = [];
        $tabelle = [];

        for ($z = 1; $z <= $zeilen; $z++) {
            $tabelle[$z] = [];
            for ($s = 1; $s <= $spalten; $s++) {
                if (isset($belegt[$z][$s])) {
                    continue;
                }
                $treffer = collect($haupt)->first(fn ($b) => $b['zeile'] === $z && $b['spalte'] === $s);
                if ($treffer) {
                    $cs = min($treffer['spalten'], $spalten - $s + 1);
                    $rs = min($treffer['zeilen'], $zeilen - $z + 1);
                    for ($zz = $z; $zz < $z + $rs; $zz++) {
                        for ($ss = $s; $ss < $s + $cs; $ss++) {
                            $belegt[$zz][$ss] = true;
                        }
                    }
                    $tabelle[$z][] = ['bereich' => $treffer, 'colspan' => $cs, 'rowspan' => $rs];
                } else {
                    $tabelle[$z][] = null;
                }
            }
        }

        return $tabelle;
    }
}
