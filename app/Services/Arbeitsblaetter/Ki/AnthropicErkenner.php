<?php

namespace App\Services\Arbeitsblaetter\Ki;

use App\Support\AbStruktur;
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
            $inhalt[] = ['type' => 'text', 'text' => "Textebene des PDFs (kann Fehler und falsche Reihenfolge enthalten, nur als Hilfe):\n".mb_substr($textHinweis, 0, 20000)];
        }
        $inhalt[] = ['type' => 'text', 'text' => 'Erfasse dieses Arbeitsblatt vollständig als Bausteine nach den Regeln.'];

        $anfrage = [
            'model' => $this->modell(),
            'max_tokens' => 16000,
            'system' => self::PROMPT,
            'messages' => [['role' => 'user', 'content' => $inhalt]],
            'output_config' => [
                'effort' => (string) config('arbeitsblaetter.ki.aufwand', 'medium'),
                'format' => ['type' => 'json_schema', 'schema' => self::schema()],
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
        $roh = json_decode($text, true);
        if (! is_array($roh)) {
            throw new AbErkennungFehler($grund === 'max_tokens'
                ? 'Das Blatt ist zu umfangreich für eine Erkennung. Bitte in mehreren Teilen erfassen.'
                : 'Die Antwort der KI konnte nicht gelesen werden.');
        }

        $struktur = AbStruktur::normalisieren(self::umwandeln($roh));

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
        $cent = $eingabe / 1_000_000 * (int) config('arbeitsblaetter.ki.preis_eingabe_cent', 400)
            + $ausgabe / 1_000_000 * (int) config('arbeitsblaetter.ki.preis_ausgabe_cent', 2000);

        return (int) ceil($cent);
    }

    /** KI-Format (flach, `seite`/`box` am Bild) → Baustein-Format. */
    public static function umwandeln(array $roh): array
    {
        $baustein = function (array $b) use (&$baustein) {
            if (($b['typ'] ?? '') === 'bild' && isset($b['box'])) {
                $b['quelle'] = ['seite' => (int) ($b['seite'] ?? 1), 'box' => $b['box']];
            }
            unset($b['seite'], $b['box']);
            if (isset($b['kinder']) && is_array($b['kinder'])) {
                $b['kinder'] = array_map($baustein, array_filter($b['kinder'], 'is_array'));
            }

            return $b;
        };

        return [
            'seiten' => (int) ($roh['seiten'] ?? 1),
            'bausteine' => array_map($baustein, array_filter((array) ($roh['bausteine'] ?? []), 'is_array')),
        ];
    }

    public static function schema(): array
    {
        $segment = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['typ'],
            'properties' => [
                'typ' => ['type' => 'string', 'enum' => ['text', 'luecke']],
                'text' => ['type' => 'string'],
                'laenge' => ['type' => 'integer', 'description' => 'Ungefähre Anzahl Zeichen, die in die Lücke passen'],
            ],
        ];
        $felder = [
            'typ' => ['type' => 'string', 'enum' => array_keys(AbStruktur::TYPEN)],
            'text' => ['type' => 'string', 'description' => 'Text für ueberschrift und anweisung'],
            'nummer' => ['type' => 'string', 'description' => 'Aufgabennummer wie im Original, z. B. „1“ oder „b)“'],
            'inhalt' => ['type' => 'array', 'items' => $segment, 'description' => 'Für text und aufgabe: Text und Lücken in Lesereihenfolge'],
            'frage' => ['type' => 'string'],
            'optionen' => ['type' => 'array', 'items' => ['type' => 'string']],
            'mehrfach' => ['type' => 'boolean'],
            'zeilen' => ['type' => 'array', 'items' => ['type' => 'array', 'items' => ['type' => 'string']]],
            'kopfzeile' => ['type' => 'boolean'],
            'anzahl' => ['type' => 'integer', 'description' => 'Anzahl der Schreiblinien'],
            'beschreibung' => ['type' => 'string', 'description' => 'Kurze Beschreibung der Abbildung'],
            'seite' => ['type' => 'integer', 'description' => 'Seite der Abbildung (ab 1)'],
            'box' => ['type' => 'array', 'items' => ['type' => 'number'], 'description' => 'Relative Box [x1, y1, x2, y2] der Abbildung auf der Seite, Werte 0 bis 1'],
        ];
        $kind = ['type' => 'object', 'additionalProperties' => false, 'required' => ['typ'], 'properties' => $felder];
        $baustein = ['type' => 'object', 'additionalProperties' => false, 'required' => ['typ'], 'properties' => array_merge($felder, [
            'kinder' => ['type' => 'array', 'items' => $kind, 'description' => 'Teilaufgaben einer aufgabe (eine Ebene)'],
        ])];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['seiten', 'bausteine'],
            'properties' => [
                'seiten' => ['type' => 'integer'],
                'bausteine' => ['type' => 'array', 'items' => $baustein],
            ],
        ];
    }

    public const PROMPT = <<<'TXT'
Du erfasst Arbeitsblätter aus Schulen (Fotos oder PDF), damit sie für Kinder mit Sehbehinderung in großer Schrift neu gesetzt werden können. Eine Lehrkraft prüft dein Ergebnis anschließend.

Gib den Inhalt in Lesereihenfolge als Bausteine aus. Übernimm Texte wörtlich (Rechtschreibung wie im Original, nichts ergänzen, nichts lösen, keine Lösungen eintragen). Kein Layout: keine Schriftgrößen, Farben oder Positionen – außer der Box bei Abbildungen.

Bausteine:
- ueberschrift: Titel des Blatts oder eines Abschnitts (text).
- anweisung: Arbeitsanweisung an die Kinder, z. B. „Ergänze …“, „Kreuze an …“ (text).
- aufgabe: nummerierte Aufgabe (nummer wie im Original, inhalt = Text und Lücken). Teilaufgaben a), b) … als kinder der Aufgabe.
- text: sonstiger Fließtext (inhalt).
- ankreuzen: Auswahl mit Kästchen (frage, optionen; mehrfach = true, wenn mehrere angekreuzt werden dürfen).
- tabelle: Tabelle (zeilen = Zellen als Text, leere Zellen als ""; kopfzeile = true, wenn die erste Zeile Überschriften enthält).
- schreiblinien: freie Linien zum Schreiben (anzahl).
- bild: Abbildung, Grafik, Skizze oder Diagramm, das zum Lösen gebraucht wird (beschreibung kurz; seite; box = [x1, y1, x2, y2] relativ zur Seite, 0 = links/oben, 1 = rechts/unten, großzügig um die Abbildung). Reine Deko (Cliparts ohne Bedeutung, Rahmen, Logos) weglassen.

Lücken: Unterstriche, leere Kästchen oder freie Stellen zum Eintragen werden zu {"typ": "luecke", "laenge": n} mit der ungefähren Zahl der Zeichen, die hineinpassen. Mathematische Ausdrücke als lesbarer Text (z. B. „3/4“, „2 · 5 =“).

Persönliche Angaben (Namen von Kindern, Noten, handschriftliche Einträge) nicht übernehmen; ein leeres Namensfeld darf als text mit Lücke erfasst werden. seiten = Anzahl der Seiten des Originals.
TXT;
}
