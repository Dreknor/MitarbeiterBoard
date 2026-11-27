<?php

namespace App\Http\Controllers\Wochenplan;

use App\Http\Controllers\Controller;
use App\Models\Klasse;
use App\Models\Schueler;
use App\Models\Wochenplan\WpAufgabe;
use App\Models\Wochenplan\WpAufgabeStatus;
use App\Models\Wochenplan\WpPlan;
use App\Policies\WpPlanPolicy;
use App\Services\Wochenplan\WpStatusService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Status je Kind und Aufgabe (offen/teilweise/erledigt + Notiz).
 * Matrix im Wochenplan-Modul ist immer verfügbar; die Endpunkte für das Tagebuch
 * (`schuelerWoche`, `klasseWoche`) nur mit Tagebuch-Anbindung.
 */
class WpFortschrittController extends Controller
{
    public function __construct(private WpStatusService $status)
    {
    }

    /** Matrix-Seite (Klasse × Aufgaben). */
    public function matrix(Request $request, WpPlan $wpPlan)
    {
        $this->authorize('trackProgress', $wpPlan);

        return view('wochenplan.new.fortschritt', [
            'wpPlan' => $wpPlan,
            'matrix' => $this->status->matrix($wpPlan),
        ]);
    }

    public function matrixDaten(WpPlan $wpPlan): JsonResponse
    {
        $this->authorize('trackProgress', $wpPlan);

        return response()->json(['matrix' => $this->status->matrix($wpPlan)]);
    }

    /** Einzelnen Status setzen. */
    public function setzen(Request $request): JsonResponse
    {
        $daten = $request->validate([
            'wp_aufgabe_id' => 'required|integer|exists:wp_aufgaben,id',
            'schueler_id' => 'required|integer|exists:schueler,id',
            'status' => ['required', Rule::in(WpAufgabeStatus::STATUS)],
            'notiz' => 'nullable|string|max:255',
        ]);

        $aufgabe = WpAufgabe::with('planFach.plan')->findOrFail($daten['wp_aufgabe_id']);
        $schueler = Schueler::findOrFail($daten['schueler_id']);
        $this->darfSetzen($request, $aufgabe, $schueler);

        $eintrag = $this->status->setzen($aufgabe, $schueler, $daten['status'], $daten['notiz'] ?? null, $request->user());
        $eintrag?->load('user');

        return response()->json([
            'eintrag' => $this->status->aufgabeMitStatus($aufgabe, $eintrag),
        ]);
    }

    /** Mehrere Zellen gleichzeitig (Matrix: „alle erledigt“, Mehrfachauswahl). */
    public function mehrere(Request $request, WpPlan $wpPlan): JsonResponse
    {
        $this->authorize('trackProgress', $wpPlan);
        $daten = $request->validate([
            'status' => ['required', Rule::in(WpAufgabeStatus::STATUS)],
            'zellen' => 'required|array|min:1|max:2000',
            'zellen.*.wp_aufgabe_id' => 'required|integer',
            'zellen.*.schueler_id' => 'required|integer',
        ]);

        $user = $request->user();
        $anzahl = $this->status->mehrere(
            $daten['zellen'],
            $daten['status'],
            $user,
            fn (Schueler $s, WpAufgabe $a) => $user->can('trackProgress', $a->planFach->plan) && WpPlanPolicy::darfSchueler($user, $s),
        );

        return response()->json(['anzahl' => $anzahl, 'matrix' => $this->status->matrix($wpPlan)]);
    }

    /** Aufgaben und Status eines Kindes in einer Woche (Tagebuch-Seitenleiste). */
    public function schuelerWoche(Request $request, Schueler $schueler): JsonResponse
    {
        abort_unless(WpStatusService::tagebuchAktiv(), 404);
        $this->darfKind($request, $schueler);

        return response()->json($this->status->wocheFuerSchueler($schueler, $this->tag($request)));
    }

    /** Zusammenfassung je Kind einer Klasse (Tagebuch-Wochenansicht: „5 ✓ · 2 ◐ von 8“). */
    public function klasseWoche(Request $request, Klasse $klasse): JsonResponse
    {
        abort_unless(WpStatusService::tagebuchAktiv(), 404);
        abort_unless($this->kannLesen($request) && WpPlanPolicy::darfKlasse($request->user(), (int) $klasse->id), 403);

        return response()->json([
            'woche' => $this->tag($request)->startOfWeek()->toDateString(),
            'schueler' => $this->status->zusammenfassungKlasse($klasse, $this->tag($request)),
        ]);
    }

    private function darfSetzen(Request $request, WpAufgabe $aufgabe, Schueler $schueler): void
    {
        $plan = $aufgabe->planFach?->plan;
        abort_unless($plan && $request->user()->can('trackProgress', $plan), 403);
        abort_unless(WpPlanPolicy::darfSchueler($request->user(), $schueler), 403);

        // Vom Tagebuch aus nur mit eingeschalteter Anbindung
        if ($request->input('quelle') === 'tagebuch') {
            abort_unless(WpStatusService::tagebuchAktiv(), 404);
        }
    }

    private function darfKind(Request $request, Schueler $schueler): void
    {
        abort_unless($this->kannLesen($request) && WpPlanPolicy::darfSchueler($request->user(), $schueler), 403);
    }

    private function kannLesen(Request $request): bool
    {
        return $request->user()->can('viewAny', WpPlan::class);
    }

    private function tag(Request $request): Carbon
    {
        try {
            return $request->filled('datum') ? Carbon::parse($request->input('datum')) : now();
        } catch (\Throwable) {
            return now();
        }
    }
}
