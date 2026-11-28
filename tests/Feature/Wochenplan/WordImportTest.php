<?php

namespace Tests\Feature\Wochenplan;

use App\Models\Klasse;
use App\Models\Schueler;
use App\Models\Wochenplan\WpFach;
use App\Models\Wochenplan\WpPlan;
use App\Services\Wochenplan\Import\WpWordParser;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use Tests\Traits\WochenplanTestHilfen;
use ZipArchive;

/** Import von Word-Wochenplänen (.docx nach der Papiervorlage). */
class WordImportTest extends TestCase
{
    use WochenplanTestHilfen;

    private Klasse $herbst;

    private Klasse $winter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->nextcloudFake(false);
        foreach (['Deutsch', 'Mathe', 'Sachunterricht'] as $i => $name) {
            WpFach::firstOrCreate(['name' => $name], ['sort_order' => $i, 'is_default' => true]);
        }
        $this->herbst = Klasse::factory()->create(['name' => 'Herbst 2']);
        $this->winter = Klasse::factory()->create(['name' => 'Winter 2']);
    }

    public function test_parser_erkennt_plaene_aufgaben_und_freiarbeit(): void
    {
        $ergebnis = app(WpWordParser::class)->parse($this->docx());

        $this->assertCount(2, $ergebnis['plaene']);
        [$gruppe, $kind] = $ergebnis['plaene'];

        $this->assertSame(1, $gruppe['nummer']);
        $this->assertSame('2026-08-31', $gruppe['von']);
        $this->assertSame('2026-09-11', $gruppe['bis']);
        $this->assertSame(2, $gruppe['stufe']);
        $this->assertSame('Her/Wi', $gruppe['gruppe']);
        $this->assertNull($gruppe['kind']);
        $this->assertTrue($gruppe['selbsteinschaetzung']);

        $this->assertSame([['kuerzel' => '✍', 'text' => "1. Woche: Olli S. 6\n2. Woche: Lesezeit"], ['kuerzel' => '5+5', 'text' => 'Matherad S. 4, 5']], $gruppe['uebungen']);

        $sprache = $gruppe['faecher'][0];
        $this->assertSame("Sprache\nABC", $sprache['label']);
        $this->assertSame([
            ['text' => 'Arbeite an deiner Nacherzählung: Beende sie.', 'freiwillig' => false],
            ['text' => 'Überarbeite sie.', 'freiwillig' => false],
            ['text' => 'Zusatz: Seite 7', 'freiwillig' => true],
        ], $sprache['aufgaben']);
        // „ab 10. Dezember“ ist kein neuer Punkt
        $this->assertSame("Wähle eine Aufgabe aus den Adventsaufgaben (ab 10. Dezember).\n______________________", $gruppe['faecher'][1]['aufgaben'][0]['text']);
        $this->assertSame([['fach' => 'Mathe', 'text' => 'Pythagorasbrett'], ['fach' => 'Mathe', 'text' => 'Tausenderkette']], $gruppe['freiarbeit']);

        $this->assertSame('Lotta', $kind['kind']);
        $this->assertSame('Fr', $kind['gruppe']);
    }

    public function test_import_ueber_vorschau_legt_klassen_und_kinderplaene_an(): void
    {
        $lotta = Schueler::factory()->create(['vorname' => 'Lotta', 'nachname' => 'Muster', 'klasse_id' => $this->herbst->id]);
        $this->actingAs($this->paedagoge([$this->herbst, $this->winter]));

        $antwort = $this->post(route('wp.import.vorschau'), ['dateien' => [$this->upload()]]);
        $antwort->assertRedirect();
        $token = basename($antwort->headers->get('Location'));

        $this->get(route('wp.import.anzeigen', $token))->assertOk()
            ->assertSee('Kinderplan: Lotta')->assertSee('Herbst 2')->assertSee('Winter 2');

        $this->post(route('wp.import.ausfuehren', $token), ['plaene' => [
            '0-0' => ['importieren' => 1, 'klassen' => [$this->herbst->id, $this->winter->id], 'von' => '2026-08-31', 'bis' => '2026-09-11'],
            '0-1' => ['importieren' => 1, 'schueler_id' => $lotta->id],
        ]])->assertRedirect(route('wp.index'));

        $plaene = WpPlan::where('name', '1. Wochenplan')->get();
        $this->assertEqualsCanonicalizing([$this->herbst->id, $this->winter->id], $plaene->pluck('klasse_id')->all());
        $plan = $plaene->firstWhere('klasse_id', $this->herbst->id);
        $this->assertTrue((bool) $plan->taegliche_uebungen_aktiv);
        $this->assertSame(['✍', '5+5'], $plan->taeglicheUebungen()->pluck('kuerzel')->all());

        $sprache = $plan->planFaecher()->whereHas('fach', fn ($q) => $q->where('name', 'Deutsch'))->firstOrFail();
        $this->assertSame('Sprache', $sprache->custom_name);
        $this->assertSame('ABC', $sprache->symbol);
        $this->assertSame(1, $sprache->aufgaben()->where('freiwillig', true)->count());

        $mathe = $plan->planFaecher()->whereHas('fach', fn ($q) => $q->where('name', 'Mathe'))->firstOrFail();
        $this->assertSame(2, $mathe->aufgaben()->where('freiarbeit', true)->count());
        $this->assertNotNull($plan->planFaecher()->whereNull('wp_fach_id')->where('symbol', '⭐🎄')->first());

        $kinderplan = WpPlan::where('schueler_id', $lotta->id)->firstOrFail();
        $this->assertSame('1. Wochenplan – Lotta Muster', $kinderplan->name);
        $this->assertSame($plan->id, $kinderplan->parent_plan_id);
    }

    public function test_vorhandene_plaene_werden_uebersprungen_und_dateien_nicht_gespeichert(): void
    {
        $this->actingAs($this->paedagoge([$this->herbst]));

        foreach ([1, 2] as $durchgang) {
            $antwort = $this->post(route('wp.import.vorschau'), ['dateien' => [$this->upload()]]);
            $token = basename($antwort->headers->get('Location'));
            $this->post(route('wp.import.ausfuehren', $token), ['plaene' => ['0-0' => ['importieren' => 1, 'klassen' => [$this->herbst->id]]]]);
        }

        $this->assertSame(1, WpPlan::where('klasse_id', $this->herbst->id)->count());
    }

    public function test_fremde_klassen_und_doc_dateien_werden_abgelehnt(): void
    {
        // nur Herbst 2 zugeordnet → Winter 2 wird nicht angelegt, auch wenn sie mitgeschickt wird
        $this->actingAs($this->paedagoge([$this->herbst]));

        $antwort = $this->post(route('wp.import.vorschau'), ['dateien' => [
            $this->upload(),
            UploadedFile::fake()->createWithContent('alt.doc', 'kein docx'),
        ]]);
        $token = basename($antwort->headers->get('Location'));
        $this->get(route('wp.import.anzeigen', $token))->assertOk()->assertSee('Nur .docx wird gelesen');

        $this->post(route('wp.import.ausfuehren', $token), ['plaene' => ['0-0' => ['importieren' => 1, 'klassen' => [$this->herbst->id, $this->winter->id]]]]);

        $this->assertSame(0, WpPlan::where('klasse_id', $this->winter->id)->count());
        $this->assertSame(1, WpPlan::where('klasse_id', $this->herbst->id)->count());
    }

    public function test_fremde_vorschau_ist_nicht_abrufbar(): void
    {
        $this->actingAs($this->paedagoge([$this->herbst]));
        $antwort = $this->post(route('wp.import.vorschau'), ['dateien' => [$this->upload()]]);
        $token = basename($antwort->headers->get('Location'));

        $this->actingAs($this->paedagoge([$this->herbst]));
        $this->get(route('wp.import.anzeigen', $token))->assertRedirect(route('wp.import'));
    }

    public function test_befehl_zeigt_ohne_ausfuehren_nur_an(): void
    {
        $user = $this->paedagoge([$this->herbst, $this->winter]);

        $this->artisan('wochenplan:import', ['pfade' => [$this->docx()], '--user' => $user->id])->assertSuccessful();
        $this->assertSame(0, WpPlan::count());

        $this->artisan('wochenplan:import', ['pfade' => [$this->docx()], '--user' => $user->id, '--ausfuehren' => true])->assertSuccessful();
        $this->assertSame(2, WpPlan::whereNotNull('klasse_id')->count());
    }

    // ─── Testdokument ───────────────────────────────────────────────────────

    private function upload(): UploadedFile
    {
        return new UploadedFile($this->docx(), '1. WP Kl 2.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);
    }

    /** Minimales .docx nach der Papiervorlage: Lerngruppen-Plan + Plan für ein Kind. */
    private function docx(): string
    {
        $p = fn (string ...$zeilen) => '<w:p>'.implode('<w:r><w:br/></w:r>', array_map(fn ($z) => '<w:r><w:t xml:space="preserve">'.htmlspecialchars($z).'</w:t></w:r>', $zeilen)).'</w:p>';
        $zelle = fn (string ...$absaetze) => '<w:tc>'.($absaetze ? implode('', $absaetze) : '<w:p/>').'</w:tc>';
        $zeile = fn (string ...$zellen) => '<w:tr>'.implode('', $zellen).'</w:tr>';
        $tabelle = fn (string ...$zeilen) => '<w:tbl>'.implode('', $zeilen).'</w:tbl>';
        $stift = '<w:p><w:r><w:sym w:font="Wingdings" w:char="F03F"/></w:r><w:r><w:t xml:space="preserve"> 1. Woche: Olli S. 6</w:t></w:r><w:r><w:br/></w:r><w:r><w:t>2. Woche: Lesezeit</w:t></w:r><w:r><w:br/></w:r><w:r><w:t xml:space="preserve">5+5   Matherad S. 4, 5</w:t></w:r></w:p>';

        $plan = fn (string $kopf, string $name) => $p($kopf).$p($name)
            .$tabelle(
                $zeile($zelle($p('Tägliche Übung'), $stift, '<w:tbl><w:tr><w:tc><w:p><w:r><w:t>Montag</w:t></w:r></w:p></w:tc></w:tr></w:tbl><w:p/>')),
                $zeile($zelle(), $zelle($p('Aufgaben')), $zelle($p('fertig')), $zelle($p('kontrolliert')), $zelle($p('Unterschrift'))),
                $zeile($zelle($p('Sprache'), $p('ABC')), $zelle($p('Arbeite an deiner Nacherzählung:1. Beende sie.'), $p('2. Überarbeite sie.'), $p('*Zusatz: Seite 7')), $zelle($p('……'), $p('……')), $zelle(), $zelle()),
                $zeile($zelle($p('Mathe-matik'), $p('5+5')), $zelle($p('Wähle eine Aufgabe aus den Adventsaufgaben (ab 10. Dezember).', '……………………')), $zelle($p('……')), $zelle(), $zelle()),
                $zeile($zelle($p('⭐🎄')), $zelle($p('Lerne den Text für die Andacht.')), $zelle($p('……')), $zelle(), $zelle()),
            )
            .$p('So habe ich gearbeitet :').$tabelle($zeile($zelle(), $zelle(), $zelle()))
            .$p('Empfehlungen für die Freiarbeit').$p('Mathe:')
            .$tabelle($zeile($zelle($p('Pythagorasbrett')), $zelle()), $zeile($zelle($p('Tausenderkette')), $zelle()), $zeile($zelle(), $zelle()));

        $body = $plan(' 1. Wochenplan vom 31.08.-11.09.2026   Klasse 2 Her/Wi', 'Name:……………………………')
            .$plan(' 1. Wochenplan vom 31.08.-11.09.2026   Klasse 2 Fr', 'Name: Lotta');
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$body.'</w:body></w:document>';

        $pfad = tempnam(sys_get_temp_dir(), 'wpdocx').'.docx';
        $zip = new ZipArchive();
        $zip->open($pfad, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $zip->addFromString('word/document.xml', $xml);
        $zip->close();

        return $pfad;
    }
}
