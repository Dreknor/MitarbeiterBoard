<?php

namespace App\Services\Arbeitsblaetter\Ki;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Erkennung über die Anthropic Messages API (HTTP-Client, strukturierte Ausgabe per JSON-Schema).
 *
 * Gesendet werden nur die Seitenbilder bzw. das PDF, ggf. die Textebene des PDFs und der Prompt.
 * Konfiguration: `config('arbeitsblaetter.ki')` (AB_KI_*). Kosten werden aus den gemeldeten
 * Token und den Preisen in der Config geschätzt (Cent).
 */
class AnthropicErkenner implements AbErkenner
{
    public const API_VERSION = '2026-10-21';

    public const FALLBACK_BETA = 'server-side-fallback-2026-07-01';

    public function konfiguriert(): bool
    {
        return (string) config('arbeitsblaetter.ki.schluessel') !== '' && (string) config('arbeitsblaetter.ki.modell') !== '';
    }

    public function anbieter(): string
    {
        return 'anthropic';
    }

    public function modell(): string
    {
        return (string) config('arbeitsblaetter.ki.modell');
    }

    public function erkennen(array $seiten, ?string $textHinweis = null): AbErkennung
    {
        if (! $this->konfiguriert()) {
            throw new AbErkennungFehler('Die KI-Erkennung ist nicht eingerichtet (AB_KI_SCHLUESSEL fehlt).');
        }

        $inhalt = [];
        foreach ($seiten as $i => $seite) {
            if ($seite['mime'] === 'application/pdf') {
                $inhalt[] = ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => base64_encode($seite['inhalt'])]];
            } else {
                $inhalt[] = ['type' => 'text', 'text' => 'Seite '.($i + 1).':'];
                $inhalt[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $seite['mime'], 'data' => base64_encode($seite['inhalt'])]];
            }
        }
        if ($textHinweis) {
            $inhalt[] = ['type' => 'text', 'text' => ErkennerVorgaben::TEXT_HINWEIS.mb_substr($textHinweis, 0, 20000)];
        }
        $inhalt[] = ['type' => 'text', 'text' => ErkennerVorgaben::AUFTRAG];

        $anfrage = [
            'model' => $this->modell(),
            'max_tokens' => 16000,
            'system' => ErkennerVorgaben::PROMPT,
            'messages' => [['role' => 'user', 'content' => $inhalt]],
            'output_config' => [
                'effort' => (string) config('arbeitsblaetter.ki.aufwand', 'medium'),
                'format' => ['type' => 'json_schema', 'schema' => ErkennerVorgaben::schema()],
            ],
            'fallbacks' => 'default',
        ];

        try {
            $antwort = Http::withHeaders([
                'x-api-key' => (string) config('arbeitsblaetter.ki.schluessel'),
                'anthropic-version' => self::API_VERSION,
                'anthropic-beta' => self::FALLBACK_BETA,
                'content-type' => 'application/json',
            ])->timeout((int) config('arbeitsblaetter.ki.timeout', 180))
                ->post((string) config('arbeitsblaetter.ki.url'), $anfrage);
        } catch (ConnectionException $e) {
            throw new AbErkennungFehler('Die KI ist nicht erreichbar: '.$e->getMessage(), true);
        }

        if ($antwort->status() === 429 || $antwort->status() >= 500) {
            throw new AbErkennungFehler('Die KI ist überlastet (HTTP '.$antwort->status().'). Es wird später erneut versucht.', true);
        }
        if (! $antwort->successful()) {
            $meldung = (string) data_get($antwort->json(), 'error.message', 'HTTP '.$antwort->status());
            throw new AbErkennungFehler('Die KI hat die Anfrage abgelehnt: '.mb_substr($meldung, 0, 300));
        }

        $daten = $antwort->json();
        $grund = (string) ($daten['stop_reason'] ?? '');
        if ($grund === 'refusal') {
            throw new AbErkennungFehler('Die KI hat die Erkennung abgelehnt. Bitte das Blatt manuell erfassen.');
        }

        $text = collect($daten['content'] ?? [])->where('type', 'text')->pluck('text')->implode('');
        $roh = ErkennerVorgaben::jsonLesen($text);
        if (! is_array($roh)) {
            throw new AbErkennungFehler($grund === 'max_tokens'
                ? 'Das Blatt ist zu umfangreich für eine Erkennung. Bitte in mehreren Teilen erfassen.'
                : 'Die Antwort der KI konnte nicht gelesen werden.');
        }

        $struktur = ErkennerVorgaben::struktur($roh);

        return new AbErkennung(
            $struktur,
            (int) ($struktur['seiten'] ?? count($seiten)),
            $this->kosten($daten['usage'] ?? []),
            $this->anbieter(),
            (string) ($daten['model'] ?? $this->modell()),
        );
    }

    /** Kosten in Cent aus den Token (Eingabe inkl. Cache, Ausgabe). */
    public function kosten(array $usage): int
    {
        $eingabe = (int) ($usage['input_tokens'] ?? 0) + (int) ($usage['cache_creation_input_tokens'] ?? 0) + (int) ($usage['cache_read_input_tokens'] ?? 0);
        $ausgabe = (int) ($usage['output_tokens'] ?? 0);
        return ErkennerVorgaben::kostenCent($eingabe, $ausgabe, (int) config('arbeitsblaetter.ki.preis_eingabe_cent', 400), (int) config('arbeitsblaetter.ki.preis_ausgabe_cent', 2000));
    }

    /** Verbindungsprüfung (kostenlos): Modell abrufen. */
    public function pruefen(): array
    {
        $antwort = Http::withHeaders([
            'x-api-key' => (string) config('arbeitsblaetter.ki.schluessel'),
            'anthropic-version' => self::API_VERSION,
        ])->timeout(15)->get('https://api.anthropic.com/v1/models/'.rawurlencode($this->modell()));

        return [$antwort->successful(), $antwort->successful() ? 'erreichbar' : 'Fehler (HTTP '.$antwort->status().')'];
    }
}
