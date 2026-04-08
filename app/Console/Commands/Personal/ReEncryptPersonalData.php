<?php

namespace App\Console\Commands\Personal;

use App\Services\Personal\PersonalAuditService;
use Illuminate\Console\Command;

class ReEncryptPersonalData extends Command
{
    protected $signature   = 'personal:re-encrypt';
    protected $description = 'Re-Encryptiert sensible Personalfelder nach APP_KEY-Rotation (nutzt OLD_APP_KEY)';

    public function handle(PersonalAuditService $service): int
    {
        if (!config('app.old_key')) {
            $this->error('❌ OLD_APP_KEY ist nicht in .env gesetzt.');
            $this->error('   Setze OLD_APP_KEY=<alter_key> in .env und starte dann erneut.');
            return self::FAILURE;
        }

        $this->warn('⚠️  Dieser Befehl entschlüsselt Felder mit OLD_APP_KEY und re-encryptiert mit dem aktuellen APP_KEY.');
        $this->warn('   Betrifft: EmployeeReview.notes, BemCase.meeting_notes, BemCase.measures');
        $this->newLine();

        if (!$this->confirm('Fortfahren?', false)) {
            $this->info('Abgebrochen.');
            return self::SUCCESS;
        }

        try {
            $this->info('Re-Encryption läuft...');
            $service->reEncryptSensitiveFields();
            $this->info('✅ Re-Encryption abgeschlossen.');
            $this->warn('   Hinweis: OLD_APP_KEY kann nun aus .env entfernt werden.');
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

