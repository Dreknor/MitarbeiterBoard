<?php

namespace App\Support;

use App\Services\Api\PaedAppService;
use Barryvdh\DomPDF\PDF;

/**
 * Gemeinsame Bausteine für DomPDF-Dokumente (Dossier, Protokoll Elterngespräch):
 * Schullogo als Data-URI, Briefkopf-Daten aus den Einstellungen, Seitenzahlen und Wasserzeichen.
 */
class PdfLayout
{
    public function __construct(private PaedAppService $app)
    {
    }

    /**
     * Schulname, Anschrift (Zeilen aus Setting `school_address`, getrennt mit „|“), Kontaktzeile und Logo.
     *
     * @return array{name: string, adresse: string[], kontakt: ?string, logo: ?string}
     */
    public function schulkopf(): array
    {
        $adresse = array_values(array_filter(array_map('trim', explode('|', (string) settings('school_address', 'paed_app')))));
        $kontakt = trim((string) settings('school_contact', 'paed_app'));

        return [
            'name' => $this->app->schoolName(),
            'adresse' => $adresse,
            'kontakt' => $kontakt !== '' ? $kontakt : null,
            'logo' => $this->logoDataUri(),
        ];
    }

    public function logoDataUri(): ?string
    {
        $path = public_path('img/' . config('app.logo'));
        if (!config('app.logo') || !is_file($path)) {
            return null;
        }

        $mime = mime_content_type($path) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
    }

    /**
     * Rendert das PDF und setzt Seitenzahlen sowie optional ein diagonales Wasserzeichen
     * (über die Canvas-API, da PHP in dompdf-Views deaktiviert ist).
     *
     * @param array{x?: float, y?: float} $position Position der Seitenzahl (Abstand von rechts/unten)
     * @param string|null $folgekopf schlanke Kopfzeile ab Seite 2 (Seite 1 trägt den Briefkopf im Inhalt)
     */
    public function rendern(PDF $pdf, ?string $wasserzeichen = null, array $position = [], ?string $folgekopf = null): string
    {
        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $fontBold = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'bold') ?? $font;

        if ($folgekopf) {
            $breite = $canvas->get_width();
            $canvas->page_script(function ($pageNumber, $pageCount, $canvas) use ($folgekopf, $font, $breite) {
                if ($pageNumber < 2) {
                    return;
                }
                $canvas->text(57, 28, $folgekopf, $font, 7.5, [0.42, 0.45, 0.5]);
                $canvas->line(57, 40, $breite - 51, 40, [0.82, 0.84, 0.86], 0.6);
            });
        }

        if ($wasserzeichen) {
            $breite = $canvas->get_width();
            $hoehe = $canvas->get_height();
            $canvas->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) use ($wasserzeichen, $fontBold, $breite, $hoehe) {
                $groesse = 80;
                $textBreite = $fontMetrics->getTextWidth($wasserzeichen, $fontBold, $groesse);
                $canvas->set_opacity(0.08);
                $canvas->text(($breite - $textBreite * 0.7) / 2, $hoehe / 2 + $textBreite * 0.35, $wasserzeichen, $fontBold, $groesse, [0.3, 0.3, 0.3], 0, 0, -45);
                $canvas->set_opacity(1);
            });
        }

        $canvas->page_text(
            $canvas->get_width() - ($position['x'] ?? 110),
            $canvas->get_height() - ($position['y'] ?? 47),
            'Seite {PAGE_NUM} von {PAGE_COUNT}',
            $font,
            7,
            [0.4, 0.4, 0.4]
        );

        return $pdf->output();
    }
}
