<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Diese Migration lädt die für den Wochenplan benötigten Schriftarten
 * automatisch herunter und legt sie im storage/fonts-Verzeichnis ab.
 *
 * Benötigte Fonts:
 *  - NotoSansSymbols2-Regular.ttf  → für Emoji-Symbole in DomPDF
 *  - OpenDyslexic-Regular.otf      → für Legasthenie-Formatvorlage
 */
return new class extends Migration
{
    /**
     * Schriftarten, die heruntergeladen werden sollen.
     * Format: 'Dateiname' => 'Download-URL'
     */
    private array $fonts = [
        // google/fonts repo (offizieller Google-Fonts-GitHub-Mirror)
        'NotoSansSymbols2-Regular.ttf' => 'https://github.com/google/fonts/raw/main/ofl/notosanssymbols2/NotoSansSymbols2-Regular.ttf',
        // OpenDyslexic (antijingoist/opendyslexic auf GitHub)
        'OpenDyslexic-Regular.otf'     => 'https://raw.githubusercontent.com/antijingoist/opendyslexic/main/compiled/OpenDyslexic-Regular.otf',
    ];

    public function up(): void
    {
        // In der Testumgebung keine externen Downloads durchführen
        if (app()->environment('testing')) {
            Log::info('[FontMigration] Test-Umgebung erkannt – Font-Download übersprungen.');
            return;
        }

        $fontDir = storage_path('fonts');

        // Verzeichnis anlegen, falls nicht vorhanden
        if (! is_dir($fontDir)) {
            mkdir($fontDir, 0755, true);
            Log::info('[FontMigration] Verzeichnis erstellt: ' . $fontDir);
        }

        foreach ($this->fonts as $filename => $url) {
            $destination = $fontDir . DIRECTORY_SEPARATOR . $filename;

            if (file_exists($destination)) {
                Log::info("[FontMigration] Schriftart bereits vorhanden, übersprungen: {$filename}");
                continue;
            }

            $this->downloadFont($url, $destination, $filename);
        }
    }

    public function down(): void
    {
        $fontDir = storage_path('fonts');

        foreach (array_keys($this->fonts) as $filename) {
            $path = $fontDir . DIRECTORY_SEPARATOR . $filename;

            if (file_exists($path)) {
                unlink($path);
                Log::info("[FontMigration] Schriftart entfernt: {$filename}");
            }
        }
    }

    /**
     * Lädt eine Schriftart-Datei von einer URL herunter.
     */
    private function downloadFont(string $url, string $destination, string $filename): void
    {
        Log::info("[FontMigration] Lade Schriftart herunter: {$filename} von {$url}");

        $context = stream_context_create([
            'http' => [
                'timeout'       => 30,
                'user_agent'    => 'Mozilla/5.0 (compatible; Laravel FontMigration)',
                'follow_location' => true,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $data = @file_get_contents($url, false, $context);

        if ($data === false || strlen($data) < 1024) {
            $error = error_get_last();
            $message = "[FontMigration] FEHLER beim Herunterladen von {$filename}: " . ($error['message'] ?? 'Unbekannter Fehler');
            Log::error($message);
            // Kein Exception-Throw – Migration soll nicht fehlschlagen wenn kein Internet vorhanden
            $this->warn("⚠  {$message}");
            return;
        }

        file_put_contents($destination, $data);
        Log::info("[FontMigration] Schriftart erfolgreich gespeichert: {$destination} (" . number_format(strlen($data) / 1024, 1) . " KB)");
    }

    /**
     * Gibt eine Warnung auf der Konsole aus (nur wenn im Artisan-Kontext).
     */
    private function warn(string $message): void
    {
        if (app()->runningInConsole()) {
            fwrite(STDERR, $message . PHP_EOL);
        }
    }
};

