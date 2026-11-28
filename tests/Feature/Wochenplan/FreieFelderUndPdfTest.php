<?php

namespace Tests\Feature\Wochenplan;

use App\Models\Klasse;
use App\Models\Schueler;
use App\Models\Wochenplan\WpAufgabe;
use App\Models\Wochenplan\WpPlan;
use App\Models\Wochenplan\WpPlanFach;
use App\Services\Wochenplan\WochenplanService;
use App\Services\Wochenplan\WpPdfService;
use App\Services\Wochenplan\WpStatusService;
use Tests\TestCase;
use Tests\Traits\WochenplanTestHilfen;

/**
 * Freie Fach-Felder, freiwillige Aufgaben (*), Empfehlungen für die Freiarbeit, Kürzel der
 * täglichen Übungen und das PDF-Layout nach der Papiervorlage.
 */
class FreieFelderUndPdfTest extends TestCase
{
    use WochenplanTestHilfen;

    private Klasse $klasse;

    private WpPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->nextcloudFake(false);
        $this->klasse = Klasse::factory()->create();
        $this->plan = $this->klassenplan($this->klasse, ['gueltig_von' => '2026-11-30', 'gueltig_bis' => '2026-12-18']);
        $this->actingAs($this->paedagoge([$this->klasse]));
    }

    public function test_freies_feld_anlegen_umbenennen_und_pruefen(): void
    {
        $this->postJson(route('wp.fach.add', $this->plan), ['custom_name' => 'Advent', 'symbol' => '⭐🎄'])
            ->assertOk()
            ->assertJsonPath('plan.faecher.0.frei', true)
            ->assertJsonPath('plan.faecher.0.name', 'Advent')
            ->assertJsonPath('plan.faecher.0.eigenes_symbol', '⭐🎄');

        $feld = WpPlanFach::where('wp_plan_id', $this->plan->id)->firstOrFail();
        $this->assertNull($feld->wp_fach_id);

        // nur Symbol ist erlaubt, ganz leer nicht
        $this->putJson(route('wp.fach.update', [$this->plan, $feld]), ['custom_name' => '', 'symbol' => '🎁'])
            ->assertOk()->assertJsonPath('plan.faecher.0.eigenes_symbol', '🎁');
        $this->putJson(route('wp.fach.update', [$this->plan, $feld]), ['custom_name' => '', 'symbol' => ''])
            ->assertStatus(422);
        $this->postJson(route('wp.fach.add', $this->plan), [])->assertStatus(422);
    }

    public function test_fach_eines_anderen_plans_kann_nicht_umbenannt_werden(): void
    {
        $anderer = $this->klassenplan($this->klasse);
        $fremd = $this->fachMitAufgaben($anderer, []);

        $this->putJson(route('wp.fach.update', [$this->plan, $fremd]), ['custom_name' => 'X'])->assertNotFound();
    }

    public function test_freiwillig_freiarbeit_und_kuerzel_werden_gespeichert(): void
    {
        $fach = $this->fachMitAufgaben($this->plan, []);

        $this->postJson(route('wp.aufgabe.store', [$this->plan, $fach]), ['aufgabe' => 'Zusatz', 'freiwillig' => true])
            ->assertOk()->assertJsonPath('plan.faecher.0.aufgaben.0.freiwillig', true);
        $this->postJson(route('wp.aufgabe.store', [$this->plan, $fach]), ['aufgabe' => 'Pythagorasbrett', 'freiarbeit' => true])
            ->assertOk()->assertJsonPath('plan.faecher.0.aufgaben.1.freiarbeit', true);

        $this->postJson(route('wp.taegliche-uebungen.store', $this->plan), ['aufgabe' => "1. Woche: Olli\n2. Woche: Lesen", 'kuerzel' => '✍'])
            ->assertOk()->assertJsonPath('plan.uebungen.0.kuerzel', '✍');
    }

    public function test_pdf_folgt_der_papiervorlage(): void
    {
        app(WochenplanService::class)->uebungenSchalten($this->plan, true);
        app(WochenplanService::class)->uebungAnlegen($this->plan, 'Deutsch mit Olli', '✍');
        app(WochenplanService::class)->uebungAnlegen($this->plan, 'Matherad', '5+5');
        $fach = $this->fachMitAufgaben($this->plan, ['Erste Aufgabe', 'Zweite Aufgabe']);
        WpAufgabe::create(['wp_plan_fach_id' => $fach->id, 'aufgabe' => 'Zeichne eine Pflanze', 'freiwillig' => true, 'sort_order' => 5]);
        WpAufgabe::create(['wp_plan_fach_id' => $fach->id, 'aufgabe' => 'Tausenderkette', 'freiarbeit' => true, 'sort_order' => 6]);
        app(WochenplanService::class)->freiesFeldHinzufuegen($this->plan, null, '⭐🎄🎁');

        $html = $this->pdfHtml($this->plan->fresh());

        // Wochenraster: 3 Wochen, Kürzel als Spaltenkopf
        $this->assertStringContainsString('Woche 3', $html);
        $this->assertStringNotContainsString('Woche 4', $html);
        $this->assertStringContainsString('5+5', $html);
        // nummeriert, freiwillige Aufgabe mit * statt Nummer
        $this->assertMatchesRegularExpression('/class="nr">1\.<\/td>\s*<td>\s*Erste Aufgabe/', $html);
        $this->assertMatchesRegularExpression('/class="nr">\*<\/td>\s*<td>\s*Zeichne eine Pflanze/', $html);
        $this->assertStringContainsString('* freiwillige Aufgabe', $html);
        // Freiarbeit nur im eigenen Abschnitt
        $this->assertSame(1, substr_count($html, 'Tausenderkette'));
        $this->assertStringContainsString('Empfehlungen für die Freiarbeit', $html);
        // Emojis als Grafik, keine Fragezeichen
        $this->assertStringNotContainsString('⭐', $html);
        $this->assertStringContainsString('data:image/svg+xml;base64,', $html);

        // das PDF selbst lässt sich erzeugen
        $pdf = app(WpPdfService::class)->generate($this->plan->fresh())->output();
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_freie_felder_werden_beim_abgleich_uebernommen(): void
    {
        $service = app(WochenplanService::class);
        $kind = Schueler::factory()->create(['klasse_id' => $this->klasse->id]);
        $kp = $service->kinderplanErstellen($this->plan, $kind);

        $feld = $service->freiesFeldHinzufuegen($this->plan, 'Advent', '🎄');
        $service->aufgabeAnlegen($feld, ['aufgabe' => 'Krippenspiel üben', 'freiwillig' => true]);

        $service->abgleichen($kp->fresh());

        $kindFeld = $kp->fresh()->planFaecher()->whereNull('wp_fach_id')->firstOrFail();
        $this->assertSame('Advent', $kindFeld->custom_name);
        $this->assertSame('🎄', $kindFeld->symbol);
        $this->assertTrue((bool) $kindFeld->aufgaben()->firstOrFail()->freiwillig);

        // zweiter Abgleich legt nichts doppelt an
        $service->abgleichen($kp->fresh());
        $this->assertSame(1, $kp->fresh()->planFaecher()->whereNull('wp_fach_id')->count());
    }

    public function test_freiarbeit_zaehlt_nicht_als_aufgabe_fuer_den_status(): void
    {
        $kind = Schueler::factory()->create(['klasse_id' => $this->klasse->id]);
        $fach = $this->fachMitAufgaben($this->plan, ['Pflicht']);
        $empfehlung = WpAufgabe::create(['wp_plan_fach_id' => $fach->id, 'aufgabe' => 'Markenspiel', 'freiarbeit' => true, 'sort_order' => 2]);

        $status = app(WpStatusService::class);
        $this->assertTrue($status->aufgabeGiltFuer($fach->aufgaben()->where('freiarbeit', false)->firstOrFail(), $kind));
        $this->assertFalse($status->aufgabeGiltFuer($empfehlung, $kind));
    }

    private function pdfHtml(WpPlan $plan): string
    {
        $plan->load(['planFaecher.aufgaben', 'planFaecher.fach', 'taeglicheUebungen', 'klasse', 'formatvorlage']);
        $fv = $plan->getEffectiveFormatvorlage();

        return view('wochenplan.pdf.standard', [
            'plan' => $plan, 'formatvorlage' => $fv, 'config' => $fv->layout_config ?? [], 'schriftCss' => 'Helvetica', 'comicSans' => null,
        ])->render();
    }
}
