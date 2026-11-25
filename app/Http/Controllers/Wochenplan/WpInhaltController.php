<?php

namespace App\Http\Controllers\Wochenplan;

use App\Exceptions\VersionKonfliktException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Wochenplan\WpAufgabeRequest;
use App\Models\PaedDiaryTask;
use App\Models\Wochenplan\WpAufgabe;
use App\Models\Wochenplan\WpPlan;
use App\Models\Wochenplan\WpPlanFach;
use App\Models\Wochenplan\WpTaeglicheUebung;
use App\Services\Wochenplan\WochenplanService;
use App\Services\Wochenplan\WpStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON-Endpunkte des Editors (Fächer, Aufgaben, tägliche Übungen, Abgleich, Tagebuch).
 * Jede Antwort enthält den vollständigen aktuellen Stand des Plans (`plan`).
 * Unter-Ressourcen werden immer gegen den Plan im Pfad geprüft.
 */
class WpInhaltController extends Controller
{
    public function __construct(private WochenplanService $service)
    {
    }

    // ─── Fächer ───────────────────────────────────────────────────────────────

    public function fachHinzufuegen(Request $request, WpPlan $wpPlan): JsonResponse
    {
        $this->authorize('update', $wpPlan);
        $daten = $request->validate(['wp_fach_id' => 'required|integer|exists:wp_faecher,id']);

        $this->service->fachHinzufuegen($wpPlan, (int) $daten['wp_fach_id']);

        return $this->stand($request, $wpPlan);
    }

    public function fachEntfernen(Request $request, WpPlan $wpPlan, WpPlanFach $wpPlanFach): JsonResponse
    {
        $this->authorize('update', $wpPlan);
        $this->gehoertZu($wpPlan, $wpPlanFach->wp_plan_id);

        $this->service->fachEntfernen($wpPlanFach);

        return $this->stand($request, $wpPlan);
    }

    public function faecherSortieren(Request $request, WpPlan $wpPlan): JsonResponse
    {
        $this->authorize('update', $wpPlan);
        $daten = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);

        $this->service->faecherSortieren($wpPlan, $daten['ids']);

        return $this->stand($request, $wpPlan);
    }

    // ─── Aufgaben ─────────────────────────────────────────────────────────────

    public function aufgabeAnlegen(WpAufgabeRequest $request, WpPlan $wpPlan, WpPlanFach $wpPlanFach): JsonResponse
    {
        $this->authorize('update', $wpPlan);
        $this->gehoertZu($wpPlan, $wpPlanFach->wp_plan_id);

        $aufgabe = $this->service->aufgabeAnlegen($wpPlanFach, $request->safe()->except('basis'));

        return $this->stand($request, $wpPlan, ['aufgabe_id' => $aufgabe->id]);
    }

    public function aufgabeAendern(WpAufgabeRequest $request, WpPlan $wpPlan, WpAufgabe $wpAufgabe): JsonResponse
    {
        $this->authorize('update', $wpPlan);
        $this->aufgabeGehoertZu($wpPlan, $wpAufgabe);

        if ($request->filled('basis') && $request->input('basis') !== $wpAufgabe->aufgabe) {
            return $this->konflikt($request, $wpPlan, 'Die Aufgabe wurde zwischenzeitlich von jemand anderem geändert.');
        }

        $this->service->aufgabeAendern($wpAufgabe, $request->safe()->except('basis'));

        return $this->stand($request, $wpPlan);
    }

    public function aufgabeLoeschen(Request $request, WpPlan $wpPlan, WpAufgabe $wpAufgabe): JsonResponse
    {
        $this->authorize('update', $wpPlan);
        $this->aufgabeGehoertZu($wpPlan, $wpAufgabe);

        $this->service->aufgabeLoeschen($wpAufgabe);

        return $this->stand($request, $wpPlan);
    }

    public function aufgabenSortieren(Request $request, WpPlan $wpPlan): JsonResponse
    {
        $this->authorize('update', $wpPlan);
        $daten = $request->validate([
            'eintraege' => 'required|array',
            'eintraege.*.id' => 'required|integer',
            'eintraege.*.wp_plan_fach_id' => 'nullable|integer',
        ]);

        $this->service->aufgabenSortieren($wpPlan, $daten['eintraege']);

        return $this->stand($request, $wpPlan);
    }

    /** Offene Tagebuch-Aufgabe in einen Kinderplan übernehmen (nur mit Tagebuch-Anbindung). */
    public function tagebuchUebernehmen(Request $request, WpPlan $wpPlan, WpPlanFach $wpPlanFach): JsonResponse
    {
        $this->authorize('update', $wpPlan);
        $this->gehoertZu($wpPlan, $wpPlanFach->wp_plan_id);
        abort_unless(WpStatusService::tagebuchAktiv(), 403, 'Die Anbindung an das Pädagogische Tagebuch ist ausgeschaltet.');

        $daten = $request->validate([
            'diary_task_id' => 'required|integer|exists:paed_diary_tasks,id',
            'aufgabe' => 'required|string|max:2000',
            'dauer' => 'nullable|string|max:50',
        ]);

        $this->service->tagebuchAufgabeUebernehmen($wpPlanFach, PaedDiaryTask::findOrFail($daten['diary_task_id']), $daten);

        return $this->stand($request, $wpPlan);
    }

    // ─── Tägliche Übungen ────────────────────────────────────────────────────

    public function uebungenSchalten(Request $request, WpPlan $wpPlan): JsonResponse
    {
        $this->authorize('update', $wpPlan);
        $daten = $request->validate(['aktiv' => 'required|boolean']);

        $this->service->uebungenSchalten($wpPlan, (bool) $daten['aktiv']);

        return $this->stand($request, $wpPlan);
    }

    public function uebungAnlegen(Request $request, WpPlan $wpPlan): JsonResponse
    {
        $this->authorize('update', $wpPlan);
        $daten = $request->validate(['aufgabe' => 'required|string|max:1000']);

        $this->service->uebungAnlegen($wpPlan, $daten['aufgabe']);

        return $this->stand($request, $wpPlan);
    }

    public function uebungAendern(Request $request, WpPlan $wpPlan, WpTaeglicheUebung $wpTaeglicheUebung): JsonResponse
    {
        $this->authorize('update', $wpPlan);
        $this->gehoertZu($wpPlan, $wpTaeglicheUebung->wp_plan_id);
        $daten = $request->validate(['aufgabe' => 'required|string|max:1000', 'basis' => 'nullable|string']);

        if (! empty($daten['basis']) && $daten['basis'] !== $wpTaeglicheUebung->aufgabe) {
            return $this->konflikt($request, $wpPlan, 'Die Übung wurde zwischenzeitlich von jemand anderem geändert.');
        }

        $this->service->uebungAendern($wpTaeglicheUebung, $daten['aufgabe']);

        return $this->stand($request, $wpPlan);
    }

    public function uebungLoeschen(Request $request, WpPlan $wpPlan, WpTaeglicheUebung $wpTaeglicheUebung): JsonResponse
    {
        $this->authorize('update', $wpPlan);
        $this->gehoertZu($wpPlan, $wpTaeglicheUebung->wp_plan_id);

        $this->service->uebungLoeschen($wpTaeglicheUebung);

        return $this->stand($request, $wpPlan);
    }

    public function uebungenSortieren(Request $request, WpPlan $wpPlan): JsonResponse
    {
        $this->authorize('update', $wpPlan);
        $daten = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);

        $this->service->uebungenSortieren($wpPlan, $daten['ids']);

        return $this->stand($request, $wpPlan);
    }

    // ─── Abgleich mit dem Klassenplan ────────────────────────────────────────

    public function abgleichVorschau(Request $request, WpPlan $wpPlan): JsonResponse
    {
        $this->authorize('update', $wpPlan);
        $fachId = $request->integer('wp_fach_id') ?: null;

        return response()->json(['vorschau' => $this->service->abgleichVorschau($wpPlan, $fachId)]);
    }

    public function abgleichen(Request $request, WpPlan $wpPlan): JsonResponse
    {
        $this->authorize('update', $wpPlan);
        $daten = $request->validate([
            'wp_fach_id' => 'nullable|integer',
            'angepasste_behalten' => 'sometimes|boolean',
            'eigene_behalten' => 'sometimes|boolean',
            'version' => 'nullable|integer',
        ]);

        try {
            $this->service->versionPruefen($wpPlan, $daten['version'] ?? null);
        } catch (VersionKonfliktException) {
            return $this->konflikt($request, $wpPlan, 'Der Plan wurde zwischenzeitlich geändert. Bitte die Vorschau erneut prüfen.');
        }

        $this->service->abgleichen(
            $wpPlan,
            isset($daten['wp_fach_id']) ? (int) $daten['wp_fach_id'] : null,
            (bool) ($daten['angepasste_behalten'] ?? true),
            (bool) ($daten['eigene_behalten'] ?? true),
        );

        return $this->stand($request, $wpPlan, ['meldung' => 'Mit dem Klassenplan abgeglichen.']);
    }

    // ─── intern ───────────────────────────────────────────────────────────────

    private function stand(Request $request, WpPlan $plan, array $extra = []): JsonResponse
    {
        return response()->json(array_merge(['plan' => $this->service->toArray($plan->fresh(), $request->user())], $extra));
    }

    private function konflikt(Request $request, WpPlan $plan, string $meldung): JsonResponse
    {
        return response()->json([
            'message' => $meldung.' Der aktuelle Stand wurde geladen.',
            'plan' => $this->service->toArray($plan->fresh(), $request->user()),
        ], 409);
    }

    private function gehoertZu(WpPlan $plan, mixed $planId): void
    {
        abort_unless((int) $planId === (int) $plan->id, 404);
    }

    private function aufgabeGehoertZu(WpPlan $plan, WpAufgabe $aufgabe): void
    {
        abort_unless($aufgabe->planFach && (int) $aufgabe->planFach->wp_plan_id === (int) $plan->id, 404);
    }
}
