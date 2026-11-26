<?php

namespace Tests\Feature\API\v1;

use App\Models\PaedDiaryTask;
use App\Models\Setting;
use App\Models\Wochenplan\WpAufgabe;
use App\Models\Wochenplan\WpFach;
use App\Models\Wochenplan\WpPlan;
use App\Models\Wochenplan\WpPlanFach;
use App\Services\Wochenplan\WochenplanService;
use Illuminate\Support\Facades\Cache;

class WeeklyPlanApiTest extends ApiTestCase
{
    private function anbindung(bool $aktiv): void
    {
        Setting::where('setting', 'wochenplan_tagebuch_anbindung')->update(['value' => $aktiv ? '1' : '0']);
        Cache::forget('setting_wochenplan_tagebuch_anbindung');
    }

    /** @return array{0: \App\Models\Klasse, 1: \App\Models\Schueler, 2: WpAufgabe} */
    private function planMitAufgabe(): array
    {
        [$klasse, $schueler] = $this->classWithStudent();
        $plan = WpPlan::factory()->create([
            'klasse_id' => $klasse->id, 'gueltig_von' => now()->startOfWeek(), 'gueltig_bis' => now()->startOfWeek()->addDays(4),
        ]);
        $pf = WpPlanFach::create(['wp_plan_id' => $plan->id, 'wp_fach_id' => WpFach::factory()->create(['name' => 'Deutsch'])->id, 'sort_order' => 0]);
        $aufgabe = WpAufgabe::create(['wp_plan_fach_id' => $pf->id, 'aufgabe' => 'Lesen S. 12', 'dauer' => '20 min', 'sort_order' => 0]);

        return [$klasse, $schueler, $aufgabe];
    }

    public function test_wochenplan_eines_kindes_abrufen_und_abhaken(): void
    {
        $this->anbindung(true);
        [$klasse, $schueler, $aufgabe] = $this->planMitAufgabe();
        $this->actingAsTeacher(['view paed diary', 'view wochenplan'], [$klasse]);

        $this->getJson(self::API."/students/{$schueler->id}/weekly-plan")->assertOk()
            ->assertJsonPath('data.summary.total', 1)
            ->assertJsonPath('data.plans.0.subjects.0.name', 'Deutsch')
            ->assertJsonPath('data.plans.0.subjects.0.tasks.0.text', 'Lesen S. 12')
            ->assertJsonPath('data.plans.0.subjects.0.tasks.0.status', 'open');

        $this->postJson(self::API."/weekly-plan/tasks/{$aufgabe->id}/status", ['student_id' => $schueler->id, 'status' => 'partial', 'note' => 'halb'])
            ->assertOk()->assertJsonPath('data.status', 'partial')->assertJsonPath('data.note', 'halb');
        $this->assertDatabaseHas('wp_aufgabe_status', ['wp_aufgabe_id' => $aufgabe->id, 'status' => 'teilweise']);

        $this->deleteJson(self::API."/weekly-plan/tasks/{$aufgabe->id}/status?student_id={$schueler->id}")
            ->assertOk()->assertJsonPath('data.status', 'open');
        $this->assertDatabaseCount('wp_aufgabe_status', 0);
    }

    public function test_done_schliesst_verknuepfte_tagebuch_aufgabe(): void
    {
        $this->anbindung(true);
        [$klasse, $schueler] = $this->planMitAufgabe();
        $plan = WpPlan::firstOrFail();
        $kp = app(WochenplanService::class)->kinderplanErstellen($plan, $schueler);
        $task = PaedDiaryTask::factory()->create(['klasse_id' => $klasse->id, 'schueler_id' => $schueler->id, 'status' => 'open']);
        $aufgabe = app(WochenplanService::class)->tagebuchAufgabeUebernehmen($kp->planFaecher()->first(), $task, []);
        $this->actingAsTeacher(['view paed diary', 'create wochenplan'], [$klasse]);

        $this->postJson(self::API."/weekly-plan/tasks/{$aufgabe->id}/status", ['student_id' => $schueler->id, 'status' => 'done'])
            ->assertOk()->assertJsonPath('data.from_diary', true);
        $this->assertSame('closed', $task->fresh()->status);
    }

    public function test_ohne_anbindung_404_und_fremde_klasse_403(): void
    {
        [$klasse, $schueler, $aufgabe] = $this->planMitAufgabe();
        $this->actingAsTeacher(['view paed diary', 'view wochenplan'], [$klasse]);

        $this->anbindung(false);
        $this->getJson(self::API."/students/{$schueler->id}/weekly-plan")->assertNotFound();

        $this->anbindung(true);
        $this->actingAsTeacher(['view paed diary', 'view wochenplan'], []);
        $this->getJson(self::API."/students/{$schueler->id}/weekly-plan")->assertForbidden();
        $this->postJson(self::API."/weekly-plan/tasks/{$aufgabe->id}/status", ['student_id' => $schueler->id, 'status' => 'done'])->assertForbidden();
    }

    public function test_ohne_wochenplan_recht_403(): void
    {
        $this->anbindung(true);
        [$klasse, $schueler] = $this->planMitAufgabe();
        $this->actingAsTeacher(['view paed diary'], [$klasse]);

        $this->getJson(self::API."/students/{$schueler->id}/weekly-plan")->assertForbidden();
    }
}
