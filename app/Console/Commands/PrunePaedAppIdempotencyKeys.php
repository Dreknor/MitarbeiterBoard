<?php

namespace App\Console\Commands;

use App\Models\ApiIdempotencyKey;
use App\Models\GradingJoinCode;
use App\Models\GradingStudentDevice;
use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Pädagogen-App: Idempotency-Keys nach 48 h löschen, abgelaufene Schüler-Beitrittscodes aufräumen.
 */
class PrunePaedAppIdempotencyKeys extends Command
{
    protected $signature = 'paed-app:prune-idempotency {--hours= : Aufbewahrungsdauer in Stunden (Standard: config paed_app.idempotency_hours)}';

    protected $description = 'Löscht alte Idempotency-Keys der Pädagogen-App sowie abgelaufene Schüler-Beitrittscodes';

    public function handle(): int
    {
        $hours = (int) ($this->option('hours') ?: config('paed_app.idempotency_hours', 48));

        $keys = ApiIdempotencyKey::where('created_at', '<', now()->subHours($hours))->delete();
        $this->info("{$keys} Idempotency-Key(s) gelöscht.");

        // Abgelaufene Codes inkl. Schüler-Geräte (die Tokens sind bereits abgelaufen)
        $expiredCodeIds = GradingJoinCode::where('expires_at', '<', now())->pluck('id');
        if ($expiredCodeIds->isNotEmpty()) {
            $deviceIds = GradingStudentDevice::whereIn('join_code_id', $expiredCodeIds)->pluck('id');
            PersonalAccessToken::where('tokenable_type', GradingStudentDevice::class)->whereIn('tokenable_id', $deviceIds)->delete();
            GradingStudentDevice::whereIn('id', $deviceIds)->delete();
            GradingJoinCode::whereIn('id', $expiredCodeIds)->delete();
        }
        $this->info($expiredCodeIds->count() . ' abgelaufene Beitrittscode(s) gelöscht.');

        return self::SUCCESS;
    }
}
