<?php

namespace App\Services;

use App\Models\PaedDiaryElterngespraech;
use App\Support\PdfLayout;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protokoll eines Elterngesprächs als PDF (Web und API v1) – mit Briefkopf, Unterschriftszeilen und
 * „ENTWURF“-Wasserzeichen, solange das Gespräch nicht abgeschlossen ist.
 * Vorbereitungsnotizen erscheinen nur, wenn `vorbereitung_im_protokoll` gesetzt ist.
 */
class PaedDiaryElterngespraechProtokoll
{
    public function __construct(private PdfLayout $layout)
    {
    }

    /** Daten für die View pdf.elterngespraech */
    public function daten(PaedDiaryElterngespraech $g): array
    {
        $g->loadMissing(['schueler', 'klasse', 'user', 'teilnehmer', 'vereinbarungen']);
        $schueler = $g->schueler;
        $entwurf = $g->status !== PaedDiaryElterngespraech::STATUS_ABGESCHLOSSEN;

        // Vorbereitung erscheint nur, wenn sie für dieses Gespräch ausdrücklich ins Protokoll soll
        $mitVorbereitung = (bool) $g->vorbereitung_im_protokoll;

        $punkte = collect($g->gespraechspunkte ?? [])
            ->filter(fn ($p) => trim((string) ($p['title'] ?? '')) !== '' || trim((string) ($p['result'] ?? '')) !== ''
                || ($mitVorbereitung && trim((string) ($p['preparation'] ?? '')) !== ''))
            ->map(fn ($p) => $mitVorbereitung ? $p : array_merge($p, ['preparation' => '']))
            ->values();

        // Abschnitte in fester Reihenfolge; leere entfallen und werden fortlaufend nummeriert
        $abschnitte = collect([
            'anlass' => filled($g->anlass),
            'vorbereitung' => $mitVorbereitung && filled($g->vorbereitung_notizen),
            'punkte' => $punkte->isNotEmpty(),
            'verlauf' => filled($g->verlauf),
            'vereinbarungen' => $g->vereinbarungen->isNotEmpty(),
            'naechster_termin' => (bool) $g->naechster_termin,
        ])->filter()->keys()->values()->flip()->map(fn ($i) => $i + 1);

        $zeit = $g->beginn ? substr($g->beginn, 0, 5) . ($g->ende ? '–' . substr($g->ende, 0, 5) : '') . ' Uhr' : null;

        return [
            'g' => $g,
            'schueler' => $schueler,
            'klasse' => $g->klasse ?? $schueler?->klasse,
            'kopf' => $this->layout->schulkopf(),
            'entwurf' => $entwurf,
            'abgesagt' => $g->status === PaedDiaryElterngespraech::STATUS_ABGESAGT,
            'zeit' => $zeit,
            'form' => PaedDiaryElterngespraech::FORMEN[$g->form] ?? $g->form,
            'punkte' => $punkte,
            'abschnitte' => $abschnitte,
            'stand' => now(),
        ];
    }

    public function pdfResponse(PaedDiaryElterngespraech $g): Response
    {
        $daten = $this->daten($g);
        $pdf = Pdf::loadView('pdf.elterngespraech', $daten)
            ->setPaper('a4')
            ->setOption('enable_font_subsetting', true);

        $folgekopf = $daten['kopf']['name'] . '  ·  Protokoll Elterngespräch – '
            . $daten['schueler']?->vorname . ' ' . $daten['schueler']?->nachname . ', ' . $g->datum->format('d.m.Y');
        $inhalt = $this->layout->rendern($pdf, $daten['entwurf'] ? 'ENTWURF' : null, ['x' => 97, 'y' => 47], $folgekopf);

        return response($inhalt, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $this->dateiname($g) . '"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /** Elterngespraech_<Nachname>_<Vorname>_<Datum>.pdf (ASCII) */
    public function dateiname(PaedDiaryElterngespraech $g): string
    {
        $teil = fn (?string $wert) => trim(preg_replace('/[^A-Za-z0-9\-]+/', '-', Str::ascii((string) $wert)), '-') ?: 'Unbekannt';

        return sprintf(
            'Elterngespraech_%s_%s_%s.pdf',
            $teil($g->schueler?->nachname),
            $teil($g->schueler?->vorname),
            $g->datum->toDateString()
        );
    }
}
