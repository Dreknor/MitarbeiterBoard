<?php

namespace Tests\Feature\Personal;

use App\Enums\ParticipantStatus;
use App\Enums\TrainingStatus;
use App\Models\personal\Employment;
use App\Models\personal\QualificationType;
use App\Models\personal\Training;
use App\Models\personal\TrainingParticipant;
use App\Models\User;
use Tests\TestCase;

class TrainingTest extends TestCase
{
    /** @test */
    public function training_index_requires_permission(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('personal.trainings.index'))->assertStatus(403);
    }

    /** @test */
    public function training_index_is_accessible_with_permission(): void
    {
        $this->actingAsWithPermission('view trainings');

        $this->get(route('personal.trainings.index'))->assertStatus(200);
    }

    /** @test */
    public function active_employee_can_register_for_training(): void
    {
        $user = $this->actingAsWithPermission('view trainings');
        Employment::factory()->create([
            'employe_id' => $user->id,
            'status'     => 'aktiv',
        ]);
        $training = Training::factory()->create([
            'status'          => TrainingStatus::Geplant,
            'max_participants' => 10,
            'end_date'        => now()->addMonth()->toDateString(),
        ]);

        $this->post(route('personal.trainings.register', $training->id));

        $this->assertDatabaseHas('pers_training_participants', [
            'training_id' => $training->id,
            'employe_id'  => $user->id,
            'status'      => 'angemeldet',
        ]);
    }

    /** @test */
    public function inactive_employee_cannot_register(): void
    {
        $user = $this->actingAsWithPermission('view trainings');
        Employment::factory()->create([
            'employe_id' => $user->id,
            'status'     => 'beendet',
        ]);
        $training = Training::factory()->create();

        $this->post(route('personal.trainings.register', $training->id))
            ->assertStatus(403);
    }

    /** @test */
    public function cannot_register_when_training_is_full(): void
    {
        $user = $this->actingAsWithPermission('view trainings');
        Employment::factory()->create([
            'employe_id' => $user->id,
            'status'     => 'aktiv',
        ]);
        $training = Training::factory()->create(['max_participants' => 1]);

        // Ersten Platz belegen
        TrainingParticipant::factory()->create([
            'training_id' => $training->id,
            'status'      => ParticipantStatus::Bestaetigt,
        ]);

        $response = $this->post(route('personal.trainings.register', $training->id));
        $response->assertSessionHas('type', 'danger');
    }

    /** @test */
    public function cannot_register_twice(): void
    {
        $user = $this->actingAsWithPermission('view trainings');
        Employment::factory()->create([
            'employe_id' => $user->id,
            'status'     => 'aktiv',
        ]);
        $training = Training::factory()->create(['max_participants' => 10]);

        // Erste Anmeldung
        $this->post(route('personal.trainings.register', $training->id));

        // Zweite Anmeldung – soll mit Warnung zurückgewiesen werden
        $response = $this->post(route('personal.trainings.register', $training->id));
        $response->assertSessionHas('type', 'warning');

        $this->assertDatabaseCount('pers_training_participants', 1);
    }

    /** @test */
    public function employee_can_cancel_registration(): void
    {
        $user = $this->actingAsWithPermission('view trainings');
        $training = Training::factory()->create();
        TrainingParticipant::factory()->create([
            'training_id' => $training->id,
            'employe_id'  => $user->id,
            'status'      => ParticipantStatus::Angemeldet,
        ]);

        $this->post(route('personal.trainings.cancel', $training->id));

        $this->assertDatabaseHas('pers_training_participants', [
            'training_id' => $training->id,
            'employe_id'  => $user->id,
            'status'      => 'abgesagt',
        ]);
    }

    /** @test */
    public function approve_requires_approve_trainings_permission(): void
    {
        $user     = $this->actingAsWithPermission('view trainings');
        $training = Training::factory()->create();
        $target   = User::factory()->create();
        TrainingParticipant::factory()->create([
            'training_id' => $training->id,
            'employe_id'  => $target->id,
        ]);

        $this->post(route('personal.trainings.approve', [$training->id, $target->id]))
            ->assertStatus(403);
    }

    /** @test */
    public function abteilungsleiter_can_approve_participant(): void
    {
        $this->actingAsWithPermission('view trainings', 'approve trainings');
        $training = Training::factory()->create();
        $target   = User::factory()->create();

        TrainingParticipant::factory()->create([
            'training_id' => $training->id,
            'employe_id'  => $target->id,
            'status'      => ParticipantStatus::Angemeldet,
        ]);

        $this->post(route('personal.trainings.approve', [$training->id, $target->id]));

        $this->assertDatabaseHas('pers_training_participants', [
            'training_id' => $training->id,
            'employe_id'  => $target->id,
            'status'      => 'bestaetigt',
        ]);
    }

    /** @test */
    public function completing_training_sets_participant_status_to_teilgenommen(): void
    {
        $this->actingAsWithPermission('view trainings', 'manage trainings');
        $training    = Training::factory()->create(['end_date' => now()->toDateString()]);
        $target      = User::factory()->create();
        $participant = TrainingParticipant::factory()->bestaetigt()->create([
            'training_id' => $training->id,
            'employe_id'  => $target->id,
        ]);

        $this->post(route('personal.trainings.complete', [$training->id, $target->id]));

        $this->assertDatabaseHas('pers_training_participants', [
            'id'     => $participant->id,
            'status' => 'teilgenommen',
        ]);
    }

    /** @test */
    public function completing_training_renews_qualification(): void
    {
        $qualType = QualificationType::factory()->create(['validity_months' => 24]);
        $training = Training::factory()->create([
            'qualification_type_id' => $qualType->id,
            'end_date'              => now()->toDateString(),
            'status'                => TrainingStatus::Bestaetigt,
        ]);
        $employe     = User::factory()->create();
        $participant = TrainingParticipant::factory()->bestaetigt()->create([
            'training_id' => $training->id,
            'employe_id'  => $employe->id,
        ]);

        $this->actingAsWithPermission('manage trainings');

        $this->post(route('personal.trainings.complete', [$training->id, $employe->id]));

        $this->assertDatabaseHas('pers_employee_qualifications', [
            'employe_id'            => $employe->id,
            'qualification_type_id' => $qualType->id,
            'status'                => 'gueltig',
        ]);
    }

    /** @test */
    public function creating_training_requires_manage_permission(): void
    {
        $user = $this->actingAsWithPermission('view trainings');

        $this->post(route('personal.trainings.store'), [
            'title'      => 'Test',
            'start_date' => now()->addWeek()->toDateString(),
            'end_date'   => now()->addWeek()->addDay()->toDateString(),
        ])->assertStatus(403);
    }

    /** @test */
    public function personalleitung_can_create_training(): void
    {
        $user = $this->actingAsWithPermission('view trainings', 'manage trainings');

        $this->post(route('personal.trainings.store'), [
            'title'      => 'Brandschutzunterweisung 2026',
            'start_date' => now()->addWeek()->toDateString(),
            'end_date'   => now()->addWeek()->addDay()->toDateString(),
            'location'   => 'Schulzentrum',
        ]);

        $this->assertDatabaseHas('pers_trainings', [
            'title'    => 'Brandschutzunterweisung 2026',
            'location' => 'Schulzentrum',
        ]);
    }

    /** @test */
    public function training_show_displays_participants(): void
    {
        $this->actingAsWithPermission('view trainings');
        $training    = Training::factory()->create();
        $participant = User::factory()->create();
        TrainingParticipant::factory()->create([
            'training_id' => $training->id,
            'employe_id'  => $participant->id,
        ]);

        $this->get(route('personal.trainings.show', $training->id))
            ->assertStatus(200);
    }

    /** @test */
    public function deleting_training_requires_manage_permission(): void
    {
        $user     = $this->actingAsWithPermission('view trainings');
        $training = Training::factory()->create(['created_by' => $user->id]);

        $this->delete(route('personal.trainings.destroy', $training->id))
            ->assertStatus(403);
    }

    /** @test */
    public function personalleitung_can_delete_training(): void
    {
        $this->actingAsWithPermission('view trainings', 'manage trainings');
        $training = Training::factory()->create();

        $this->delete(route('personal.trainings.destroy', $training->id))
            ->assertRedirect();

        $this->assertSoftDeleted('pers_trainings', ['id' => $training->id]);
    }

    /** @test */
    public function training_is_marked_as_full_when_max_reached(): void
    {
        $training = Training::factory()->create(['max_participants' => 2]);

        TrainingParticipant::factory()->count(2)->create([
            'training_id' => $training->id,
            'status'      => ParticipantStatus::Bestaetigt,
        ]);

        $this->assertTrue($training->fresh()->isFull());
        $this->assertEquals(0, $training->fresh()->freePlaces());
    }
}

