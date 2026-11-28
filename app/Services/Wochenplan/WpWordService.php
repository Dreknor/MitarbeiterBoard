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
            'taeglicheUebungen',
            'klasse',
            'schueler',
            'formatvorlage',
        ]);

        $formatvorlage = $plan->getEffectiveFormatvorlage();
        $config        = $formatvorlage->layout_config ?? [];

        // Schriftart & Schriftgröße
        $schriftart    = $formatvorlage->schriftart ?? 'Arial';
        $schriftgroesse = $this->fontSizeMap[$formatvorlage->schriftgroesse ?? 'normal'] ?? 11;

        // Seitenränder: in der Formatvorlage in mm gespeichert (Editor und Standardvorlagen)
        $raender = $config['seitenraender'] ?? [];
        $margins = [];
        foreach (['oben' => 20, 'rechts' => 15, 'unten' => 20, 'links' => 15] as $seite => $standardMm) {
            $margins[$seite] = $this->zahl($raender[$seite] ?? null, $standardMm) / 10;
        }

        // Nutzbare Breite (cm) für Prozentangaben
        $quer = ($config['papier']['ausrichtung'] ?? 'portrait') === 'landscape';
        $nutzbar = max(5.0, ($quer ? 29.7 : 21.0) - $margins['links'] - $margins['rechts']);

        // Spaltenbreiten: „15%“ (Editor/Standardvorlagen) oder cm; Schlüssel „fach“ bzw. „fach_breite“
        $spalten = $config['spalten'] ?? [];
        $breite = function (string $schluessel, ?float $standard) use ($spalten, $nutzbar): ?float {
            $wert = $spalten[$schluessel.'_breite'] ?? $spalten[$schluessel] ?? null;
            if ($wert === null || $wert === '') {
                return $standard;
            }
            if (is_string($wert) && str_ends_with(trim($wert), '%')) {
                return round($nutzbar * $this->zahl(rtrim(trim($wert), '%'), 0) / 100, 2);
            }

            return $this->zahl($wert, $standard ?? 0);
        };

        $colFach         = $breite('fach', 3.5);
        $hasDauer        = !empty($spalten['zeige_dauer']) || !empty($spalten['dauer_breite']);
        $colDauer        = $hasDauer ? ($breite('dauer', 2.0) ?: 2.0) : 0;
        $colCheck        = ($spalten['zeige_check_spalte'] ?? true) ? 1.2 : 0;
        $hasKontrolliert = $spalten['zeige_kontrolliert_spalte'] ?? false;
        $colKontrolliert = $hasKontrolliert ? 2.5 : 0;
        $hasUnterschrift = $spalten['zeige_unterschrift_spalte'] ?? true;
        $colUnterschrift = $hasUnterschrift ? $breite('unterschrift', 3.5) : 0;
        // Aufgaben-Breite: Rest der verfügbaren Breite
        $colAufgaben = max(3.0, $nutzbar - $colFach - $colDauer - $colCheck - $colKontrolliert - $colUnterschrift);

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

        // ─── Tägliche Übungen ─────────────────────────────────────────────────
        if (!empty($plan->taegliche_uebungen_aktiv) && $plan->taeglicheUebungen->isNotEmpty()) {
            $section->addText(
                '✏ Tägliche Übungen',
                ['name' => $schriftart, 'size' => $schriftgroesse, 'bold' => true],
                ['spaceBefore' => 120, 'spaceAfter' => 60, 'borderBottom' => ['size' => 6, 'color' => '666666']]
            );

            // Konfiguration aus Formatvorlage
            $tuCfg = $config['taegliche_uebungen'] ?? [];
            $tuLayout       = $tuCfg['layout'] ?? 'wochenweise';
            $tuSizeDatum    = isset($tuCfg['schriftgroesse_datum_pt'])     ? (int) $tuCfg['schriftgroesse_datum_pt']    : max(7, $schriftgroesse - 2);
            $tuSizeAufgaben = isset($tuCfg['schriftgroesse_aufgaben_pt'])  ? (int) $tuCfg['schriftgroesse_aufgaben_pt'] : $schriftgroesse;
            $tuSizeWochentag= isset($tuCfg['schriftgroesse_wochentag_pt']) ? (int) $tuCfg['schriftgroesse_wochentag_pt']: max(7, $schriftgroesse - 1);
            $tuMaxTage      = (int) ($tuCfg['max_tage_pro_tabelle'] ?? 0);

            // Aufgaben-Spalte-Breite: „Xcm" → float, alles andere → Fallback
            $tuAufgabenBreiteRaw = $tuCfg['aufgaben_spalte_breite'] ?? 'auto';
            if ($tuAufgabenBreiteRaw !== 'auto' && preg_match('/^([\d.]+)\s*cm$/i', $tuAufgabenBreiteRaw, $m)) {
                $tuAufgabenBreiteCm = (float) $m[1];
            } else {
                // Vertikal: Datum-Spalte schmaler (3 cm), Horizontal: breite Aufgaben-Spalte (7 cm)
                $tuAufgabenBreiteCm = $tuLayout === 'vertikal' ? 3.0 : 7.0;
            }

            // Wochentage im Planungszeitraum berechnen
            $wordWochentage = [];
            if ($plan->gueltig_von && $plan->gueltig_bis) {
                $wordCur = $plan->gueltig_von->copy();
                while ($wordCur->lte($plan->gueltig_bis)) {
                    if ($wordCur->isWeekday()) {
                        $wordWochentage[] = $wordCur->copy();
                    }
                    $wordCur->addDay();
                }
            }
            $wordTagNamen = ['Mo', 'Di', 'Mi', 'Do', 'Fr'];

            // Tage in Gruppen aufteilen
            $wordTagesGruppen = ($tuMaxTage > 0 && count($wordWochentage) > $tuMaxTage)
                ? array_chunk($wordWochentage, $tuMaxTage)
                : [$wordWochentage];

            // Verfügbare Textbreite für gleichmäßige Übungs-Spalten berechnen
            $sectionStyleObj   = $section->getStyle();
            $verfuegbareTwips  = $sectionStyleObj->getPageSizeW()
                               - $sectionStyleObj->getMarginLeft()
                               - $sectionStyleObj->getMarginRight();
            $uebungCount = $plan->taeglicheUebungen->count();

            // Nummernsymbole ①②③… für kompakte Spaltenköpfe
            $wwNummern = ['①','②','③','④','⑤','⑥','⑦','⑧','⑨','⑩'];

            // Legende vor der Tabelle (für vertikal & wochenweise)
            if (in_array($tuLayout, ['vertikal', 'wochenweise'])) {
                $legendeText = '';
                foreach ($plan->taeglicheUebungen as $idx => $uebung) {
                    $legendeText .= ($wwNummern[$idx] ?? ($idx + 1) . '.') . ' '
                                  . ($uebung->kuerzel ? $uebung->kuerzel.' ' : '') . strip_tags($uebung->aufgabe ?? '') . '   ';
                }
                $section->addText(
                    trim($legendeText),
                    ['name' => $schriftart, 'size' => $tuSizeAufgaben],
                    ['spaceBefore' => 60, 'spaceAfter' => 80]
                );
            }

            foreach ($wordTagesGruppen as $gruppeIdx => $wordGruppe) {
                $dayCount = count($wordGruppe);

                $uebungTable = $section->addTable([
                    'borderSize'  => 6,
                    'borderColor' => '888888',
                    'cellMargin'  => 80,
                ]);

                if ($tuLayout === 'vertikal') {
                    // ── VERTIKAL ──
                    $datumTwip   = Converter::cmToTwip($tuAufgabenBreiteCm);
                    $uebungTwips = $uebungCount > 0
                        ? (int) (($verfuegbareTwips - $datumTwip) / $uebungCount)
                        : Converter::cmToTwip(2.5);

                    $uebungTable->addRow(280);
                    $uebungTable->addCell($datumTwip, ['bgColor' => 'E5E7EB'])
                                ->addText('Datum', ['name' => $schriftart, 'size' => $tuSizeWochentag, 'bold' => true]);
                    foreach ($plan->taeglicheUebungen as $idx => $uebung) {
                        $nummer = $wwNummern[$idx] ?? (($idx + 1) . '.');
                        $uebungTable->addCell($uebungTwips, ['bgColor' => 'E5E7EB'])
                                    ->addText(
                                        $nummer,
                                        ['name' => $schriftart, 'size' => $tuSizeAufgaben, 'bold' => true],
                                        ['alignment' => Jc::CENTER]
                                    );
                    }

                    foreach ($wordGruppe as $wordTag) {
                        $tagKuerzel = $wordTagNamen[$wordTag->dayOfWeek !== 0 ? ($wordTag->dayOfWeek - 1) : 4] ?? '';
                        $tagLabel   = $tagKuerzel . ' ' . $wordTag->format('d.m.');
                        $uebungTable->addRow(280);
                        $uebungTable->addCell($datumTwip)
                                    ->addText($tagLabel, ['name' => $schriftart, 'size' => $tuSizeDatum, 'bold' => true]);
                        for ($u = 0; $u < $uebungCount; $u++) {
                            $uebungTable->addCell($uebungTwips);
                        }
                    }

                } elseif ($tuLayout === 'wochenweise') {
                    // ── WOCHENWEISE: Eine Tabelle, Wochentage als Spaltengruppen, Aufgaben-Nummern als Unter-Spalten ──
                    $wwWochenMap = [];
                    foreach ($wordGruppe as $wordTag) {
                        $wkKey = $wordTag->format('o-W');
                        $dow   = $wordTag->dayOfWeek !== 0 ? ($wordTag->dayOfWeek - 1) : 4;
                        $wwWochenMap[$wkKey][$dow] = $wordTag;
                    }
                    ksort($wwWochenMap);
                    $wwWochenListe = array_values($wwWochenMap);
                    $wwVorhandDow  = [];
                    foreach ($wwWochenMap as $wDays) {
                        foreach (array_keys($wDays) as $d) { $wwVorhandDow[$d] = true; }
                    }
                    ksort($wwVorhandDow);
                    $wwDowListe = array_keys($wwVorhandDow);
                    $wwDowCount = count($wwDowListe);
                    $wwTagVoll  = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag'];

                    // Wochenspalte: mindestens 2.5 cm (≈15% bei A4)
                    $wwWocheTwip  = Converter::cmToTwip(max(2.5, $tuAufgabenBreiteCm));
                    // Rest gleichmäßig auf (Tage × Aufgaben) Spalten
                    $wwGesamtSpal = $wwDowCount * $uebungCount;
                    $wwZellTwip   = $wwGesamtSpal > 0
                        ? (int) (($verfuegbareTwips - $wwWocheTwip) / $wwGesamtSpal)
                        : Converter::cmToTwip(1.0);

                    // ── Kopfzeile 1: Wochentag-Namen (merged über $uebungCount Spalten) ──
                    $uebungTable->addRow(280);
                    $uebungTable->addCell($wwWocheTwip, ['bgColor' => 'E5E7EB'])->addText('');
                    foreach ($wwDowListe as $dow) {
                        $uebungTable->addCell($wwZellTwip * $uebungCount, [
                            'bgColor'  => 'E5E7EB',
                            'gridSpan' => $uebungCount,
                        ])->addText(
                            $wwTagVoll[$dow] ?? '',
                            ['name' => $schriftart, 'size' => $tuSizeWochentag, 'bold' => true],
                            ['alignment' => Jc::CENTER]
                        );
                    }

                    // ── Kopfzeile 2: Aufgaben-Nummern (kompakt, für jeden Tag wiederholt) ──
                    $uebungTable->addRow(240);
                    $uebungTable->addCell($wwWocheTwip, ['bgColor' => 'E5E7EB'])->addText('');
                    foreach ($wwDowListe as $dow) {
                        foreach ($plan->taeglicheUebungen as $idx => $uebung) {
                            $nummer = $wwNummern[$idx] ?? (($idx + 1) . '.');
                            $uebungTable->addCell($wwZellTwip, ['bgColor' => 'E5E7EB'])
                                        ->addText(
                                            $nummer,
                                            ['name' => $schriftart, 'size' => $tuSizeDatum, 'bold' => true],
                                            ['alignment' => Jc::CENTER]
                                        );
                        }
                    }

                    // ── Daten-Zeilen: eine Zeile pro Woche ──
                    foreach ($wwWochenListe as $wIdx => $wDays) {
                        $uebungTable->addRow(280);
                        $uebungTable->addCell($wwWocheTwip)
                                    ->addText(
                                        'Woche ' . ($wIdx + 1),
                                        ['name' => $schriftart, 'size' => $tuSizeWochentag, 'bold' => true]
                                    );
                        foreach ($wwDowListe as $dow) {
                            foreach ($plan->taeglicheUebungen as $uebung) {
                                if (isset($wDays[$dow])) {
                                    $uebungTable->addCell($wwZellTwip);
                                } else {
                                    $uebungTable->addCell($wwZellTwip, ['bgColor' => 'F0F0F0'])
                                                ->addText('–', ['name' => $schriftart, 'size' => $tuSizeDatum], ['alignment' => Jc::CENTER]);
                                }
                            }
                        }
                    }
                    // Keine addText('') nötig – wird am Ende des äußeren foreach gemacht

                } else {
                    // ── HORIZONTAL ──
                    $uebungTable->addRow(280);
                    $uebungTable->addCell(Converter::cmToTwip($tuAufgabenBreiteCm), ['bgColor' => 'E5E7EB'])
                                ->addText('Übung', ['name' => $schriftart, 'size' => $tuSizeAufgaben, 'bold' => true]);
                    foreach ($wordGruppe as $wordTag) {
                        $tagKuerzel = $wordTagNamen[$wordTag->dayOfWeek !== 0 ? ($wordTag->dayOfWeek - 1) : 4] ?? '';
                        $tagLabel   = $tagKuerzel . "\n" . $wordTag->format('d.m.');
                        $uebungTable->addCell(Converter::cmToTwip(1.2), ['bgColor' => 'E5E7EB'])
                                    ->addText($tagLabel, ['name' => $schriftart, 'size' => $tuSizeDatum], ['alignment' => Jc::CENTER]);
                    }

                    foreach ($plan->taeglicheUebungen as $uebung) {
                        $uebungTable->addRow(280);
                        $uebungTable->addCell(Converter::cmToTwip($tuAufgabenBreiteCm))
                                    ->addText(($uebung->kuerzel ? $uebung->kuerzel.' ' : '') . strip_tags($uebung->aufgabe ?? ''), ['name' => $schriftart, 'size' => $tuSizeAufgaben]);
                        for ($d = 0; $d < $dayCount; $d++) {
                            $uebungTable->addCell(Converter::cmToTwip(1.2));
                        }
                    }
                }

                $section->addText('');
            }
        }

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
        if ($colCheck > 0) {
            $table->addCell(Converter::cmToTwip($colCheck), $headerCellStyle)
                  ->addText('✓', $headerFontStyle, $headerParaStyle);
        }
        if ($hasUnterschrift) {
            $table->addCell(Converter::cmToTwip($colUnterschrift), $headerCellStyle)
                  ->addText('Unterschrift', $headerFontStyle, $headerParaStyle);
        }
        if ($hasKontrolliert) {
            $table->addCell(Converter::cmToTwip($colKontrolliert), $headerCellStyle)
                  ->addText('Kontrolliert', $headerFontStyle, $headerParaStyle);
        }

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
            // Empfehlungen für die Freiarbeit stehen in einem eigenen Abschnitt am Ende
            $aufgaben = $planFach->aufgaben->filter(fn($a) => !$a->trashed() && !$a->freiarbeit)->values();
            if ($aufgaben->isEmpty() && $planFach->aufgaben->contains(fn ($a) => $a->freiarbeit)) {
                continue;
            }
            $fachName = trim(($planFach->display_name ?? '').' '.($planFach->symbol ?? ''));

            if ($aufgaben->isEmpty()) {
                // Fach ohne Aufgaben: leere Zeile
                $table->addRow();
                $table->addCell(Converter::cmToTwip($colFach), $fachZellStyle)
                      ->addText(
                          $fachName,
                          $fachFontStyle,
                          ['alignment' => Jc::CENTER]
                      );
                $table->addCell(Converter::cmToTwip($colAufgaben));
                if ($hasDauer) {
                    $table->addCell(Converter::cmToTwip($colDauer));
                }
                if ($colCheck > 0) {
                    $table->addCell(Converter::cmToTwip($colCheck));
                }
                if ($hasUnterschrift) {
                    $table->addCell(Converter::cmToTwip($colUnterschrift));
                }
                if ($hasKontrolliert) {
                    $table->addCell(Converter::cmToTwip($colKontrolliert));
                }
                continue;
            }

            $table->addRow();
            // Fachname-Zelle (vertikal zentriert)
            $table->addCell(Converter::cmToTwip($colFach), $fachZellStyle)
                  ->addText(
                      $fachName,
                      $fachFontStyle,
                      ['alignment' => Jc::CENTER]
                  );

            // Aufgaben-Zelle
            $aufgabenZelle = $table->addCell(Converter::cmToTwip($colAufgaben));
            $nr = 0;
            foreach ($aufgaben as $aufgabe) {
                // wie im PDF: freiwillige Aufgaben mit *, sonst nummeriert (ab zwei Aufgaben)
                $praefix = $aufgabe->freiwillig ? '* ' : ($aufgaben->count() > 1 ? (++$nr).'. ' : '');
                $text = $praefix.strip_tags($aufgabe->aufgabe ?? '');
                $aufgabenZelle->addText($text, $aufgabeFontStyle, $aufgabeParaStyle);
                if (!empty($aufgabe->link)) {
                    $aufgabenZelle->addLink($aufgabe->link, $aufgabe->link, array_merge((array) $aufgabeFontStyle, ['color' => '1D4ED8', 'underline' => 'single', 'size' => 8]), $aufgabeParaStyle);
                }
            }

            // Dauer-Zelle
            if ($hasDauer) {
                $dauerZelle = $table->addCell(Converter::cmToTwip($colDauer));
                foreach ($aufgaben as $aufgabe) {
                    $dauerZelle->addText($aufgabe->dauer ?? '', $aufgabeFontStyle, $aufgabeParaStyle);
                }
            }

            // Haken-Zelle
            if ($colCheck > 0) {
                $table->addCell(Converter::cmToTwip($colCheck));
            }

            // Unterschrift-Zelle
            if ($hasUnterschrift) {
                $table->addCell(Converter::cmToTwip($colUnterschrift));
            }

            // Kontrolliert-Zelle
            if ($hasKontrolliert) {
                $table->addCell(Converter::cmToTwip($colKontrolliert));
            }
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

        // ─── Empfehlungen für die Freiarbeit ──────────────────────────────────
        $freiarbeit = $plan->planFaecher->filter(fn ($pf) => $pf->aufgaben->contains(fn ($a) => $a->freiarbeit));
        if ($freiarbeit->isNotEmpty()) {
            $section->addPageBreak();
            $section->addText('Empfehlungen für die Freiarbeit', ['name' => $schriftart, 'size' => $schriftgroesse + 1, 'bold' => true, 'underline' => 'single'], ['spaceAfter' => 200]);
            foreach ($freiarbeit as $planFach) {
                $section->addText(($planFach->display_name ?: 'Weitere').':', ['name' => $schriftart, 'size' => $schriftgroesse + 1, 'underline' => 'single'], ['spaceBefore' => 160, 'spaceAfter' => 80]);
                $faTabelle = $section->addTable(['borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 60]);
                foreach ($planFach->aufgaben->filter(fn ($a) => $a->freiarbeit) as $aufgabe) {
                    $faTabelle->addRow();
                    $faTabelle->addCell(Converter::cmToTwip(12))->addText(($aufgabe->freiwillig ? '* ' : '').strip_tags($aufgabe->aufgabe ?? ''), ['name' => $schriftart, 'size' => $schriftgroesse]);
                    $faTabelle->addCell(Converter::cmToTwip(1.5));
                    $faTabelle->addCell(Converter::cmToTwip(1.5));
                }
            }
        }

        // ─── Datei speichern ──────────────────────────────────────────────────
        $filename = $this->filename($plan);
        $tempDir  = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        $path     = $tempDir . '/' . uniqid('wp_word_') . '.docx';

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

    /** Zahl aus Konfiguration („15“, 15, „1,5“) – sonst Standardwert. */
    private function zahl(mixed $wert, float|int $standard): float
    {
        if (is_string($wert)) {
            $wert = str_replace(',', '.', trim($wert));
        }

        return is_numeric($wert) ? (float) $wert : (float) $standard;
    }
}
