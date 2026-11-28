<?php

namespace Tests\Feature\AngepassteArbeitsblaetter;

use App\Models\Arbeitsblaetter\AngepasstesBlatt;
use App\Services\Arbeitsblaetter\AbQuellenFehler;
use App\Services\Arbeitsblaetter\AbQuellenService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;
use Tests\Traits\ArbeitsblaetterTestHilfen;

/** Eingang: PDF mit/ohne Text, DOCX über Umwandlung und PHPWord-Ersatzweg, ODT ohne Umwandlung → Meldung. */
class QuellenTest extends TestCase
{
    use ArbeitsblaetterTestHilfen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->arbeitsblaetterEinrichten();
    }

    private function pdf(string $html): string
    {
        return Pdf::loadHTML($html)->output();
    }

    private function docx(): string
    {
        $word = new PhpWord();
        $word->addTitleStyle(1, ['bold' => true, 'size' => 16]);
        $abschnitt = $word->addSection();
        $abschnitt->addTitle('Brüche', 1);
        $abschnitt->addText('Ergänze: 3/4 = _____ /8');
        $tabelle = $abschnitt->addTable();
        $tabelle->addRow();
        $tabelle->addCell()->addText('Bruch');
        $tabelle->addCell()->addText('Dezimal');
        $pfad = tempnam(sys_get_temp_dir(), 'docx');
        $word->save($pfad, 'Word2007');
        $inhalt = file_get_contents($pfad);
        unlink($pfad);

        return $inhalt;
    }

    public function test_pdf_mit_textebene_gibt_hinweis_an_die_ki(): void
    {
        $eingang = app(AbQuellenService::class)->ausInhalt($this->pdf('<h1>Brüche üben</h1><p>Ergänze die fehlenden Zahlen in der Tabelle.</p>'), 'blatt.pdf');

        $this->assertSame('pdf', $eingang['art']);
        $this->assertSame(1, $eingang['seitenzahl']);
        $this->assertStringContainsString('fehlenden Zahlen', (string) $eingang['textebene']);

        $this->actingAs($this->lehrkraft());
        $this->post(route('arbeitsblaetter.store'), ['art' => 'upload', 'dateien' => [UploadedFile::fake()->createWithContent('blatt.pdf', $this->pdf('<p>Ergänze die fehlenden Zahlen in der Tabelle.</p>'))]]);
        $this->assertStringContainsString('fehlenden Zahlen', (string) $this->erkenner->aufrufe[0]['text']);
        $this->assertSame('application/pdf', $this->erkenner->aufrufe[0]['seiten'][0]['mime']);
    }

    public function test_pdf_ohne_text_hat_keine_textebene(): void
    {
        $eingang = app(AbQuellenService::class)->ausInhalt($this->pdf('<div style="width:100px;height:100px;background:#000"></div>'), 'scan.pdf');
        $this->assertNull($eingang['textebene']);
    }

    public function test_zu_viele_seiten(): void
    {
        config(['arbeitsblaetter.max_seiten' => 2]);
        $this->expectException(AbQuellenFehler::class);
        app(AbQuellenService::class)->ausInhalt($this->pdf(str_repeat('<p style="page-break-after:always">Seite</p>', 3)), 'lang.pdf');
    }

    public function test_docx_ueber_umwandlung(): void
    {
        $eingang = app(AbQuellenService::class)->ausInhalt($this->docx(), 'blatt.docx');

        $this->assertSame('pdf', $eingang['art']);
        $this->assertSame(['blatt.docx'], $this->konvertierung->aufrufe);
        $this->assertStringStartsWith('%PDF', $eingang['seiten'][0]['inhalt']);
    }

    public function test_docx_ersatzweg_phpword(): void
    {
        $this->konvertierung->verfuegbar = false;
        $this->actingAs($this->lehrkraft());

        $this->post(route('arbeitsblaetter.store'), ['art' => 'upload', 'dateien' => [UploadedFile::fake()->createWithContent('bruch.docx', $this->docx())]])->assertRedirect();

        $blatt = AngepasstesBlatt::firstOrFail();
        $this->assertSame(AngepasstesBlatt::ZU_PRUEFEN, $blatt->status);
        $this->assertCount(0, $this->erkenner->aufrufe, 'Rohstruktur ohne KI');
        $typen = collect($blatt->strukturGueltig()['bausteine'])->pluck('typ')->all();
        $this->assertSame(['ueberschrift', 'text', 'tabelle'], $typen);
        $text = $blatt->strukturGueltig()['bausteine'][1];
        $this->assertSame('luecke', $text['inhalt'][1]['typ']);
    }

    public function test_odt_ohne_umwandlung_meldet_pdf(): void
    {
        $this->konvertierung->verfuegbar = false;
        $this->actingAs($this->lehrkraft());

        $this->post(route('arbeitsblaetter.store'), ['art' => 'upload', 'dateien' => [UploadedFile::fake()->createWithContent('blatt.odt', 'PK-odt')]])
            ->assertSessionHasErrors(['dateien' => 'Diese Datei kann gerade nicht umgewandelt werden. Bitte als PDF speichern und erneut hochladen.']);
        $this->assertSame(0, AngepasstesBlatt::count());
    }
}
