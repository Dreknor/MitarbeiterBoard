<?php

namespace Tests\Feature\Wiki;

use App\Models\WikiSeite;
use App\Services\Wiki\WikiService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Wiki-PDF: Bilder werden als data-URI eingebettet – DomPDF darf sie nicht stillschweigend weglassen
 * (dafür muss `data://` in config/dompdf.php unter `allowed_protocols` stehen).
 */
class WikiPdfTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('wiki');
        // Ohne Schullogo zählt nur das Bild aus dem Seiteninhalt.
        config(['app.logo' => null]);
    }

    public function test_pdf_enthaelt_eingebettetes_bild(): void
    {
        $user = $this->actingAsWithPermission('view wiki', 'edit wiki');
        $png = base64_encode(UploadedFile::fake()->image('foto.png', 120, 80)->getContent());
        $seite = app(WikiService::class)->anlegen($user, [
            'titel' => 'Mit Bild',
            'inhalt' => '<p>Vorher</p><p><img src="data:image/png;base64,'.$png.'" alt="Foto"></p>',
        ]);
        $this->assertSame(1, $seite->getMedia(WikiSeite::BILDER)->count());

        $pdf = $this->get(route('wiki.pdf', $seite->slug))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->getContent();

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThanOrEqual(1, $this->bilderIm($pdf), 'Das Bild fehlt im Wiki-PDF.');
    }

    public function test_pdf_ohne_bild_enthaelt_kein_bild(): void
    {
        $user = $this->actingAsWithPermission('view wiki', 'edit wiki');
        $seite = app(WikiService::class)->anlegen($user, ['titel' => 'Nur Text', 'inhalt' => '<p>Text</p>']);

        $pdf = $this->get(route('wiki.pdf', $seite->slug))->assertOk()->getContent();

        $this->assertSame(0, $this->bilderIm($pdf));
    }

    /** Anzahl der Bild-XObjects im PDF (DomPDF/CPDF schreibt die Objekt-Dictionaries unkomprimiert). */
    private function bilderIm(string $pdf): int
    {
        return preg_match_all('#/Subtype\s*/Image#', $pdf);
    }
}
