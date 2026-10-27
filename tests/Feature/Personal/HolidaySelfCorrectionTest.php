<?php

namespace Tests\Feature\Personal;

use App\Models\personal\Holiday;
use App\Models\User;
use Tests\TestCase;
use Tests\Traits\MocksExternalApis;

/**
 * Benutzer ohne Vorgesetzten (z. B. Geschäftsführung) dürfen ihre eigenen
 * Urlaube jederzeit korrigieren und löschen – auch rückwirkend.
 */
class HolidaySelfCorrectionTest extends TestCase
{
    use MocksExternalApis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockHolidayApi();
    }

    private function pastHoliday(User $user): Holiday
    {
        return Holiday::factory()->for($user, 'employe')->approved()->create([
            'start_date' => now()->subMonth()->startOfWeek()->format('Y-m-d'),
            'end_date'   => now()->subMonth()->startOfWeek()->addDays(2)->format('Y-m-d'),
        ]);
    }

    /** @test */
    public function user_ohne_vorgesetzten_kann_vergangenen_urlaub_korrigieren(): void
    {
        $user = $this->actingAsWithPermission('has holidays');
        $holiday = $this->pastHoliday($user);

        $start = $holiday->start_date->copy()->addDay();
        $end = $holiday->end_date->copy();

        $this->from('/holidays')
            ->put(route('holidays.correct', $holiday->id), [
                'start_date' => $start->format('Y-m-d'),
                'end_date'   => $end->format('Y-m-d'),
            ])
            ->assertSessionHas('type', 'success');

        $holiday->refresh();
        $this->assertEquals($start->format('Y-m-d'), $holiday->start_date->format('Y-m-d'));
        $this->assertEquals(workdays($start, $end), $holiday->days);
        $this->assertTrue($holiday->approved);
    }

    /** @test */
    public function user_ohne_vorgesetzten_kann_vergangenen_urlaub_loeschen(): void
    {
        $user = $this->actingAsWithPermission('has holidays');
        $holiday = $this->pastHoliday($user);

        $this->from('/holidays')->get('holidays/' . $holiday->id . '/delete')
            ->assertSessionHas('type', 'success');

        $this->assertSoftDeleted($holiday);
    }

    /** @test */
    public function user_mit_vorgesetztem_kann_vergangenen_urlaub_nicht_korrigieren(): void
    {
        $superior = User::factory()->create();
        $user = $this->actingAsWithPermission('has holidays');
        $user->update(['superior_id' => $superior->id]);
        $holiday = $this->pastHoliday($user);
        $originalStart = $holiday->start_date->format('Y-m-d');

        $this->from('/holidays')
            ->put(route('holidays.correct', $holiday->id), [
                'start_date' => $holiday->start_date->copy()->addDay()->format('Y-m-d'),
                'end_date'   => $holiday->end_date->format('Y-m-d'),
            ])
            ->assertSessionHas('type', 'danger');

        $this->from('/holidays')->get('holidays/' . $holiday->id . '/delete')
            ->assertSessionHas('type', 'danger');

        $holiday->refresh();
        $this->assertEquals($originalStart, $holiday->start_date->format('Y-m-d'));
        $this->assertNull($holiday->deleted_at);
    }

    /** @test */
    public function fremder_urlaub_kann_nicht_korrigiert_werden(): void
    {
        $this->actingAsWithPermission('has holidays');
        $holiday = $this->pastHoliday(User::factory()->create());

        $this->from('/holidays')
            ->put(route('holidays.correct', $holiday->id), [
                'start_date' => $holiday->start_date->format('Y-m-d'),
                'end_date'   => $holiday->end_date->format('Y-m-d'),
            ])
            ->assertSessionHas('type', 'danger');
    }

    /** @test */
    public function korrektur_mit_ueberschneidung_wird_abgelehnt(): void
    {
        $user = $this->actingAsWithPermission('has holidays');
        $holiday = $this->pastHoliday($user);
        $other = Holiday::factory()->for($user, 'employe')->approved()->create([
            'start_date' => $holiday->end_date->copy()->addDays(7)->format('Y-m-d'),
            'end_date'   => $holiday->end_date->copy()->addDays(8)->format('Y-m-d'),
        ]);

        $this->from('/holidays')
            ->put(route('holidays.correct', $holiday->id), [
                'start_date' => $holiday->start_date->format('Y-m-d'),
                'end_date'   => $other->start_date->format('Y-m-d'),
            ])
            ->assertSessionHas('type', 'danger');
    }
}
