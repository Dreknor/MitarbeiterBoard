<?php

namespace App\Services\Arbeitsblaetter\Ki;

use App\Models\Arbeitsblaetter\AbKiProtokoll;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

/**
 * Zeichnet KI-Aufrufe auf (Fehlersuche): Anfrage ohne Bild-/PDF-Daten (Platzhalter mit Größe),
 * rohe Antwort, HTTP-Status, Dauer. Schlüssel stehen nie in der Anfrage (nur in Headern) und
 * werden nicht gespeichert. Das Blatt setzt der Job über {@see fuer()}.
 */
class KiProtokoll
{
    public const AUFBEWAHRUNG_TAGE = 30;

    public const MAX_ANTWORT = 500_000;

    private static ?int $blattId = null;

    public static function fuer(?int $blattId): void
    {
        self::$blattId = $blattId;
    }

    public static function blattId(): ?int
    {
        return self::$blattId;
    }

    public static function aufzeichnen(string $anbieter, string $modell, string $url, array $anfrage, ?Response $antwort, ?\Throwable $fehler, float $start): void
    {
        try {
            AbKiProtokoll::create([
                'blatt_id' => self::$blattId,
                'anbieter' => $anbieter,
                'modell' => mb_substr($modell, 0, 100),
                'url' => mb_substr(preg_replace('/([?&]key=)[^&]+/', '$1***', $url) ?? $url, 0, 500),
                'http_status' => $antwort?->status(),
                'dauer_ms' => (int) round((microtime(true) - $start) * 1000),
                'anfrage' => json_encode(self::ohneDaten($anfrage), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
                'antwort' => $antwort ? mb_substr($antwort->body(), 0, self::MAX_ANTWORT) : null,
                'fehler' => $fehler ? mb_substr($fehler->getMessage(), 0, 2000) : null,
            ]);
        } catch (\Throwable $e) {
            Log::info('KI-Protokoll konnte nicht gespeichert werden', ['fehler' => $e->getMessage()]);
        }
    }

    /** Lange Base64-/Data-URI-Werte durch „[Base64, 123 KB]“ ersetzen. */
    public static function ohneDaten(mixed $wert): mixed
    {
        if (is_array($wert)) {
            return array_map([self::class, 'ohneDaten'], $wert);
        }
        if (is_string($wert) && strlen($wert) > 1000) {
            $roh = preg_replace('/^data:[^;]+;base64,/', '', $wert) ?? $wert;
            if (preg_match('/^[A-Za-z0-9+\/=\s]+$/', substr($roh, 0, 2000))) {
                return '[Base64, '.max(1, (int) round(strlen($roh) * 0.75 / 1024)).' KB]';
            }
        }

        return $wert;
    }

    public static function bereinigen(): int
    {
        return AbKiProtokoll::where('created_at', '<', now()->subDays(self::AUFBEWAHRUNG_TAGE))->delete();
    }
}
