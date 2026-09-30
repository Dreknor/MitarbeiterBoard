<?php

namespace Tests\Feature\Personal;

use App\Enums\ContractType;
use App\Enums\EmploymentStatus;
use App\Enums\TerminationReason;
use App\Models\Group;
use App\Models\personal\EmployeData;
use App\Models\personal\Employment;
use App\Models\personal\HourType;
use App\Models\personal\SchoolType;
use App\Models\personal\TeacherDetail;
use App\Models\User;
use App\Services\Personal\ContractService;
use Tests\TestCase;

/**
 * Regressionen aus der Zusammenführung von Personalakte, Stammdaten und Verträgen.
 */
class PersonalakteRegressionTest extends TestCase
{
    private function hr(string ...$extra): User
    {
        return $this->actingAsWithPermission(
            'view personal_data', 'view contracts', 'edit contracts', 'view personal_data:all', 'edit personal_data:all', ...$extra
        );
    }

    private function payload(Employment $employment, array $override = []): array
    {
        return array_merge([
            'employment_type' => $employment->employment_type?->value ?? 'regulaer',
            'contract_type'   => $employment->contract_type?->value ?? 'unbefristet',
            'department_id'   => $employment->department_id,
            'hour_type_id'    => $employment->hour_type_id,
            'start'           => $employment->start->toDateString(),
            'hours'           => $employment->hours,
        ], $override);
    }

    private function stammdaten(array $override = []): array
    {
        return array_merge([
            'familienname' => 'Muster', 'vorname' => 'Erika', 'geburtstag' => '1980-05-01',
            'geschlecht' => 'weiblich', 'schwerbehindert' => 0, 'staatsangehoerigkeit' => 'polnisch',
            'send_mail_if_absence' => 1, 'caldav_working_time' => 0, 'caldav_events' => 0,
        ], $override);
    }

    // ---------------------------------------------------------------- Verträge

    /** @test */
    public function editing_an_unbefristet_contract_keeps_the_scheduled_exit_date(): void
    {
        $this->hr();
        $employment = Employment::factory()->create([
            'contract_type' => ContractType::Unbefristet, 'start' => now()->subYear(), 'end' => null,
        ]);
        $employment->setBeendet(TerminationReason::KuendigungAN, now()->addMonths(2)->startOfDay());
        $exit = $employment->fresh()->end->toDateString();

        // Das Formular sendet bei unbefristeten Verträgen ohne sichtbares Enddatum kein "end"
        $this->put(route('personal.contracts.update', $employment->id), $this->payload($employment->fresh(), ['hours' => 25]))
            ->assertSessionHas('type', 'success');

        $employment->refresh();
        $this->assertSame($exit, $employment->end?->toDateString());
        $this->assertSame(TerminationReason::KuendigungAN, $employment->termination_reason);
        $this->assertEquals(25, (float) $employment->hours);
    }

    /** @test */
    public function clearing_the_exit_date_withdraws_a_scheduled_termination(): void
    {
        $this->hr();
        $employment = Employment::factory()->create(['contract_type' => ContractType::Unbefristet, 'start' => now()->subYear()]);
        $employment->setBeendet(TerminationReason::KuendigungAN, now()->addMonth()->startOfDay());

        $this->put(route('personal.contracts.update', $employment->id), $this->payload($employment->fresh(), ['end' => '']))
            ->assertSessionHas('type', 'success');

        $employment->refresh();
        $this->assertNull($employment->end);
        $this->assertNull($employment->termination_reason);
        $this->assertSame(EmploymentStatus::Aktiv, $employment->status);
    }

    /** @test */
    public function an_ended_contract_keeps_its_end_date_when_edited(): void
    {
        $this->hr();
        $employment = Employment::factory()->create(['contract_type' => ContractType::Unbefristet, 'start' => '2024-01-01']);
        $employment->setBeendet(TerminationReason::KuendigungAN, now()->subMonth()->startOfDay());
        $end = $employment->fresh()->end->toDateString();

        $this->put(route('personal.contracts.update', $employment->id), $this->payload($employment->fresh(), ['comment' => 'Korrektur']));

        $this->assertSame($end, $employment->fresh()->end?->toDateString());
    }

    /** @test */
    public function edit_form_keeps_manually_set_teacher_hours(): void
    {
        $this->hr();
        $school = SchoolType::factory()->create(['default_deputat' => 26]);
        $hourType = HourType::factory()->create(['fulltimehours' => 40]);
        $employment = Employment::factory()->create([
            'employment_type' => 'lehrer', 'hour_type_id' => $hourType->id, 'hours' => 33, 'start' => now()->subYear(),
        ]);
        // Deputat 26 → rechnerisch 40 Std.; gespeichert sind bewusst 33 Std.
        TeacherDetail::create([
            'employment_id' => $employment->id, 'school_type_id' => $school->id, 'deputat_hours' => 26,
            'reduction_hours' => 0, 'anrechnungsstunden' => 0, 'valid_from' => $employment->start,
        ]);

        $this->get(route('personal.contracts.edit', $employment->id))
            ->assertOk()
            ->assertViewHas('formConfig', fn ($cfg) => $cfg['manual'] === true);
    }

    /** @test */
    public function tab_parameter_is_not_reflected_into_javascript(): void
    {
        $this->hr();
        $employe = User::factory()->create();

        $this->get(route('personal.contracts.index', $employe->id) . "?tab=');alert(1);('")
            ->assertOk()
            ->assertDontSee('alert(1)', false);
    }

    /** @test */
    public function nightly_job_leaves_soft_deleted_contracts_untouched(): void
    {
        $deleted = Employment::factory()->create([
            'contract_type' => ContractType::Befristet, 'start' => '2025-01-01', 'end' => now()->subDay(),
        ]);
        $deleted->delete();

        $this->assertSame(0, app(ContractService::class)->endExpired());
        $this->assertSame(EmploymentStatus::Aktiv, Employment::withTrashed()->find($deleted->id)->status);
    }

    // ---------------------------------------------------------------- Stammdaten

    /** @test */
    public function viewing_the_stammdaten_page_does_not_create_a_record(): void
    {
        $this->hr('edit employe');
        $employe = User::factory()->create(['name' => 'Erika Muster']);

        $this->get(route('personal.personalakte.stammdaten', $employe->id))
            ->assertOk()
            ->assertSee('value="Muster"', false);

        $this->assertDatabaseMissing('employes_data', ['user_id' => $employe->id]);
    }

    /** @test */
    public function stammdaten_form_shows_the_stored_nationality(): void
    {
        $this->hr('edit employe');
        $employe = User::factory()->create();
        EmployeData::create(['user_id' => $employe->id, 'familienname' => 'Muster', 'vorname' => 'Erika', 'staatsangehoerigkeit' => 'polnisch']);

        $this->get(route('personal.personalakte.stammdaten', $employe->id))
            ->assertOk()
            ->assertSee('value="polnisch"', false)
            ->assertDontSee('value="deutsch"', false);
    }

    /** @test */
    public function saving_stammdaten_creates_and_updates_the_record(): void
    {
        $this->hr('edit employe');
        $employe = User::factory()->create();

        $this->put(route('employes.update', $employe->id), $this->stammdaten())->assertSessionHas('type', 'success');
        $this->assertDatabaseHas('employes_data', ['user_id' => $employe->id, 'staatsangehoerigkeit' => 'polnisch', 'familienname' => 'Muster']);
        $this->assertTrue((bool) $employe->fresh()->send_mails_if_absence);

        $this->put(route('employes.update', $employe->id), $this->stammdaten(['send_mail_if_absence' => 0]));
        $this->assertFalse((bool) $employe->fresh()->send_mails_if_absence);
    }

    /** @test */
    public function work_data_can_be_updated_with_edit_employe_permission_and_zero_is_saved(): void
    {
        $this->actingAsWithPermission('edit employe');
        $employe = User::factory()->create();
        EmployeData::create(['user_id' => $employe->id, 'mail_timesheet' => true]);

        $this->put(route('employes.data.update', $employe->id), [
            'holidayClaim' => 30, 'date_start' => now()->toDateString(), 'mail_timesheet' => 0,
        ])->assertSessionHas('type', 'success');

        $this->assertFalse((bool) $employe->fresh()->employe_data->mail_timesheet);
    }

    /** @test */
    public function time_recording_key_must_be_unique(): void
    {
        $this->actingAsWithPermission('edit employe');
        $other = User::factory()->create();
        EmployeData::create(['user_id' => $other->id, 'time_recording_key' => '12345678']);
        $employe = User::factory()->create();

        $this->put(route('employes.data.update', $employe->id), [
            'holidayClaim' => 30, 'date_start' => now()->toDateString(), 'time_recording_key' => '12345678',
        ])->assertSessionHasErrors('time_recording_key');
    }

    // ---------------------------------------------------------------- Übersicht & Akte

    /** @test */
    public function employee_list_shows_status_and_departments(): void
    {
        $this->hr('edit employe');
        $group = Group::factory()->asDepartment()->create(['name' => 'Hort Nord']);
        $employe = User::factory()->create();
        Employment::factory()->create(['employe_id' => $employe->id, 'department_id' => $group->id, 'start' => now()->subYear()]);

        $this->get(route('employes.index'))
            ->assertOk()
            ->assertSee('Hort Nord')
            ->assertSee('Aktiv');
    }

    /** @test */
    public function bulk_holiday_claim_page_renders_and_saves_only_changed_claims(): void
    {
        $this->actingAsWithPermission('edit employe');
        $group = Group::factory()->create(['name' => 'Team Hort']);
        $member = User::factory()->create();
        $group->users()->attach($member->id);

        $this->get(route('employes.bulk-holiday-claim'))->assertOk()->assertSee('Team Hort (1)');

        $this->post(route('employes.bulk-holiday-claim.update'), [
            'group_id' => $group->id, 'holiday_claim' => 31, 'date_start' => now()->toDateString(),
        ])->assertSessionHas('type', 'success');

        $this->assertDatabaseHas('employe_holiday_claims', ['employe_id' => $member->id, 'holiday_claim' => 31]);
    }

    /** @test */
    public function all_akte_pages_share_the_navigation(): void
    {
        $this->hr('edit employe', 'view personal_audit');
        $employe = User::factory()->create();

        foreach ([
            route('personal.personalakte.show', $employe->id),
            route('personal.personalakte.stammdaten', $employe->id),
            route('personal.contracts.index', $employe->id),
            route('personal.personalakte.verlauf', $employe->id),
        ] as $url) {
            $this->get($url)->assertOk()->assertSee('Bereiche der Personalakte');
        }
    }
}
