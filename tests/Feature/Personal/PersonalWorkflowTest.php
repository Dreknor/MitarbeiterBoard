<?php

namespace Tests\Feature\Personal;

use App\Enums\ContractType;
use App\Enums\EmploymentType;
use App\Enums\ProcedureLinkStatus;
use App\Enums\ProcedureLinkType;
use App\Enums\QualificationStatus;
use App\Enums\TerminationReason;
use App\Events\Personal\EmployeeNameChanged;
use App\Models\Group;
use App\Models\personal\EmployeData;
use App\Models\personal\EmployeeQualification;
use App\Models\personal\Employment;
use App\Models\personal\HourType;
use App\Models\personal\PersonalReminder;
use App\Models\personal\ProcedureLink;
use App\Models\personal\QualificationType;
use App\Models\personal\SchoolType;
use App\Models\Procedure;
use App\Models\Procedure_Step;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\Personal\PersonalReminderNotification;
use App\Services\Personal\ContractService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PersonalWorkflowTest extends TestCase
{
    private function hr(string ...$extra): User
    {
        return $this->actingAsWithPermission(
            'view personal_data', 'view contracts', 'edit contracts', 'view personal_data:all', 'edit personal_data:all', ...$extra
        );
    }

    private function contractData(array $override = []): array
    {
        return array_merge([
            'employment_type' => 'regulaer',
            'contract_type'   => 'unbefristet',
            'department_id'   => Group::factory()->asDepartment()->create()->id,
            'hour_type_id'    => HourType::factory()->create(['fulltimehours' => 40])->id,
            'start'           => now()->toDateString(),
            'hours'           => 20,
        ], $override);
    }

    private function template(string $settingKey): Procedure
    {
        $template = Procedure::factory()->vorlage()->create();
        Procedure_Step::factory()->create(['procedure_id' => $template->id, 'parent' => null, 'durationDays' => 3]);
        Setting::updateOrCreate(['setting' => $settingKey], ['module' => 'Personal', 'setting_name' => $settingKey, 'type' => 'number', 'value' => (string) $template->id]);

        return $template;
    }

    /** @test */
    public function teacher_hours_are_calculated_from_deputat(): void
    {
        $this->hr();
        $employe = User::factory()->create();
        $school = SchoolType::factory()->create(['default_deputat' => 28]);
        $data = $this->contractData(['employment_type' => 'lehrer', 'school_type_id' => $school->id, 'deputat_hours' => 14]);
        unset($data['hours']);

        $this->post(route('personal.contracts.store', $employe->id), $data)->assertSessionHasNoErrors();

        // 14 ÷ 28 × 40 = 20 Wochenstunden (50 %)
        $this->assertEquals(20.0, (float) Employment::where('employe_id', $employe->id)->firstOrFail()->hours);
    }

    /** @test */
    public function manual_hours_override_the_calculation_for_teachers(): void
    {
        $this->hr();
        $employe = User::factory()->create();
        $school = SchoolType::factory()->create(['default_deputat' => 28]);

        $this->post(route('personal.contracts.store', $employe->id), $this->contractData([
            'employment_type' => 'lehrer', 'school_type_id' => $school->id, 'deputat_hours' => 14,
            'hours' => 18, 'hours_manual' => 1,
        ]))->assertSessionHasNoErrors();

        $this->assertEquals(18.0, (float) Employment::where('employe_id', $employe->id)->firstOrFail()->hours);
    }

    /** @test */
    public function first_employment_starts_onboarding_and_links_the_procedure(): void
    {
        $this->hr();
        $this->template('onboarding_template_id');
        $employe = User::factory()->create();

        app(ContractService::class)->create($employe, $this->contractData());
        app(ContractService::class)->create($employe, $this->contractData()); // zweite Anstellung: kein weiteres Onboarding

        $links = ProcedureLink::where('employe_id', $employe->id)->get();
        $this->assertCount(1, $links);
        $this->assertSame(ProcedureLinkType::Onboarding, $links->first()->type);
        $this->assertNotNull($links->first()->procedure->started_at);
    }

    /** @test */
    public function no_onboarding_without_configured_template(): void
    {
        $this->hr();
        $employe = User::factory()->create();

        app(ContractService::class)->create($employe, $this->contractData());

        $this->assertSame(0, ProcedureLink::count());
    }

    /** @test */
    public function completing_all_steps_closes_the_process_link_and_marks_qualification(): void
    {
        $this->hr();
        $template = $this->template('onboarding_template_id');
        $type = QualificationType::factory()->create(['name' => 'Erste-Hilfe-Kurs', 'category' => 'freiwillig', 'validity_months' => 24]);
        Procedure_Step::where('procedure_id', $template->id)->update(['name' => 'Erste-Hilfe-Kurs']);
        $employe = User::factory()->create();
        app(ContractService::class)->create($employe, $this->contractData());

        $link = ProcedureLink::firstOrFail();
        $step = Procedure_Step::where('procedure_id', $link->procedure_id)->firstOrFail();
        app(\App\Services\Procedure\ProcedureStepService::class)->complete($step, auth()->user());

        $this->assertSame(ProcedureLinkStatus::Abgeschlossen, $link->fresh()->status);
        $qual = EmployeeQualification::where('employe_id', $employe->id)->where('qualification_type_id', $type->id)->firstOrFail();
        $this->assertSame(QualificationStatus::Gueltig, $qual->status);
    }

    /** @test */
    public function final_termination_starts_offboarding_and_retention_reminder(): void
    {
        $this->hr();
        $this->template('offboarding_template_id');
        $employe = User::factory()->create();
        $employment = Employment::factory()->create(['employe_id' => $employe->id, 'start' => now()->subYear()]);

        $employment->setBeendet(TerminationReason::KuendigungAN, now()->subDay());

        $this->assertSame(ProcedureLinkType::Offboarding, ProcedureLink::where('employe_id', $employe->id)->firstOrFail()->type);
        $retention = PersonalReminder::where('employe_id', $employe->id)->where('type', PersonalReminder::TYPE_RETENTION)->firstOrFail();
        $this->assertSame(now()->subDay()->addYears(10)->toDateString(), $retention->due_date->toDateString());
    }

    /** @test */
    public function termination_with_another_open_employment_does_not_offboard(): void
    {
        $this->hr();
        $this->template('offboarding_template_id');
        $employe = User::factory()->create();
        $ended = Employment::factory()->create(['employe_id' => $employe->id, 'start' => now()->subYear()]);
        Employment::factory()->create(['employe_id' => $employe->id, 'start' => now()->subYear()]);

        $ended->setBeendet(TerminationReason::Aufhebung, now()->subDay());

        $this->assertSame(0, ProcedureLink::count());
        $this->assertSame(0, PersonalReminder::where('type', PersonalReminder::TYPE_RETENTION)->count());
    }

    /** @test */
    public function probation_and_fixed_term_reminders_are_created_and_notified(): void
    {
        Notification::fake();
        $hr = $this->hr();
        $employe = User::factory()->create();

        app(ContractService::class)->create($employe, $this->contractData([
            'contract_type' => 'befristet', 'end' => now()->addDays(30)->toDateString(),
            'probation_end' => now()->addDays(10)->toDateString(),
        ]));

        $this->assertSame(2, PersonalReminder::where('employe_id', $employe->id)->open()->count());

        $sent = app(\App\Services\Personal\PersonalReminderService::class)->notifyDue();

        // Probezeit (10 Tage, Vorlauf 14) ist fällig, Vertragsende (30 Tage, Vorlauf 60) ebenfalls
        $this->assertSame(2, $sent);
        Notification::assertSentTo($hr, PersonalReminderNotification::class);
    }

    /** @test */
    public function changing_probation_end_reschedules_the_reminder(): void
    {
        $this->hr();
        $employe = User::factory()->create();
        $employment = app(ContractService::class)->create($employe, $this->contractData([
            'probation_end' => now()->addDays(5)->toDateString(),
        ]))['employment'];
        PersonalReminder::query()->update(['notified_at' => now()]);

        $employment->update(['probation_end' => now()->addDays(20)]);

        $reminder = PersonalReminder::where('employment_id', $employment->id)->firstOrFail();
        $this->assertNull($reminder->notified_at);
        $this->assertSame(now()->addDays(20)->toDateString(), $reminder->due_date->toDateString());
    }

    /** @test */
    public function required_qualifications_are_initialized_as_missing(): void
    {
        $this->hr();
        $type = QualificationType::factory()->create(['category' => 'pflicht', 'is_active' => true, 'applies_to' => null]);
        $employe = User::factory()->create();

        app(ContractService::class)->create($employe, $this->contractData());

        $qual = EmployeeQualification::where('employe_id', $employe->id)->where('qualification_type_id', $type->id)->firstOrFail();
        $this->assertSame(QualificationStatus::Fehlend, $qual->status);
    }

    /** @test */
    public function renaming_an_employee_dispatches_name_changed(): void
    {
        $employe = User::factory()->create();
        $data = EmployeData::create(['user_id' => $employe->id, 'familienname' => 'Alt', 'vorname' => 'Anna', 'geschlecht' => 'anderes']);
        Event::fake([EmployeeNameChanged::class]);

        $data->update(['familienname' => 'Neu']);

        Event::assertDispatched(EmployeeNameChanged::class, fn ($e) => $e->oldFamilienname === 'Alt' && $e->newName === 'Anna Neu');
    }

    /** @test */
    public function employes_show_redirects_to_the_personalakte(): void
    {
        $this->hr('edit employe');
        $employe = User::factory()->create();

        $this->get(route('employes.show', $employe->id))->assertRedirect(route('personal.personalakte.show', $employe->id));
    }

    /** @test */
    public function stammdaten_page_renders_and_akte_has_no_orgchart_card(): void
    {
        $this->hr('edit employe', 'view orgchart');
        $employe = User::factory()->create();

        $this->get(route('personal.personalakte.stammdaten', $employe->id))->assertOk();
        $this->get(route('personal.personalakte.show', $employe->id))
            ->assertOk()
            ->assertDontSee('Hierarchie &amp; Stellenstruktur', false)
            ->assertSee('Was wirkt sich wo aus');
    }

    /** @test */
    public function verlauf_requires_permission_and_shows_who_changed_what(): void
    {
        // Auditing ist in der Konsole (Tests) aus; der Observer wird nur beim Booten des Modells registriert
        config(['audit.console' => true]);
        Employment::observe(new \OwenIt\Auditing\AuditableObserver);
        $employe = User::factory()->create();

        $this->hr();
        $this->get(route('personal.personalakte.verlauf', $employe->id))->assertForbidden();

        $hr = $this->hr('view personal_audit');
        $employment = app(ContractService::class)->create($employe, $this->contractData(['hours' => 20]), $hr)['employment'];
        $employment->update(['hours' => 30]);

        $this->get(route('personal.personalakte.verlauf', $employe->id))
            ->assertOk()
            ->assertSee('Änderungsverlauf')
            ->assertSee('Wochenstunden')
            ->assertSee($hr->name);
    }

    /** @test */
    public function expired_fixed_term_contract_is_ended_by_the_nightly_command(): void
    {
        $employment = Employment::factory()->create([
            'contract_type' => ContractType::Befristet, 'employment_type' => EmploymentType::Regulaer,
            'start' => now()->subYear(), 'end' => now()->subDays(2),
        ]);

        $this->artisan('personal:vertraege-abschliessen')->assertSuccessful();

        $this->assertSame('beendet', $employment->fresh()->status->value);
    }
}
