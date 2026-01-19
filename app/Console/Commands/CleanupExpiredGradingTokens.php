<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupExpiredGradingTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'grading:cleanup-tokens';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Löscht abgelaufene QR-Code Tokens für die Gruppendokumentation';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Bereinige abgelaufene Tokens...');

        $deleted = DB::table('grading_student_tokens')
            ->where('expires_at', '<', now())
            ->delete();

        $this->info("$deleted abgelaufene Token(s) gelöscht.");

        return Command::SUCCESS;
    }
}
