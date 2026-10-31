<?php

namespace App\Http\Controllers\API\v1;

use App\Exceptions\VersionKonfliktException;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\v1\ParentMeetingRequest;
use App\Models\PaedDiaryElterngespraech;
use App\Models\PaedDiaryElterngespraechVereinbarung;
use App\Models\Schueler;
use App\Services\PaedDiaryElterngespraechProtokoll;
use App\Services\PaedDiaryElterngespraechService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * API v1 – Elterngespräche (Planung, Vorbereitung, Protokoll). Gleiche Logik und Rechte wie im Web
 * (PaedDiaryElterngespraechService, PaedDiaryElterngespraechPolicy); die JSON-Darstellung liefert
 * PaedDiaryElterngespraechService::toArray() für Web und API gemeinsam.
 */
class ParentMeetingApiController extends Controller
{
    public function __construct(private PaedDiaryElterngespraechService $service)
    {
    }

    /** GET /api/v1/students/{student}/parent-meetings */
    public function index(Request $request, Schueler $schueler): JsonResponse
    {
        $this->authorize('view', $schueler);
        $user = $request->user();

        return response()->json([
            'data' => $this->service->fuerSchueler($schueler, $user)->map(fn ($g) => $this->service->toArray($g, $user))->values(),
        ]);
    }

    /** POST /api/v1/students/{student}/parent-meetings */
    public function store(ParentMeetingRequest $request, Schueler $schueler): JsonResponse
    {
        $this->authorize('create', [PaedDiaryElterngespraech::class, $schueler]);
        [$gespraech, $schluessel] = $this->service->anlegen($schueler, $request->daten(), $request->user());

        return response()->json([
            'data' => $this->service->toArray($gespraech, $request->user()),
            'agreement_keys' => (object) $schluessel,
        ], 201);
    }

    /** GET /api/v1/parent-meetings/{meeting} */
    public function show(Request $request, PaedDiaryElterngespraech $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        return response()->json(['data' => $this->service->toArray($meeting, $request->user())]);
    }

    /** PUT /api/v1/parent-meetings/{meeting} – nur übergebene Felder; mit `version` 409 bei fremder Änderung */
    public function update(ParentMeetingRequest $request, PaedDiaryElterngespraech $meeting): JsonResponse
    {
        $this->authorize('update', $meeting);
        $user = $request->user();

        try {
            [$meeting, $schluessel] = $this->service->aktualisieren($meeting, $request->daten(), $user, $request->version());
        } catch (VersionKonfliktException $e) {
            return response()->json([
                'message' => 'Das Gespräch wurde zwischenzeitlich geändert.',
                'data' => $this->service->toArray($meeting->fresh(), $user),
            ], 409);
        }

        return response()->json([
            'data' => $this->service->toArray($meeting, $user),
            'agreement_keys' => (object) $schluessel,
        ]);
    }

    /** POST /api/v1/parent-meetings/{meeting}/complete */
    public function complete(Request $request, PaedDiaryElterngespraech $meeting): JsonResponse
    {
        $this->authorize('abschliessen', $meeting);

        return response()->json(['data' => $this->service->toArray($this->service->abschliessen($meeting, $request->user()), $request->user())]);
    }

    /** POST /api/v1/parent-meetings/{meeting}/reopen */
    public function reopen(Request $request, PaedDiaryElterngespraech $meeting): JsonResponse
    {
        $this->authorize('wiederOeffnen', $meeting);

        return response()->json(['data' => $this->service->toArray($this->service->wiederOeffnen($meeting, $request->user()), $request->user())]);
    }

    /** POST /api/v1/parent-meetings/{meeting}/cancel */
    public function cancel(Request $request, PaedDiaryElterngespraech $meeting): JsonResponse
    {
        $this->authorize('absagen', $meeting);

        return response()->json(['data' => $this->service->toArray($this->service->absagen($meeting, $request->user()), $request->user())]);
    }

    /** DELETE /api/v1/parent-meetings/{meeting} */
    public function destroy(PaedDiaryElterngespraech $meeting): Response
    {
        $this->authorize('delete', $meeting);
        $this->service->loeschen($meeting);

        return response()->noContent();
    }

    /** POST /api/v1/parent-meetings/{meeting}/agreements/{agreement}/transfer {type: task|goal} */
    public function transfer(Request $request, PaedDiaryElterngespraech $meeting, PaedDiaryElterngespraechVereinbarung $agreement): JsonResponse
    {
        // Die Vereinbarung muss zum Gespräch im Pfad gehören (kein Querzugriff über fremde IDs)
        abort_unless((int) $agreement->elterngespraech_id === (int) $meeting->id, 404);
        $this->authorize('view', $meeting);
        $this->authorize('update', $meeting->schueler);
        $type = $request->validate(['type' => ['required', Rule::in(['task', 'goal'])]])['type'];

        $agreement = $this->service->vereinbarungUebernehmen($agreement, $type === 'task' ? 'aufgabe' : 'ziel', $request->user());

        return response()->json(['data' => [
            'id' => $agreement->id,
            'task_id' => $agreement->paed_diary_task_id,
            'goal_id' => $agreement->paed_diary_goal_id,
        ]]);
    }

    /** GET /api/v1/parent-meetings/{meeting}/protocol.pdf */
    public function pdf(PaedDiaryElterngespraech $meeting, PaedDiaryElterngespraechProtokoll $protokoll): Response
    {
        $this->authorize('view', $meeting);

        return $protokoll->pdfResponse($meeting);
    }
}
