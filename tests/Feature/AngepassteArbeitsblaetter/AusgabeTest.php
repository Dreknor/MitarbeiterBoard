<?php

namespace Tests\Feature\AngepassteArbeitsblaetter;

use App\Models\Arbeitsblaetter\AbFassung;
use App\Models\Arbeitsblaetter\AbProfilVorlage;
use App\Models\Klasse;
use App\Services\Arbeitsblaetter\AbAusgabeService;
use App\Services\Arbeitsblaetter\AbSatzService;
use App\Services\Arbeitsblaetter\AngepasstesBlattService;
use App\Support\AbProfil;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\ArbeitsblaetterTestHilfen;

/** Ausgabe: PDF nach Profil, A3, Wiederverwendung, Sammel-PDF, Nextcloud-Pfad. */
class AusgabeTest extends TestCase
{
    use ArbeitsblaetterTestHilfen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->arbeitsblaetterEinrichten();
    }

    public function test_pdf_enthaelt_profilschrift_und_groesse(): void
    {
        $blatt = $this->blatt();
        $werte = AbProfil::normalisieren(['schrift' => 'atkinson', 'groesse' => 28, 'fett' => true]);

        $html = app(AbSatzService::class)->html($blatt, $werte, 'pdf');
        $this->assertStringContainsString('font-size: 28pt', $html);
        $this->assertStringContainsString("font-family: 'ab-atkinson'", $html);
        $this->assertStringContainsString('AtkinsonHyperlegible-Bold.ttf', $html);

        $pdf = app(AbSatzService::class)->pdf($blatt, $werte);
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString('AtkinsonHyperlegible', $pdf, 'Schrift ist eingebettet');
    }

    public function test_a3_format(): void
    {
        $blatt = $this->blatt();
        $pdf = app(AbSatzService::class)->pdf($blatt, AbProfil::normalisieren(['format' => 'A3']));

        // A3 = 841.89 x 1190.55 pt
        $this->assertMatchesRegularExpression('/MediaBox \[0(\.0+)? 0(\.0+)? 841\.8\d* 1190\.5\d*\]/', $pdf);
    }

    public function test_fassung_wird_wiederverwendet_und_bei_aenderung_neu_gesetzt(): void
    {
        $kind = $this->kindMitProfil();
        $blatt = $this->blatt();
        $ausgabe = app(AbAusgabeService::class);

        $erste = $ausgabe->fassung($blatt, $kind);
        $this->assertTrue($erste->neuGesetzt);
        Storage::disk('arbeitsblaetter')->assertExists($erste->pdf_pfad);

        $zweite = $ausgabe->fassung($blatt->fresh(), $kind);
        $this->assertSame($erste->id, $zweite->id);
        $this->assertFalse($zweite->neuGesetzt, 'gleicher Hash + gleiche Version → kein Neusatz');

        app(AngepasstesBlattService::class)->speichern($blatt, ['bausteine' => [['typ' => 'text', 'text' => 'Neu']]], $blatt->fresh()->version);
        $this->assertSame(AbFassung::VERALTET, $erste->fresh()->status, 'Änderung nach Freigabe → veraltet');

        $dritte = $ausgabe->fassung($blatt->fresh(), $kind);
        $this->assertTrue($dritte->neuGesetzt);
        $this->assertSame(AbFassung::AKTUELL, $dritte->fresh()->status);
    }

    public function test_docx_wird_erzeugt_wenn_eingeschaltet(): void
    {
        $this->abEinstellung('ab_docx_ausgabe', '1');
        $blatt = $this->blatt();
        $fassung = app(AbAusgabeService::class)->fassung($blatt, null, AbProfilVorlage::where('name', 'Groß')->first());

        $this->assertNotNull($fassung->docx_pfad);
        $this->assertStringStartsWith('PK', Storage::disk('arbeitsblaetter')->get($fassung->docx_pfad));
    }

    public function test_sammel_pdf_und_download_ueber_seite(): void
    {
        $klasse = Klasse::factory()->create(['name' => '3a']);
        $a = $this->kindMitProfil($klasse);
        $b = $this->kindMitProfil($klasse, ['format' => 'A3'], 'Groß');
        $blatt = $this->blatt();
        $this->actingAs($this->lehrkraft());

        $antwort = $this->post(route('arbeitsblaetter.ausgabe.erzeugen', $blatt), [
            'kinder' => [$a->id, $b->id],
            'aktion' => 'sammel',
        ]);
        $antwort->assertOk();
        $this->assertSame('application/pdf', $antwort->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $antwort->getContent());
        $this->assertSame(2, AbFassung::count());

        $this->get(route('arbeitsblaetter.ausgabe', $blatt))->assertOk()->assertSee($a->vorname);
    }

    public function test_nextcloud_pfad_aus_vorlage(): void
    {
        Queue::fake();
        $klasse = Klasse::factory()->create(['name' => '4b']);
        $kind = $this->kindMitProfil($klasse);
        $kind->update(['vorname' => 'Mia', 'nachname' => 'Muster']);
        $this->abEinstellung('ab_ordner_vorlage', 'AB/{klasse}/{nachname} {vorname}');
        $this->abEinstellung('ab_datei_vorlage', '{titel}');
        $blatt = $this->blatt(['titel' => 'Brüche: Teil 1']);

        $ausgabe = app(AbAusgabeService::class);
        $fassung = $ausgabe->fassung($blatt, $kind->fresh());
        $this->assertTrue($ausgabe->ablegen($fassung));

        $this->assertArrayHasKey('AB/4b/Muster Mia/Brüche- Teil 1.pdf', $this->abNextcloud->dateien);
        $this->assertNotNull($ausgabe->vorlageFehler('{gibtsnicht}'));
        $this->assertNotNull($ausgabe->vorlageFehler('../x'));
        $this->assertNull($ausgabe->vorlageFehler('{schuljahr}/{titel}'));
    }

    public function test_ohne_auswahl_und_ohne_recht(): void
    {
        $blatt = $this->blatt();
        $this->actingAs($this->lehrkraft());
        $this->post(route('arbeitsblaetter.ausgabe.erzeugen', $blatt), ['aktion' => 'einzeln'])->assertSessionHasErrors('kinder');

        $this->actingAs($this->lehrkraft(['view wiki']));
        $this->get(route('arbeitsblaetter.ausgabe', $blatt))->assertForbidden();
    }
}
