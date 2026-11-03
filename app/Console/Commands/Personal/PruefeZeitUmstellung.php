<?php

namespace App\Console\Commands\Personal;

use App\Models\personal\Timesheet;
use App\Services\Personal\Zeit\TimesheetService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Prüft vor bzw. nach dem Update der Zeitwirtschaft die Bestandsdaten – ohne etwas zu speichern.
 *
 *  1. Altmonate (vor dem Stichtag, nicht gesperrt): Berechnung nach bisheriger Methode
 *     muss den gespeicherten Werten entsprechen → belegt, dass sich nichts verändert.
 *  2. Erster Monat nach dem Stichtag: Vergleich bisherige vs. neue Methode je Person
 *     → zeigt, wo das neue Arbeitszeitmodell andere Werte liefert (z. B. Teilzeit, Wochenend-Gutschriften).
 */
class PruefeZeitUmstellung extends Command
{
    protected $signature = 'personal:zeit-umstellung-pruefen
                            {--monat= : Monat für den Methodenvergleich (YYYY-MM), Standard: Stichtag}
                            {--nur-abweichungen : Nur Personen mit Unterschieden anzeigen}';

    protected $description = 'Prüft Arbeitszeitnachweise auf Veränderungen durch die Umstellung (liest nur, speichert nichts)';

    public function handle(TimesheetService $timesheets): int
    {
        $stichtag = $timesheets->stichtag();
        $this->info('Stichtag neues Modell: '.$stichtag->format('d.m.Y'));

        // 1. Altmonate
        $alt = Timesheet::with('employe')->whereNull('locked_at')->get()
            ->filter(fn (Timesheet $t) => $t->employe && $timesheets->istHistorisch($t));

        $abweichend = [];
        foreach ($alt as $ts) {
            $neu = $timesheets->berechne($ts);
            if ((int) $neu['working_time_account'] !== (int) $ts->working_time_account
                || abs((float) $neu['holidays_rest'] - (float) $ts->holidays_rest) > 0.01) {
                $abweichend[] = [
                    $ts->employe->name, $ts->monthStart()->format('m/Y'),
                    $this->hm($ts->working_time_account), $this->hm($neu['working_time_account']),
                    $ts->holidays_rest, $neu['holidays_rest'],
                ];
            }
        }

        $this->line('');
        $this->info('1) Nicht gesperrte Altmonate: '.$alt->count().' geprüft, '.count($abweichend).' mit Abweichung zwischen gespeichertem und neu berechnetem Wert.');
        if ($abweichend !== []) {
            $this->warn('   Die gespeicherten Werte waren schon vor dem Update nicht mehr aktuell (letzte Berechnung beim letzten Öffnen/Bearbeiten).');
            $this->warn('   Abgelaufene Altmonate sind eingefroren: sie bleiben unverändert, bis jemand den Monat bearbeitet oder „Neu berechnen“ wählt.');
            $this->warn('   Die Spalte „berechnet“ zeigt, was die bisherige Methode dann ergeben würde.');
            $this->table(['Person', 'Monat', 'Saldo gespeichert', 'Saldo berechnet', 'Rest gespeichert', 'Rest berechnet'], $abweichend);
        }

        // 2. Methodenvergleich
        $monat = $this->option('monat') ? Carbon::createFromFormat('Y-m-d', $this->option('monat').'-01') : $stichtag->copy();
        $zeilen = [];
        foreach (Timesheet::with('employe')->where('year', $monat->year)->where('month', $monat->month)->get() as $ts) {
            if (!$ts->employe) {
                continue;
            }
            $v = $timesheets->vergleiche($ts);
            $diff = (int) $v['neu']['working_time_account'] - (int) $v['bisher']['working_time_account'];
            $restDiff = round((float) $v['neu']['holidays_rest'] - (float) $v['bisher']['holidays_rest'], 1);
            if ($this->option('nur-abweichungen') && $diff === 0 && $restDiff == 0.0) {
                continue;
            }
            $zeilen[] = [
                $ts->employe->name,
                $this->hm($v['bisher']['working_time_account']), $this->hm($v['neu']['working_time_account']), $this->hm($diff),
                $v['bisher']['holidays_rest'], $v['neu']['holidays_rest'], $restDiff,
            ];
        }

        $this->line('');
        $this->info('2) Methodenvergleich für '.$monat->format('m/Y').' ('.count($zeilen).' Nachweise):');
        if ($zeilen === []) {
            $this->line('   Keine Nachweise bzw. keine Unterschiede.');
        } else {
            $this->table(['Person', 'Saldo bisher', 'Saldo neu', 'Diff.', 'Rest bisher', 'Rest neu', 'Diff.'], $zeilen);
        }

        return self::SUCCESS;
    }

    private function hm($sekunden): string
    {
        $minuten = (int) round(((float) $sekunden) / 60);

        return ($minuten < 0 ? '-' : '').intdiv(abs($minuten), 60).':'.str_pad((string) (abs($minuten) % 60), 2, '0', STR_PAD_LEFT);
    }
}
