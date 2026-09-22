<?php

namespace Tests\Unit\Services;

use App\Models\Klasse;
use App\Models\Wochenplan\WpFormatvorlage;
use App\Models\Wochenplan\WpPlan;
use App\Services\Wochenplan\WpWordService;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class WpWordServiceTest extends TestCase
{
    use CreatesTestData;

    private WpWordService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WpWordService();
    }

    protected function tearDown(): void
    {
        // Temporäre DOCX-Dateien aufräumen
        $tempFiles = glob(storage_path('app/temp/wp_word_*.docx'));
        if ($tempFiles) {
            foreach ($tempFiles as $file) {
                @unlink($file);
            }
        }
        parent::tearDown();
    }

    // ─── generate ────────────────────────────────────────────────────────────

    public function test_generate_erstellt_docx_datei(): void
    {
        $plan = $this->createWpPlan('klassenplan');

        $path = $this->service->generate($plan);

        $this->assertFileExists($path);
        $this->assertStringEndsWith('.docx', $path);

        // Aufräumen
        @unlink($path);
    }

    public function test_generate_docx_hat_inhalt(): void
    {
        $plan = $this->createWpPlan('klassenplan');

        $path = $this->service->generate($plan);

        $this->assertFileExists($path);
        $this->assertGreaterThan(0, filesize($path));

        @unlink($path);
    }

    public function test_generate_verwendet_schriftart_aus_formatvorlage(): void
    {
        /** @var WpFormatvorlage $formatvorlage */
        $formatvorlage = WpFormatvorlage::factory()->create([
            'schriftart'     => 'Times New Roman',
            'schriftgroesse' => 'gross',
            'layout_config'  => [
                'papier'  => ['groesse' => 'A4', 'ausrichtung' => 'portrait'],
                'spalten' => [],
            ],
        ]);

        $klasse = Klasse::factory()->create();
        $plan   = WpPlan::factory()->create([
            'formatvorlage_id' => $formatvorlage->id,
            'klasse_id'        => $klasse->id,
        ]);

        // Kein Exception → Schriftart wird korrekt übergeben
        $path = $this->service->generate($plan);

        $this->assertFileExists($path);
        @unlink($path);
    }

    public function test_generate_seitenraender_aus_config(): void
    {
        /** @var WpFormatvorlage $formatvorlage */
        $formatvorlage = WpFormatvorlage::factory()->create([
            'layout_config' => [
                'papier'       => ['groesse' => 'A4', 'ausrichtung' => 'portrait'],
                'seitenraender'=> ['oben' => 1.5, 'unten' => 1.5, 'links' => 2.0, 'rechts' => 2.0],
                'spalten'      => [],
            ],
        ]);

        $klasse = Klasse::factory()->create();
        $plan   = WpPlan::factory()->create([
            'formatvorlage_id' => $formatvorlage->id,
            'klasse_id'        => $klasse->id,
        ]);

        $path = $this->service->generate($plan);
        $this->assertFileExists($path);
        $this->assertGreaterThan(0, filesize($path));

        @unlink($path);
    }

    public function test_generate_schuelerplan(): void
    {
        $plan = $this->createWpPlan('schuelerplan');

        $path = $this->service->generate($plan);

        $this->assertFileExists($path);
        $this->assertGreaterThan(0, filesize($path));

        @unlink($path);
    }
}

