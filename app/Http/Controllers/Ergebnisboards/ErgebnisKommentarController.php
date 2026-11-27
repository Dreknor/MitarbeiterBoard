<?php

namespace App\Http\Controllers\Ergebnisboards;

use App\Http\Controllers\Controller;
use App\Models\Ergebnisboard;
use App\Models\ErgebnisKarte;
use App\Models\ErgebnisKommentar;
use App\Services\Ergebnisboards\ErgebnisboardKommentarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Kommentare auf Boards und Karten (JSON für die Alpine-Oberfläche).
 * Regeln in ErgebnisboardKommentarService; Kommentare ändern die Board-Version nicht.
 */
class ErgebnisKommentarController extends Controller
{
    public function __construct(private ErgebnisboardKommentarService $service)
    {
    }

    /** GET – Kommentare am Board (ohne `karte`) oder an einer Karte. */
    public function index(Request $request, Ergebnisboard $board): JsonResponse
    {
        $this->authorize('view', $board);

        return $this->antwort($board, $this->karte($request, $board), $request);
    }

    public function store(Request $request, Ergebnisboard $board): JsonResponse
    {
        $this->authorize('comment', $board);
        $daten = $request->validate([
            'text' => ['required', 'string', 'max:'.ErgebnisKommentar::MAX_ZEICHEN],
            'karte' => ['nullable', 'integer'],
        ], [], ['text' => 'Kommentar']);
        $karte = $this->karte($request, $board);

        $this->service->erstellen($board, $karte, $request->user(), $daten['text']);

        return $this->antwort($board, $karte, $request, 201);
    }

    public function update(Request $request, Ergebnisboard $board, ErgebnisKommentar $kommentar): JsonResponse
    {
        abort_unless($this->service->darfBearbeiten($board, $kommentar, $request->user()), 403);
        $daten = $request->validate([
            'text' => ['required', 'string', 'max:'.ErgebnisKommentar::MAX_ZEICHEN],
        ], [], ['text' => 'Kommentar']);

        $this->service->bearbeiten($board, $kommentar, $request->user(), $daten['text']);

        return $this->antwort($board, $kommentar->karte, $request);
    }

    public function destroy(Request $request, Ergebnisboard $board, ErgebnisKommentar $kommentar): JsonResponse
    {
        abort_unless($this->service->darfLoeschen($board, $kommentar, $request->user()), 403);
        $this->service->loeschen($kommentar);

        return $this->antwort($board, $kommentar->karte, $request);
    }

    private function karte(Request $request, Ergebnisboard $board): ?ErgebnisKarte
    {
        if (! $request->filled('karte')) {
            return null;
        }

        return ErgebnisKarte::query()->where('board_id', $board->id)->findOrFail($request->integer('karte'));
    }

    private function antwort(Ergebnisboard $board, ?ErgebnisKarte $karte, Request $request, int $status = 200): JsonResponse
    {
        return response()->json([
            'kommentare' => $this->service->liste($board, $karte, $request->user()),
            'anzahl' => $this->service->anzahl($board, $karte),
            'karte' => $karte?->id,
        ], $status);
    }
}
