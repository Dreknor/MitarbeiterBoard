<?php

namespace App\Http\Controllers\Wochenplan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wochenplan\WpPlanRequest;
use App\Models\Klasse;
use App\Models\Schueler;
use App\Models\Wochenplan\WpFach;
use App\Models\Wochenplan\WpFormatvorlage;
use App\Models\Wochenplan\WpPlan;
use App\Models\Wochenplan\WpPlanFach;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class WpPlanController extends Controller
{
    public function index(Request $request)
    {
        $query = WpPlan::with(['klasse', 'schueler', 'planFaecher', 'kinderPlaene'])
            ->where('is_vorlage', false);

        // Filter
        if ($request->filled('klasse_id')) {
            $query->where('klasse_id', $request->klasse_id);
        }
        if ($request->get('typ') === 'klassenplan') {
            $query->klassenplaene();
        } elseif ($request->get('typ') === 'schuelerplan') {
            $query->schuelerplaene();
        }
        if ($request->get('zeitraum') === 'aktuell') {
            $query->aktuell();
        } elseif ($request->get('zeitraum') === 'vergangen') {
            $query->where('gueltig_bis', '<', now());
        }

        $plaene   = $query->orderByDesc('gueltig_von')->paginate(12)->withQueryString();
        $klassen  = Klasse::orderBy('name')->get();
        $vorlagen = WpPlan::vorlagen()
            ->with(['planFaecher', 'kinderPlaene'])
            ->orderBy('name')
            ->get();

        return view('wochenplan.new.index', compact('plaene', 'klassen', 'vorlagen'))->with([
            'filterKlasseId' => $request->get('klasse_id'),
            'filterTyp'      => $request->get('typ', 'alle'),
            'filterZeitraum' => $request->get('zeitraum', 'alle'),
        ]);
    }

    public function indexKlasse(Klasse $klasse, Request $request)
    {
        return $this->index($request->merge(['klasse_id' => $klasse->id]));
    }

    public function create()
    {
        $klassen       = Klasse::orderBy('name')->get();
        $vorlagen      = WpPlan::vorlagen()->orderBy('name')->get();
        $formatvorlagen = WpFormatvorlage::orderBy('name')->get();

        return view('wochenplan.new.create', compact('klassen', 'vorlagen', 'formatvorlagen'));
    }

    public function store(WpPlanRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = auth()->id();

        if (!empty($data['is_vorlage'])) {
            $data['is_vorlage'] = true;
        }

        // Aus Vorlage erstellen
        if ($request->filled('vorlage_id')) {
            $vorlage = WpPlan::findOrFail($request->vorlage_id);
            $plan = $vorlage->duplizieren(array_merge($data, ['is_vorlage' => false]));
        } else {
            unset($data['vorlage_id']);
            $plan = WpPlan::create($data);

            // Standard-Fächer zuordnen
            $defaultFaecher = WpFach::default()->ordered()->get();
            foreach ($defaultFaecher as $index => $fach) {
                $plan->planFaecher()->create([
                    'wp_fach_id'  => $fach->id,
                    'sort_order'  => $index,
                ]);
            }
        }

        return redirect()->route('wp.edit', $plan)
            ->with(['type' => 'success', 'Meldung' => 'Plan wurde erstellt.']);
    }

    public function edit(WpPlan $wpPlan)
    {
        $wpPlan->load([
            'planFaecher.aufgaben',
            'planFaecher.fach',
            'klasse',
            'schueler',
            'kinderPlaene.schueler',
            'formatvorlage',
            'media',
        ]);

        $alleFaecher    = WpFach::ordered()->get();
        $formatvorlagen = WpFormatvorlage::orderBy('name')->get();

        return view('wochenplan.new.edit', compact('wpPlan', 'alleFaecher', 'formatvorlagen'));
    }

    public function update(WpPlanRequest $request, WpPlan $wpPlan)
    {
        $wpPlan->update($request->validated());

        return redirect()->back()
            ->with(['type' => 'success', 'Meldung' => 'Plan wurde gespeichert.']);
    }

    public function destroy(WpPlan $wpPlan)
    {
        $wpPlan->delete();

        return redirect()->route('wp.index')
            ->with(['type' => 'success', 'Meldung' => 'Plan wurde gelöscht.']);
    }

    public function duplizieren(WpPlan $wpPlan)
    {
        $neuerPlan = $wpPlan->duplizieren([
            'name'       => $wpPlan->name . ' (Kopie)',
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('wp.edit', $neuerPlan)
            ->with(['type' => 'success', 'Meldung' => 'Plan wurde dupliziert.']);
    }

    public function createSchuelerplan(WpPlan $wpPlan)
    {
        if ($wpPlan->isVorlage()) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Von einer Vorlage können keine Kinderpläne erstellt werden.',
            ]);
        }

        if ($wpPlan->klasse) {
            // Nur Schüler der zugeordneten Klasse anzeigen
            $schueler = $wpPlan->klasse->schueler()
                ->orderBy('nachname')->orderBy('vorname')->get();
            $klassen = null;
        } else {
            // Plan ohne Klasse: alle Schüler aller Klassen anzeigen (gruppiert)
            $schueler = Schueler::with('klasse')
                ->orderBy('nachname')->orderBy('vorname')->get();
            $klassen = Klasse::orderBy('name')->get();
        }

        $formatvorlagen   = WpFormatvorlage::orderBy('name')->get();
        $bereitsVorhanden = $wpPlan->kinderPlaene()->pluck('schueler_id')->toArray();

        return view('wochenplan.new.schuelerplan.create', compact(
            'wpPlan', 'schueler', 'formatvorlagen', 'bereitsVorhanden', 'klassen'
        ));
    }

    public function storeSchuelerplan(Request $request, WpPlan $wpPlan)
    {
        $request->validate([
            'schueler_ids'     => 'required|array|min:1',
            'schueler_ids.*'   => 'exists:schueler,id',
            'formatvorlage_id' => 'nullable|exists:wp_formatvorlagen,id',
        ]);

        $formatvorlageId  = $request->input('formatvorlage_id');
        $erstelltePlaene  = [];

        foreach ($request->input('schueler_ids') as $schuelerId) {
            $schueler          = Schueler::findOrFail($schuelerId);
            $erstelltePlaene[] = $wpPlan->erstelleSchuelerplan($schueler, $formatvorlageId);
        }

        $anzahl = count($erstelltePlaene);

        if ($anzahl === 1) {
            return redirect()->route('wp.edit', $erstelltePlaene[0])->with([
                'type'    => 'success',
                'Meldung' => 'Kinderplan für ' . $erstelltePlaene[0]->schueler->vorname . ' wurde erstellt.',
            ]);
        }

        return redirect()->route('wp.edit', $wpPlan)->with([
            'type'    => 'success',
            'Meldung' => $anzahl . ' Kinderpläne wurden erstellt.',
        ]);
    }

    public function addFach(Request $request, WpPlan $wpPlan)
    {
        $request->validate(['wp_fach_id' => 'required|exists:wp_faecher,id']);

        // Prüfen ob Fach bereits vorhanden
        $exists = $wpPlan->planFaecher()->where('wp_fach_id', $request->wp_fach_id)->exists();
        if (!$exists) {
            $maxOrder = $wpPlan->planFaecher()->max('sort_order') ?? -1;
            $wpPlan->planFaecher()->create([
                'wp_fach_id'  => $request->wp_fach_id,
                'sort_order'  => $maxOrder + 1,
            ]);
        }

        return redirect()->back()
            ->with(['type' => 'success', 'Meldung' => 'Fach wurde hinzugefügt.']);
    }

    public function removeFach(WpPlanFach $wpPlanFach)
    {
        $wpPlanFach->aufgaben()->delete();
        $wpPlanFach->delete();

        return redirect()->back()
            ->with(['type' => 'success', 'Meldung' => 'Fach wurde entfernt.']);
    }

    public function reorderFaecher(Request $request)
    {
        $request->validate([
            'order'              => 'required|array',
            'order.*.id'         => 'required|exists:wp_plan_faecher,id',
            'order.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($request->input('order') as $item) {
            WpPlanFach::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json(['status' => 'ok']);
    }

    public function addMedia(Request $request, WpPlan $wpPlan)
    {
        $request->validate([
            'files'   => 'required|array|min:1',
            'files.*' => 'file|mimes:pdf,jpg,jpeg,png,doc,docx,odt|max:10240',
        ]);

        $count = 0;
        foreach ($request->file('files') as $file) {
            $wpPlan->addMedia($file)->toMediaCollection('arbeitsblaetter');
            $count++;
        }

        return redirect()->back()
            ->with(['type' => 'success', 'Meldung' => $count . ' Datei(en) hochgeladen.']);
    }

    public function removeMedia(Media $media)
    {
        $media->delete();

        return redirect()->back()
            ->with(['type' => 'success', 'Meldung' => 'Datei wurde entfernt.']);
    }
}
