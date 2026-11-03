<?php

namespace App\Services;

use App\Models\GradingDocumentationSession;
use App\Models\PaedDiaryCategory;
use App\Models\PaedDiaryColumn;
use App\Models\PaedDiaryColumnValue;
use App\Models\PaedDiaryEntry;
use App\Models\PaedDiaryGoal;
use App\Models\PaedDiarySchuelerAbsence;
use App\Models\PaedDiaryTask;
use App\Models\Schueler;
use App\Models\User;
use App\Services\Api\StudentDataService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Daten der Schülerakte im Pädagogischen Tagebuch – gemeinsam genutzt von der Web-Ansicht
 * (bisherige und neue Darstellung) und dem Excel-Export.
 *
 * In der Schülerakte sind alle Einträge zum Schüler sichtbar, auch vertrauliche (dossier_only).
 * Einträge, Aufgaben und Spaltenwerte werden schülerbasiert geladen (nicht nach der aktuellen
 * klasse_id), damit Daten aus früheren Klassen/Schuljahren erhalten bleiben.
 */
class PaedDiarySchuelerService
{
    /** Größter abfragbarer Zeitraum in Tagen (Schutz vor Abfragen über Jahrzehnte). */
    public const MAX_TAGE = 731;

    public function __construct(private StudentDataService $data)
    {
    }

    /**
     * Einträge, Spalten, Aufgaben, Ziele, Stufe und Fehlzeitenmuster für einen Zeitraum.
     */
    public function daten(Schueler $schueler, Carbon $from, Carbon $to, User $user): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $entries = $this->eintraege($schueler, $from, $to);
        [$columns, $columnValues] = $this->spalten($schueler, $from, $to);

        return [
            'entries' => $entries,
            'current_stage' => $this->aktuelleStufe($schueler),
            'stage_history' => $this->stufenHistorie($schueler),
            'columns' => $columns,
            'column_values' => $columnValues,
            'tasks' => $this->aufgaben($schueler, $from, $to),
            'period' => ['from' => $from->format('d.m.Y'), 'to' => $to->format('d.m.Y')],
            'categories' => $this->kategorien($user, $entries),
            'goals' => $this->ziele($schueler),
            'absence_patterns' => PaedDiarySchuelerAbsence::patternSummaryForStudent($schueler->id, $from, $to),
        ];
    }

    /**
     * Einträge (neueste zuerst), aufeinanderfolgende Tage mit gleichem Inhalt zusammengefasst.
     */
    public function eintraege(Schueler $schueler, Carbon $from, Carbon $to): Collection
    {
        $entries = PaedDiaryEntry::with(['user:id,name', 'category:id,name,color'])
            ->whereBetween('datum', [$from->toDateString(), $to->toDateString()])
            ->whereHas('schueler', fn ($q) => $q->where('schueler.id', $schueler->id))
            ->orderBy('datum')
            ->orderBy('id')
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'date' => $e->datum->toDateString(),
                'content' => $e->content,
                'user' => $e->user?->name,
                'formatted_date' => $e->datum->format('d.m.Y'),
                'category' => $e->category?->name ?? '',
                'category_id' => $e->category_id,
                'category_color' => $e->category?->color,
                'dossier_only' => (bool) $e->dossier_only,
                'completed_at' => $e->completed_at?->toDateString(),
            ]);

        return $this->zusammenfassen($entries)->reverse()->values();
    }

    /**
     * Fasst aufeinanderfolgende Tage mit identischem Eintrag (Inhalt, Autor, Kategorie,
     * Vertraulichkeit, Status) zu einem Datumsbereich zusammen – auch wenn am selben Tag
     * weitere Einträge dazwischen liegen.
     *
     * @param Collection $entries aufsteigend nach Datum sortiert
     */
    public function zusammenfassen(Collection $entries): Collection
    {
        $offen = [];   // Gruppenschlüssel => Index in $grouped
        $grouped = [];

        foreach ($entries as $entry) {
            $key = implode('|', [
                $entry['content'] ?? '', $entry['user'] ?? '', $entry['category_id'] ?? '',
                !empty($entry['dossier_only']) ? 1 : 0, empty($entry['completed_at']) ? 'offen' : 'erledigt',
            ]);

            if (isset($offen[$key])) {
                $i = $offen[$key];
                $diff = Carbon::parse($grouped[$i]['date_to'])->diffInDays(Carbon::parse($entry['date']));
                if ($diff === 1) {
                    $grouped[$i]['date_to'] = $entry['date'];
                    $grouped[$i]['entry_ids'][] = $entry['id'];
                    continue;
                }
                if ($diff === 0) {
                    continue; // gleicher Tag, gleicher Inhalt (Duplikat)
                }
            }

            $entry['date_from'] = $entry['date'];
            $entry['date_to'] = $entry['date'];
            $entry['entry_ids'] = [$entry['id']];
            $grouped[] = $entry;
            $offen[$key] = count($grouped) - 1;
        }

        return collect($grouped);
    }

    /**
     * Zusatzspalten (aktuelle Klasse + Spalten mit Werten im Zeitraum) und deren Werte je Tag.
     *
     * @return array{0: Collection, 1: Collection}
     */
    public function spalten(Schueler $schueler, Carbon $from, Carbon $to): array
    {
        $values = PaedDiaryColumnValue::where('schueler_id', $schueler->id)
            ->whereBetween('datum', [$from->toDateString(), $to->toDateString()])
            ->get();

        $columns = PaedDiaryColumn::where('klasse_id', $schueler->klasse_id)
            ->orWhereIn('id', $values->pluck('paed_diary_column_id')->unique())
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'type' => $c->type,
                'category' => $c->category,
                'deactivated_from' => $c->deactivated_from?->toDateString(),
                // aktiv, wenn nicht oder erst nach dem Zeitraumende deaktiviert
                'active' => !$c->deactivated_from || $c->deactivated_from->gt($to),
            ]);

        $byDay = $values
            ->groupBy(fn ($v) => Carbon::parse($v->datum)->toDateString())
            ->map(fn ($dayValues) => $dayValues->keyBy('paed_diary_column_id'));

        return [$columns, $byDay];
    }

    public function aufgaben(Schueler $schueler, Carbon $from, Carbon $to): Collection
    {
        return PaedDiaryTask::where('schueler_id', $schueler->id)
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'description' => $t->description,
                'due_date' => $t->due_date?->format('d.m.Y'),
                'status' => $t->status,
                'highlighted' => $t->highlighted,
                'created_at' => $t->created_at->format('d.m.Y H:i'),
            ]);
    }

    public function aktuelleStufe(Schueler $schueler): ?array
    {
        $stage = $schueler->grading_stage;

        return $stage ? [
            'id' => $stage->id,
            'name' => $stage->name,
            'symbol' => $stage->symbol,
            'sort_order' => $stage->sort_order,
            'image_url' => $stage->image_url ?? null,
        ] : null;
    }

    /**
     * Stufenwechsel (neueste zuerst), Zeitpunkte als ISO 8601 (auch in Safari/iPadOS lesbar).
     */
    public function stufenHistorie(Schueler $schueler): Collection
    {
        return $schueler->grading_history()
            ->with(['stage', 'previous_stage', 'changed_by_user:id,name'])
            ->get()
            ->map(fn ($h) => [
                'at' => $h->created_at ? Carbon::parse($h->created_at)->toIso8601String() : null,
                'stage_id' => $h->grading_stage_id,
                'stage_name' => $h->stage?->name,
                'previous_stage_id' => $h->previous_grading_stage_id,
                'previous_stage_name' => $h->previous_stage?->name,
                'changed_by' => $h->changed_by,
                'changed_by_name' => $h->changed_by_user?->name,
            ]);
    }

    /**
     * Kategorien für den Filter: globale, eigene und die in den Einträgen tatsächlich verwendeten
     * (nicht die privaten Kategorien aller anderen Nutzer).
     */
    public function kategorien(User $user, Collection $entries): Collection
    {
        $genutzt = $entries->pluck('category_id')->filter()->unique()->all();

        return PaedDiaryCategory::where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $user->id)->orWhereIn('id', $genutzt))
            ->orderBy('name')
            ->get(['id', 'name', 'color']);
    }

    public function ziele(Schueler $schueler): Collection
    {
        return PaedDiaryGoal::with(['user:id,name', 'achievedByUser:id,name'])
            ->where('schueler_id', $schueler->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($g) => [
                'id' => $g->id,
                'goal_text' => $g->goal_text,
                'created_at' => $g->created_at?->toIso8601String(),
                'formatted_created_at' => $g->created_at?->format('d.m.Y H:i'),
                'user' => $g->user?->name,
                'achieved_at' => $g->achieved_at?->toIso8601String(),
                'formatted_achieved_at' => $g->achieved_at?->format('d.m.Y H:i'),
                'achieved_by' => $g->achievedByUser?->name,
            ]);
    }

    /**
     * Abgeschlossene Reflexionen (Graduierungs-Sessions) des Schülers – dieselbe Abfrage wie API
     * und Dossier (StudentDataService::completedGradingSessions).
     */
    public function reflexionen(Schueler $schueler, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        return $this->data->completedGradingSessions($schueler, $from, $to);
    }

    /**
     * Schlanke Darstellung der Reflexionen für das Frontend (keine kompletten Modelle mit
     * Nutzerdaten im Seitenquelltext). Fragen ohne Antwort und inaktive Fragen ohne Bewertung
     * werden ausgelassen. Älteste zuerst (für Verlaufsdiagramme).
     */
    public function reflexionenAlsDaten(Collection $sessions): Collection
    {
        return $sessions->sortBy('completed_at')->values()->map(function (GradingDocumentationSession $s) {
            $self = $s->studentAnswers->keyBy('question_id');
            $teacher = $s->teacherAssessments->keyBy('question_id');

            $fragen = $s->gradingSystem?->questions
                ->filter(fn ($q) => $q->active || $self->has($q->id) || $teacher->has($q->id))
                ->map(fn ($q) => [
                    'id' => $q->id,
                    'text' => $q->question,
                    'self' => $self->get($q->id)?->self_rating,
                    'teacher' => $teacher->get($q->id)?->teacher_rating,
                    'comment' => $teacher->get($q->id)?->comment,
                ])->values() ?? collect();

            return [
                'id' => $s->id,
                'date' => $s->completed_at?->toDateString(),
                'formatted_date' => $s->completed_at?->format('d.m.Y'),
                'system' => $s->gradingSystem?->name,
                'type' => $s->type,
                'teacher_name' => $s->user?->name,
                'coaching_note' => $s->coachingNotes->first()?->note,
                'questions' => $fragen,
            ];
        });
    }
}
