<?php
namespace App\Http\Controllers;

use App\Models\GradingStage;
use App\Models\SchuelerGradingHistory;
use App\Models\Klasse;
use App\Models\PaedDiaryColumn;
use App\Models\PaedDiaryColumnValue;
use App\Models\PaedDiaryEntry;
use App\Models\PaedDiaryTask;
use App\Models\Schueler;
use App\Models\PaedDiaryClassGroup;
use App\Exports\PaedDiaryExport;
use App\Exports\PaedDiarySchuelerExport;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Exception;

class PaedDiaryController extends Controller
{
    /**
     * Zeigt die Hauptansicht des Pädagogischen Tagebuchs für eine Klasse des angemeldeten Benutzers.
     * Wählt entweder die angeforderte Klasse oder die erste verfügbare Klasse.
     *
     * @param Request $request
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $klassen = $user->paed_klassen()->withCount('schueler')->orderBy('name')->get();
        if ($klassen->isEmpty()){
            return redirect()->back()->with(['type'=>'warning','Meldung'=>'Keine Klassen zugewiesen.']);
        }
        $klasse = null;
        if ($request->filled('klasse')){
            $klasse = $klassen->firstWhere('id',(int)$request->get('klasse'));
        }
        if (!$klasse){
            $klasse = $klassen->first();
        }
        $groups = Auth::user()->paed_diary_class_groups()->with('klassen:id,name')->get();
        return view('paedDiary.index', [
            'klassen'=>$klassen,
            'klasse'=>$klasse,
            'groups'=>$groups,
        ]);
    }

    /**
     * Erzeugt einen Cache-Key für eine Klassen-Woche.
     *
     * @param int $klasseId
     * @param \Carbon\Carbon $weekStart Wochenbeginn (Montag)
     * @return string
     */
    private function weekCacheKey($klasseId, Carbon $weekStart){
        return 'paed_week_'.$klasseId.'_'.$weekStart->format('Ymd');
    }

    /**
     * Löscht den Wochen-Cache für das Datum (wird auf Wochenbeginn normalisiert).
     *
     * @param int $klasseId
     * @param \Carbon\Carbon $date Beliebiges Datum innerhalb der Woche
     * @return void
     */
    private function forgetWeekCache($klasseId, Carbon $date){
        $start=$date->copy()->startOfWeek();
        Cache::forget($this->weekCacheKey($klasseId,$start));
    }

    /**
     * Liefert alle relevanten Wochen-Daten (Tage, Schüler, Einträge, Spalten, Spaltenwerte, offene Aufgaben).
     * Wird via AJAX vom Frontend abgefragt.
     *
     * @param Request $request {klasse_id:int, week_start?:date}
     * @return \Illuminate\Http\JsonResponse
     */
    public function weekData(Request $request)
    {
        $request->validate([
            'klasse_id' => ['nullable','integer','exists:klassen,id'],
            'group_id' => ['nullable','integer','exists:paed_diary_class_groups,id'],
            'week_start' => ['nullable','date']
        ]);
        if (!$request->filled('klasse_id') && !$request->filled('group_id')) {
            return response()->json(['message'=>'klasse_id oder group_id erforderlich'],422);
        }
        $user = Auth::user();
        $isGroup = $request->filled('group_id');
        $group = null; $klasse = null; $klassen = collect();
        if ($isGroup) {
            $group = PaedDiaryClassGroup::with('klassen:id,name,kuerzel')->where('id',$request->group_id)->where('user_id',$user->id)->firstOrFail();
            $userKlassenIds = $user->paed_klassen()->pluck('klassen.id');
            $klassen = $group->klassen->whereIn('id',$userKlassenIds)->values();
            if ($klassen->isEmpty()) {
                return response()->json(['message'=>'Keine Klassen in Gruppe verfügbar'],422);
            }
        } else {
            $klasse = $user->paed_klassen()->where('klassen.id',$request->klasse_id)->firstOrFail();
            $klassen = collect([$klasse]);
        }
        $weekStart = $request->filled('week_start') ? Carbon::parse($request->week_start)->startOfWeek() : Carbon::now()->startOfWeek();
        $periodEnd = $weekStart->copy()->addDays(4);
        $period = CarbonPeriod::create($weekStart, $periodEnd);
        $days = collect();
        foreach ($period as $date){ $days->push(['date'=>$date->toDateString(),'label'=>$date->format('D d.m.')]); }

        // Schüler aller Klassen laden
        $schueler = \App\Models\Schueler::whereIn('klasse_id',$klassen->pluck('id'))->with('grading_stage')->orderBy('klasse_id')->orderBy('vorname')->orderBy('nachname')->get(['id','vorname','nachname','grading_stage_id','klasse_id']);

        // Spalten aller Klassen vereinigt
        $columns = PaedDiaryColumn::whereIn('klasse_id',$klassen->pluck('id'))
            ->when(Schema::hasColumn('paed_diary_columns','deactivated_from'), function($q) use ($weekStart){
                $q->where(function($qq) use ($weekStart){
                    $qq->whereNull('deactivated_from')->orWhere('deactivated_from','>', $weekStart->toDateString());
                });
            })
            ->orderBy('klasse_id')->orderBy('sort_order')->get();
        $entries = PaedDiaryEntry::with(['schueler:id','user:id,name'])
            ->whereIn('klasse_id',$klassen->pluck('id'))
            ->whereBetween('datum', [$weekStart->toDateString(), $periodEnd->toDateString()])
            ->get();
        $entryData = $entries->map(fn($e)=>[
            'id'=>$e->id,'date'=>$e->datum->toDateString(),'content'=>$e->content,
            'schueler_ids'=>$e->schueler->pluck('id'),'user'=>$e->user?->name,
            'completed_at'=>$e->completed_at,
            'klasse_id'=>$e->klasse_id
        ]);
        $openEntries = $entries->filter(fn($e)=>!$e->completed_at)->map(fn($e)=>[
            'id'=>$e->id,
            'schueler_ids'=>$e->schueler->pluck('id'),
            'date'=>$e->datum->toDateString(),
            'content'=>$e->content,
            'user'=>$e->user?->name,
            'klasse_id'=>$e->klasse_id
        ])->values();
        $columnValues = PaedDiaryColumnValue::whereIn('paed_diary_column_id', $columns->pluck('id'))
            ->whereBetween('datum', [$weekStart->toDateString(), $periodEnd->toDateString()])
            ->get();
        $valuesGrouped = [];
        foreach ($columnValues as $v){
            $valuesGrouped[$v->paed_diary_column_id][$v->schueler_id][$v->datum->toDateString()] = $v->value;
        }
        $tasks = PaedDiaryTask::whereIn('klasse_id',$klassen->pluck('id'))->open()->with('schueler:id,vorname,nachname')->get()->map(fn($t)=>[
            'id'=>$t->id,'schueler_id'=>$t->schueler_id,'title'=>$t->title,
            'due_date'=>$t->due_date?->toDateString(),'highlighted'=>$t->highlighted,'klasse_id'=>$t->klasse_id,
        ]);
        return response()->json([
            'is_group'=>$isGroup,
            'group'=>$isGroup ? ['id'=>$group->id,'name'=>$group->name] : null,
            'days'=>$days,
            'schueler'=>$schueler->map(fn($s)=>[
                'id'=>$s->id,'name'=>$s->vorname.' '.$s->nachname,
                'klasse_id'=>$s->klasse_id,
                'klasse_name'=>$klassen->firstWhere('id',$s->klasse_id)?->name,
                'stage'=> $s->grading_stage ? ['id'=>$s->grading_stage->id,'name'=>$s->grading_stage->name,'symbol'=>$s->grading_stage->symbol,'sort_order'=>$s->grading_stage->sort_order,'image_url'=>$s->grading_stage->image_url] : null
            ]),
            'klassen'=>$klassen->map(fn($k)=>['id'=>$k->id,'name'=>$k->name,'kuerzel'=>$k->kuerzel]),
            'can_manage_grading' => Auth::user()->can('manage grading systems'),
            'entries'=>$entryData,
            'open_entries'=>$openEntries,
            'columns'=>$columns->map(fn($c)=>['id'=>$c->id,'name'=>$c->name,'slug'=>$c->slug,'type'=>$c->type,'klasse_id'=>$c->klasse_id,'deactivated_from'=>Schema::hasColumn('paed_diary_columns','deactivated_from') ? $c->deactivated_from?->toDateString():null]),
            'column_values'=>$valuesGrouped,
            'tasks'=>$tasks,
        ]);
    }

    /**
     * Liefert alle Einträge einer Zelle (Schüler + Datum) zur Anzeige im Detail.
     *
     * @param Request $request {klasse_id, schueler_id, date}
     * @return \Illuminate\Http\JsonResponse
     */
    public function cellEntries(Request $request){
        $data = $request->validate([
            'klasse_id'=>['required','integer','exists:klassen,id'],
            'schueler_id'=>['required','integer','exists:schueler,id'],
            'date'=>['required','date']
        ]);
        $user = Auth::user();
        $klasse = $user->paed_klassen()->where('klassen.id',$data['klasse_id'])->firstOrFail();
        $schueler = Schueler::where('id',$data['schueler_id'])->where('klasse_id',$klasse->id)->firstOrFail();
        $entries = PaedDiaryEntry::with(['user:id,name','schueler:id'])
            ->where('klasse_id',$klasse->id)
            ->whereDate('datum', Carbon::parse($data['date'])->toDateString())
            ->whereHas('schueler', fn($q)=>$q->where('schueler.id',$schueler->id))
            ->orderByDesc('id')
            ->get()
            ->map(fn($e)=>[
                'id'=>$e->id,'content'=>$e->content,'author'=>$e->user?->name,'count_schueler'=>$e->schueler->count()
            ]);
        return response()->json(['entries'=>$entries]);
    }

    /**
     * Exportiert die Wochen-Daten einer Klasse als Excel-Datei.
     *
     * @param Request $request {klasse_id, week_start?}
     * @return StreamedResponse
     */
    public function exportExcel(Request $request){
        $request->validate([
            'klasse_id'=>['nullable','integer','exists:klassen,id'],
            'group_id'=>['nullable','integer','exists:paed_diary_class_groups,id'],
            'week_start'=>['nullable','date']
        ]);
        if(!$request->filled('klasse_id') && !$request->filled('group_id')){
            return response()->json(['message'=>'klasse_id oder group_id erforderlich'],422);
        }
        $user = Auth::user();
        $isGroup = $request->filled('group_id');
        $klassen = collect(); $group = null; $klasse = null;
        if($isGroup){
            $group = PaedDiaryClassGroup::with('klassen:id,name,kuerzel')
                ->where('id',$request->group_id)->where('user_id',$user->id)->firstOrFail();
            $userKlassenIds = $user->paed_klassen()->pluck('klassen.id');
            $klassen = $group->klassen->whereIn('id',$userKlassenIds)->values();
            if($klassen->isEmpty()) return response()->json(['message'=>'Keine Klassen in Gruppe verfügbar'],422);
        } else {
            $klasse = $user->paed_klassen()->where('klassen.id',$request->klasse_id)->firstOrFail();
            $klassen = collect([$klasse]);
        }
        $weekStart = $request->filled('week_start')? Carbon::parse($request->week_start)->startOfWeek():Carbon::now()->startOfWeek();
        $weekEnd = $weekStart->copy()->addDays(4);
        $entries = PaedDiaryEntry::with(['user:id,name','schueler.grading_stage'])
            ->whereIn('klasse_id',$klassen->pluck('id'))
            ->whereBetween('datum',[$weekStart->toDateString(),$weekEnd->toDateString()])
            ->orderBy('datum')
            ->get();
        $rows = [];
        foreach($entries as $entry){
            foreach($entry->schueler as $s){
                $kObj = $klassen->firstWhere('id',$entry->klasse_id);
                $rows[] = [
                    'Klasse'=>$kObj? $kObj->name:'',
                    'Kürzel'=>$kObj? $kObj->kuerzel:'',
                    'Datum'=>$entry->datum->format('Y-m-d'),
                    'Schüler'=>$s->vorname.' '.$s->nachname,
                    'Autor'=>$entry->user?->name,
                    'Notiz'=>preg_replace('/\s+/',' ',trim($entry->content)),
                    'Stufe'=>$s->grading_stage?->name ?? ''
                ];
            }
        }
        $filename = $isGroup
            ? 'paed_tagebuch_gruppe_'.$group->name.'_'.$weekStart->format('Ymd').'.xlsx'
            : 'paed_tagebuch_'.($klasse->kuerzel ?? 'klasse').'_'.$weekStart->format('Ymd').'.xlsx';
        return Excel::download(new PaedDiaryExport($rows), $filename);
    }

    /**
     * Speichert einen neuen Tagebuch-Eintrag (Notiz) für ausgewählte Schüler.
     *
     * @param Request $request {klasse_id, date, content, schueler_ids[]}
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeEntry(Request $request){
        $validated = $request->validate([
            'klasse_id'=>['nullable','integer','exists:klassen,id'],
            'group_id'=>['nullable','integer','exists:paed_diary_class_groups,id'],
            'date'=>['required','date'],
            'content'=>['required','string'],
            'schueler_ids'=>['required','array','min:1'],
            'schueler_ids.*'=>['integer','exists:schueler,id'],
            'completed'=>['nullable']
        ]);
        if(!$request->filled('klasse_id') && !$request->filled('group_id')){
            return response()->json(['message'=>'klasse_id oder group_id erforderlich'],422);
        }
        $user = Auth::user();
        $isGroup = $request->filled('group_id');
        $dateObj = Carbon::parse($validated['date']);
        $idsCreated = [];
        if($isGroup){
            $group = PaedDiaryClassGroup::with('klassen:id')->where('id',$request->group_id)->where('user_id',$user->id)->firstOrFail();
            $userKlassenIds = $user->paed_klassen()->pluck('klassen.id');
            foreach($group->klassen->whereIn('id',$userKlassenIds) as $klasse){
                $schuelerIds = Schueler::whereIn('id',$validated['schueler_ids'])
                    ->where('klasse_id',$klasse->id)->pluck('id')->all();
                if(empty($schuelerIds)) continue;
                $entry = PaedDiaryEntry::create([
                    'klasse_id'=>$klasse->id,
                    'user_id'=>$user->id,
                    'datum'=>$dateObj->toDateString(),
                    'content'=>trim($validated['content']),
                    'completed_at'=>$request->has('completed')?Carbon::now():null
                ]);
                $entry->schueler()->sync($schuelerIds);
                $this->forgetWeekCache($klasse->id,$dateObj);
                $idsCreated[]=$entry->id;
            }
            return response()->json(['success'=>true,'entry_ids'=>$idsCreated]);
        }
        $klasse = $user->paed_klassen()->where('klassen.id',$validated['klasse_id'])->firstOrFail();
        $schuelerIds = Schueler::whereIn('id',$validated['schueler_ids'])
            ->where('klasse_id',$klasse->id)->pluck('id')->all();
        if(empty($schuelerIds)) return response()->json(['message'=>'Keine gültigen Schüler'],422);
        $entry = PaedDiaryEntry::create([
            'klasse_id'=>$klasse->id,
            'user_id'=>$user->id,
            'datum'=>$dateObj->toDateString(),
            'content'=>trim($validated['content']),
            'completed_at'=>$request->has('completed')?Carbon::now():null
        ]);
        $entry->schueler()->sync($schuelerIds);
        $this->forgetWeekCache($klasse->id,$dateObj);
        return response()->json(['success'=>true,'entry_id'=>$entry->id]);
    }

    /**
     * Aktualisiert einen bestehenden Tagebuch-Eintrag.
     *
     * @param PaedDiaryEntry $entry Route-Model-Binding
     * @param Request $request {klasse_id, date, content, schueler_ids[]}
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateEntry(PaedDiaryEntry $entry, Request $request)
    {
        // ...existing code before $entry->update([...]) remains...
        // (Wir fügen nur Transaktions-/Finalize-Logik hinzu.)
        $validated = $request->validate([
            'klasse_id'=>['required','integer','exists:klassen,id'],
            'date'=>['required','date'],
            'content'=>['required','string'],
            'schueler_ids'=>['required','array','min:1'],
            'schueler_ids.*'=>['integer','exists:schueler,id'],
            'completed' => ['nullable']
        ]);
        $user = Auth::user();
        if ($entry->klasse_id != $validated['klasse_id']){
            abort(403);
        }
        $klasse = $user->paed_klassen()->where('klassen.id',$validated['klasse_id'])->firstOrFail();
        $oldDate = $entry->datum->copy();
        $newDate = \Carbon\Carbon::parse($validated['date']);
        $validSchueler = \App\Models\Schueler::whereIn('id',$validated['schueler_ids'])->where('klasse_id',$klasse->id)->pluck('id')->all();
        if (empty($validSchueler)){
            return response()->json(['message'=>'Keine gültigen Schüler'],422);
        }
        $wasCompleted = (bool)$entry->completed_at;
        $completedAt = $entry->completed_at;
        if ($request->has('completed')) {
            if (!$entry->completed_at) {
                $completedAt = \Carbon\Carbon::now();
            }
        } else {
            $completedAt = null; // Re-Open
        }
        \DB::beginTransaction();
        try {
            $entry->update([
                'datum'=>$newDate->toDateString(),
                'content'=>trim($validated['content']),
                'completed_at'=>$completedAt
            ]);
            $entry->schueler()->sync($validSchueler);
            // Falls gerade von offen -> abgeschlossen gewechselt: Klon-Logik anwenden
            if (!$wasCompleted && $completedAt) {
                // frisch laden (Relationen aktualisieren)
                $entry->load('schueler');
                $this->finalizeEntry($entry);
            }
            \DB::commit();
        } catch (\Throwable $e) {
            \DB::rollBack();
            return response()->json(['message'=>'Fehler beim Aktualisieren'],500);
        }
        $this->forgetWeekCache($klasse->id, $oldDate);
        if (!$oldDate->isSameWeek($newDate)){
            $this->forgetWeekCache($klasse->id, $newDate);
        }
        return response()->json(['success'=>true]);
    }

    /**
     * Löscht einen Tagebuch-Eintrag (inkl. Pivot-Einträge) und invalidiert den Cache.
     *
     * @param PaedDiaryEntry $entry
     * @param Request $request {klasse_id}
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroyEntry(PaedDiaryEntry $entry, Request $request)
    {
        $data = $request->validate([
            'klasse_id'=>['required','integer','exists:klassen,id']
        ]);
        $user = Auth::user();
        if ($entry->klasse_id != $data['klasse_id']){
            abort(403);
        }
        $klasse = $user->paed_klassen()->where('klassen.id',$entry->klasse_id)->firstOrFail();
        $date = $entry->datum->copy();
        $entry->schueler()->detach();
        $entry->delete();
        $this->forgetWeekCache($klasse->id, $date);
        return response()->json(['success'=>true]);
    }

    /**
     * Legt eine neue Zusatz-Spalte (Column) für die Klasse oder Gruppe an.
     * Unterstützt auch Gruppenmodus (group_id).
     *
     * @param Request $request {klasse_id, group_id, name, type?}
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeColumn(Request $request)
    {
        $data = $request->validate([
            'klasse_id'=>['nullable','integer','exists:klassen,id'],
            'group_id'=>['nullable','integer','exists:paed_diary_class_groups,id'],
            'name'=>['required','string','max:50'],
            'type'=>['nullable','in:text,boolean,number']
        ]);
        if(!$request->filled('klasse_id') && !$request->filled('group_id'))
            return response()->json(['message'=>'klasse_id oder group_id erforderlich'],422);
        $user = Auth::user();
        $slug = Str::slug($data['name']);
        $type = $data['type'] ?? 'text';
        if($request->filled('group_id')){
            $group = PaedDiaryClassGroup::with('klassen:id')->where('id',$request->group_id)->where('user_id',$user->id)->firstOrFail();
            $userKlassenIds = $user->paed_klassen()->pluck('klassen.id');
            $created = [];
            foreach($group->klassen->whereIn('id',$userKlassenIds) as $klasse){
                if(PaedDiaryColumn::where('klasse_id',$klasse->id)->where('slug',$slug)->exists()) continue;
                $sort = PaedDiaryColumn::where('klasse_id',$klasse->id)->max('sort_order') + 1;
                $col = PaedDiaryColumn::create([
                    'klasse_id'=>$klasse->id,'name'=>$data['name'],'slug'=>$slug,'type'=>$type,'sort_order'=>$sort
                ]);
                $this->forgetWeekCache($klasse->id,Carbon::now());
                $created[] = ['id'=>$col->id,'klasse_id'=>$klasse->id,'name'=>$col->name];
            }
            return response()->json(['success'=>true,'columns'=>$created]);
        }
        $klasse = $user->paed_klassen()->where('klassen.id',$data['klasse_id'])->firstOrFail();
        if(PaedDiaryColumn::where('klasse_id',$klasse->id)->where('slug',$slug)->exists())
            return response()->json(['message'=>'Spalte existiert bereits'],422);
        $sort = PaedDiaryColumn::where('klasse_id',$klasse->id)->max('sort_order') + 1;
        $col = PaedDiaryColumn::create([
            'klasse_id'=>$klasse->id,'name'=>$data['name'],'slug'=>$slug,'type'=>$type,'sort_order'=>$sort
        ]);
        $this->forgetWeekCache($klasse->id,Carbon::now());
        return response()->json(['success'=>true,'column'=>['id'=>$col->id,'name'=>$col->name]]);
    }

    /**
     * Speichert oder aktualisiert den Wert einer Zusatz-Spalte für einen Schüler an einem Datum.
     *
     * @param Request $request {column_id, schueler_id, date, value?}
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeColumnValue(Request $request)
    {
        $data = $request->validate([
            'column_id'=>['required','integer','exists:paed_diary_columns,id'],
            'schueler_id'=>['required','integer','exists:schueler,id'],
            'date'=>['required','date'],
            'value'=>['nullable','string','max:255']
        ]);
        $column = PaedDiaryColumn::findOrFail($data['column_id']);
        $user = Auth::user();
        $klasse = $user->paed_klassen()->where('klassen.id',$column->klasse_id)->firstOrFail();
        $schueler = Schueler::where('id',$data['schueler_id'])->where('klasse_id',$klasse->id)->firstOrFail();
        $dateObj = Carbon::parse($data['date']);
        PaedDiaryColumnValue::updateOrCreate([
            'paed_diary_column_id'=>$column->id,
            'schueler_id'=>$schueler->id,
            'datum'=>$dateObj->toDateString()
        ], [ 'value'=>$data['value'] ]);
        $this->forgetWeekCache($klasse->id, $dateObj);
        return response()->json(['success'=>true]);
    }

    /**
     * Erzeugt eine neue Aufgabe (Task) für einen Schüler.
     *
     * @param Request $request {klasse_id, schueler_id, title, description?, due_date?, highlighted?}
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeTask(Request $request)
    {
        $data = $request->validate([
            'klasse_id'=>['nullable','integer','exists:klassen,id'],
            'group_id'=>['nullable','integer','exists:paed_diary_class_groups,id'],
            'schueler_id'=>['required','integer','exists:schueler,id'],
            'title'=>['required','string','max:100'],
            'description'=>['nullable','string'],
            'due_date'=>['nullable','date'],
            'highlighted'=>['nullable','boolean']
        ]);
        if(!$request->filled('klasse_id') && !$request->filled('group_id'))
            return response()->json(['message'=>'klasse_id oder group_id erforderlich'],422);
        $user = Auth::user();
        $highlighted = $data['highlighted'] ?? true;
        $ids=[];
        if($request->filled('group_id')){
            $group = PaedDiaryClassGroup::with('klassen:id')->where('id',$request->group_id)->where('user_id',$user->id)->firstOrFail();
            $userKlassenIds = $user->paed_klassen()->pluck('klassen.id');
            foreach($group->klassen->whereIn('id',$userKlassenIds) as $klasse){
                $schueler = Schueler::where('id',$data['schueler_id'])->where('klasse_id',$klasse->id)->first();
                if(!$schueler) continue;
                $task = PaedDiaryTask::create([
                    'klasse_id'=>$klasse->id,
                    'schueler_id'=>$schueler->id,
                    'title'=>$data['title'],
                    'description'=>$data['description']??null,
                    'due_date'=>$data['due_date']??null,
                    'status'=>'open',
                    'highlighted'=>$highlighted,
                    'created_by'=>$user->id
                ]);
                $this->forgetWeekCache($klasse->id,Carbon::now());
                $ids[]=[
                    'id'=>$task->id,
                    'schueler_id'=>$task->schueler_id,
                    'title'=>$task->title,
                    'due_date'=>$task->due_date?->toDateString(),
                    'highlighted'=>$task->highlighted,
                    'klasse_id'=>$klasse->id
                ];
            }
            return response()->json(['success'=>true,'tasks'=>$ids]);
        }
        $klasse = $user->paed_klassen()->where('klassen.id',$data['klasse_id'])->firstOrFail();
        $schueler = Schueler::where('id',$data['schueler_id'])->where('klasse_id',$klasse->id)->firstOrFail();
        $task = PaedDiaryTask::create([
            'klasse_id'=>$klasse->id,
            'schueler_id'=>$schueler->id,
            'title'=>$data['title'],
            'description'=>$data['description']??null,
            'due_date'=>$data['due_date']??null,
            'status'=>'open',
            'highlighted'=>$highlighted,
            'created_by'=>$user->id
        ]);
        $this->forgetWeekCache($klasse->id,Carbon::now());
        return response()->json(['success'=>true,'task'=>[
            'id'=>$task->id,
            'schueler_id'=>$task->schueler_id,
            'title'=>$task->title,
            'due_date'=>$task->due_date?->toDateString(),
            'highlighted'=>$task->highlighted,
            'klasse_id'=>$klasse->id
        ]]);
    }

    /**
     * Schließt (finalisiert) eine bestehende Aufgabe.
     *
     * @param PaedDiaryTask $task
     * @return \Illuminate\Http\JsonResponse
     */
    public function closeTask(PaedDiaryTask $task){
        $user = Auth::user();
        $user->paed_klassen()->where('klassen.id',$task->klasse_id)->firstOrFail();
        $task->update(['status'=>'closed','highlighted'=>false,'closed_at'=>now()]);
        $this->forgetWeekCache($task->klasse_id,Carbon::now());
        return response()->json(['success'=>true]);
    }

    /**
     * Löscht eine Zusatz-Spalte (Column) und invalidiert den Cache.
     *
     * @param PaedDiaryColumn $column
     * @param Request $request {klasse_id}
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroyColumn(PaedDiaryColumn $column, Request $request)
    {
        $data = $request->validate([
            'klasse_id'=>['required','integer','exists:klassen,id']
        ]);
        $user = Auth::user();
        if ($column->klasse_id != $data['klasse_id']){
            abort(403);
        }
        $klasse = $user->paed_klassen()->where('klassen.id',$column->klasse_id)->firstOrFail();
        $weekStart = null;
        try {
            if (Schema::hasColumn('paed_diary_columns','deactivated_from')) {
                $weekStart = $request->filled('week_start')
                    ? Carbon::parse($request->week_start)->startOfWeek()
                    : Carbon::now()->startOfWeek();

                // Soft deactivate: Set deactivated_from if not already set or earlier than weekStart
                if (is_null($column->deactivated_from) || $column->deactivated_from->gt($weekStart)) {
                    $column->deactivated_from = $weekStart->toDateString();
                    $column->save();
                }

                // Clear cache for the class and week
                $this->forgetWeekCache($klasse->id, $weekStart);

                return response()->json(['success' => true]);
            }
        } catch (Exception $e) {
            // Log the error for debugging
            Log::error('Error in destroyColumn: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'An error occurred while deactivating the column.'], 500);
        }
    }

    /**
     * Liefert alle (auch deaktivierte) Spalten einer Klasse zur Verwaltung.
     *
     * @param Request $request {klasse_id}
     * @return \Illuminate\Http\JsonResponse
     */
    public function columnsAll(Request $request){
        $data = $request->validate([
            'klasse_id'=>['required','integer','exists:klassen,id']
        ]);
        $user = Auth::user();
        $klasse = $user->paed_klassen()->where('klassen.id',$data['klasse_id'])->firstOrFail();
        $cols = PaedDiaryColumn::where('klasse_id',$klasse->id)->orderBy('sort_order')->get()->map(fn($c)=>[
            'id'=>$c->id,
            'name'=>$c->name,
            'type'=>$c->type,
            'sort_order'=>$c->sort_order,
            'deactivated_from'=>$c->deactivated_from?->toDateString()
        ]);
        return response()->json(['columns'=>$cols]);
    }

    /**
     * Reaktiviert eine zuvor deaktivierte Spalte.
     *
     * @param PaedDiaryColumn $column
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function restoreColumn(PaedDiaryColumn $column, Request $request){
        $user = Auth::user();
        $klasse = $user->paed_klassen()->where('klassen.id',$column->klasse_id)->firstOrFail();
        if ($column->deactivated_from){
            $column->deactivated_from = null;
            $column->save();
            $this->forgetWeekCache($klasse->id, Carbon::now());
        }
        return response()->json(['success'=>true]);
    }

    /**
     * Zeigt die Einzelansicht eines Schülers im Pädagogischen Tagebuch.
     *
     * @param Schueler $schueler
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function schuelerView(Schueler $schueler, Request $request)
    {
        $user = Auth::user();
        $klasse = $user->paed_klassen()->where('klassen.id', $schueler->klasse_id)->firstOrFail();

        return view('paedDiary.schueler', [
            'schueler' => $schueler,
            'klasse' => $klasse,
        ]);
    }

    /**
     * Liefert Daten (Einträge, Spalten, Werte, Aufgaben) für einen Schüler in einem Datumsbereich.
     *
     * @param Schueler $schueler
     * @param Request $request {date_from, date_to}
     * @return \Illuminate\Http\JsonResponse
     */
    public function schuelerData(Schueler $schueler, Request $request)
    {
        $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from']
        ]);

        try {

            $user = Auth::user();
            $klasse = $user->paed_klassen()->where('klassen.id', $schueler->klasse_id)->firstOrFail();

            $dateFrom = Carbon::parse($request->date_from);
            $dateTo = Carbon::parse($request->date_to);

            // Einträge für den Schüler laden
            $entries = PaedDiaryEntry::with(['user:id,name'])
                ->where('klasse_id', $klasse->id)
                ->whereBetween('datum', [$dateFrom->toDateString(), $dateTo->toDateString()])
                ->whereHas('schueler', fn($q) => $q->where('schueler.id', $schueler->id))
                ->orderBy('datum')
                ->get()
                ->map(fn($e) => [
                    'id' => $e->id,
                    'date' => $e->datum->toDateString(),
                    'content' => $e->content,
                    'user' => $e->user?->name,
                    'formatted_date' => $e->datum->format('d.m.Y')
                ]);

            // Aktuelle Stage und Historie (mit Bild/Sort-Order und menschlichem Namen des Änderers)
            $schueler->load('grading_stage','grading_history.stage','grading_history.previous_stage','grading_history.changed_by_user');
            $currentStage = $schueler->grading_stage ? [
                'id'=>$schueler->grading_stage->id,
                'name'=>$schueler->grading_stage->name,
                'symbol'=>$schueler->grading_stage->symbol,
                'sort_order'=>$schueler->grading_stage->sort_order,
                'image_url'=>$schueler->grading_stage->image_url ?? null
            ] : null;
            $history = $schueler->grading_history->map(function($h){
                $at = null;
                try {
                    if ($h->created_at) {
                        $at = Carbon::parse($h->created_at)->toDateTimeString();
                    }
                } catch (\Throwable $_) {
                    $at = (string)$h->created_at;
                }
                return [
                    'at'=>$at,
                    'stage_id'=>$h->grading_stage_id,
                    'stage_name'=>$h->stage?->name,
                    'previous_stage_id'=>$h->previous_grading_stage_id,
                    'previous_stage_name'=>$h->previous_stage?->name,
                    'changed_by'=>$h->changed_by,
                    'changed_by_name'=>$h->changed_by_user?->name ?? null
                ];
            });

            // Spalten für die Klasse laden
            $columns = PaedDiaryColumn::where('klasse_id', $klasse->id)
                ->orderBy('sort_order')
                ->get()
                ->map(fn($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'type' => $c->type
                ]);

            // Spaltenwerte für den Schüler laden und nach YYYY-MM-DD gruppieren
            $columnValues = PaedDiaryColumnValue::whereIn('paed_diary_column_id', $columns->pluck('id'))
                ->where('schueler_id', $schueler->id)
                ->whereBetween('datum', [$dateFrom->toDateString(), $dateTo->toDateString()])
                ->get()
                ->groupBy(function($v){
                    try {
                        return Carbon::parse($v->datum)->toDateString();
                    } catch (\Throwable $_) {
                        return (string)$v->datum;
                    }
                })
                ->map(function($dayValues){
                    // key by column id so frontend can access by column id
                    return $dayValues->keyBy('paed_diary_column_id');
                });

            // Aufgaben für den Schüler laden
            $tasks = PaedDiaryTask::where('klasse_id', $klasse->id)
                ->where('schueler_id', $schueler->id)
                ->whereBetween('created_at', [$dateFrom, $dateTo->addDay()])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(fn($t) => [
                    'id' => $t->id,
                    'title' => $t->title,
                    'description' => $t->description,
                    'due_date' => $t->due_date?->format('d.m.Y'),
                    'status' => $t->status,
                    'highlighted' => $t->highlighted,
                    'created_at' => $t->created_at->format('d.m.Y H:i')
                ]);

            return response()->json([
                'entries' => $entries,
                'current_stage' => $currentStage,
                'stage_history' => $history,
                'columns' => $columns,
                'column_values' => $columnValues,
                'tasks' => $tasks,
                'period' => [
                    'from' => $dateFrom->format('d.m.Y'),
                    'to' => $dateTo->format('d.m.Y')
                ]
            ]);

        } catch (\Throwable $e) {
            // Log the error via the normal logger (no custom debug file)
            Log::error('schuelerData exception: '.$e->getMessage(), ['exception' => $e]);
            return response()->json(['message'=>'Interner Serverfehler'],500);
        }
    }

    /**
     * Exportiert die Schüler-Daten (Einträge, Spalten, Aufgaben) als Excel-Datei für einen Zeitraum.
     *
     * @param Schueler $schueler
     * @param Request $request {date_from, date_to}
     * @return StreamedResponse
     */
    public function exportSchuelerWord(Schueler $schueler, Request $request)
    {
        $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from']
        ]);

        $user = Auth::user();
        $klasse = $user->paed_klassen()->where('klassen.id', $schueler->klasse_id)->firstOrFail();

        $dateFrom = Carbon::parse($request->date_from);
        $dateTo = Carbon::parse($request->date_to);

        // Einträge für den Schüler laden (transformiert wie in schuelerData)
        $entries = PaedDiaryEntry::with(['user:id,name'])
            ->where('klasse_id', $klasse->id)
            ->whereBetween('datum', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->whereHas('schueler', fn($q) => $q->where('schueler.id', $schueler->id))
            ->orderBy('datum')
            ->get()
            ->map(fn($e) => [
                'id' => $e->id,
                'date' => $e->datum->toDateString(),
                'content' => $e->content,
                'user' => $e->user?->name,
                'formatted_date' => $e->datum->format('d.m.Y')
            ]);

        // Spalten für die Klasse laden (transformiert)
        $columns = PaedDiaryColumn::where('klasse_id', $klasse->id)
            ->orderBy('sort_order')
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'type' => $c->type
            ]);

        // Spaltenwerte für den Schüler laden (transformiert) und nach YYYY-MM-DD gruppiert
        $columnValues = PaedDiaryColumnValue::whereIn('paed_diary_column_id', $columns->pluck('id'))
            ->where('schueler_id', $schueler->id)
            ->whereBetween('datum', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->get()
            ->groupBy(function($v){
                try {
                    return Carbon::parse($v->datum)->toDateString();
                } catch (\Throwable $_) {
                    return (string)$v->datum;
                }
            })
            ->map(function($dayValues){
                return $dayValues->keyBy('paed_diary_column_id');
            });

        // Aufgaben für den Schüler laden (transformiert)
        $tasks = PaedDiaryTask::where('klasse_id', $klasse->id)
            ->where('schueler_id', $schueler->id)
            ->whereBetween('created_at', [$dateFrom, $dateTo->copy()->addDay()])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'description' => $t->description,
                'due_date' => $t->due_date?->format('d.m.Y'),
                'status' => $t->status,
                'highlighted' => $t->highlighted,
                'created_at' => $t->created_at->format('d.m.Y H:i')
            ]);

        return Excel::download(
            new PaedDiarySchuelerExport($schueler, $entries, $columns, $columnValues, $tasks, $dateFrom, $dateTo),
            'paed_tagebuch_' . $schueler->vorname . '_' . $schueler->nachname . '_' . $dateFrom->format('Ymd') . '_' . $dateTo->format('Ymd') . '.xlsx'
        );
    }

    /**
     * Ändert die Stage eines Schülers, legt einen History-Eintrag an und verknüpft optional einen Tagebucheintrag.
     * Erwartet: schueler_id, grading_stage_id, optional paed_diary_entry_id
     */
    public function changeSchuelerStage(Request $request)
    {
        $data = $request->validate([
            'schueler_id'=>['required','integer','exists:schueler,id'],
            'grading_stage_id'=>['nullable','integer','exists:grading_stages,id'],
            'paed_diary_entry_id'=>['nullable','integer','exists:paed_diary_entries,id']
        ]);
        $user = Auth::user();
        $schueler = Schueler::findOrFail($data['schueler_id']);
        $klasse = $user->paed_klassen()->where('klassen.id',$schueler->klasse_id)->firstOrFail();

        $previous = $schueler->grading_stage_id;
        $newStage = null;
        if (!empty($data['grading_stage_id'])){
            $newStage = GradingStage::findOrFail($data['grading_stage_id']);
            // Validieren: Stage gehört zum System der Klasse (falls gesetzt)
            if ($klasse->grading_system_id && $newStage->grading_system_id != $klasse->grading_system_id){
                return response()->json(['message'=>'Stage gehört nicht zum System der Klasse'],422);
            }
        }

        // Update Schüler and create history + optional diary entry in a transaction
        $paedEntryId = $data['paed_diary_entry_id'] ?? null;
        DB::beginTransaction();
        try {
            // Update student
            $schueler->grading_stage_id = $data['grading_stage_id'] ?? null;
            $schueler->save();

            // If no paed_diary_entry_id provided, create an automatic diary entry describing the change
            if (empty($paedEntryId)) {
                $prevStageName = null;
                if (!empty($previous)) {
                    $prev = GradingStage::find($previous);
                    $prevStageName = $prev?->name;
                }
                $newStageName = $newStage?->name ?? null;
                $userId = $user->id ?? null;
                $studentName = $schueler->vorname . ' ' . $schueler->nachname;
                $parts = [];
                if ($prevStageName) $parts[] = 'von "' . $prevStageName . '"';
                if ($newStageName) $parts[] = 'auf "' . $newStageName . '"';
                $changeText = 'Stufe geändert ' . ($parts ? implode(' ', $parts) : '') . ' für ' . $studentName . '.';

                $entry = PaedDiaryEntry::create([
                    'klasse_id' => $klasse->id,
                    'user_id' => $userId,
                    'datum' => now(),
                    'content' => $changeText
                ]);
                // attach the student
                $entry->schueler()->sync([$schueler->id]);
                $paedEntryId = $entry->id;
            }

            // History anlegen und mit dem Tagebucheintrag verknüpfen
            SchuelerGradingHistory::create([
                'schueler_id'=>$schueler->id,
                'grading_system_id'=>$klasse->grading_system_id,
                'grading_stage_id'=>$data['grading_stage_id'] ?? null,
                'previous_grading_stage_id'=>$previous,
                'changed_by'=>$user->id,
                'paed_diary_entry_id'=>$paedEntryId,
                'created_at'=>now()
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('changeSchuelerStage failed: ' . $e->getMessage());
            return response()->json(['message'=>'Fehler beim Anlegen des Tagebucheintrags'],500);
        }

        // Cache invalideren
        $this->forgetWeekCache($klasse->id, Carbon::now());

        return response()->json(['success'=>true,'new_stage'=> $newStage ? ['id'=>$newStage->id,'name'=>$newStage->name,'symbol'=>$newStage->symbol] : null]);
    }

    /**
     * Liefert die verfügbaren Stufen für eine Klasse (für Frontend-Auswahl)
     *
     * @param Klasse $klasse
     * @return \Illuminate\Http\JsonResponse
     */
    public function getClassStages(Klasse $klasse)
    {
        $user = Auth::user();
        // Prüfen, ob Nutzer Zugriff auf die Klasse hat
        $user->paed_klassen()->where('klassen.id', $klasse->id)->firstOrFail();

        if (!$klasse->grading_system_id) {
            return response()->json(['stages' => []]);
        }
        $stages = GradingStage::where('grading_system_id', $klasse->grading_system_id)->orderBy('sort_order')->get();
        $data = $stages->map(fn($s)=>['id'=>$s->id,'name'=>$s->name,'symbol'=>$s->symbol,'sort_order'=>$s->sort_order]);
        return response()->json(['stages'=>$data]);
    }

    /**
     * Setzt einen Eintrag auf abgeschlossen und kopiert den Inhalt auf alle Tage zwischen Erstellung und Abschluss.
     * @param PaedDiaryEntry $entry
     * @param Request $request {klasse_id, completed_at}
     * @return \Illuminate\Http\JsonResponse
     */
    public function completeEntry(PaedDiaryEntry $entry, Request $request)
    {
        $request->validate([
            'klasse_id'=>['nullable','integer','exists:klassen,id'],
        ]);
        $user = Auth::user();
        // Zugriff auf Klasse des Eintrags prüfen (unabhängig von gesendeter klasse_id)
        $user->paed_klassen()->where('klassen.id',$entry->klasse_id)->firstOrFail();
        if ($entry->completed_at) {
            return response()->json(['success'=>true]);
        }
        DB::beginTransaction();
        try {
            $entry->completed_at = Carbon::now();
            $entry->save();
            $entry->load('schueler');
            $this->finalizeEntry($entry);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('completeEntry failed: '.$e->getMessage());
            return response()->json(['message'=>'Fehler beim Abschließen des Eintrags'],500);
        }
        return response()->json(['success'=>true]);
    }

    /**
     * Interne Hilfsfunktion: Klont einen frisch abgeschlossenen Eintrag (bereits completed_at gesetzt)
     * auf alle fehlenden Tage zwischen Eintragsdatum und heutigem Datum (inkl.).
     * Vermeidet Duplikate für dieselben Schüler am jeweiligen Tag.
     *
     * @param PaedDiaryEntry $entry
     * @return void
     */
    private function finalizeEntry(PaedDiaryEntry $entry): void
    {
        $klasseId = $entry->klasse_id;
        $start = \Carbon\Carbon::parse($entry->datum)->startOfDay();
        $endDate = \Carbon\Carbon::now()->startOfDay();
        if ($endDate->lt($start)) {
            $endDate = $start->copy();
        }
        $schuelerIds = $entry->schueler->pluck('id')->all();
        for ($d = $start->copy()->addDay(); $d->lte($endDate); $d->addDay()) {
            $exists = \App\Models\PaedDiaryEntry::where('klasse_id',$klasseId)
                ->whereDate('datum',$d->toDateString())
                ->whereHas('schueler',function($q) use ($schuelerIds){ $q->whereIn('schueler.id',$schuelerIds); })
                ->exists();
            if (!$exists) {
                $newEntry = \App\Models\PaedDiaryEntry::create([
                    'klasse_id'=>$klasseId,
                    'user_id'=>$entry->user_id,
                    'datum'=>$d->toDateString(),
                    'content'=>$entry->content,
                    'completed_at'=>$entry->completed_at
                ]);
                $newEntry->schueler()->sync($schuelerIds);
            }
        }
        $this->forgetWeekCache($klasseId, $start);
        $this->forgetWeekCache($klasseId, $endDate);
    }

    // ---- Klassenkopplungs-Gruppen CRUD ----
    public function classGroups(Request $request)
    {
        $groups = Auth::user()->paed_diary_class_groups()->with(['klassen:id,name,kuerzel'])->get()->map(fn($g)=>[
            'id'=>$g->id,
            'name'=>$g->name,
            'klassen'=>$g->klassen->map(fn($k)=>['id'=>$k->id,'name'=>$k->name,'kuerzel'=>$k->kuerzel])
        ]);
        return response()->json(['groups'=>$groups]);
    }
    public function storeClassGroup(Request $request)
    {
        $data = $request->validate([
            'name'=>['required','string','max:80'],
            'klasse_ids'=>['required','array','min:2'],
            'klasse_ids.*'=>['integer','exists:klassen,id']
        ]);
        $user = Auth::user();
        $allowed = $user->paed_klassen()->whereIn('klassen.id',$data['klasse_ids'])->pluck('klassen.id')->all();
        if (count($allowed) !== count($data['klasse_ids'])) {
            return response()->json(['message'=>'Ungültige Klassen ausgewählt'],422);
        }
        $group = PaedDiaryClassGroup::create(['user_id'=>$user->id,'name'=>$data['name']]);
        $group->klassen()->sync($allowed);
        return response()->json(['success'=>true,'group'=>['id'=>$group->id,'name'=>$group->name]]);
    }
    public function updateClassGroup(PaedDiaryClassGroup $group, Request $request)
    {
        if ($group->user_id !== Auth::id()) abort(403);
        $data = $request->validate([
            'name'=>['sometimes','string','max:80'],
            'klasse_ids'=>['sometimes','array','min:2'],
            'klasse_ids.*'=>['integer','exists:klassen,id']
        ]);
        $user = Auth::user();
        if (array_key_exists('klasse_ids',$data)) {
            $allowed = $user->paed_klassen()->whereIn('klassen.id',$data['klasse_ids'])->pluck('klassen.id')->all();
            if (count($allowed) !== count($data['klasse_ids'])) {
                return response()->json(['message'=>'Ungültige Klassen ausgewählt'],422);
            }
            $group->klassen()->sync($allowed);
        }
        if (array_key_exists('name',$data)) { $group->name = $data['name']; }
        $group->save();
        return response()->json(['success'=>true]);
    }
    public function destroyClassGroup(PaedDiaryClassGroup $group)
    {
        if ($group->user_id !== Auth::id()) abort(403);
        $group->delete();
        return response()->json(['success'=>true]);
    }
}
