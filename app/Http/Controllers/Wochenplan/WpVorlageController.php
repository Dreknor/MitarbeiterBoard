<?php

namespace App\Http\Controllers\Wochenplan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wochenplan\WpPlanRequest;
use App\Models\Wochenplan\WpFormatvorlage;
use App\Models\Wochenplan\WpPlan;
use App\Services\Wochenplan\WochenplanService;
use Illuminate\Http\Request;

/** Vorlagen: für alle mit `create wochenplan` nutzbar, ändern/löschen nur Ersteller:in und Sonderrecht. */
class WpVorlageController extends Controller
{
    public function __construct(private WochenplanService $service)
    {
    }

    public function index(Request $request)
    {
        $this->authorize('create', WpPlan::class);

        return view('wochenplan.new.vorlagen.index', [
            'vorlagen' => WpPlan::vorlagen()->with(['planFaecher.fach', 'planFaecher.aufgaben', 'creator'])->orderBy('name')->get(),
            'klassen' => $this->service->klassenFuer($request->user()),
            'formatvorlagen' => WpFormatvorlage::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function alsVorlageSpeichern(Request $request, WpPlan $wpPlan)
    {
        $this->authorize('view', $wpPlan);
        $this->authorize('create', WpPlan::class);
        $daten = $request->validate(['vorlage_name' => 'required|string|max:255']);

        $this->service->alsVorlageSpeichern($wpPlan, $daten['vorlage_name'], $request->user());

        return $this->antwort($request, 'Als Vorlage gespeichert.');
    }

    public function vonVorlageErstellen(WpPlanRequest $request, WpPlan $wpPlan)
    {
        $this->authorize('useTemplate', $wpPlan);

        $plan = $this->service->erstellen(array_merge(
            $request->safe()->except(['version', 'is_vorlage', 'vorlage_name']),
            ['vorlage_id' => $wpPlan->id]
        ), $request->user());

        return redirect()->route('wp.edit', $plan)->with(['type' => 'success', 'Meldung' => 'Plan aus Vorlage erstellt.']);
    }

    public function destroy(WpPlan $wpPlan)
    {
        abort_unless($wpPlan->isVorlage(), 404);
        $this->authorize('delete', $wpPlan);

        $this->service->loeschen($wpPlan);

        return redirect()->route('wp.vorlagen.index')->with(['type' => 'success', 'Meldung' => 'Vorlage gelöscht.']);
    }

    private function antwort(Request $request, string $meldung)
    {
        if ($request->expectsJson()) {
            return response()->json(['meldung' => $meldung]);
        }

        return redirect()->back()->with(['type' => 'success', 'Meldung' => $meldung]);
    }
}
