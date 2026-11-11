<?php

namespace App\Exports;

use App\Services\KrankmeldungService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Statistik-Blatt: Kennzahlen, Monatsverlauf, Wochentage, Dauerverteilung.
 */
class SickNotesStatistikExport implements FromArray, WithTitle, ShouldAutoSize
{
    public function __construct(
        private readonly KrankmeldungService $service,
        private readonly Collection $meldungen,
        private readonly array $filter,
    ) {
    }

    public function array(): array
    {
        $k = $this->service->kennzahlen($this->meldungen, $this->filter);
        $verlauf = $this->service->monatsverlauf($this->meldungen, $this->filter);

        $zeilen = [
            ['Zeitraum', $this->filter['von']->format('d.m.Y').' – '.$this->filter['bis']->format('d.m.Y')],
            [],
            ['Kennzahlen'],
            ['Krankmeldungen', $k['faelle']],
            ['Krankheitstage (Arbeitstage im Zeitraum)', $k['tage']],
            ['Betroffene Personen', $k['personen']],
            ['Ø Dauer je Krankmeldung (Arbeitstage)', $k['durchschnitt']],
            ['Ø Krankheitstage je betroffener Person', $k['tage_je_person']],
            ['Kurzerkrankungen ohne Schein', $k['kurz'].' ('.$k['kurz_anteil'].' %)'],
            ['Langzeit (über 30 Arbeitstage)', $k['langzeit']],
            ['Krankenschein fehlt', $k['fehlt']],
            [],
            ['Monatsverlauf (Arbeitstage)', 'Krankmeldungen (Beginn)', 'Tage gesamt', ...$verlauf['gruende']],
        ];

        foreach ($verlauf['monate'] as $monat) {
            $zeilen[] = [$monat['label'], $monat['faelle'], $monat['gesamt'], ...array_values($monat['gruende'])];
        }

        $zeilen[] = [];
        $zeilen[] = ['Beginn nach Wochentag', 'Krankmeldungen'];
        foreach ($this->service->wochentage($this->meldungen) as $tag => $anzahl) {
            $zeilen[] = [$tag, $anzahl];
        }

        $zeilen[] = [];
        $zeilen[] = ['Dauer (Arbeitstage)', 'Krankmeldungen'];
        foreach ($this->service->dauerverteilung($this->meldungen) as $gruppe => $anzahl) {
            $zeilen[] = [$gruppe, $anzahl];
        }

        return $zeilen;
    }

    public function title(): string
    {
        return 'Statistik';
    }
}
