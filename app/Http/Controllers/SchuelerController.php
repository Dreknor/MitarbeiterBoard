<?php

namespace App\Http\Controllers;

use App\Models\Klasse;
use App\Models\Schueler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class SchuelerController extends Controller
{
    public function store(Request $request, Klasse $klasse)
    {
        $validated = $request->validate([
            'vorname' => ['required','string','max:255'],
            'nachname' => ['required','string','max:255'],
            'geburtsdatum' => ['nullable','date']
        ]);

        $validated['klasse_id'] = $klasse->id;
        Schueler::create($validated);

        return redirect()->back()->with([
            'type' => 'success',
            'Meldung' => 'Schüler hinzugefügt.'
        ]);
    }

    public function importForm()
    {
        return view('schueler.import');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required','file','mimes:xlsx,csv,txt']
        ]);

        $path = $request->file('file')->getRealPath();
        $collection = Excel::toCollection(null, $request->file('file'))->first();

        if ($collection->count() === 0) {
            return back()->with(['type'=>'warning','Meldung'=>'Datei leer.']);
        }

        // Erwartete Spalten: import_key, vorname, nachname, geburtsdatum (YYYY-MM-DD), klasse (Name oder Kürzel)
        $header = $collection->first()->keys();
        $required = collect(['import_key','vorname','nachname','klasse']);
        if ($required->diff($header)->isNotEmpty()){
            return back()->with(['type'=>'danger','Meldung'=>'Fehlende Spalten: '.$required->diff($header)->implode(', ')]);
        }

        $importedKeys = [];
        DB::beginTransaction();
        try {
            foreach ($collection as $row){
                if (blank($row['import_key']) || blank($row['vorname']) || blank($row['nachname'])){
                    continue; // Zeile überspringen
                }
                $klasseValue = $row['klasse'] ?? null;
                $klasse = null;
                if (!blank($klasseValue)){
                    $klasse = Klasse::where('kuerzel',$klasseValue)->orWhere('name',$klasseValue)->first();
                }

                $data = [
                    'vorname' => trim($row['vorname']),
                    'nachname' => trim($row['nachname']),
                    'geburtsdatum' => $row['geburtsdatum'] ?? null,
                    'klasse_id' => $klasse?->id
                ];

                $schueler = Schueler::withTrashed()->where('import_key', $row['import_key'])->first();

                if ($schueler){
                    $schueler->fill($data);
                    // Wenn keine Klasse mehr -> archivieren, sonst wiederherstellen
                    if (is_null($klasse)){
                        if (is_null($schueler->deleted_at)){
                            $schueler->delete();
                        }
                    } else {
                        if (!is_null($schueler->deleted_at)){
                            $schueler->restore();
                        }
                    }
                    $schueler->save();
                } else {
                    $schueler = Schueler::create(array_merge($data,[ 'import_key' => $row['import_key'] ]));
                    if (is_null($klasse)){
                        $schueler->delete(); // direkt archivieren wenn keine Klasse
                    }
                }
                $importedKeys[] = $row['import_key'];
            }

            // Schüler archivieren, die nicht mehr im Import auftauchen
            if (!empty($importedKeys)){
                Schueler::whereNotIn('import_key', $importedKeys)->whereNull('deleted_at')->update(['deleted_at'=>now()]);
            }

            DB::commit();
        } catch (\Throwable $e){
            DB::rollBack();
            Log::error('Schueler Import Fehler: '.$e->getMessage(), ['trace'=>$e->getTraceAsString()]);
            return back()->with(['type'=>'danger','Meldung'=>'Import fehlgeschlagen.']);
        }

        return redirect()->back()->with(['type'=>'success','Meldung'=>'Import abgeschlossen.']);
    }

    public function edit(Schueler $schueler)
    {
        return view('schueler.edit', [
            'schueler' => $schueler,
            'klasse' => $schueler->klasse
        ]);
    }

    public function update(Request $request, Schueler $schueler)
    {
        $validated = $request->validate([
            'vorname' => ['required','string','max:255'],
            'nachname' => ['required','string','max:255'],
            'geburtsdatum' => ['nullable','date']
        ]);

        $schueler->update($validated);

        return redirect(url('klassen/'.$schueler->klasse_id.'/edit'))->with([
            'type' => 'success',
            'Meldung' => 'Schüler aktualisiert.'
        ]);
    }

    public function destroy(Schueler $schueler)
    {
        $schueler->delete();

        return redirect()->back()->with([
            'type' => 'warning',
            'Meldung' => 'Schüler gelöscht.'
        ]);
    }
}

