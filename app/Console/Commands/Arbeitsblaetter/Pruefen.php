<?php

namespace App\Console\Commands\Arbeitsblaetter;

use App\Services\Arbeitsblaetter\AbPruefung;
use Illuminate\Console\Command;

/**
 * Prüft die Einrichtung der Angepassten Arbeitsblätter: KI-Konfiguration/Erreichbarkeit,
 * Nextcloud, Umwandlungsweg (Collabora/PHPWord/keiner), Schriften, PHP-GD, Queue und
 * Kinder mit Sehprofil ohne Klasse. Exit-Code 1, wenn etwas zu tun ist.
 */
class Pruefen extends Command
{
    protected $signature = 'arbeitsblaetter:pruefen {--verbindung : Verbindungen zu KI und Nextcloud aktiv testen}';

    protected $description = 'Prüft KI, Nextcloud, Word-Umwandlung und Schriften der Angepassten Arbeitsblätter';

    public function handle(AbPruefung $pruefung): int
    {
        $todo = false;
        foreach ($pruefung->status((bool) $this->option('verbindung')) as $eintrag) {
            $zeile = $eintrag['titel'].': '.$eintrag['text'];
            match ($eintrag['ok']) {
                true => $this->info('✓ '.$zeile),
                false => $this->warn('✗ '.$zeile),
                null => $this->line('• '.$zeile),
            };
            $todo = $todo || $eintrag['todo'];
        }

        if (! $this->option('verbindung')) {
            $this->line('');
            $this->line('Mit --verbindung werden KI und Nextcloud zusätzlich aktiv geprüft.');
        }

        return $todo ? self::FAILURE : self::SUCCESS;
    }
}
