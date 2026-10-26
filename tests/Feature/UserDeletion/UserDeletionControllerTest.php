<?php

namespace Tests\Feature\UserDeletion;

use App\Models\Ticket;
use App\Models\User;
use App\Notifications\UserDeletionDueNotification;
use App\Services\UserDeletion\UserDeletionService;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserDeletionControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_delete_button_redirects_to_handover_form(): void
    {
        $this->actingAsWithPermission('edit users');
        $user = User::factory()->create();

        $this->delete('users/'.$user->id)->assertRedirect(url('users/'.$user->id.'/deactivate'));
        $this->assertFalse($user->fresh()->trashed());
    }

    public function test_handover_form_shows_open_items_and_impact(): void
    {
        $this->actingAsWithPermission('edit users');
        $superior = User::factory()->create(['name' => 'Chefin Beispiel']);
        $user = User::factory()->create(['superior_id' => $superior->id]);
        Ticket::factory()->create(['assigned_to' => $user->id, 'status' => 'open']);

        $this->get('users/'.$user->id.'/deactivate')
            ->assertOk()
            ->assertSee('Offene Tickets (zugewiesen)')
            ->assertSee('Chefin Beispiel (Vorgesetzte/r)')
            ->assertSee('Auswirkungen');
    }

    public function test_deactivation_requires_confirmation_and_applies_handover(): void
    {
        $this->actingAsWithPermission('edit users');
        $superior = User::factory()->create();
        $user = User::factory()->create(['superior_id' => $superior->id]);
        $ticket = Ticket::factory()->create(['assigned_to' => $user->id, 'status' => 'open']);

        $this->post('users/'.$user->id.'/deactivate', ['left_at' => '2026-07-31', 'handover' => ['tickets' => $superior->id]])
            ->assertSessionHasErrors('confirm');
        $this->assertFalse($user->fresh()->trashed());

        $this->post('users/'.$user->id.'/deactivate', [
            'left_at' => '2026-07-31',
            'handover' => ['tickets' => $superior->id],
            'confirm' => '1',
        ])->assertRedirect(url('users'));

        $this->assertTrue(User::withTrashed()->find($user->id)->trashed());
        $this->assertSame($superior->id, $ticket->fresh()->assigned_to);
    }

    public function test_overview_and_stage_pages_require_permission(): void
    {
        $this->actingAsWithPermission('edit users');

        $this->get('user-deletion')->assertForbidden();
    }

    public function test_admin_confirms_anonymization_on_impact_page(): void
    {
        $admin = $this->actingAsWithPermission('manage user deletion');
        $user = User::factory()->create(['name' => 'Erika Muster']);
        app(UserDeletionService::class)->deactivate($user, [], $admin, now()->subMonths(13));

        $this->get('user-deletion')->assertOk()->assertSee('Erika Muster')->assertSee('Anonymisierung fällig');

        $this->get('user-deletion/'.$user->id.'/stage/2')
            ->assertOk()
            ->assertSee('Vollständige Übersicht der Auswirkungen');

        $this->post('user-deletion/'.$user->id.'/anonymize', [])->assertSessionHasErrors('confirm');

        $this->post('user-deletion/'.$user->id.'/anonymize', ['confirm' => '1'])->assertRedirect(url('user-deletion'));

        $this->assertSame(UserDeletionService::STAGE_ANONYMIZED, (int) User::withTrashed()->find($user->id)->deletion_stage);
        $this->get('user-deletion')->assertDontSee('Erika Muster');
    }

    public function test_admin_confirms_final_deletion(): void
    {
        $admin = $this->actingAsWithPermission('manage user deletion');
        $user = User::factory()->create();
        $service = app(UserDeletionService::class);
        $service->deactivate($user, [], $admin, now()->subMonths(13));
        $service->anonymize($user->fresh(), $admin);

        $this->get('user-deletion/'.$user->id.'/stage/3')->assertOk()->assertSee('Benutzerdatensatz');

        $this->post('user-deletion/'.$user->id.'/purge', ['confirm' => '1'])->assertRedirect(url('user-deletion'));

        $this->assertNull(User::withTrashed()->find($user->id));
    }

    public function test_anonymized_account_cannot_be_restored(): void
    {
        $admin = $this->actingAsWithPermission('edit users');
        $user = User::factory()->create();
        $service = app(UserDeletionService::class);
        $service->deactivate($user, [], $admin, now()->subMonths(13));
        $service->anonymize($user->fresh(), $admin);

        $this->get('users/restore/'.$user->id);

        $this->assertTrue(User::withTrashed()->find($user->id)->trashed());
    }

    public function test_scheduled_check_notifies_admins_once(): void
    {
        Notification::fake();

        $admin = $this->createUserWithPermission('manage user deletion');
        $user = User::factory()->create();
        app(UserDeletionService::class)->deactivate($user, [], $admin, now()->subMonths(13));

        app(UserDeletionService::class)->runScheduledChecks();
        Notification::assertSentTo($admin, UserDeletionDueNotification::class);

        Notification::fake();
        app(UserDeletionService::class)->runScheduledChecks();
        Notification::assertNothingSent();
    }
}
