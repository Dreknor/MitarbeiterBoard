<?php

namespace App\Http\Controllers\Wochenplan;

use App\Http\Controllers\Controller;
use App\Models\Klasse;
use App\Models\Schueler;
use App\Models\Setting;
use App\Models\Wochenplan\WpPlan;
use App\Services\Wochenplan\WpAblageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Einstellungen des Wochenplans (Recht `edit settings`). Gespeichert in der Tabelle
 * `settings` (Modul „Wochenplan“) – dieselben Werte erscheinen auch unter settings/Wochenplan.
 */
class WpEinstellungenController extends Controller
{
    public const SCHALTER = ['wochenplan_tagebuch_anbindung', 'wochenplan_nextcloud_aktiv'];

    public const TEXTE = ['wochenplan_nextcloud_basisordner', 'wochenplan_ordner_vorlage', 'wochenplan_datei_vorlage', 'wochenplan_kinderplan_vorlage'];

    public function __construct(private WpAblageService $ablage)
    {
    }

    public function index()
    {
        $vorlagen = $this->ablage->vorlagen();

        return view('wochenplan.new.einstellungen', [
            'werte' => [
                'wochenplan_tagebuch_anbindung' => (bool) settings('wochenplan_tagebuch_anbindung'),
                'wochenplan_nextcloud_aktiv' => (bool) settings('wochenplan_nextcloud_aktiv'),
                'wochenplan_nextcloud_basisordner' => (string) (settings('wochenplan_nextcloud_basisordner') ?: '/Wochenpläne'),
                'wochenplan_ordner_vorlage' => $vorlagen['ordner'],
                'wochenplan_datei_vorlage' => $vorlagen['datei'],
                'wochenplan_kinderplan_vorlage' => $vorlagen['kinderplan'],
            ],
            'konfiguriert' => (bool) (config('nextcloud.wochenplan.url') && config('nextcloud.wochenplan.username')),
            'platzhalter' => WpAblageService::PLATZHALTER,
            'platzhalterKind' => WpAblageService::PLATZHALTER_KIND,
            'aktiv' => $this->ablage->aktiv(),
            'statistik' => [
                'ausstehend' => WpPlan::where('ablage_status', 'ausstehend')->count(),
                'fehler' => WpPlan::where('ablage_status', 'fehler')->count(),
                'dateien_fehler' => \App\Models\Wochenplan\WpDatei::whereIn('status', ['fehler', 'fehlt'])->count(),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $daten = $request->validate([
            'wochenplan_tagebuch_anbindung' => 'sometimes|boolean',
            'wochenplan_nextcloud_aktiv' => 'sometimes|boolean',
            'wochenplan_nextcloud_basisordner' => ['required', 'string', 'max:255', 'regex:/^[^\\\\:*?"<>|]+$/u', 'not_regex:/(^|\/)\.\.?(\/|$)/'],
            'wochenplan_ordner_vorlage' => 'required|string|max:255',
            'wochenplan_datei_vorlage' => 'required|string|max:255',
            'wochenplan_kinderplan_vorlage' => 'required|string|max:255',
        ], [
            'wochenplan_nextcloud_basisordner.regex' => 'Der Basisordner enthält unzulässige Zeichen.',
            'wochenplan_nextcloud_basisordner.not_regex' => 'Der Basisordner darf keine Ordner „.“ oder „..“ enthalten.',
        ]);

        $this->vorlagenPruefen($daten);

        foreach (self::SCHALTER as $schluessel) {
            $this->speichern($schluessel, $request->boolean($schluessel) ? '1' : '0');
        }
        foreach (self::TEXTE as $schluessel) {
            $this->speichern($schluessel, trim($daten[$schluessel]));
        }
        Cache::forget('settings');

        return redirect()->route('wp.einstellungen')->with(['type' => 'success', 'Meldung' => 'Einstellungen gespeichert.']);
    }

    /** Live-Vorschau der Ordnerstruktur mit Beispieldaten. */
    public function vorschau(Request $request): JsonResponse
    {
        $vorlagen = [
            'ordner' => (string) $request->input('wochenplan_ordner_vorlage', ''),
            'datei' => (string) $request->input('wochenplan_datei_vorlage', ''),
            'kinderplan' => (string) $request->input('wochenplan_kinderplan_vorlage', ''),
        ];
        $fehler = array_filter([
            'wochenplan_ordner_vorlage' => $this->ablage->vorlageFehler($vorlagen['ordner']),
            'wochenplan_datei_vorlage' => $this->ablage->vorlageFehler($vorlagen['datei']),
            'wochenplan_kinderplan_vorlage' => $this->ablage->vorlageFehler($vorlagen['kinderplan'], true),
        ]);
        if ($fehler) {
            return response()->json(['fehler' => $fehler], 422);
        }

        $beispiel = new WpPlan([
            'name' => 'Wochenplan Herbst',
            'gueltig_von' => now()->startOfWeek(),
            'gueltig_bis' => now()->startOfWeek()->addDays(4),
        ]);
        $beispiel->setRelation('klasse', new Klasse(['name' => '3a']));
        $kind = new WpPlan(['name' => 'Wochenplan Herbst', 'gueltig_von' => $beispiel->gueltig_von, 'gueltig_bis' => $beispiel->gueltig_bis]);
        $kind->setRelation('schueler', (new Schueler(['vorname' => 'Mia', 'nachname' => 'Muster']))->setRelation('klasse', new Klasse(['name' => '3a'])));
        $kind->setRelation('parentPlan', $beispiel);

        $basis = trim((string) $request->input('wochenplan_nextcloud_basisordner', '/Wochenpläne'), '/');
        $werte = $this->ablage->platzhalterWerte($beispiel);
        $ordner = $this->ablage->einsetzen($vorlagen['ordner'], $werte);
        $datei = $this->ablage->einsetzen($vorlagen['datei'], $werte);
        $kindDatei = $this->ablage->einsetzen($vorlagen['kinderplan'], $this->ablage->platzhalterWerte($kind->forceFill(['schueler_id' => 1])));

        return response()->json([
            'beispiel' => [
                $basis.'/'.$ordner.'/'.$datei.'.pdf',
                $basis.'/'.$ordner.'/'.$datei.'.docx',
                $basis.'/'.$ordner.'/Arbeitsblätter/Arbeitsblatt.pdf',
                $basis.'/'.$ordner.'/'.$kindDatei.'.pdf',
                $basis.'/'.$ordner.'/'.$kindDatei.'.docx',
            ],
        ]);
    }

    /** Alle Pläne neu ablegen (z. B. nach geänderter Ordnerstruktur). */
    public function neuAblegen()
    {
        if (! $this->ablage->aktiv()) {
            return redirect()->route('wp.einstellungen')->with(['type' => 'warning', 'Meldung' => 'Die Nextcloud-Ablage ist nicht aktiv.']);
        }

        $anzahl = $this->ablage->alleNeuAblegen();

        return redirect()->route('wp.einstellungen')->with([
            'type' => 'success',
            'Meldung' => $anzahl.' Pläne werden in den nächsten Minuten neu abgelegt.',
        ]);
    }

    private function vorlagenPruefen(array $daten): void
    {
        $fehler = array_filter([
            'wochenplan_ordner_vorlage' => $this->ablage->vorlageFehler($daten['wochenplan_ordner_vorlage']),
            'wochenplan_datei_vorlage' => $this->ablage->vorlageFehler($daten['wochenplan_datei_vorlage']),
            'wochenplan_kinderplan_vorlage' => $this->ablage->vorlageFehler($daten['wochenplan_kinderplan_vorlage'], true),
        ]);
        if ($fehler) {
            throw ValidationException::withMessages($fehler);
        }
    }

    private function speichern(string $schluessel, string $wert): void
    {
        $setting = Setting::where('setting', $schluessel)->first();
        if ($setting) {
            $setting->update(['value' => $wert]);
        } else {
            Setting::create([
                'module' => 'Wochenplan',
                'setting' => $schluessel,
                'setting_name' => $schluessel,
                'type' => in_array($schluessel, self::SCHALTER, true) ? 'boolean' : 'string',
                'value' => $wert,
            ]);
        }
        Cache::forget('setting_'.$schluessel);
    }
}
