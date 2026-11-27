<?php

namespace App\Http\Controllers;

use App\Models\Monitoring\MonitoringDienst;
use App\Services\Monitoring\MonitoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Monitoring (Systemstatus): Statusleiste im Dashboard und Verwaltung im Reiter
 * „Monitoring“ der Einstellungen (Recht `edit settings`, Routen in routes/web.php).
 * Logik: App\Services\Monitoring\MonitoringService.
 */
class MonitoringController extends Controller
{
    public function __construct(private MonitoringService $monitoring)
    {
    }

    /** GET monitoring/statusleiste – Statusleiste neu laden (Dashboard aktualisiert sie jede Minute). */
    public function statusleiste()
    {
        if (! $this->monitoring->sichtbarFuer(auth()->user())) {
            return response('', 204);
        }

        return view('monitoring._leiste', [
            'monAnzeige' => $this->monitoring->anzeige(),
            'monVerwalten' => (bool) auth()->user()->can('edit settings'),
        ]);
    }

    /** POST monitoring/einstellungen */
    public function update(Request $request)
    {
        $daten = $request->validate([
            'rollen_sichtbar' => ['nullable', 'array'],
            'rollen_sichtbar.*' => ['integer', Rule::exists('roles', 'id')],
            'rollen_benachrichtigen' => ['nullable', 'array'],
            'rollen_benachrichtigen.*' => ['integer', Rule::exists('roles', 'id')],
            'heartbeat_minuten' => ['required', 'integer', 'min:2', 'max:240'],
            'dienste' => ['nullable', 'array'],
            'dienste.*.name' => ['required', 'string', 'max:100'],
            'dienste.*.url' => ['nullable', 'string', 'max:500', 'url:http,https'],
            'dienste.*.abhaengig_von_id' => ['nullable', 'integer', Rule::exists('monitoring_dienste', 'id')],
            'dienste.*.sortierung' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [], [
            'heartbeat_minuten' => 'Zeitlimit',
            'dienste.*.name' => 'Name',
            'dienste.*.url' => 'Adresse',
        ]);

        $this->monitoring->einstellungSpeichern('monitoring_rollen_sichtbar', json_encode(array_map('intval', $daten['rollen_sichtbar'] ?? [])));
        $this->monitoring->einstellungSpeichern('monitoring_rollen_benachrichtigen', json_encode(array_map('intval', $daten['rollen_benachrichtigen'] ?? [])));
        $this->monitoring->einstellungSpeichern('monitoring_heartbeat_minuten', (string) $daten['heartbeat_minuten']);

        $eingaben = $daten['dienste'] ?? [];
        $dienste = MonitoringDienst::whereIn('id', array_keys($eingaben))->get()->keyBy('id');

        foreach ($eingaben as $id => $eingabe) {
            $dienst = $dienste->get($id);
            if ($dienst === null) {
                continue;
            }

            $eltern = $eingabe['abhaengig_von_id'] ?? null;
            if ($eltern !== null && ($dienst->istQuelle() || $this->wuerdeKreisBilden((int) $id, (int) $eltern, $eingaben))) {
                throw ValidationException::withMessages(["dienste.$id.abhaengig_von_id" => "„{$dienst->name}“ kann nicht von diesem Dienst abhängen."]);
            }

            if ($dienst->typ === MonitoringDienst::TYP_HTTP && empty($eingabe['url'])) {
                throw ValidationException::withMessages(["dienste.$id.url" => "Für „{$dienst->name}“ ist eine Adresse nötig."]);
            }

            $aktiv = $request->boolean("dienste.$id.aktiv");

            $dienst->fill([
                'name' => trim($eingabe['name']),
                'aktiv' => $aktiv,
                'abhaengig_von_id' => $eltern !== null ? (int) $eltern : null,
                'sortierung' => (int) ($eingabe['sortierung'] ?? $dienst->sortierung),
            ]);

            if ($dienst->typ === MonitoringDienst::TYP_HTTP && $dienst->url !== $eingabe['url']) {
                $dienst->url = $eingabe['url'];
                $dienst->fehlversuche = 0;
            }

            // Deaktivierte Dienste melden keine Behebung mehr
            if (! $aktiv) {
                $dienst->gemeldet = false;
            }

            $dienst->save();
        }

        return $this->zurueck('success', 'Monitoring-Einstellungen gespeichert.');
    }

    /**
     * Würde „$id hängt von $eltern ab“ einen Kreis bilden (inkl. der gerade eingegebenen Abhängigkeiten)?
     */
    private function wuerdeKreisBilden(int $id, int $eltern, array $eingaben): bool
    {
        $bekannt = MonitoringDienst::pluck('abhaengig_von_id', 'id')->all();
        foreach ($eingaben as $dienstId => $eingabe) {
            $bekannt[$dienstId] = isset($eingabe['abhaengig_von_id']) ? (int) $eingabe['abhaengig_von_id'] : null;
        }

        $aktuell = $eltern;
        for ($i = 0; $i < 50 && $aktuell !== null; $i++) {
            if ($aktuell === $id) {
                return true;
            }
            $aktuell = $bekannt[$aktuell] ?? null;
        }

        return false;
    }

    /** POST monitoring/token – neues Heartbeat-Token (wird einmalig angezeigt). */
    public function token()
    {
        $token = $this->monitoring->tokenErzeugen();

        return redirect()->to(url('settings/Monitoring'))->with([
            'type' => 'success',
            'Meldung' => 'Neues Token erzeugt. Das bisherige Token ist ab sofort ungültig.',
            'monitoring_token' => $token,
        ]);
    }

    /** POST monitoring/dienste – Webserver hinzufügen. */
    public function dienstAnlegen(Request $request)
    {
        $daten = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'url' => ['required', 'string', 'max:500', 'url:http,https'],
        ], [], ['url' => 'Adresse']);

        $dienst = MonitoringDienst::create([
            'schluessel' => 'http-'.Str::lower(Str::random(10)),
            'name' => trim($daten['name']),
            'typ' => MonitoringDienst::TYP_HTTP,
            'url' => $daten['url'],
            'sortierung' => (int) MonitoringDienst::max('sortierung') + 1,
        ]);

        $this->monitoring->webserverPruefen($dienst);

        return $this->zurueck('success', "„{$dienst->name}“ wird jetzt überwacht.");
    }

    /** DELETE monitoring/dienste/{dienst} */
    public function dienstLoeschen(MonitoringDienst $dienst)
    {
        $name = $dienst->name;
        $dienst->delete();

        return $this->zurueck('success', "„{$name}“ wird nicht mehr überwacht.");
    }

    /** POST monitoring/pruefen – Prüfung sofort ausführen. */
    public function pruefen()
    {
        $this->monitoring->pruefen();

        return $this->zurueck('success', 'Prüfung ausgeführt.');
    }

    private function zurueck(string $typ, string $meldung)
    {
        return redirect()->to(url('settings/Monitoring'))->with(['type' => $typ, 'Meldung' => $meldung]);
    }
}
