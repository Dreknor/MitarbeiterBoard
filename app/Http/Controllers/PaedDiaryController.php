<?php
namespace App\Http\Controllers;

use App\Models\Klasse;
use App\Models\PaedDiaryColumn;
use App\Models\PaedDiaryColumnValue;
use App\Models\PaedDiaryEntry;
use App\Models\PaedDiaryTask;
use App\Models\Schueler;
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
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaedDiaryController extends Controller
{
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
        return view('paedDiary.index', [
            'klassen'=>$klassen,
            'klasse'=>$klasse,
        ]);
    }

    private function weekCacheKey($klasseId, Carbon $weekStart){
        return 'paed_week_'.$klasseId.'_'.$weekStart->format('Ymd');
    }
    private function forgetWeekCache($klasseId, Carbon $date){
        $start = $date->copy()->startOfWeek();
        Cache::forget($this->weekCacheKey($klasseId, $start));
    }

    public function weekData(Request $request)
    {
        $request->validate([
            'klasse_id' => ['required','integer','exists:klassen,id'],
            'week_start' => ['nullable','date']
        ]);
        $user = Auth::user();
        $klasse = $user->paed_klassen()->where('klassen.id',$request->klasse_id)->firstOrFail();
        $weekStart = $request->filled('week_start') ? Carbon::parse($request->week_start)->startOfWeek() : Carbon::now()->startOfWeek();
        $periodEnd = $weekStart->copy()->addDays(4);
        $period = CarbonPeriod::create($weekStart, $periodEnd);
        $days = collect();
        foreach ($period as $date){ $days->push(['date'=>$date->toDateString(),'label'=>$date->format('D d.m.')]); }
        $schueler = $klasse->schueler()->orderBy('vorname')->orderBy('nachname')->get(['id','vorname','nachname']);

        $columnsQuery = PaedDiaryColumn::where('klasse_id',$klasse->id)->orderBy('sort_order');
        if (Schema::hasColumn('paed_diary_columns','deactivated_from')){
            $columnsQuery->where(function($q) use ($weekStart){
                $q->whereNull('deactivated_from')->orWhere('deactivated_from','>', $weekStart->toDateString());
            });
        }
        try {
            $columns = $columnsQuery->get();

            // Boolean-Spalten bleiben als 'boolean' - keine Umwandlung nötig
            // Das Frontend erwartet 'boolean' als Typ für Button-Rendering
        } catch (\Throwable $e) {
            // Fallback ohne Filter falls Spalte fehlt oder Migration nicht gelaufen
            $columns = PaedDiaryColumn::where('klasse_id',$klasse->id)->orderBy('sort_order')->get();
        }

        $entries = PaedDiaryEntry::with(['schueler:id','user:id,name'])
            ->where('klasse_id',$klasse->id)
            ->whereBetween('datum', [$weekStart->toDateString(), $periodEnd->toDateString()])
            ->get();
        $entryData = $entries->map(fn($e)=>[
            'id'=>$e->id,'date'=>$e->datum->toDateString(),'content'=>$e->content,
            'schueler_ids'=>$e->schueler->pluck('id'),'user'=>$e->user?->name
        ]);

        $columnValues = PaedDiaryColumnValue::whereIn('paed_diary_column_id', $columns->pluck('id'))
            ->whereBetween('datum', [$weekStart->toDateString(), $periodEnd->toDateString()])
            ->get();
        $valuesGrouped = [];
        foreach ($columnValues as $v){
            $valuesGrouped[$v->paed_diary_column_id][$v->schueler_id][$v->datum->toDateString()] = $v->value;
        }
        $tasks = PaedDiaryTask::where('klasse_id',$klasse->id)->open()->with('schueler:id,vorname,nachname')->get()->map(fn($t)=>[
            'id'=>$t->id,'schueler_id'=>$t->schueler_id,'title'=>$t->title,
            'due_date'=>$t->due_date?->toDateString(),'highlighted'=>$t->highlighted,
        ]);

        return response()->json([
            'days'=>$days,
            'schueler'=>$schueler->map(fn($s)=>['id'=>$s->id,'name'=>$s->vorname.' '.$s->nachname]),
            'entries'=>$entryData,
            'columns'=>$columns->map(fn($c)=>['id'=>$c->id,'name'=>$c->name,'slug'=>$c->slug,'type'=>$c->type,'deactivated_from'=>Schema::hasColumn('paed_diary_columns','deactivated_from') ? $c->deactivated_from?->toDateString():null]),
            'column_values'=>$valuesGrouped,
            'tasks'=>$tasks,
        ]);
    }

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

    public function exportExcel(Request $request){
        $request->validate([
            'klasse_id'=>['required','integer','exists:klassen,id'],
            'week_start'=>['nullable','date']
        ]);
        $user = Auth::user();
        $klasse = $user->paed_klassen()->where('klassen.id',$request->klasse_id)->firstOrFail();
        $weekStart = $request->filled('week_start') ? Carbon::parse($request->week_start)->startOfWeek() : Carbon::now()->startOfWeek();
        $weekEnd = $weekStart->copy()->addDays(4);

        $entries = PaedDiaryEntry::with(['user:id,name','schueler:id,vorname,nachname'])
            ->where('klasse_id',$klasse->id)
            ->whereBetween('datum', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->orderBy('datum')
            ->get();

        $rows = [];
        foreach ($entries as $entry){
            foreach ($entry->schueler as $s){
                $rows[] = [
                    'Datum'=>$entry->datum->format('Y-m-d'),
                    'Schüler'=>$s->vorname.' '.$s->nachname,
                    'Autor'=>$entry->user?->name,
                    'Notiz'=>preg_replace('/\s+/',' ', trim($entry->content))
                ];
            }
        }
        $filename = 'paed_tagebuch_'.$klasse->kuerzel.'_'.$weekStart->format('Ymd').'.xlsx';

        return Excel::download(new PaedDiaryExport($rows), $filename);
    }

    public function storeEntry(Request $request)
    {
        $validated = $request->validate([
            'klasse_id'=>['required','integer','exists:klassen,id'],
            'date'=>['required','date'],
            'content'=>['required','string'],
            'schueler_ids'=>['required','array','min:1'],
            'schueler_ids.*'=>['integer','exists:schueler,id']
        ]);
        $user = Auth::user();
        $klasse = $user->paed_klassen()->where('klassen.id',$validated['klasse_id'])->firstOrFail();
        $dateObj = Carbon::parse($validated['date']);
        $validSchueler = Schueler::whereIn('id',$validated['schueler_ids'])->where('klasse_id',$klasse->id)->pluck('id')->all();
        if (empty($validSchueler)){
            return response()->json(['message'=>'Keine gültigen Schüler'],422);
        }
        $entry = PaedDiaryEntry::create([
            'klasse_id'=>$klasse->id,
            'user_id'=>$user->id,
            'datum'=>$dateObj->toDateString(),
            'content'=>trim($validated['content'])
        ]);
        $entry->schueler()->sync($validSchueler);
        $this->forgetWeekCache($klasse->id, $dateObj);
        return response()->json(['success'=>true,'entry_id'=>$entry->id]);
    }

    // Neue Methode: Update eines bestehenden Eintrags (Notiz bearbeiten)
    public function updateEntry(PaedDiaryEntry $entry, Request $request)
    {
        $validated = $request->validate([
            'klasse_id'=>['required','integer','exists:klassen,id'],
            'date'=>['required','date'],
            'content'=>['required','string'],
            'schueler_ids'=>['required','array','min:1'],
            'schueler_ids.*'=>['integer','exists:schueler,id']
        ]);
        $user = Auth::user();
        // Sicherstellen, dass der Eintrag zur gewünschten Klasse gehört und Nutzer Zugriff hat
        if ($entry->klasse_id != $validated['klasse_id']){
            abort(403);
        }
        $klasse = $user->paed_klassen()->where('klassen.id',$validated['klasse_id'])->firstOrFail();
        $oldDate = $entry->datum->copy();
        $newDate = Carbon::parse($validated['date']);
        $validSchueler = Schueler::whereIn('id',$validated['schueler_ids'])->where('klasse_id',$klasse->id)->pluck('id')->all();
        if (empty($validSchueler)){
            return response()->json(['message'=>'Keine gültigen Schüler'],422);
        }
        $entry->update([
            'datum'=>$newDate->toDateString(),
            'content'=>trim($validated['content'])
        ]);
        $entry->schueler()->sync($validSchueler);
        // Week Cache für alte und neue Woche invalidieren (falls Wechsel)
        $this->forgetWeekCache($klasse->id, $oldDate);
        if (!$oldDate->isSameWeek($newDate)){
            $this->forgetWeekCache($klasse->id, $newDate);
        }
        return response()->json(['success'=>true]);
    }

    // Neue Methode: Löschen eines Eintrags
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

    public function storeColumn(Request $request)
    {
        $data = $request->validate([
            'klasse_id'=>['required','integer','exists:klassen,id'],
            'name'=>['required','string','max:50'],
            'type'=>['nullable','in:text,boolean,number']
        ]);
        $user = Auth::user();
        $klasse = $user->paed_klassen()->where('klassen.id',$data['klasse_id'])->firstOrFail();
        $slug = Str::slug($data['name']);
        $exists = PaedDiaryColumn::where('klasse_id',$klasse->id)->where('slug',$slug)->exists();
        if ($exists){
            return response()->json(['message'=>'Spalte existiert bereits'],422);
        }
        $sort = PaedDiaryColumn::where('klasse_id',$klasse->id)->max('sort_order') + 1;
        $column = PaedDiaryColumn::create([
            'klasse_id'=>$klasse->id,
            'name'=>$data['name'],
            'slug'=>$slug,
            'type'=>$data['type'] ?? 'text',
            'sort_order'=>$sort
        ]);
        $this->forgetWeekCache($klasse->id, Carbon::now());
        return response()->json(['success'=>true,'column'=>['id'=>$column->id,'name'=>$column->name]]);
    }

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

    public function storeTask(Request $request)
    {
        $data = $request->validate([
            'klasse_id'=>['required','integer','exists:klassen,id'],
            'schueler_id'=>['required','integer','exists:schueler,id'],
            'title'=>['required','string','max:100'],
            'description'=>['nullable','string'],
            'due_date'=>['nullable','date'],
            'highlighted'=>['nullable','boolean']
        ]);
        $user = Auth::user();
        $klasse = $user->paed_klassen()->where('klassen.id',$data['klasse_id'])->firstOrFail();
        $schueler = Schueler::where('id',$data['schueler_id'])->where('klasse_id',$klasse->id)->firstOrFail();
        $task = PaedDiaryTask::create([
            'klasse_id'=>$klasse->id,
            'schueler_id'=>$schueler->id,
            'title'=>$data['title'],
            'description'=>$data['description'] ?? null,
            'due_date'=>$data['due_date'] ?? null,
            'status'=>'open',
            'highlighted'=>$data['highlighted'] ?? true,
            'created_by'=>$user->id
        ]);
        $this->forgetWeekCache($klasse->id, Carbon::now());
        return response()->json(['success'=>true,'task'=>[
            'id'=>$task->id,
            'schueler_id'=>$task->schueler_id,
            'title'=>$task->title,
            'due_date'=>$task->due_date?->toDateString(),
            'highlighted'=>$task->highlighted
        ]]);
    }

    public function closeTask(PaedDiaryTask $task)
    {
        $user = Auth::user();
        $klasse = $user->paed_klassen()->where('klassen.id',$task->klasse_id)->firstOrFail();
        $task->update(['status'=>'closed','highlighted'=>false,'closed_at'=>now()]);
        $this->forgetWeekCache($klasse->id, Carbon::now());
        return response()->json(['success'=>true]);
    }
    public function destroyColumn(PaedDiaryColumn $column, Request $request)
    {
        try {
            $user = Auth::user();
            $klasse = $user->paed_klassen()->where('klassen.id', $column->klasse_id)->firstOrFail();

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
        } catch (Exception $e) {
            // Log the error for debugging
            Log::error('Error in destroyColumn: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'An error occurred while deactivating the column.'], 500);
        }
    }

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

    public function schuelerView(Schueler $schueler, Request $request)
    {
        $user = Auth::user();
        $klasse = $user->paed_klassen()->where('klassen.id', $schueler->klasse_id)->firstOrFail();

        return view('paedDiary.schueler', [
            'schueler' => $schueler,
            'klasse' => $klasse,
        ]);
    }

    public function schuelerData(Schueler $schueler, Request $request)
    {
        $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from']
        ]);

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

        // Spalten für die Klasse laden
        $columns = PaedDiaryColumn::where('klasse_id', $klasse->id)
            ->orderBy('sort_order')
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'type' => $c->type
            ]);

        // Spaltenwerte für den Schüler laden
        $columnValues = PaedDiaryColumnValue::whereIn('paed_diary_column_id', $columns->pluck('id'))
            ->where('schueler_id', $schueler->id)
            ->whereBetween('datum', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->get()
            ->groupBy('datum')
            ->map(fn($dayValues) => $dayValues->keyBy('paed_diary_column_id'));

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
            'columns' => $columns,
            'column_values' => $columnValues,
            'tasks' => $tasks,
            'period' => [
                'from' => $dateFrom->format('d.m.Y'),
                'to' => $dateTo->format('d.m.Y')
            ]
        ]);
    }

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

        // Spaltenwerte für den Schüler laden
        $columnValues = PaedDiaryColumnValue::whereIn('paed_diary_column_id', $columns->pluck('id'))
            ->where('schueler_id', $schueler->id)
            ->whereBetween('datum', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->get()
            ->groupBy('datum')
            ->map(fn($dayValues) => $dayValues->keyBy('paed_diary_column_id'));

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
}
