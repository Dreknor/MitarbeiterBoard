<?php

namespace App\Services\Wochenplan;

use App\Models\Wochenplan\WpPlan;
use Illuminate\Support\Facades\Log;
use setasign\Fpdi\Fpdi;

class WpPdfMergeService
{
    /**
     * Erstellt eine kombinierte PDF: Wochenplan + alle Arbeitsblätter
     * (bei Kinderplänen inkl. der Arbeitsblätter des Klassenplans).
     *
     * @param  string  $wochenplanPdfPath  Pfad zur generierten Wochenplan-PDF
     * @return string                      Pfad zur kombinierten PDF (temporär)
     */
    public function mergeWithAttachments(string $wochenplanPdfPath, WpPlan $plan): string
    {
        $arbeitsblaetter = $plan->alleDateien();

        if ($arbeitsblaetter->isEmpty()) {
            return $wochenplanPdfPath;
        }

        if (!class_exists(Fpdi::class)) {
            Log::warning('WpPdfMergeService: FPDI nicht verfügbar. PDF-Merging nicht möglich.');
            return $wochenplanPdfPath;
        }

        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $outputPath = $tempDir . '/' . uniqid('wp_merged_') . '.pdf';
        $ablage = app(WpAblageService::class);

        $pdf = new Fpdi();
        $this->addPdfPages($pdf, $wochenplanPdfPath);

        foreach ($arbeitsblaetter as $datei) {
            $kopie = null;
            $attachmentPdfPath = null;
            try {
                $kopie = $ablage->temporaereKopie($datei);
                if (!$kopie) {
                    continue;
                }
                $attachmentPdfPath = $this->convertToPdf($kopie, (string) $datei->mime_type);
                if ($attachmentPdfPath) {
                    $this->addPdfPages($pdf, $attachmentPdfPath);
                }
            } catch (\Throwable $e) {
                Log::warning('Arbeitsblatt konnte nicht angehängt werden', [
                    'datei_id' => $datei->id,
                    'name'     => $datei->name,
                    'error'    => $e->getMessage(),
                ]);
            } finally {
                if ($attachmentPdfPath && $attachmentPdfPath !== $kopie) {
                    @unlink($attachmentPdfPath);
                }
                if ($kopie) {
                    @unlink($kopie);
                }
            }
        }

        $pdf->Output($outputPath, 'F');

        return $outputPath;
    }

    /**
     * Konvertiert eine Datei in PDF (falls nötig).
     *
     * - PDF → direkt zurückgeben
     * - DOCX/DOC → LibreOffice CLI (bevorzugt) oder PhpWord→HTML→DomPDF (Fallback)
     * - JPG/PNG → Als PDF-Seite einbetten via FPDF
     *
     * @return string|null  Pfad zur PDF-Datei oder null bei Fehler/unbekanntem Format
     */
    public function convertToPdf(string $path, string $mimeType): ?string
    {
        if (str_contains($mimeType, 'pdf')) {
            return $path;
        }

        if (in_array($mimeType, [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])) {
            return $this->convertWordToPdf($path);
        }

        if (str_starts_with($mimeType, 'image/')) {
            return $this->convertImageToPdf($path, $mimeType);
        }

        Log::info("Unbekanntes Dateiformat für PDF-Konvertierung: {$mimeType}");
        return null;
    }

    /**
     * Konvertiert Word-Dokument zu PDF.
     *
     * Strategie (konfigurierbar via config('wochenplan.pdf_attachments.word_converter')):
     * 1. 'libreoffice' → soffice --headless --convert-to pdf
     * 2. 'phpword'     → PhpWord laden → HTML rendern → DomPDF
     * 3. 'auto'        → LibreOffice versuchen, Fallback auf PhpWord
     */
    private function convertWordToPdf(string $wordPath): ?string
    {
        $strategy = config('wochenplan.pdf_attachments.word_converter', 'auto');
        $tempDir  = storage_path('app/temp');
        $outputPath = $tempDir . '/' . uniqid('word_to_pdf_') . '.pdf';

        // LibreOffice versuchen (wenn konfiguriert oder auto)
        if (in_array($strategy, ['libreoffice', 'auto']) && $this->isLibreOfficeAvailable()) {
            $result = $this->convertWithLibreOffice($wordPath, $tempDir, $outputPath);
            if ($result) return $result;
        }

        // PhpWord Fallback (wenn konfiguriert oder auto)
        if (in_array($strategy, ['phpword', 'auto'])) {
            return $this->convertWithPhpWord($wordPath, $outputPath);
        }

        return null;
    }

    private function convertWithLibreOffice(string $wordPath, string $tempDir, string $outputPath): ?string
    {
        $command = sprintf(
            'soffice --headless --convert-to pdf --outdir %s %s 2>&1',
            escapeshellarg($tempDir),
            escapeshellarg($wordPath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode === 0) {
            $convertedName = pathinfo($wordPath, PATHINFO_FILENAME) . '.pdf';
            $convertedPath = $tempDir . '/' . $convertedName;
            if (file_exists($convertedPath)) {
                if ($convertedPath !== $outputPath) {
                    rename($convertedPath, $outputPath);
                }
                return $outputPath;
            }
        }

        Log::warning('LibreOffice-Konvertierung fehlgeschlagen', [
            'command' => $command,
            'output'  => implode("\n", $output),
            'code'    => $returnCode,
        ]);

        return null;
    }

    private function convertWithPhpWord(string $wordPath, string $outputPath): ?string
    {
        try {
            $phpWord   = \PhpOffice\PhpWord\IOFactory::load($wordPath);
            $htmlWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'HTML');

            $htmlPath = storage_path('app/temp/' . uniqid('word_html_') . '.html');
            $htmlWriter->save($htmlPath);

            $html = file_get_contents($htmlPath);
            @unlink($htmlPath);

            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            file_put_contents($outputPath, $dompdf->output());

            return $outputPath;
        } catch (\Exception $e) {
            Log::warning('PhpWord-Konvertierung fehlgeschlagen: ' . $e->getMessage(), [
                'file' => $wordPath,
            ]);
            return null;
        }
    }

    /**
     * Konvertiert ein Bild in eine PDF-Seite (zentriert auf A4).
     */
    private function convertImageToPdf(string $imagePath, string $mimeType): ?string
    {
        try {
            $outputPath = storage_path('app/temp/' . uniqid('img_to_pdf_') . '.pdf');

            $pdf = new \FPDF();
            $pdf->AddPage();

            $type = match (true) {
                str_contains($mimeType, 'png')  => 'PNG',
                str_contains($mimeType, 'jpeg'),
                str_contains($mimeType, 'jpg')  => 'JPG',
                default => null,
            };

            if ($type === null) return null;

            // Bild zentriert auf A4-Seite einpassen
            [$imgWidth, $imgHeight] = getimagesize($imagePath);
            $pageWidth  = 190;   // A4 abzgl. je 10mm Rand
            $pageHeight = 277;

            $ratio     = min($pageWidth / $imgWidth, $pageHeight / $imgHeight);
            $newWidth  = $imgWidth * $ratio;
            $newHeight = $imgHeight * $ratio;

            $x = (210 - $newWidth) / 2;
            $y = (297 - $newHeight) / 2;

            $pdf->Image($imagePath, $x, $y, $newWidth, $newHeight, $type);
            $pdf->Output('F', $outputPath);

            return $outputPath;
        } catch (\Exception $e) {
            Log::warning('Bild-zu-PDF-Konvertierung fehlgeschlagen: ' . $e->getMessage(), [
                'file' => $imagePath,
            ]);
            return null;
        }
    }

    /**
     * Fügt alle Seiten einer PDF-Datei zum FPDI-Objekt hinzu.
     */
    private function addPdfPages(Fpdi $pdf, string $pdfPath): void
    {
        $pageCount = $pdf->setSourceFile($pdfPath);
        for ($i = 1; $i <= $pageCount; $i++) {
            $template = $pdf->importPage($i);
            $size     = $pdf->getTemplateSize($template);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($template);
        }
    }

    /**
     * Prüft ob LibreOffice auf dem System verfügbar ist.
     */
    private function isLibreOfficeAvailable(): bool
    {
        static $available = null;
        if ($available !== null) return $available;

        exec('soffice --version 2>&1', $output, $returnCode);
        $available = $returnCode === 0;
        return $available;
    }
}








