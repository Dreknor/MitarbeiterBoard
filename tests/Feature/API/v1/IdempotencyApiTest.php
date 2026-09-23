<?php

namespace Tests\Feature\API\v1;

use App\Models\ApiIdempotencyKey;
use App\Models\DiagnosticArea;
use App\Models\DiagnosticDevelopmentGoal;
use App\Models\Klasse;
use App\Models\PaedDiaryEntry;
use App\Models\Schueler;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * B3 – Idempotenz für schreibende API-Aufrufe (Header Idempotency-Key).
 */
class IdempotencyApiTest extends ApiTestCase
{
    private Klasse $klasse;
    private Schueler $schueler;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->klasse, $this->schueler] = $this->classWithStudent();
        $this->user = $this->actingAsTeacher(['view paed diary', 'view diagnostics'], [$this->klasse]);
    }

    private function entryPayload(array $overrides = []): array
    {
        return array_merge([
            'schueler_id' => $this->schueler->id,
            'entry_date' => '2026-09-23',
            'content' => 'Hat heute konzentriert gearbeitet.',
        ], $overrides);
    }

    /** @test */
    public function doppelter_post_mit_gleichem_key_legt_nur_einen_eintrag_an(): void
    {
        $key = (string) Str::uuid();

        $first = $this->withHeader('Idempotency-Key', $key)
            ->postJson(self::API . '/paed-diary/entries', $this->entryPayload())
            ->assertCreated()
            ->assertHeaderMissing('Idempotent-Replayed');

        $second = $this->withHeader('Idempotency-Key', $key)
            ->postJson(self::API . '/paed-diary/entries', $this->entryPayload())
            ->assertCreated()
            ->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame($first->getContent(), $second->getContent());
        $this->assertDatabaseCount('paed_diary_entries', 1);
        $this->assertDatabaseHas('api_idempotency_keys', ['user_id' => $this->user->id, 'key' => $key, 'response_status' => 201]);
    }

    /** @test */
    public function reihenfolge_der_json_felder_spielt_keine_rolle(): void
    {
        $key = (string) Str::uuid();
        $payload = $this->entryPayload();

        $this->withHeader('Idempotency-Key', $key)->postJson(self::API . '/paed-diary/entries', $payload)->assertCreated();
        $this->withHeader('Idempotency-Key', $key)->postJson(self::API . '/paed-diary/entries', array_reverse($payload, true))
            ->assertCreated()->assertHeader('Idempotent-Replayed', 'true');

        $this->assertDatabaseCount('paed_diary_entries', 1);
    }

    /** @test */
    public function gleicher_key_mit_anderen_daten_liefert_422(): void
    {
        $key = (string) Str::uuid();

        $this->withHeader('Idempotency-Key', $key)->postJson(self::API . '/paed-diary/entries', $this->entryPayload())->assertCreated();

        $this->withHeader('Idempotency-Key', $key)
            ->postJson(self::API . '/paed-diary/entries', $this->entryPayload(['content' => 'Anderer Text']))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Idempotency-Key wurde mit anderen Daten verwendet.');

        $this->assertDatabaseCount('paed_diary_entries', 1);
    }

    /** @test */
    public function laufende_anfrage_mit_gleichem_key_liefert_409(): void
    {
        $key = (string) Str::uuid();
        $lock = Cache::lock('api_idempotency:' . $this->user->id . ':' . $key, 60);
        $this->assertTrue($lock->get());

        $this->withHeader('Idempotency-Key', $key)
            ->postJson(self::API . '/paed-diary/entries', $this->entryPayload())
            ->assertStatus(409)
            ->assertJsonStructure(['message']);

        $lock->release();
        $this->assertDatabaseCount('paed_diary_entries', 0);
    }

    /** @test */
    public function ohne_header_verhalten_wie_bisher(): void
    {
        $this->postJson(self::API . '/paed-diary/entries', $this->entryPayload())->assertCreated();
        $this->postJson(self::API . '/paed-diary/entries', $this->entryPayload())->assertCreated();

        $this->assertDatabaseCount('paed_diary_entries', 2);
        $this->assertDatabaseCount('api_idempotency_keys', 0);
    }

    /** @test */
    public function diagnose_post_mit_zielen_legt_ziele_nur_einmal_an(): void
    {
        $area = DiagnosticArea::factory()->create();
        $key = (string) Str::uuid();
        $payload = [
            'schueler_id' => $this->schueler->id,
            'area_id' => $area->id,
            'goals' => [['title' => 'Zehnerübergang sicher beherrschen'], ['title' => 'Einmaleins der 7']],
        ];

        $this->withHeader('Idempotency-Key', $key)->postJson(self::API . '/diagnostic/sessions', $payload)->assertCreated();
        $this->withHeader('Idempotency-Key', $key)->postJson(self::API . '/diagnostic/sessions', $payload)
            ->assertCreated()->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame(2, DiagnosticDevelopmentGoal::count());
    }

    /** @test */
    public function fehlerantworten_werden_nicht_gespeichert(): void
    {
        $key = (string) Str::uuid();

        $this->withHeader('Idempotency-Key', $key)
            ->postJson(self::API . '/paed-diary/entries', $this->entryPayload(['content' => '']))
            ->assertStatus(422);
        $this->assertDatabaseCount('api_idempotency_keys', 0);

        // Nach Korrektur funktioniert derselbe Key
        $this->withHeader('Idempotency-Key', $key)->postJson(self::API . '/paed-diary/entries', $this->entryPayload())->assertCreated();
    }

    /** @test */
    public function delete_wird_ebenfalls_wiederholt(): void
    {
        $entry = PaedDiaryEntry::factory()->create(['klasse_id' => $this->klasse->id, 'user_id' => $this->user->id]);
        $entry->schueler()->attach($this->schueler->id);
        $key = (string) Str::uuid();

        $this->withHeader('Idempotency-Key', $key)->deleteJson(self::API . "/paed-diary/entries/{$entry->id}")->assertNoContent();
        // Ohne Idempotenz wäre das ein 404 – mit Key die gespeicherte 204-Antwort
        $this->withHeader('Idempotency-Key', $key)->deleteJson(self::API . "/paed-diary/entries/{$entry->id}")
            ->assertNoContent()->assertHeader('Idempotent-Replayed', 'true');
    }

    /** @test */
    public function keys_sind_pro_benutzer_getrennt(): void
    {
        $key = (string) Str::uuid();
        $this->withHeader('Idempotency-Key', $key)->postJson(self::API . '/paed-diary/entries', $this->entryPayload())->assertCreated();

        $this->actingAsTeacher(['view paed diary'], [$this->klasse]);
        $this->withHeader('Idempotency-Key', $key)->postJson(self::API . '/paed-diary/entries', $this->entryPayload())
            ->assertCreated()->assertHeaderMissing('Idempotent-Replayed');

        $this->assertDatabaseCount('paed_diary_entries', 2);
    }

    /** @test */
    public function ungueltiger_key_liefert_422(): void
    {
        $this->withHeader('Idempotency-Key', 'kein-uuid')
            ->postJson(self::API . '/paed-diary/entries', $this->entryPayload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('Idempotency-Key');
    }

    /** @test */
    public function fremde_klasse_bleibt_403_und_wird_nicht_gespeichert(): void
    {
        [, $fremd] = $this->classWithStudent();

        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson(self::API . '/paed-diary/entries', $this->entryPayload(['schueler_id' => $fremd->id]))
            ->assertForbidden();

        $this->assertDatabaseCount('api_idempotency_keys', 0);
    }

    /** @test */
    public function prune_command_loescht_keys_nach_48_stunden(): void
    {
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson(self::API . '/paed-diary/entries', $this->entryPayload())->assertCreated();

        $this->travel(47)->hours();
        $this->artisan('paed-app:prune-idempotency')->assertSuccessful();
        $this->assertSame(1, ApiIdempotencyKey::count());

        $this->travel(2)->hours();
        $this->artisan('paed-app:prune-idempotency')->assertSuccessful();
        $this->assertSame(0, ApiIdempotencyKey::count());
    }
}
