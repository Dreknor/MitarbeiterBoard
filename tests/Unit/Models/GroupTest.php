<?php

namespace Tests\Unit\Models;

use App\Models\Group;
use App\Models\Meeting;
use App\Models\personal\Employment;
use App\Models\personal\HourType;
use App\Models\personal\Roster;
use App\Models\Presence;
use App\Models\Theme;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

class GroupTest extends TestCase
{
    // ─── Dual-Purpose: Abteilung ──────────────────────────────────────────────

    public function test_group_als_abteilung_mit_needsRoster(): void
    {
        $group = Group::factory()->asDepartment()->create();

        $this->assertTrue($group->needsRoster);
    }

    public function test_group_als_meeting_gruppe(): void
    {
        $group = Group::factory()->asMeetingGroup()->create();

        $this->assertFalse($group->needsRoster);
        $this->assertTrue($group->use_meetings);
    }

    // ─── Relationen ──────────────────────────────────────────────────────────

    public function test_group_hat_users_pivot_relation(): void
    {
        $group = Group::factory()->create();
        $user  = User::factory()->create();

        $group->users()->attach($user);

        $this->assertCount(1, $group->users);
        $this->assertEquals($user->id, $group->users->first()->id);
    }

    public function test_group_user_zuweisung_und_entfernung(): void
    {
        $group = Group::factory()->create();
        $user  = User::factory()->create();

        $group->users()->attach($user);
        $this->assertCount(1, $group->users);

        $group->users()->detach($user);
        $group->refresh();
        $this->assertCount(0, $group->users);
    }

    public function test_group_hat_themes_relation(): void
    {
        $group = Group::factory()->asMeetingGroup()->create();
        Theme::factory()->for($group)->create();

        $this->assertCount(1, $group->themes);
    }

    public function test_group_hat_meetings_relation(): void
    {
        $group   = Group::factory()->asMeetingGroup()->create();
        $creator = User::factory()->create();
        Meeting::factory()->for($group)->create();

        $this->assertCount(1, $group->meetings);
    }

    public function test_group_hat_presences_relation(): void
    {
        $group = Group::factory()->create();
        $user  = User::factory()->create();
        Presence::create([
            'group_id'   => $group->id,
            'user_id'    => $user->id,
            'date'       => now()->toDateString(),
            'created_by' => $user->id,
        ]);

        $this->assertCount(1, $group->presences);
    }

    public function test_group_hat_rosters_relation(): void
    {
        $dept   = Group::factory()->asDepartment()->create();
        Roster::factory()->for($dept, 'department')->create();

        $this->assertCount(1, $dept->rosters);
    }

    public function test_group_hat_employments_relation(): void
    {
        $dept = Group::factory()->asDepartment()->create();
        $user = User::factory()->create();
        $ht   = HourType::factory()->create();

        Employment::factory()->for($user, 'employe')->for($dept, 'department')->create([
            'hour_type_id' => $ht->id,
        ]);

        $this->assertCount(1, $dept->employments);
    }

    public function test_group_hat_roster_task_requirements_relation(): void
    {
        $dept = Group::factory()->asDepartment()->create();

        $this->assertCount(0, $dept->roster_task_requirements);
    }

    // ─── weekday_name ────────────────────────────────────────────────────────

    public function test_weekday_name_gibt_wochentag_zurueck(): void
    {
        $group = Group::factory()->create(['meeting_weekday' => 1]); // Montag

        $name = $group->weekday_name();
        $this->assertNotEmpty($name);
        $this->assertIsString($name);
    }

    // ─── activeEmployes ──────────────────────────────────────────────────────

    public function test_activeEmployes_gibt_aktive_mitarbeiter_zurueck(): void
    {
        $dept = Group::factory()->asDepartment()->create();
        $user = User::factory()->create();
        $ht   = HourType::factory()->create();

        Employment::factory()->for($user, 'employe')->for($dept, 'department')->create([
            'hour_type_id' => $ht->id,
            'start'        => Carbon::now()->subYear(),
            'end'          => null,
        ]);

        $result = $dept->activeEmployes(Carbon::now());

        $this->assertCount(1, $result);
        $this->assertEquals($user->id, $result->first()->id);
    }

    public function test_activeEmployes_schliesst_beendete_anstellungen_aus(): void
    {
        $dept = Group::factory()->asDepartment()->create();
        $user = User::factory()->create();
        $ht   = HourType::factory()->create();

        Employment::factory()->for($user, 'employe')->for($dept, 'department')->create([
            'hour_type_id' => $ht->id,
            'start'        => Carbon::now()->subYears(2),
            'end'          => Carbon::now()->subYear(),
        ]);

        $result = $dept->activeEmployes(Carbon::now());

        $this->assertCount(0, $result);
    }

    // ─── Casts ───────────────────────────────────────────────────────────────

    public function test_needsRoster_ist_boolean(): void
    {
        $group = Group::factory()->asDepartment()->create();
        $this->assertIsBool($group->needsRoster);
    }

    public function test_hasWochenplan_ist_boolean(): void
    {
        $group = Group::factory()->withWochenplan()->create();
        $this->assertIsBool($group->hasWochenplan);
        $this->assertTrue($group->hasWochenplan);
    }

    // ─── SoftDeletes ─────────────────────────────────────────────────────────

    public function test_group_wird_soft_deleted(): void
    {
        $group = Group::factory()->create();
        $id    = $group->id;

        $group->delete();

        $this->assertNull(Group::find($id));
        $this->assertNotNull(Group::withTrashed()->find($id));
    }

    public function test_group_ist_wiederherstellbar(): void
    {
        $group = Group::factory()->create();
        $id    = $group->id;

        $group->delete();
        Group::withTrashed()->find($id)->restore();

        $this->assertNotNull(Group::find($id));
    }

    // ─── Creator ─────────────────────────────────────────────────────────────

    public function test_group_hat_creator_relation(): void
    {
        $creator = User::factory()->create();
        $group   = Group::factory()->create(['creator_id' => $creator->id]);

        $this->assertEquals($creator->id, $group->creator->id);
    }
}

