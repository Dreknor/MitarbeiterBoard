<?php

namespace App\Http\Controllers;

use App\Exceptions\VersionKonfliktException;
use App\Http\Requests\PaedDiaryElterngespraechRequest;
use App\Models\PaedDiaryElterngespraech;
use App\Models\PaedDiaryElterngespraechVereinbarung;
use App\Models\Schueler;
use App\Services\PaedDiaryElterngespraechProtokoll;
use App\Services\PaedDiaryElterngespraechService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Elterngespräche in der Schülerakte (bisherige und neue Ansicht). Alle Antworten als JSON,
 * Logik in PaedDiaryElterngespraechService (geteilt mit der API v1).
 */
class PaedDiaryElterngespraechController extends Controller
{
    public function __construct(private PaedDiaryElterngespraechService $service)
    {
    }

    public function index(Schueler $schueler, Request $request): JsonResponse
    {
        $this->authorize('view', $schueler);
        $user = $request->user();

        return response()->json([
            'data' => $this->service->fuerSchueler($schueler, $user)->map(fn ($g) => $this->service->toArray($g, $user))->values(),
        ]);
    }

    public function store(PaedDiaryElterngespraechRequest $request, Schueler $schueler): JsonResponse
    {
        $this->authorize('create', [PaedDiaryElterngespraech::class, $schueler]);
        [$gespraech, $schluessel] = $this->service->anlegen($schueler, $request->daten(), $request->user());

        return response()->json([
            'data' => $this->service->toArray($gespraech, $request->user()),
            'agreement_keys' => (object) $schluessel,
        ], 201);
    }

    public function update(PaedDiaryElterngespraechRequest $request, PaedDiaryElterngespraech $gespraech): JsonResponse
    {
        $this->authorize('update', $gespraech);
        $user = $request->user();

        try {
            [$gespraech, $schluessel] = $this->service->aktualisieren($gespraech, $request->daten(), $user, $request->version());
        } catch (VersionKonfliktException $e) {
            $aktuell = $gespraech->fresh();

            return response()->json([
                'message' => 'Das Gespräch wurde zwischenzeitlich' . ($aktuell->aktualisiertVon ? ' von ' . $aktuell->aktualisiertVon->name : '') . ' geändert.',
                'data' => $this->service->toArray($aktuell, $user),
            ], 409);
        }

        return response()->json([
            'data' => $this->service->toArray($gespraech, $user),
            'agreement_keys' => (object) $schluessel,
        ]);
    }

    public function abschliessen(PaedDiaryElterngespraech $gespraech, Request $request): JsonResponse
    {
        $this->authorize('abschliessen', $gespraech);

        return response()->json(['data' => $this->service->toArray($this->service->abschliessen($gespraech, $request->user()), $request->user())]);
    }

    public function wiederOeffnen(PaedDiaryElterngespraech $gespraech, Request $request): JsonResponse
    {
        $this->authorize('wiederOeffnen', $gespraech);

        return response()->json(['data' => $this->service->toArray($this->service->wiederOeffnen($gespraech, $request->user()), $request->user())]);
    }

    public function absagen(PaedDiaryElterngespraech $gespraech, Request $request): JsonResponse
    {
        $this->authorize('absagen', $gespraech);

        return response()->json(['data' => $this->service->toArray($this->service->absagen($gespraech, $request->user()), $request->user())]);
    }

    public function destroy(PaedDiaryElterngespraech $gespraech): JsonResponse
    {
        $this->authorize('delete', $gespraech);
        $this->service->loeschen($gespraech);

        return response()->json(['success' => true]);
    }

    /**
     * Vereinbarung als Aufgabe oder Ziel übernehmen – auch nach Abschluss des Gesprächs.
     */
    public function uebernehmen(PaedDiaryElterngespraechVereinbarung $vereinbarung, Request $request): JsonResponse
    {
        $gespraech = $vereinbarung->gespraech;
        $this->authorize('view', $gespraech);
        $this->authorize('update', $gespraech->schueler);
        $art = $request->validate(['type' => ['required', Rule::in(['aufgabe', 'ziel'])]])['type'];

        $vereinbarung = $this->service->vereinbarungUebernehmen($vereinbarung, $art, $request->user());

        return response()->json([
            'data' => [
                'id' => $vereinbarung->id,
                'task_id' => $vereinbarung->paed_diary_task_id,
                'goal_id' => $vereinbarung->paed_diary_goal_id,
            ],
        ]);
    }

    public function pdf(PaedDiaryElterngespraech $gespraech, PaedDiaryElterngespraechProtokoll $protokoll)
    {
        $this->authorize('view', $gespraech);

        return $protokoll->pdfResponse($gespraech);
    }
}
