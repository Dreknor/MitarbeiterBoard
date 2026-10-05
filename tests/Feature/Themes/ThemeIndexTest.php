<?php

namespace Tests\Feature\Themes;

use App\Models\Group;
use App\Models\Theme;
use App\Models\User;
use Tests\TestCase;

class ThemeIndexTest extends TestCase
{
    private Group $group;
    private User $me;

    protected function setUp(): void
    {
        parent::setUp();

        $this->me = $this->actingAsWithPermission('view groups');
        $this->group = Group::factory()->create(['name' => 'Steuergruppe', 'viewType' => 'date']);
        $this->group->users()->attach($this->me->id);
    }

    private function thema(string $datum, string $titel): void
    {
        Theme::factory()->create([
            'group_id' => $this->group->id,
            'creator_id' => $this->me->id,
            'date' => $datum,
            'theme' => $titel,
        ]);
    }

    /** @test */
    public function offen_steht_beim_stapeln_oben_danach_tage_absteigend(): void
    {
        $this->group->update(['stack_themes' => true]);
        $this->thema(now()->addWeek()->toDateString(), 'Thema naechste Woche');
        $this->thema(now()->subWeek()->toDateString(), 'Thema Vergangenheit');
        $this->thema(now()->addWeeks(2)->toDateString(), 'Thema uebernaechste Woche');

        $this->get(url($this->group->name.'/themes'))
            ->assertOk()
            ->assertSeeInOrder([
                'id="offen"',
                'id="'.now()->addWeeks(2)->format('Ymd').'"',
                'id="'.now()->addWeek()->format('Ymd').'"',
            ], false)
            ->assertSee('Zum nächsten Termin ('.now()->addWeek()->format('d.m.Y').')');
    }

    /** @test */
    public function ohne_stapeln_springt_der_button_zu_heute(): void
    {
        $this->thema(now()->subWeek()->toDateString(), 'Thema Vergangenheit');
        $this->thema(now()->toDateString(), 'Thema heute');
        $this->thema(now()->addWeek()->toDateString(), 'Thema Zukunft');

        $this->get(url($this->group->name.'/themes'))
            ->assertOk()
            ->assertSeeInOrder([
                now()->addWeek()->format('d.m.Y'),
                now()->format('d.m.Y'),
                now()->subWeek()->format('d.m.Y'),
            ])
            ->assertSee('Zu heute')
            ->assertSee('#'.now()->format('Ymd'), false);
    }
}
