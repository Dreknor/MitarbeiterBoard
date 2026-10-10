<?php

namespace Tests\Feature\Updater;

use App\Services\Updater\UpdaterService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Online-Updater gegen echte, temporäre Git-Repositories (bare "origin" + Checkout).
 * composer, npm, Wartungsmodus und Artisan-Nacharbeiten sind abgeschaltet.
 */
class UpdaterTest extends TestCase
{
    private string $tmp;
    private string $origin;
    private string $work;
    private string $seedRepo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmp = sys_get_temp_dir() . '/mb-updater-' . uniqid();
        $this->origin = $this->tmp . '/origin.git';
        $this->work = $this->tmp . '/work';
        $this->seedRepo = $this->tmp . '/seed';
        File::ensureDirectoryExists($this->tmp);

        $this->sh(['git', 'init', '--bare', '-b', 'main', $this->origin]);
        $this->sh(['git', 'clone', $this->origin, $this->seedRepo]);
        $this->commit($this->seedRepo, 'README.md', 'v1', 'Erster Stand');
        $this->sh(['git', 'push', 'origin', 'HEAD:main'], $this->seedRepo);
        $this->sh(['git', 'clone', '-b', 'main', $this->origin, $this->work]);

        config([
            'updater.path' => $this->work,
            'updater.storage_path' => $this->tmp . '/storage',
            'updater.remote' => 'origin',
            'updater.branch' => 'main',
            'updater.composer' => false,
            'updater.npm_build' => false,
            'updater.maintenance_mode' => false,
            'updater.artisan_commands' => [],
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmp);

        parent::tearDown();
    }

    private function sh(array $command, ?string $cwd = null): string
    {
        $result = Process::path($cwd ?? $this->tmp)
            ->env(['GIT_AUTHOR_NAME' => 'Test', 'GIT_AUTHOR_EMAIL' => 't@example.org',
                'GIT_COMMITTER_NAME' => 'Test', 'GIT_COMMITTER_EMAIL' => 't@example.org'])
            ->run($command);

        $this->assertTrue($result->successful(), implode(' ', $command) . ': ' . $result->errorOutput());

        return trim($result->output());
    }

    private function commit(string $repo, string $file, string $content, string $message): void
    {
        File::put($repo . '/' . $file, $content);
        $this->sh(['git', 'add', $file], $repo);
        $this->sh(['git', 'commit', '-m', $message], $repo);
    }

    private function publishNewCommit(string $message = 'Neue Funktion'): string
    {
        $this->commit($this->seedRepo, 'README.md', uniqid(), $message);
        $this->sh(['git', 'push', 'origin', 'HEAD:main'], $this->seedRepo);

        return $this->sh(['git', 'rev-parse', 'HEAD'], $this->seedRepo);
    }

    public function test_updater_requires_permission(): void
    {
        $this->actingAsWithPermission('view wiki');

        $this->get(route('updater.index'))->assertForbidden();
        $this->get(route('updater.status'))->assertForbidden();
        $this->post(route('updater.update'))->assertForbidden();
    }

    public function test_check_fetches_and_lists_pending_commits(): void
    {
        $this->actingAsWithPermission('make updates');
        $this->publishNewCommit('Neue Funktion für Tickets');

        $this->from(route('updater.index'))
            ->post(route('updater.check'))
            ->assertRedirect(route('updater.index'))
            ->assertSessionHas('Meldung', '1 neue Änderung(en) verfügbar.');

        $this->get(route('updater.index'))
            ->assertOk()
            ->assertSee('Neue Funktion für Tickets')
            ->assertSee('Update starten');
    }

    public function test_start_launches_background_process(): void
    {
        Process::fake(['nohup *' => Process::result()]);
        $user = $this->actingAsWithPermission('make updates');

        $this->post(route('updater.update'))
            ->assertRedirect(route('updater.index'))
            ->assertSessionHas('type', 'info');

        Process::assertRan(fn ($process) => str_contains($process->command, "app:update --user={$user->id}"));
        $this->assertSame(UpdaterService::STATE_QUEUED, app(UpdaterService::class)->status()['state']);

        // Zweiter Start wird abgelehnt, solange der erste läuft
        $this->post(route('updater.update'))->assertSessionHas('type', 'danger');
        Process::assertRanTimes(fn ($process) => str_starts_with($process->command, 'nohup'), 1);
    }

    public function test_start_is_refused_with_local_changes(): void
    {
        Process::fake(['nohup *' => Process::result()]);
        $this->actingAsWithPermission('make updates');
        File::put($this->work . '/README.md', 'lokal geändert');

        $this->post(route('updater.update'))->assertSessionHas('type', 'danger');

        Process::assertNotRan(fn ($process) => str_starts_with($process->command, 'nohup'));
    }

    public function test_status_endpoint_returns_state_and_log(): void
    {
        $this->actingAsWithPermission('make updates');
        $updater = app(UpdaterService::class);
        $updater->writeStatus(['state' => UpdaterService::STATE_SUCCESS, 'message' => 'Fertig'], true);
        $updater->log('Zeile 1');

        $this->getJson(route('updater.status'))
            ->assertOk()
            ->assertJsonPath('status.state', 'success')
            ->assertJsonPath('status.message', 'Fertig')
            ->assertJson(fn ($json) => $json->where('log', fn ($log) => str_contains($log, 'Zeile 1'))->etc());
    }

    public function test_command_fast_forwards_to_remote(): void
    {
        $target = $this->publishNewCommit();

        $this->artisan('app:update')->assertSuccessful();

        $this->assertSame($target, $this->sh(['git', 'rev-parse', 'HEAD'], $this->work));
        $status = app(UpdaterService::class)->status();
        $this->assertSame(UpdaterService::STATE_SUCCESS, $status['state']);
        $this->assertSame($target, $status['to']);
    }

    public function test_command_reports_up_to_date(): void
    {
        $this->artisan('app:update')->assertSuccessful();

        $this->assertSame('Die Anwendung ist bereits aktuell.', app(UpdaterService::class)->status()['message']);
    }

    public function test_command_rolls_back_when_post_step_fails(): void
    {
        $before = $this->sh(['git', 'rev-parse', 'HEAD'], $this->work);
        $this->publishNewCommit();
        config(['updater.composer' => true, 'updater.composer_binary' => '/nonexistent/composer']);

        // Vorabprüfung schlägt wegen fehlendem composer fehl → --force, um den Rollback zu prüfen
        $this->artisan('app:update', ['--force' => true])->assertFailed();

        $this->assertSame($before, $this->sh(['git', 'rev-parse', 'HEAD'], $this->work));
        $status = app(UpdaterService::class)->status();
        $this->assertSame(UpdaterService::STATE_FAILED, $status['state']);
        $this->assertStringContainsString('zurückgesetzt', $status['message']);
    }

    public function test_command_refuses_parallel_run(): void
    {
        $other = new UpdaterService();
        $this->assertTrue($other->acquireLock());

        $this->artisan('app:update')->assertFailed();

        $other->releaseLock();
    }
}
