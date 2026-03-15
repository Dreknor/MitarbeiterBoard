<?php

namespace Tests\Unit\Models;

use App\Models\OxCalendar;
use App\Models\OxSyncLog;
use App\Models\User;
use Tests\TestCase;

class OxSyncLogTest extends TestCase
{
    /** @test */
    public function ox_sync_log_kann_erstellt_werden(): void
    {
        $log = OxSyncLog::factory()->create();

        $this->assertInstanceOf(OxSyncLog::class, $log);
    }

    /** @test */
    public function ox_sync_log_hat_kalender_relation(): void
    {
        $log = OxSyncLog::factory()->create();

        $this->assertInstanceOf(OxCalendar::class, $log->kalender);
    }

    /** @test */
    public function ox_sync_log_hat_benutzer_relation(): void
    {
        $user = User::factory()->create();
        $log  = OxSyncLog::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $log->benutzer);
    }

    /** @test */
    public function ox_sync_log_castet_details_als_array(): void
    {
        $log = OxSyncLog::factory()->create([
            'details' => ['count' => 5, 'errors' => 0],
        ]);

        $this->assertIsArray($log->details);
        $this->assertSame(5, $log->details['count']);
    }

    /** @test */
    public function ox_sync_log_hat_kein_soft_deletes(): void
    {
        $log = OxSyncLog::factory()->create();
        $log->delete();

        $this->assertNull(OxSyncLog::find($log->id));
    }
}

