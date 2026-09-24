<?php

namespace App\Services;

use App\Models\PaedDiaryAppointment;
use App\Models\PaedDiaryClassDayPause;
use App\Models\PaedDiaryColumn;
use App\Models\PaedDiaryColumnValue;
use App\Models\PaedDiaryEntry;
use App\Models\PaedDiaryEntryPause;
use App\Models\PaedDiarySchuelerAbsence;
use App\Models\PaedDiaryTask;
use App\Models\Schueler;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Gemeinsame Logik der Wochenansicht (Kalender) des Pädagogischen Tagebuchs –
 * genutzt vom Web-Frontend (PaedDiaryController) und von der API v1 (Pädagogen-App).
 *
 * Die Methoden liefern Modelle/Collections; die Controller formatieren die Antwort selbst.
 */
class PaedDiaryCalendarService
{
    /**
     * Lädt alle Daten einer Schulwoche (Mo–Fr) für die übergebenen Klassen.
     *
     * Nebenwirkung (wie bisher im Web): offene Einträge werden an Ferientagen und an
     * Klassen-Tagespausen automatisch pausiert.
     *
     * @param Collection $klassen Klassen-Modelle (bereits auf Zugriff geprüft)
     * @return array{
     *   week_start: Carbon, week_end: Carbon, days: Collection, schueler: Collection, columns: Collection,
     *   column_values: Collection, entries: Collection, tasks: Collection, pauses: Collection,
     *   absences: Collection, class_day_pauses: Collection
     * }
     */
    public function loadWeek(Collection $klassen, Carbon $weekStart): array
    {
        $weekStart = $weekStart->copy()->startOfWeek();
        $periodEnd = $weekStart->copy()->addDays(4);
        $period = CarbonPeriod::create($weekStart, $periodEnd);
        $days = collect();
        $ferienDates = [];

        foreach ($period as $date) {
            $ferienInfo = is_ferien($date);
            $isFerienTag = !is_null($ferienInfo);

            // Sicherer Zugriff auf ferien_name - kann Objekt oder Array sein
            $ferienName = null;
            if ($isFerienTag) {
                if (is_object($ferienInfo)) {
                    $ferienName = $ferienInfo->name ?? $ferienInfo->slug ?? 'Ferien';
                } elseif (is_array($ferienInfo)) {
                    $ferienName = $ferienInfo['name'] ?? $ferienInfo['slug'] ?? 'Ferien';
                } else {
                    $ferienName = 'Ferien';
                }
            }

            $days->push([
                'date' => $date->toDateString(),
                'label' => $date->format('D d.m.'),
                'is_ferien' => $isFerienTag,
                'ferien_name' => $ferienName
            ]);

            if ($isFerienTag) {
                $ferienDates[] = $date->toDateString();
            }
        }

        // Schüler aller Klassen laden
        $schueler = Schueler::whereIn('klasse_id', $klassen->pluck('id'))->with('grading_stage')->orderBy('klasse_id')->orderBy('vorname')->orderBy('nachname')->get(['id', 'vorname', 'nachname', 'grading_stage_id', 'klasse_id']);
        $schuelerIds = $schueler->pluck('id');

        // Spaltenwerte der Woche zuerst schülerbasiert laden (unabhängig von der aktuellen
        // Klasse!), damit auch Werte aus vorherigen Schuljahren/Klassen erhalten bleiben,
        // wenn Lehrer in der Wochenansicht in die Vergangenheit zurückblättern.
        $columnValues = PaedDiaryColumnValue::whereIn('schueler_id', $schuelerIds)
            ->whereBetween('datum', [$weekStart->toDateString(), $periodEnd->toDateString()])
            ->get();
        $historicalColumnIds = $columnValues->pluck('paed_diary_column_id')->unique();

        // Spalten aller Klassen vereinigt: aktuelle Klassenspalten + alle Spalten, für die in
        // dieser Woche tatsächlich historische Werte existieren (auch aus einer früheren Klasse
        // der Schüler) - so bleibt die vollständige Historie für Lehrer jederzeit einsehbar.
        $columns = PaedDiaryColumn::where(function ($q) use ($klassen, $historicalColumnIds) {
                $q->whereIn('klasse_id', $klassen->pluck('id'))
                  ->orWhereIn('id', $historicalColumnIds);
            })
            ->where(function ($q) use ($weekStart) {
                $q->whereNull('deactivated_from')->orWhere('deactivated_from', '>', $weekStart->toDateString());
            })
            ->orderBy('klasse_id')->orderBy('sort_order')->get();

        // Einträge der aktuellen Woche laden.
        // WICHTIG: NICHT (mehr) nach klasse_id filtern, sondern nach den aktuell in dieser
        // Klasse/Gruppe befindlichen Schülern (schueler_id)! Beim Schuljahreswechsel bleibt die
        // Schueler-ID erhalten, aber klasse_id wird auf die neue Klasse aktualisiert - alte
        // Einträge (z.B. aus dem letzten Schuljahr, wenn man in der Wochenansicht zurückblättert)
        // referenzieren noch die damalige klasse_id. Eine klasse_id-Filterung würde diese
        // Einträge sonst unsichtbar machen, obwohl der Schüler weiterhin korrekt zugeordnet ist.
        $currentWeekEntries = PaedDiaryEntry::with(['schueler:id', 'user:id,name', 'category:id,name,color'])
            ->whereHas('schueler', fn($q) => $q->whereIn('schueler.id', $schuelerIds))
            ->whereBetween('datum', [$weekStart->toDateString(), $periodEnd->toDateString()])
            ->where('dossier_only', false)
            ->get();

        // Zusätzlich alle offenen Einträge aus vorherigen Wochen laden (ebenfalls schülerbasiert, s.o.)
        $previousOpenEntries = PaedDiaryEntry::with(['schueler:id', 'user:id,name', 'category:id,name,color'])
            ->whereHas('schueler', fn($q) => $q->whereIn('schueler.id', $schuelerIds))
            ->where('datum', '<', $weekStart->toDateString())
            ->whereNull('completed_at')
            ->where('dossier_only', false)
            ->get();

        // Beide Collections zusammenführen
        $entries = $currentWeekEntries->merge($previousOpenEntries);

        // Auto-Pausierung: Offene Einträge während der Ferien pausieren
        // WICHTIG: Ein Eintrag darf NICHT rückwirkend für sein eigenes Start-Datum (oder
        // frühere Tage) pausiert werden – sonst verschwindet ein gerade erst erstellter,
        // offener Eintrag sofort wieder aus der Ansicht, obwohl er heute (ggf. innerhalb
        // eines laut Kalender noch laufenden Ferienzeitraums) bewusst angelegt wurde.
        // Zusätzlich: Manche Kolleg*innen legen Einträge bewusst WÄHREND laufender Ferien an
        // (z.B. Hort-/Ferienbetreuung). Wurde ein Eintrag selbst innerhalb eines Ferienzeitraums
        // erstellt (created_at liegt in den Ferien), wird er grundsätzlich NIE automatisch
        // pausiert - unabhängig vom konkreten Ferientag. Die Auto-Pause soll nur bereits vor den
        // Ferien offene Einträge betreffen, die während der Ferien "liegen bleiben" würden.
        if (!empty($ferienDates)) {
            try {
                // Prüfen ob reason-Spalte bereits existiert (Migration ggf. noch nicht gelaufen)
                $reasonColumnExists = Schema::hasColumn('paed_diary_entry_pauses', 'reason');
                foreach ($entries as $entry) {
                    if (is_null($entry->completed_at)) {
                        // Wurde der Eintrag bewusst während laufender Ferien erstellt? Dann nie pausieren.
                        $createdAt = $entry->created_at ?? $entry->datum;
                        if (!is_null(is_ferien($createdAt->copy()))) {
                            continue;
                        }
                        $entryStart = $entry->datum->copy()->startOfDay();
                        foreach ($entry->schueler as $schueler_item) {
                            foreach ($ferienDates as $ferienDate) {
                                if (Carbon::parse($ferienDate)->lte($entryStart)) {
                                    continue;
                                }
                                $pauseData = [
                                    'paed_diary_entry_id' => $entry->id,
                                    'schueler_id' => $schueler_item->id,
                                    'date' => $ferienDate,
                                ];
                                $defaults = $reasonColumnExists ? ['reason' => 'Ferien'] : [];
                                $this->ensureEntryPause($pauseData, $defaults);
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Ferien-Auto-Pause fehlgeschlagen: ' . $e->getMessage());
            }
        }

        // ── Klassen-Tag-Pausen (manuelle Veranstaltungs-Pausen) laden und anwenden ──
        $classDayPauseRecords = collect();
        try {
            if (Schema::hasTable('paed_diary_class_day_pauses')) {
                $classDayPauseRecords = PaedDiaryClassDayPause::whereIn('klasse_id', $klassen->pluck('id'))
                    ->whereBetween('date', [$weekStart->toDateString(), $periodEnd->toDateString()])
                    ->get();

                // Gruppiert nach klasse_id: [klasseId => [dateStr, ...]]
                $classDayPauseDates = $classDayPauseRecords
                    ->groupBy('klasse_id')
                    ->map(fn($items) => $items->pluck('date')->map(fn($d) => $d->toDateString())->toArray());

                if ($classDayPauseRecords->isNotEmpty()) {
                    $reasonColumnExists2 = Schema::hasColumn('paed_diary_entry_pauses', 'reason');
                    foreach ($entries as $entry) {
                        if (is_null($entry->completed_at)) {
                            $pauseDates = $classDayPauseDates[$entry->klasse_id] ?? [];
                            foreach ($entry->schueler as $schueler_item) {
                                foreach ($pauseDates as $pauseDate) {
                                    $pauseData = [
                                        'paed_diary_entry_id' => $entry->id,
                                        'schueler_id' => $schueler_item->id,
                                        'date' => $pauseDate,
                                    ];
                                    $defaults = $reasonColumnExists2 ? ['reason' => 'Veranstaltung'] : [];
                                    $this->ensureEntryPause($pauseData, $defaults);
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Klassen-Tag-Pause-Anwendung fehlgeschlagen: ' . $e->getMessage());
            $classDayPauseRecords = collect();
        }

        // Aufgaben: ebenfalls schülerbasiert statt klasse_id-basiert (Begründung s.o.)
        $tasks = PaedDiaryTask::whereIn('schueler_id', $schuelerIds)->open()->with('schueler:id,vorname,nachname')->get();

        // Pausen für Tage der Woche laden (inkl. neu erstellte Ferien-Pausen)
        // whereDate statt whereBetween: `date` wird je nach Datenbank mit Uhrzeit gespeichert (sonst fehlt der Freitag)
        $pauseRecords = PaedDiaryEntryPause::whereIn('paed_diary_entry_id', $entries->pluck('id'))
            ->whereDate('date', '>=', $weekStart->toDateString())
            ->whereDate('date', '<=', $periodEnd->toDateString())
            ->get(Schema::hasColumn('paed_diary_entry_pauses', 'reason')
                ? ['paed_diary_entry_id', 'schueler_id', 'date', 'reason']
                : ['paed_diary_entry_id', 'schueler_id', 'date']);

        // Abwesenheiten für die aktuelle Woche laden (schülerbasiert statt klasse_id-basiert, s.o.)
        $absencesForWeek = PaedDiarySchuelerAbsence::whereIn('schueler_id', $schuelerIds)
            ->whereBetween('datum', [$weekStart->toDateString(), $periodEnd->toDateString()])
            ->get(['id', 'schueler_id', 'datum']);

        return [
            'week_start' => $weekStart,
            'week_end' => $periodEnd,
            'days' => $days,
            'schueler' => $schueler,
            'columns' => $columns,
            'column_values' => $columnValues,
            'entries' => $entries,
            'tasks' => $tasks,
            'pauses' => $pauseRecords,
            'absences' => $absencesForWeek,
            'class_day_pauses' => $classDayPauseRecords,
        ];
    }

    /**
     * Auffälligkeiten im Fehlzeitenmuster je Schüler (Fenster laut Einstellungen bis heute).
     */
    public function absenceAlerts(int $schuelerId): array
    {
        $windowEnd = Carbon::now()->endOfDay();
        $windowStart = $windowEnd->copy()->subDays((int) PaedDiarySchuelerAbsence::patternSettings()['weekday_window_days']);

        return PaedDiarySchuelerAbsence::patternSummaryForStudent($schuelerId, $windowStart, $windowEnd);
    }

    /**
     * Termin-Vorkommen im Zeitraum für Klassen bzw. eine Klassen-Gruppe (inkl. wiederkehrender Termine).
     *
     * @param int[] $classIds
     */
    public function appointments(array $classIds, ?int $groupId, Carbon $start, Carbon $end): array
    {
        if (empty($classIds)) {
            return [];
        }

        $start = $start->copy()->startOfDay();
        $end = $end->copy()->endOfDay();

        $appointments = PaedDiaryAppointment::with([
            'klassen:id,name',
            'groups:id,name',
            'schueler:id,vorname,nachname,klasse_id',
            'exceptions',
        ])
            ->where(function ($q) use ($classIds, $groupId) {
                $q->whereHas('klassen', fn ($qq) => $qq->whereIn('klassen.id', $classIds))
                  ->orWhereHas('schueler', fn ($qq) => $qq->whereIn('schueler.klasse_id', $classIds));
                if ($groupId) {
                    $q->orWhereHas('groups', fn ($qq) => $qq->where('paed_diary_class_group_id', $groupId));
                }
            })
            ->whereDate('start_date', '<=', $end->toDateString())
            ->get();

        $out = [];
        foreach ($appointments as $app) {
            $occ = $app->getOccurrencesInRange($start->copy(), $end->copy());
            if (empty($occ)) continue;
            $k = $app->klassen->map(fn ($k) => ['id' => $k->id, 'name' => $k->name]);
            $g = $app->groups->map(fn ($gr) => ['id' => $gr->id, 'name' => $gr->name]);
            $s = $app->schueler->map(fn ($st) => ['id' => $st->id, 'name' => $st->vorname . ' ' . $st->nachname, 'klasse_id' => $st->klasse_id]);
            foreach ($occ as $o) {
                $out[] = array_merge($o, [
                    'klassen'       => $k,
                    'groups'        => $g,
                    'schueler'      => $s,
                    'pause_entries' => (bool) $app->pause_entries,
                    'recurring_type'     => $app->recurring_type,
                    'recurring_interval' => $app->recurring_interval,
                    'recurring_end_date' => $app->recurring_end_date?->toDateString(),
                ]);
            }
        }
        usort($out, fn ($a, $b) => $a['date'] === $b['date'] ? strcmp($a['start_time'] ?? '', $b['start_time'] ?? '') : strcmp($a['date'], $b['date']));

        return $out;
    }

    // ── Abwesenheiten ─────────────────────────────────────────────────

    /**
     * Setzt die Abwesenheit eines Schülers an einem Tag (idempotent) und pausiert dabei
     * seine offenen Notizen bzw. entfernt diese Pausen wieder.
     *
     * @return array{absent: bool, pauses: array, removed_entry_ids: int[]}
     */
    public function setAbsence(int $schuelerId, int $klasseId, string $date, bool $absent, ?int $userId): array
    {
        $datumString = Carbon::parse($date)->toDateString();

        $existing = PaedDiarySchuelerAbsence::where('schueler_id', $schuelerId)
            ->where('klasse_id', $klasseId)
            ->whereDate('datum', $datumString)
            ->first();

        if (!$absent) {
            $removed = [];
            if ($existing) {
                $removed = $this->removeAbsencePauses($existing);
                $existing->delete();
            }
            return ['absent' => false, 'pauses' => [], 'removed_entry_ids' => $removed];
        }

        $absence = $existing ?? PaedDiarySchuelerAbsence::create([
            'schueler_id' => $schuelerId,
            'klasse_id'   => $klasseId,
            'datum'       => $datumString,
            'marked_by'   => $userId,
        ]);

        return ['absent' => true, 'pauses' => $this->pauseEntriesForAbsence($absence), 'removed_entry_ids' => []];
    }

    /**
     * Pausiert alle offenen Einträge des Schülers für den Abwesenheitstag.
     *
     * @return array<int, array{entry_id: int, schueler_id: int, date: string}>
     */
    public function pauseEntriesForAbsence(PaedDiarySchuelerAbsence $absence): array
    {
        $datumStr = $absence->datum->toDateString();

        $openEntries = PaedDiaryEntry::where('klasse_id', $absence->klasse_id)
            ->whereNull('completed_at')
            ->whereDate('datum', '<=', $datumStr)
            ->whereHas('schueler', fn ($q) => $q->where('schueler.id', $absence->schueler_id))
            ->pluck('id');

        $created = [];
        foreach ($openEntries as $entryId) {
            $this->ensureEntryPause([
                'paed_diary_entry_id' => $entryId,
                'schueler_id'         => $absence->schueler_id,
                'date'                => $datumStr,
            ]);
            $created[] = [
                'entry_id'    => $entryId,
                'schueler_id' => $absence->schueler_id,
                'date'        => $datumStr,
            ];
        }

        return $created;
    }

    /**
     * Entfernt alle Pausen des Schülers am Abwesenheitstag.
     *
     * @return int[] entry_ids der gelöschten Pausen
     */
    public function removeAbsencePauses(PaedDiarySchuelerAbsence $absence): array
    {
        $datumStr = $absence->datum->toDateString();

        $entryIds = PaedDiaryEntry::where('klasse_id', $absence->klasse_id)
            ->pluck('id');

        $removedEntryIds = PaedDiaryEntryPause::whereIn('paed_diary_entry_id', $entryIds)
            ->where('schueler_id', $absence->schueler_id)
            ->whereDate('date', $datumStr)
            ->pluck('paed_diary_entry_id')
            ->toArray();

        PaedDiaryEntryPause::whereIn('paed_diary_entry_id', $entryIds)
            ->where('schueler_id', $absence->schueler_id)
            ->whereDate('date', $datumStr)
            ->delete();

        return $removedEntryIds;
    }

    /**
     * Legt eine Tages-Pause für Eintrag/Schüler an, falls noch keine existiert.
     * (Suche per whereDate, da `date` je nach Datenbank mit Uhrzeit gespeichert wird.)
     *
     * @param array{paed_diary_entry_id: int, schueler_id: int, date: string} $keys
     */
    public function ensureEntryPause(array $keys, array $defaults = []): PaedDiaryEntryPause
    {
        $existing = PaedDiaryEntryPause::where('paed_diary_entry_id', $keys['paed_diary_entry_id'])
            ->where('schueler_id', $keys['schueler_id'])
            ->whereDate('date', $keys['date'])
            ->first();

        return $existing ?? PaedDiaryEntryPause::create(array_merge($keys, $defaults));
    }

    // ── Tages-Pausen (Klasse) ─────────────────────────────────────────

    /**
     * Pausiert alle offenen Einträge der Klassen an einem Tag (z. B. Wandertag).
     *
     * @return array<int, array{klasse_id: int, date: string, reason: string}>
     */
    public function pauseClassDay(Collection $klassen, string $date, ?string $reason, ?int $userId): array
    {
        $dateStr = Carbon::parse($date)->toDateString();
        $reason  = trim($reason ?? '') ?: 'Veranstaltung';
        $reasonColumnExists = Schema::hasColumn('paed_diary_entry_pauses', 'reason');

        $created = [];
        foreach ($klassen as $klasse) {
            // Klassen-Tag-Pause anlegen (idempotent)
            $exists = PaedDiaryClassDayPause::where('klasse_id', $klasse->id)->whereDate('date', $dateStr)->exists();
            if (!$exists) {
                PaedDiaryClassDayPause::create([
                    'klasse_id' => $klasse->id, 'date' => $dateStr, 'reason' => $reason, 'paused_by' => $userId,
                ]);
            }

            // Alle offenen Einträge dieser Klasse bis einschl. dieses Tages pausieren
            $openEntries = PaedDiaryEntry::where('klasse_id', $klasse->id)
                ->whereNull('completed_at')
                ->whereDate('datum', '<=', $dateStr)
                ->with('schueler:id')
                ->get();

            foreach ($openEntries as $entry) {
                foreach ($entry->schueler as $stu) {
                    $pauseData = ['paed_diary_entry_id' => $entry->id, 'schueler_id' => $stu->id, 'date' => $dateStr];
                    $defaults  = $reasonColumnExists ? ['reason' => $reason] : [];
                    $this->ensureEntryPause($pauseData, $defaults);
                }
            }

            $created[] = ['klasse_id' => $klasse->id, 'date' => $dateStr, 'reason' => $reason];
        }

        return $created;
    }

    /**
     * Hebt die Tages-Pause der Klassen auf. Entfernt nur Pausen mit reason = 'Veranstaltung'
     * (nicht Ferien- oder individuelle Pausen).
     */
    public function unpauseClassDay(Collection $klassen, string $date): void
    {
        $dateStr = Carbon::parse($date)->toDateString();
        $reasonColumnExists = Schema::hasColumn('paed_diary_entry_pauses', 'reason');

        foreach ($klassen as $klasse) {
            $dayPauses = PaedDiaryClassDayPause::where('klasse_id', $klasse->id)->whereDate('date', $dateStr);
            // Grund der Tagespause merken: Die Eintrags-Pausen tragen denselben Grund (z. B. „Wandertag“).
            $reasons = (clone $dayPauses)->pluck('reason')->filter()->push('Veranstaltung')->unique()->values()->all();
            $dayPauses->delete();

            // Nur durch class-day-pause erzeugte Eintrags-Pausen entfernen
            $entryIds = PaedDiaryEntry::where('klasse_id', $klasse->id)->pluck('id');
            $query = PaedDiaryEntryPause::whereIn('paed_diary_entry_id', $entryIds)
                ->whereDate('date', $dateStr);
            if ($reasonColumnExists) {
                $query->whereIn('reason', $reasons);
            }
            $query->delete();
        }
    }
}
