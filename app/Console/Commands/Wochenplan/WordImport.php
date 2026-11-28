<?php

namespace App\Console\Commands\Wochenplan;

use App\Models\User;
use App\Services\Wochenplan\Import\WpImportService;
use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

/**
 * Importiert Word-Wochenpläne (.docx nach der Papiervorlage) aus Dateien oder Ordnern.
 * Ohne --ausfuehren wird nur angezeigt, was erkannt wurde. Klassen kommen aus der Überschrift
 * („Klasse 2 Her/Wi“ → Herbst 2, Winter 2) oder aus --klasse; Pläne mit Namen werden Kinderplänen
 * zugeordnet, wenn das Kind eindeutig gefunden wird. Vorhandene Pläne werden übersprungen.
 */
class WordImport extends Command
{
    protected $signature = 'wochenplan:import
        {pfade* : .docx-Dateien oder Ordner (rekursiv)}
        {--user= : ID der Person, unter der die Pläne angelegt werden (braucht Zugriff auf die Klassen)}
        {--klasse=* : Ziel-Klasse(n) für Lerngruppen-Pläne (ID oder Name), statt aus der Überschrift}
        {--ausfuehren : Pläne wirklich anlegen}';

    protected $description = 'Importiert Word-Wochenpläne (.docx) in den Wochenplan';

    public function handle(WpImportService $import): int
    {
        $user = User::find($this->option('user'));
        if (! $user) {
            $this->error('Bitte mit --user=<ID> angeben, unter wem die Pläne angelegt werden.');

            return self::FAILURE;
        }

        $dateien = [];
        foreach ($this->argument('pfade') as $pfad) {
            if (is_dir($pfad)) {
                foreach (Finder::create()->files()->in($pfad)->name(['*.docx', '*.doc'])->notName('~$*')->sortByName() as $f) {
                    $dateien[] = ['name' => $f->getRelativePathname(), 'pfad' => $f->getRealPath()];
                }
            } elseif (is_file($pfad)) {
                $dateien[] = ['name' => basename($pfad), 'pfad' => $pfad];
            } else {
                $this->warn('Nicht gefunden: '.$pfad);
            }
        }

        $klassenIds = collect($this->option('klasse'))->map(fn ($k) => is_numeric($k)
            ? (int) $k
            : \App\Models\Klasse::where('name', $k)->value('id'))->filter()->all();

        $vorschau = $import->vorschau($dateien, $user, $klassenIds);
        $auswahl = [];
        $zeilen = [];
        foreach ($vorschau as $datei) {
            if ($datei['fehler']) {
                $zeilen[] = [$datei['name'], '–', '–', '–', '–', 'FEHLER: '.$datei['fehler']];

                continue;
            }
            foreach ($datei['hinweise'] as $h) {
                $zeilen[] = [$datei['name'], '–', '–', '–', '–', $h];
            }
            foreach ($datei['plaene'] as $p) {
                $ziel = $p['kind']
                    ? ($p['schueler_id'] ? collect($p['kandidaten'])->firstWhere('id', $p['schueler_id'])['name'] ?? $p['kind'] : '? '.$p['kind'])
                    : \App\Models\Klasse::whereIn('id', $p['klassen'])->pluck('name')->implode(', ');
                $ok = ! $p['vorhanden'] && $p['von'] && ($p['kind'] ? $p['schueler_id'] : $p['klassen']);
                $auswahl[$p['schluessel']] = ['importieren' => $ok];
                $zeilen[] = [
                    $datei['name'], $p['name'], ($p['von'] ?? '?').' – '.($p['bis'] ?? '?'), $ziel ?: '–',
                    $p['anzahl']['aufgaben'].' A / '.$p['anzahl']['freiarbeit'].' FA / '.$p['anzahl']['uebungen'].' TÜ',
                    $p['vorhanden'] ? 'schon vorhanden' : implode(' ', $p['hinweise']),
                ];
            }
        }
        $this->table(['Datei', 'Plan', 'Zeitraum', 'Klasse / Kind', 'Inhalt', 'Hinweis'], $zeilen);

        if (! $this->option('ausfuehren')) {
            $this->info(count(array_filter($auswahl, fn ($a) => $a['importieren'])).' Plan/Pläne würden angelegt. Mit --ausfuehren wirklich importieren.');

            return self::SUCCESS;
        }

        $ergebnis = $import->importieren($vorschau, $auswahl, $user);
        foreach ($ergebnis['meldungen'] as $m) {
            $this->warn($m);
        }
        $this->info($ergebnis['angelegt'].' Plan/Pläne angelegt, '.$ergebnis['uebersprungen'].' übersprungen.');

        return self::SUCCESS;
    }
}
