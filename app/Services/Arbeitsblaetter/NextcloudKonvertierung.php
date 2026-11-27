<?php

namespace App\Services\Arbeitsblaetter;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Word/ODT → PDF über die Umwandlungs-API der Nextcloud (ab Version 31, mit Collabora/Office):
 *
 * 1. Datei per WebDAV nach `/_ab_tmp/{uuid}/` des Dienstkontos legen,
 * 2. `oc:fileid` per PROPFIND lesen,
 * 3. `POST /ocs/v2.php/apps/files/api/v1/convert` (fileId, targetMimeType = application/pdf),
 * 4. erzeugtes PDF laden, temporären Ordner löschen.
 */
class NextcloudKonvertierung implements AbKonvertierung
{
    public const TEMP_ORDNER = '_ab_tmp';

    public function verfuegbar(): bool
    {
        return (bool) config('arbeitsblaetter.nextcloud.konvertierung')
            && config('arbeitsblaetter.nextcloud.url')
            && config('arbeitsblaetter.nextcloud.username');
    }

    public function bezeichnung(): string
    {
        return 'Nextcloud (Collabora)';
    }

    public function zuPdf(string $inhalt, string $dateiname): ?string
    {
        if (! $this->verfuegbar()) {
            return null;
        }

        $ordner = self::TEMP_ORDNER.'/'.Str::uuid();
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', $dateiname) ?: 'datei.docx';
        $pfad = $ordner.'/'.$name;

        try {
            if (! $this->dav('MKCOL', self::TEMP_ORDNER)->successful() && ! $this->existiert(self::TEMP_ORDNER)) {
                throw new \RuntimeException('Temporärer Ordner konnte nicht angelegt werden.');
            }
            $this->dav('MKCOL', $ordner);
            $hochladen = $this->http()->withBody($inhalt, 'application/octet-stream')->put($this->davUrl($pfad));
            if (! $hochladen->successful()) {
                throw new \RuntimeException('Upload fehlgeschlagen (HTTP '.$hochladen->status().').');
            }

            $fileId = $this->fileId($pfad);
            if (! $fileId) {
                throw new \RuntimeException('Datei-ID nicht gefunden.');
            }

            $antwort = $this->http()
                ->withHeaders(['OCS-APIRequest' => 'true', 'Accept' => 'application/json'])
                ->asForm()
                ->post(rtrim((string) config('arbeitsblaetter.nextcloud.url'), '/').'/ocs/v2.php/apps/files/api/v1/convert', [
                    'fileId' => $fileId,
                    'targetMimeType' => 'application/pdf',
                ]);
            if (! $antwort->successful()) {
                throw new \RuntimeException('Umwandlung fehlgeschlagen (HTTP '.$antwort->status().').');
            }

            // Antwort: ocs.data.path (Pfad im Benutzerordner, z. B. „/_ab_tmp/…/datei.pdf“)
            $ziel = (string) data_get($antwort->json(), 'ocs.data.path', '');
            $ziel = $ziel !== '' ? ltrim($ziel, '/') : $ordner.'/'.pathinfo($name, PATHINFO_FILENAME).'.pdf';

            $pdf = $this->http()->get($this->davUrl($ziel));
            if (! $pdf->successful() || ! str_starts_with($pdf->body(), '%PDF')) {
                throw new \RuntimeException('PDF konnte nicht geladen werden.');
            }

            return $pdf->body();
        } catch (\Throwable $e) {
            Log::warning('Angepasste Arbeitsblätter: Umwandlung in PDF fehlgeschlagen', ['datei' => $dateiname, 'fehler' => $e->getMessage()]);

            return null;
        } finally {
            try {
                $this->http()->delete($this->davUrl($ordner));
            } catch (\Throwable) {
                // Aufräumen ist nicht kritisch
            }
        }
    }

    private function fileId(string $pfad): ?string
    {
        $body = '<?xml version="1.0"?><d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns"><d:prop><oc:fileid/></d:prop></d:propfind>';
        $antwort = $this->http()->withHeaders(['Depth' => '0'])->withBody($body, 'application/xml')->send('PROPFIND', $this->davUrl($pfad));
        if (! $antwort->successful()) {
            return null;
        }
        if (preg_match('/<oc:fileid>(\d+)<\/oc:fileid>/', $antwort->body(), $m)) {
            return $m[1];
        }

        return null;
    }

    private function existiert(string $pfad): bool
    {
        return $this->http()->withHeaders(['Depth' => '0'])->send('PROPFIND', $this->davUrl($pfad))->successful();
    }

    private function dav(string $methode, string $pfad)
    {
        return $this->http()->send($methode, $this->davUrl($pfad));
    }

    private function http(): PendingRequest
    {
        return Http::withBasicAuth(
            (string) config('arbeitsblaetter.nextcloud.username'),
            (string) config('arbeitsblaetter.nextcloud.password'),
        )->timeout((int) config('arbeitsblaetter.nextcloud.timeout', 60));
    }

    private function davUrl(string $pfad): string
    {
        $kodiert = implode('/', array_map('rawurlencode', array_filter(explode('/', trim($pfad, '/')), fn ($t) => $t !== '')));

        return rtrim((string) config('arbeitsblaetter.nextcloud.url'), '/')
            .'/remote.php/dav/files/'.rawurlencode((string) config('arbeitsblaetter.nextcloud.username')).'/'.$kodiert;
    }
}
