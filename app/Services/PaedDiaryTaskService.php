<?php

namespace App\Services;

use App\Models\PaedDiaryTask;
use Carbon\Carbon;

/**
 * Gemeinsame Logik für Aufgaben des Pädagogischen Tagebuchs (Web-Frontend und API v1).
 * Eine Aufgabe gehört immer zu genau einem Schüler; für mehrere Schüler entstehen mehrere Aufgaben.
 */
class PaedDiaryTaskService
{
    public function __construct(private PaedDiaryEntryService $entries)
    {
    }

    /**
     * Legt je Schüler eine offene Aufgabe an.
     *
     * @param int[] $schuelerIds Schüler der Klasse (bereits geprüft)
     * @param array{title: string, description?: ?string, due_date?: ?string} $data
     * @return PaedDiaryTask[]
     */
    public function createForStudents(int $klasseId, array $schuelerIds, array $data, int $userId, bool $highlighted = true): array
    {
        $created = [];
        foreach ($schuelerIds as $schuelerId) {
            $created[] = PaedDiaryTask::create([
                'klasse_id' => $klasseId,
                'schueler_id' => $schuelerId,
                'title' => trim($data['title']),
                'description' => $data['description'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'status' => 'open',
                'highlighted' => $highlighted,
                'created_by' => $userId,
            ]);
        }
        $this->entries->forgetWeekCache($klasseId, Carbon::now());

        return $created;
    }

    /**
     * Ändert Titel, Beschreibung, Fälligkeit und Hervorhebung.
     * Nicht übergebene Felder bleiben unverändert (`highlighted`) bzw. werden geleert (wie im Web).
     */
    public function update(PaedDiaryTask $task, array $data): PaedDiaryTask
    {
        $task->update([
            'title' => trim($data['title']),
            'description' => $data['description'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'highlighted' => $data['highlighted'] ?? $task->highlighted,
        ]);
        $this->entries->forgetWeekCache($task->klasse_id, Carbon::now());

        return $task;
    }

    public function close(PaedDiaryTask $task): void
    {
        if ($task->status === 'closed') {
            return;
        }
        $task->update(['status' => 'closed', 'highlighted' => false, 'closed_at' => now()]);
        $this->entries->forgetWeekCache($task->klasse_id, Carbon::now());
    }

    /** Einheitliche Darstellung (Web-JSON und API). */
    public function toArray(PaedDiaryTask $task): array
    {
        return [
            'id' => $task->id,
            'schueler_id' => $task->schueler_id,
            'klasse_id' => $task->klasse_id,
            'title' => $task->title,
            'description' => $task->description,
            'due_date' => $task->due_date?->toDateString(),
            'highlighted' => (bool) $task->highlighted,
            'status' => $task->status,
        ];
    }
}
