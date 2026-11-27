<?php

namespace App\Services\Arbeitsblaetter\Ki;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Erkennung über OpenRouter (Chat Completions, `response_format` JSON-Schema).
 *
 * Zwei Varianten (Treiber):
 * - `openrouter_auto`: Modell `openrouter/auto` – OpenRouter wählt ein passendes (kostenpflichtiges) Modell.
 * - `openrouter_free`: Modell `openrouter/free` – zufälliges kostenloses Modell; Kosten 0, dafür
 *   schwankende Qualität und niedrigere Limits. PDFs gehen dort standardmäßig über die kostenlose
 *   Text-Umwandlung (`cloudflare-ai`), Bilder direkt.
 *
 * Gesendet werden nur Seitenbilder/PDF, ggf. die Textebene und der Prompt. Mit
 * `datensammlung = deny` schließt OpenRouter Anbieter aus, die Eingaben speichern oder auswerten.
 * Kosten: `usage.cost` (USD ≈ EUR), sonst Schätzung aus Token (bei „free“ immer 0).
 */
class OpenRouterErkenner implements AbErkenner
{
    public function __construct(private string $variante = 'auto')
    {
    }

    public function istKostenlos(): bool
    {
        return $this->variante === 'free';
    }

    public function konfiguriert(): bool
    {
        return $this->schluessel() !== '' && $this->modell() !== '';
    }

    public function anbieter(): string
    {
        return 'openrouter_'.$this->variante;
    }

    public function modell(): string
    {
        return (string) config('arbeitsblaetter.ki.openrouter.modell_'.$this->variante);
    }

    private function schluessel(): string
    {
        return (string) config('arbeitsblaetter.ki.openrouter.schluessel');
    }

    private function basis(): string
    {
        return rtrim((string) config('arbeitsblaetter.ki.openrouter.url'), '/');
    }

    public function erkennen(array $seiten, ?string $textHinweis = null): AbErkennung
    {
        if (! $this->konfiguriert()) {
            throw new AbErkennungFehler('OpenRouter ist nicht eingerichtet (AB_OPENROUTER_SCHLUESSEL fehlt).');
        }

        $inhalt = [];
        $mitPdf = false;
        foreach ($seiten as $i => $seite) {
            $daten = base64_encode($seite['inhalt']);
            if ($seite['mime'] === 'application/pdf') {
                $mitPdf = true;
                $inhalt[] = ['type' => 'file', 'file' => ['filename' => 'arbeitsblatt.pdf', 'file_data' => 'data:application/pdf;base64,'.$daten]];
            } else {
                $inhalt[] = ['type' => 'text', 'text' => 'Seite '.($i + 1).':'];
                $inhalt[] = ['type' => 'image_url', 'image_url' => ['url' => 'data:'.$seite['mime'].';base64,'.$daten]];
            }
        }
        if ($textHinweis) {
            $inhalt[] = ['type' => 'text', 'text' => ErkennerVorgaben::TEXT_HINWEIS.mb_substr($textHinweis, 0, 20000)];
        }
        $inhalt[] = ['type' => 'text', 'text' => ErkennerVorgaben::AUFTRAG];

        $anfrage = [
            'model' => $this->modell(),
            'messages' => [
                ['role' => 'system', 'content' => ErkennerVorgaben::PROMPT."\n\nAntworte ausschließlich mit einem JSON-Objekt nach diesem Schema:\n".json_encode(ErkennerVorgaben::schema(), JSON_UNESCAPED_UNICODE)],
                ['role' => 'user', 'content' => $inhalt],
            ],
            'max_tokens' => 16000,
            // strict = false: das Schema hat optionale Felder; die Antwort geht ohnehin durch AbStruktur
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => ['name' => 'arbeitsblatt', 'strict' => false, 'schema' => ErkennerVorgaben::schema()],
            ],
            'provider' => array_filter([
                'require_parameters' => true,
                'data_collection' => config('arbeitsblaetter.ki.openrouter.datensammlung') === 'allow' ? null : 'deny',
            ], fn ($v) => $v !== null),
            'usage' => ['include' => true],
        ];

        $engine = (string) config('arbeitsblaetter.ki.openrouter.pdf_engine_'.$this->variante);
        if ($mitPdf && $engine !== '') {
            $anfrage['plugins'] = [['id' => 'file-parser', 'pdf' => ['engine' => $engine]]];
        }

        try {
            $antwort = Http::withToken($this->schluessel())
                ->withHeaders(['X-Title' => 'MitarbeiterBoard'])
                ->timeout((int) config('arbeitsblaetter.ki.timeout', 180))
                ->post($this->basis().'/chat/completions', $anfrage);
        } catch (ConnectionException $e) {
            throw new AbErkennungFehler('OpenRouter ist nicht erreichbar: '.$e->getMessage(), true);
        }

        if (in_array($antwort->status(), [408, 429], true) || $antwort->status() >= 500) {
            throw new AbErkennungFehler('OpenRouter ist ausgelastet (HTTP '.$antwort->status().'). Es wird später erneut versucht.', true);
        }
        if ($antwort->status() === 402) {
            throw new AbErkennungFehler('Das OpenRouter-Guthaben reicht nicht aus. Bitte Guthaben aufladen oder den kostenlosen Treiber wählen.');
        }
        if (! $antwort->successful()) {
            $meldung = (string) data_get($antwort->json(), 'error.message', 'HTTP '.$antwort->status());
            throw new AbErkennungFehler('OpenRouter hat die Anfrage abgelehnt: '.mb_substr($meldung, 0, 300));
        }

        $daten = $antwort->json();
        if ($fehler = data_get($daten, 'error.message')) {
            // Fehler des gewählten Anbieters kommen teils mit HTTP 200
            throw new AbErkennungFehler('OpenRouter: '.mb_substr((string) $fehler, 0, 300), true);
        }
        $wahl = $daten['choices'][0] ?? [];
        $grund = (string) ($wahl['finish_reason'] ?? '');
        if ($grund === 'content_filter') {
            throw new AbErkennungFehler('Das Modell hat die Erkennung abgelehnt. Bitte das Blatt manuell erfassen.');
        }

        $text = $wahl['message']['content'] ?? '';
        if (is_array($text)) {
            $text = collect($text)->pluck('text')->implode('');
        }
        $roh = ErkennerVorgaben::jsonLesen((string) $text);
        if (! is_array($roh)) {
            throw new AbErkennungFehler($grund === 'length'
                ? 'Das Blatt ist zu umfangreich für eine Erkennung. Bitte in mehreren Teilen erfassen.'
                : 'Die Antwort des Modells konnte nicht gelesen werden.', $this->istKostenlos());
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

    /** Kosten in Cent: gemeldete Kosten (USD), sonst Schätzung; „free“ immer 0. */
    public function kosten(array $usage): int
    {
        if ($this->istKostenlos()) {
            return 0;
        }
        if (isset($usage['cost']) && is_numeric($usage['cost'])) {
            return (int) ceil((float) $usage['cost'] * 100);
        }

        return ErkennerVorgaben::kostenCent(
            (int) ($usage['prompt_tokens'] ?? 0),
            (int) ($usage['completion_tokens'] ?? 0),
            (int) config('arbeitsblaetter.ki.openrouter.preis_eingabe_cent', 300),
            (int) config('arbeitsblaetter.ki.openrouter.preis_ausgabe_cent', 1500),
        );
    }

    /** Verbindungsprüfung (kostenlos): Schlüssel abfragen. */
    public function pruefen(): array
    {
        $antwort = Http::withToken($this->schluessel())->timeout(15)->get($this->basis().'/key');

        return [$antwort->successful(), $antwort->successful() ? 'erreichbar' : 'Fehler (HTTP '.$antwort->status().')'];
    }
}
