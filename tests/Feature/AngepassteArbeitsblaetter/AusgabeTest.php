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
        $this->assertMatchesRegularExpression("/font-family: 'ab-atkinson-[0-9a-f]{8}'/", $html);
        $this->assertStringContainsString('AtkinsonHyperlegible-Bold.ttf', $html);

        $pdf = app(AbSatzService::class)->pdf($blatt, $werte);
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString('AtkinsonHyperlegible', $pdf, 'Schrift ist eingebettet');
    }

    public function test_arial_und_verdana_mit_ersatzschrift(): void
    {
        config(['arbeitsblaetter.schriften_pfad' => sys_get_temp_dir().'/ab-keine-schriften-'.uniqid()]);
        $blatt = $this->blatt();
        $satz = app(AbSatzService::class);

        $this->assertTrue(AbSatzService::nutztErsatz('verdana'));
        $this->assertSame('Verdana (Ersatz: DejaVu Sans)', AbSatzService::schriftLabel('verdana'));
        $this->assertSame('Arial (Ersatz: Liberation Sans)', AbSatzService::schriftLabel('arial'));

        $html = $satz->html($blatt, AbProfil::normalisieren(['schrift' => 'arial']), 'pdf');
        $this->assertMatchesRegularExpression("/font-family: 'ab-arial-[0-9a-f]{8}', 'Arial'/", $html);
        $this->assertStringContainsString('LiberationSans-Regular.ttf', $html);
        $this->assertStringContainsString('LiberationSans', $satz->pdf($blatt, AbProfil::normalisieren(['schrift' => 'arial'])));
        $this->assertStringContainsString('DejaVuSans', $satz->pdf($blatt, AbProfil::normalisieren(['schrift' => 'verdana'])));
        $this->assertSame('Verdana 20 pt · A4', AbProfil::kurz(['schrift' => 'verdana']));

        // Word nutzt den echten Schriftnamen
        $docx = $satz->docx($blatt, AbProfil::normalisieren(['schrift' => 'verdana']));
        $zip = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($zip, $docx);
        $archiv = new \ZipArchive();
        $archiv->open($zip);
        $this->assertStringContainsString('Verdana', $archiv->getFromName('word/styles.xml'));
        $archiv->close();
        unlink($zip);

        // Ersatzschrift ist kein Fehler im Prüfbefehl
        $this->artisan('arbeitsblaetter:pruefen')->expectsOutputToContain('Ersatzschrift für Arial → Liberation Sans')->assertSuccessful();
    }

    public function test_lizenzierte_schriften_der_schule_werden_genutzt(): void
    {
        $ordner = sys_get_temp_dir().'/ab-schriften-'.uniqid();
        mkdir($ordner);
        // Stellvertretend: Atkinson-Dateien unter den Windows-Dateinamen ablegen
        copy(resource_path('fonts/arbeitsblaetter/AtkinsonHyperlegible-Regular.ttf'), $ordner.'/VERDANA.TTF');
        copy(resource_path('fonts/arbeitsblaetter/AtkinsonHyperlegible-Bold.ttf'), $ordner.'/verdanab.ttf');
        config(['arbeitsblaetter.schriften_pfad' => $ordner]);

        config(['arbeitsblaetter.schriften_pfad' => $ordner.'-leer']);
        app(AbSatzService::class)->pdf($this->blatt(), AbProfil::normalisieren(['schrift' => 'verdana', 'fett' => true])); // Ersatz merkt sich DomPDF
        config(['arbeitsblaetter.schriften_pfad' => $ordner]);
        $this->assertFalse(AbSatzService::nutztErsatz('verdana'));
        $this->assertSame('Verdana', AbSatzService::schriftLabel('verdana'));
        $this->assertSame($ordner.'/VERDANA.TTF', AbSatzService::schriftPfad('verdana'));
        $this->assertStringContainsString('verdanab.ttf', app(AbSatzService::class)->html($this->blatt(), AbProfil::normalisieren(['schrift' => 'verdana']), 'pdf'));
        // Ordner außerhalb des Projekts: DomPDF darf die Datei trotzdem lesen (chroot) und bettet sie ein –
        // auch wenn vorher (z. B. in einem früheren Test oder vor dem Laden) die Ersatzschrift genutzt wurde
        $this->assertMatchesRegularExpression('/BaseFont\s*\/[A-Z]+\+AtkinsonHyperlegible-Bold/', app(AbSatzService::class)->pdf($this->blatt(), AbProfil::normalisieren(['schrift' => 'verdana', 'fett' => true])));

        $this->actingAs($this->lehrkraft());
        $this->get(route('arbeitsblaetter.schrift', ['verdana', 'normal']))->assertOk();

        array_map('unlink', glob($ordner.'/*'));
        rmdir($ordner);
    }

    public function test_pdf_hat_seitenrand_und_abbildung(): void
    {
        $blatt = $this->blatt([], []);
        $media = app(AngepasstesBlattService::class)->abbildungAusInhalt($blatt, $this->bildInhalt(200, 120, 'png'), 'image/png');
        $blatt->update(['struktur' => ['bausteine' => [
            ['typ' => 'text', 'text' => 'Links beginnt der Text'],
            ['typ' => 'bild', 'media_id' => $media->id, 'beschreibung' => 'Zoo'],
        ]]]);

        $pdf = app(AbSatzService::class)->pdf($blatt->fresh(), AbProfil::normalisieren(['rand' => 20]));

        $this->assertStringContainsString('/Subtype /Image', $pdf, 'Abbildung ist eingebettet');
        // Seitenrand: kein Text am linken Rand (html-margin darf den @page-Rand nicht überschreiben)
        $this->assertDoesNotMatchRegularExpression('/html,\s*body\s*\{\s*margin:\s*0/', app(AbSatzService::class)->html($blatt, AbProfil::standard(), 'pdf'));
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
