<?php

namespace Tests\Feature\AngepassteArbeitsblaetter;

use App\Jobs\Arbeitsblaetter\AbBlattErkennen;
use App\Models\Arbeitsblaetter\AbKiVerbrauch;
use App\Models\Arbeitsblaetter\AngepasstesBlatt;
use App\Services\Arbeitsblaetter\AbSatzService;
use App\Services\Arbeitsblaetter\AngepasstesBlattService;
use App\Services\Arbeitsblaetter\Ki\AbErkenner;
use App\Services\Arbeitsblaetter\Ki\AnthropicErkenner;
use App\Services\Arbeitsblaetter\Ki\ErkennerVorgaben;
use App\Support\AbProfil;
use App\Support\AbStruktur;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\ArbeitsblaetterTestHilfen;

/**
 * Regressionstests zum Testbericht vom 27.11.2026 (F02–F16) und zum Praxistest der KI-Modelle.
 */
class TestberichtTest extends TestCase
{
    use ArbeitsblaetterTestHilfen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->arbeitsblaetterEinrichten();
    }

    public function test_f02_inhalte_in_kinder_werden_gerettet_und_flache_liste_gruppiert(): void
    {
        $s = ErkennerVorgaben::struktur(['seiten' => 1, 'bausteine' => [
            ['typ' => 'ueberschrift', 'text' => 'Satzarten 1'],
            // Merksatz in kinder statt text (Blatt 08)
            ['typ' => 'kasten', 'titel' => 'Merke!', 'kinder' => [['typ' => 'text', 'inhalt' => [['typ' => 'text', 'text' => 'Sätze, in denen wir etwas ausrufen, nennen wir Ausrufesätze.']]]]],
            // Strichliste in kinder statt zeilen (Blatt 02)
            ['typ' => 'tabelle', 'kinder' => [['typ' => 'text', 'text' => 'Hase IIII'], ['typ' => 'text', 'text' => 'Pferd II']]],
            // flache Liste: Teilaufgaben/Linien mit teil_von_aufgabe
            ['typ' => 'aufgabe', 'nummer' => '1.', 'inhalt' => [['typ' => 'text', 'text' => 'Bilde Ausrufesätze.']], 'teil_von_aufgabe' => false],
            ['typ' => 'aufgabe', 'nummer' => 'a)', 'inhalt' => [['typ' => 'text', 'text' => 'Jakob ruft:']], 'teil_von_aufgabe' => true],
            ['typ' => 'schreiblinien', 'anzahl' => 2, 'teil_von_aufgabe' => true],
            ['typ' => 'text', 'inhalt' => [['typ' => 'text', 'text' => 'Ende']], 'teil_von_aufgabe' => false],
        ]]);

        $typen = array_column($s['bausteine'], 'typ');
        $this->assertSame(['ueberschrift', 'kasten', 'tabelle', 'aufgabe', 'text'], $typen);
        $this->assertStringContainsString('Ausrufesätze', AbStruktur::segmentText($s['bausteine'][1]['inhalt']));
        $this->assertSame([['Hase IIII'], ['Pferd II']], $s['bausteine'][2]['zeilen']);
        $this->assertSame('1', $s['bausteine'][3]['nummer'], 'F15: Nummer ohne Punkt');
        $this->assertSame(['aufgabe', 'schreiblinien'], array_column($s['bausteine'][3]['kinder'], 'typ'));
        $this->assertSame([], AbStruktur::pruefhinweise($s));
    }

    public function test_f05_boxen_im_format_0_bis_1000_und_kommentare(): void
    {
        $s = ErkennerVorgaben::struktur(['bausteine' => [
            ['typ' => 'bild', 'beschreibung' => '5-Cent, 2-Cent (vermutlich Fehler im Original) und 1-Cent', 'seite' => 1, 'box' => [117, 107, 479, 939]],
        ]]);
        $bild = $s['bausteine'][0];
        $this->assertEqualsWithDelta([0.102, 0.092, 0.494, 0.954], $bild['quelle']['box'], 0.0001, '÷ 1000 und 1,5 % Rand');
        $this->assertSame('5-Cent, 2-Cent und 1-Cent', $bild['beschreibung'], 'F14: Modellkommentar entfernt');
    }

    public function test_f10_zeilenumbrueche_nur_im_fliesstext_entfernt(): void
    {
        $s = ErkennerVorgaben::struktur(['bausteine' => [
            ['typ' => 'text', 'inhalt' => [['typ' => 'text', 'text' => 'Die Überschrift soll '], ['typ' => 'luecke', 'laenge' => 20], ['typ' => 'text', 'text' => " für die Geschichte\nwecken."]]],
            ['typ' => 'text', 'inhalt' => [['typ' => 'text', 'text' => "Vater - Sofa - Maus\nBiene - Papier - Kino"]]],
        ]]);
        $this->assertSame([['typ' => 'text', 'text' => 'Die Überschrift soll '], ['typ' => 'luecke', 'laenge' => 20], ['typ' => 'text', 'text' => ' für die Geschichte wecken.']], $s['bausteine'][0]['inhalt'], 'Leerzeichen an der Lücke bleiben');
        $this->assertSame("Vater - Sofa - Maus\nBiene - Papier - Kino", $s['bausteine'][1]['inhalt'][0]['text'], 'Listenzeilen bleiben');
    }

    public function test_f03_unvollstaendig_wird_nachgefragt_mit_staerkerem_modell(): void
    {
        config(['arbeitsblaetter.ki.nachfragen' => true]);
        $mager = ['seiten' => 1, 'bausteine' => [['typ' => 'ueberschrift', 'text' => 'Offene oder geschl. Silbe? 1'], ['typ' => 'kasten', 'titel' => 'Merke:']]];
        $this->erkenner->struktur = $mager;
        $blatt = $this->blatt(['status' => AngepasstesBlatt::ERKENNUNG, 'struktur' => AbStruktur::leer(), 'ki_seiten' => 1]);
        $blatt->addMediaFromString($this->bildInhalt())->usingFileName('s.jpg')->withCustomProperties(['mime' => 'image/jpeg'])->toMediaCollection(AngepasstesBlatt::ORIGINALE);

        (new AbBlattErkennen($blatt->id))->handle(app(AngepasstesBlattService::class), app(AbErkenner::class));

        $blatt->refresh();
        $this->assertCount(2, $this->erkenner->aufrufe, 'einmal nachgefragt');
        $this->assertSame(AngepasstesBlatt::ZU_PRUEFEN, $blatt->status);
        $this->assertStringContainsString('vermutlich unvollständig', implode(' ', $blatt->ki_hinweise));
        $this->assertSame(6.0, (float) AbKiVerbrauch::sum('kosten_cent'), 'beide Anfragen verbucht');
    }

    public function test_nachfrage_modell_der_claude_treiber(): void
    {
        config(['arbeitsblaetter.ki.modell' => 'claude-haiku-5-5', 'arbeitsblaetter.ki.nachfrage_modell' => 'claude-sonnet-5-5', 'arbeitsblaetter.ki.preis_eingabe_cent' => null, 'arbeitsblaetter.ki.preis_ausgabe_cent' => null]);
        $haiku = app(AnthropicErkenner::class);
        $sonnet = $haiku->nachfrageErkenner();

        $this->assertSame('claude-sonnet-5-5', $sonnet->modell());
        $this->assertSame('claude-haiku-5-5', $haiku->modell());
        $this->assertSame([10.0, 50.0], $haiku->preise(), 'Listenpreis des Modells');
        $this->assertSame(0.35, $haiku->kosten(['input_tokens' => 10000, 'output_tokens' => 5000]), 'Bruchteile eines Cents bleiben erhalten');
        $this->assertSame([200.0, 1000.0], $sonnet->preise());
        // Anthropic: keine Zahlengrenzen, alle Felder Pflicht (max. 24 optionale Felder je Schema)
        $rechenfeld = collect(ErkennerVorgaben::schema(false)['properties']['bausteine']['items']['anyOf'])->first(fn ($t) => $t['properties']['typ']['enum'] === ['rechenfeld']);
        $this->assertArrayNotHasKey('minimum', $rechenfeld['properties']['reihen']);
        $this->assertSame(array_keys($rechenfeld['properties']), $rechenfeld['required']);
    }

    public function test_f04_erneut_erkennen_ersetzt_statt_anzuhaengen(): void
    {
        $user = $this->lehrkraft();
        $this->actingAs($user);
        $blatt = $this->blatt(['status' => AngepasstesBlatt::ZU_PRUEFEN, 'erstellt_von' => $user->id, 'ki_seiten' => 1]);
        $blatt->addMediaFromString($this->bildInhalt())->usingFileName('s.jpg')->withCustomProperties(['mime' => 'image/jpeg'])->toMediaCollection(AngepasstesBlatt::ORIGINALE);

        $this->postJson(route('arbeitsblaetter.erkennen', $blatt))->assertOk();

        $this->assertSame(['ueberschrift', 'anweisung', 'aufgabe', 'bild'], array_column($blatt->fresh()->strukturGueltig()['bausteine'], 'typ'), 'altes Ergebnis ersetzt');
    }

    public function test_f16_freigabe_fragt_bei_leeren_bausteinen_nach(): void
    {
        $user = $this->lehrkraft();
        $this->actingAs($user);
        $blatt = $this->blatt(['status' => AngepasstesBlatt::ZU_PRUEFEN, 'erstellt_von' => $user->id], [
            ['typ' => 'tabelle', 'zeilen' => [['', '']]],
            ['typ' => 'kasten', 'titel' => 'Merke:'],
        ]);

        $this->postJson(route('arbeitsblaetter.freigeben', $blatt), ['version' => $blatt->version])
            ->assertStatus(422)->assertJsonCount(2, 'hinweise');
        $this->assertSame(AngepasstesBlatt::ZU_PRUEFEN, $blatt->fresh()->status);

        $this->getJson(route('arbeitsblaetter.daten', $blatt))->assertJsonCount(2, 'blatt.pruefhinweise');

        $this->postJson(route('arbeitsblaetter.freigeben', $blatt), ['version' => $blatt->version, 'trotzdem' => true])->assertOk();
        $this->assertSame(AngepasstesBlatt::FREIGEGEBEN, $blatt->fresh()->status);
    }

    public function test_f07_f12_satz_luecken_titel_zeichenflaeche(): void
    {
        $blatt = $this->blatt(['titel' => '06 d4 akkusativobjekt 1'], [
            ['typ' => 'ueberschrift', 'text' => 'Das Akkusativobjekt 1'],
            ['typ' => 'text', 'inhalt' => [['typ' => 'text', 'text' => 'Antwort: '], ['typ' => 'luecke', 'laenge' => 40]]],
            ['typ' => 'zeichenflaeche', 'hoehe' => 5],
        ]);
        $satz = app(AbSatzService::class);
        $werte = AbProfil::normalisieren(['groesse' => 36]);
        $html = $satz->html($blatt, $werte, 'pdf');

        $this->assertStringNotContainsString('<h1>06 d4 akkusativobjekt 1</h1>', $html, 'F12: Dateiname nicht zusätzlich');
        preg_match('/ab-luecke" style="width: ([\d.]+)pt/', $html, $m);
        $this->assertLessThanOrEqual(AbProfil::layout($werte)['nutzbreite_pt'] * 0.7, (float) $m[1], 'F07: Lücke nicht breiter als die Zeile');
        $this->assertStringContainsString('ab-zeichenflaeche', $html);
        $this->assertStringStartsWith('%PDF', $satz->pdf($blatt, $werte));
        $this->assertStringStartsWith('PK', $satz->docx($blatt, $werte));
    }

    public function test_grundschul_lineatur_aus_original_oder_profil(): void
    {
        $struktur = ErkennerVorgaben::struktur(['bausteine' => [
            ['typ' => 'schreiblinien', 'anzahl' => 3, 'lineatur' => 'grundschule'],
            ['typ' => 'schreiblinien', 'anzahl' => 2],
            ['typ' => 'schreiblinien', 'anzahl' => 1, 'lineatur' => 'quatsch'],
        ]]);
        $this->assertSame(['grundschule', 'einfach', 'einfach'], array_column($struktur['bausteine'], 'lineatur'));

        $blatt = $this->blatt([], $struktur['bausteine']);
        $satz = app(AbSatzService::class);
        $zaehle = fn (array $werte) => substr_count($satz->html($blatt, AbProfil::normalisieren($werte), 'pdf'), '<div class="ab-gs">');

        $this->assertSame(3, $zaehle([]), 'wie im Original: nur der erste Baustein');
        $this->assertSame(6, $zaehle(['lineatur' => 'grundschule']), 'Profil: alle Schreibzeilen mit Mittelband');
        $this->assertSame(0, $zaehle(['lineatur' => 'einfach']));
        $this->assertNotSame(AbProfil::hash([]), AbProfil::hash(['lineatur' => 'grundschule']), 'Profiländerung macht Fassungen veraltet');
        $this->assertStringStartsWith('%PDF', $satz->pdf($blatt, AbProfil::normalisieren(['lineatur' => 'grundschule', 'farbschema' => 'invertiert'])));
        $this->assertStringStartsWith('PK', $satz->docx($blatt, AbProfil::normalisieren(['lineatur' => 'grundschule'])));
        $this->assertContains('grundschule', collect(ErkennerVorgaben::schema(false)['properties']['bausteine']['items']['anyOf'])
            ->first(fn ($t) => $t['properties']['typ']['enum'] === ['schreiblinien'])['properties']['lineatur']['enum']);
    }

    public function test_f17_upload_ohne_titel_nimmt_ueberschrift(): void
    {
        $this->actingAs($this->lehrkraft());
        $this->post(route('arbeitsblaetter.store'), ['art' => 'upload', 'dateien' => [UploadedFile::fake()->createWithContent('01_m3_kombinatorik_1.jpg', $this->bildInhalt())]]);

        $this->assertSame('Brüche', AngepasstesBlatt::firstOrFail()->titel);
    }

    public function test_f13_vorlage_gross_unterscheidet_sich_vom_standard(): void
    {
        $gross = collect(AbProfil::STANDARD_VORLAGEN)->firstWhere(0, 'Groß')[1];
        $this->assertGreaterThan(AbProfil::standard()['groesse'], AbProfil::normalisieren($gross)['groesse']);
    }

    public function test_f05_kontrastbild_behaelt_feine_linien(): void
    {
        $blatt = $this->blatt();
        // hellblaue Linie (wie ein Ausmalraster) auf Weiß
        $bild = imagecreatetruecolor(100, 100);
        imagefill($bild, 0, 0, imagecolorallocate($bild, 255, 255, 255));
        imageline($bild, 0, 50, 99, 50, imagecolorallocate($bild, 170, 200, 215));
        ob_start();
        imagepng($bild);
        $media = $blatt->addMediaFromString((string) ob_get_clean())->usingFileName('a.png')->toMediaCollection(AngepasstesBlatt::ABBILDUNGEN);

        $satz = app(AbSatzService::class);
        $kontrast = imagecreatefromstring($satz->bildInhalt($media, true));
        $invertiert = imagecreatefromstring($satz->bildInhalt($media, true, true));

        $this->assertLessThan(140, imagecolorsforindex($kontrast, imagecolorat($kontrast, 50, 50))['red'], 'Linie deutlich dunkler');
        $this->assertSame(255, imagecolorsforindex($kontrast, imagecolorat($kontrast, 10, 10))['red'], 'Papier bleibt weiß');
        $this->assertGreaterThan(160, imagecolorsforindex($invertiert, imagecolorat($invertiert, 50, 50))['red'], 'invertiert: helle Linie');
        $this->assertSame(0, imagecolorsforindex($invertiert, imagecolorat($invertiert, 10, 10))['red'], 'invertiert: schwarzer Grund');
    }
}
