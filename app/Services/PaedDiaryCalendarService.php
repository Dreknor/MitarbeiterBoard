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

/**
 * Gemeinsame Logik der Wochenansicht (Kalender) des Pädagogischen Tagebuchs –
 * genutzt vom Web-Frontend (PaedDiaryController) und von der API v1 (Pädagogen-App).
 *
 * Die Methoden liefern Modelle/Collections; die Controller formatieren die Antwort selbst.
 */
class PaedDiaryCalendarService
{
    /** Grund für Pausen, die durch eine Abwesenheit entstehen. */
    public const GRUND_ABWESENHEIT = 'Abwesenheit';

    /** Grund für manuell gesetzte Tages-Pausen einzelner Notizen. */
    public const GRUND_MANUELL = 'Manuell';

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
                'label' => $date->copy()->locale('de')->isoFormat('dd DD.MM.'),
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
        // Auto-Pausen werden gesammelt und anschließend in einem Durchgang angelegt
        // (vorher: SELECT + INSERT je Eintrag/Schüler/Tag bei jedem Wochenaufruf).
        $autoPausen = [];

        if (!empty($ferienDates)) {
            foreach ($entries as $entry) {
                if (!is_null($entry->completed_at)) {
                    continue;
                }
                // Wurde der Eintrag bewusst während laufender Ferien erstellt? Dann nie pausieren.
                $createdAt = $entry->created_at ?? $entry->datum;
                if (!is_null(is_ferien($createdAt->copy()))) {
                    continue;
                }
                $entryStart = $entry->datum->toDateString();
                foreach ($entry->schueler as $schueler_item) {
                    foreach ($ferienDates as $ferienDate) {
                        if ($ferienDate <= $entryStart) {
                            continue;
                        }
                        $autoPausen[] = [$entry->id, $schueler_item->id, $ferienDate, 'Ferien'];
                    }
                }
            }
        }

        // ── Klassen-Tag-Pausen (manuelle Veranstaltungs-Pausen) laden und anwenden ──
        // Maßgeblich ist die aktuelle Klasse des Schülers (Ansicht ist schülerbasiert, s. o.),
        // und eine Notiz wird erst ab ihrem Startdatum pausiert.
        $classDayPauseRecords = PaedDiaryClassDayPause::whereIn('klasse_id', $klassen->pluck('id'))
            ->whereBetween('date', [$weekStart->toDateString(), $periodEnd->toDateString()])
            ->get();

        if ($classDayPauseRecords->isNotEmpty()) {
            $classDayPauseDates = $classDayPauseRecords
                ->groupBy('klasse_id')
                ->map(fn($items) => $items->pluck('date')->map(fn($d) => $d->toDateString())->toArray());
            $schuelerKlasse = $schueler->pluck('klasse_id', 'id');

            foreach ($entries as $entry) {
                if (!is_null($entry->completed_at)) {
                    continue;
                }
                $entryStart = $entry->datum->toDateString();
                foreach ($entry->schueler as $schueler_item) {
                    $klasseId = $schuelerKlasse[$schueler_item->id] ?? $entry->klasse_id;
                    foreach ($classDayPauseDates[$klasseId] ?? [] as $pauseDate) {
                        if ($pauseDate < $entryStart) {
                            continue;
                        }
                        $autoPausen[] = [$entry->id, $schueler_item->id, $pauseDate, 'Veranstaltung'];
                    }
                }
            }
        }

        try {
            $this->ensureEntryPauses($autoPausen);
        } catch (\Throwable $e) {
            Log::warning('Auto-Pausen (Ferien/Veranstaltung) fehlgeschlagen: ' . $e->getMessage());
        }

        // Aufgaben: ebenfalls schülerbasiert statt klasse_id-basiert (Begründung s.o.)
        $tasks = PaedDiaryTask::whereIn('schueler_id', $schuelerIds)->open()->with('schueler:id,vorname,nachname')->get();

        // Pausen für Tage der Woche laden (inkl. neu erstellte Ferien-Pausen)
        // whereDate statt whereBetween: `date` wird je nach Datenbank mit Uhrzeit gespeichert (sonst fehlt der Freitag)
        $pauseRecords = PaedDiaryEntryPause::whereIn('paed_diary_entry_id', $entries->pluck('id'))
            ->whereDate('date', '>=', $weekStart->toDateString())
            ->whereDate('date', '<=', $periodEnd->toDateString())
            ->get(['paed_diary_entry_id', 'schueler_id', 'date', 'reason']);

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
        return $this->absenceAlertsFor([$schuelerId])[$schuelerId] ?? [];
    }

    /**
     * Wie absenceAlerts(), aber für mehrere Schüler mit einer Abfrage (Wochenansicht).
     *
     * @param int[] $schuelerIds
     * @return array<int, array> [schueler_id => alerts]
     */
    public function absenceAlertsFor(array $schuelerIds): array
    {
        $settings = PaedDiarySchuelerAbsence::patternSettings();
        $tage = max($settings['weekday_window_days'], $settings['total_absence_window_days'], $settings['short_cluster_window_days']);
        $windowEnd = Carbon::now()->endOfDay();

        return PaedDiarySchuelerAbsence::patternSummariesForStudents($schuelerIds, $windowEnd->copy()->subDays($tage), $windowEnd);
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

        try {
            $absence = $existing ?? PaedDiarySchuelerAbsence::create([
                'schueler_id' => $schuelerId,
                'klasse_id'   => $klasseId,
                'datum'       => $datumString,
                'marked_by'   => $userId,
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException|\Illuminate\Database\QueryException $e) {
            // Doppelklick/paralleler Request: Abwesenheit wurde inzwischen angelegt → idempotent antworten
            $absence = PaedDiarySchuelerAbsence::where('schueler_id', $schuelerId)
                ->where('klasse_id', $klasseId)
                ->whereDate('datum', $datumString)
                ->first();
            if (!$absence) {
                throw $e;
            }
        }

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

        // Schülerbasiert (wie die Wochenansicht) – auch Notizen mit früherer klasse_id
        $openEntries = PaedDiaryEntry::whereNull('completed_at')
            ->whereDate('datum', '<=', $datumStr)
            ->whereHas('schueler', fn ($q) => $q->where('schueler.id', $absence->schueler_id))
            ->pluck('id');

        $created = [];
        foreach ($openEntries as $entryId) {
            $this->ensureEntryPause([
                'paed_diary_entry_id' => $entryId,
                'schueler_id'         => $absence->schueler_id,
                'date'                => $datumStr,
            ], ['reason' => self::GRUND_ABWESENHEIT]);
            $created[] = [
                'entry_id'    => $entryId,
                'schueler_id' => $absence->schueler_id,
                'date'        => $datumStr,
            ];
        }

        return $created;
    }

    /**
     * Entfernt die durch die Abwesenheit entstandenen Pausen des Schülers an diesem Tag.
     * Ferien-, Termin-, Veranstaltungs- und manuelle Pausen bleiben erhalten. Pausen ohne Grund
     * stammen aus der Zeit vor der Grund-Kennzeichnung (Abwesenheit) und werden mit entfernt.
     *
     * @return int[] entry_ids der gelöschten Pausen
     */
    public function removeAbsencePauses(PaedDiarySchuelerAbsence $absence): array
    {
        $query = PaedDiaryEntryPause::where('schueler_id', $absence->schueler_id)
            ->whereDate('date', $absence->datum->toDateString())
            ->where(fn ($q) => $q->where('reason', self::GRUND_ABWESENHEIT)->orWhereNull('reason'));

        $removedEntryIds = (clone $query)->pluck('paed_diary_entry_id')->map(fn ($id) => (int) $id)->all();
        $query->delete();

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

    /**
     * Legt mehrere Tages-Pausen an, sofern sie noch nicht existieren – mit einer Abfrage für die
     * bestehenden Pausen und einem Batch-Insert statt SELECT/INSERT je Kombination.
     *
     * @param array<int, array{0: int, 1: int, 2: string, 3: ?string}> $pausen [entry_id, schueler_id, Y-m-d, reason]
     */
    public function ensureEntryPauses(array $pausen): void
    {
        if (empty($pausen)) {
            return;
        }

        $dates = array_column($pausen, 2);
        $vorhanden = PaedDiaryEntryPause::whereIn('paed_diary_entry_id', array_unique(array_column($pausen, 0)))
            ->whereDate('date', '>=', min($dates))
            ->whereDate('date', '<=', max($dates))
            ->get(['paed_diary_entry_id', 'schueler_id', 'date'])
            ->mapWithKeys(fn ($p) => [$p->paed_diary_entry_id . '|' . $p->schueler_id . '|' . $p->date->toDateString() => true]);

        $now = now();
        $modell = new PaedDiaryEntryPause();
        $neu = [];
        foreach ($pausen as [$entryId, $schuelerId, $date, $reason]) {
            $key = $entryId . '|' . $schuelerId . '|' . $date;
            if (isset($vorhanden[$key])) {
                continue;
            }
            $vorhanden[$key] = true;
            $neu[] = [
                'paed_diary_entry_id' => $entryId,
                'schueler_id' => $schuelerId,
                // gleiches Format wie beim Speichern über das Modell (Cast 'date')
                'date' => $modell->fromDateTime(Carbon::parse($date)->startOfDay()),
                'reason' => $reason,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($neu, 500) as $chunk) {
            PaedDiaryEntryPause::insert($chunk);
        }
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

        $created = [];
        foreach ($klassen as $klasse) {
            // Klassen-Tag-Pause anlegen (idempotent)
            $exists = PaedDiaryClassDayPause::where('klasse_id', $klasse->id)->whereDate('date', $dateStr)->exists();
            if (!$exists) {
                PaedDiaryClassDayPause::create([
                    'klasse_id' => $klasse->id, 'date' => $dateStr, 'reason' => $reason, 'paused_by' => $userId,
                ]);
            }

            // Alle offenen Einträge der aktuellen Schüler dieser Klasse bis einschl. dieses Tages
            // pausieren (schülerbasiert wie die Wochenansicht, auch Notizen mit früherer klasse_id)
            $schuelerIds = Schueler::where('klasse_id', $klasse->id)->pluck('id')->all();
            $openEntries = PaedDiaryEntry::whereNull('completed_at')
                ->whereDate('datum', '<=', $dateStr)
                ->whereHas('schueler', fn ($q) => $q->whereIn('schueler.id', $schuelerIds))
                ->with('schueler:id')
                ->get();

            $pausen = [];
            foreach ($openEntries as $entry) {
                foreach ($entry->schueler as $stu) {
                    if (in_array($stu->id, $schuelerIds)) {
                        $pausen[] = [$entry->id, $stu->id, $dateStr, $reason];
                    }
                }
            }
            $this->ensureEntryPauses($pausen);

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

        foreach ($klassen as $klasse) {
            $dayPauses = PaedDiaryClassDayPause::where('klasse_id', $klasse->id)->whereDate('date', $dateStr);
            // Grund der Tagespause merken: Die Eintrags-Pausen tragen denselben Grund (z. B. „Wandertag“).
            $reasons = (clone $dayPauses)->pluck('reason')->filter()->push('Veranstaltung')->unique()->values()->all();
            $dayPauses->delete();

            // Nur durch die Tages-Pause erzeugte Eintrags-Pausen der Schüler dieser Klasse entfernen
            PaedDiaryEntryPause::whereIn('schueler_id', Schueler::where('klasse_id', $klasse->id)->select('id'))
                ->whereDate('date', $dateStr)
                ->whereIn('reason', $reasons)
                ->delete();
        }
    }
}
