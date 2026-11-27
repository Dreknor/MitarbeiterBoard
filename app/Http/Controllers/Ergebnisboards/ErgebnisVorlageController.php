<?php

namespace App\Http\Controllers\Ergebnisboards;

use App\Http\Controllers\Controller;
use App\Models\Ergebnisboard;
use App\Models\ErgebnisVorlage;
use App\Services\Ergebnisboards\ErgebnisVorlagenService;
use App\Support\ErgebnisboardVorlagen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Vorlagen-Editor der Ergebnisboards (nur `manage result boards`) und „Board als Vorlage speichern“.
 * Speichern läuft als JSON (Alpine-Editor), Regeln in ErgebnisVorlagenService.
 */
class ErgebnisVorlageController extends Controller
{
    public function __construct(private ErgebnisVorlagenService $service)
    {
    }

    public function index(): View
    {
        $this->authorize('manageTemplates', Ergebnisboard::class);

        return view('ergebnisboards.vorlagen.index', [
            'eingebaut' => array_filter(ErgebnisboardVorlagen::alle(false), fn ($v) => ! $v['eigene']),
            'eigene' => ErgebnisVorlage::query()->with('ersteller')->orderBy('titel')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('manageTemplates', Ergebnisboard::class);
        $basis = ErgebnisboardVorlagen::finden((string) $request->input('basis', 'frei')) ?? ErgebnisboardVorlagen::finden('frei');

        return view('ergebnisboards.vorlagen.form', [
            'vorlage' => null,
            'definition' => array_merge($basis, ['titel' => '', 'beschreibung' => null, 'aktiv' => true]),
            'farben' => ErgebnisboardVorlagen::FARBEN,
            'status' => ErgebnisboardVorlagen::STATUS,
        ]);
    }

    public function edit(ErgebnisVorlage $vorlage): View
    {
        $this->authorize('manageTemplates', Ergebnisboard::class);

        return view('ergebnisboards.vorlagen.form', [
            'vorlage' => $vorlage,
            'definition' => array_merge(ErgebnisboardVorlagen::normalisieren($vorlage->definition ?? []), [
                'titel' => $vorlage->titel,
                'beschreibung' => $vorlage->beschreibung,
                'aktiv' => $vorlage->aktiv,
            ]),
            'farben' => ErgebnisboardVorlagen::FARBEN,
            'status' => ErgebnisboardVorlagen::STATUS,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('manageTemplates', Ergebnisboard::class);
        $vorlage = $this->service->speichern(null, $this->validieren($request), $request->user());

        return response()->json(['message' => 'Vorlage angelegt.', 'redirect' => route('ergebnisboards.vorlagen.index'), 'id' => $vorlage->id], 201);
    }

    public function update(Request $request, ErgebnisVorlage $vorlage): JsonResponse
    {
        $this->authorize('manageTemplates', Ergebnisboard::class);
        $this->service->speichern($vorlage, $this->validieren($request), $request->user());

        return response()->json(['message' => 'Vorlage gespeichert.', 'redirect' => route('ergebnisboards.vorlagen.index'), 'id' => $vorlage->id]);
    }

    /** Löschen – bestehende Boards behalten ihr Raster (es wurde beim Anlegen kopiert). */
    public function destroy(ErgebnisVorlage $vorlage): RedirectResponse
    {
        $this->authorize('manageTemplates', Ergebnisboard::class);
        $vorlage->delete();

        return redirect()->route('ergebnisboards.vorlagen.index')->with(['type' => 'success', 'Meldung' => 'Vorlage gelöscht. Bestehende Boards bleiben unverändert.']);
    }

    /** POST ergebnisboards/{board}/als-vorlage – Raster des Boards als eigene Vorlage. */
    public function ausBoard(Request $request, Ergebnisboard $board): RedirectResponse
    {
        $this->authorize('manageTemplates', Ergebnisboard::class);
        $this->authorize('view', $board);
        $daten = $request->validate([
            'titel' => ['required', 'string', 'max:255'],
            'beschreibung' => ['nullable', 'string', 'max:500'],
        ]);
        $vorlage = $this->service->ausBoard($board, $daten['titel'], $daten['beschreibung'] ?? null, $request->user());

        return redirect()->route('ergebnisboards.vorlagen.edit', $vorlage)->with(['type' => 'success', 'Meldung' => 'Vorlage aus dem Board angelegt.']);
    }

    private function validieren(Request $request): array
    {
        return $request->validate([
            'titel' => ['required', 'string', 'max:255'],
            'beschreibung' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:60'],
            'spalten' => ['required', 'string', 'max:120'],
            'bereiche_frei' => ['sometimes', 'boolean'],
            'auto_status' => ['sometimes', 'boolean'],
            'aktiv' => ['sometimes', 'boolean'],
            'bereiche' => ['required', 'array', 'min:1', 'max:40'],
            'bereiche.*.schluessel' => ['nullable', 'string', 'max:64'],
            'bereiche.*.titel' => ['required', 'string', 'max:80'],
            'bereiche.*.hinweis' => ['nullable', 'string', 'max:200'],
            'bereiche.*.badge' => ['nullable', 'string', 'max:60'],
            'bereiche.*.farbe' => ['nullable', 'in:'.implode(',', array_keys(ErgebnisboardVorlagen::FARBEN))],
            'bereiche.*.icon' => ['nullable', 'string', 'max:60'],
            'bereiche.*.spalte' => ['nullable', 'integer', 'min:1', 'max:12'],
            'bereiche.*.spalten' => ['nullable', 'integer', 'min:1', 'max:12'],
            'bereiche.*.zeile' => ['nullable', 'integer', 'min:1', 'max:12'],
            'bereiche.*.zeilen' => ['nullable', 'integer', 'min:1', 'max:12'],
            'bereiche.*.raster' => ['nullable', 'in:1,2'],
            'bereiche.*.eltern' => ['nullable', 'string', 'max:64'],
            'bereiche.*.status' => ['nullable', 'in:'.implode(',', ErgebnisboardVorlagen::STATUS)],
            'achsen' => ['nullable', 'array'],
            'legende' => ['nullable', 'array'],
        ]);
    }
}
