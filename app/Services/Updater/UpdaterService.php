<?php

namespace App\Services\Updater;

use App\Models\User;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * Gemeinsame Logik für den Online-Updater (Web-Oberfläche und `app:update`).
 *
 * Status, Log und Lock liegen in config('updater.storage_path'), damit sie
 * cache:clear überleben und auch während des Wartungsmodus lesbar sind.
 */
class UpdaterService
{
    public const STATE_IDLE = 'idle';
    public const STATE_QUEUED = 'queued';
    public const STATE_RUNNING = 'running';
    public const STATE_SUCCESS = 'success';
    public const STATE_FAILED = 'failed';

    /** Zeit, die ein gestarteter Prozess hat, um den Lock zu übernehmen */
    private const START_GRACE_SECONDS = 60;

    /** @var resource|null */
    private $lockHandle = null;

    // ── Git ──────────────────────────────────────────────────────────────

    public function path(): string
    {
        return rtrim(config('updater.path', base_path()), '/');
    }

    /**
     * Führt git im Arbeitsverzeichnis aus. safe.directory wird pro Aufruf
     * gesetzt, weil der Webserver-User meist nicht Besitzer des Checkouts ist.
     */
    public function git(array $args, bool $throw = true): ProcessResult
    {
        $command = array_merge(
            [config('updater.git_binary', 'git'), '-c', 'safe.directory=' . $this->path()],
            $args
        );

        $result = Process::path($this->path())
            ->env($this->processEnv())
            ->timeout(120)
            ->run($command);

        if ($throw && $result->failed()) {
            throw new RuntimeException('git ' . implode(' ', $args) . ': ' . trim($result->errorOutput() ?: $result->output()));
        }

        return $result;
    }

    public function currentCommit(): ?string
    {
        $result = $this->git(['rev-parse', 'HEAD'], false);

        return $result->successful() ? trim($result->output()) : null;
    }

    public function currentBranch(): ?string
    {
        $result = $this->git(['rev-parse', '--abbrev-ref', 'HEAD'], false);
        $branch = trim($result->output());

        return $result->successful() && $branch !== 'HEAD' ? $branch : null;
    }

    public function branch(): ?string
    {
        return config('updater.branch') ?: $this->currentBranch();
    }

    public function remote(): string
    {
        return config('updater.remote', 'origin');
    }

    public function remoteRef(): string
    {
        return $this->remote() . '/' . $this->branch();
    }

    public function fetch(): void
    {
        $this->git(['fetch', '--prune', $this->remote(), $this->branch()]);
    }

    /**
     * Informationen zum installierten Stand.
     */
    public function currentVersion(): array
    {
        $result = $this->git(['log', '-1', '--format=%H%x1f%h%x1f%ad%x1f%s', '--date=iso-strict'], false);

        if ($result->failed()) {
            return [];
        }

        [$hash, $short, $date, $subject] = array_pad(explode("\x1f", trim($result->output()), 4), 4, null);

        return [
            'hash' => $hash,
            'short' => $short,
            'date' => $date ? Carbon::parse($date) : null,
            'subject' => $subject,
            'branch' => $this->currentBranch(),
        ];
    }

    /**
     * Commits, die auf dem Remote-Branch liegen, aber noch nicht installiert sind
     * (Stand des letzten fetch).
     */
    public function pendingCommits(int $limit = 100): array
    {
        $result = $this->git([
            'log', '--format=%H%x1f%h%x1f%an%x1f%ad%x1f%s', '--date=iso-strict',
            '-n', (string) $limit, 'HEAD..' . $this->remoteRef(),
        ], false);

        if ($result->failed()) {
            return [];
        }

        return collect(explode("\n", trim($result->output())))
            ->filter()
            ->map(function (string $line) {
                [$hash, $short, $author, $date, $subject] = array_pad(explode("\x1f", $line, 5), 5, null);

                return [
                    'hash' => $hash,
                    'short' => $short,
                    'author' => $author,
                    'date' => $date ? Carbon::parse($date) : null,
                    'subject' => $subject,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Anzahl lokaler Commits, die nicht auf dem Remote liegen. Dann ist kein
     * Fast-Forward möglich.
     */
    public function localOnlyCommitCount(): int
    {
        $result = $this->git(['rev-list', '--count', $this->remoteRef() . '..HEAD'], false);

        return $result->successful() ? (int) trim($result->output()) : 0;
    }

    /**
     * Geänderte, versionierte Dateien im Arbeitsverzeichnis.
     */
    public function localChanges(): array
    {
        $result = $this->git(['status', '--porcelain', '--untracked-files=no'], false);

        return array_values(array_filter(explode("\n", rtrim($result->output()))));
    }

    public function changedFiles(string $from, string $to): array
    {
        $result = $this->git(['diff', '--name-only', $from, $to], false);

        return array_values(array_filter(explode("\n", trim($result->output()))));
    }

    // ── Vorabprüfung ────────────────────────────────────────────────────

    /**
     * @return array<int, array{label: string, ok: bool, blocking: bool, hint: ?string}>
     */
    public function preflight(): array
    {
        $checks = [];
        $add = function (string $label, bool $ok, ?string $hint = null, bool $blocking = true) use (&$checks) {
            $checks[] = ['label' => $label, 'ok' => $ok, 'blocking' => $blocking, 'hint' => $ok ? null : $hint];
        };

        $add('Prozesse können gestartet werden (proc_open)', function_exists('proc_open'),
            'proc_open ist in der PHP-Konfiguration deaktiviert (disable_functions).');

        $isRepo = $this->git(['rev-parse', '--is-inside-work-tree'], false)->successful();
        $add('Git-Repository gefunden', $isRepo,
            'Das Verzeichnis ist kein Git-Checkout oder git ist nicht installiert.');

        if ($isRepo) {
            $branch = $this->branch();
            $add('Branch ermittelt', (bool) $branch,
                'Kein Branch ausgecheckt (detached HEAD). UPDATER_BRANCH setzen oder Branch auschecken.');

            if ($branch) {
                $remoteExists = $this->git(['rev-parse', '--verify', '--quiet', $this->remoteRef()], false)->successful();
                $add("Remote-Branch {$this->remoteRef()} vorhanden", $remoteExists,
                    'Bitte zuerst nach Updates suchen (git fetch).');

                if ($remoteExists) {
                    $ahead = $this->localOnlyCommitCount();
                    $add('Keine lokalen Commits', $ahead === 0,
                        "{$ahead} lokale(r) Commit(s) sind nicht auf dem Server – ein Fast-Forward-Update ist nicht möglich.");
                }
            }

            $changes = $this->localChanges();
            $add('Keine lokal geänderten Dateien', $changes === [],
                'Geändert: ' . implode(', ', array_slice(array_map(fn ($l) => trim(substr($l, 3)), $changes), 0, 8))
                . (count($changes) > 8 ? ' …' : ''));
        }

        foreach (['.git', 'vendor', 'public', 'storage', 'bootstrap/cache'] as $dir) {
            $full = $this->path() . '/' . $dir;
            $add("Schreibrechte: {$dir}", ! file_exists($full) || is_writable($full),
                "Der Webserver-Benutzer ({$this->processUser()}) darf {$dir} nicht schreiben.");
        }

        $add('PHP-CLI gefunden', (bool) $this->phpBinary(), 'UPDATER_PHP_BINARY setzen.');

        if (config('updater.composer')) {
            $add('Composer gefunden', (bool) $this->findExecutable(config('updater.composer_binary')),
                'UPDATER_COMPOSER_BINARY setzen oder UPDATER_COMPOSER=false.');
        }

        if (config('updater.npm_build')) {
            $add('npm gefunden', (bool) $this->findExecutable(config('updater.npm_binary')),
                'Ohne npm wird das Frontend nicht neu gebaut. UPDATER_NPM_BINARY setzen oder UPDATER_NPM_BUILD=false.',
                false);
        }

        return $checks;
    }

    public function preflightPassed(?array $checks = null): bool
    {
        return collect($checks ?? $this->preflight())
            ->every(fn (array $check) => $check['ok'] || ! $check['blocking']);
    }

    // ── Status & Log ────────────────────────────────────────────────────

    public function storagePath(string $file = ''): string
    {
        $dir = config('updater.storage_path', storage_path('app/updater'));
        File::ensureDirectoryExists($dir);

        return $file === '' ? $dir : $dir . '/' . $file;
    }

    public function status(): array
    {
        $file = $this->storagePath('status.json');
        $status = File::exists($file) ? (json_decode(File::get($file), true) ?: []) : [];
        $status += ['state' => self::STATE_IDLE];

        // Prozess ist ohne Abschluss verschwunden (Absturz, kill, Timeout)
        if (in_array($status['state'], [self::STATE_QUEUED, self::STATE_RUNNING], true) && ! $this->isRunning()) {
            $since = isset($status['updated_at']) ? Carbon::parse($status['updated_at']) : null;
            if (! $since || $since->diffInSeconds(now()) > self::START_GRACE_SECONDS) {
                $status['state'] = self::STATE_FAILED;
                $status['message'] = 'Der Update-Prozess wurde unerwartet beendet. Bitte das Log prüfen.';
            }
        }

        return $status;
    }

    public function writeStatus(array $data, bool $reset = false): array
    {
        $status = $reset ? [] : $this->status();
        $status = array_merge($status, $data, ['updated_at' => now()->toIso8601String()]);

        File::put($this->storagePath('status.json'), json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), true);

        return $status;
    }

    public function isActive(): bool
    {
        return in_array($this->status()['state'], [self::STATE_QUEUED, self::STATE_RUNNING], true);
    }

    public function log(string $line): void
    {
        File::append($this->storagePath('update.log'), '[' . now()->format('H:i:s') . '] ' . $line . PHP_EOL);
    }

    public function logRaw(string $output): void
    {
        File::append($this->storagePath('update.log'), $output);
    }

    public function clearLog(): void
    {
        File::put($this->storagePath('update.log'), '');
    }

    public function logTail(int $lines = 300): string
    {
        $file = $this->storagePath('update.log');
        if (! File::exists($file)) {
            return '';
        }

        return implode("\n", array_slice(explode("\n", rtrim(File::get($file))), -$lines));
    }

    // ── Lock ────────────────────────────────────────────────────────────

    /**
     * Sperrt für die Laufzeit des aktuellen Prozesses (flock, wird vom OS beim
     * Prozessende freigegeben).
     */
    public function acquireLock(): bool
    {
        $handle = fopen($this->storagePath('update.lock'), 'c');
        if (! $handle || ! flock($handle, LOCK_EX | LOCK_NB)) {
            return false;
        }

        $this->lockHandle = $handle;

        return true;
    }

    public function releaseLock(): void
    {
        if ($this->lockHandle) {
            flock($this->lockHandle, LOCK_UN);
            fclose($this->lockHandle);
            $this->lockHandle = null;
        }
    }

    public function isRunning(): bool
    {
        if ($this->lockHandle) {
            return true;
        }

        $handle = @fopen($this->storagePath('update.lock'), 'c');
        if (! $handle) {
            return false;
        }

        $free = flock($handle, LOCK_EX | LOCK_NB);
        if ($free) {
            flock($handle, LOCK_UN);
        }
        fclose($handle);

        return ! $free;
    }

    // ── Start ───────────────────────────────────────────────────────────

    /**
     * Startet `php artisan app:update` losgelöst vom Web-Request.
     */
    public function start(User $user): void
    {
        if ($this->isActive()) {
            throw new RuntimeException('Es läuft bereits ein Update.');
        }

        $php = $this->phpBinary();
        if (! $php) {
            throw new RuntimeException('PHP-CLI wurde nicht gefunden (UPDATER_PHP_BINARY setzen).');
        }

        $this->clearLog();
        $this->writeStatus([
            'state' => self::STATE_QUEUED,
            'step' => 'Wird gestartet …',
            'user' => $user->name,
            'started_at' => now()->toIso8601String(),
        ], true);

        $command = sprintf(
            'nohup %s %s app:update --user=%d --quiet >> %s 2>&1 &',
            escapeshellarg($php),
            escapeshellarg(base_path('artisan')),
            $user->id,
            escapeshellarg($this->storagePath('update.log'))
        );

        $result = Process::path(base_path())->env($this->processEnv())->run($command);

        if ($result->failed()) {
            $this->writeStatus(['state' => self::STATE_FAILED, 'message' => 'Start fehlgeschlagen: ' . $result->errorOutput()]);
            throw new RuntimeException('Der Update-Prozess konnte nicht gestartet werden.');
        }
    }

    // ── Umgebung ────────────────────────────────────────────────────────

    /**
     * php-fpm startet Kindprozesse oft ohne PATH/HOME – ohne diese finden
     * composer und npm weder sich selbst noch ihren Cache.
     */
    public function processEnv(): array
    {
        $home = getenv('HOME') ?: $this->storagePath('home');

        return [
            'PATH' => getenv('PATH') ?: '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            'HOME' => $home,
            'COMPOSER_HOME' => getenv('COMPOSER_HOME') ?: $this->storagePath('composer'),
            'npm_config_cache' => getenv('npm_config_cache') ?: $this->storagePath('npm-cache'),
        ];
    }

    public function phpBinary(): ?string
    {
        return config('updater.php_binary') ?: ((new PhpExecutableFinder())->find(false) ?: null);
    }

    public function findExecutable(?string $name): ?string
    {
        if (! $name) {
            return null;
        }

        if (str_contains($name, '/')) {
            return is_executable($name) ? $name : null;
        }

        $path = getenv('PATH') ?: '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin';

        return (new ExecutableFinder())->find($name, null, explode(PATH_SEPARATOR, $path));
    }

    private function processUser(): string
    {
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            return posix_getpwuid(posix_geteuid())['name'] ?? (string) posix_geteuid();
        }

        return get_current_user();
    }
}
