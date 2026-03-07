<?php

namespace App\Services\Wochenplan;

use App\Models\Wochenplan\WpPlan;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;
use PhpOffice\PhpWord\SimpleType\TblWidth;
use PhpOffice\PhpWord\Style\Language;
use PhpOffice\PhpWord\IOFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class WpWordService
{
    /**
     * Schriftgrößen-Mapping für Formatvorlagen.
     */
    private array $fontSizeMap = [
        'normal'     => 11,
        'gross'      => 14,
        'sehr_gross' => 18,
    ];

    /**
     * Generiert eine DOCX-Datei und gibt den Pfad zurück.
     */
    public function generate(WpPlan $plan): string
    {
        $plan->load([
            'planFaecher.aufgaben',
            'planFaecher.fach',
            'klasse',
            'schueler',
            'formatvorlage',
        ]);

        $formatvorlage = $plan->getEffectiveFormatvorlage();
        $config        = $formatvorlage->layout_config ?? [];

        // Schriftart & Schriftgröße
        $schriftart    = $formatvorlage->schriftart ?? 'Arial';
        $schriftgroesse = $this->fontSizeMap[$formatvorlage->schriftgroesse ?? 'normal'] ?? 11;

        // Seitenränder (cm)
        $margins = $config['seitenraender'] ?? ['oben' => 2, 'rechts' => 2, 'unten' => 2, 'links' => 2];

        // Spaltenbreiten (cm)
        $spalten   = $config['spalten'] ?? [];
        $hasDauer  = !empty($spalten['dauer_breite']) || false;

        // Spaltenbreiten in cm
        $colFach         = $spalten['fach_breite'] ?? 3.5;
        $colAufgaben     = $spalten['aufgaben_breite'] ?? ($hasDauer ? 8.5 : 9.0);
        $colDauer        = $spalten['dauer_breite'] ?? 0;
        $colCheck        = 1.0;
        $colUnterschrift = $spalten['unterschrift_breite'] ?? 3.5;

        // ─── PhpWord Instanz ──────────────────────────────────────────────────
        $phpWord = new PhpWord();
        $phpWord->getSettings()->setThemeFontLang(new Language(Language::DE_DE));
        $phpWord->setDefaultFontName($schriftart);
        $phpWord->setDefaultFontSize($schriftgroesse);

        // ─── Section (Seitenränder) ───────────────────────────────────────────
        $section = $phpWord->addSection([
            'marginLeft'   => Converter::cmToTwip($margins['links'] ?? 2),
            'marginRight'  => Converter::cmToTwip($margins['rechts'] ?? 2),
            'marginTop'    => Converter::cmToTwip($margins['oben'] ?? 2),
            'marginBottom' => Converter::cmToTwip($margins['unten'] ?? 2),
        ]);

        // ─── Tab-Style für Überschrift (links/rechts ausgerichtet) ────────────
        $sectionStyle = $section->getStyle();
        $textBreite   = $sectionStyle->getPageSizeW()
                      - $sectionStyle->getMarginRight()
                      - $sectionStyle->getMarginLeft();

        $phpWord->addParagraphStyle('wp_leftRight', [
            'tabs' => [
                new \PhpOffice\PhpWord\Style\Tab('right', $textBreite),
            ],
        ]);

        // ─── Überschrift ──────────────────────────────────────────────────────
        $titelFontStyle = [
            'name'      => $schriftart,
            'size'      => $schriftgroesse + 2,
            'bold'      => true,
            'underline' => \PhpOffice\PhpWord\Style\Font::UNDERLINE_SINGLE,
        ];

        $kontextText = '';
        if ($plan->isSchuelerplan() && $plan->schueler) {
            $kontextText = $plan->schueler->vorname . ' ' . $plan->schueler->nachname;
        } elseif ($plan->klasse) {
            $kontextText = $plan->klasse->name;
        }

        $ueberschrift = $plan->name;
        if ($plan->gueltig_von && $plan->gueltig_bis) {
            $ueberschrift .= ' vom ' . $plan->gueltig_von->format('d.m.')
                           . ' bis ' . $plan->gueltig_bis->format('d.m.Y');
        }
        if ($kontextText) {
            $ueberschrift .= "\t" . $kontextText;
        }

        $section->addText($ueberschrift, $titelFontStyle, 'wp_leftRight');

        // ─── Name-Feld ────────────────────────────────────────────────────────
        $nameFontStyle = ['name' => $schriftart, 'size' => $schriftgroesse];

        if ($plan->isSchuelerplan() && $plan->schueler) {
            $section->addText(
                'Name: ' . $plan->schueler->vorname . ' ' . $plan->schueler->nachname,
                $nameFontStyle,
                ['spaceBefore' => 200]
            );
        } else {
            $section->addText(
                'Name: ................................................................',
                $nameFontStyle,
                ['spaceBefore' => 200]
            );
        }
        $section->addText('');

        // ─── Tabelle ──────────────────────────────────────────────────────────
        $tableStyle = [
            'borderSize'  => 6,
            'borderColor' => '3D3D3D',
            'cellMargin'  => 80,
            'unit'        => TblWidth::TWIP,
        ];

        $headerCellStyle = [
            'bgColor' => 'E5E7EB',
            'valign'  => 'center',
        ];

        $headerFontStyle = [
            'name'  => $schriftart,
            'size'  => $schriftgroesse - 1,
            'bold'  => true,
        ];

        $headerParaStyle = [
            'alignment' => Jc::CENTER,
        ];

        $table = $section->addTable($tableStyle);

        // Kopfzeile
        $table->addRow(300);
        $table->addCell(Converter::cmToTwip($colFach), $headerCellStyle)
              ->addText('Fach', $headerFontStyle, $headerParaStyle);
        $table->addCell(Converter::cmToTwip($colAufgaben), $headerCellStyle)
              ->addText('Aufgaben', $headerFontStyle, $headerParaStyle);
        if ($hasDauer) {
            $table->addCell(Converter::cmToTwip($colDauer), $headerCellStyle)
                  ->addText('Dauer', $headerFontStyle, $headerParaStyle);
        }
        $table->addCell(Converter::cmToTwip($colCheck), $headerCellStyle)
              ->addText('✓', $headerFontStyle, $headerParaStyle);
        $table->addCell(Converter::cmToTwip($colUnterschrift), $headerCellStyle)
              ->addText('Unterschrift', $headerFontStyle, $headerParaStyle);

        // Fach-Zeilen
        $fachFontStyle = [
            'name' => $schriftart,
            'size' => $schriftgroesse,
            'bold' => true,
        ];

        $aufgabeFontStyle = [
            'name' => $schriftart,
            'size' => $schriftgroesse,
        ];

        $aufgabeParaStyle = [
            'spaceBefore' => 60,
            'spaceAfter'  => 60,
        ];

        $fachZellStyle = [
            'valign' => 'center',
        ];

        foreach ($plan->planFaecher as $planFach) {
            $aufgaben = $planFach->aufgaben->filter(fn($a) => !$a->trashed());

            if ($aufgaben->isEmpty()) {
                // Fach ohne Aufgaben: leere Zeile
                $table->addRow();
                $table->addCell(Converter::cmToTwip($colFach), $fachZellStyle)
                      ->addText(
                          $planFach->custom_name ?? ($planFach->fach?->name ?? ''),
                          $fachFontStyle,
                          ['alignment' => Jc::CENTER]
                      );
                $table->addCell(Converter::cmToTwip($colAufgaben));
                if ($hasDauer) {
                    $table->addCell(Converter::cmToTwip($colDauer));
                }
                $table->addCell(Converter::cmToTwip($colCheck));
                $table->addCell(Converter::cmToTwip($colUnterschrift));
                continue;
            }

            $table->addRow();
            // Fachname-Zelle (vertikal zentriert)
            $table->addCell(Converter::cmToTwip($colFach), $fachZellStyle)
                  ->addText(
                      $planFach->custom_name ?? ($planFach->fach?->name ?? ''),
                      $fachFontStyle,
                      ['alignment' => Jc::CENTER]
                  );

            // Aufgaben-Zelle
            $aufgabenZelle = $table->addCell(Converter::cmToTwip($colAufgaben));
            foreach ($aufgaben as $aufgabe) {
                $text = strip_tags($aufgabe->aufgabe ?? '');
                $aufgabenZelle->addText($text, $aufgabeFontStyle, $aufgabeParaStyle);
            }

            // Dauer-Zelle
            if ($hasDauer) {
                $dauerZelle = $table->addCell(Converter::cmToTwip($colDauer));
                foreach ($aufgaben as $aufgabe) {
                    $dauerZelle->addText($aufgabe->dauer ?? '', $aufgabeFontStyle, $aufgabeParaStyle);
                }
            }

            // Haken-Zelle
            $table->addCell(Converter::cmToTwip($colCheck));

            // Unterschrift-Zelle
            $table->addCell(Converter::cmToTwip($colUnterschrift));
        }

        $section->addText('');

        // ─── Selbsteinschätzung ───────────────────────────────────────────────
        if ($plan->selbsteinschaetzung > 0) {
            $section->addText(
                'Wie hast du gearbeitet?',
                ['name' => $schriftart, 'size' => $schriftgroesse, 'bold' => true],
                ['spaceBefore' => 200]
            );

            $bewertungsTable = $section->addTable([
                'borderSize'  => 6,
                'borderColor' => '3D3D3D',
                'cellMargin'  => 80,
            ]);
            $bewertungsRow = $bewertungsTable->addRow(400);

            if ($plan->selbsteinschaetzung === 1) {
                // Smileys (Text-Darstellung, da Bilder pfadabhängig)
                foreach (['😔', '😐', '😊'] as $smiley) {
                    $bewertungsRow->addCell(2200, ['valign' => 'center'])
                                  ->addText($smiley, ['size' => 20], ['alignment' => Jc::CENTER]);
                }
            } elseif ($plan->selbsteinschaetzung === 2) {
                // Skala 1–10
                for ($x = 1; $x <= 10; $x++) {
                    $bewertungsRow->addCell(800, ['valign' => 'center'])
                                  ->addText(
                                      (string) $x,
                                      ['name' => $schriftart, 'size' => $schriftgroesse],
                                      ['alignment' => Jc::CENTER, 'spacingBefore' => 240]
                                  );
                }
            }
        }

        // ─── Unterschriftszeilen ──────────────────────────────────────────────
        if (!empty($config['unterschrift'])) {
            $section->addText('');
            $section->addText(
                'Unterschrift Lehrer: ................................................................',
                ['name' => $schriftart, 'size' => $schriftgroesse],
                ['spaceBefore' => 200]
            );
        }

        // ─── Datei speichern ──────────────────────────────────────────────────
        $filename = $this->filename($plan);
        $path     = storage_path($filename);

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($path);

        return $path;
    }

    /**
     * Gibt die generierte DOCX als Download zurück und löscht die Datei danach.
     */
    public function download(WpPlan $plan): BinaryFileResponse
    {
        $path = $this->generate($plan);

        return response()->download($path, $this->filename($plan))->deleteFileAfterSend(true);
    }

    /**
     * Erstellt einen sinnvollen Dateinamen für die DOCX-Datei.
     */
    public function filename(WpPlan $plan): string
    {
        $name = str_replace([' ', '/'], '_', $plan->name ?? 'Wochenplan');
        $name = preg_replace('/[^A-Za-z0-9_\-äöüÄÖÜß]/', '', $name);

        if ($plan->isSchuelerplan() && $plan->schueler) {
            $name .= '_' . $plan->schueler->vorname . '_' . $plan->schueler->nachname;
        } elseif ($plan->klasse) {
            $name .= '_' . $plan->klasse->name;
        }

        return $name . '.docx';
    }
}

