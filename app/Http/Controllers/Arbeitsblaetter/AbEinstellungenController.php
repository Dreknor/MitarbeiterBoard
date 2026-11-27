<?php

namespace App\Http\Controllers\Arbeitsblaetter;

use App\Http\Controllers\Controller;
use App\Models\Arbeitsblaetter\AngepasstesBlatt;
use App\Models\Klasse;
use App\Models\Schueler;
use App\Models\Setting;
use App\Services\Arbeitsblaetter\AbAusgabeService;
use App\Services\Arbeitsblaetter\AbPruefung;
use App\Services\Arbeitsblaetter\AbKiBudget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Einstellungen der Angepassten Arbeitsblätter (Recht `edit settings`): Ordnervorlagen mit
 * Vorschau, Automatik im Wochenplan, DOCX, KI-Limit + Verbrauch, Status der Verbindungen.
 * Gespeichert in `settings` (Modul „Angepasste Arbeitsblätter“).
 */
class AbEinstellungenController extends Controller
{
    public const SCHALTER = ['ab_wochenplan_automatisch', 'ab_docx_ausgabe', 'ab_ms_schriften_laden'];

    public const TEXTE = ['ab_ordner_vorlage', 'ab_datei_vorlage', 'ab_ki_limit_seiten', 'ab_ki_limit_euro', 'ab_ki_warnung_prozent'];

    public function __construct(private AbAusgabeService $ausgabe, private AbKiBudget $budget)
    {
    }

    public function index(Request $request)
    {
        $this->rechtPruefen($request);
        $vorlagen = $this->ausgabe->vorlagen();
        $verbrauch = $this->budget->verbrauch();

        return view('arbeitsblaetter.einstellungen', [
            'werte' => [
                'ab_ordner_vorlage' => $vorlagen['ordner'],
                'ab_datei_vorlage' => $vorlagen['datei'],
                'ab_wochenplan_automatisch' => \App\Services\Arbeitsblaetter\AbWochenplanService::automatikAn(),
                'ab_docx_ausgabe' => (bool) settings('ab_docx_ausgabe'),
                'ab_ms_schriften_laden' => (bool) settings('ab_ms_schriften_laden'),
                'ab_ki_limit_seiten' => (string) (settings('ab_ki_limit_seiten') ?? '0'),
                'ab_ki_limit_euro' => (string) (settings('ab_ki_limit_euro') ?? '0'),
                'ab_ki_warnung_prozent' => (string) (settings('ab_ki_warnung_prozent') ?: '80'),
            ],
            'platzhalter' => AbAusgabeService::PLATZHALTER,
            'verbrauch' => $verbrauch,
            'prozent' => $this->budget->prozent($verbrauch),
            'limits' => $this->budget->limits(),
            'status' => app(AbPruefung::class)->status(false),
            'schriften' => collect(\App\Services\Arbeitsblaetter\AbSatzService::eigeneSchriften())
                ->mapWithKeys(fn ($s) => [\App\Support\AbProfil::SCHRIFTEN[$s] => ! \App\Services\Arbeitsblaetter\AbSatzService::nutztErsatz($s)])->all(),
            'schriftenOrdner' => app(\App\Services\Arbeitsblaetter\AbSchriftenService::class)->zielOrdner(),
            'statistik' => [
                'blaetter' => AngepasstesBlatt::count(),
                'zur_pruefung' => AngepasstesBlatt::where('status', AngepasstesBlatt::ZU_PRUEFEN)->count(),
                'fehler' => AngepasstesBlatt::where('status', AngepasstesBlatt::FEHLER)->count(),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $this->rechtPruefen($request);
        $daten = $request->validate([
            'ab_ordner_vorlage' => 'required|string|max:255',
            'ab_datei_vorlage' => 'required|string|max:255',
            'ab_ki_limit_seiten' => 'required|integer|min:0|max:100000',
            'ab_ki_limit_euro' => ['required', 'regex:/^\d{1,6}([.,]\d{1,2})?$/'],
            'ab_ki_warnung_prozent' => 'required|integer|min:1|max:100',
            'ab_wochenplan_automatisch' => 'sometimes|boolean',
            'ab_docx_ausgabe' => 'sometimes|boolean',
            'ab_ms_schriften_laden' => 'sometimes|boolean',
        ], ['ab_ki_limit_euro.regex' => 'Bitte einen Betrag in Euro angeben (z. B. 20 oder 12,50).']);

        $fehler = array_filter([
            'ab_ordner_vorlage' => $this->ausgabe->vorlageFehler($daten['ab_ordner_vorlage']),
            'ab_datei_vorlage' => $this->ausgabe->vorlageFehler($daten['ab_datei_vorlage']),
        ]);
        if ($fehler) {
            throw ValidationException::withMessages($fehler);
        }

        foreach (self::SCHALTER as $schluessel) {
            $this->speichern($schluessel, $request->boolean($schluessel) ? '1' : '0');
        }
        foreach (self::TEXTE as $schluessel) {
            $this->speichern($schluessel, str_replace(',', '.', trim((string) $daten[$schluessel])));
        }

        return redirect()->route('arbeitsblaetter.einstellungen')->with(['type' => 'success', 'Meldung' => 'Einstellungen gespeichert.']);
    }

    /** Live-Vorschau des Ablagepfads mit Beispieldaten. */
    public function vorschau(Request $request): JsonResponse
    {
        $this->rechtPruefen($request);
        $ordner = (string) $request->input('ab_ordner_vorlage', '');
        $datei = (string) $request->input('ab_datei_vorlage', '');
        $fehler = array_filter([
            'ab_ordner_vorlage' => $this->ausgabe->vorlageFehler($ordner),
            'ab_datei_vorlage' => $this->ausgabe->vorlageFehler($datei),
        ]);
        if ($fehler) {
            return response()->json(['fehler' => $fehler], 422);
        }

        $kind = (new Schueler(['vorname' => 'Mia', 'nachname' => 'Muster']))->setRelation('klasse', new Klasse(['name' => '3a']));
        $blatt = new AngepasstesBlatt(['titel' => 'Brüche Seite 12']);
        $werte = $this->ausgabe->platzhalterWerte($blatt, $kind, null);
        $einsetzen = fn (string $m) => preg_replace_callback('/\{([a-z]+)\}/', fn ($t) => (string) ($werte[$t[1]] ?? ''), $m);

        return response()->json(['beispiel' => trim($einsetzen($ordner), '/').'/'.$einsetzen($datei).'.pdf']);
    }

    /** Arial/Verdana jetzt bereitstellen (Server-Schriftordner, mit Freigabe auch Download von Microsoft). */
    public function schriften(Request $request)
    {
        $this->rechtPruefen($request);
        $service = app(\App\Services\Arbeitsblaetter\AbSchriftenService::class);

        try {
            $ergebnis = $service->automatisch();
        } catch (\Throwable $e) {
            return redirect()->route('arbeitsblaetter.einstellungen')->with(['type' => 'warning', 'Meldung' => 'Schriften konnten nicht geladen werden: '.$e->getMessage()]);
        }

        if (! $ergebnis['fehlt']) {
            return redirect()->route('arbeitsblaetter.einstellungen')->with(['type' => 'success', 'Meldung' => 'Arial und Verdana sind jetzt im Original vorhanden.']);
        }

        return redirect()->route('arbeitsblaetter.einstellungen')->with([
            'type' => 'warning',
            'Meldung' => 'Es fehlen noch: '.implode(', ', $ergebnis['fehlt']).'. '.($ergebnis['hinweis'] ?? ''),
        ]);
    }

    /** Verbindungen aktiv prüfen (KI, Nextcloud, Umwandlung). */
    public function pruefen(Request $request): JsonResponse
    {
        $this->rechtPruefen($request);

        return response()->json(['status' => app(AbPruefung::class)->status(true)]);
    }

    private function rechtPruefen(Request $request): void
    {
        abort_unless($request->user()->can('edit settings'), 403);
    }

    private function speichern(string $schluessel, string $wert): void
    {
        $setting = Setting::where('setting', $schluessel)->first();
        if ($setting) {
            $setting->update(['value' => $wert]);
        } else {
            Setting::create([
                'module' => 'Angepasste Arbeitsblätter',
                'setting' => $schluessel,
                'setting_name' => $schluessel,
                'type' => in_array($schluessel, self::SCHALTER, true) ? 'boolean' : 'string',
                'value' => $wert,
            ]);
        }
        Cache::forget('setting_'.$schluessel);
    }
}
