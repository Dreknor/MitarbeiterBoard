<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateKlasseRequest;
use App\Http\Requests\EditKlasseRequest;
use App\Models\Klasse;
use App\Models\User;
use App\Models\GradingStage;
use App\Models\PaedDiaryEntry;
use App\Models\SchuelerGradingHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class KlasseController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return response()->view('klassen.klassen',[
            'klassen' => Klasse::all()
        ]);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(CreateKlasseRequest $request)
    {
        Klasse::create($request->validated());

        return redirect()->back()->with([
            'type'  => "success",
            'Meldung'=> 'Klasse wurde angelegt.'
        ]);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Klasse  $klasse
     * @return \Illuminate\Http\Response
     */
    public function destroy($klasse)
    {
        $klasse = Klasse::find($klasse);
        $name = $klasse->name;
        $klasse->delete();

        return redirect()->back()->with([
            'type' => 'warning',
            'Meldung' => $name.' wurde gelöscht.'
        ]);
    }

    public function edit($klasse)
    {
        $klasse = Klasse::with(['schueler','paed_users'])->find($klasse);
        $paedUsers = User::permission('view paed diary')->orderBy('name')->get();
        $systems = \App\Models\GradingSystem::orderBy('name')->get();
        return response()->view('klassen.edit',[
            'klasse' => $klasse,
            'paedUsers' => $paedUsers
            , 'systems' => $systems
        ]);
    }

    public function update(Request $request, Klasse $klassen)
    {

        $validatedData = $request->validate([
            'name' => ['required', 'unique:klassen,name,'.$klassen->id, 'max:255'],
            'kuerzel' => ['required', 'unique:klassen,kuerzel,'.$klassen->id, 'max:255'],
            'paed_user_ids' => ['nullable','array'],
            'paed_user_ids.*' => ['integer','exists:users,id']
            , 'grading_system_id' => ['nullable','integer','exists:grading_systems,id']
        ]);

        // Remember previous grading_system to detect changes
        $previousSystem = $klassen->grading_system_id;

        DB::beginTransaction();
        try {
            $klassen->update($validatedData);

            if ($request->has('paed_user_ids')){
                $klassen->paed_users()->sync($request->get('paed_user_ids'));
            } else {
                $klassen->paed_users()->sync([]);
            }

            // If grading system was set or changed to a non-null value, assign default (lowest) stage
            $newSystem = $validatedData['grading_system_id'] ?? null;
            if (!empty($newSystem) && $newSystem != $previousSystem) {
                // find lowest stage by sort_order
                $stage = GradingStage::where('grading_system_id', $newSystem)->orderBy('sort_order')->first();
                if ($stage) {
                    // students without a stage
                    $students = $klassen->schueler()->whereNull('grading_stage_id')->get();
                    if ($students->isNotEmpty()) {
                        $assignedNames = [];
                        foreach ($students as $s) {
                            $assignedNames[] = $s->vorname . ' ' . $s->nachname;
                        }

                        // Create paed diary entry describing assignment
                        $userId = auth()->id() ?? null;
                        $content = 'Automatische Stufen-Zuweisung: Schüler ohne Stufe wurden der Stufe "' . $stage->name . '" zugewiesen: ' . implode(', ', $assignedNames) . '.';
                        $entry = PaedDiaryEntry::create([
                            'klasse_id' => $klassen->id,
                            'user_id' => $userId,
                            'datum' => now(),
                            'content' => $content
                        ]);
                        // attach schueler to the paed diary entry
                        $entry->schueler()->sync($students->pluck('id')->toArray());

                        // Invalidate week cache for the class so the new diary entry becomes visible immediately
                        try {
                            $weekStart = Carbon::parse($entry->datum)->startOfWeek();
                            Cache::forget('paed_week_'.$klassen->id.'_'. $weekStart->format('Ymd'));
                        } catch (\Throwable $e) {
                            // non-fatal
                        }

                        // update students and create history entries
                        foreach ($students as $s) {
                            SchuelerGradingHistory::create([
                                'schueler_id' => $s->id,
                                'grading_system_id' => $newSystem,
                                'grading_stage_id' => $stage->id,
                                'previous_grading_stage_id' => null,
                                'changed_by' => $userId,
                                'paed_diary_entry_id' => $entry->id,
                                'created_at' => now()
                            ]);
                            $s->grading_stage_id = $stage->id;
                            $s->save();
                        }
                    }
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return redirect(url('klassen/'.$klassen->id.'/edit'))->with([ 'type' => 'danger', 'Meldung' => 'Fehler beim Aktualisieren: '.$e->getMessage() ]);
        }

        return redirect(url('klassen/'.$klassen->id.'/edit'))->with([
            'type' => 'success',
            'Meldung' => 'Klasse wurde aktualisiert.'
        ]);
    }
}
