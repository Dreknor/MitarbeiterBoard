<?php

namespace App\Services\Arbeitsblaetter\Ki;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Erkennung über die Google Gemini API (`models/{modell}:generateContent`) mit strukturierter
 * Ausgabe (`responseMimeType` application/json + `responseJsonSchema`).
 *
 * Bilder und PDFs gehen als `inlineData` (Base64) mit, dazu Prompt und ggf. Textebene – nie
 * Namen, Klassen oder Sehprofile. Konfiguration: `config('arbeitsblaetter.ki.gemini')` (AB_GEMINI_*).
 * Kosten werden aus `usageMetadata` (Prompt-, Antwort- und Denk-Token) geschätzt.
 */
class GeminiErkenner implements AbErkenner
{
    public function konfiguriert(): bool
    {
        return $this->schluessel() !== '' && $this->modell() !== '';
    }

    public function anbieter(): string
    {
        return 'gemini';
    }

    public function modell(): string
    {
        return (string) config('arbeitsblaetter.ki.gemini.modell');
    }

    private function schluessel(): string
    {
        return (string) config('arbeitsblaetter.ki.gemini.schluessel');
    }

    public function erkennen(array $seiten, ?string $textHinweis = null): AbErkennung
    {
        if (! $this->konfiguriert()) {
            throw new AbErkennungFehler('Gemini ist nicht eingerichtet (AB_GEMINI_SCHLUESSEL fehlt).');
        }

        $teile = [];
        foreach ($seiten as $i => $seite) {
            if ($seite['mime'] !== 'application/pdf') {
                $teile[] = ['text' => 'Seite '.($i + 1).':'];
            }
            $teile[] = ['inlineData' => ['mimeType' => $seite['mime'], 'data' => base64_encode($seite['inhalt'])]];
        }
        if ($textHinweis) {
            $teile[] = ['text' => ErkennerVorgaben::TEXT_HINWEIS.mb_substr($textHinweis, 0, 20000)];
        }
        $teile[] = ['text' => ErkennerVorgaben::AUFTRAG];

        $anfrage = [
            'systemInstruction' => ['parts' => [['text' => ErkennerVorgaben::PROMPT]]],
            'contents' => [['role' => 'user', 'parts' => $teile]],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseJsonSchema' => ErkennerVorgaben::schema(),
                'maxOutputTokens' => 16000,
            ],
        ];

        $ersatz = (string) config('arbeitsblaetter.ki.gemini.ersatzmodell');
        try {
            $antwort = $this->senden($this->modell(), $anfrage);
        } catch (AbErkennungFehler $e) {
            // Überlastet: einmal sofort mit dem Ersatzmodell versuchen
            if (! $e->wiederholbar || $ersatz === '' || $ersatz === $this->modell()) {
                throw $e;
            }
            $antwort = $this->senden($ersatz, $anfrage);
        }

        $daten = $antwort->json();
        if ($block = data_get($daten, 'promptFeedback.blockReason')) {
            throw new AbErkennungFehler('Gemini hat die Erkennung abgelehnt ('.$block.'). Bitte das Blatt manuell erfassen.');
        }
        $kandidat = $daten['candidates'][0] ?? [];
        $grund = (string) ($kandidat['finishReason'] ?? '');
        if (in_array($grund, ['SAFETY', 'PROHIBITED_CONTENT', 'BLOCKLIST', 'SPII', 'RECITATION'], true)) {
            throw new AbErkennungFehler('Gemini hat die Erkennung abgelehnt ('.$grund.'). Bitte das Blatt manuell erfassen.');
        }

        $text = collect($kandidat['content']['parts'] ?? [])->reject(fn ($t) => ! empty($t['thought']))->pluck('text')->implode('');
        $roh = ErkennerVorgaben::jsonLesen($text);
        if (! is_array($roh)) {
            throw new AbErkennungFehler($grund === 'MAX_TOKENS'
                ? 'Das Blatt ist zu umfangreich für eine Erkennung. Bitte in mehreren Teilen erfassen.'
                : 'Die Antwort von Gemini konnte nicht gelesen werden.');
        }

        $struktur = ErkennerVorgaben::struktur($roh);
        $nutzung = $daten['usageMetadata'] ?? [];

        return new AbErkennung(
            $struktur,
            (int) ($struktur['seiten'] ?? count($seiten)),
            ErkennerVorgaben::kostenCent(
                (int) ($nutzung['promptTokenCount'] ?? 0),
                (int) ($nutzung['candidatesTokenCount'] ?? 0) + (int) ($nutzung['thoughtsTokenCount'] ?? 0),
                (int) config('arbeitsblaetter.ki.gemini.preis_eingabe_cent', 50),
                (int) config('arbeitsblaetter.ki.gemini.preis_ausgabe_cent', 300),
            ),
            $this->anbieter(),
            (string) ($daten['modelVersion'] ?? $this->modell()),
        );
    }

    /** @throws AbErkennungFehler */
    private function senden(string $modell, array $anfrage): \Illuminate\Http\Client\Response
    {
        $url = rtrim((string) config('arbeitsblaetter.ki.gemini.url'), '/').'/models/'.rawurlencode($modell).':generateContent';

        try {
            $antwort = Http::withHeaders(['x-goog-api-key' => $this->schluessel()])
                ->timeout((int) config('arbeitsblaetter.ki.timeout', 180))
                ->post($url, $anfrage);
        } catch (ConnectionException $e) {
            throw new AbErkennungFehler('Gemini ist nicht erreichbar: '.$e->getMessage(), true);
        }

        if ($antwort->status() === 429 || $antwort->status() >= 500) {
            $meldung = (string) data_get($antwort->json(), 'error.message', '');
            throw new AbErkennungFehler('Gemini ist überlastet (HTTP '.$antwort->status().($meldung !== '' ? ': '.mb_substr($meldung, 0, 150) : '').').', true);
        }
        if (! $antwort->successful()) {
            $meldung = (string) data_get($antwort->json(), 'error.message', 'HTTP '.$antwort->status());
            throw new AbErkennungFehler('Gemini hat die Anfrage abgelehnt: '.mb_substr($meldung, 0, 300));
        }

        return $antwort;
    }

    /** Verbindungsprüfung (kostenlos): Modell abrufen. */
    public function pruefen(): array
    {
        $antwort = Http::withHeaders(['x-goog-api-key' => $this->schluessel()])->timeout(15)
            ->get(rtrim((string) config('arbeitsblaetter.ki.gemini.url'), '/').'/models/'.rawurlencode($this->modell()));

        return [$antwort->successful(), $antwort->successful() ? 'erreichbar' : 'Fehler (HTTP '.$antwort->status().')'];
    }
}
