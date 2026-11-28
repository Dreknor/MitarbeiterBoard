<?php

namespace App\Console\Commands\Arbeitsblaetter;

use App\Services\Arbeitsblaetter\AbSatzService;
use App\Services\Arbeitsblaetter\AbSchriftenService;
use Illuminate\Console\Command;

/**
 * Stellt die Microsoft-Schriften Arial und Verdana für die Angepassten Arbeitsblätter bereit
 * (die übrigen Schriften liegen im Repository).
 *
 *   php artisan arbeitsblaetter:schriften                       Stand anzeigen, Schriftordner des Servers übernehmen
 *   php artisan arbeitsblaetter:schriften --von=/pfad/fonts     lizenzierte Dateien aus einem Ordner übernehmen
 *   php artisan arbeitsblaetter:schriften --laden               Originalpakete von Microsoft laden (fragt nach der Lizenz)
 *   php artisan arbeitsblaetter:schriften --laden --lizenz-akzeptiert   ohne Rückfrage (z. B. Skripte)
 *   php artisan arbeitsblaetter:schriften --automatisch         für den Online-Updater: lädt nur, wenn in den
 *                                                               Einstellungen freigegeben; scheitert nie
 */
class Schriften extends Command
{
    protected $signature = 'arbeitsblaetter:schriften
                            {--von= : Ordner mit lizenzierten Schriftdateien (z. B. eine Kopie von C:\Windows\Fonts)}
                            {--laden : Arial und Verdana aus Microsofts „Core Fonts for the Web“ laden}
                            {--lizenz-akzeptiert : Die Lizenzbedingungen von Microsoft sind akzeptiert (keine Rückfrage)}
                            {--automatisch : Für den Online-Updater – lädt nur mit Freigabe in den Einstellungen, Exit immer 0}
                            {--force : Vorhandene Dateien überschreiben}';

    protected $description = 'Stellt Arial und Verdana für die Angepassten Arbeitsblätter bereit (Ordner, Server oder Download)';

    public function handle(AbSchriftenService $schriften): int
    {
        $this->line('Zielordner: '.$schriften->zielOrdner());

        try {
            if ($this->option('automatisch')) {
                return $this->automatisch($schriften);
            }

            if ($von = $this->option('von')) {
                $this->bericht('Aus '.$von.' übernommen', $schriften->ausOrdner($von, (bool) $this->option('force')));
            } else {
                $this->bericht('Aus Schriftordnern des Servers übernommen', $schriften->ausSystem());
            }

            if ($this->option('laden') && ($this->option('force') || ! $schriften->vollstaendig())) {
                if (! $this->lizenzBestaetigt()) {
                    $this->warn('Abgebrochen – ohne Zustimmung zu den Lizenzbedingungen wird nichts geladen.');

                    return self::FAILURE;
                }
                $this->line('Lade arial32.exe und verdan32.exe …');
                $this->bericht('Geladen und entpackt', $schriften->laden((bool) $this->option('force')));
            }
        } catch (\Throwable $e) {
            $this->error('✗ '.$e->getMessage());

            return self::FAILURE;
        }

        return $this->stand($schriften) ? self::SUCCESS : self::FAILURE;
    }

    private function automatisch(AbSchriftenService $schriften): int
    {
        try {
            $ergebnis = $schriften->automatisch();
            $this->bericht('Aus Schriftordnern des Servers übernommen', $ergebnis['kopiert']);
            $this->bericht('Geladen und entpackt', $ergebnis['geladen']);
            if ($ergebnis['hinweis'] && $ergebnis['fehlt']) {
                $this->line('• '.$ergebnis['hinweis']);
            }
        } catch (\Throwable $e) {
            $this->warn('! Schriften konnten nicht geladen werden: '.$e->getMessage());
        }
        $this->stand($schriften);

        return self::SUCCESS; // Ein Update darf an den Schriften nie scheitern
    }

    private function lizenzBestaetigt(): bool
    {
        if ($this->option('lizenz-akzeptiert') || AbSchriftenService::ladenErlaubt()) {
            return true;
        }
        $this->line('Arial und Verdana gehören Microsoft. Es gelten die Lizenzbedingungen der „Core Fonts for the Web“:');
        $this->line('  '.AbSchriftenService::LIZENZ_URL);

        return $this->input->isInteractive() && $this->confirm('Lizenzbedingungen akzeptieren und laden?', false);
    }

    private function bericht(string $titel, array $dateien): void
    {
        if ($dateien) {
            $this->info('✓ '.$titel.': '.implode(', ', $dateien));
        }
    }

    /** Liefert true, wenn beide Schriften im Original vorhanden sind. */
    private function stand(AbSchriftenService $schriften): bool
    {
        foreach (AbSatzService::eigeneSchriften() as $schrift) {
            AbSatzService::nutztErsatz($schrift)
                ? $this->line('• '.AbSatzService::schriftLabel($schrift).' – Original fehlt')
                : $this->info('✓ '.AbSatzService::schriftLabel($schrift).' im Original vorhanden');
        }

        return $schriften->vollstaendig();
    }
}
