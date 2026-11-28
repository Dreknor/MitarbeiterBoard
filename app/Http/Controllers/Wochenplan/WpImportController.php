<?php

namespace App\Http\Controllers\Wochenplan;

use App\Http\Controllers\Controller;
use App\Models\Schueler;
use App\Models\Wochenplan\WpPlan;
use App\Services\Wochenplan\Import\WpImportService;
use App\Services\Wochenplan\WochenplanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Import von Word-Wochenplänen: Hochladen → Vorschau (zwischengespeichert je Person/Token) → Anlegen.
 * Die Dateien selbst werden nicht aufbewahrt, nur das Ergebnis des Einlesens.
 */
class WpImportController extends Controller
{
    private const DAUER_MINUTEN = 120;

    public function __construct(private WpImportService $import, private WochenplanService $wochenplan)
    {
    }

    public function formular()
    {
        $this->authorize('create', WpPlan::class);

        return view('wochenplan.new.import.formular', ['klassen' => $this->wochenplan->klassenFuer(auth()->user())]);
    }

    public function vorschau(Request $request): RedirectResponse
    {
        $this->authorize('create', WpPlan::class);
        $daten = $request->validate([
            'dateien' => 'required|array|max:20',
            'dateien.*' => 'file|max:20480',
            'klassen' => 'nullable|array',
            'klassen.*' => 'integer',
        ], ['dateien.required' => 'Bitte mindestens eine Word-Datei auswählen.']);

        $dateien = collect($request->file('dateien'))->map(fn ($f) => [
            'name' => $f->getClientOriginalName(),
            'pfad' => $f->getPathname(),   // getRealPath() liefert unter Windows für Upload-Temp-Dateien teils false
        ])->all();

        $vorschau = $this->import->vorschau($dateien, $request->user(), array_map('intval', $daten['klassen'] ?? []));
        $token = Str::random(24);
        Cache::put($this->schluessel($request, $token), $vorschau, now()->addMinutes(self::DAUER_MINUTEN));

        return redirect()->route('wp.import.anzeigen', $token);
    }

    public function anzeigen(Request $request, string $token)
    {
        $this->authorize('create', WpPlan::class);
        $vorschau = Cache::get($this->schluessel($request, $token));
        if ($vorschau === null) {
            return redirect()->route('wp.import')->with(['Meldung' => 'Die Vorschau ist abgelaufen – bitte die Dateien erneut hochladen.', 'type' => 'warning']);
        }

        $klassen = $this->wochenplan->klassenFuer($request->user());
        $kinder = Schueler::whereIn('klasse_id', $klassen->pluck('id'))->orderBy('vorname')->orderBy('nachname')->get(['id', 'vorname', 'nachname', 'klasse_id'])->groupBy('klasse_id');

        return view('wochenplan.new.import.vorschau', compact('vorschau', 'token', 'klassen', 'kinder'));
    }

    public function importieren(Request $request, string $token): RedirectResponse
    {
        $this->authorize('create', WpPlan::class);
        $vorschau = Cache::get($this->schluessel($request, $token));
        if ($vorschau === null) {
            return redirect()->route('wp.import')->with(['Meldung' => 'Die Vorschau ist abgelaufen – bitte die Dateien erneut hochladen.', 'type' => 'warning']);
        }

        $daten = $request->validate([
            'plaene' => 'nullable|array',
            'plaene.*.importieren' => 'nullable|boolean',
            'plaene.*.klassen' => 'nullable|array',
            'plaene.*.klassen.*' => 'integer',
            'plaene.*.schueler_id' => 'nullable|integer',
            'plaene.*.von' => 'nullable|date',
            'plaene.*.bis' => 'nullable|date|after_or_equal:plaene.*.von',
        ]);

        $ergebnis = $this->import->importieren($vorschau, $daten['plaene'] ?? [], $request->user());
        Cache::forget($this->schluessel($request, $token));

        $meldung = $ergebnis['angelegt'].' Plan/Pläne importiert'.($ergebnis['uebersprungen'] ? ', '.$ergebnis['uebersprungen'].' übersprungen' : '').'.';
        if ($ergebnis['meldungen']) {
            $meldung .= ' '.implode(' ', array_slice($ergebnis['meldungen'], 0, 5));
        }

        return redirect()->route('wp.index')->with(['Meldung' => $meldung, 'type' => $ergebnis['angelegt'] ? 'success' : 'warning']);
    }

    private function schluessel(Request $request, string $token): string
    {
        return 'wp-import:'.$request->user()->id.':'.$token;
    }
}
