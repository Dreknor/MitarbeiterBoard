<?php

namespace Tests\Unit;

use App\Models\personal\Holiday;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tests\Traits\MocksExternalApis;

class HelpersTest extends TestCase
{
    use MocksExternalApis;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    // ─── redirectBack ───────────────────────────────────────────────────────

    public function test_redirectBack_ohne_parameter_redirectet_zurueck(): void
    {
        $response = redirectBack();

        $this->assertEquals(302, $response->getStatusCode());
    }

    public function test_redirectBack_setzt_type_und_meldung_in_session(): void
    {
        $response = redirectBack('success', 'Gespeichert.');

        $this->assertEquals('success', $response->getSession()->get('type'));
        $this->assertEquals('Gespeichert.', $response->getSession()->get('Meldung'));
    }

    public function test_redirectBack_mit_anchor(): void
    {
        $response = redirectBack('info', 'Hinweis', '#abschnitt');

        $this->assertStringContainsString('#abschnitt', $response->getTargetUrl());
    }

    // ─── money ──────────────────────────────────────────────────────────────

    public function test_money_formatiert_betrag_mit_symbol(): void
    {
        $result = money(100.5);
        // number_format() ohne Locale liefert englisches Format (100.50)
        $this->assertStringContainsString('100', $result);
        $this->assertStringContainsString('50', $result);
        $this->assertStringContainsString('€', $result);
    }

    public function test_money_formatiert_betrag_ohne_symbol(): void
    {
        $result = money(100.5, false);
        $this->assertStringContainsString('100', $result);
        $this->assertStringContainsString('50', $result);
        $this->assertStringNotContainsString('€', $result);
    }

    public function test_money_null_gibt_null_zurueck(): void
    {
        $this->assertNull(money(null));
    }

    public function test_money_null_ohne_symbol_gibt_null_zurueck(): void
    {
        $this->assertNull(money(null, false));
    }

    public function test_money_null_wert(): void
    {
        $this->assertNull(money(0));
    }

    // ─── lz ─────────────────────────────────────────────────────────────────

    public function test_lz_einstellige_zahl_erhaelt_fuehrende_null(): void
    {
        $this->assertEquals('05', lz(5));
    }

    public function test_lz_zweistellige_zahl_unveraendert(): void
    {
        $this->assertEquals('12', lz(12));
    }

    public function test_lz_null_ergibt_00(): void
    {
        $this->assertEquals('00', lz(0));
    }

    // ─── format_number ──────────────────────────────────────────────────────

    public function test_format_number_null_ergibt_00(): void
    {
        $this->assertEquals('00', format_number(null));
    }

    public function test_format_number_einstellig_erhaelt_fuehrende_null(): void
    {
        $this->assertEquals('05', format_number(5));
    }

    public function test_format_number_zweistellig_unveraendert(): void
    {
        $this->assertEquals('12', format_number(12));
    }

    // ─── convertTime ────────────────────────────────────────────────────────

    public function test_convertTime_null(): void
    {
        $this->assertEquals('00:00:00', convertTime(0));
    }

    public function test_convertTime_3600_sekunden_ergibt_eine_stunde(): void
    {
        $this->assertEquals('01:00:00', convertTime(3600));
    }

    public function test_convertTime_7320_sekunden(): void
    {
        // 7320s = 2h 2min 0s
        $this->assertEquals('02:02:00', convertTime(7320));
    }

    public function test_convertTime_negativ_hat_minuszeichen(): void
    {
        $result = convertTime(-3600);
        $this->assertStringStartsWith('-', $result);
        $this->assertStringContainsString('01:00:00', $result);
    }

    // ─── percent_to_seconds ─────────────────────────────────────────────────

    public function test_percent_to_seconds_100_prozent(): void
    {
        $this->assertEquals(144000, percent_to_seconds(100));
    }

    public function test_percent_to_seconds_50_prozent(): void
    {
        $this->assertEquals(72000, percent_to_seconds(50));
    }

    public function test_percent_to_seconds_0_prozent(): void
    {
        $this->assertEquals(0, percent_to_seconds(0));
    }

    // ─── settings ───────────────────────────────────────────────────────────

    public function test_settings_liest_wert_aus_db(): void
    {
        Setting::factory()->forKey('test_key', 'test_value')->create();
        Cache::flush();

        $this->assertEquals('test_value', settings('test_key'));
    }

    public function test_settings_fallback_auf_config(): void
    {
        Cache::flush();
        config(['config.nicht_vorhanden_key' => 'fallback_wert']);

        $this->assertEquals('fallback_wert', settings('nicht_vorhanden_key'));
    }

    public function test_settings_fallback_auf_angegebene_config_datei(): void
    {
        Cache::flush();
        config(['absences.absent_test_key' => 'absent_fallback']);

        $this->assertEquals('absent_fallback', settings('absent_test_key', 'absences'));
    }

    public function test_settings_gibt_null_zurueck_wenn_nicht_vorhanden(): void
    {
        Cache::flush();

        $this->assertNull(settings('komplett_unbekannter_schluessel_xyz'));
    }

    // ─── is_holiday ─────────────────────────────────────────────────────────

    public function test_is_holiday_erkennt_feiertag(): void
    {
        $this->mockHolidayApi();
        Cache::flush();

        // 01.01.2026 = Neujahr (aus MocksExternalApis)
        $result = is_holiday(Carbon::parse('2026-01-01'));

        $this->assertNotFalse($result);
        $this->assertNotNull($result);
    }

    public function test_is_holiday_kein_feiertag(): void
    {
        $this->mockHolidayApi();
        Cache::flush();

        // 05.01.2026 = normaler Werktag
        $result = is_holiday(Carbon::parse('2026-01-05'));

        $this->assertFalse((bool)$result);
    }

    public function test_is_holiday_api_fehler_gibt_false_zurueck(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'ipty.de/*' => \Illuminate\Support\Facades\Http::response(null, 500),
        ]);
        Cache::flush();

        $result = is_holiday(Carbon::parse('2026-01-01'));

        $this->assertFalse((bool)$result);
    }

    // ─── is_ferien ──────────────────────────────────────────────────────────

    public function test_is_ferien_in_ferien(): void
    {
        $this->mockFerienApi();
        Cache::flush();

        // 10.02.2026 = Winterferien (aus MocksExternalApis: 09.02–21.02)
        $result = is_ferien(Carbon::parse('2026-02-10'));

        $this->assertNotNull($result);
    }

    public function test_is_ferien_ausserhalb_der_ferien(): void
    {
        $this->mockFerienApi();
        Cache::flush();

        // 01.03.2026 = außerhalb jeder Ferien
        $result = is_ferien(Carbon::parse('2026-03-01'));

        $this->assertNull($result);
    }

    public function test_is_ferien_api_fehler_gibt_null_zurueck(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'ferien-api.de/*' => \Illuminate\Support\Facades\Http::response(null, 500),
        ]);
        Cache::flush();

        $result = is_ferien(Carbon::parse('2026-02-10'));

        $this->assertNull($result);
    }

    // ─── workdays ───────────────────────────────────────────────────────────

    public function test_workdays_montag_bis_freitag_5_tage(): void
    {
        $this->mockHolidayApi();
        Cache::flush();

        // Mo 05.01.2026 – Fr 09.01.2026 = 5 Arbeitstage
        $this->assertEquals(5, workdays('2026-01-05', '2026-01-09'));
    }

    public function test_workdays_ignoriert_wochenende(): void
    {
        $this->mockHolidayApi();
        Cache::flush();

        // Mo 05.01.2026 – So 11.01.2026 = 5 Arbeitstage
        $this->assertEquals(5, workdays('2026-01-05', '2026-01-11'));
    }

    public function test_workdays_mit_feiertag(): void
    {
        $this->mockHolidayApi();
        Cache::flush();

        // 29.12.2025 – 02.01.2026: 01.01.2026 ist Neujahr → 4 Arbeitstage
        $this->assertEquals(4, workdays('2025-12-29', '2026-01-02'));
    }

    // ─── getHolidayCellData ──────────────────────────────────────────────────

    public function test_getHolidayCellData_wochenende_ergibt_bg_info(): void
    {
        $this->mockHolidayApi();
        $this->mockFerienApi();
        Cache::flush();

        $saturday = Carbon::parse('2026-01-10'); // Samstag
        $data = getHolidayCellData(null, $saturday);

        $this->assertEquals('bg-info', $data['class']);
    }

    public function test_getHolidayCellData_feiertag_ergibt_bg_info(): void
    {
        $this->mockHolidayApi();
        $this->mockFerienApi();
        Cache::flush();

        $neujahr = Carbon::parse('2026-01-01');
        $data = getHolidayCellData(null, $neujahr);

        $this->assertEquals('bg-info', $data['class']);
    }

    public function test_getHolidayCellData_genehmigter_urlaub(): void
    {
        $this->mockHolidayApi();
        $this->mockFerienApi();
        Cache::flush();

        $day = Carbon::parse('2026-01-05'); // normaler Montag
        $holiday = (object)['approved' => true, 'rejected' => false];
        $data = getHolidayCellData($holiday, $day);

        $this->assertStringContainsString('success', $data['class']);
        $this->assertNotEmpty($data['icon']);
    }

    public function test_getHolidayCellData_abgelehnter_urlaub(): void
    {
        $this->mockHolidayApi();
        $this->mockFerienApi();
        Cache::flush();

        $day = Carbon::parse('2026-01-05');
        $holiday = (object)['approved' => false, 'rejected' => true];
        $data = getHolidayCellData($holiday, $day);

        $this->assertStringContainsString('danger', $data['class']);
    }

    public function test_getHolidayCellData_ausstehender_urlaub(): void
    {
        $this->mockHolidayApi();
        $this->mockFerienApi();
        Cache::flush();

        $day = Carbon::parse('2026-01-05');
        $holiday = (object)['approved' => false, 'rejected' => false];
        $data = getHolidayCellData($holiday, $day);

        $this->assertStringContainsString('amber', $data['class']);
    }
}


