<?php

namespace Tests\Feature\Vertretungsplan;

use App\Models\Klasse;
use App\Models\LessonTime;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\Setting;
use App\Models\Vertretung;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VertretungsplanImportTest extends TestCase
{
    private string $validKey;

    protected function setUp(): void
    {
        parent::setUp();

        // Http::fake() aufrufen – VertretungObserver macht POST ans ElternInfoBoard
        Http::fake();

        // Echten API-Key aus der DB lesen (Migration erstellt ihn per Str::random(25))
        $importKeySetting = \App\Models\Setting::where('setting', 'indiware_import_key')->first();
        $this->validKey   = $importKeySetting ? $importKeySetting->value : 'test-key-fallback';

        // VP-Raumintegration aktivieren (separater Eintrag mit machine-key als 'setting')
        \Illuminate\Support\Facades\DB::table('settings')->updateOrInsert(
            ['setting' => 'vp_room_integration_enabled'],
            ['module'  => 'rooms', 'setting_name' => 'VP Raumintegration', 'type' => 'boolean',
             'value'   => '1', 'created_at' => now(), 'updated_at' => now()]
        );

        // LessonTimes seeden
        $this->seedLessonTimes();
    }

    private function seedLessonTimes(): void
    {
        $times = [
            ['period' => 1, 'start' => '07:30', 'end' => '08:15', 'week' => null],
            ['period' => 2, 'start' => '08:25', 'end' => '09:10', 'week' => null],
            ['period' => 3, 'start' => '09:30', 'end' => '10:15', 'week' => null],
            ['period' => 4, 'start' => '10:25', 'end' => '11:10', 'week' => null],
            ['period' => 5, 'start' => '11:30', 'end' => '12:15', 'week' => null],
        ];
        foreach ($times as $t) {
            LessonTime::create($t);
        }
    }

    private function buildImportJson(array $aktionen, string $datum = '16.03.2026'): array
    {
        return [
            'Vertretungsplan' => [
                [
                    'Kopf' => [
                        'Datum' => $datum,
                    ],
                    'Aktionen' => $aktionen,
                ],
            ],
        ];
    }

    private function buildAktion(array $override = []): array
    {
        return array_merge([
            'Ak_Id'       => 1000,
            'Ak_Art'      => 'Änd.',
            'Ak_StundeVon' => 1,
            'Ak_StundenAnz' => 1,
            'Ak_Fach'     => 'MA',
            'Raeume'      => ['R101'],
            'VRaeume'     => ['R101'],
            'Klassen'     => ['5a'],
        ], $override);
    }

    // ─── Test 1: Gültiger Key → 200 ──────────────────────────────────────────

    public function test_import_valid_key_200(): void
    {
        $payload = $this->buildImportJson([]);

        $response = $this->putJson(
            '/api/vertretungen/' . $this->validKey . '/vp',
            $payload
        );

        $response->assertStatus(200)
            ->assertJsonPath('status', 'ok');
    }

    // ─── Test 2: Ungültiger Key → 401 ────────────────────────────────────────

    public function test_import_invalid_key_401(): void
    {
        $payload = $this->buildImportJson([]);

        $response = $this->putJson(
            '/api/vertretungen/FALSCHER_KEY/vp',
            $payload
        );

        $response->assertStatus(401);
    }

    // ─── Test 3: Import erstellt RoomBookings ─────────────────────────────────

    public function test_import_creates_room_bookings(): void
    {
        Room::factory()->create([
            'name'               => 'R101',
            'room_number'        => 'R101',
            'indiware_shortname' => 'R101',
        ]);

        $payload = $this->buildImportJson([
            $this->buildAktion(['Raeume' => ['R101'], 'VRaeume' => ['R101']]),
        ]);

        $response = $this->putJson(
            '/api/vertretungen/' . $this->validKey . '/vp',
            $payload
        );

        $response->assertStatus(200);
        $summary = $response->json('summary');
        $this->assertGreaterThanOrEqual(1, $summary['room_bookings_created']);
        $this->assertEquals(1, RoomBooking::where('source', 'indiware_vp')->count());
    }

    // ─── Test 4: show_vertretungen=false → Vertretung übersprungen, Raum trotzdem ──

    public function test_import_show_vertretungen_false(): void
    {
        Room::factory()->create([
            'name'               => 'R101',
            'room_number'        => 'R101',
            'indiware_shortname' => 'R101',
        ]);

        // Klasse mit show_vertretungen=false
        Klasse::factory()->create([
            'name'              => '5a',
            'kuerzel'           => '5a',
            'show_vertretungen' => false,
        ]);

        $payload = $this->buildImportJson([
            $this->buildAktion(['Klassen' => ['5a'], 'Raeume' => ['R101'], 'VRaeume' => ['R101']]),
        ]);

        $response = $this->putJson(
            '/api/vertretungen/' . $this->validKey . '/vp',
            $payload
        );

        $response->assertStatus(200);
        $summary = $response->json('summary');
        $this->assertGreaterThanOrEqual(1, $summary['vertretungen_skipped_hidden']);
        // Raumbuchung trotzdem erstellt
        $this->assertGreaterThanOrEqual(1, $summary['room_bookings_created']);
    }

    // ─── Test 5: Summary-Struktur enthält alle Keys ───────────────────────────

    public function test_import_summary_structure(): void
    {
        $payload  = $this->buildImportJson([]);
        $response = $this->putJson(
            '/api/vertretungen/' . $this->validKey . '/vp',
            $payload
        );

        $response->assertStatus(200);
        $summary = $response->json('summary');

        $requiredKeys = [
            'days_processed',
            'vertretungen_created',
            'room_bookings_created',
            'room_bookings_cancelled',
            'room_bookings_skipped',
            'missing_rooms',
            'room_conflicts',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $summary, "Summary muss Key '{$key}' enthalten");
        }
    }

    // ─── Test 6: Ungültiges JSON → 400 ───────────────────────────────────────

    public function test_import_malformed_json_400(): void
    {
        $response = $this->call(
            'PUT',
            '/api/vertretungen/' . $this->validKey . '/vp',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            'UNGÜLTIGES{JSON['
        );

        $response->assertStatus(400);
    }

    // ─── Test: Doppelte Ak_Id wird dedupliziert ───────────────────────────────

    public function test_duplicate_ak_ids_deduplicated(): void
    {
        Room::factory()->create([
            'name'               => 'R101',
            'room_number'        => 'R101',
            'indiware_shortname' => 'R101',
        ]);
        Klasse::factory()->create([
            'name'              => '5a',
            'kuerzel'           => '5a',
            'show_vertretungen' => true,
        ]);

        // Gleiche Aktion doppelt im JSON (Ak_Id=100, gleiche StundeVon)
        $aktion = $this->buildAktion(['Ak_Id' => 100, 'Ak_Art' => 'Änd.', 'Raeume' => ['R101'], 'VRaeume' => ['R101'], 'Klassen' => ['5a']]);

        $payload = $this->buildImportJson([$aktion, $aktion]); // doppelt!

        $response = $this->putJson(
            '/api/vertretungen/' . $this->validKey . '/vp',
            $payload
        );

        $response->assertStatus(200);
        $summary = $response->json('summary');

        // Nur 1 Aktion verarbeitet
        $this->assertEquals(1, $summary['actions_total'], 'Doppelte Ak_Id muss dedupliziert werden');
        $this->assertEquals(1, RoomBooking::where('source', 'indiware_vp')->count());
        $this->assertEquals(1, Vertretung::count());
    }

    // ─── Test: Dreifachstunde wird als Doppelstunde markiert (>= 2) ──────────

    public function test_dreifachstunde_marked_as_doppelstunde(): void
    {
        Klasse::factory()->create([
            'name'              => '5a',
            'kuerzel'           => '5a',
            'show_vertretungen' => true,
        ]);

        $payload = $this->buildImportJson([
            $this->buildAktion([
                'Ak_StundenAnz' => 3,
                'Klassen'       => ['5a'],
                'Raeume'        => [],
                'VRaeume'       => [],
            ]),
        ]);

        $response = $this->putJson(
            '/api/vertretungen/' . $this->validKey . '/vp',
            $payload
        );

        $response->assertStatus(200);
        $vertretung = Vertretung::first();
        $this->assertNotNull($vertretung);
        $this->assertTrue((bool) $vertretung->Doppelstunde, 'Ak_StundenAnz=3 muss Doppelstunde=true setzen');
    }

    // ─── Test: TODO-1 – AktionDate nicht von vorheriger Aktion geerbt ─────────

    public function test_aktionDate_not_inherited_between_aktionen(): void
    {
        Klasse::factory()->create([
            'name'              => '5a',
            'kuerzel'           => '5a',
            'show_vertretungen' => true,
        ]);
        Klasse::factory()->create([
            'name'              => '6b',
            'kuerzel'           => '6b',
            'show_vertretungen' => true,
        ]);

        // Aktion 1: hat Ak_DatumVon=12.03.2026
        $aktion1 = $this->buildAktion([
            'Ak_Id'       => 200,
            'Ak_DatumVon' => '12.03.2026',
            'Klassen'     => ['5a'],
            'Raeume'      => [],
            'VRaeume'     => [],
        ]);
        // Aktion 2: hat KEIN Ak_DatumVon → soll Kopf-Datum verwenden
        $aktion2 = $this->buildAktion([
            'Ak_Id'   => 201,
            'Klassen' => ['6b'],
            'Raeume'  => [],
            'VRaeume' => [],
        ]);

        $payload = $this->buildImportJson([$aktion1, $aktion2], '16.03.2026');

        $response = $this->putJson(
            '/api/vertretungen/' . $this->validKey . '/vp',
            $payload
        );

        $response->assertStatus(200);

        $v5a = Vertretung::whereHas('klasse', fn($q) => $q->where('name', '5a'))->first();
        $v6b = Vertretung::whereHas('klasse', fn($q) => $q->where('name', '6b'))->first();

        $this->assertNotNull($v5a);
        $this->assertNotNull($v6b);

        // Aktion 1: muss Datum 12.03.2026 haben
        $this->assertEquals('2026-03-12', Carbon::parse($v5a->date)->format('Y-m-d'));
        // Aktion 2: muss Kopf-Datum 16.03.2026 haben (NICHT 12.03.)
        $this->assertEquals('2026-03-16', Carbon::parse($v6b->date)->format('Y-m-d'));
    }

    // ─── Test: TODO-10 – Ungültiges Datum-Format → kein Crash ───────────────

    public function test_invalid_date_format_uses_kopf_datum(): void
    {
        Klasse::factory()->create([
            'name'              => '5a',
            'kuerzel'           => '5a',
            'show_vertretungen' => true,
        ]);

        $payload = $this->buildImportJson([
            $this->buildAktion([
                'Ak_DatumVon' => 'INVALID',
                'Klassen'     => ['5a'],
                'Raeume'      => [],
                'VRaeume'     => [],
            ]),
        ], '16.03.2026');

        $response = $this->putJson(
            '/api/vertretungen/' . $this->validKey . '/vp',
            $payload
        );

        // Kein 500-Fehler
        $response->assertStatus(200);
        // Aktion wurde verarbeitet mit Fallback auf Kopf-Datum
        $vertretung = Vertretung::first();
        $this->assertNotNull($vertretung);
        $this->assertEquals('2026-03-16', Carbon::parse($vertretung->date)->format('Y-m-d'));
    }

    // ─── Test: TODO-10 – Ungültige Stundennummer wird übersprungen ───────────

    public function test_invalid_stunde_skips_aktion(): void
    {
        $payload = $this->buildImportJson([
            $this->buildAktion(['Ak_StundeVon' => 99]),
        ]);

        $response = $this->putJson(
            '/api/vertretungen/' . $this->validKey . '/vp',
            $payload
        );

        // Kein Crash
        $response->assertStatus(200);
        $this->assertEquals(0, Vertretung::count());
        $this->assertEquals(0, RoomBooking::count());
    }
}









