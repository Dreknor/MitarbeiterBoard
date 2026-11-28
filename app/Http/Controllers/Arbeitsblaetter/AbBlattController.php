<?php

namespace App\Http\Controllers\Arbeitsblaetter;

use App\Exceptions\VersionKonfliktException;
use App\Http\Controllers\Controller;
use App\Models\Arbeitsblaetter\AbProfilVorlage;
use App\Models\Arbeitsblaetter\AngepasstesBlatt;
use App\Models\Schueler;
use App\Models\Wochenplan\WpFach;
use App\Services\Arbeitsblaetter\AbKiBudget;
use App\Services\Arbeitsblaetter\AbQuellenFehler;
use App\Services\Arbeitsblaetter\AbQuellenService;
use App\Services\Arbeitsblaetter\AbSatzService;
use App\Services\Arbeitsblaetter\AngepasstesBlattService;
use App\Services\Arbeitsblaetter\Ki\AbErkenner;
use App\Services\Arbeitsblaetter\SehprofilService;
use App\Support\AbProfil;
use App\Support\AbStruktur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Bibliothek, Erfassung und Prüf-Editor der Angepassten Arbeitsblätter.
 * Alle Regeln liegen im {@see AngepasstesBlattService}; Zugriff über die AngepasstesBlattPolicy.
 */
class AbBlattController extends Controller
{
    public function __construct(
        private AngepasstesBlattService $service,
        private AbSatzService $satz,
        private SehprofilService $profile,
    ) {
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', AngepasstesBlatt::class);
        $filter = $request->only(['q', 'fach', 'klassenstufe', 'ansicht']);
        $filter['ansicht'] = in_array($filter['ansicht'] ?? 'alle', ['alle', 'meine', 'zur_pruefung', 'freigegeben'], true) ? ($filter['ansicht'] ?? 'alle') : 'alle';

        return view('arbeitsblaetter.index', [
            'blaetter' => $this->service->bibliothek($request->user(), $filter),
            'filter' => $filter,
            'faecher' => WpFach::orderBy('name')->get(['id', 'name']),
            'offen' => $this->service->offeneFuer($request->user()),
            'profilkinder' => $this->profile->kinderMitProfil()->count(),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', AngepasstesBlatt::class);
        $erkenner = app(AbErkenner::class);

        return view('arbeitsblaetter.create', [
            'faecher' => WpFach::orderBy('name')->get(['id', 'name']),
            'kiAktiv' => $erkenner->konfiguriert(),
            'kiGesperrt' => app(AbKiBudget::class)->sperrgrund(),
            'maxSeiten' => (int) config('arbeitsblaetter.max_seiten', 10),
            'endungen' => AbQuellenService::ERLAUBTE_ENDUNGEN,
            'ziele' => $this->schnellZiele($request),
        ]);
    }

    // ─── Schnellmodus: hochladen → fertige Fassungen ─────────────────────────

    /**
     * Kinder mit aktivem Profil (eigene Klassen zuerst) und Profil-Vorlagen für die Auswahl „Für wen?“.
     *
     * @return array{kinder: array, vorlagen: array}
     */
    private function schnellZiele(Request $request): array
    {
        $meine = $request->user()->paedKlassenIds()->all();

        return [
            'kinder' => $this->profile->kinderMitProfil()
                ->sortBy(fn ($k) => [in_array($k->klasse_id, $meine, false) ? 0 : 1, $k->klasse?->name ?? 'zzz', $k->nachname, $k->vorname])
                ->map(fn ($k) => [
                    'id' => $k->id,
                    'name' => $k->vorname.' '.$k->nachname,
                    'klasse' => $k->klasse?->name ?? 'Ohne Klasse',
                    'meine' => in_array($k->klasse_id, $meine, false),
                    'profil' => AbProfil::kurz($this->profile->wirksameWerte($k) ?? []),
                ])->values()->all(),
            'vorlagen' => $this->profile->vorlagen()->map(fn ($v) => ['id' => $v->id, 'name' => $v->name, 'profil' => AbProfil::kurz($v->wirksameWerte())])->values()->all(),
        ];
    }

    /** Schnellmodus: Datei(en) hochladen und für die gewählten Kinder/Vorlagen fertig setzen lassen. */
    public function schnellStore(Request $request)
    {
        $this->authorize('create', AngepasstesBlatt::class);
        $max = (int) config('arbeitsblaetter.max_seiten', 10);
        $daten = $request->validate([
            'dateien' => 'required|array|max:'.$max,
            'dateien.*' => 'file|mimes:'.AbQuellenService::ERLAUBTE_ENDUNGEN.'|max:'.config('arbeitsblaetter.max_upload_kb', 20480),
            'kinder' => 'nullable|array',
            'kinder.*' => 'integer',
            'vorlagen' => 'nullable|array',
            'vorlagen.*' => 'integer',
        ], [
            'dateien.required' => 'Bitte ein Foto oder eine Datei auswählen.',
            'dateien.max' => 'Höchstens '.$max.' Seiten pro Blatt.',
            'dateien.*.mimes' => 'Erlaubt sind PDF, Fotos (JPG/PNG) und Word (DOC/DOCX/ODT).',
            'dateien.*.max' => 'Eine Datei darf höchstens 20 MB groß sein.',
        ]);

        $kinder = array_values(array_intersect(array_map('intval', $daten['kinder'] ?? []), $this->profile->kinderMitProfil()->pluck('id')->all()));
        $vorlagen = array_values(array_intersect(array_map('intval', $daten['vorlagen'] ?? []), AbProfilVorlage::pluck('id')->all()));
        if (! $kinder && ! $vorlagen) {
            throw ValidationException::withMessages(['kinder' => 'Bitte mindestens ein Kind oder eine Profil-Vorlage auswählen.']);
        }
        $erkenner = app(AbErkenner::class);
        $grund = ! $erkenner->konfiguriert() ? 'Für den Schnellmodus ist eine KI-Erkennung nötig – bitte den erweiterten Modus nutzen.' : app(AbKiBudget::class)->sperrgrund();
        if ($grund) {
            throw ValidationException::withMessages(['dateien' => $grund]);
        }

        try {
            $blatt = $this->service->ausUploads(['schnell_ziele' => ['kinder' => $kinder, 'vorlagen' => $vorlagen]], $request->file('dateien', []), 'upload', $request->user());
        } catch (AbQuellenFehler $e) {
            throw ValidationException::withMessages(['dateien' => $e->getMessage()]);
        }

        $ziel = route('arbeitsblaetter.schnell', $blatt);
        if ($request->expectsJson()) {
            return $this->sofort(response()->json(['url' => $ziel, 'id' => $blatt->id]));
        }

        return redirect($ziel);
    }

    /** Statusseite des Schnellmodus: Erkennung abwarten, Abbildungen ausschneiden, Fassungen herunterladen. */
    public function schnell(Request $request, AngepasstesBlatt $blatt)
    {
        $this->authorize('view', $blatt);
        if (! $blatt->schnell_ziele) {
            return redirect()->route('arbeitsblaetter.edit', $blatt);
        }
        $this->haengendeBeenden($blatt);

        return view('arbeitsblaetter.schnell', [
            'blatt' => $blatt,
            'daten' => $this->blattArray($blatt, $request),
            'fertig' => $blatt->istFreigegeben() ? $this->schnellFassungen($blatt, $request) : null,
        ]);
    }

    /**
     * Schnellmodus abschließen: ohne Prüfung freigeben und die Fassungen der gewählten Kinder/Vorlagen
     * setzen. Leere Bausteine (Prüfhinweise) werden als Hinweis zurückgegeben, blockieren aber nicht.
     */
    public function schnellFertig(Request $request, AngepasstesBlatt $blatt): JsonResponse
    {
        $this->authorize('update', $blatt);
        $request->validate(['version' => 'required|integer']);
        abort_unless($blatt->schnell_ziele, 404);
        if ($blatt->status === AngepasstesBlatt::ERKENNUNG) {
            return response()->json(['message' => 'Die Erkennung läuft noch.'], 409);
        }
        if (empty($blatt->strukturGueltig()['bausteine'])) {
            return response()->json(['message' => 'Es wurde nichts erkannt – bitte im erweiterten Modus erfassen.'], 422);
        }

        try {
            $this->service->versionPruefen($blatt, $request->input('version'));
            if (! $blatt->istFreigegeben()) {
                $this->service->freigeben($blatt, $request->user());
            }
        } catch (VersionKonfliktException) {
            return response()->json(['message' => 'Das Blatt wurde zwischenzeitlich geändert. Bitte neu laden.'], 409);
        }

        return response()->json($this->schnellFassungen($blatt->fresh(), $request));
    }

    /** Fassungen der Schnell-Ziele (wiederverwendet oder neu gesetzt) mit Download-Links. */
    private function schnellFassungen(AngepasstesBlatt $blatt, Request $request): array
    {
        $ziele = (array) $blatt->schnell_ziele;
        $erlaubt = $this->profile->kinderMitProfil()->pluck('id')->all();
        $fassungen = app(\App\Services\Arbeitsblaetter\AbAusgabeService::class)->erzeugen(
            $blatt,
            array_values(array_intersect(array_map('intval', $ziele['kinder'] ?? []), $erlaubt)),
            array_map('intval', $ziele['vorlagen'] ?? []),
            $request->user(),
        );

        return [
            'fassungen' => $fassungen->map(fn ($f) => [
                'id' => $f->id,
                'empfaenger' => $f->empfaenger,
                'pdf' => route('arbeitsblaetter.fassungen.datei', [$blatt, $f->id, 'pdf']),
                'ansehen' => route('arbeitsblaetter.fassungen.datei', [$blatt, $f->id, 'pdf']).'?inline=1',
                'docx' => $f->docx_pfad ? route('arbeitsblaetter.fassungen.datei', [$blatt, $f->id, 'docx']) : null,
            ])->values()->all(),
            'hinweise' => array_values(array_merge((array) $blatt->ki_hinweise, array_column(AbStruktur::pruefhinweise($blatt->strukturGueltig()), 'text'))),
            'titel' => $blatt->titel,
        ];
    }

    public function store(Request $request)
    {
        $this->authorize('create', AngepasstesBlatt::class);
        $max = (int) config('arbeitsblaetter.max_seiten', 10);
        $daten = $request->validate([
            'titel' => 'nullable|string|max:200',
            'wp_fach_id' => 'nullable|integer|exists:wp_faecher,id',
            'klassenstufe' => 'nullable|integer|min:1|max:13',
            'art' => 'required|in:kamera,upload,manuell',
            'dateien' => 'required_unless:art,manuell|array|max:'.$max,
            'dateien.*' => 'file|mimes:'.AbQuellenService::ERLAUBTE_ENDUNGEN.'|max:'.config('arbeitsblaetter.max_upload_kb', 20480),
            'erkennen' => 'sometimes|boolean',
        ], [
            'dateien.required_unless' => 'Bitte ein Foto oder eine Datei auswählen.',
            'dateien.max' => 'Höchstens '.$max.' Seiten pro Blatt.',
            'dateien.*.mimes' => 'Erlaubt sind PDF, Fotos (JPG/PNG) und Word (DOC/DOCX/ODT).',
            'dateien.*.max' => 'Eine Datei darf höchstens 20 MB groß sein.',
        ]);

        $kopf = array_intersect_key($daten, array_flip(['titel', 'wp_fach_id', 'klassenstufe']));

        if ($daten['art'] === 'manuell') {
            $blatt = $this->service->leer($kopf, $request->user());
        } else {
            try {
                $blatt = $this->service->ausUploads($kopf, $request->file('dateien', []), $daten['art'], $request->user(), $request->boolean('erkennen', true));
            } catch (AbQuellenFehler $e) {
                throw ValidationException::withMessages(['dateien' => $e->getMessage()]);
            }
        }

        $ziel = route('arbeitsblaetter.edit', $blatt);
        if ($request->expectsJson()) {
            return $this->sofort(response()->json(['url' => $ziel, 'id' => $blatt->id]));
        }

        return redirect($ziel);
    }

    public function edit(Request $request, AngepasstesBlatt $blatt)
    {
        $this->authorize('view', $blatt);
        $this->haengendeBeenden($blatt);

        return view('arbeitsblaetter.edit', [
            'blatt' => $blatt,
            'daten' => $this->blattArray($blatt, $request),
            'faecher' => WpFach::orderBy('name')->get(['id', 'name']),
            'vorschauProfile' => $this->vorschauProfile($request),
            'typen' => AbStruktur::TYPEN,
        ]);
    }

    public function daten(Request $request, AngepasstesBlatt $blatt): JsonResponse
    {
        $this->authorize('view', $blatt);
        $this->haengendeBeenden($blatt);

        return response()->json(['blatt' => $this->blattArray($blatt, $request)]);
    }

    public function update(Request $request, AngepasstesBlatt $blatt): JsonResponse
    {
        $this->authorize('update', $blatt);
        $daten = $request->validate([
            'version' => 'required|integer',
            'titel' => 'sometimes|string|max:200',
            'wp_fach_id' => 'sometimes|nullable|integer|exists:wp_faecher,id',
            'klassenstufe' => 'sometimes|nullable|integer|min:1|max:13',
            'struktur' => 'sometimes|array',
        ]);

        if (isset($daten['struktur']) && ($fehler = AbStruktur::pruefen($daten['struktur']))) {
            return response()->json(['message' => implode(' ', $fehler)], 422);
        }

        try {
            $blatt = $this->service->speichern($blatt, $daten['struktur'] ?? null, $daten['version'], array_intersect_key($daten, array_flip(['titel', 'wp_fach_id', 'klassenstufe'])));
        } catch (VersionKonfliktException $e) {
            return response()->json([
                'message' => 'Das Blatt wurde zwischenzeitlich von jemand anderem gespeichert.',
                'blatt' => $this->blattArray($blatt->fresh(), $request),
            ], 409);
        }

        return response()->json(['blatt' => $this->blattArray($blatt, $request), 'meldung' => 'Gespeichert.']);
    }

    /** HTML-Vorschau (gleiche Blade-Vorlage wie das PDF) mit wählbarem Profil. */
    public function vorschau(Request $request, AngepasstesBlatt $blatt)
    {
        $this->authorize('view', $blatt);
        $daten = $request->validate([
            'struktur' => 'sometimes|array',
            'titel' => 'sometimes|nullable|string|max:200',
            'profil' => 'nullable|string|max:40',
        ]);

        $werte = $this->profilWerte((string) ($daten['profil'] ?? ''), $request);
        $html = $this->satz->html($blatt, $werte, 'html', isset($daten['struktur']) ? AbStruktur::normalisieren($daten['struktur']) : null, null, $daten['titel'] ?? null);

        return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8', 'X-Frame-Options' => 'SAMEORIGIN']);
    }

    public function freigeben(Request $request, AngepasstesBlatt $blatt): JsonResponse
    {
        $this->authorize('update', $blatt);
        $request->validate(['version' => 'required|integer', 'trotzdem' => 'sometimes|boolean']);

        // Plausibilität: leere Tabellen/Kästen, Abbildungen ohne Bild → erst nach Rückfrage freigeben
        $hinweise = AbStruktur::pruefhinweise($blatt->strukturGueltig());
        if ($hinweise && ! $request->boolean('trotzdem')) {
            return response()->json([
                'message' => 'Vor der Freigabe bitte prüfen: '.implode(' ', array_column($hinweise, 'text')),
                'hinweise' => $hinweise,
            ], 422);
        }

        try {
            $this->service->versionPruefen($blatt, $request->input('version'));
            $blatt = $this->service->freigeben($blatt, $request->user());
        } catch (VersionKonfliktException) {
            return response()->json(['message' => 'Das Blatt wurde zwischenzeitlich geändert. Bitte neu laden.', 'blatt' => $this->blattArray($blatt->fresh(), $request)], 409);
        } catch (AbQuellenFehler $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $meldung = $blatt->quelle_wp_datei_id
            ? 'Freigegeben. Die angepassten Fassungen werden jetzt in den Kinderplänen abgelegt.'
            : 'Freigegeben. Über „Ausgabe“ entstehen die Fassungen pro Kind.';

        return response()->json(['blatt' => $this->blattArray($blatt->fresh(), $request), 'meldung' => $meldung]);
    }

    public function erkennen(Request $request, AngepasstesBlatt $blatt): JsonResponse
    {
        $this->authorize('update', $blatt);
        if ($blatt->status === AngepasstesBlatt::ERKENNUNG) {
            return response()->json(['message' => 'Die Erkennung läuft bereits.'], 422);
        }
        $grund = $this->service->erkennungStarten($blatt, $request->user());
        if ($grund) {
            return response()->json(['message' => $grund], 422);
        }

        return $this->sofort(response()->json(['blatt' => $this->blattArray($blatt->fresh(), $request), 'meldung' => 'Die Erkennung wurde gestartet.']));
    }

    /**
     * Läuft die Erkennung ohne Queue-Worker nach der Antwort im selben Prozess, muss der Browser
     * die Antwort als vollständig erkennen (Content-Length, Verbindung schließen) – sonst wartet
     * er, bis die Erkennung fertig ist.
     */
    private function sofort(JsonResponse $antwort): JsonResponse
    {
        if (config('queue.default') === 'sync') {
            $antwort->headers->set('Content-Length', (string) strlen((string) $antwort->getContent()));
            $antwort->headers->set('Connection', 'close');
        }

        return $antwort;
    }

    /** KI-Protokoll des Blatts (Fehlersuche): Anfragen ohne Bilddaten, rohe Antworten, erkannter Inhalt. */
    public function kiProtokoll(Request $request, AngepasstesBlatt $blatt): JsonResponse
    {
        $this->authorize('update', $blatt);

        return response()->json(['eintraege' => \App\Models\Arbeitsblaetter\AbKiProtokoll::where('blatt_id', $blatt->id)
            ->latest('id')->limit(20)->get()
            ->map(fn ($e) => self::protokollArray($e))->values()]);
    }

    public static function protokollArray(\App\Models\Arbeitsblaetter\AbKiProtokoll $e): array
    {
        $antwort = json_decode((string) $e->antwort, true);
        $text = is_array($antwort) ? (
            collect(data_get($antwort, 'candidates.0.content.parts', []))->reject(fn ($t) => ! empty($t['thought']))->pluck('text')->implode('')
            ?: collect(data_get($antwort, 'content', []))->where('type', 'text')->pluck('text')->implode('')
            ?: (string) (is_string(data_get($antwort, 'choices.0.message.content')) ? data_get($antwort, 'choices.0.message.content') : '')
        ) : '';
        $inhalt = $text !== '' ? \App\Services\Arbeitsblaetter\Ki\ErkennerVorgaben::jsonLesen($text) : null;
        $huebsch = fn ($wert) => json_encode($wert, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return [
            'id' => $e->id,
            'zeit' => $e->created_at?->format('d.m.Y H:i:s'),
            'anbieter' => $e->anbieter,
            'modell' => $e->modell,
            'http_status' => $e->http_status,
            'dauer' => number_format($e->dauer_ms / 1000, 1, ',', '.').' s',
            'fehler' => $e->fehler,
            'anfrage' => (string) $e->anfrage,
            'antwort' => is_array($antwort) ? $huebsch($antwort) : (string) $e->antwort,
            'inhalt' => $inhalt !== null ? $huebsch($inhalt) : ($text !== '' ? $text : null),
            'nutzung' => is_array($antwort) ? (data_get($antwort, 'usageMetadata') ?? data_get($antwort, 'usage')) : null,
            'blatt_id' => $e->blatt_id,
        ];
    }

    /**
     * Ohne Queue-Worker: die geöffnete Seite startet den gemerkten Erkennungsauftrag mit dieser (lange
     * laufenden) Anfrage im Hintergrund und fragt parallel `daten` ab.
     */
    public function erkennenAusfuehren(Request $request, AngepasstesBlatt $blatt): JsonResponse
    {
        $this->authorize('view', $blatt);
        @ignore_user_abort(true);
        @set_time_limit(0);
        $this->service->erkennungAusfuehren($blatt);

        return response()->json(['blatt' => $this->blattArray($blatt->fresh(), $request)]);
    }

    public function erkennenAbbrechen(Request $request, AngepasstesBlatt $blatt): JsonResponse
    {
        $this->authorize('update', $blatt);
        $blatt = $this->service->erkennungAbbrechen($blatt);

        return response()->json(['blatt' => $this->blattArray($blatt->fresh(), $request), 'meldung' => 'Erkennung abgebrochen – Sie können das Blatt jetzt selbst erfassen.']);
    }

    private function haengendeBeenden(AngepasstesBlatt $blatt): void
    {
        if ($blatt->status === AngepasstesBlatt::ERKENNUNG && $this->service->haengendeErkennungenBeenden($blatt)) {
            $blatt->refresh();
        }
    }

    public function duplizieren(Request $request, AngepasstesBlatt $blatt)
    {
        $this->authorize('view', $blatt);
        $this->authorize('create', AngepasstesBlatt::class);
        $kopie = $this->service->duplizieren($blatt, $request->user());

        return redirect()->route('arbeitsblaetter.edit', $kopie)->with(['type' => 'success', 'Meldung' => 'Kopie angelegt.']);
    }

    public function destroy(Request $request, AngepasstesBlatt $blatt)
    {
        $this->authorize('delete', $blatt);
        $this->service->loeschen($blatt);

        return redirect()->route('arbeitsblaetter.index')->with(['type' => 'success', 'Meldung' => 'Das Blatt „'.$blatt->titel.'“ wurde gelöscht.']);
    }

    /** Original (Seitenbild/PDF) – nur über diese Route mit Policy. */
    public function original(Request $request, AngepasstesBlatt $blatt, int $media)
    {
        $this->authorize('view', $blatt);

        return $this->mediaAntwort($blatt, $media, AngepasstesBlatt::ORIGINALE);
    }

    /** Abbildung; `?darstellung=kontrast|invertiert` liefert sie wie im PDF aufbereitet (Vorschau). */
    public function abbildung(Request $request, AngepasstesBlatt $blatt, int $media)
    {
        $this->authorize('view', $blatt);
        $darstellung = (string) $request->query('darstellung', '');
        if (in_array($darstellung, ['kontrast', 'invertiert'], true)) {
            $m = $blatt->getMedia(AngepasstesBlatt::ABBILDUNGEN)->firstWhere('id', $media);
            abort_unless($m, 404);
            $png = $this->satz->bildInhalt($m, true, $darstellung === 'invertiert');
            abort_unless($png, 404);

            return response($png, 200, ['Content-Type' => 'image/png', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=3600']);
        }

        return $this->mediaAntwort($blatt, $media, AngepasstesBlatt::ABBILDUNGEN);
    }

    public function abbildungStore(Request $request, AngepasstesBlatt $blatt): JsonResponse
    {
        $this->authorize('update', $blatt);
        $request->validate(['bild' => 'required|file|mimes:png,jpg,jpeg|max:10240']);

        $media = $this->service->abbildungSpeichern($blatt, $request->file('bild'), $request->boolean('feinschnitt'));
        abort_unless($media, 422, 'Die Abbildung konnte nicht gespeichert werden.');

        return response()->json([
            'id' => $media->id,
            'url' => route('arbeitsblaetter.abbildungen.show', [$blatt, $media->id]),
        ]);
    }

    /** Schriftdateien für die HTML-Vorschau (gleiche Schrift wie im PDF). */
    public function schrift(string $schrift, string $schnitt)
    {
        abort_unless(isset(AbSatzService::SCHRIFT_DATEIEN[$schrift]) && in_array($schnitt, ['normal', 'fett'], true), 404);
        $pfad = AbSatzService::schriftPfad($schrift, $schnitt === 'fett' ? 1 : 0);
        abort_unless($pfad, 404);

        return response()->file($pfad, ['Content-Type' => 'font/ttf', 'Cache-Control' => 'public, max-age=2592000']);
    }

    // ─── intern ───────────────────────────────────────────────────────────────

    private function mediaAntwort(AngepasstesBlatt $blatt, int $mediaId, string $sammlung)
    {
        /** @var Media|null $media */
        $media = $blatt->getMedia($sammlung)->firstWhere('id', $mediaId);
        abort_unless($media && is_file($media->getPath()), 404);

        $mime = (string) ($media->getCustomProperty('mime') ?: $media->mime_type ?: 'application/octet-stream');
        $name = str_replace('"', '', $media->file_name);

        return response()->file($media->getPath(), [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.Str::ascii($name).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /** Profile, mit denen die Vorschau angezeigt werden kann (Vorlagen + Kinder mit Profil). */
    private function vorschauProfile(Request $request): array
    {
        $profile = [['wert' => '', 'label' => 'Standard ('.AbProfil::kurz(AbProfil::standard()).')']];
        foreach ($this->profile->vorlagen() as $v) {
            $profile[] = ['wert' => 'v'.$v->id, 'label' => 'Vorlage „'.$v->name.'“ – '.AbProfil::kurz($v->wirksameWerte())];
        }
        foreach ($this->profile->kinderMitProfil() as $kind) {
            $profile[] = ['wert' => 'k'.$kind->id, 'label' => $kind->vorname.' '.$kind->nachname.($kind->klasse ? ' ('.$kind->klasse->name.')' : '')];
        }

        return $profile;
    }

    /** „v12“ = Vorlage, „k34“ = Kind mit Profil, sonst Standard. */
    private function profilWerte(string $profil, Request $request): array
    {
        if (preg_match('/^v(\d+)$/', $profil, $m) && ($v = AbProfilVorlage::find((int) $m[1]))) {
            return $v->wirksameWerte();
        }
        if (preg_match('/^k(\d+)$/', $profil, $m) && ($kind = Schueler::find((int) $m[1]))) {
            return $this->profile->wirksameWerte($kind) ?? AbProfil::standard();
        }

        return AbProfil::standard();
    }

    private function blattArray(AngepasstesBlatt $blatt, Request $request): array
    {
        $user = $request->user();
        $originale = $blatt->getMedia(AngepasstesBlatt::ORIGINALE);
        $wochenplan = null;
        if ($blatt->quelle_wp_datei_id && ($datei = $blatt->quelleWpDatei)) {
            $plan = $datei->plan;
            $wochenplan = [
                'datei' => $datei->name,
                'plan' => $plan?->name,
                'klasse' => $plan?->klasse?->name,
                'url' => $plan && $user->can('view', $plan) ? route('wp.edit', $plan) : null,
                'fassungen' => \App\Models\Wochenplan\WpDatei::where('ersetzt_wp_datei_id', $datei->id)->count(),
            ];
        }

        return [
            'id' => $blatt->id,
            'titel' => $blatt->titel,
            'wp_fach_id' => $blatt->wp_fach_id,
            'klassenstufe' => $blatt->klassenstufe,
            'status' => $blatt->status,
            'status_text' => $blatt->status_text,
            'quelle' => $blatt->quelle,
            'version' => (int) $blatt->version,
            'struktur' => $blatt->strukturGueltig(),
            'fehler' => $blatt->fehler,
            'freigegeben_at' => $blatt->freigegeben_at?->format('d.m.Y H:i'),
            'pruefer' => $blatt->pruefer?->name,
            'ersteller' => $blatt->ersteller?->name,
            'darf_bearbeiten' => $user->can('update', $blatt),
            'darf_ausgeben' => $user->can('ausgeben', $blatt),
            'erkennung_wartet' => $this->service->erkennungWartet($blatt),
            'erkennung_grund' => $blatt->status === AngepasstesBlatt::ERKENNUNG ? null : $this->service->erkennungGesperrt($blatt),
            'ki' => $blatt->ki_anbieter ? ['anbieter' => $blatt->ki_anbieter, 'modell' => $blatt->ki_modell, 'seiten' => $blatt->ki_seiten] : null,
            'ki_hinweise' => array_values((array) $blatt->ki_hinweise),
            'pruefhinweise' => AbStruktur::pruefhinweise($blatt->strukturGueltig()),
            'originale' => $originale->map(fn (Media $m) => [
                'id' => $m->id,
                'url' => route('arbeitsblaetter.originale.show', [$blatt, $m->id]),
                'mime' => (string) ($m->getCustomProperty('mime') ?: $m->mime_type),
                'name' => $m->file_name,
            ])->values(),
            'abbildungen' => $blatt->getMedia(AngepasstesBlatt::ABBILDUNGEN)->mapWithKeys(fn (Media $m) => [
                $m->id => route('arbeitsblaetter.abbildungen.show', [$blatt, $m->id]),
            ]),
            'wochenplan' => $wochenplan,
            'urls' => [
                'speichern' => route('arbeitsblaetter.update', $blatt),
                'daten' => route('arbeitsblaetter.daten', $blatt),
                'vorschau' => route('arbeitsblaetter.vorschau', $blatt),
                'freigeben' => route('arbeitsblaetter.freigeben', $blatt),
                'erkennen' => route('arbeitsblaetter.erkennen', $blatt),
                'abbrechen' => route('arbeitsblaetter.erkennen.abbrechen', $blatt),
                'ausfuehren' => route('arbeitsblaetter.erkennen.ausfuehren', $blatt),
                'protokoll' => route('arbeitsblaetter.ki-protokoll', $blatt),
                'abbildung' => route('arbeitsblaetter.abbildungen.store', $blatt),
                'ausgabe' => route('arbeitsblaetter.ausgabe', $blatt),
                'schnell' => $blatt->schnell_ziele ? route('arbeitsblaetter.schnell', $blatt) : null,
            ],
        ];
    }
}
