<?php

namespace App\Http\Controllers\Wiki;

use App\Http\Controllers\Controller;
use App\Models\WikiSeite;
use App\Services\Wiki\WikiAnhangService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Wiki-Anhänge: Auslieferung nur mit Leserecht auf die Seite (private Disk `wiki`),
 * Hochladen (Editor/Bild-Upload, Dateiliste) und Entfernen mit Bearbeitungsrecht.
 */
class WikiAnhangController extends Controller
{
    private const INLINE = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'video/mp4', 'audio/mpeg'];

    public function __construct(private WikiAnhangService $anhaenge)
    {
    }

    public function show(Media $media): BinaryFileResponse
    {
        abort_unless($media->model_type === WikiSeite::class, 404);
        $seite = WikiSeite::withTrashed()->find($media->model_id);
        abort_unless($seite, 404);
        $this->authorize('view', $seite);

        $pfad = $media->getPath();
        abort_unless(is_file($pfad), 404, 'Datei nicht gefunden');

        $inline = in_array($media->mime_type, self::INLINE, true);
        $antwort = response()->file($pfad, [
            'Content-Type' => $inline ? $media->mime_type : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=86400',
        ]);
        $antwort->setContentDisposition($inline ? 'inline' : 'attachment', $media->file_name, Str::ascii($media->file_name));

        return $antwort;
    }

    /**
     * Upload aus dem Editor (TinyMCE: Antwort {location}) oder der Dateiliste (mehrere Dateien).
     */
    public function store(Request $request, WikiSeite $seite): JsonResponse
    {
        $this->authorize('update', $seite);
        $request->validate([
            'datei' => ['required_without:dateien', 'file', 'max:'.WikiAnhangService::MAX_DATEI_KB, 'mimes:'.implode(',', WikiAnhangService::DATEI_MIMES)],
            'dateien' => ['required_without:datei', 'array', 'max:10'],
            'dateien.*' => ['file', 'max:'.WikiAnhangService::MAX_DATEI_KB, 'mimes:'.implode(',', WikiAnhangService::DATEI_MIMES)],
        ], [], ['datei' => 'Datei', 'dateien.*' => 'Datei']);

        // `datei` = Bild aus dem Editor, `dateien` = Dateiliste (auch Bilder landen dann in der Download-Liste)
        $alsDatei = ! $request->hasFile('datei');
        $dateien = $alsDatei ? $request->file('dateien') : [$request->file('datei')];
        $ergebnis = [];
        foreach ($dateien as $datei) {
            $media = $this->anhaenge->hochladen($seite, $datei, $request->user(), $alsDatei);
            $ergebnis[] = $this->alsArray($media);
        }

        return response()->json([
            'location' => $ergebnis[0]['url'],
            'dateien' => $ergebnis,
        ], 201);
    }

    public function destroy(Request $request, WikiSeite $seite, Media $media): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $seite);
        $this->anhaenge->entfernen($seite, $media, $request->user());

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Datei entfernt.']);
        }

        return back()->with(['type' => 'success', 'Meldung' => 'Datei entfernt. Ältere Fassungen behalten ihren Link noch '.WikiAnhangService::AUFBEWAHRUNG_TAGE.' Tage.']);
    }

    private function alsArray(Media $media): array
    {
        return [
            'id' => $media->id,
            'name' => $media->file_name,
            'url' => $this->anhaenge->url($media),
            'bild' => str_starts_with((string) $media->mime_type, 'image/'),
            'groesse' => $media->human_readable_size,
        ];
    }
}
