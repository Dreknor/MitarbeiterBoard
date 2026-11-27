<?php

namespace App\Services\Wochenplan\Nextcloud;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WebDAV-Umsetzung über das Dienst-Konto aus `config('nextcloud.wochenplan')`.
 * Unterklassen (z. B. {@see \App\Services\Arbeitsblaetter\AbNextcloudWebdav}) ändern
 * nur Zugangsdaten ({@see konfigSchluessel()}), Basisordner und An/Aus.
 */
class WpNextcloudWebdav implements WpNextcloudSpeicher
{
    public function aktiv(): bool
    {
        return (bool) settings('wochenplan_nextcloud_aktiv')
            && config('nextcloud.wochenplan.url')
            && config('nextcloud.wochenplan.username');
    }

    /** Config-Schlüssel mit url/username/password/timeout. */
    protected function konfigSchluessel(): string
    {
        return 'nextcloud.wochenplan';
    }

    protected function basisordner(): string
    {
        return (string) (settings('wochenplan_nextcloud_basisordner') ?: '/Wochenpläne');
    }

    protected function konfig(string $feld): mixed
    {
        return config($this->konfigSchluessel().'.'.$feld);
    }

    public function ordnerAnlegen(string $pfad): bool
    {
        $teile = array_values(array_filter(explode('/', $this->voll($pfad)), fn ($t) => $t !== ''));
        $aktuell = '';
        foreach ($teile as $teil) {
            $aktuell .= '/'.$teil;
            try {
                $antwort = $this->http()->send('MKCOL', $this->url($aktuell, false));
                if (! $antwort->successful() && ! in_array($antwort->status(), [405, 409], true)) {
                    // 405 = existiert bereits
                    $this->log('ordnerAnlegen', $aktuell, 'HTTP '.$antwort->status());

                    return false;
                }
            } catch (\Throwable $e) {
                $this->log('ordnerAnlegen', $aktuell, $e->getMessage());

                return false;
            }
        }

        return true;
    }

    public function hochladen(string $inhalt, string $pfad): bool
    {
        if (! $this->ordnerAnlegen($this->elternOrdner($pfad))) {
            return false;
        }

        try {
            $antwort = $this->http()->withBody($inhalt, 'application/octet-stream')->put($this->url($pfad));

            if (! $antwort->successful()) {
                $this->log('hochladen', $pfad, 'HTTP '.$antwort->status());
            }

            return $antwort->successful();
        } catch (\Throwable $e) {
            $this->log('hochladen', $pfad, $e->getMessage());

            return false;
        }
    }

    public function herunterladen(string $pfad): string|false
    {
        try {
            $antwort = $this->http()->get($this->url($pfad));

            return $antwort->successful() ? $antwort->body() : false;
        } catch (\Throwable $e) {
            $this->log('herunterladen', $pfad, $e->getMessage());

            return false;
        }
    }

    public function verschieben(string $von, string $nach): bool
    {
        if (! $this->ordnerAnlegen($this->elternOrdner($nach))) {
            return false;
        }

        try {
            $antwort = $this->http()
                ->withHeaders(['Destination' => $this->url($nach), 'Overwrite' => 'T'])
                ->send('MOVE', $this->url($von));

            if (! $antwort->successful()) {
                $this->log('verschieben', $von.' → '.$nach, 'HTTP '.$antwort->status());
            }

            return $antwort->successful();
        } catch (\Throwable $e) {
            $this->log('verschieben', $von.' → '.$nach, $e->getMessage());

            return false;
        }
    }

    public function existiert(string $pfad): bool
    {
        try {
            return $this->http()->withHeaders(['Depth' => '0'])->send('PROPFIND', $this->url($pfad))->successful();
        } catch (\Throwable $e) {
            $this->log('existiert', $pfad, $e->getMessage());

            return false;
        }
    }

    public function loeschen(string $pfad): bool
    {
        try {
            $antwort = $this->http()->delete($this->url($pfad));

            return $antwort->successful() || $antwort->status() === 404;
        } catch (\Throwable $e) {
            $this->log('loeschen', $pfad, $e->getMessage());

            return false;
        }
    }

    public function liste(string $pfad): array
    {
        try {
            $antwort = $this->http()->withHeaders(['Depth' => '1'])->send('PROPFIND', $this->url($pfad));
            if (! $antwort->successful()) {
                return [];
            }
        } catch (\Throwable $e) {
            $this->log('liste', $pfad, $e->getMessage());

            return [];
        }

        $xml = @simplexml_load_string($antwort->body());
        if ($xml === false) {
            return [];
        }
        $xml->registerXPathNamespace('d', 'DAV:');

        $basis = rtrim(parse_url($this->url($pfad), PHP_URL_PATH) ?? '', '/');
        $eintraege = [];
        foreach ($xml->xpath('//d:response') ?: [] as $response) {
            $response->registerXPathNamespace('d', 'DAV:');
            $href = rtrim(rawurldecode((string) ($response->xpath('d:href')[0] ?? '')), '/');
            if ($href === '' || $href === rawurldecode($basis)) {
                continue;
            }
            $name = basename($href);
            $ordner = count($response->xpath('.//d:resourcetype/d:collection')) > 0;
            $eintraege[] = [
                'name' => $name,
                'pfad' => trim(trim($pfad, '/').'/'.$name, '/'),
                'ordner' => $ordner,
                'groesse' => (int) ($response->xpath('.//d:getcontentlength')[0] ?? 0),
                'mime_type' => $ordner ? null : ((string) ($response->xpath('.//d:getcontenttype')[0] ?? '') ?: null),
            ];
        }

        usort($eintraege, fn ($a, $b) => [! $a['ordner'], mb_strtolower($a['name'])] <=> [! $b['ordner'], mb_strtolower($b['name'])]);

        return $eintraege;
    }

    public function webLink(string $pfad, bool $istOrdner = false): ?string
    {
        $basisUrl = rtrim((string) $this->konfig('url'), '/');
        if ($basisUrl === '') {
            return null;
        }
        $voll = $this->voll($pfad);
        $ordner = $istOrdner ? $voll : $this->elternOrdner($voll);
        $query = ['dir' => '/'.ltrim($ordner, '/')];
        if (! $istOrdner) {
            $query['scrollto'] = basename($voll);
        }

        return $basisUrl.'/index.php/apps/files/?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    protected function http(): PendingRequest
    {
        return Http::withBasicAuth(
            (string) $this->konfig('username'),
            (string) $this->konfig('password'),
        )->timeout((int) ($this->konfig('timeout') ?: 60));
    }

    /** Vollständiger Pfad inkl. Basisordner. */
    protected function voll(string $pfad): string
    {
        $basis = trim($this->basisordner(), '/');

        return trim($basis.'/'.trim($pfad, '/'), '/');
    }

    protected function url(string $pfad, bool $mitBasis = true): string
    {
        $voll = $mitBasis ? $this->voll($pfad) : trim($pfad, '/');
        $kodiert = implode('/', array_map('rawurlencode', array_filter(explode('/', $voll), fn ($t) => $t !== '')));

        return rtrim((string) $this->konfig('url'), '/')
            .'/remote.php/dav/files/'.rawurlencode((string) $this->konfig('username'))
            .'/'.$kodiert;
    }

    protected function elternOrdner(string $pfad): string
    {
        $pfad = trim($pfad, '/');
        $pos = strrpos($pfad, '/');

        return $pos === false ? '' : substr($pfad, 0, $pos);
    }

    protected function log(string $aktion, string $pfad, string $fehler): void
    {
        Log::warning('Nextcloud '.$this->konfigSchluessel().': '.$aktion.' fehlgeschlagen', ['pfad' => $pfad, 'fehler' => $fehler]);
    }
}
