<?php

namespace Tests\Feature\AngepassteArbeitsblaetter;

use App\Models\Arbeitsblaetter\AbKiProtokoll;
use App\Models\Arbeitsblaetter\AngepasstesBlatt;
use App\Services\Arbeitsblaetter\AbSatzService;
use App\Services\Arbeitsblaetter\Ki\AbErkenner;
use App\Services\Arbeitsblaetter\Ki\AbErkennerFabrik;
use App\Services\Arbeitsblaetter\Ki\ErkennerVorgaben;
use App\Services\Arbeitsblaetter\Ki\KiProtokoll;
use App\Support\AbProfil;
use App\Support\AbStruktur;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\ArbeitsblaetterTestHilfen;

/**
 * Qualität der Erkennung und Fehlersuche: KI-Protokoll, Nachbearbeitung typischer Modellfehler,
 * Bausteine Kasten/Rechenfeld, vorab angekreuzte Kästchen, Titel aus dem Dateinamen.
 */
class ErkennungQualitaetTest extends TestCase
{
    use ArbeitsblaetterTestHilfen;

    private const ROH = ['seiten' => 1, 'bausteine' => [
        ['typ' => 'ueberschrift', 'text' => 'Fragen bewerten'],
        ['typ' => 'anweisung', 'text' => 'Beantworte die Fragen. Kreuze an.'],
        ['typ' => 'aufgabe', 'nummer' => '1', 'inhalt' => [], 'kinder' => [
            ['typ' => 'kasten', 'titel' => 'Eintritt', 'inhalt' => [['typ' => 'text', 'text' => "Kinder: 8 €\nErwachsene: 16 €"]]],
            ['typ' => 'aufgabe', 'nummer' => 'a)', 'inhalt' => [['typ' => 'text', 'text' => 'Wie viel kostet der Eintritt für zwei Kinder?']]],
            ['typ' => 'ankreuzen', 'frage' => 'Kann ich beantworten:', 'optionen' => ['Kann ich beantworten:', 'Kann ich nicht beantworten:'], 'angekreuzt' => [0]],
            ['typ' => 'rechenfeld', 'reihen' => 2, 'inhalt' => [['typ' => 'text', 'text' => '2 · '], ['typ' => 'luecke', 'laenge' => 4]]],
            ['typ' => 'text', 'inhalt' => []],
        ]],
    ]];

    protected function setUp(): void
    {
        parent::setUp();
        $this->arbeitsblaetterEinrichten();
    }

    public function test_nachbearbeitung_korrigiert_typische_modellfehler(): void
    {
        $s = ErkennerVorgaben::struktur(self::ROH);

        $this->assertSame(['ueberschrift', 'aufgabe'], array_column($s['bausteine'], 'typ'), 'Anweisung wurde Aufgabentext');
        $aufgabe = $s['bausteine'][1];
        $this->assertSame('Beantworte die Fragen. Kreuze an.', $aufgabe['inhalt'][0]['text']);
        $kinder = array_column($aufgabe['kinder'], null, 'typ');
        $this->assertSame('', $kinder['ankreuzen']['frage'], 'Frage, die nur eine Option wiederholt, entfällt');
        $this->assertSame([0], $kinder['ankreuzen']['angekreuzt']);
        $this->assertSame('Eintritt', $kinder['kasten']['titel']);
        $this->assertSame(2, $kinder['rechenfeld']['reihen']);
        $this->assertNotContains('text', array_column($aufgabe['kinder'], 'typ'), 'leerer Text entfällt');
        $this->assertArrayHasKey('reihen', ErkennerVorgaben::schema()['properties']['bausteine']['items']['properties']);
        $this->assertContains('kasten', ErkennerVorgaben::schema()['properties']['bausteine']['items']['properties']['typ']['enum']);
    }

    public function test_pdf_mit_kasten_rechenfeld_und_kreuz(): void
    {
        $blatt = $this->blatt([], AbStruktur::normalisieren(self::ROH)['bausteine']);
        $satz = app(AbSatzService::class);

        $html = $satz->html($blatt, AbProfil::normalisieren(['groesse' => 28]), 'pdf');
        $this->assertStringContainsString('class="ab-baustein ab-kasten', $html);
        $this->assertStringContainsString('class="ab-raster"', $html);
        $this->assertStringContainsString('alt="angekreuzt"', $html);

        $pdf = $satz->pdf($blatt, AbProfil::normalisieren(['groesse' => 28]));
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringStartsWith('PK', $satz->docx($blatt, AbProfil::normalisieren([])));
    }

    public function test_titel_aus_dateiname_wird_durch_ueberschrift_ersetzt(): void
    {
        $this->erkenner->struktur = self::ROH;
        $this->actingAs($this->lehrkraft());

        $this->post(route('arbeitsblaetter.store'), ['art' => 'upload', 'dateien' => [UploadedFile::fake()->createWithContent('e110_pk2723929_fhires.jpg', $this->bildInhalt())]]);
        $blatt = AngepasstesBlatt::firstOrFail();
        $this->assertSame('Fragen bewerten', $blatt->titel);
        $this->assertFalse($blatt->titel_automatisch);

        // selbst eingegebener Titel bleibt
        $this->post(route('arbeitsblaetter.store'), ['art' => 'upload', 'titel' => 'Mein Titel', 'dateien' => [UploadedFile::fake()->createWithContent('x.jpg', $this->bildInhalt())]]);
        $this->assertSame('Mein Titel', AngepasstesBlatt::latest('id')->first()->titel);
    }

    public function test_ki_protokoll_ohne_bilddaten_und_nur_fuer_berechtigte(): void
    {
        config([
            'arbeitsblaetter.ki.treiber' => 'gemini', 'arbeitsblaetter.ki.gemini.schluessel' => 'geheimer-schluessel',
            'arbeitsblaetter.ki.gemini.modell' => 'gemini-flash-latest', 'arbeitsblaetter.ki.wiederholung_sekunden' => [0, 0],
        ]);
        $this->app->forgetInstance(AbErkenner::class);
        $this->app->bind(AbErkenner::class, fn () => AbErkennerFabrik::erstellen());
        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
            ->push(['error' => ['message' => 'The model is overloaded.']], 503)
            ->push(['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => json_encode(self::ROH)]]]]], 'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 5]])]);
        $ersteller = $this->lehrkraft();
        $this->actingAs($ersteller);

        $this->post(route('arbeitsblaetter.store'), ['art' => 'upload', 'dateien' => [UploadedFile::fake()->createWithContent('zoo.jpg', $this->bildInhalt())]]);
        $blatt = AngepasstesBlatt::firstOrFail();

        $eintraege = AbKiProtokoll::where('blatt_id', $blatt->id)->orderBy('id')->get();
        $this->assertCount(2, $eintraege);
        $this->assertSame([503, 200], $eintraege->pluck('http_status')->all());
        $this->assertStringContainsString('[Base64,', $eintraege[0]->anfrage);
        $this->assertStringNotContainsString(substr(base64_encode($this->bildInhalt()), 100, 200), $eintraege[0]->anfrage);
        $this->assertStringNotContainsString('geheimer-schluessel', $eintraege[0]->anfrage.$eintraege[0]->url);

        $this->getJson(route('arbeitsblaetter.ki-protokoll', $blatt))->assertOk()
            ->assertJsonPath('eintraege.0.http_status', 200)
            ->assertJsonPath('eintraege.1.http_status', 503)
            ->assertJsonPath('eintraege.0.nutzung.promptTokenCount', 10)
            ->assertJsonFragment(['anbieter' => 'gemini']);
        $this->assertStringContainsString('"Fragen bewerten"', $this->getJson(route('arbeitsblaetter.ki-protokoll', $blatt))->json('eintraege.0.inhalt'));

        $this->actingAs($this->lehrkraft());
        $this->getJson(route('arbeitsblaetter.ki-protokoll', $blatt))->assertForbidden();

        $this->actingAs($this->lehrkraft(['edit settings']));
        $this->get(route('arbeitsblaetter.einstellungen'))->assertOk()->assertSee('Letzte KI-Aufrufe')->assertSee('HTTP 503');

        AbKiProtokoll::query()->update(['created_at' => now()->subDays(31)]);
        $this->assertSame(2, KiProtokoll::bereinigen());
    }
}
