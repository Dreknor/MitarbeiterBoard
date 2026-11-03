<?php

namespace Tests\Feature\Personal\Zeit;

use App\Models\personal\Roster;
use App\Models\personal\RosterEvents;
use App\Models\personal\WorkingTime;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

class RosterDashboardCardTest extends TestCase
{
    use ZeitTestHelpers;

    private \App\Models\Group $abteilung;
    private \App\Models\Group $fremd;
    private User $ma;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-30 10:00:00');

        $this->abteilung = $this->abteilung();
        $this->fremd = $this->abteilung();
        $this->ma = User::factory()->create();
        $this->ma->groups_rel()->attach($this->abteilung->id);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function plan(\App\Models\Group $abteilung, array $attribute = []): Roster
    {
        return Roster::factory()->create(array_merge([
            'department_id' => $abteilung->id,
            'start_date' => '2026-09-28',
            'type' => 'normal',
            'published' => true,
        ], $attribute));
    }

    private function kachel(User $user): string
    {
        $this->actingAs($user);

        return view('personal.rosters.homeView')->render();
    }

    public function test_zeigt_veroeffentlichte_plaene_fremder_abteilungen_wenn_eingeplant(): void
    {
        $eigen = $this->plan($this->abteilung);
        $mitDienst = $this->plan($this->fremd);
        $mitTermin = $this->plan($this->abteilung(), ['start_date' => '2026-10-05']);
        $ohneMich = $this->plan($this->abteilung());
        $entwurf = $this->plan($this->abteilung(), ['published' => false]);
        $vergangen = $this->plan($this->fremd, ['start_date' => '2026-09-21']);

        WorkingTime::create(['roster_id' => $mitDienst->id, 'employe_id' => $this->ma->id, 'date' => '2026-10-01', 'start' => '08:00', 'end' => '14:00']);
        RosterEvents::create(['roster_id' => $mitTermin->id, 'employe_id' => $this->ma->id, 'date' => '2026-10-06', 'start' => '09:00', 'end' => '10:00', 'event' => 'Elterngespräch']);
        WorkingTime::create(['roster_id' => $entwurf->id, 'employe_id' => $this->ma->id, 'date' => '2026-10-01', 'start' => '08:00', 'end' => '14:00']);
        WorkingTime::create(['roster_id' => $vergangen->id, 'employe_id' => $this->ma->id, 'date' => '2026-09-22', 'start' => '08:00', 'end' => '14:00']);

        $html = $this->kachel($this->ma);

        foreach ([$eigen, $mitDienst, $mitTermin] as $roster) {
            $this->assertStringContainsString(route('roster.export.pdf', $roster->id), $html);
        }
        foreach ([$ohneMich, $entwurf, $vergangen] as $roster) {
            $this->assertStringNotContainsString(route('roster.export.pdf', $roster->id), $html);
        }

        $this->assertStringContainsString(route('roster.export.employe.pdf', [$mitDienst->id, $this->ma->id]), $html);
        $this->assertStringNotContainsString(route('roster.export.employe.pdf', [$eigen->id, $this->ma->id]), $html);

        // alle verlinkten Pläne darf die Person auch öffnen
        $this->actingAs($this->ma)->get(route('roster.export.pdf', $mitDienst->id))->assertOk();
    }

    public function test_arbeitszeiten_heute_aus_allen_aktuellen_plaenen(): void
    {
        $kollegin = User::factory()->create(['name' => 'Kollegin Hort']);
        $kollege = User::factory()->create(['name' => 'Kollege Schule']);

        $eigen = $this->plan($this->abteilung);
        $fremd = $this->plan($this->fremd);

        WorkingTime::create(['roster_id' => $eigen->id, 'employe_id' => $kollegin->id, 'date' => '2026-09-30', 'start' => '07:00', 'end' => '13:00']);
        WorkingTime::create(['roster_id' => $fremd->id, 'employe_id' => $this->ma->id, 'date' => '2026-09-30', 'start' => '12:00', 'end' => '16:00']);
        WorkingTime::create(['roster_id' => $fremd->id, 'employe_id' => $kollege->id, 'date' => '2026-09-30', 'start' => '08:00', 'end' => '12:00']);
        WorkingTime::create(['roster_id' => $fremd->id, 'employe_id' => $kollege->id, 'date' => '2026-10-01', 'start' => '09:30', 'end' => '12:00']);

        $html = $this->kachel($this->ma);

        $this->assertStringContainsString('Kollegin Hort', $html);
        $this->assertStringContainsString('Kollege Schule', $html);
        $this->assertStringContainsString($this->abteilung->name, $html);
        $this->assertStringContainsString($this->fremd->name, $html);
        $this->assertStringNotContainsString('09:30', $html);
    }

    public function test_planende_sehen_entwuerfe_ihrer_abteilung(): void
    {
        $planer = $this->rechte(User::factory()->create(), 'create roster');
        $planer->groups_rel()->attach($this->abteilung->id);

        $entwurf = $this->plan($this->abteilung, ['published' => false]);
        $fremderEntwurf = $this->plan($this->fremd, ['published' => false]);

        $html = $this->kachel($planer);

        $this->assertStringContainsString(route('roster.show', $entwurf->id), $html);
        $this->assertStringContainsString('Entwurf', $html);
        $this->assertStringNotContainsString(route('roster.export.pdf', $fremderEntwurf->id), $html);
    }
}
