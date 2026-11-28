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

    private ?string $modellWahl = null;

    public function modell(): string
    {
        return $this->modellWahl ?? (string) config('arbeitsblaetter.ki.modell');
    }

    /** Stärkeres Modell für die Nachfrage, wenn ein Ergebnis unvollständig wirkt (AB_KI_NACHFRAGE_MODELL). */
    public function nachfrageErkenner(): ?AbErkenner
    {
        $modell = (string) config('arbeitsblaetter.ki.nachfrage_modell');
        if ($modell === '' || $modell === $this->modell()) {
            return null;
        }
        $kopie = clone $this;
        $kopie->modellWahl = $modell;

        return $kopie;
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
                'format' => ['type' => 'json_schema', 'schema' => ErkennerVorgaben::schema(false)],
            ],
            'fallbacks' => 'default',
        ];

        $start = microtime(true);
        $url = (string) config('arbeitsblaetter.ki.url');
        try {
            $antwort = Http::withHeaders([
                'x-api-key' => (string) config('arbeitsblaetter.ki.schluessel'),
                'anthropic-version' => self::API_VERSION,
                'anthropic-beta' => self::FALLBACK_BETA,
                'content-type' => 'application/json',
            ])->timeout((int) config('arbeitsblaetter.ki.timeout', 180))
                ->post($url, $anfrage);
            KiProtokoll::aufzeichnen($this->anbieter(), $this->modell(), $url, $anfrage, $antwort, KiProtokoll::fehlerText($antwort), $start);
        } catch (ConnectionException $e) {
            KiProtokoll::aufzeichnen($this->anbieter(), $this->modell(), $url, $anfrage, null, $e, $start);
            throw new AbErkennungFehler('Die KI ist nicht erreichbar: '.$e->getMessage(), true);
        }

        if ($antwort->status() === 429 || $antwort->status() >= 500) {
            throw new AbErkennungFehler('Die KI ist überlastet (HTTP '.$antwort->status().'). Es wird später erneut versucht.', true, $antwort->status());
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
    public function kosten(array $usage): float
    {
        $eingabe = (int) ($usage['input_tokens'] ?? 0) + (int) ($usage['cache_creation_input_tokens'] ?? 0) + (int) ($usage['cache_read_input_tokens'] ?? 0);
        $ausgabe = (int) ($usage['output_tokens'] ?? 0);
        [$ein, $aus] = $this->preise();

        return ErkennerVorgaben::kostenCent($eingabe, $ausgabe, $ein, $aus);
    }

    /**
     * Listenpreise in Cent je 1 Mio. Token (Eingabe, Ausgabe), Stand 11/2026 (USD ≈ EUR).
     * AB_KI_PREIS_EINGABE_CENT / AB_KI_PREIS_AUSGABE_CENT überschreiben sie.
     */
    public const PREISE = [
        'claude-haiku-5-5' => [10, 50],
        'claude-sonnet-5-5' => [200, 1000],
        'claude-opus-5-5' => [400, 2000],
    ];

    /** @return array{0: float, 1: float} */
    public function preise(): array
    {
        $liste = self::PREISE[$this->modell()] ?? [400, 2000];
        $ein = config('arbeitsblaetter.ki.preis_eingabe_cent');
        $aus = config('arbeitsblaetter.ki.preis_ausgabe_cent');

        return [$ein !== null && $ein !== '' ? (float) $ein : (float) $liste[0], $aus !== null && $aus !== '' ? (float) $aus : (float) $liste[1]];
    }

    /** Verbindungsprüfung (kostenlos): Modell abrufen. */
    public function pruefen(): array
    {
        $antwort = Http::withHeaders([
            'x-api-key' => (string) config('arbeitsblaetter.ki.schluessel'),
            'anthropic-version' => self::API_VERSION,
        ])->timeout(15)->get('https://api.anthropic.com/v1/models/'.rawurlencode($this->modell()));
        if (! $antwort->successful()) {
            return [false, 'Fehler (HTTP '.$antwort->status().')'];
        }

        // Antwortschema mit einer Mini-Anfrage prüfen (Bruchteil eines Cents): die strukturierte Ausgabe hat
        // Grenzen (optionale Felder, Grammatikgröße), die erst die API meldet
        $probe = Http::withHeaders([
            'x-api-key' => (string) config('arbeitsblaetter.ki.schluessel'),
            'anthropic-version' => self::API_VERSION,
            'content-type' => 'application/json',
        ])->timeout(60)->post((string) config('arbeitsblaetter.ki.url'), [
            'model' => $this->modell(),
            'max_tokens' => 300,
            'messages' => [['role' => 'user', 'content' => 'Arbeitsblatt mit der Überschrift „Probe“. Antworte nach dem Schema.']],
            'output_config' => ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => ErkennerVorgaben::schema(false)]],
        ]);
        if (! $probe->successful()) {
            return [false, 'erreichbar, aber das Antwortschema wird abgelehnt: '.mb_substr((string) data_get($probe->json(), 'error.message', 'HTTP '.$probe->status()), 0, 200)];
        }

        return [true, 'erreichbar, Antwortschema angenommen'];
    }
}
