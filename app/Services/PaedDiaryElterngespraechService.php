<?php

namespace App\Services;

use App\Exceptions\VersionKonfliktException;
use App\Models\PaedDiaryElterngespraech;
use App\Models\PaedDiaryElterngespraechVereinbarung;
use App\Models\PaedDiaryGoal;
use App\Models\PaedDiaryTask;
use App\Models\Schueler;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Elterngespräche der Schülerakte – gemeinsame Logik für Web (bisherige und neue Ansicht) und API v1.
 *
 * Eingaben sind bereits validiert (PaedDiaryElterngespraechRequest) und nutzen englische Schlüssel
 * wie die JSON-Ausgabe (toArray()). Beim Ändern werden nur übergebene Felder übernommen.
 */
class PaedDiaryElterngespraechService
{
    /** Eingabe-Schlüssel => Spalte */
    private const FELDER = [
        'date' => 'datum',
        'start_time' => 'beginn',
        'end_time' => 'ende',
        'location' => 'ort',
        'form' => 'form',
        'confidential' => 'vertraulich',
        'student_present' => 'schueler_anwesend',
        'parents' => 'eltern_teilnehmende',
        'other_participants' => 'weitere_teilnehmende',
        'reason' => 'anlass',
        'preparation_notes' => 'vorbereitung_notizen',
        'preparation_in_protocol' => 'vorbereitung_im_protokoll',
        'notes' => 'verlauf',
        'next_date' => 'naechster_termin',
    ];

    public function __construct(
        private PaedDiaryAppointmentService $termine,
        private PaedDiaryTaskService $aufgaben
    ) {
    }

    /**
     * Alle für den Benutzer sichtbaren Gespräche des Schülers (ohne Zeitraumfilter, damit auch
     * künftige Termine erscheinen), neueste zuerst.
     */
    public function fuerSchueler(Schueler $schueler, User $user): Collection
    {
        return PaedDiaryElterngespraech::with($this->relationen())
            ->where('schueler_id', $schueler->id)
            ->sichtbarFuer($user)
            ->orderByDesc('datum')
            ->orderByDesc('beginn')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return array{0: PaedDiaryElterngespraech, 1: array<string,int>} Gespräch und Zuordnung
     *         Client-Schlüssel => ID neu angelegter Vereinbarungen
     */
    public function anlegen(Schueler $schueler, array $daten, User $user): array
    {
        return DB::transaction(function () use ($schueler, $daten, $user) {
            $gespraech = new PaedDiaryElterngespraech([
                'schueler_id' => $schueler->id,
                'klasse_id' => $schueler->klasse_id,
                'user_id' => $user->id,
                'status' => PaedDiaryElterngespraech::STATUS_GEPLANT,
                'form' => 'vor_ort',
                'version' => 1,
            ]);
            $this->fuellen($gespraech, $daten);
            $gespraech->aktualisiert_von = $user->id;
            $gespraech->save();

            $schluessel = $this->synchronisieren($gespraech, $daten);
            $this->terminAbgleichen($gespraech, $daten, $user);

            return [$gespraech->fresh($this->relationen()), $schluessel];
        });
    }

    /**
     * @param int|null $version Stand, auf dem die Änderung beruht (null = ohne Konfliktprüfung)
     * @return array{0: PaedDiaryElterngespraech, 1: array<string,int>}
     * @throws VersionKonfliktException wenn zwischenzeitlich jemand anderes gespeichert hat
     */
    public function aktualisieren(PaedDiaryElterngespraech $gespraech, array $daten, User $user, ?int $version = null): array
    {
        return DB::transaction(function () use ($gespraech, $daten, $user, $version) {
            $gespraech = PaedDiaryElterngespraech::whereKey($gespraech->id)->lockForUpdate()->firstOrFail();
            if ($version !== null && $version !== (int) $gespraech->version) {
                throw new VersionKonfliktException('Das Gespräch wurde zwischenzeitlich geändert.');
            }

            $this->fuellen($gespraech, $daten);
            $gespraech->aktualisiert_von = $user->id;
            $gespraech->version = (int) $gespraech->version + 1;
            $gespraech->save();

            $schluessel = $this->synchronisieren($gespraech, $daten);
            $this->terminAbgleichen($gespraech, $daten, $user);

            return [$gespraech->fresh($this->relationen()), $schluessel];
        });
    }

    public function abschliessen(PaedDiaryElterngespraech $gespraech, User $user): PaedDiaryElterngespraech
    {
        return $this->statusSetzen($gespraech, $user, [
            'status' => PaedDiaryElterngespraech::STATUS_ABGESCHLOSSEN,
            'abgeschlossen_at' => Carbon::now(),
            'abgeschlossen_von' => $user->id,
        ]);
    }

    public function wiederOeffnen(PaedDiaryElterngespraech $gespraech, User $user): PaedDiaryElterngespraech
    {
        return $this->statusSetzen($gespraech, $user, [
            'status' => PaedDiaryElterngespraech::STATUS_GEPLANT,
            'abgeschlossen_at' => null,
            'abgeschlossen_von' => null,
            'abgesagt_at' => null,
        ]);
    }

    /** Absagen entfernt den Kalendertermin. */
    public function absagen(PaedDiaryElterngespraech $gespraech, User $user): PaedDiaryElterngespraech
    {
        return DB::transaction(function () use ($gespraech, $user) {
            $this->terminEntfernen($gespraech);

            return $this->statusSetzen($gespraech, $user, [
                'status' => PaedDiaryElterngespraech::STATUS_ABGESAGT,
                'abgesagt_at' => Carbon::now(),
            ]);
        });
    }

    public function loeschen(PaedDiaryElterngespraech $gespraech): void
    {
        DB::transaction(function () use ($gespraech) {
            $this->terminEntfernen($gespraech);
            $gespraech->teilnehmer()->detach();
            $gespraech->vereinbarungen()->delete();
            $gespraech->delete();
        });
    }

    /**
     * Übernimmt eine Vereinbarung als Aufgabe oder Ziel in die Schülerakte. Wiederholte Aufrufe
     * legen nichts doppelt an.
     *
     * @param string $art aufgabe|ziel
     */
    public function vereinbarungUebernehmen(PaedDiaryElterngespraechVereinbarung $vereinbarung, string $art, User $user): PaedDiaryElterngespraechVereinbarung
    {
        $gespraech = $vereinbarung->gespraech;
        $schueler = $gespraech->schueler;
        $datum = $gespraech->datum->format('d.m.Y');

        if ($art === 'aufgabe') {
            if ($vereinbarung->paed_diary_task_id && PaedDiaryTask::whereKey($vereinbarung->paed_diary_task_id)->exists()) {
                return $vereinbarung;
            }
            if (!$schueler->klasse_id) {
                throw ValidationException::withMessages(['type' => 'Der Schüler ist keiner Klasse zugeordnet.']);
            }
            $text = trim($vereinbarung->text);
            [$aufgabe] = $this->aufgaben->createForStudents($schueler->klasse_id, [$schueler->id], [
                'title' => Str::limit($text, 250),
                'description' => trim('Aus dem Elterngespräch vom ' . $datum . '.' . (mb_strlen($text) > 250 ? "\n\n" . $text : '')),
                'due_date' => $vereinbarung->bis?->toDateString(),
            ], $user->id);
            $vereinbarung->update(['paed_diary_task_id' => $aufgabe->id]);
        } else {
            if ($vereinbarung->paed_diary_goal_id && PaedDiaryGoal::whereKey($vereinbarung->paed_diary_goal_id)->exists()) {
                return $vereinbarung;
            }
            $ziel = PaedDiaryGoal::create([
                'schueler_id' => $schueler->id,
                'user_id' => $user->id,
                'goal_text' => Str::limit(trim($vereinbarung->text), 2000),
            ]);
            $vereinbarung->update(['paed_diary_goal_id' => $ziel->id]);
        }

        return $vereinbarung->fresh();
    }

    /**
     * JSON-Darstellung für Web und API (englische Schlüssel) inkl. Berechtigungen für die Oberfläche.
     */
    public function toArray(PaedDiaryElterngespraech $g, User $user): array
    {
        $g->loadMissing($this->relationen());

        return [
            'id' => $g->id,
            'student_id' => $g->schueler_id,
            'class_id' => $g->klasse_id,
            'date' => $g->datum?->toDateString(),
            'start_time' => $g->beginn ? substr($g->beginn, 0, 5) : null,
            'end_time' => $g->ende ? substr($g->ende, 0, 5) : null,
            'location' => $g->ort,
            'form' => $g->form,
            'form_label' => PaedDiaryElterngespraech::FORMEN[$g->form] ?? $g->form,
            'status' => $g->status,
            'status_label' => PaedDiaryElterngespraech::STATUS[$g->status] ?? $g->status,
            'protocol_open' => $g->protokollOffen(),
            'confidential' => (bool) $g->vertraulich,
            'student_present' => (bool) $g->schueler_anwesend,
            'parents' => $g->eltern_teilnehmende,
            'other_participants' => $g->weitere_teilnehmende,
            'reason' => $g->anlass,
            'topics' => array_values($g->gespraechspunkte ?? []),
            'preparation_notes' => $g->vorbereitung_notizen,
            'preparation_in_protocol' => (bool) $g->vorbereitung_im_protokoll,
            'notes' => $g->verlauf,
            'next_date' => $g->naechster_termin?->toDateString(),
            'participants' => $g->teilnehmer->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values(),
            'agreements' => $g->vereinbarungen->map(fn ($v) => [
                'id' => $v->id,
                'text' => $v->text,
                'responsible' => $v->verantwortlich,
                'due_date' => $v->bis?->toDateString(),
                'task_id' => $v->paed_diary_task_id,
                'goal_id' => $v->paed_diary_goal_id,
            ])->values(),
            'calendar' => (bool) $g->paed_diary_appointment_id,
            'appointment_id' => $g->paed_diary_appointment_id,
            'pause_entries' => (bool) $g->appointment?->pause_entries,
            'created_by' => $g->user ? ['id' => $g->user->id, 'name' => $g->user->name] : null,
            'completed_at' => $g->abgeschlossen_at?->toIso8601String(),
            'completed_by' => $g->abgeschlossenVon?->name,
            'cancelled_at' => $g->abgesagt_at?->toIso8601String(),
            'updated_at' => $g->updated_at?->toIso8601String(),
            'updated_by' => $g->aktualisiertVon?->name,
            'version' => (int) $g->version,
            'can' => [
                'update' => $user->can('update', $g),
                'complete' => $user->can('abschliessen', $g),
                'cancel' => $user->can('absagen', $g),
                'reopen' => $user->can('wiederOeffnen', $g),
                'delete' => $user->can('delete', $g),
            ],
        ];
    }

    /**
     * Kolleg:innen für die Teilnehmer-Auswahl: zuerst Lehrkräfte der Klasse, dann alle übrigen
     * Personen mit Zugriff auf das Tagebuch.
     */
    public function kollegenAuswahl(Schueler $schueler): Collection
    {
        $klasse = $schueler->klasse?->paed_users()->orderBy('name')->get(['users.id', 'users.name']) ?? collect();
        try {
            $weitere = User::permission('view paed diary')->whereNotIn('users.id', $klasse->pluck('id'))->orderBy('name')->get(['users.id', 'users.name']);
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist $e) {
            $weitere = collect();
        }

        return $klasse->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'class' => true])
            ->concat($weitere->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'class' => false]))
            ->values();
    }

    private function relationen(): array
    {
        return ['teilnehmer:id,name', 'vereinbarungen', 'appointment', 'user:id,name', 'abgeschlossenVon:id,name', 'aktualisiertVon:id,name'];
    }

    private function fuellen(PaedDiaryElterngespraech $g, array $daten): void
    {
        foreach (self::FELDER as $schluessel => $spalte) {
            if (array_key_exists($schluessel, $daten)) {
                $wert = $daten[$schluessel];
                $g->{$spalte} = is_string($wert) && trim($wert) === '' ? null : $wert;
            }
        }
        if (array_key_exists('form', $daten) && !$daten['form']) {
            $g->form = 'vor_ort';
        }
        if (array_key_exists('topics', $daten)) {
            $g->gespraechspunkte = collect($daten['topics'] ?? [])
                ->map(fn ($p) => [
                    'title' => trim((string) ($p['title'] ?? '')),
                    'preparation' => trim((string) ($p['preparation'] ?? '')),
                    'result' => trim((string) ($p['result'] ?? '')),
                ])
                ->reject(fn ($p) => $p['title'] === '' && $p['preparation'] === '' && $p['result'] === '')
                ->values()
                ->all();
        }
    }

    /**
     * Teilnehmer und Vereinbarungen übernehmen (nur wenn übergeben).
     *
     * @return array<string,int> Client-Schlüssel => ID neu angelegter Vereinbarungen
     */
    private function synchronisieren(PaedDiaryElterngespraech $g, array $daten): array
    {
        if (array_key_exists('participant_ids', $daten)) {
            $g->teilnehmer()->sync(array_values(array_unique(array_map('intval', $daten['participant_ids'] ?? []))));
        }
        if (!array_key_exists('agreements', $daten)) {
            return [];
        }

        $vorhanden = $g->vereinbarungen()->get()->keyBy('id');
        $behalten = [];
        $schluessel = [];
        foreach (array_values($daten['agreements'] ?? []) as $i => $eingabe) {
            $text = trim((string) ($eingabe['text'] ?? ''));
            $id = isset($eingabe['id']) ? (int) $eingabe['id'] : null;
            if ($text === '' && !($id && $vorhanden->has($id))) {
                continue;
            }
            $werte = [
                'text' => $text,
                'verantwortlich' => $eingabe['responsible'] ?? null,
                'bis' => $eingabe['due_date'] ?? null,
                'sort_order' => $i,
            ];
            if ($id && $vorhanden->has($id)) {
                $vorhanden[$id]->update($werte);
                $behalten[] = $id;
            } else {
                $neu = $g->vereinbarungen()->create($werte);
                $behalten[] = $neu->id;
                if (!empty($eingabe['key'])) {
                    $schluessel[(string) $eingabe['key']] = $neu->id;
                }
            }
        }
        $g->vereinbarungen()->whereNotIn('id', $behalten)->delete();

        return $schluessel;
    }

    /**
     * Gleicht den Termin im Tagebuch-Kalender ab. Er wird nur geschrieben, wenn sich Termin-Felder
     * geändert haben – Autospeichern soll den Termin (und die Pausen) nicht ständig neu setzen.
     */
    private function terminAbgleichen(PaedDiaryElterngespraech $g, array $daten, User $user): void
    {
        $termin = $g->paed_diary_appointment_id ? $g->appointment()->first() : null;
        $gewuenscht = array_key_exists('calendar', $daten) ? (bool) $daten['calendar'] : (bool) $termin;
        $pausieren = array_key_exists('pause_entries', $daten) ? (bool) $daten['pause_entries'] : (bool) $termin?->pause_entries;

        if (!$gewuenscht || !$g->istGeplant()) {
            $this->terminEntfernen($g);

            return;
        }

        $schueler = $g->schueler;
        $soll = [
            'title' => 'Elterngespräch ' . $schueler->vorname . ' ' . $schueler->nachname,
            'description' => null,
            'start_date' => $g->datum->toDateString(),
            'start_time' => $g->beginn ? substr($g->beginn, 0, 5) : null,
            'end_time' => $g->ende ? substr($g->ende, 0, 5) : null,
        ];

        if ($termin) {
            $unveraendert = $termin->title === $soll['title']
                && $termin->start_date?->toDateString() === $soll['start_date']
                && $termin->start_time?->format('H:i') === $soll['start_time']
                && $termin->end_time?->format('H:i') === $soll['end_time']
                && (bool) $termin->pause_entries === $pausieren
                && !$termin->is_recurring
                && $termin->schueler()->where('schueler.id', $schueler->id)->exists();
            if ($unveraendert) {
                return;
            }
        }

        $termin = $this->termine->fuerSchueler($schueler, $soll, $pausieren, $user, $termin);
        if ((int) $g->paed_diary_appointment_id !== (int) $termin->id) {
            $g->paed_diary_appointment_id = $termin->id;
            $g->saveQuietly();
        }
    }

    private function terminEntfernen(PaedDiaryElterngespraech $g): void
    {
        $termin = $g->paed_diary_appointment_id ? $g->appointment()->first() : null;
        if ($termin) {
            $this->termine->delete($termin);
        }
        if ($g->paed_diary_appointment_id) {
            $g->paed_diary_appointment_id = null;
            $g->saveQuietly();
        }
    }

    private function statusSetzen(PaedDiaryElterngespraech $g, User $user, array $werte): PaedDiaryElterngespraech
    {
        $g->fill($werte);
        $g->aktualisiert_von = $user->id;
        $g->version = (int) $g->version + 1;
        $g->save();

        return $g->fresh($this->relationen());
    }
}
