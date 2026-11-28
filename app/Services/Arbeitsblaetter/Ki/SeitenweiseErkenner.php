<?php

namespace App\Services\Arbeitsblaetter\Ki;

use App\Support\AbStruktur;
use setasign\Fpdi\Fpdi;

/**
 * Lange Blätter abschnittsweise erkennen: mehr als {@see MAX_SEITEN} Seiten (PDF oder Fotos) würden
 * die Antwortlänge der Modelle sprengen („zu umfangreich“). Die Seiten werden in Abschnitte geteilt,
 * einzeln erkannt und wieder zusammengefügt (Seitenzahlen der Abbildungen werden verschoben).
 * Kann ein PDF nicht geteilt werden (FPDI liest nicht jedes PDF), geht es wie bisher im Ganzen.
 */
class SeitenweiseErkenner implements AbErkenner
{
    public const MAX_SEITEN = 3;

    public function __construct(private AbErkenner $innen, private int $maxSeiten = self::MAX_SEITEN)
    {
    }

    public function konfiguriert(): bool
    {
        return $this->innen->konfiguriert();
    }

    public function anbieter(): string
    {
        return $this->innen->anbieter();
    }

    public function modell(): string
    {
        return $this->innen->modell();
    }

    public function nachfrageErkenner(): ?AbErkenner
    {
        $nachfrage = method_exists($this->innen, 'nachfrageErkenner') ? $this->innen->nachfrageErkenner() : null;

        return $nachfrage ? new self($nachfrage, $this->maxSeiten) : null;
    }

    public function erkennen(array $seiten, ?string $textHinweis = null): AbErkennung
    {
        $abschnitte = $this->aufteilen($seiten);
        if (count($abschnitte) <= 1) {
            return $this->innen->erkennen($seiten, $textHinweis);
        }

        $bausteine = [];
        $kosten = 0.0;
        $hinweise = [];
        $gesamt = 0;
        $modell = null;
        foreach ($abschnitte as [$teil, $versatz, $anzahl]) {
            // Die Textebene gilt fürs ganze Dokument – je Abschnitt würde sie mehr verwirren als helfen
            $e = $this->innen->erkennen($teil, null);
            $modell ??= $e->modell;
            $kosten += $e->kostenCent;
            $hinweise = array_merge($hinweise, $e->hinweise);
            $gesamt += $anzahl;
            foreach ($e->struktur['bausteine'] as $b) {
                $bausteine[] = $this->seiteVerschieben($b, $versatz);
            }
        }

        return new AbErkennung(
            // Dubletten/Lösungsteile über Abschnittsgrenzen hinweg (Lösungsblatt steht meist im letzten Abschnitt)
            AbStruktur::normalisieren(['seiten' => $gesamt, 'bausteine' => ErkennerVorgaben::loesungsteilEntfernen($bausteine)]),
            $gesamt,
            round($kosten, 4),
            $this->innen->anbieter(),
            (string) $modell,
            array_values(array_unique($hinweise)),
        );
    }

    /**
     * @return array<int, array{0: array, 1: int, 2: int}> Abschnitte: [Seiten, Seitenversatz, Seitenzahl]
     */
    public function aufteilen(array $seiten): array
    {
        $bilder = array_values(array_filter($seiten, fn ($s) => str_starts_with((string) $s['mime'], 'image/')));
        if (count($bilder) === count($seiten)) {
            if (count($seiten) <= $this->maxSeiten) {
                return [[$seiten, 0, count($seiten)]];
            }

            return array_map(fn ($teil, $i) => [$teil, $i * $this->maxSeiten, count($teil)], array_chunk($seiten, $this->maxSeiten), array_keys(array_chunk($seiten, $this->maxSeiten)));
        }

        if (count($seiten) !== 1 || $seiten[0]['mime'] !== 'application/pdf') {
            return [[$seiten, 0, count($seiten)]];
        }

        try {
            return $this->pdfTeilen($seiten[0]['inhalt']);
        } catch (\Throwable) {
            return [[$seiten, 0, 1]];
        }
    }

    /** @return array<int, array{0: array, 1: int, 2: int}> */
    private function pdfTeilen(string $inhalt): array
    {
        $pfad = storage_path('app/temp/'.uniqid('ab_teilen_', true).'.pdf');
        if (! is_dir(dirname($pfad))) {
            @mkdir(dirname($pfad), 0755, true);
        }
        file_put_contents($pfad, $inhalt);
        try {
            $anzahl = (new Fpdi())->setSourceFile($pfad);
            if ($anzahl <= $this->maxSeiten) {
                return [[[['inhalt' => $inhalt, 'mime' => 'application/pdf']], 0, $anzahl]];
            }

            $abschnitte = [];
            for ($start = 1; $start <= $anzahl; $start += $this->maxSeiten) {
                $pdf = new Fpdi();
                $pdf->setSourceFile($pfad);
                $ende = min($anzahl, $start + $this->maxSeiten - 1);
                for ($nr = $start; $nr <= $ende; $nr++) {
                    $vorlage = $pdf->importPage($nr);
                    $groesse = $pdf->getTemplateSize($vorlage);
                    $pdf->AddPage($groesse['orientation'], [$groesse['width'], $groesse['height']]);
                    $pdf->useTemplate($vorlage);
                }
                $abschnitte[] = [[['inhalt' => $pdf->Output('S'), 'mime' => 'application/pdf']], $start - 1, $ende - $start + 1];
            }

            return $abschnitte;
        } finally {
            @unlink($pfad);
        }
    }

    private function seiteVerschieben(array $b, int $versatz): array
    {
        if ($versatz && $b['typ'] === 'bild' && ! empty($b['quelle']['seite'])) {
            $b['quelle']['seite'] += $versatz;
        }
        if (! empty($b['kinder'])) {
            $b['kinder'] = array_map(fn ($k) => $this->seiteVerschieben($k, $versatz), $b['kinder']);
        }

        return $b;
    }
}
