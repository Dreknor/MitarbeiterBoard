<?php

namespace Tests\Feature\UserDeletion;

use App\Models\Group;
use App\Models\PaedDiaryEntry;
use App\Models\Post;
use App\Models\Ticket;
use App\Models\User;
use App\Models\personal\EmployeData;
use App\Models\personal\EmployeeQualification;
use App\Models\personal\QualificationType;
use App\Models\personal\Timesheet;
use App\Services\UserDeletion\UserDeletionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class UserDeletionServiceTest extends TestCase
{
    private UserDeletionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(UserDeletionService::class);
    }

    private function admin(): User
    {
        return User::factory()->create();
    }

    public function test_deactivate_hands_over_open_tickets_to_the_superior(): void
    {
        $superior = User::factory()->create();
        $user = User::factory()->create(['superior_id' => $superior->id]);
        $ticket = Ticket::factory()->create(['assigned_to' => $user->id, 'status' => 'open']);

        $this->assertSame($superior->id, $this->service->suggestedHandoverTarget($user)->id);

        $this->service->deactivate($user, ['tickets' => $superior->id], $this->admin(), Carbon::parse('2026-06-30'));

        $user->refresh();
        $this->assertTrue($user->trashed());
        $this->assertSame(UserDeletionService::STAGE_DEACTIVATED, (int) $user->deletion_stage);
        $this->assertSame('2026-06-30', $user->left_at->toDateString());
        $this->assertSame($superior->id, $ticket->fresh()->assigned_to);
    }

    public function test_handover_candidates_are_superior_and_group_members(): void
    {
        $superior = User::factory()->create();
        $colleague = User::factory()->create();
        $stranger = User::factory()->create();
        $user = User::factory()->create(['superior_id' => $superior->id]);

        $group = Group::factory()->create();
        $group->users()->attach([$user->id, $colleague->id]);

        $candidates = $this->service->handoverCandidates($user)->pluck('id');

        $this->assertSame($superior->id, $candidates->first());
        $this->assertTrue($candidates->contains($colleague->id));
        $this->assertFalse($candidates->contains($stranger->id));
    }

    public function test_deactivate_rejects_handover_to_someone_outside_the_candidates(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();
        Ticket::factory()->create(['assigned_to' => $user->id, 'status' => 'open']);

        $this->expectException(RuntimeException::class);
        $this->service->deactivate($user, ['tickets' => $stranger->id], $this->admin());
    }

    public function test_anonymize_requires_the_waiting_period(): void
    {
        $user = User::factory()->create();
        $this->service->deactivate($user, [], $this->admin(), now()->subMonths(6));

        $this->assertFalse($this->service->canAnonymize($user->fresh()));

        $this->expectException(RuntimeException::class);
        $this->service->anonymize($user->fresh(), $this->admin());
    }

    public function test_anonymize_keeps_shared_content_and_locks_personnel_file(): void
    {
        $user = User::factory()->create(['username' => 'max.mustermann', 'kuerzel' => 'MM']);
        $post = Post::factory()->create(['author_id' => $user->id, 'released' => true]);
        $entry = PaedDiaryEntry::factory()->create(['user_id' => $user->id]);
        $group = Group::factory()->create();
        $group->users()->attach($user->id);
        EmployeData::factory()->create(['user_id' => $user->id, 'familienname' => 'Mustermann']);

        $this->service->deactivate($user, [], $this->admin(), now()->subMonths(13));
        $this->service->anonymize($user->fresh(), $this->admin());

        $user = User::withTrashed()->find($user->id);
        $placeholder = $this->service->placeholder();

        $this->assertSame(UserDeletionService::STAGE_ANONYMIZED, (int) $user->deletion_stage);
        $this->assertStringNotContainsString('max', strtolower($user->name.$user->email));
        $this->assertNull($user->username);
        $this->assertNull($user->kuerzel);
        $this->assertNotNull($user->sso_uid_hash);

        $this->assertSame($placeholder->id, $post->fresh()->author_id);
        $this->assertSame($placeholder->id, (int) DB::table('paed_diary_entries')->where('id', $entry->id)->value('user_id'));
        $this->assertSame(0, DB::table('group_user')->where('user_id', $user->id)->count());

        // Personaldaten bleiben gesperrt erhalten
        $this->assertSame(1, DB::table('employes_data')->where('user_id', $user->id)->count());

        $this->assertTrue(UserDeletionService::isSsoBlocked('max.mustermann'));
    }

    public function test_restore_is_not_possible_after_anonymization(): void
    {
        $user = User::factory()->create();
        $this->service->deactivate($user, [], $this->admin(), now()->subMonths(13));
        $this->service->anonymize($user->fresh(), $this->admin());

        $this->expectException(RuntimeException::class);
        $this->service->restore(User::withTrashed()->find($user->id));
    }

    public function test_purge_deletes_categories_as_their_retention_expires(): void
    {
        $user = User::factory()->create();
        Timesheet::factory()->create(['employe_id' => $user->id]);
        $type = QualificationType::factory()->create(['name' => 'Erste Hilfe']);
        EmployeeQualification::factory()->create(['employe_id' => $user->id, 'qualification_type_id' => $type->id]);

        $this->service->deactivate($user, [], $this->admin(), Carbon::parse('2026-03-31'));
        Carbon::setTestNow('2027-04-01');
        $this->service->anonymize(User::withTrashed()->find($user->id), $this->admin());

        // Nach 3 Jahren (ab Ende 2026): Qualifikationen weg, Arbeitszeitnachweise bleiben (8 Jahre)
        $now = Carbon::parse('2030-01-01');
        $user = User::withTrashed()->find($user->id);
        $this->assertTrue($this->service->canPurge($user, $now));
        $result = $this->service->purge($user, $this->admin(), $now);

        $this->assertFalse($result['deleted']);
        $this->assertSame(0, DB::table('pers_employee_qualifications')->where('employe_id', $user->id)->count());
        $this->assertSame(1, DB::table('timesheets')->where('employe_id', $user->id)->count());

        // Vor Ablauf der 8 Jahre nichts weiter löschbar
        $this->assertFalse($this->service->canPurge($user->fresh(), Carbon::parse('2034-12-31')));

        // Ab 01.01.2035: alles weg, auch der User-Datensatz
        $result = $this->service->purge($user->fresh(), $this->admin(), Carbon::parse('2035-01-01'));

        $this->assertTrue($result['deleted']);
        $this->assertSame(0, DB::table('timesheets')->where('employe_id', $user->id)->count());
        $this->assertNull(User::withTrashed()->find($user->id));

        Carbon::setTestNow();
    }

    public function test_account_without_personnel_data_can_be_purged_right_after_anonymization(): void
    {
        $user = User::factory()->create();
        $this->service->deactivate($user, [], $this->admin(), now()->subMonths(13));
        $this->service->anonymize($user->fresh(), $this->admin());

        $user = User::withTrashed()->find($user->id);
        $this->assertTrue($this->service->canPurge($user));

        $this->assertTrue($this->service->purge($user, $this->admin())['deleted']);
    }

    public function test_legal_hold_blocks_purge(): void
    {
        $user = User::factory()->create();
        $this->service->deactivate($user, [], $this->admin(), now()->subMonths(13));
        $this->service->anonymize($user->fresh(), $this->admin());

        $user = User::withTrashed()->find($user->id);
        $this->service->setLegalHold($user, $this->admin(), true, 'Arbeitsgerichtsverfahren');

        $this->assertFalse($this->service->canPurge($user->fresh()));
        $this->expectException(RuntimeException::class);
        $this->service->purge($user->fresh(), $this->admin());
    }

    public function test_fuehrungszeugnis_is_deleted_three_months_after_leaving(): void
    {
        $user = User::factory()->create();
        $fz = QualificationType::factory()->create(['name' => 'Erweitertes Führungszeugnis']);
        $other = QualificationType::factory()->create(['name' => 'Erste Hilfe']);
        EmployeeQualification::factory()->create(['employe_id' => $user->id, 'qualification_type_id' => $fz->id]);
        EmployeeQualification::factory()->create(['employe_id' => $user->id, 'qualification_type_id' => $other->id]);

        $this->service->deactivate($user, [], $this->admin(), now()->subMonths(2));
        $this->assertSame(0, $this->service->purgeFuehrungszeugnisse());

        $this->assertSame(1, $this->service->purgeFuehrungszeugnisse(now()->addMonth()->addDay()));
        $this->assertSame(1, DB::table('pers_employee_qualifications')->where('employe_id', $user->id)->count());
    }

    public function test_pending_notifications_include_legacy_and_due_accounts_once(): void
    {
        // Altkonto: deaktiviert vor Einführung des Löschkonzepts (deletion_notified_stage = 0)
        $legacy = User::factory()->create();
        $legacy->delete();
        $legacy->forceFill(['deletion_stage' => 1, 'left_at' => now()->subMonths(2)])->save();

        $due = User::factory()->create();
        $this->service->deactivate($due, [], $this->admin(), now()->subMonths(13));

        $pending = $this->service->pendingNotifications();
        $this->assertTrue($pending['legacy']->contains('id', $legacy->id));
        $this->assertTrue($pending['anonymize']->contains('id', $due->id));

        $this->service->markNotified($pending);

        $pending = $this->service->pendingNotifications();
        $this->assertCount(0, $pending['legacy']);
        $this->assertCount(0, $pending['anonymize']);
    }

    public function test_placeholder_cannot_be_deactivated_or_restored(): void
    {
        $this->expectException(RuntimeException::class);
        $this->service->restore($this->service->placeholder());
    }
}
