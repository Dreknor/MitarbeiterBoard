<?php

namespace Tests\Feature;

use App\Models\OxCalendar;
use App\Models\Setting;
use App\Models\User;
use Tests\TestCase;

/** Migration 2026_11_26_000001: persönliche Kalenderdaten aus settings in ihre Tabellen. */
class CalendarPersoenlicheDatenMigrationTest extends TestCase
{
    public function test_farben_und_feed_token_werden_uebernommen_und_settings_bereinigt(): void
    {
        $user = User::factory()->create();
        $kalender = OxCalendar::factory()->create();
        $token = str_repeat('ab', 32);

        Setting::insert([
            ['module' => 'Kalender', 'setting' => "user_color_{$user->id}_{$kalender->id}", 'setting_name' => 'x', 'type' => 'string', 'value' => '#FF0000'],
            ['module' => 'Kalender', 'setting' => "user_color_{$user->id}_999999", 'setting_name' => 'x', 'type' => 'string', 'value' => '#00ff00'],
            ['module' => 'Kalender', 'setting' => "feed_token_{$user->id}", 'setting_name' => 'x', 'type' => 'string', 'value' => $token],
        ]);

        $migration = require database_path('migrations/2026_11_26_000001_kalender_persoenliche_daten_aus_settings.php');
        $migration->up();

        $this->assertSame('#ff0000', $user->calendarColors()->first()->pivot->farbe);
        $this->assertSame(1, $user->calendarColors()->count(), 'unbekannter Kalender wird verworfen');
        $this->assertSame($token, $user->fresh()->calendar_token);
        $this->assertSame(0, Setting::where('setting', 'like', 'user_color_%')->orWhere('setting', 'like', 'feed_token_%')->count());

        // Bestehender Abo-Link funktioniert weiter
        \Spatie\Permission\Models\Permission::findOrCreate('view calendar');
        $user->givePermissionTo('view calendar');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $this->get("/calendar/feed/{$token}.ics")->assertOk()->assertSee('BEGIN:VCALENDAR');
    }
}
