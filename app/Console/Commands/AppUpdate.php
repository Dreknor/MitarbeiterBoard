<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Updater\UpdaterService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

/**
 * Aktualisiert die Anwendung aus dem Git-Repository (wie deploy.sh).
 *
 * Wird vom Online-Updater (/updater) als Hintergrundprozess gestartet, kann
 * aber auch direkt auf der Konsole laufen. Schlägt composer/npm fehl, wird der
 * Code auf den vorherigen Stand zurückgesetzt. Nach Beginn der Migrationen
 * gibt es kein automatisches Zurücksetzen mehr.
 */
class AppUpdate extends Command
{
    protected $signature = 'app:update
                            {--user= : ID des auslösenden Benutzers (Web-Updater)}
                            {--force : Auch ohne neue Commits bzw. trotz fehlgeschlagener Vorabprüfung ausführen}';

    protected $description = 'Aktualisiert die Anwendung aus dem Git-Repository (git pull, composer, build, migrate)';

    private UpdaterService $updater;

    public function handle(UpdaterService $updater): int
    {
        $this->updater = $updater;

        if (! $updater->acquireLock()) {
            $this->error('Es läuft bereits ein Update.');

            return self::FAILURE;
        }

        $fromWeb = $updater->status()['state'] === UpdaterService::STATE_QUEUED;
        if (! $fromWeb) {
            $updater->clearLog();
        }

        $user = $this->option('user') ? User::find($this->option('user')) : null;
        $updater->writeStatus([
            'state' => UpdaterService::STATE_RUNNING,
            'step' => 'Vorbereitung',
            'user' => $user?->name ?? ($fromWeb ? $updater->status()['user'] ?? null : 'Konsole'),
            'started_at' => $fromWeb ? ($updater->status()['started_at'] ?? now()->toIso8601String()) : now()->toIso8601String(),
            'finished_at' => null,
            'message' => null,
            'from' => null,
            'to' => null,
        ], ! $fromWeb);

        $from = null;
        $codeUpdated = false;
        $migrationsStarted = false;
        $wentDown = false;

        try {
            $this->step('Lade Änderungen vom Server');
            $updater->fetch();

            $from = $updater->currentCommit();
            $to = trim($updater->git(['rev-parse', $updater->remoteRef()])->output());
            $updater->writeStatus(['from' => $from, 'to' => $to]);
            $this->say("Installiert: {$from}");
            $this->say("Verfügbar:   {$to} ({$updater->remoteRef()})");

            $this->step('Vorabprüfung');
            $checks = $updater->preflight();
            foreach ($checks as $check) {
                $this->say(($check['ok'] ? '  ✔ ' : ($check['blocking'] ? '  ✘ ' : '  ! ')) . $check['label']
                    . ($check['hint'] ? ' – ' . $check['hint'] : ''));
            }
            if (! $updater->preflightPassed($checks) && ! $this->option('force')) {
                throw new RuntimeException('Vorabprüfung fehlgeschlagen.');
            }

            if ($from === $to && ! $this->option('force')) {
                $this->finish(UpdaterService::STATE_SUCCESS, 'Die Anwendung ist bereits aktuell.');

                return self::SUCCESS;
            }

            $changed = $from === $to ? [] : $updater->changedFiles($from, $to);

            if (config('updater.maintenance_mode') && ! app()->isDownForMaintenance()) {
                $this->step('Wartungsmodus aktivieren');
                $this->artisan(['down', '--retry=60']);
                $wentDown = true;
            }

            $this->step('Code aktualisieren');
            $updater->git(['merge', '--ff-only', $updater->remoteRef()]);
            $codeUpdated = $from !== $to;
            $this->say('Neuer Stand: ' . $updater->currentCommit());

            if (config('updater.composer')) {
                $this->step('PHP-Abhängigkeiten installieren (composer)');
                $this->composerInstall();
            }

            $this->buildFrontend($changed);

            $migrationsStarted = true;
            foreach (config('updater.artisan_commands', []) as $command) {
                $this->step('php artisan ' . implode(' ', (array) $command));
                $this->artisan((array) $command);
            }

            $this->finish(UpdaterService::STATE_SUCCESS, 'Update erfolgreich abgeschlossen.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->say('FEHLER: ' . $e->getMessage());
            $message = $e->getMessage();

            if ($codeUpdated && ! $migrationsStarted) {
                $message .= ' ' . $this->rollback($from);
            } elseif ($migrationsStarted) {
                $message .= ' Der neue Code ist installiert, die Nacharbeiten (Migration/Caches) sind unvollständig – bitte manuell prüfen.';
            }

            $this->finish(UpdaterService::STATE_FAILED, $message);

            return self::FAILURE;
        } finally {
            if ($wentDown) {
                try {
                    $this->artisan(['up']);
                    $this->say('Wartungsmodus beendet.');
                } catch (Throwable $e) {
                    $this->say('Wartungsmodus konnte nicht beendet werden: ' . $e->getMessage());
                }
            }

            $updater->releaseLock();
        }
    }

    private function composerInstall(): void
    {
        $composer = $this->updater->findExecutable(config('updater.composer_binary'));
        if (! $composer) {
            throw new RuntimeException('composer wurde nicht gefunden.');
        }

        $this->runProcess(array_merge([$composer], config('updater.composer_args', ['install', '--no-interaction'])));
    }

    /**
     * public/build ist nicht versioniert – neu bauen, wenn sich Frontend-Dateien
     * geändert haben oder noch kein Build existiert.
     */
    private function buildFrontend(array $changed): void
    {
        if (! config('updater.npm_build')) {
            return;
        }

        $path = $this->updater->path();
        $hasBuild = File::exists($path . '/public/build/manifest.json');
        $frontendChanged = collect($changed)->contains(fn (string $file) => preg_match(
            '#^(resources/(js|css|sass)/|package(-lock)?\.json$|vite\.config|tailwind\.config|postcss\.config)#', $file
        ));

        if ($hasBuild && ! $frontendChanged && ! $this->option('force')) {
            $this->say('Frontend unverändert – kein Build nötig.');

            return;
        }

        $npm = $this->updater->findExecutable(config('updater.npm_binary'));
        if (! $npm) {
            $this->say('WARNUNG: npm nicht gefunden – Frontend wurde nicht neu gebaut (npm run build manuell ausführen).');

            return;
        }

        if (! File::isDirectory($path . '/node_modules') || in_array('package-lock.json', $changed, true)) {
            $this->step('Frontend-Abhängigkeiten installieren (npm ci)');
            $this->runProcess([$npm, 'ci', '--no-audit', '--no-fund']);
        }

        $this->step('Frontend bauen (npm run build)');
        $this->runProcess([$npm, 'run', 'build']);
    }

    /**
     * Setzt den Code auf den Stand vor dem Update zurück.
     */
    private function rollback(?string $from): string
    {
        if (! $from) {
            return '';
        }

        $this->step('Zurücksetzen auf vorherigen Stand');

        try {
            $this->updater->git(['reset', '--hard', $from]);
        } catch (Throwable $e) {
            $this->say('Zurücksetzen fehlgeschlagen: ' . $e->getMessage());

            return "Zurücksetzen auf {$from} fehlgeschlagen – bitte manuell prüfen.";
        }

        $message = "Der Code wurde auf {$from} zurückgesetzt.";

        if (config('updater.composer')) {
            try {
                $this->composerInstall();
            } catch (Throwable $e) {
                $this->say('composer install nach dem Zurücksetzen fehlgeschlagen: ' . $e->getMessage());
                $message .= ' composer install muss manuell ausgeführt werden.';
            }
        }

        return $message;
    }

    /**
     * Artisan läuft als eigener Prozess, damit nach git pull der neue Code
     * (Migrationen, Service-Provider) geladen wird.
     */
    private function artisan(array $args): void
    {
        $php = $this->updater->phpBinary();
        if (! $php) {
            throw new RuntimeException('PHP-CLI wurde nicht gefunden.');
        }

        $this->runProcess(array_merge([$php, $this->updater->path() . '/artisan'], $args, ['--no-interaction']));
    }

    private function runProcess(array $command): void
    {
        $result = Process::path($this->updater->path())
            ->env($this->updater->processEnv())
            ->timeout((int) config('updater.timeout', 900))
            ->run($command, function (string $type, string $output) {
                $this->updater->logRaw($output);
                $this->output->write($output);
            });

        if ($result->failed()) {
            throw new RuntimeException(basename($command[0]) . ' ' . ($command[1] ?? '')
                . ' ist fehlgeschlagen (Exit-Code ' . $result->exitCode() . ').');
        }
    }

    private function step(string $label): void
    {
        $this->updater->writeStatus(['step' => $label]);
        $this->say('▶ ' . $label);
    }

    private function say(string $line): void
    {
        $this->updater->log($line);
        $this->line($line);
    }

    private function finish(string $state, string $message): void
    {
        $this->updater->writeStatus([
            'state' => $state,
            'step' => null,
            'message' => $message,
            'finished_at' => now()->toIso8601String(),
        ]);
        $this->say($message);
    }
}
