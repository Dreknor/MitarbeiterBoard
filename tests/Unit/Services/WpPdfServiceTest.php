<?php

namespace Tests\Unit\Services;

use App\Models\Klasse;
use App\Models\Wochenplan\WpFormatvorlage;
use App\Models\Wochenplan\WpPlan;
use App\Services\Wochenplan\WpPdfService;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class WpPdfServiceTest extends TestCase
{
    use CreatesTestData;

    private WpPdfService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WpPdfService();
    }

    // ─── generate ────────────────────────────────────────────────────────────

    public function test_generate_gibt_pdf_objekt_zurueck(): void
    {
        $plan = $this->createWpPlan('klassenplan');

        $pdf = $this->service->generate($plan);

        $this->assertInstanceOf(\Barryvdh\DomPDF\PDF::class, $pdf);
    }

    public function test_generate_pdf_hat_inhalt(): void
    {
        $plan = $this->createWpPlan('klassenplan');

        $pdf    = $this->service->generate($plan);
        $output = $pdf->output();

        // PDF-Binary muss Inhalt haben und mit %PDF- beginnen
        $this->assertNotEmpty($output);
        $this->assertStringStartsWith('%PDF-', $output);
    }

    public function test_generate_verwendet_formatvorlage_schriftgroesse(): void
    {
        /** @var WpFormatvorlage $formatvorlage */
        $formatvorlage = WpFormatvorlage::factory()->create([
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

        // Kein Exception → Formatvorlage wird korrekt geladen
        $pdf = $this->service->generate($plan);
        $this->assertNotNull($pdf->output());
    }

    public function test_generate_mit_landscape_papierformat(): void
    {
        /** @var WpFormatvorlage $formatvorlage */
        $formatvorlage = WpFormatvorlage::factory()->create([
            'layout_config' => [
                'papier' => ['groesse' => 'A4', 'ausrichtung' => 'landscape'],
            ],
        ]);

        $klasse = Klasse::factory()->create();
        $plan   = WpPlan::factory()->create([
            'formatvorlage_id' => $formatvorlage->id,
            'klasse_id'        => $klasse->id,
        ]);

        $pdf    = $this->service->generate($plan);
        $output = $pdf->output();

        $this->assertNotEmpty($output);
    }

    public function test_generate_vorlage_plan(): void
    {
        $plan = $this->createWpPlan('vorlage');

        // Vorlage-Pläne haben keine Klasse – kein Exception erwartet
        $pdf    = $this->service->generate($plan);
        $output = $pdf->output();

        $this->assertNotEmpty($output);
    }
}

