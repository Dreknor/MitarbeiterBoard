<?php

namespace App\Services\Ergebnisboards;

use App\Models\Ergebnisboard;
use App\Models\ErgebnisKarte;
use App\Models\GroupTaskUser;
use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\ThemeTaskService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kopplung Karte ↔ Aufgabe (Stufe 3).
 *
 * - Karte → Aufgabe nur bei Boards mit verknüpftem Thema (`theme_id`), angelegt über
 *   ThemeTaskService (Kontext: Sitzung des Boards, wenn das Thema dort auf der Tagesordnung steht,
 *   sonst die Gruppe des Themas); Zuständige der Karte werden zu Zuständigen der Aufgabe.
 * - Status der Aufgabe → Spalte: erledigt → Feld mit Status „erledigt“; bei `auto_status`
 *   zusätzlich offen / in Arbeit (gemeinsame Aufgabe teilweise erledigt).
 *   Ausgelöst von ErgebnisKarteAufgabeObserver (Task, GroupTaskUser).
 */
class ErgebnisboardAufgabenService
{
    public function __construct(
        private ThemeTaskService $tasks,
        private ErgebnisboardService $boards,
        private ErgebnisboardVerlaufService $verlauf,
    ) {
    }

    public function alsAufgabe(Ergebnisboard $board, ErgebnisKarte $karte, User $user, ?string $datum = null): Task
    {
        if ($karte->task_id && $karte->task) {
            throw ValidationException::withMessages(['aufgabe' => 'Zu dieser Karte gibt es bereits eine Aufgabe.']);
        }
        $theme = $board->theme;
        if (! $theme) {
            throw ValidationException::withMessages(['aufgabe' => 'Verknüpfen Sie das Board zuerst mit einem Thema – Aufgaben gehören immer zu einem Thema.']);
        }
        $zustaendige = $karte->zustaendige()->get();
        if ($zustaendige->isEmpty()) {
            throw ValidationException::withMessages(['aufgabe' => 'Tragen Sie zuerst mindestens eine zuständige Person ein.']);
        }

        $kontext = $board->meeting && $board->meeting->themes()->whereKey($theme->id)->exists()
            ? $board->meeting
            : $theme->group;
        if (! $kontext) {
            throw ValidationException::withMessages(['aufgabe' => 'Das Thema gehört zu keiner Gruppe.']);
        }

        $datum ??= ($karte->faellig_am ?? now()->addDays(14))->toDateString();
        $text = $karte->titel.($karte->zusatz ? ' – '.$karte->zusatz : '');

        $ergebnis = $this->tasks->create($theme, $kontext, $zustaendige, false, mb_substr($text, 0, 1000), $datum, $user);
        $task = $ergebnis['task'];
        if (! $task) {
            throw ValidationException::withMessages(['aufgabe' => 'Die Aufgabe konnte nicht angelegt werden.']);
        }

        if ($ergebnis['added']->isNotEmpty()) {
            $this->tasks->notify($task, $ergebnis['added'], $user);
        }

        $karte->task_id = $task->id;
        if (! $karte->faellig_am) {
            $karte->faellig_am = $datum;
        }
        $karte->save();
        $this->boards->versionErhoehen($board);
        $this->verlauf->karte($board, $karte, 'aufgabe_angelegt', [], $user);
        $this->synchronisieren($task);

        return $task;
    }

    /** Aufgabe für die Person als erledigt markieren (gemeinsam: nur deren Anteil). */
    public function erledigen(ErgebnisKarte $karte, User $user): bool
    {
        $task = $karte->task;
        if (! $task) {
            throw ValidationException::withMessages(['aufgabe' => 'Zu dieser Karte gibt es keine Aufgabe.']);
        }
        $ok = $this->tasks->complete($task, $user);
        if ($ok && $karte->board) {
            $this->verlauf->karte($karte->board, $karte, 'aufgabe_erledigt', [], $user);
        }
        $this->synchronisieren($task->fresh());

        return $ok;
    }

    /** Karten aller Boards mit dieser Aufgabe in die passende Spalte verschieben. */
    public function synchronisieren(Task $task): void
    {
        $task = Task::withCompleted()->with('taskUsers')->find($task->id);
        if (! $task) {
            return;
        }

        $status = $this->status($task);

        ErgebnisKarte::query()->where('task_id', $task->id)->with('board')->get()
            ->each(function (ErgebnisKarte $karte) use ($status) {
                $board = $karte->board;
                if (! $board || $board->istArchiviert()) {
                    return;
                }
                if (! $board->auto_status && $status !== 'erledigt') {
                    return;
                }
                $ziel = $board->bereichMitStatus($status);
                if (! $ziel || $ziel['schluessel'] === $karte->bereich) {
                    return;
                }
                // nicht aus „erledigt“ heraus zurückschieben, wenn das Board kein auto_status hat
                DB::transaction(function () use ($karte, $board, $ziel) {
                    $this->verlauf->karte($board, $karte, 'karte_verschoben', [
                        'von' => $this->boards->bereichTitel($board, $karte->bereich),
                        'nach' => $this->boards->bereichTitel($board, $ziel['schluessel']),
                        'automatisch' => true,
                    ]);
                    $karte->bereich = $ziel['schluessel'];
                    $karte->sort = (int) $board->karten()->where('bereich', $ziel['schluessel'])->max('sort') + 1;
                    $karte->save();
                    $this->boards->versionErhoehen($board);
                });
            });
    }

    public function status(Task $task): string
    {
        if ($task->completed) {
            return 'erledigt';
        }
        if (! $task->isPersonal() && $task->taskUsers->whereNotNull('completed_at')->isNotEmpty()) {
            return 'in_arbeit';
        }

        return 'offen';
    }
}
