<?php

namespace Database\Seeders;

use App\Models\Ergebnisboard;
use App\Models\User;
use App\Services\Ergebnisboards\ErgebnisboardService;
use Illuminate\Database\Seeder;

/**
 * Legt die Ergebnisse des Pädagogischen Tages (ehemals statische Infografik) als Ergebnisboard an.
 * Nur manuell ausführen:
 *   php artisan db:seed --class=ErgebnisboardBeispielSeeder
 * Ersteller: ERGEBNISBOARD_USER_ID (Umgebungsvariable) oder die erste Person mit `manage result boards`, sonst User #1.
 * Läuft nicht doppelt (gleicher Titel wird übersprungen).
 */
class ErgebnisboardBeispielSeeder extends Seeder
{
    public const TITEL = 'Ergebnisse des Pädagogischen Tages';

    public function run(ErgebnisboardService $service): void
    {
        if (Ergebnisboard::query()->where('titel', self::TITEL)->exists()) {
            $this->command?->info('Board existiert bereits – übersprungen.');

            return;
        }

        $user = User::find((int) env('ERGEBNISBOARD_USER_ID'))
            ?? User::permission('manage result boards')->first()
            ?? User::query()->orderBy('id')->first();

        $board = $service->anlegen([
            'titel' => self::TITEL,
            'untertitel' => 'Priorisierung der Maßnahmen nach Aufwand und pädagogischem Ertrag – von der Rahmung zur Zielskizze',
            'kopfzeile' => 'Gesamtkollegium • Schuljahr 2026/27',
            'fusszeile' => 'Pinnwand-Dokumentation • Pädagogischer Tag',
            'vorlage' => 'aufwand_ertrag',
        ], $user);

        // Unterbereich „Schwerpunkt: Ganztag leben“ im Feld Cream of the Crop
        $bereiche = $board->bereiche;
        $bereiche[] = ['titel' => 'Schwerpunkt: „Ganztag leben“', 'eltern' => 'cream', 'farbe' => 'gelb', 'raster' => 2];
        $board = $service->bereicheSpeichern($board, $bereiche);
        $ganztag = collect($board->bereiche)->firstWhere('eltern', 'cream')['schluessel'];

        $karten = [
            'rotten' => [
                ['Rückzugsraum für Kinder', '(eher OS)'],
            ],
            'weeds' => [
                ['Sinnvolle Gestaltung der Gremien & Weiterentwicklung', '(Streitschlichter, Klassenrat, …)'],
            ],
            $ganztag => [
                ['Individuelles Lernen', 'im Schulalltag sinnvoll organisieren (→ Ziel B.1)'],
                ['Verbindliche Lernzeit', '= längere Freiarbeit (FA)'],
                ['Offener Beginn (7:30 – 8:30)', 'Ruhiger, flexibler Start in den Tag'],
                ['Lebenspraktisches Tun', 'Fest in der Tagesstruktur verankern'],
                ['Input = Einführung + angeleitete / mögliche Darbietung', null, null, true],
            ],
            'uebergang' => [
                ['Effektive Zusammenarbeit', 'DB, Arbeitsgruppen, LG-Austausch, …'],
                ['Softskills für alle stärken', 'Grundlage für gute Teamarbeit'],
                ['Präsenz eines Schulpsychologen', 'Team unterstützen → mehr Professionen ins Haus'],
                ['Graduierungssystem etablieren', 'in allen Gruppen → Persönlichkeitsentwicklung'],
                ['Mehr / größere Räume schaffen', 'Für Inklusion / Material sowie Räume für den Austausch mit Kolleg*innen & Eltern', null, true],
            ],
            'quick_wins' => [
                ['Beziehungsarbeit', 'Als zentralen Schwerpunkt stärken'],
                ['Mehr Klassenaktivitäten', '(Gefühle, Gemeinschaft, …)'],
                ['Klare Elternkommunikation', 'Ziele & Werte → Bewusstsein stärken'],
                ['KT-Kommunikationsstruktur', 'Im Team verbessern & Wertschätzung fördern'],
                ['Außerschulische Aktionen', 'stärken & umsetzen (LpT)'],
                ['Austauschzeiten', 'Verlässlich im Alltag verankern'],
            ],
            'seite' => [
                ['Finanzkonzept / Anderes', 'Finanzielle Rahmenbedingungen klären', 'Leitung'],
                ['Neue Räume ermöglichen?', 'Raumkapazitäten prüfen & erschließen', 'Leitung'],
                ['Personalgespräche?', '→ Ziele & Zusammenarbeit im Team → Entwicklung', 'Personal & Team'],
            ],
        ];

        foreach ($karten as $bereich => $liste) {
            foreach ($liste as $k) {
                $service->karteSpeichern($board->fresh(), null, [
                    'bereich' => $bereich,
                    'titel' => $k[0],
                    'zusatz' => $k[1] ?? null,
                    'etikett' => $k[2] ?? null,
                    'breit' => (bool) ($k[3] ?? false),
                ]);
            }
        }

        $this->command?->info('Board angelegt: '.route('ergebnisboards.show', $board));
    }
}
