<?php

namespace Tests\Feature\Personal;

use App\Models\personal\PersonalAccessLog;
use App\Models\User;
use App\Services\Personal\PersonalAuditService;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    /** @test */
    public function accessing_personal_route_creates_access_log(): void
    {
        $user   = $this->actingAsWithPermission('view personal_data:all', 'view contracts');
        $target = User::factory()->create();

        $this->get(route('personal.contracts.index', $target->id));

        $this->assertDatabaseHas('pers_access_logs', [
            'user_id' => $user->id,
            'action'  => 'view',
        ]);
    }

    /** @test */
    public function audit_log_not_created_for_unauthenticated_request(): void
    {
        $this->get('/mein-profil');

        $this->assertDatabaseCount('pers_access_logs', 0);
    }

    /** @test */
    public function cleanup_deletes_old_non_deletion_logs(): void
    {
        PersonalAccessLog::factory()->create(['created_at' => now()->subYears(3)]);
        PersonalAccessLog::factory()->create(['created_at' => now()->subMonths(6)]);

        $deleted = app(PersonalAuditService::class)->cleanupOldLogs();

        $this->assertEquals(1, $deleted);
        $this->assertDatabaseCount('pers_access_logs', 1);
    }

    /** @test */
    public function deletion_logs_are_not_cleaned_up(): void
    {
        PersonalAccessLog::factory()->create([
            'action'     => 'deletion',
            'created_at' => now()->subYears(3),
        ]);

        app(PersonalAuditService::class)->cleanupOldLogs();

        $this->assertDatabaseCount('pers_access_logs', 1);
    }
}

