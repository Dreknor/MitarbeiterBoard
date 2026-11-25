<?php

namespace Tests\Feature\Wochenplan;

use App\Models\Klasse;
use App\Models\Wochenplan\WpDatei;
use App\Models\Wochenplan\WpFach;
use App\Models\Wochenplan\WpFormatvorlage;
use App\Support\SvgSanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\WochenplanTestHilfen;

class WochenplanSicherheitTest extends TestCase
{
    use WochenplanTestHilfen;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('wochenplan');
        $this->nextcloudFake(false);
    }

    public function test_datei_eines_fremden_plans_kann_nicht_geloescht_werden(): void
    {
        $a = Klasse::factory()->create();
        $eigener = $this->klassenplan($a);
        $andererEigener = $this->klassenplan($a);
        $datei = app(\App\Services\Wochenplan\WpAblageService::class)
            ->dateiAusInhalt($andererEigener, 'blatt.pdf', '%PDF-1.4', 'application/pdf', null);

        $this->actingAs($this->paedagoge([$a]));
        // Datei gehört nicht zum Plan im Pfad → 404, nichts gelöscht
        $this->deleteJson(route('wp.dateien.destroy', [$eigener, $datei]))->assertNotFound();
        $this->assertDatabaseHas('wp_dateien', ['id' => $datei->id]);

        $this->deleteJson(route('wp.dateien.destroy', [$andererEigener, $datei]))->assertOk();
        $this->assertDatabaseMissing('wp_dateien', ['id' => $datei->id]);
    }

    public function test_arbeitsblatt_nur_mit_zugriff_auf_den_plan(): void
    {
        $a = Klasse::factory()->create();
        $b = Klasse::factory()->create();
        $plan = $this->klassenplan($a);
        $this->actingAs($this->paedagoge([$a]));

        $this->post(route('wp.dateien.store', $plan), ['files' => [UploadedFile::fake()->create('blatt.pdf', 20, 'application/pdf')]], ['Accept' => 'application/json'])
            ->assertOk();
        $datei = WpDatei::firstOrFail();
        $this->assertSame(WpDatei::LOKAL, $datei->status);

        $this->get(route('wp.dateien.show', $datei))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($this->paedagoge([$b]))->get(route('wp.dateien.show', $datei))->assertForbidden();
    }

    public function test_upload_lehnt_unzulaessige_dateien_ab(): void
    {
        $a = Klasse::factory()->create();
        $plan = $this->klassenplan($a);
        $this->actingAs($this->paedagoge([$a]));

        $this->post(route('wp.dateien.store', $plan), ['files' => [UploadedFile::fake()->create('boese.php', 1, 'application/x-php')]], ['Accept' => 'application/json'])
            ->assertStatus(422);
        $this->assertDatabaseCount('wp_dateien', 0);
    }

    public function test_svg_symbole_werden_bereinigt(): void
    {
        $sauber = SvgSanitizer::clean('<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(2)</script><circle cx="5" cy="5" r="4" fill="red" onclick="x()"/><a href="javascript:alert(3)"><rect width="1" height="1"/></a><foreignObject><div>x</div></foreignObject></svg>');

        $this->assertStringContainsString('<circle', $sauber);
        $this->assertStringNotContainsString('onload', $sauber);
        $this->assertStringNotContainsString('script', $sauber);
        $this->assertStringNotContainsString('onclick', $sauber);
        $this->assertStringNotContainsString('javascript', $sauber);
        $this->assertStringNotContainsString('foreignObject', $sauber);
        $this->assertSame('', SvgSanitizer::clean('<div onclick="x">kein svg</div>'));

        $fach = WpFach::factory()->create(['symbol_typ' => 'svg', 'symbol_wert' => '<svg><script>alert(1)</script><path d="M0 0"/></svg>']);
        $this->assertStringNotContainsString('<script', $fach->symbol_html);
    }

    public function test_fach_speichert_nur_bereinigtes_svg(): void
    {
        $this->actingAsWithPermission('manage wochenplan-faecher');
        $this->post(route('wp.faecher.store'), [
            'name' => 'Testfach SVG',
            'symbol_typ' => 'svg',
            'symbol_wert' => '<svg onload="alert(1)"><circle r="2"/></svg>',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertStringNotContainsString('onload', WpFach::where('name', 'Testfach SVG')->value('symbol_wert'));
    }

    public function test_formatvorlage_nur_mit_erlaubtem_template(): void
    {
        $this->actingAsWithPermission('manage wochenplan-formatvorlagen');
        $basis = ['name' => 'Test', 'schriftgroesse' => 'normal'];

        $this->post(route('wp.formatvorlagen.store'), $basis + ['blade_template' => 'auth.login'])->assertSessionHasErrors('blade_template');

        $fv = new WpFormatvorlage(['blade_template' => 'layouts.app']);
        $this->assertSame('wochenplan.pdf.standard', $fv->sicheresTemplate());
    }

    public function test_hilfeseite_laedt(): void
    {
        $this->actingAs($this->paedagoge([]))->get(route('wp.hilfe'))->assertOk()->assertSee('Anleitung Wochenplan');
    }
}
