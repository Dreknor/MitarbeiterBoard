<?php

namespace App\Http\Controllers\Wochenplan;

use App\Exceptions\VersionKonfliktException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Wochenplan\WpPlanRequest;
use App\Models\Klasse;
use App\Models\PaedDiaryTask;
use App\Models\Schueler;
use App\Models\Wochenplan\WpFach;
use App\Models\Wochenplan\WpFormatvorlage;
use App\Models\Wochenplan\WpPlan;
use App\Policies\WpPlanPolicy;
use App\Services\Wochenplan\WochenplanService;
use App\Services\Wochenplan\WpStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WpPlanController extends Controller
{
    public function __construct(private WochenplanService $service)
    {
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', WpPlan::class);
        $user = $request->user();

        $filter = $request->only(['klasse_id', 'typ', 'zeitraum', 'q', 'schuljahr', 'woche', 'meine']);
        $plaene = $this->service->liste($user, $filter);

        return view('wochenplan.new.index', [
            'plaene' => $plaene,
            'klassen' => $this->service->klassenFuer($user),
            'schuljahre' => $this->service->schuljahre(),
            'vorlagen' => WpPlan::vorlagen()->withCount('aufgaben')->orderBy('name')->get(),
            'filter' => $filter,
            'darfAlles' => WpPlan::darfAlles($user),
            'darfSchreiben' => $user->can('create', WpPlan::class),
        ]);
    }

    public function indexKlasse(Klasse $klasse, Request $request)
    {
        return redirect()->route('wp.index', array_merge($request->query(), ['klasse_id' => $klasse->id]));
    }

    public function hilfe()
    {
        $this->authorize('viewAny', WpPlan::class);

        return view('wochenplan.new.hilfe');
    }

    public function create(Request $request)
    {
        $this->authorize('create', WpPlan::class);

        return view('wochenplan.new.create', [
            'klassen' => $this->service->klassenFuer($request->user()),
            'vorlagen' => WpPlan::vorlagen()->orderBy('name')->get(),
            'formatvorlagen' => WpFormatvorlage::orderBy('name')->get(),
            'vorlageId' => $request->integer('vorlage') ?: null,
        ]);
    }

    public function store(WpPlanRequest $request): RedirectResponse
    {
        $this->authorize('create', WpPlan::class);

        $plan = $this->service->erstellen($request->safe()->except(['version']), $request->user());

        return redirect()->route('wp.edit', $plan)->with(['type' => 'success', 'Meldung' => 'Plan wurde erstellt.']);
    }

    /** Editor (mit Schreibrecht) bzw. Ansicht (nur lesen). */
    public function edit(Request $request, WpPlan $wpPlan)
    {
        $this->authorize('view', $wpPlan);
        $user = $request->user();

        return view('wochenplan.new.edit', [
            'wpPlan' => $wpPlan,
            'daten' => $this->service->toArray($wpPlan, $user),
            'alleFaecher' => WpFach::ordered()->get(['id', 'name']),
            'formatvorlagen' => WpFormatvorlage::orderBy('name')->get(['id', 'name']),
            'klassen' => $this->service->klassenFuer($user),
            'diaryTasks' => $this->offeneTagebuchAufgaben($wpPlan),
            'tagebuchAktiv' => WpStatusService::tagebuchAktiv(),
            'darfBearbeiten' => $user->can('update', $wpPlan),
            'darfFortschritt' => $user->can('trackProgress', $wpPlan),
        ]);
    }

    /** Aktueller Stand als JSON (Editor, nach Konflikten). */
    public function daten(Request $request, WpPlan $wpPlan): JsonResponse
    {
        $this->authorize('view', $wpPlan);

        return response()->json(['plan' => $this->service->toArray($wpPlan, $request->user())]);
    }

    public function update(WpPlanRequest $request, WpPlan $wpPlan)
    {
        $this->authorize('update', $wpPlan);

        try {
            $plan = $this->service->aktualisieren($wpPlan, $request->safe()->except(['version']), $request->input('version'));
        } catch (VersionKonfliktException $e) {
            if ($request->expectsJson()) {
                return $this->konflikt($request, $wpPlan);
            }

            return redirect()->back()->with(['type' => 'warning', 'Meldung' => 'Der Plan wurde zwischenzeitlich geändert. Bitte erneut speichern.']);
        }

        if ($request->expectsJson()) {
            return response()->json(['plan' => $this->service->toArray($plan, $request->user())]);
        }

        return redirect()->back()->with(['type' => 'success', 'Meldung' => 'Plan wurde gespeichert.']);
    }

    public function destroy(WpPlan $wpPlan): RedirectResponse
    {
        $this->authorize('delete', $wpPlan);

        $vorlage = $wpPlan->isVorlage();
        $this->service->loeschen($wpPlan);

        return redirect()->route($vorlage ? 'wp.vorlagen.index' : 'wp.index')
            ->with(['type' => 'success', 'Meldung' => $vorlage ? 'Vorlage wurde gelöscht.' : 'Plan wurde gelöscht.']);
    }

    public function duplizieren(Request $request, WpPlan $wpPlan): RedirectResponse
    {
        $this->authorize('update', $wpPlan);

        $neu = $this->service->duplizieren($wpPlan, ['name' => $wpPlan->name.' (Kopie)'], $request->user());
        if ($neu->isSchuelerplan()) {
            $this->service->geaendert($neu);
        }

        return redirect()->route('wp.edit', $neu)->with(['type' => 'success', 'Meldung' => 'Plan wurde dupliziert.']);
    }

    public function fortschreiben(Request $request, WpPlan $wpPlan): RedirectResponse
    {
        $this->authorize('update', $wpPlan);
        abort_if($wpPlan->isVorlage(), 422, 'Vorlagen haben keinen Zeitraum.');

        $neu = $this->service->fortschreiben($wpPlan, $request->boolean('mit_kinderplaenen'), $request->user());

        return redirect()->route('wp.edit', $neu)->with([
            'type' => 'success',
            'Meldung' => 'Plan für die Woche '.$neu->zeitraum.' angelegt.',
        ]);
    }

    public function createSchuelerplan(Request $request, WpPlan $wpPlan)
    {
        $this->authorize('update', $wpPlan);

        if (! $wpPlan->isKlassenplan()) {
            return redirect()->back()->with(['type' => 'warning', 'Meldung' => 'Kinderpläne gibt es nur zu Klassenplänen.']);
        }

        $user = $request->user();
        if ($wpPlan->klasse) {
            $schueler = $wpPlan->klasse->schueler()->orderBy('nachname')->orderBy('vorname')->get();
        } else {
            $schueler = Schueler::with('klasse')
                ->whereIn('klasse_id', $this->service->klassenFuer($user)->pluck('id'))
                ->orderBy('nachname')->orderBy('vorname')->get();
        }

        return view('wochenplan.new.schuelerplan.create', [
            'wpPlan' => $wpPlan,
            'schueler' => $schueler,
            'formatvorlagen' => WpFormatvorlage::orderBy('name')->get(),
            'bereitsVorhanden' => $wpPlan->kinderPlaene()->pluck('schueler_id')->all(),
            'hatNiveaus' => $wpPlan->aufgaben()->whereNotNull('niveau')->exists(),
        ]);
    }

    public function storeSchuelerplan(Request $request, WpPlan $wpPlan): RedirectResponse
    {
        $this->authorize('update', $wpPlan);
        abort_unless($wpPlan->isKlassenplan(), 422);

        $request->validate([
            'schueler_ids' => 'required|array|min:1',
            'schueler_ids.*' => 'integer|exists:schueler,id',
            'formatvorlage_id' => 'nullable|integer|exists:wp_formatvorlagen,id',
            'niveau' => 'nullable|array',
            'niveau.*' => 'nullable|integer|in:1,2,3',
        ], ['schueler_ids.required' => 'Bitte mindestens ein Kind auswählen.']);

        $user = $request->user();
        $erstellt = [];
        foreach ($request->input('schueler_ids') as $schuelerId) {
            $schueler = Schueler::findOrFail($schuelerId);
            abort_unless(WpPlanPolicy::darfSchueler($user, $schueler), 403);
            $niveau = $request->input('niveau.'.$schuelerId);
            $erstellt[] = $this->service->kinderplanErstellen(
                $wpPlan,
                $schueler,
                $request->integer('formatvorlage_id') ?: null,
                $niveau !== null && $niveau !== '' ? (int) $niveau : null,
                $user,
            );
        }

        if (count($erstellt) === 1) {
            return redirect()->route('wp.edit', $erstellt[0])->with([
                'type' => 'success',
                'Meldung' => 'Kinderplan für '.$erstellt[0]->schueler->vorname.' wurde erstellt.',
            ]);
        }

        return redirect()->route('wp.edit', $wpPlan)->with([
            'type' => 'success',
            'Meldung' => count($erstellt).' Kinderpläne wurden erstellt.',
        ]);
    }

    private function offeneTagebuchAufgaben(WpPlan $plan)
    {
        if (! WpStatusService::tagebuchAktiv() || ! $plan->isSchuelerplan() || ! $plan->schueler_id) {
            return collect();
        }

        $verknuepft = $plan->aufgaben()->whereNotNull('paed_diary_task_id')->pluck('paed_diary_task_id')->all();

        return PaedDiaryTask::open()
            ->where('schueler_id', $plan->schueler_id)
            ->whereNotIn('id', $verknuepft)
            ->orderByDesc('highlighted')
            ->orderByRaw('due_date IS NULL, due_date ASC')
            ->orderByDesc('created_at')
            ->get();
    }

    private function konflikt(Request $request, WpPlan $plan): JsonResponse
    {
        return response()->json([
            'message' => 'Der Plan wurde zwischenzeitlich von jemand anderem geändert. Der aktuelle Stand wurde geladen.',
            'plan' => $this->service->toArray($plan->fresh(), $request->user()),
        ], 409);
    }
}
