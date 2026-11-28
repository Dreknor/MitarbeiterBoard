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
            ['typ' => 'tabelle', 'zeilen' => []],
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

    public function test_lange_blaetter_werden_abschnittsweise_erkannt(): void
    {
        // 7-seitiges PDF → 3 Abschnitte (3 + 3 + 1 Seiten); Abbildungen behalten ihre echte Seite
        $pdf = new \setasign\Fpdi\Fpdi();
        for ($i = 1; $i <= 7; $i++) {
            $pdf->AddPage();
            $pdf->SetFont('Helvetica', '', 14);
            $pdf->Cell(0, 10, 'Seite '.$i);
        }
        $this->erkenner->struktur = ['seiten' => 1, 'bausteine' => [
            ['typ' => 'aufgabe', 'nummer' => '1', 'inhalt' => [['typ' => 'text', 'text' => 'Aufgabe']]],
            ['typ' => 'bild', 'beschreibung' => 'Grafik', 'quelle' => ['seite' => 1, 'box' => [0.1, 0.1, 0.5, 0.5]]],
        ]];
        $seitenweise = new \App\Services\Arbeitsblaetter\Ki\SeitenweiseErkenner($this->erkenner);

        $e = $seitenweise->erkennen([['inhalt' => $pdf->Output('S'), 'mime' => 'application/pdf']], 'Textebene');

        $this->assertCount(3, $this->erkenner->aufrufe);
        $this->assertNull($this->erkenner->aufrufe[0]['text'], 'Textebene gilt fürs ganze Dokument, nicht je Abschnitt');
        $this->assertSame(7, $e->seiten);
        $this->assertSame([1, 4, 7], array_column(array_column(array_filter($e->struktur['bausteine'], fn ($b) => $b['typ'] === 'bild'), 'quelle'), 'seite'));
        $this->assertSame(9.0, $e->kostenCent, 'Kosten aller Abschnitte');

        // Fotos: 5 Seiten → 3 + 2, kurze Blätter gehen im Ganzen
        $this->erkenner->aufrufe = [];
        $foto = ['inhalt' => $this->bildInhalt(), 'mime' => 'image/jpeg'];
        $seitenweise->erkennen(array_fill(0, 5, $foto));
        $this->assertSame([3, 2], array_map(fn ($a) => count($a['seiten']), $this->erkenner->aufrufe));
        $this->erkenner->aufrufe = [];
        $seitenweise->erkennen([$foto, $foto]);
        $this->assertCount(1, $this->erkenner->aufrufe);
    }

    public function test_gutachten_runde1_satz(): void
    {
        $l = AbProfil::layout(AbProfil::normalisieren(['groesse' => 28, 'fett' => true]));
        // Strichliste als echte Striche (Fünfergruppe), Null ohne Schrägstrich, Terme brechen nicht um
        $this->assertSame(6, substr_count(AbSatzService::textHtml('IIIIII', $l), 'class="ab-strich"'));
        $this->assertStringContainsString('ab-strich-luecke', AbSatzService::textHtml('I I I I I I', $l));
        $this->assertStringContainsString('<span class="ab-null">10</span>', AbSatzService::textHtml('10 €', $l), 'ganze Zahl in einem Span – DomPDF bricht an Span-Grenzen');
        $this->assertStringContainsString('<span class="ab-null">−0,1</span>', AbSatzService::textHtml('(−0,1)', $l));
        $this->assertStringContainsString('&#039;', AbSatzService::textHtml("It's 10", $l), 'Entities bleiben heil');
        $this->assertStringContainsString('<span class="ab-term">7/25&nbsp;+&nbsp;2&nbsp;2/11</span>', AbSatzService::textHtml('Rechne 7/25 + 2 2/11 = x', $l));
        $this->assertStringNotContainsString('ab-null', AbSatzService::textHtml('10', AbProfil::layout(AbProfil::normalisieren(['schrift' => 'verdana']))));

        $blatt = $this->blatt(['titel' => '05 d2 offene geschlossene silben 1'], [
            ['typ' => 'ueberschrift', 'text' => 'Offene oder geschl. Silbe? 1'],
            ['typ' => 'aufgabe', 'nummer' => '3', 'inhalt' => [['typ' => 'text', 'text' => 'Finde das Wort.']]],
            ['typ' => 'tabelle', 'zeilen' => [['Gegenwart', 'Vergangenheit', 'Übersetzung'], ['ich bin', 'ich war', '']]],
            ['typ' => 'bild', 'media_id' => null, 'beschreibung' => 'x', 'quelle' => ['seite' => 1, 'box' => [0.1, 0.1, 0.3, 0.2]]],
        ]);
        $html = app(AbSatzService::class)->html($blatt, AbProfil::normalisieren(['groesse' => 28, 'fett' => true]), 'pdf');
        $this->assertStringNotContainsString('<h1>05 d2', $html, 'Dateiname nie als Titel');
        $this->assertStringContainsString('<td class="ab-nummer">3</td>', $html, 'Nummer in eigener Zelle statt negativem Einzug');
        $this->assertMatchesRegularExpression('/ab-tabelle[^"]*" style="font-size: (\d+(\.\d+)?)pt/', $html);
        preg_match('/ab-tabelle[^"]*" style="font-size: ([\d.]+)pt/', $html, $m);
        $this->assertLessThan(28, (float) $m[1], 'breite Tabelle wird verkleinert statt über den Rand zu laufen');
    }

    public function test_lehrkraft_material_wird_erkannt(): void
    {
        $this->assertNotEmpty(ErkennerVorgaben::lehrkraftMaterial("Hinweise für die Lehrkraft\nZiel der Stunde …", AbStruktur::leer()));
        $this->assertSame([], ErkennerVorgaben::lehrkraftMaterial('Arbeitsblatt: Die Zelle', AbStruktur::leer()));
        // Lehrplanbezug/Erwartungshorizont am Ende zählen beim Vollständigkeitsvergleich nicht mit
        $struktur = AbStruktur::normalisieren(['bausteine' => [['typ' => 'text', 'inhalt' => [['typ' => 'text', 'text' => str_repeat('Aufgabe zur Karte der Erde. ', 8)]]], ['typ' => 'text', 'inhalt' => 'a'], ['typ' => 'text', 'inhalt' => 'b']]]);
        $textebene = str_repeat('Aufgabe zur Karte der Erde. ', 8).' Lehrplanbezug '.str_repeat('Kompetenzen und Hinweise für den Unterricht. ', 8);
        $this->assertSame([], ErkennerVorgaben::unvollstaendig($struktur, 1, $textebene));
    }

    public function test_lange_aufgabe_wird_vollstaendig_gesetzt(): void
    {
        // früher stand die ganze Aufgabe in einer Tabellenzelle – DomPDF teilt die nie und schnitt den Rest ab
        $saetze = array_map(fn ($i) => 'Satz '.str_repeat(chr(97 + $i % 26), 3).chr(97 + intdiv($i, 26)).' ist hier lang genug für eine Zeile', range(0, 39));
        $blatt = $this->blatt([], [['typ' => 'aufgabe', 'nummer' => '1', 'inhalt' => [['typ' => 'text', 'text' => implode("
", $saetze)]]]]);
        $satz = app(AbSatzService::class);
        $werte = AbProfil::normalisieren(['groesse' => 28, 'fett' => true]);

        $this->assertStringContainsString('<p class="ab-kopf-zeile">', $satz->html($blatt, $werte, 'pdf'));
        $this->assertSame([], $satz->fehlendeWoerter($satz->pdf($blatt, $werte), $blatt->strukturGueltig()));
        $this->assertNotEmpty($satz->fehlendeWoerter($satz->pdf($this->blatt([], [['typ' => 'text', 'inhalt' => 'kurz']]), $werte), $blatt->strukturGueltig()));
    }

    public function test_feinschnitt_entfernt_reste_der_nachbarbilder(): void
    {
        // Motiv in der Mitte, oben ein schmaler Rest des Nachbarbilds, rundherum weißer Rand
        $bild = imagecreatetruecolor(400, 400);
        imagefill($bild, 0, 0, imagecolorallocate($bild, 255, 255, 255));
        $schwarz = imagecolorallocate($bild, 0, 0, 0);
        imagefilledrectangle($bild, 0, 0, 399, 20, $schwarz);
        imagerectangle($bild, 120, 120, 300, 340, $schwarz);
        imageline($bild, 120, 120, 300, 340, $schwarz);
        ob_start();
        imagepng($bild);
        $png = app(\App\Services\Arbeitsblaetter\AngepasstesBlattService::class)->ausschneiden((string) ob_get_clean(), [0, 0, 1, 1]);

        $neu = imagecreatefromstring($png);
        $this->assertLessThan(230, imagesy($neu), 'Rest oben und weißer Rand sind weg');
        $this->assertGreaterThan(170, imagesx($neu), 'Motiv bleibt ganz');
    }

    public function test_loesungsteil_der_ki_wird_entfernt(): void
    {
        $aufgabe = fn ($nr) => ['typ' => 'aufgabe', 'nummer' => $nr, 'inhalt' => [['typ' => 'text', 'text' => 'Berechne den Umfang des Rechtecks mit a = '.$nr.' cm.']]];
        $struktur = ErkennerVorgaben::nachbearbeiten(AbStruktur::normalisieren(['bausteine' => [
            ['typ' => 'ueberschrift', 'text' => 'Rechnen'], $aufgabe('1'), $aufgabe('2'), $aufgabe('1'),
            ['typ' => 'ueberschrift', 'text' => 'Lösungen'], $aufgabe('3'),
        ]]));
        $this->assertSame(['ueberschrift', 'aufgabe', 'aufgabe'], array_column($struktur['bausteine'], 'typ'));
    }

    public function test_leere_einspaltige_tabelle_wird_schreiblinien_und_backslash_n_umbruch(): void
    {
        $struktur = ErkennerVorgaben::nachbearbeiten(AbStruktur::normalisieren(['bausteine' => [
            ['typ' => 'tabelle', 'kopfzeile' => true, 'zeilen' => [['Vokal am Ende'], [''], [''], ['']]],
            ['typ' => 'text', 'inhalt' => [['typ' => 'text', 'text' => 'Zeile eins.\nZeile zwei.']]],
        ]]));
        $this->assertSame(['text', 'schreiblinien', 'text'], array_column($struktur['bausteine'], 'typ'));
        $this->assertSame(3, $struktur['bausteine'][1]['anzahl']);
        $this->assertStringContainsString("\n", $struktur['bausteine'][2]['inhalt'][0]['text']);
    }

    public function test_ueberlappende_bilder_werden_eins(): void
    {
        $bild = fn ($box) => ['typ' => 'bild', 'beschreibung' => 'x', 'quelle' => ['seite' => 1, 'box' => $box]];
        $struktur = ErkennerVorgaben::nachbearbeiten(AbStruktur::normalisieren(['bausteine' => [
            $bild([0.1, 0.1, 0.3, 0.3]), $bild([0.2, 0.15, 0.35, 0.32]), $bild([0.6, 0.1, 0.8, 0.3]),
        ]]));
        $this->assertCount(2, $struktur['bausteine']);
        $this->assertSame([0.1, 0.1, 0.35, 0.32], $struktur['bausteine'][0]['quelle']['box']);
    }

    public function test_frage_bleibt_mit_ersten_zwei_linien_zusammen(): void
    {
        $blatt = $this->blatt([], [
            ['typ' => 'text', 'inhalt' => [['typ' => 'text', 'text' => 'Warum ist eine ausgewogene Ernährung für ein Kaninchen wichtig?']]],
            ['typ' => 'schreiblinien', 'anzahl' => 12],
        ]);
        $html = app(AbSatzService::class)->html($blatt, AbProfil::normalisieren(['groesse' => 28, 'fett' => true]), 'pdf');
        $this->assertStringContainsString('ab-geteilt', $html);
        $this->assertSame(12, substr_count($html, '<div class="ab-linie"></div>'), 'keine Linie geht verloren');
    }

    public function test_wortspeicher_in_spalten_und_bilder_mit_schreiblinie(): void
    {
        $bild = fn () => ['typ' => 'bild', 'media_id' => null, 'beschreibung' => 'x', 'quelle' => ['seite' => 1, 'box' => [0.1, 0.1, 0.2, 0.3]]];
        $blatt = $this->blatt([], [
            ['typ' => 'kasten', 'titel' => 'Wörter', 'inhalt' => "Lupe
Kerze
Teelicht
Pipette
Glasstab
Messer"],
            $bild(), $bild(), $bild(),
        ]);
        $html = app(AbSatzService::class)->html($blatt, AbProfil::normalisieren(['groesse' => 28]), 'pdf');
        $this->assertStringContainsString('ab-wortliste', $html);
        $this->assertSame(3, substr_count($html, '<div class="ab-bild-linie">'), 'Wortspeicher + Bilder = Beschriften');
        $this->assertStringContainsString('<span class="ab-bild-nr">3</span>', $html);
    }

    public function test_antwortspalten_und_kopfzeile_der_tabelle(): void
    {
        $html = app(AbSatzService::class)->html($this->blatt([], [['typ' => 'tabelle', 'kopfzeile' => true, 'zeilen' => [['Wort', 'Übersetzung'], ['monster', ''], ['house', '']]]]), AbProfil::normalisieren([]), 'pdf');
        $this->assertStringContainsString('<thead>', $html);
        $this->assertMatchesRegularExpression('/<th style="width: 50%">/', $html, 'leere Antwortspalte bekommt Schreibplatz');
    }

    public function test_leere_tabelle_zum_ausfuellen_ist_kein_pruefhinweis(): void
    {
        $struktur = AbStruktur::normalisieren(['bausteine' => [
            ['typ' => 'tabelle', 'zeilen' => [['', ''], ['', ''], ['', '']]],
            ['typ' => 'tabelle', 'zeilen' => []],
        ]]);
        $this->assertCount(1, AbStruktur::pruefhinweise($struktur), 'nur die Tabelle ohne Zellen');
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
