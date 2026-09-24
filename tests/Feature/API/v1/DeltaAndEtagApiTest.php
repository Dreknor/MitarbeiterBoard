<?php

namespace Tests\Feature\API\v1;

use App\Models\DiagnosticArea;
use App\Models\DiagnosticDevelopmentGoal;
use App\Models\DiagnosticSession;
use App\Models\GradingStage;
use App\Models\GradingSystem;
use App\Models\PaedDiaryCategory;
use App\Models\PaedDiaryEntry;

/**
 * B8 – Delta-Abfragen (updated_since) und ETag-Caching der Kataloge.
 */
class DeltaAndEtagApiTest extends ApiTestCase
{
    /** @test */
    public function tagebuch_delta_liefert_nur_geaenderte_eintraege(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        $user = $this->actingAsTeacher(['view paed diary'], [$klasse]);

        $alt = PaedDiaryEntry::factory()->create(['klasse_id' => $klasse->id, 'user_id' => $user->id]);
        $alt->schueler()->attach($schueler->id);

        $this->travel(10)->minutes();
        $since = now()->copy();
        $this->travel(1)->minutes();

        $neu = PaedDiaryEntry::factory()->create(['klasse_id' => $klasse->id, 'user_id' => $user->id]);
        $neu->schueler()->attach($schueler->id);

        $url = self::API . "/students/{$schueler->id}/paed-diary/entries";
        $this->getJson($url)->assertOk()->assertJsonCount(2, 'data');

        $this->getJson($url . '?updated_since=' . urlencode($since->toIso8601String()))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $neu->id);

        // Zeitzone des Clients wird berücksichtigt
        $this->getJson($url . '?updated_since=' . urlencode($since->copy()->setTimezone('UTC')->toIso8601String()))
            ->assertOk()->assertJsonCount(1, 'data');

        // Änderung eines alten Eintrags erscheint im Delta
        $alt->update(['content' => 'geändert']);
        $this->getJson($url . '?updated_since=' . urlencode($since->toIso8601String()))
            ->assertOk()->assertJsonCount(2, 'data');

        $this->getJson($url . '?updated_since=gestern-irgendwann')->assertStatus(422)->assertJsonValidationErrors('updated_since');
    }

    /** @test */
    public function diagnose_delta_liefert_nur_geaenderte_sitzungen_und_ziele(): void
    {
        [$klasse, $schueler] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary', 'view diagnostics'], [$klasse]);

        DiagnosticSession::factory()->completed()->create(['schueler_id' => $schueler->id]);
        $altesZiel = DiagnosticDevelopmentGoal::factory()->create(['schueler_id' => $schueler->id]);

        $this->travel(5)->minutes();
        $since = now()->toIso8601String();
        $this->travel(1)->minutes();

        $neueSitzung = DiagnosticSession::factory()->completed()->create(['schueler_id' => $schueler->id]);
        $altesZiel->update(['status' => DiagnosticDevelopmentGoal::STATUS_ARCHIVED, 'archived_at' => now()]);

        $url = self::API . "/students/{$schueler->id}/diagnostic/history";
        $this->getJson($url)->assertOk()->assertJsonCount(2, 'data.sessions');

        // Archivierte Ziele sind im Delta enthalten, damit die App sie entfernen kann
        $this->getJson($url . '?updated_since=' . urlencode($since))
            ->assertOk()
            ->assertJsonCount(1, 'data.sessions')
            ->assertJsonPath('data.sessions.0.id', $neueSitzung->id)
            ->assertJsonCount(1, 'data.development_goals')
            ->assertJsonPath('data.development_goals.0.status', 'archived');

        [, $fremd] = $this->classWithStudent();
        $this->getJson(self::API . "/students/{$fremd->id}/diagnostic/history?updated_since=" . urlencode($since))->assertForbidden();
    }

    /** @test */
    public function kataloge_liefern_etag_und_304(): void
    {
        [$klasse] = $this->classWithStudent();
        $this->actingAsTeacher(['view paed diary', 'view diagnostics'], [$klasse]);
        PaedDiaryCategory::factory()->create(['name' => 'Sozialverhalten']);
        DiagnosticArea::factory()->create();
        $system = GradingSystem::factory()->create();
        GradingStage::create(['grading_system_id' => $system->id, 'name' => 'Stufe I', 'slug' => 's1', 'sort_order' => 1]);

        foreach (['/paed-diary/categories', '/diagnostic/areas', '/grading/stages'] as $path) {
            $first = $this->getJson(self::API . $path)->assertOk();
            $etag = $first->headers->get('ETag');
            $this->assertNotEmpty($etag, "ETag fehlt für {$path}");

            $this->withHeader('If-None-Match', $etag)->getJson(self::API . $path)
                ->assertStatus(304)
                ->assertNoContent(304);

            $this->withHeader('If-None-Match', '"veraltet"')->getJson(self::API . $path)->assertOk();
            $this->flushHeaders();
        }

        // Änderung am Katalog → neuer ETag
        $etag = $this->getJson(self::API . '/paed-diary/categories')->headers->get('ETag');
        PaedDiaryCategory::factory()->create(['name' => 'Arbeitsverhalten']);
        $this->withHeader('If-None-Match', $etag)->getJson(self::API . '/paed-diary/categories')->assertOk()->assertJsonCount(2, 'data');
    }
}
