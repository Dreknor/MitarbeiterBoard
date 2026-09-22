<?php

namespace App\Console\Commands;

use App\Services\NextcloudTalkService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestNextcloudConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nextcloud:test {--create-dir= : Test directory creation (e.g., "TestOrdner")}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Nextcloud Talk connection and WebDAV functionality';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Testing Nextcloud Connection...');
        $this->newLine();

        $service = new NextcloudTalkService();

        // Test 1: Check if enabled
        $this->info('Test 1: Configuration Check');
        if (!$service->isEnabled()) {
            $this->error('❌ Nextcloud Talk is not enabled or not properly configured');
            $this->warn('Please check your .env file:');
            $this->line('  NEXTCLOUD_ENABLED=true');
            $this->line('  NEXTCLOUD_URL=' . config('nextcloud.url'));
            $this->line('  NEXTCLOUD_USERNAME=' . config('nextcloud.username'));
            $this->line('  NEXTCLOUD_PASSWORD=' . (config('nextcloud.password') ? '[SET]' : '[NOT SET]'));
            return 1;
        }
        $this->info('✅ Nextcloud Talk is enabled and configured');
        $this->line('   URL: ' . config('nextcloud.url'));
        $this->line('   Username: ' . config('nextcloud.username'));
        $this->newLine();

        // Test 2: Test directory creation (if specified)
        if ($testDir = $this->option('create-dir')) {
            $this->info('Test 2: Directory Creation');
            $this->line("   Testing creation of directory: {$testDir}");

            // Use reflection to access protected method for testing
            $reflection = new \ReflectionClass($service);
            $method = $reflection->getMethod('ensureDirectoryExists');
            $method->setAccessible(true);

            try {
                $result = $method->invoke($service, $testDir);

                if ($result) {
                    $this->info("✅ Directory '{$testDir}' created or already exists");
                } else {
                    $this->error("❌ Failed to create directory '{$testDir}'");
                    $this->warn('Check the Laravel log for details: storage/logs/laravel.log');
                    return 1;
                }
            } catch (\Exception $e) {
                $this->error('❌ Exception: ' . $e->getMessage());
                return 1;
            }
            $this->newLine();
        }

        // Test 3: Check roster chat token
        $this->info('Test 3: Chat Token Configuration');
        $chatToken = config('nextcloud.roster_chat_token');
        if (empty($chatToken)) {
            $this->warn('⚠️  No roster chat token configured');
            $this->line('   Set NEXTCLOUD_ROSTER_CHAT_TOKEN in .env');
        } else {
            $this->info('✅ Roster chat token is configured');
            $this->line('   Token: ' . substr($chatToken, 0, 8) . '...');
        }
        $this->newLine();

        $this->info('All tests completed!');
        $this->newLine();

        // Show example usage
        $this->comment('To test creating the Dienstpläne directory:');
        $this->line('  php artisan nextcloud:test --create-dir="Dienstpläne"');
        $this->newLine();

        return 0;
    }
}
