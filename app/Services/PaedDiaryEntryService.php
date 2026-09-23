<?php

namespace App\Services;

use App\Models\PaedDiaryEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Gemeinsame Logik für Tagebucheinträge (Web-Frontend und API v1).
 */
class PaedDiaryEntryService
{
    /**
     * Cache-Key der Wochenansicht (identisch zu PaedDiaryHelperTrait::weekCacheKey).
     */
    public function weekCacheKey(int|string $klasseId, Carbon $date): string
    {
        return "paed_diary_week_{$klasseId}_{$date->copy()->startOfWeek()->toDateString()}";
    }

    public function forgetWeekCache(int|string|null $klasseId, Carbon $date): void
    {
        if ($klasseId === null) {
            return;
        }
        Cache::forget($this->weekCacheKey($klasseId, $date));
    }

    /**
     * Schließt eine offene Notiz ab (Web-Wochenansicht und API v1).
     *
     * Betrifft der Eintrag mehrere Schüler und wird `$schuelerId` übergeben, wird nur dieser
     * Schüler abgeschlossen (eigener, abgeschlossener Eintrag); für die übrigen bleibt die Notiz offen.
     * Muss innerhalb einer DB-Transaktion aufgerufen werden.
     */
    public function complete(PaedDiaryEntry $entry, Carbon $completedAt, ?int $schuelerId = null): void
    {
        if ($entry->completed_at) {
            return;
        }

        $entry->loadMissing('schueler');
        $allStudentIds = $entry->schueler->pluck('id')->all();

        if ($schuelerId && in_array($schuelerId, $allStudentIds, true) && count($allStudentIds) > 1) {
            // Eintrag betrifft mehrere Schüler: Nur den gewählten Schüler abschließen,
            // der Eintrag bleibt für die übrigen Schüler unverändert offen.
            $entry->schueler()->detach($schuelerId);

            $completedEntry = PaedDiaryEntry::create([
                'klasse_id' => $entry->klasse_id,
                'user_id' => $entry->user_id,
                'datum' => $entry->datum,
                'content' => $entry->content,
                'category_id' => $entry->category_id,
                'dossier_only' => $entry->dossier_only,
                'completed_at' => $completedAt,
            ]);
            $completedEntry->schueler()->sync([$schuelerId]);

            // Bereits vorhandene Pausen dieses Schülers auf den neuen (abgeschlossenen) Eintrag übertragen,
            // damit finalize() sie korrekt berücksichtigt.
            $entry->pauses()->where('schueler_id', $schuelerId)
                ->update(['paed_diary_entry_id' => $completedEntry->id]);

            $completedEntry->load('schueler', 'pauses');
            $this->finalize($completedEntry);
        } else {
            // Nur ein Schüler betroffen (oder kein schueler_id übergeben): kompletten Eintrag abschließen.
            $entry->completed_at = $completedAt;
            $entry->save();
            $entry->load('schueler');
            $this->finalize($entry);
        }
    }

    /**
     * Finalisiert einen bisher offenen Eintrag beim Abschließen:
     * Für jeden Tag zwischen Startdatum und Abschlussdatum wird (unter Berücksichtigung
     * pausierter Tage je Schüler) ein eigener Eintrag angelegt.
     * (Unverändert aus PaedDiaryController::finalizeEntry übernommen.)
     */
    public function finalize(PaedDiaryEntry $entry): void
    {
        // Angepasst: pausierte Tage pro Schüler berücksichtigen
        $klasseId = $entry->klasse_id;
        $start = \Carbon\Carbon::parse($entry->datum)->startOfDay();
        $completedDate = $entry->completed_at?->copy()->startOfDay();
        if(!$completedDate){
            // Wenn kein Abschlussdatum vorhanden, keine Finalisierung (Sicherheitsnetz)
            return;
        }
        if ($completedDate->lt($start)) {
            $completedDate = $start->copy();
        }
        $entry->loadMissing('schueler','pauses');
        $allStudentIds = $entry->schueler->pluck('id')->all();
        // Pausen gruppieren: [schueler_id][Y-m-d] => true
        $pauseMap = [];
        foreach($entry->pauses as $pause){
            $pauseMap[$pause->schueler_id][$pause->date->toDateString()] = true;
        }
        // Start-Tag: entferne pausierte Schüler am Starttag aus Pivot
        $startDateStr = $start->toDateString();
        $keepStartStudents = array_filter($allStudentIds, fn($sid)=> empty($pauseMap[$sid][$startDateStr]));
        if(count($keepStartStudents) !== count($allStudentIds)){
            $entry->schueler()->sync($keepStartStudents);
        }
        // Falls keine Schüler mehr übrig -> Eintrag löschen
        if(empty($keepStartStudents)){
            $entry->schueler()->detach();
            $entry->delete();
        }
        // Weitere Tage (exklusive Start) bis einschließlich completedDate
        for($d = $start->copy()->addDay(); $d->lte($completedDate); $d->addDay()){
            $dateStr = $d->toDateString();
            // Schüler ohne Pause an diesem Tag
            $activeStudents = array_filter($allStudentIds, function($sid) use ($pauseMap, $dateStr) { return empty($pauseMap[$sid][$dateStr]); });
            if(empty($activeStudents)) continue; // nichts einzutragen
            // Prüfen ob bereits ein Eintrag mit gleichem Inhalt (und gleicher Kategorie) für (alle) diese Schüler existiert
            $existing = PaedDiaryEntry::where('klasse_id',$klasseId)
                ->whereDate('datum',$dateStr)
                ->where('content',$entry->content)
                ->when($entry->category_id === null, function($q){ $q->whereNull('category_id'); }, function($q) use ($entry){ $q->where('category_id',$entry->category_id); })
                ->whereHas('schueler', function($q) use ($activeStudents){ $q->whereIn('schueler.id',$activeStudents); })
                ->first();
            if($existing){
                // sicherstellen dass alle activeStudents verknüpft sind
                $merged = array_unique(array_merge($existing->schueler()->pluck('schueler.id')->all(), $activeStudents));
                $existing->schueler()->sync($merged);
                continue;
            }
            $newEntry = PaedDiaryEntry::create([
                'klasse_id'=>$klasseId,
                'user_id'=>$entry->user_id,
                'datum'=>$dateStr,
                'content'=>$entry->content,
                'completed_at'=>$entry->completed_at,
                'category_id'=>$entry->category_id,
                'dossier_only'=>$entry->dossier_only,
            ]);
            $newEntry->schueler()->sync($activeStudents);
        }
        $this->forgetWeekCache($klasseId, $start);
        $this->forgetWeekCache($klasseId, $completedDate);
    }
}
