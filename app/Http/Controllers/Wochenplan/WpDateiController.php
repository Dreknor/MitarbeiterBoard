<?php

namespace App\Http\Controllers\Wochenplan;

use App\Http\Controllers\Controller;
use App\Models\Wochenplan\WpDatei;
use App\Models\Wochenplan\WpPlan;
use App\Services\Wochenplan\WochenplanService;
use App\Services\Wochenplan\WpAblageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Arbeitsblätter: Upload, Auslieferung (nur mit Leserecht am Plan), Entfernen,
 * erneuter Upload und Auswahl vorhandener Nextcloud-Dateien.
 */
class WpDateiController extends Controller
{
    public const ERLAUBTE_ENDUNGEN = 'pdf,jpg,jpeg,png,doc,docx,odt';

    public function __construct(
        private WochenplanService $service,
        private WpAblageService $ablage,
    ) {
    }

    public function store(Request $request, WpPlan $wpPlan): JsonResponse
    {
        $this->authorize('update', $wpPlan);
        $request->validate([
            'files' => 'required|array|min:1|max:20',
            'files.*' => 'file|mimes:'.self::ERLAUBTE_ENDUNGEN.'|max:20480',
        ], [
            'files.*.mimes' => 'Erlaubt sind PDF, Bilder (JPG/PNG), Word und ODT.',
            'files.*.max' => 'Eine Datei darf höchstens 20 MB groß sein.',
        ]);

        foreach ($request->file('files') as $datei) {
            $this->ablage->dateiSpeichern($wpPlan, $datei, $request->user()->id);
        }
        $this->service->geaendert($wpPlan);

        return response()->json(['plan' => $this->service->toArray($wpPlan->fresh(), $request->user())]);
    }

    /**
     * Liefert ein Arbeitsblatt aus. Zugriff mit Leserecht am Plan der Datei – oder bei
     * Arbeitsblättern eines Klassenplans auch über einen seiner Kinderpläne.
     */
    public function show(Request $request, WpDatei $wpDatei)
    {
        $plan = $wpDatei->plan;
        abort_unless($plan, 404);

        $user = $request->user();
        $darf = $user->can('view', $plan)
            || $plan->kinderPlaene()->get()->contains(fn (WpPlan $kind) => $user->can('view', $kind));
        abort_unless($darf, 403);

        $inhalt = $this->ablage->dateiInhalt($wpDatei);
        abort_if($inhalt === null, 404, 'Die Datei ist derzeit nicht verfügbar.');

        $art = $request->boolean('download') ? 'attachment' : 'inline';
        $name = str_replace('"', '', $wpDatei->name);

        return response($inhalt, 200, [
            'Content-Type' => $wpDatei->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => $art.'; filename="'.Str::ascii($name).'"; filename*=UTF-8\'\''.rawurlencode($name),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function destroy(Request $request, WpPlan $wpPlan, WpDatei $wpDatei): JsonResponse
    {
        $this->authorize('update', $wpPlan);
        abort_unless((int) $wpDatei->wp_plan_id === (int) $wpPlan->id, 404);

        $this->service->dateiEntfernen($wpDatei);

        return response()->json(['plan' => $this->service->toArray($wpPlan->fresh(), $request->user())]);
    }

    public function erneut(Request $request, WpPlan $wpPlan): JsonResponse
    {
        $this->authorize('update', $wpPlan);

        $this->ablage->erneutVersuchen($wpPlan);

        return response()->json([
            'plan' => $this->service->toArray($wpPlan->fresh(), $request->user()),
            'meldung' => 'Ablage wird erneut versucht.',
        ]);
    }

    /** Inhalt eines Nextcloud-Ordners für die Dateiauswahl (relativ zum Basisordner). */
    public function nextcloudListe(Request $request, WpPlan $wpPlan): JsonResponse
    {
        $this->authorize('update', $wpPlan);
        abort_unless($this->ablage->aktiv(), 404, 'Die Nextcloud-Ablage ist nicht aktiv.');

        $pfad = $this->sichererPfad((string) $request->query('pfad', ''));

        $eintraege = collect($this->ablage->speicher()->liste($pfad))
            ->reject(fn ($e) => $e['name'] === WpAblageService::GELOESCHT_ORDNER)
            ->filter(fn ($e) => $e['ordner'] || in_array(strtolower(pathinfo($e['name'], PATHINFO_EXTENSION)), explode(',', self::ERLAUBTE_ENDUNGEN), true))
            ->values();

        return response()->json(['pfad' => $pfad, 'eintraege' => $eintraege]);
    }

    /** Verknüpft vorhandene Nextcloud-Dateien mit dem Plan (ohne erneuten Upload). */
    public function nextcloudVerknuepfen(Request $request, WpPlan $wpPlan): JsonResponse
    {
        $this->authorize('update', $wpPlan);
        abort_unless($this->ablage->aktiv(), 404, 'Die Nextcloud-Ablage ist nicht aktiv.');

        $daten = $request->validate(['pfade' => 'required|array|min:1|max:20', 'pfade.*' => 'required|string|max:1000']);

        foreach ($daten['pfade'] as $roh) {
            $pfad = $this->sichererPfad($roh);
            $ordner = trim(dirname($pfad), './');
            $eintrag = collect($this->ablage->speicher()->liste($ordner))->firstWhere('pfad', $pfad);
            if (! $eintrag || $eintrag['ordner']) {
                continue;
            }
            if (! in_array(strtolower(pathinfo($eintrag['name'], PATHINFO_EXTENSION)), explode(',', self::ERLAUBTE_ENDUNGEN), true)) {
                continue;
            }
            $this->ablage->nextcloudDateiVerknuepfen($wpPlan, $eintrag, $request->user()->id);
        }
        $this->service->geaendert($wpPlan);

        return response()->json(['plan' => $this->service->toArray($wpPlan->fresh(), $request->user())]);
    }

    private function sichererPfad(string $pfad): string
    {
        $teile = array_filter(explode('/', str_replace('\\', '/', $pfad)), fn ($t) => $t !== '' && $t !== '.' && $t !== '..');

        return implode('/', $teile);
    }
}
