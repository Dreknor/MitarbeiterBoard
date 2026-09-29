<?php

namespace Tests\Feature\Personal\Zeit;

use App\Models\personal\EmployeData;
use App\Models\personal\TimesheetDays;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class TimeRecordingTerminalTest extends TestCase
{
    use ZeitTestHelpers;

    private User $ma;
    private EmployeData $daten;

    protected function setUp(): void
    {
        parent::setUp();
        $this->neuesModell();
        Carbon::setTestNow('2026-09-29 07:30:00');
        RateLimiter::clear('time-recording-pin:1');

        $this->ma = $this->mitarbeiter();
        $this->daten = EmployeData::create([
            'user_id' => $this->ma->id,
            'familienname' => 'Muster',
            'vorname' => 'Erika',
            'geschlecht' => 'weiblich',
            'time_recording_key' => '1234567890',
            'secret_key' => '246810',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_pin_wird_gehasht_gespeichert(): void
    {
        $roh = \DB::table('employes_data')->where('id', $this->daten->id)->value('secret_key');

        $this->assertNotSame('246810', $roh);
        $this->assertTrue(Hash::check('246810', $roh));
        $this->assertArrayNotHasKey('secret_key', $this->daten->fresh()->toArray());
    }

    public function test_stempeln_mit_chip_und_pin(): void
    {
        $this->post(route('time_recording.read_key'), ['key' => '1234567890'])->assertOk()->assertSee('Bitte PIN eingeben');
        $this->post(route('time_recording.login'), ['secret_key' => '246810'])->assertOk()->assertSee('Kommen um');

        $this->assertSame(1, TimesheetDays::whereNull('end')->count());

        Carbon::setTestNow('2026-09-29 16:00:00');
        $this->post(route('time_recording.read_key'), ['key' => '1234567890']);
        $this->post(route('time_recording.login'), ['secret_key' => '246810'])->assertOk()->assertSee('Gehen um');

        $buchung = TimesheetDays::firstOrFail();
        $this->assertSame('16:00', $buchung->end->format('H:i'));
        $this->assertSame(30, (int) $buchung->pause, 'Automatische Pause nach § 4 ArbZG');
    }

    public function test_ohne_gescannten_chip_ist_kein_login_moeglich(): void
    {
        // Früher lag der Chip global im Cache – ein zweites Gerät konnte ihn "erben".
        $this->post(route('time_recording.login'), ['secret_key' => '246810'])->assertRedirect(route('time_recording.start'));
        $this->assertSame(0, TimesheetDays::count());
    }

    public function test_falsche_pin_sperrt_nach_fuenf_versuchen(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('time_recording.read_key'), ['key' => '1234567890']);
            $this->post(route('time_recording.login'), ['secret_key' => '000000'])->assertRedirect(route('time_recording.start'));
        }

        $this->post(route('time_recording.read_key'), ['key' => '1234567890'])->assertSessionHasErrors('key');
        $this->assertSame(0, TimesheetDays::count());
    }

    public function test_bestehende_pin_kann_am_terminal_nicht_ueberschrieben_werden(): void
    {
        $this->post(route('time_recording.read_key'), ['key' => '1234567890']);
        $this->post(route('time_recording.storeSecret'), ['secret_key' => '111111', 'secret_key_confirmation' => '111111'])
            ->assertRedirect(route('time_recording.start'));

        $this->assertTrue($this->daten->fresh()->checkPin('246810'));
    }

    public function test_neue_pin_kann_einmalig_gesetzt_werden(): void
    {
        $this->daten->update(['secret_key' => null]);

        $this->post(route('time_recording.read_key'), ['key' => '1234567890'])->assertOk()->assertSee('noch keine PIN');
        $this->post(route('time_recording.storeSecret'), ['secret_key' => '12', 'secret_key_confirmation' => '12'])->assertSessionHasErrors('secret_key');
        $this->post(route('time_recording.storeSecret'), ['secret_key' => '135790', 'secret_key_confirmation' => '135790'])->assertRedirect(route('time_recording.start'));

        $this->assertTrue($this->daten->fresh()->checkPin('135790'));
    }

    public function test_klartext_pin_aus_altbestand_wird_beim_login_gehasht(): void
    {
        \DB::table('employes_data')->where('id', $this->daten->id)->update(['secret_key' => '975310']);

        $this->post(route('time_recording.read_key'), ['key' => '1234567890']);
        $this->post(route('time_recording.login'), ['secret_key' => '975310'])->assertOk();

        $this->assertTrue(Hash::check('975310', \DB::table('employes_data')->where('id', $this->daten->id)->value('secret_key')));
    }

    public function test_terminal_token_bindet_an_registrierte_geraete(): void
    {
        $this->settingSetzen('time_recording_terminal_token', 'geheimes-terminal-token');

        $this->get(route('time_recording.start'))->assertForbidden();

        $antwort = $this->get(route('time_recording.start', ['token' => 'geheimes-terminal-token']))->assertRedirect(route('time_recording.start'));
        $cookie = collect($antwort->headers->getCookies())->first(fn ($c) => $c->getName() === \App\Http\Middleware\TimeRecordingTerminal::COOKIE);
        $this->assertNotNull($cookie);

        $this->withCookie(\App\Http\Middleware\TimeRecordingTerminal::COOKIE, hash('sha256', 'geheimes-terminal-token'))
            ->get(route('time_recording.start'))->assertOk();
    }

    public function test_kommen_gehen_aus_dem_dashboard_nur_per_post(): void
    {
        $this->actingAs($this->ma)->get('timesheets/'.$this->ma->id.'/login')->assertNotFound();
        $this->actingAs($this->ma)->post(route('timesheets.stamp'))->assertRedirect(route('home'));

        $this->assertSame(1, TimesheetDays::count());
    }
}
