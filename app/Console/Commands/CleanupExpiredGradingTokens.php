<?php

namespace App\Console\Commands;

use App\Services\GradingJoinService;
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
    protected $description = 'Löscht abgelaufene QR-Code Tokens, Beitrittscodes und Schüler-Geräte (inkl. Sanctum-Tokens) der Graduierung';

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

        // Beitrittscodes und Schüler-Geräte (Schüler-iPads) inkl. Sanctum-Tokens
        $ergebnis = app(GradingJoinService::class)->aufraeumen();
        $this->info("{$ergebnis['codes']} Beitrittscode(s), {$ergebnis['devices']} Schüler-Gerät(e) und {$ergebnis['tokens']} Geräte-Token(s) gelöscht.");

        return Command::SUCCESS;
    }
}
