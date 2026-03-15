<?php

namespace Tests\Unit\Services;

use App\Models\Group;
use App\Models\OxCalendar;
use App\Models\User;
use App\Services\OxCalendarService;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Unit-Tests für OxCalendarService::sichtbareKalender() und canWriteCalendar().
 *
 * Prüft die zentrale Sichtbarkeits- und Schreibberechtigungs-Logik.
 */
class OxCalendarServiceSichtbarkeitTest extends TestCase
{
    protected OxCalendarService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->service = new OxCalendarService();

        // Benötigte Permissions sicherstellen
        Permission::findOrCreate('view calendar');
        Permission::findOrCreate('manage calendar');
        Permission::findOrCreate('create calendar events');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    // =========================================================================
    // sichtbareKalender()
    // =========================================================================

    public function test_admin_sieht_alle_sichtbaren_kalender(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo(['manage calendar', 'view calendar']);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $cal1         = OxCalendar::factory()->create(['sichtbar' => true]);
        $cal2         = OxCalendar::factory()->create(['sichtbar' => true]);
        $calUnsichtbar = OxCalendar::factory()->create(['sichtbar' => false]);

        $sichtbare = $this->service->sichtbareKalender($admin);

        $this->assertTrue($sichtbare->contains('id', $cal1->id));
        $this->assertTrue($sichtbare->contains('id', $cal2->id));
        $this->assertFalse($sichtbare->contains('id', $calUnsichtbar->id));
    }

    public function test_admin_sieht_kalender_mit_eingeschraenkten_gruppen(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo(['manage calendar', 'view calendar']);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $fremdeGruppe = Group::factory()->create();
        $kalender = OxCalendar::factory()->create(['sichtbar' => true]);
        $kalender->groups()->attach($fremdeGruppe->id, ['schreibbar' => false]);
        // Admin ist nicht in fremdeGruppe – trotzdem sichtbar

        $sichtbare = $this->service->sichtbareKalender($admin);

        $this->assertTrue($sichtbare->contains('id', $kalender->id));
    }

    public function test_user_sieht_oeffentliche_kalender_ohne_gruppen(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view calendar');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $oeffentlich = OxCalendar::factory()->create(['sichtbar' => true]);
        // Keine Gruppen → öffentlich

        $sichtbare = $this->service->sichtbareKalender($user);

        $this->assertTrue($sichtbare->contains('id', $oeffentlich->id));
    }

    public function test_user_sieht_kalender_seiner_eigenen_gruppe(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view calendar');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $gruppe = Group::factory()->create();
        $user->groups_rel()->attach($gruppe);

        $kalenderMitGruppe = OxCalendar::factory()->create(['sichtbar' => true]);
        $kalenderMitGruppe->groups()->attach($gruppe->id, ['schreibbar' => false]);

        $sichtbare = $this->service->sichtbareKalender($user);

        $this->assertTrue($sichtbare->contains('id', $kalenderMitGruppe->id));
    }

    public function test_user_sieht_nicht_kalender_anderer_gruppen(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view calendar');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $andereGruppe = Group::factory()->create();
        // User ist NICHT in andereGruppe

        $kalenderAndereGruppe = OxCalendar::factory()->create(['sichtbar' => true]);
        $kalenderAndereGruppe->groups()->attach($andereGruppe->id, ['schreibbar' => false]);

        $sichtbare = $this->service->sichtbareKalender($user);

        $this->assertFalse($sichtbare->contains('id', $kalenderAndereGruppe->id));
    }

    public function test_user_sieht_kalender_wenn_in_mindestens_einer_von_mehreren_gruppen(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view calendar');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $gruppeA = Group::factory()->create();
        $gruppeB = Group::factory()->create();
        $user->groups_rel()->attach($gruppeA); // User nur in Gruppe A

        $kalender = OxCalendar::factory()->create(['sichtbar' => true]);
        $kalender->groups()->attach($gruppeA->id, ['schreibbar' => false]);
        $kalender->groups()->attach($gruppeB->id, ['schreibbar' => false]);
        // Kalender hat beide Gruppen, User ist in Gruppe A → sichtbar

        $sichtbare = $this->service->sichtbareKalender($user);

        $this->assertTrue($sichtbare->contains('id', $kalender->id));
    }

    public function test_unsichtbarer_kalender_wird_nie_gezeigt(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo(['manage calendar', 'view calendar']);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $unsichtbar = OxCalendar::factory()->create(['sichtbar' => false]);

        $sichtbare = $this->service->sichtbareKalender($admin);

        $this->assertFalse($sichtbare->contains('id', $unsichtbar->id));
    }

    public function test_user_ohne_view_calendar_sieht_nichts(): void
    {
        $user = User::factory()->create();
        // Keine Permissions

        OxCalendar::factory()->create(['sichtbar' => true]);

        $sichtbare = $this->service->sichtbareKalender($user);

        $this->assertEmpty($sichtbare);
    }

    // =========================================================================
    // canWriteCalendar()
    // =========================================================================

    public function test_admin_darf_in_schreibbaren_kalender_schreiben(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo(['manage calendar', 'create calendar events']);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $kalender = OxCalendar::factory()->create(['schreibbar' => true]);
        $kalender->load('groups'); // Relation laden

        $this->assertTrue($this->service->canWriteCalendar($admin, $kalender));
    }

    public function test_user_ohne_create_permission_darf_nicht_schreiben(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view calendar');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $kalender = OxCalendar::factory()->create(['schreibbar' => true]);
        $kalender->load('groups');

        $this->assertFalse($this->service->canWriteCalendar($user, $kalender));
    }

    public function test_user_darf_nicht_in_nicht_schreibbaren_kalender_schreiben(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('create calendar events');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $kalender = OxCalendar::factory()->create(['schreibbar' => false]);
        $kalender->load('groups');

        $this->assertFalse($this->service->canWriteCalendar($user, $kalender));
    }

    public function test_user_darf_in_oeffentlich_schreibbaren_kalender_ohne_gruppen_schreiben(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('create calendar events');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $kalender = OxCalendar::factory()->create(['schreibbar' => true]);
        // Keine Gruppen → öffentlich schreibbar

        $kalender->load('groups');

        $this->assertTrue($this->service->canWriteCalendar($user, $kalender));
    }

    public function test_user_darf_schreiben_wenn_in_schreibbarer_gruppe(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('create calendar events');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $gruppe = Group::factory()->create();
        $user->groups_rel()->attach($gruppe);

        $kalender = OxCalendar::factory()->create(['schreibbar' => true]);
        $kalender->groups()->attach($gruppe->id, ['schreibbar' => true]);

        $kalender->load('groups');

        $this->assertTrue($this->service->canWriteCalendar($user, $kalender));
    }

    public function test_user_darf_nicht_schreiben_wenn_gruppe_nicht_schreibbar(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('create calendar events');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $gruppe = Group::factory()->create();
        $user->groups_rel()->attach($gruppe);

        $kalender = OxCalendar::factory()->create(['schreibbar' => true]);
        $kalender->groups()->attach($gruppe->id, ['schreibbar' => false]); // Nur lesen!

        $kalender->load('groups');

        $this->assertFalse($this->service->canWriteCalendar($user, $kalender));
    }

    public function test_user_darf_nicht_schreiben_wenn_nicht_in_kalender_gruppe(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('create calendar events');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $andereGruppe = Group::factory()->create();
        // User ist NICHT in andereGruppe

        $kalender = OxCalendar::factory()->create(['schreibbar' => true]);
        $kalender->groups()->attach($andereGruppe->id, ['schreibbar' => true]);

        $kalender->load('groups');

        $this->assertFalse($this->service->canWriteCalendar($user, $kalender));
    }

    // =========================================================================
    // Rückgabetyp
    // =========================================================================

    public function test_sichtbareKalender_gibt_collection_zurueck(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view calendar');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $result = $this->service->sichtbareKalender($user);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
    }
}

