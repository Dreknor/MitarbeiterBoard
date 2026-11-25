<?php

namespace Tests\Feature\Wochenplan;

use App\Models\Klasse;
use App\Models\Schueler;
use App\Models\Wochenplan\WpBaustein;
use App\Models\Wochenplan\WpFach;
use App\Services\Wochenplan\WochenplanService;
use App\Support\QrCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\Traits\WochenplanTestHilfen;

/** Bibliothek, Sammel-Export, QR-Codes, Altsystem-Befehl, Vorlagen. */
class WochenplanErweiterungenTest extends TestCase
{
    use WochenplanTestHilfen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->nextcloudFake(false);
    }

    public function test_aufgaben_bibliothek(): void
    {
        $klasse = Klasse::factory()->create(['name' => '3b']);
        $plan = $this->klassenplan($klasse);
        $fach = $this->fachMitAufgaben($plan, [['aufgabe' => 'Einmaleins üben', 'dauer' => '15 min']], 'Mathe');
        $user = $this->paedagoge([$klasse]);
        $this->actingAs($user);

        $this->postJson(route('wp.aufgabe.bibliothek', [$plan, $fach->aufgaben->first()]))->assertOk();
        $this->postJson(route('wp.aufgabe.bibliothek', [$plan, $fach->aufgaben->first()]))->assertOk()
            ->assertJsonPath('meldung', 'Diese Aufgabe ist bereits in der Bibliothek.');

        $baustein = WpBaustein::firstOrFail();
        $this->assertSame(3, $baustein->klassenstufe);
        $this->assertSame($fach->wp_fach_id, $baustein->wp_fach_id);

        $this->getJson(route('wp.bibliothek.index', ['q' => 'Einmaleins', 'klassenstufe' => 3]))->assertOk()
            ->assertJsonPath('bausteine.0.aufgabe', 'Einmaleins üben');
        $this->getJson(route('wp.bibliothek.index', ['klassenstufe' => 4]))->assertOk()->assertJsonCount(0, 'bausteine');
        $this->get(route('wp.bibliothek.index'))->assertOk()->assertSee('Aufgaben-Bibliothek');

        $this->actingAs($this->paedagoge([]))->deleteJson(route('wp.bibliothek.destroy', $baustein))->assertForbidden();
        $this->actingAs($user)->deleteJson(route('wp.bibliothek.destroy', $baustein))->assertOk();
    }

    public function test_sammel_pdf_und_zip_der_kinderplaene(): void
    {
        $klasse = Klasse::factory()->create();
        $plan = $this->klassenplan($klasse);
        $this->fachMitAufgaben($plan, ['Lesen']);
        $service = app(WochenplanService::class);
        foreach (Schueler::factory()->count(2)->create(['klasse_id' => $klasse->id]) as $kind) {
            $service->kinderplanErstellen($plan, $kind);
        }
        $this->actingAs($this->paedagoge([$klasse]));

        $pdf = $this->get(route('wp.export.kinderplaene.pdf', $plan))->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));

        $zip = $this->get(route('wp.export.kinderplaene.zip', $plan))->assertOk();
        $pfad = $zip->baseResponse->getFile()->getPathname();
        $archiv = new \ZipArchive();
        $archiv->open($pfad);
        $this->assertSame(4, $archiv->numFiles, 'je Kind PDF und Word');
        $archiv->close();
    }

    public function test_links_erscheinen_als_qr_code_im_pdf_template(): void
    {
        $this->assertStringContainsString('<svg', QrCode::svg('https://example.org'));

        $klasse = Klasse::factory()->create();
        $plan = $this->klassenplan($klasse);
        $this->fachMitAufgaben($plan, [['aufgabe' => 'Video ansehen', 'link' => 'https://example.org/video']]);
        $plan->load('planFaecher.aufgaben', 'planFaecher.fach', 'taeglicheUebungen');
        $fv = $plan->getEffectiveFormatvorlage();

        $html = view('wochenplan.pdf.standard', ['plan' => $plan, 'formatvorlage' => $fv, 'config' => $fv->layout_config ?? []])->render();
        $this->assertStringContainsString('data:image/svg+xml;base64,', $html);
        $this->assertStringContainsString('example.org/video', $html);
    }

    public function test_plan_aus_vorlage_und_als_vorlage_speichern(): void
    {
        $klasse = Klasse::factory()->create();
        $plan = $this->klassenplan($klasse);
        $this->fachMitAufgaben($plan, ['A', 'B']);
        $this->actingAs($this->paedagoge([$klasse]));

        $this->post(route('wp.vorlagen.speichern', $plan), ['vorlage_name' => 'Meine Vorlage'])->assertRedirect();
        $vorlage = \App\Models\Wochenplan\WpPlan::vorlagen()->where('vorlage_name', 'Meine Vorlage')->firstOrFail();
        $this->assertNull($vorlage->klasse_id);
        $this->assertSame(2, $vorlage->aufgaben()->count());

        $this->post(route('wp.store'), [
            'name' => 'Aus Vorlage', 'klasse_id' => $klasse->id, 'gueltig_von' => '2026-11-30', 'gueltig_bis' => '2026-12-04',
            'selbsteinschaetzung' => 0, 'vorlage_id' => $vorlage->id,
        ])->assertRedirect();
        $neu = \App\Models\Wochenplan\WpPlan::where('name', 'Aus Vorlage')->firstOrFail();
        $this->assertFalse($neu->is_vorlage);
        $this->assertSame(2, $neu->aufgaben()->count());
        $this->get(route('wp.create', ['vorlage' => $vorlage->id]))->assertOk()->assertSee('Meine Vorlage');
    }

    public function test_altsystem_entfernen_nur_mit_ausfuehren(): void
    {
        $this->assertTrue(Schema::hasTable('wochenplaene'));
        DB::table('wochenplaene')->insert(['name' => 'Alt', 'gueltig_ab' => '2021-01-01', 'gueltig_bis' => '2021-01-08', 'group_id' => \App\Models\Group::factory()->create()->id, 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('wochenplan:alt-entfernen')->assertSuccessful();
        $this->assertTrue(Schema::hasTable('wochenplaene'), 'Lesemodus löscht nichts');

        $this->artisan('wochenplan:alt-entfernen', ['--ausfuehren' => true, '--no-interaction' => true])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('wochenplaene'));
        $this->assertFalse(Schema::hasTable('wprows'));
        $this->assertFalse(Schema::hasTable('wp_tasks'));
        $this->assertFalse(Schema::hasColumn('groups', 'hasWochenplan'));
    }

    public function test_uebersicht_filter(): void
    {
        $klasse = Klasse::factory()->create();
        $this->klassenplan($klasse, ['name' => 'Diese Woche Plan']);
        $this->klassenplan($klasse, ['name' => 'Alter Plan', 'gueltig_von' => now()->subWeeks(5)->startOfWeek(), 'gueltig_bis' => now()->subWeeks(5)->startOfWeek()->addDays(4)]);
        $this->actingAs($this->paedagoge([$klasse]));

        $this->get(route('wp.index', ['zeitraum' => 'aktuell']))->assertOk()->assertSee('Diese Woche Plan')->assertDontSee('Alter Plan');
        $this->get(route('wp.index', ['zeitraum' => 'vergangen']))->assertOk()->assertSee('Alter Plan')->assertDontSee('Diese Woche Plan');
        $this->get(route('wp.index', ['q' => 'Alter']))->assertOk()->assertSee('Alter Plan')->assertDontSee('Diese Woche Plan');
    }

    public function test_seiten_laden(): void
    {
        $klasse = Klasse::factory()->create();
        $plan = $this->klassenplan($klasse);
        Schueler::factory()->create(['klasse_id' => $klasse->id]);
        $this->actingAs($this->paedagoge([$klasse], ['create wochenplan', 'manage wochenplan-faecher', 'manage wochenplan-formatvorlagen']));

        $this->get(route('wp.create'))->assertOk();
        $this->get(route('wp.schuelerplan.create', $plan))->assertOk();
        $this->get(route('wp.vorlagen.index'))->assertOk();
        $this->get(route('wp.faecher.index'))->assertOk();
        $this->get(route('wp.formatvorlagen.index'))->assertOk();
        $this->get(route('wp.formatvorlagen.create'))->assertOk();
        $this->get(route('wp.export.vorschau', $plan))->assertOk();
        $this->get(route('wp.export.word', $plan))->assertOk();
    }

    public function test_artisan_befehle(): void
    {
        $this->artisan('wochenplan:nextcloud-umzug', ['--dry-run' => true])->assertSuccessful();
        $this->artisan('wochenplan:dateien-pruefen')->assertSuccessful();
        // Ablage aus → neu ablegen nicht möglich
        $this->artisan('wochenplan:neu-ablegen')->assertFailed();

        $this->nextcloudFake(true);
        $this->klassenplan(Klasse::factory()->create());
        \Illuminate\Support\Facades\Queue::fake();
        $this->artisan('wochenplan:neu-ablegen')->assertSuccessful();
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\Wochenplan\WpPlanAblegen::class);
    }

    public function test_pruefbefehl_meldet_fehlende_klassenzuordnung(): void
    {
        $ohne = $this->paedagoge([]);
        $this->artisan('wochenplan:pruefen')->expectsOutputToContain('ohne Klasse')->assertFailed();

        $ohne->paed_klassen()->attach(Klasse::factory()->create()->id);
        $this->artisan('wochenplan:pruefen')
            ->expectsOutputToContain('Alle Personen mit Wochenplan-Recht sind mindestens einer Klasse zugeordnet')
            ->assertSuccessful();

        // Verbindungstest ohne aktive Ablage schlägt fehl
        $this->artisan('wochenplan:pruefen', ['--verbindung' => true])->assertFailed();
        $this->nextcloudFake(true);
        $this->artisan('wochenplan:pruefen', ['--verbindung' => true])->assertSuccessful();
    }
}
