<?php

namespace App\Services\Monitoring;

use App\Models\Monitoring\MonitoringDienst;
use App\Models\Monitoring\MonitoringEreignis;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\SystemstatusGeaendert;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Throwable;

/**
 * Systemstatus: Heartbeat von außen (z. B. Home Assistant mit WireGuard-Tunnel ins Schulnetz)
 * und eigene HTTP-Prüfungen von Webservern.
 *
 * Regeln:
 *  - Jede Statusänderung wird in `monitoring_ereignisse` protokolliert.
 *  - Benachrichtigt werden die Rollen aus der Einstellung `monitoring_rollen_benachrichtigen`
 *    (Kategorie „system“, Kanäle nach persönlicher Einstellung) – nur bei Wechseln, nie bei jeder Prüfung.
 *  - Folgefehler werden zurückgehalten: Ein Dienst wird nicht als gestört gemeldet, solange der Dienst,
 *    von dem er abhängt (z. B. RDP → VPN), gestört ist oder – bei Heartbeat-Diensten – der Heartbeat
 *    ausgeblieben ist. Fällt die Ursache weg und der Fehler bleibt, wird er dann gemeldet.
 *  - Webserver gelten erst nach HTTP_FEHLVERSUCHE Fehlschlägen in Folge als gestört.
 */
class MonitoringService
{
    public const HTTP_FEHLVERSUCHE = 2;

    public const MAX_HEARTBEAT_DIENSTE = 30;

    public const SCHLUESSEL_REGEX = '/^[a-z0-9][a-z0-9_-]{0,39}$/';

    /*
    |--------------------------------------------------------------------------
    | Einstellungen
    |--------------------------------------------------------------------------
    */

    /** @return array<int, int> */
    public function rollenSichtbar(): array
    {
        return $this->rollenAus('monitoring_rollen_sichtbar');
    }

    /** @return array<int, int> */
    public function rollenBenachrichtigen(): array
    {
        return $this->rollenAus('monitoring_rollen_benachrichtigen');
    }

    public function heartbeatMinuten(): int
    {
        $wert = (int) settings('monitoring_heartbeat_minuten');

        return $wert >= 2 ? $wert : 15;
    }

    public function einstellungSpeichern(string $schluessel, string $wert): void
    {
        $vorhanden = Setting::where('setting', $schluessel)->exists();

        if ($vorhanden) {
            Setting::where('setting', $schluessel)->update(['value' => $wert]);
        } else {
            Setting::create([
                'module' => 'Monitoring',
                'setting' => $schluessel,
                'setting_name' => $schluessel,
                'type' => 'hidden',
                'value' => $wert,
            ]);
        }

        Cache::forget('setting_'.$schluessel);
        Cache::forget('settings');
    }

    /** @return array<int, int> */
    private function rollenAus(string $schluessel): array
    {
        $werte = json_decode((string) settings($schluessel), true);

        return is_array($werte) ? array_values(array_unique(array_map('intval', $werte))) : [];
    }

    /*
    |--------------------------------------------------------------------------
    | Token
    |--------------------------------------------------------------------------
    */

    public function tokenEingerichtet(): bool
    {
        return (string) settings('monitoring_token_hash') !== '';
    }

    /** Erzeugt ein neues Token (das alte wird ungültig) und gibt es einmalig im Klartext zurück. */
    public function tokenErzeugen(): string
    {
        $token = Str::random(48);

        $this->einstellungSpeichern('monitoring_token_hash', hash('sha256', $token));
        $this->einstellungSpeichern('monitoring_token_erstellt', now()->toIso8601String());

        return $token;
    }

    public function tokenGueltig(?string $token): bool
    {
        $hash = (string) settings('monitoring_token_hash');

        return $hash !== '' && $token !== null && $token !== '' && hash_equals($hash, hash('sha256', $token));
    }

    /*
    |--------------------------------------------------------------------------
    | Heartbeat
    |--------------------------------------------------------------------------
    */

    /**
     * Verarbeitet eine Meldung des Heartbeat-Absenders.
     *
     * @param  array<string, array{status: string, meldung?: ?string, latenz_ms?: ?int}>  $dienste
     * @return array{aktualisiert: array<int, string>, ignoriert: array<int, string>}
     */
    public function heartbeatVerarbeiten(array $dienste): array
    {
        $quelle = $this->quelle();
        $this->statusSetzen($quelle, MonitoringDienst::STATUS_OK, null, null);

        $aktualisiert = [];
        $ignoriert = [];

        foreach ($dienste as $schluessel => $meldung) {
            $dienst = MonitoringDienst::where('schluessel', $schluessel)->first();

            if ($dienst === null) {
                if (MonitoringDienst::where('typ', MonitoringDienst::TYP_HEARTBEAT)->count() >= self::MAX_HEARTBEAT_DIENSTE) {
                    $ignoriert[] = $schluessel;
                    continue;
                }

                $dienst = MonitoringDienst::create([
                    'schluessel' => $schluessel,
                    'name' => $this->standardName($schluessel),
                    'typ' => MonitoringDienst::TYP_HEARTBEAT,
                    'sortierung' => (int) MonitoringDienst::max('sortierung') + 1,
                ]);
            }

            if ($dienst->typ !== MonitoringDienst::TYP_HEARTBEAT) {
                $ignoriert[] = $schluessel;
                continue;
            }

            $status = $meldung['status'] === 'ok' ? MonitoringDienst::STATUS_OK : MonitoringDienst::STATUS_FEHLER;
            $this->statusSetzen($dienst, $status, $meldung['meldung'] ?? null, $meldung['latenz_ms'] ?? null);
            $aktualisiert[] = $schluessel;
        }

        $this->melden();

        return ['aktualisiert' => $aktualisiert, 'ignoriert' => $ignoriert];
    }

    public function quelle(): MonitoringDienst
    {
        return MonitoringDienst::firstOrCreate(
            ['schluessel' => MonitoringDienst::QUELLE],
            ['name' => 'Überwachung (Heartbeat)', 'typ' => MonitoringDienst::TYP_QUELLE, 'sortierung' => 0],
        );
    }

    private function standardName(string $schluessel): string
    {
        return match ($schluessel) {
            'vpn' => 'VPN',
            'rdp' => 'Terminalserver (RDP)',
            default => Str::of($schluessel)->replace(['_', '-'], ' ')->title()->toString(),
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Regelmäßige Prüfung (Scheduler, alle 5 Minuten)
    |--------------------------------------------------------------------------
    */

    public function pruefen(): void
    {
        $this->heartbeatZeitlimitPruefen();

        MonitoringDienst::where('typ', MonitoringDienst::TYP_HTTP)
            ->where('aktiv', true)
            ->orderBy('sortierung')
            ->get()
            ->each(fn (MonitoringDienst $dienst) => $this->webserverPruefen($dienst));

        $this->melden();

        MonitoringEreignis::where('created_at', '<', now()->subDays(MonitoringEreignis::AUFBEWAHRUNG_TAGE))->delete();
    }

    private function heartbeatZeitlimitPruefen(): void
    {
        $quelle = MonitoringDienst::where('schluessel', MonitoringDienst::QUELLE)->first();

        if ($quelle === null || ! $quelle->aktiv || $quelle->status === MonitoringDienst::STATUS_FEHLER) {
            return;
        }

        $letzter = $quelle->letzte_pruefung;

        if ($letzter === null || $letzter->lt(now()->subMinutes($this->heartbeatMinuten()))) {
            $text = $letzter
                ? 'Kein Heartbeat seit '.$letzter->copy()->timezone(config('app.timezone'))->format('d.m.Y H:i').' Uhr'
                : 'Noch kein Heartbeat empfangen';
            // letzte_pruefung bleibt der Zeitpunkt des letzten Heartbeats
            $this->statusSetzen($quelle, MonitoringDienst::STATUS_FEHLER, $text, null, false);
        }
    }

    public function webserverPruefen(MonitoringDienst $dienst): void
    {
        $start = microtime(true);
        $fehler = null;

        try {
            $antwort = Http::timeout(10)
                ->connectTimeout(5)
                ->withUserAgent('MitarbeiterBoard-Monitoring')
                ->get($dienst->url);

            $latenz = (int) round((microtime(true) - $start) * 1000);

            if ($antwort->status() >= 400) {
                $fehler = 'HTTP '.$antwort->status();
            }
        } catch (ConnectionException) {
            $latenz = null;
            $fehler = 'Keine Verbindung oder Zeitüberschreitung';
        } catch (Throwable $e) {
            $latenz = null;
            $fehler = Str::limit('Fehler: '.$e->getMessage(), 250);
        }

        if ($fehler === null) {
            $dienst->fehlversuche = 0;
            $this->statusSetzen($dienst, MonitoringDienst::STATUS_OK, null, $latenz);

            return;
        }

        $dienst->fehlversuche++;

        if ($dienst->fehlversuche >= self::HTTP_FEHLVERSUCHE) {
            $this->statusSetzen($dienst, MonitoringDienst::STATUS_FEHLER, $fehler, $latenz);
        } else {
            // Erster Fehlschlag: Status bleibt, nur vermerken
            $dienst->letzte_pruefung = now();
            $dienst->save();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Status & Meldungen
    |--------------------------------------------------------------------------
    */

    public function statusSetzen(MonitoringDienst $dienst, string $status, ?string $meldung, ?int $latenz, bool $pruefzeitSetzen = true): void
    {
        $alt = $dienst->status;

        if ($pruefzeitSetzen) {
            $dienst->letzte_pruefung = now();
        }
        $dienst->meldung = $meldung !== null ? Str::limit($meldung, 250) : null;
        $dienst->latenz_ms = $latenz;

        if ($alt !== $status) {
            $dienst->status = $status;
            $dienst->status_seit = now();

            MonitoringEreignis::create([
                'dienst_id' => $dienst->id,
                'status_alt' => $alt,
                'status_neu' => $status,
                'meldung' => $dienst->meldung,
            ]);
        }

        $dienst->save();
    }

    /**
     * Gleicht „gemeldet“ mit dem Status ab und benachrichtigt bei neuen Störungen bzw. Behebungen.
     */
    public function melden(): void
    {
        $dienste = MonitoringDienst::where('aktiv', true)->orderBy('sortierung')->orderBy('id')->get();
        $quelle = $dienste->firstWhere('schluessel', MonitoringDienst::QUELLE);
        $alle = MonitoringDienst::all()->keyBy('id');

        $stoerungen = [];
        $behoben = [];

        foreach ($dienste as $dienst) {
            if ($dienst->status === MonitoringDienst::STATUS_FEHLER && ! $dienst->gemeldet) {
                if ($this->ursacheAnderswo($dienst, $quelle, $alle)) {
                    continue;
                }
                $dienst->gemeldet = true;
                $dienst->save();
                $stoerungen[] = $dienst;
            } elseif ($dienst->status === MonitoringDienst::STATUS_OK && $dienst->gemeldet) {
                $dienst->gemeldet = false;
                $dienst->save();
                $behoben[] = $dienst;
            }
        }

        if ($stoerungen === [] && $behoben === []) {
            return;
        }

        $empfaenger = $this->empfaenger();

        if ($empfaenger->isNotEmpty()) {
            Notification::send($empfaenger, new SystemstatusGeaendert(
                collect($stoerungen)->map(fn ($d) => ['name' => $d->name, 'meldung' => $d->meldung])->all(),
                collect($behoben)->map(fn ($d) => ['name' => $d->name, 'meldung' => null])->all(),
            ));
        }
    }

    /**
     * Liegt die Ursache eines Fehlers bei einem anderen Dienst (Abhängigkeit oder ausgefallener Heartbeat)?
     */
    private function ursacheAnderswo(MonitoringDienst $dienst, ?MonitoringDienst $quelle, Collection $alle): bool
    {
        if ($dienst->typ === MonitoringDienst::TYP_HEARTBEAT && $quelle !== null && $quelle->status === MonitoringDienst::STATUS_FEHLER) {
            return true;
        }

        $eltern = $dienst->abhaengig_von_id ? $alle->get($dienst->abhaengig_von_id) : null;

        return $eltern !== null && $eltern->aktiv && $eltern->status === MonitoringDienst::STATUS_FEHLER;
    }

    /** @return Collection<int, User> */
    public function empfaenger(): Collection
    {
        $rollen = $this->rollenModelle($this->rollenBenachrichtigen());

        return $rollen->isEmpty() ? collect() : User::role($rollen)->get();
    }

    private function rollenModelle(array $ids): Collection
    {
        return $ids === [] ? collect() : Role::whereIn('id', $ids)->where('guard_name', 'web')->get();
    }

    /*
    |--------------------------------------------------------------------------
    | Anzeige (Statusleiste im Dashboard)
    |--------------------------------------------------------------------------
    */

    public function sichtbarFuer(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        $ids = $this->rollenSichtbar();

        return $ids !== [] && $user->roles->pluck('id')->intersect($ids)->isNotEmpty();
    }

    /**
     * Wirksamer Status aller aktiven Dienste für die Anzeige.
     * Die Quelle erscheint nur, wenn sie nicht in Ordnung ist.
     *
     * @return Collection<int, array{name: string, status: string, text: string, hinweis: ?string, seit: ?Carbon, pruefung: ?Carbon, latenz: ?int}>
     */
    public function anzeige(): Collection
    {
        $dienste = MonitoringDienst::where('aktiv', true)->orderBy('sortierung')->orderBy('id')->get();
        $alle = MonitoringDienst::all()->keyBy('id');
        $quelle = $dienste->firstWhere('schluessel', MonitoringDienst::QUELLE);

        return $dienste
            ->reject(fn ($d) => $d->istQuelle() && $d->status === MonitoringDienst::STATUS_OK)
            ->map(function (MonitoringDienst $dienst) use ($quelle, $alle) {
                $status = $dienst->status;
                $hinweis = $dienst->meldung;

                if ($dienst->typ === MonitoringDienst::TYP_HEARTBEAT && $quelle !== null && $quelle->status === MonitoringDienst::STATUS_FEHLER) {
                    $status = MonitoringDienst::STATUS_UNBEKANNT;
                    $hinweis = 'Keine aktuelle Meldung – die Überwachung ist ausgefallen';
                } elseif ($status === MonitoringDienst::STATUS_FEHLER && $dienst->abhaengig_von_id) {
                    $eltern = $alle->get($dienst->abhaengig_von_id);
                    if ($eltern && $eltern->aktiv && $eltern->status === MonitoringDienst::STATUS_FEHLER) {
                        $status = 'betroffen';
                        $hinweis = 'Nicht prüfbar, weil „'.$eltern->name.'“ gestört ist';
                    }
                }

                return [
                    'name' => $dienst->name,
                    'status' => $status,
                    'text' => match ($status) {
                        MonitoringDienst::STATUS_OK => 'in Betrieb',
                        MonitoringDienst::STATUS_FEHLER => $dienst->istQuelle() ? 'ausgefallen' : 'gestört',
                        'betroffen' => 'betroffen',
                        default => 'unbekannt',
                    },
                    'hinweis' => $hinweis,
                    'seit' => $dienst->status_seit,
                    'pruefung' => $dienst->letzte_pruefung,
                    'latenz' => $dienst->latenz_ms,
                ];
            })
            ->values();
    }
}
