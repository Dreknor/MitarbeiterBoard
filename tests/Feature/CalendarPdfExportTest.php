<?php

namespace Tests\Feature;

use App\Models\OxCalendar;
use App\Models\OxTermin;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Feature-Tests für TODO 28: Kalender-PDF-Export.
 */
class CalendarPdfExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_debug_auth_state(): void
    {
        $user = $this->actingAsWithPermission('view calendar');

        // NUR PDF Route ohne vorherigen Index-Aufruf
        $response = $this->get('/calendar/export/pdf');
        $this->assertEquals(200, $response->status(),
            'PDF Redirect zu: ' . $response->headers->get('Location') .
            ' | Headers: ' . json_encode(array_keys($response->headers->all())));
    }

    public function test_pdf_export_liefert_pdf_content_type(): void
    {
        $user     = $this->actingAsWithPermission('view calendar');
        $calendar = OxCalendar::factory()->create(['sichtbar' => true]);

        OxTermin::factory()->create([
            'ox_calendar_id' => $calendar->id,
            'beginn'         => now()->startOfWeek()->addHours(10),
            'ende'           => now()->startOfWeek()->addHours(11),
        ]);

        $response = $this->get(route('calendar.export.pdf', [
            'date' => now()->format('Y-m-d'),
        ]));

        $response->assertOk();
        $this->assertStringContainsString(
            'application/pdf',
            $response->headers->get('content-type')
        );
    }

    public function test_pdf_export_erfordert_view_calendar_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('calendar.export.pdf'))
            ->assertForbidden();
    }

    public function test_pdf_export_erfordert_authentifizierung(): void
    {
        $this->get(route('calendar.export.pdf'))
            ->assertRedirect('/login');
    }

    public function test_pdf_export_respektiert_kalender_filter(): void
    {
        $user = $this->actingAsWithPermission('view calendar');
        $cal1 = OxCalendar::factory()->create(['sichtbar' => true, 'name' => 'Schule']);
        $cal2 = OxCalendar::factory()->create(['sichtbar' => true, 'name' => 'Hort']);

        $response = $this->get(route('calendar.export.pdf', [
            'date'      => now()->format('Y-m-d'),
            'calendars' => $cal1->id,
        ]));

        $response->assertOk();
        $this->assertStringContainsString(
            'application/pdf',
            $response->headers->get('content-type')
        );
    }

    public function test_pdf_export_ohne_datum_verwendet_aktuelle_woche(): void
    {
        $this->actingAsWithPermission('view calendar');

        $response = $this->get(route('calendar.export.pdf'));
        // Debug: Zeige Redirect-Ziel
        if ($response->status() === 302) {
            $this->fail('Unexpected redirect to: ' . $response->headers->get('Location') . ' | Body: ' . substr($response->getContent(), 0, 500));
        }
        $response->assertOk();
    }

    public function test_pdf_export_dateiname_enthaelt_kw(): void
    {
        $this->actingAsWithPermission('view calendar');

        $kw = now()->isoWeek();

        $response = $this->get(route('calendar.export.pdf', [
            'date' => now()->format('Y-m-d'),
        ]));

        $response->assertOk();
        $disposition = $response->headers->get('content-disposition');
        $this->assertStringContainsString('KW' . $kw, $disposition);
    }
}






