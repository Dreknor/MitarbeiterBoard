<?php

namespace Tests\Feature\Wochenplan;

use App\Jobs\Wochenplan\WpPlanAblegen;
use App\Models\Klasse;
use App\Models\Schueler;
use App\Models\Wochenplan\WpDatei;
use App\Models\Wochenplan\WpPlan;
use App\Services\Wochenplan\WochenplanService;
use App\Services\Wochenplan\WpAblageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\WochenplanTestHilfen;

/** Nextcloud als zentraler Speicher: Ablage von PDF/Word, Arbeitsblätter, Umzug, _gelöscht, Prüfung. */
class NextcloudAblageTest extends TestCase
{
    use WochenplanTestHilfen;

    private Klasse $klasse;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('wochenplan');
        $this->nextcloudFake(true);
        $this->klasse = Klasse::factory()->create(['name' => '3a']);
    }

    private function plan(array $werte = []): WpPlan
    {
        return $this->klassenplan($this->klasse, array_merge([
            'name' => 'Herbst',
            'gueltig_von' => '2026-11-23',
            'gueltig_bis' => '2026-11-27',
        ], $werte));
    }

    public function test_ziel_aus_vorlagen(): void
    {
        $ziel = app(WpAblageService::class)->ziel($this->plan());

        $this->assertSame('2026-27/3a/2026-KW48 Herbst', $ziel['ordner']);
        $this->assertSame('Herbst', $ziel['datei']);
    }

    public function test_vorlagen_werden_geprueft(): void
    {
        $ablage = app(WpAblageService::class);
        $this->assertNull($ablage->vorlageFehler('{schuljahr}/{klasse}/{plan}'));
        $this->assertNotNull($ablage->vorlageFehler('{gibtsnicht}'));
        $this->assertNotNull($ablage->vorlageFehler('../{plan}'));
        $this->assertNotNull($ablage->vorlageFehler('a:b'));
        $this->assertNotNull($ablage->vorlageFehler('{vorname}'));
        $this->assertNull($ablage->vorlageFehler('Kinder/{nachname}', true));
        $this->assertSame('a-b/c', $ablage->einsetzen('{plan}/c', ['plan' => 'a:b']));
    }

    public function test_plan_wird_als_pdf_und_word_abgelegt(): void
    {
        $plan = $this->plan();
        $this->fachMitAufgaben($plan, ['Lesen']);

        $ok = app(WpAblageService::class)->ablegen($plan);
        $this->assertTrue($ok, (string) $plan->fresh()->ablage_fehler);

        $this->assertContains('2026-27/3a/2026-KW48 Herbst/Herbst.pdf', $this->nextcloud->pfade());
        $this->assertContains('2026-27/3a/2026-KW48 Herbst/Herbst.docx', $this->nextcloud->pfade());
        $this->assertStringStartsWith('%PDF', $this->nextcloud->dateien['2026-27/3a/2026-KW48 Herbst/Herbst.pdf']);
        $this->assertSame('abgelegt', $plan->fresh()->ablage_status);
    }

    public function test_aenderung_fordert_verzoegerte_ablage_an(): void
    {
        Queue::fake();
        $plan = $this->plan();
        $this->actingAs($this->paedagoge([$this->klasse]));

        $this->putJson(route('wp.update', $plan), ['name' => 'Neu'])->assertOk();

        Queue::assertPushed(WpPlanAblegen::class, fn ($job) => $job->planId === $plan->id && $job->delay !== null);
        $this->assertSame('ausstehend', $plan->fresh()->ablage_status);
    }

    public function test_aeltere_anforderung_wird_uebersprungen(): void
    {
        $plan = $this->plan();
        $plan->update(['ablage_angefordert_at' => now()->addMinute()]);

        (new WpPlanAblegen($plan->id, now()->subMinute()->toIso8601String()))->handle(app(WpAblageService::class));

        $this->assertSame([], $this->nextcloud->pfade());
    }

    public function test_umbenennen_verschiebt_ordner_und_dateien(): void
    {
        $plan = $this->plan();
        $ablage = app(WpAblageService::class);
        $ablage->ablegen($plan);
        $datei = $ablage->dateiAusInhalt($plan->fresh(), 'Blatt.pdf', '%PDF-blatt', 'application/pdf', null);
        $this->assertSame(WpDatei::ABGELEGT, $datei->fresh()->status);
        $this->assertSame('2026-27/3a/2026-KW48 Herbst/Arbeitsblätter/Blatt.pdf', $datei->fresh()->nextcloud_pfad);

        $plan->update(['name' => 'Winter']);
        $ablage->ablegen($plan->fresh());

        $pfade = $this->nextcloud->pfade();
        $this->assertContains('2026-27/3a/2026-KW48 Winter/Winter.pdf', $pfade);
        $this->assertContains('2026-27/3a/2026-KW48 Winter/Arbeitsblätter/Blatt.pdf', $pfade);
        $this->assertNotContains('2026-27/3a/2026-KW48 Herbst/Herbst.pdf', $pfade);
        $this->assertSame('2026-27/3a/2026-KW48 Winter/Arbeitsblätter/Blatt.pdf', $datei->fresh()->nextcloud_pfad);
        $this->assertSame('%PDF-blatt', $ablage->dateiInhalt($datei->fresh()));
    }

    public function test_kinderplan_liegt_im_ordner_des_klassenplans(): void
    {
        $plan = $this->plan();
        $kind = Schueler::factory()->create(['klasse_id' => $this->klasse->id, 'vorname' => 'Mia', 'nachname' => 'Muster']);
        $ablage = app(WpAblageService::class);
        $ablage->ablegen($plan);
        $kp = app(WochenplanService::class)->kinderplanErstellen($plan->fresh(), $kind);

        $ablage->ablegen($kp->fresh());

        $this->assertContains('2026-27/3a/2026-KW48 Herbst/Kinderpläne/Muster, Mia.pdf', $this->nextcloud->pfade());
        $this->assertContains('2026-27/3a/2026-KW48 Herbst/Kinderpläne/Muster, Mia.docx', $this->nextcloud->pfade());
    }

    public function test_geloeschter_plan_wandert_nach_geloescht(): void
    {
        $plan = $this->plan();
        app(WpAblageService::class)->ablegen($plan);
        $this->actingAs($this->paedagoge([$this->klasse]));

        $this->delete(route('wp.destroy', $plan))->assertRedirect();

        $pfade = $this->nextcloud->pfade();
        $this->assertEmpty(array_filter($pfade, fn ($p) => str_starts_with($p, '2026-27/')));
        $this->assertNotEmpty(array_filter($pfade, fn ($p) => str_starts_with($p, '_gelöscht/')));
    }

    public function test_upload_ueber_den_editor_und_abruf(): void
    {
        $plan = $this->plan();
        $this->actingAs($this->paedagoge([$this->klasse]));

        $this->post(route('wp.dateien.store', $plan), ['files' => [UploadedFile::fake()->createWithContent('Blatt 1.pdf', '%PDF-inhalt')]], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('plan.dateien.0.status', 'abgelegt');

        $datei = WpDatei::firstOrFail();
        $this->assertNull($datei->lokaler_pfad, 'Zwischenspeicher nach dem Upload geleert');
        $this->assertSame('%PDF-inhalt', $this->get(route('wp.dateien.show', $datei))->assertOk()->getContent());
    }

    public function test_fehlgeschlagener_upload_bleibt_im_zwischenspeicher(): void
    {
        $this->nextcloud->fehlschlagen = true;
        $plan = $this->plan();

        $datei = app(WpAblageService::class)->dateiAusInhalt($plan, 'Blatt.pdf', '%PDF', 'application/pdf', null);

        $datei->refresh();
        // Fehlversuch: Wiederholung ist eingeplant (bzw. nach dem letzten Versuch „fehler“)
        $this->assertContains($datei->status, [WpDatei::FEHLER, WpDatei::AUSSTEHEND]);
        $this->assertNotNull($datei->lokaler_pfad);
        $this->assertSame('%PDF', app(WpAblageService::class)->dateiInhalt($datei));

        $this->nextcloud->fehlschlagen = false;
        app(WpAblageService::class)->erneutVersuchen($plan);
        $this->assertSame(WpDatei::ABGELEGT, $datei->fresh()->status);
    }

    public function test_kinderplan_nutzt_arbeitsblaetter_des_klassenplans(): void
    {
        $plan = $this->plan();
        $kind = Schueler::factory()->create(['klasse_id' => $this->klasse->id]);
        $kp = app(WochenplanService::class)->kinderplanErstellen($plan, $kind);
        app(WpAblageService::class)->dateiAusInhalt($plan, 'Gemeinsam.pdf', '%PDF', 'application/pdf', null);

        $dateien = $kp->fresh()->alleDateien();
        $this->assertCount(1, $dateien);
        $this->assertTrue((bool) $dateien->first()->vom_klassenplan);

        // Zugriff über den Kinderplan
        $lehrer = $this->paedagoge([$this->klasse]);
        $this->actingAs($lehrer)->get(route('wp.dateien.show', $dateien->first()))->assertOk();
    }

    public function test_naechtliche_pruefung_markiert_fehlende_dateien(): void
    {
        $plan = $this->plan();
        $datei = app(WpAblageService::class)->dateiAusInhalt($plan, 'Blatt.pdf', '%PDF', 'application/pdf', null);
        $this->nextcloud->loeschen($datei->fresh()->nextcloud_pfad);

        $ergebnis = app(WpAblageService::class)->dateienPruefen();

        $this->assertSame(1, $ergebnis['fehlt']);
        $this->assertSame(WpDatei::FEHLT, $datei->fresh()->status);
    }

    public function test_verknuepfte_nextcloud_datei_bleibt_beim_entfernen_liegen(): void
    {
        $plan = $this->plan();
        $this->nextcloud->hochladen('%PDF-material', 'Material/Bruchrechnen.pdf');
        $this->actingAs($this->paedagoge([$this->klasse]));

        $this->getJson(route('wp.nextcloud.liste', [$plan, 'pfad' => 'Material']))->assertOk()
            ->assertJsonPath('eintraege.0.name', 'Bruchrechnen.pdf');
        $this->postJson(route('wp.nextcloud.verknuepfen', $plan), ['pfade' => ['Material/Bruchrechnen.pdf']])->assertOk()
            ->assertJsonPath('plan.dateien.0.name', 'Bruchrechnen.pdf');

        $datei = WpDatei::firstOrFail();
        $this->deleteJson(route('wp.dateien.destroy', [$plan, $datei]))->assertOk();
        $this->assertContains('Material/Bruchrechnen.pdf', $this->nextcloud->pfade());
    }

    public function test_einstellungsseite_speichert_und_prueft_vorlagen(): void
    {
        $this->actingAsWithPermission('edit settings');

        $this->get(route('wp.einstellungen'))->assertOk()->assertSee('Nextcloud-Ablage');

        $gueltig = [
            'wochenplan_tagebuch_anbindung' => '1',
            'wochenplan_nextcloud_aktiv' => '1',
            'wochenplan_nextcloud_basisordner' => '/Schule/Wochenpläne',
            'wochenplan_ordner_vorlage' => '{klasse}/{jahr}-KW{kw}',
            'wochenplan_datei_vorlage' => '{plan}',
            'wochenplan_kinderplan_vorlage' => '{nachname}',
        ];
        $this->post(route('wp.einstellungen.update'), array_merge($gueltig, ['wochenplan_ordner_vorlage' => '{falsch}']))
            ->assertSessionHasErrors('wochenplan_ordner_vorlage');
        $this->post(route('wp.einstellungen.update'), $gueltig)->assertRedirect(route('wp.einstellungen'));

        $this->assertSame('{klasse}/{jahr}-KW{kw}', settings('wochenplan_ordner_vorlage'));
        $this->assertSame('1', (string) settings('wochenplan_tagebuch_anbindung'));

        $this->postJson(route('wp.einstellungen.vorschau'), $gueltig)->assertOk()
            ->assertJsonFragment(['Schule/Wochenpläne/3a/'.now()->isoWeekYear().'-KW'.str_pad((string) now()->isoWeek(), 2, '0', STR_PAD_LEFT).'/Wochenplan Herbst.pdf']);
    }

    public function test_ohne_aktive_ablage_bleiben_dateien_lokal(): void
    {
        $this->nextcloud->aktiv = false;
        $plan = $this->plan();

        $datei = app(WpAblageService::class)->dateiAusInhalt($plan, 'Blatt.pdf', '%PDF', 'application/pdf', null);

        $this->assertSame(WpDatei::LOKAL, $datei->status);
        $this->assertSame([], $this->nextcloud->pfade());
    }
}
