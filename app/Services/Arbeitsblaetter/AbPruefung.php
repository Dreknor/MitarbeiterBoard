<?php

namespace App\Services\Arbeitsblaetter;

use App\Models\Arbeitsblaetter\Sehprofil;
use App\Models\Schueler;
use App\Services\Arbeitsblaetter\Ki\AbErkenner;
use App\Services\Arbeitsblaetter\Ki\AbErkennerFabrik;
use Illuminate\Support\Facades\Http;

/**
 * Prüfungen für Einstellungsseite und `php artisan arbeitsblaetter:pruefen`:
 * KI, Nextcloud, Umwandlungsweg (Collabora/PHPWord/keiner), Schriften, GD, Queue, Profilkinder ohne Klasse.
 *
 * Jede Prüfung: ['titel', 'ok' (true|false|null = Hinweis), 'text', 'todo' (bool: etwas zu tun)].
 */
class AbPruefung
{
    /** @return array<string, array{titel: string, ok: ?bool, text: string, todo: bool}> */
    public function status(bool $verbindung = false): array
    {
        return [
            'ki' => $this->ki($verbindung),
            'nextcloud' => $this->nextcloud($verbindung),
            'konvertierung' => $this->konvertierung($verbindung),
            'schriften' => $this->schriften(),
            'gd' => $this->gd(),
            'queue' => $this->queue(),
            'profilkinder' => $this->profilkinder(),
        ];
    }

    private function ki(bool $verbindung): array
    {
        $treiber = (string) config('arbeitsblaetter.ki.treiber');
        $erkenner = app(AbErkenner::class);
        if (! $erkenner->konfiguriert()) {
            return ['titel' => 'KI-Erkennung', 'ok' => null, 'todo' => false,
                'text' => $treiber === 'keiner' || $treiber === ''
                    ? 'Keine KI eingerichtet (AB_KI_TREIBER). Blätter werden manuell erfasst.'
                    : 'Treiber „'.$treiber.'“ ohne Schlüssel (AB_KI_SCHLUESSEL) – nur manuelle Erfassung.'];
        }
        $text = (AbErkennerFabrik::TREIBER[$treiber] ?? $treiber).', Modell '.$erkenner->modell();
        if (! $verbindung || $treiber !== 'anthropic') {
            return ['titel' => 'KI-Erkennung', 'ok' => true, 'text' => $text, 'todo' => false];
        }
        try {
            $antwort = Http::withHeaders([
                'x-api-key' => (string) config('arbeitsblaetter.ki.schluessel'),
                'anthropic-version' => Ki\AnthropicErkenner::API_VERSION,
            ])->timeout(15)->get('https://api.anthropic.com/v1/models/'.rawurlencode($erkenner->modell()));
            $ok = $antwort->successful();

            return ['titel' => 'KI-Erkennung', 'ok' => $ok, 'todo' => ! $ok,
                'text' => $text.($ok ? ' – erreichbar.' : ' – Fehler (HTTP '.$antwort->status().').')];
        } catch (\Throwable $e) {
            return ['titel' => 'KI-Erkennung', 'ok' => false, 'todo' => true, 'text' => $text.' – nicht erreichbar: '.$e->getMessage()];
        }
    }

    private function nextcloud(bool $verbindung): array
    {
        $speicher = app(AbNextcloudSpeicher::class);
        if (! $speicher->aktiv()) {
            return ['titel' => 'Nextcloud-Ablage', 'ok' => null, 'todo' => false,
                'text' => 'Nicht eingerichtet (AB_NEXTCLOUD_* bzw. NEXTCLOUD_WOCHENPLAN_*). Fassungen nur zum Herunterladen.'];
        }
        if (! $verbindung) {
            return ['titel' => 'Nextcloud-Ablage', 'ok' => true, 'todo' => false, 'text' => 'Eingerichtet ('.config('arbeitsblaetter.nextcloud.url').').'];
        }
        $ok = $speicher->existiert('');

        return ['titel' => 'Nextcloud-Ablage', 'ok' => $ok, 'todo' => ! $ok, 'text' => $ok ? 'Verbindung in Ordnung.' : 'Keine Verbindung zum Dienstkonto.'];
    }

    private function konvertierung(bool $verbindung): array
    {
        $konvertierung = app(AbKonvertierung::class);
        if (! $konvertierung->verfuegbar()) {
            return ['titel' => 'Word-Umwandlung', 'ok' => null, 'todo' => false,
                'text' => 'Ersatzweg PHPWord: DOCX wird als Rohstruktur gelesen; DOC/ODT bitte als PDF speichern.'];
        }
        if (! $verbindung || ! $konvertierung instanceof NextcloudKonvertierung) {
            return ['titel' => 'Word-Umwandlung', 'ok' => true, 'todo' => false, 'text' => $konvertierung->bezeichnung().' (ungeprüft).'];
        }
        try {
            $antwort = Http::withBasicAuth((string) config('arbeitsblaetter.nextcloud.username'), (string) config('arbeitsblaetter.nextcloud.password'))
                ->withHeaders(['OCS-APIRequest' => 'true', 'Accept' => 'application/json'])->timeout(15)
                ->get(rtrim((string) config('arbeitsblaetter.nextcloud.url'), '/').'/ocs/v2.php/cloud/capabilities');
            $version = (string) data_get($antwort->json(), 'ocs.data.version.string', '?');
            $umwandlungen = data_get($antwort->json(), 'ocs.data.capabilities.files.file_conversions', []);
            $pdf = collect($umwandlungen)->contains(fn ($u) => ($u['to'] ?? '') === 'application/pdf');

            return ['titel' => 'Word-Umwandlung', 'ok' => $pdf, 'todo' => ! $pdf,
                'text' => 'Nextcloud '.$version.($pdf ? ' – Umwandlung in PDF verfügbar.' : ' – keine Umwandlung in PDF gemeldet (ab Nextcloud 31 mit Collabora). Ersatzweg PHPWord greift.')];
        } catch (\Throwable $e) {
            return ['titel' => 'Word-Umwandlung', 'ok' => false, 'todo' => true, 'text' => 'Nextcloud nicht erreichbar: '.$e->getMessage()];
        }
    }

    private function schriften(): array
    {
        $fehlt = AbSatzService::fehlendeSchriften();

        return ['titel' => 'Schriften', 'ok' => ! $fehlt, 'todo' => (bool) $fehlt,
            'text' => $fehlt ? 'Es fehlen: '.implode(', ', $fehlt) : 'Atkinson Hyperlegible, DejaVu Sans, Liberation Sans vorhanden.'];
    }

    private function gd(): array
    {
        $ok = function_exists('imagecreatefromstring');

        return ['titel' => 'Bildverarbeitung (PHP-GD)', 'ok' => $ok, 'todo' => ! $ok,
            'text' => $ok ? 'Vorhanden.' : 'PHP-GD fehlt – Fotos können nicht verarbeitet werden.'];
    }

    private function queue(): array
    {
        $sync = config('queue.default') === 'sync';

        return ['titel' => 'Hintergrundaufträge (Queue)', 'ok' => $sync ? null : true, 'todo' => false,
            'text' => $sync ? 'Queue läuft synchron – Erkennung und Ausgabe blockieren die Anfrage. Empfohlen: Worker (queue:work).' : 'Queue „'.config('queue.default').'“.'];
    }

    private function profilkinder(): array
    {
        $ohneKlasse = Schueler::whereIn('id', Sehprofil::where('aktiv', true)->select('schueler_id'))->whereDoesntHave('klasse')->get();

        return ['titel' => 'Kinder mit Sehprofil', 'ok' => $ohneKlasse->isEmpty(), 'todo' => $ohneKlasse->isNotEmpty(),
            'text' => $ohneKlasse->isEmpty()
                ? Sehprofil::where('aktiv', true)->count().' aktive Profile.'
                : 'Ohne Klasse (keine Wochenplan-Fassungen): '.$ohneKlasse->map(fn ($s) => $s->vorname.' '.$s->nachname)->implode(', ')];
    }
}
