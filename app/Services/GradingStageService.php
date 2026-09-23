<?php

namespace App\Services;

use App\Models\GradingStage;
use App\Models\Klasse;
use App\Models\PaedDiaryEntry;
use App\Models\Schueler;
use App\Models\SchuelerGradingHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Vergabe von Graduierungsstufen inkl. Historie und automatischem Tagebucheintrag.
 *
 * Gemeinsam genutzt vom Web-Frontend (PaedDiaryController::changeSchuelerStage)
 * und der API v1 (GradingApiController), damit beide Wege identische Daten erzeugen.
 */
class GradingStageService
{
    /**
     * @throws InvalidArgumentException wenn die Stufe nicht zum Graduierungssystem der Klasse gehört
     * @throws \Throwable bei Datenbankfehlern (Transaktion wird zurückgerollt)
     */
    public function changeStage(
        Schueler $schueler,
        ?GradingStage $newStage,
        User $user,
        ?Klasse $klasse = null,
        ?int $paedDiaryEntryId = null
    ): SchuelerGradingHistory {
        $klasse ??= $schueler->klasse;

        if ($newStage && $klasse?->grading_system_id && (int) $newStage->grading_system_id !== (int) $klasse->grading_system_id) {
            throw new InvalidArgumentException('Stage gehört nicht zum System der Klasse');
        }

        $previous = $schueler->grading_stage_id;

        $history = DB::transaction(function () use ($schueler, $newStage, $user, $klasse, $paedDiaryEntryId, $previous) {
            $schueler->grading_stage_id = $newStage?->id;
            $schueler->save();

            if (empty($paedDiaryEntryId)) {
                $prevStageName = $previous ? GradingStage::find($previous)?->name : null;
                $parts = [];
                if ($prevStageName) {
                    $parts[] = 'von "' . $prevStageName . '"';
                }
                if ($newStage?->name) {
                    $parts[] = 'auf "' . $newStage->name . '"';
                }
                $changeText = 'Stufe geändert ' . ($parts ? implode(' ', $parts) : '') . ' für ' . $schueler->vorname . ' ' . $schueler->nachname . '.';

                $entry = PaedDiaryEntry::create([
                    'klasse_id' => $klasse?->id ?? $schueler->klasse_id,
                    'user_id' => $user->id,
                    'datum' => now()->toDateString(),
                    'content' => $changeText,
                    'completed_at' => Carbon::now(),
                ]);
                $entry->schueler()->sync([$schueler->id]);
                $paedDiaryEntryId = $entry->id;
            }

            return SchuelerGradingHistory::create([
                'schueler_id' => $schueler->id,
                'grading_system_id' => $klasse?->grading_system_id ?? $newStage?->grading_system_id,
                'grading_stage_id' => $newStage?->id,
                'previous_grading_stage_id' => $previous,
                'changed_by' => $user->id,
                'paed_diary_entry_id' => $paedDiaryEntryId,
                'created_at' => now(),
            ]);
        });

        // Wochen-Cache des Tagebuchs invalidieren (siehe PaedDiaryHelperTrait::weekCacheKey)
        $klasseId = $klasse?->id ?? $schueler->klasse_id;
        if ($klasseId) {
            Cache::forget('paed_diary_week_' . $klasseId . '_' . Carbon::now()->startOfWeek()->toDateString());
        }

        return $history;
    }
}
