<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Lädt die für den Wochenplan benötigten Schriftarten herunter.
 *
 * Verwendung:
 *   php artisan wochenplan:fonts-download
 *   php artisan wochenplan:fonts-download --force   (überschreibt vorhandene Dateien)
 */
class DownloadWochenplanFonts extends Command
{
    protected $signature = 'wochenplan:fonts-download
                            {--force : Vorhandene Schriftarten überschreiben}';

    protected $description = 'Lädt die benötigten Schriftarten für den Wochenplan-PDF-Export herunter (NotoSansSymbols2, OpenDyslexic)';

    /**
     * Zu ladende Schriftarten.
     * Format: 'Dateiname' => 'Download-URL'
     */
    private array $fonts = [
        'NotoSansSymbols2-Regular.ttf' => 'https://github.com/google/fonts/raw/main/ofl/notosanssymbols2/NotoSansSymbols2-Regular.ttf',
        'OpenDyslexic-Regular.otf'     => 'https://raw.githubusercontent.com/antijingoist/opendyslexic/main/compiled/OpenDyslexic-Regular.otf',
    ];

    public function handle(): int
    {
        $fontDir = storage_path('fonts');
        $force   = $this->option('force');

        // Verzeichnis anlegen, falls nicht vorhanden
        if (! is_dir($fontDir)) {
            mkdir($fontDir, 0755, true);
            $this->info("📁 Verzeichnis erstellt: {$fontDir}");
        }

        $this->info('Lade Schriftarten für den Wochenplan herunter...');
        $this->newLine();

        $success = 0;
        $skipped = 0;
        $failed  = 0;

        foreach ($this->fonts as $filename => $url) {
            $destination = $fontDir . DIRECTORY_SEPARATOR . $filename;

            if (file_exists($destination) && ! $force) {
                $size = number_format(filesize($destination) / 1024, 1);
                $this->line("  <fg=yellow>⏭  Übersprungen:</> {$filename} (bereits vorhanden, {$size} KB)");
                $skipped++;
                continue;
            }

            $this->line("  <fg=cyan>⬇  Herunterladen:</> {$filename}");
            $this->line("     URL: {$url}");

            $result = $this->downloadFont($url, $destination);

            if ($result === false) {
                $this->line("  <fg=red>✗  Fehler:</> {$filename} konnte nicht heruntergeladen werden.");
                Log::error("[DownloadWochenplanFonts] Fehler beim Herunterladen: {$filename} von {$url}");
                $failed++;
            } else {
                $size = number_format($result / 1024, 1);
                $this->line("  <fg=green>✓  Gespeichert:</> {$filename} ({$size} KB)");
                Log::info("[DownloadWochenplanFonts] Schriftart gespeichert: {$destination} ({$size} KB)");
                $success++;
            }

            $this->newLine();
        }

        $this->newLine();
        $this->info("Abgeschlossen: {$success} heruntergeladen, {$skipped} übersprungen, {$failed} fehlgeschlagen.");

        if ($failed > 0) {
            $this->warn('⚠  Einige Schriftarten konnten nicht heruntergeladen werden.');
            $this->warn('   Bitte prüfen Sie die Internetverbindung des Servers und laden Sie die Dateien');
            $this->warn('   manuell in das Verzeichnis storage/fonts/ hoch.');
            $this->newLine();
            $this->table(
                ['Dateiname', 'Quelle'],
                collect($this->fonts)->map(fn($url, $file) => [$file, $url])->values()->toArray()
            );
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Lädt eine Datei von einer URL herunter.
     *
     * @return int|false  Dateigröße in Bytes bei Erfolg, false bei Fehler
     */
    private function downloadFont(string $url, string $destination): int|false
    {
        $context = stream_context_create([
            'http' => [
                'timeout'         => 30,
                'user_agent'      => 'Mozilla/5.0 (compatible; Laravel WochenplanFontDownloader)',
                'follow_location' => true,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $data = @file_get_contents($url, false, $context);

        if ($data === false || strlen($data) < 1024) {
            return false;
        }

        file_put_contents($destination, $data);

        return strlen($data);
    }
}

