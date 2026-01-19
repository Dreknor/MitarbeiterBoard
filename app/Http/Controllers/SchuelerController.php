<?php

namespace App\Http\Controllers;

use App\Models\Klasse;
use App\Models\Schueler;
use App\Models\GradingStage;
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
        // Default-Stage der Klasse zuweisen, falls vorhanden
        if ($klasse->grading_system_id) {
            $default = GradingStage::where('grading_system_id', $klasse->grading_system_id)->where('is_default', true)->first();
            if ($default) $validated['grading_stage_id'] = $default->id;
        }
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
        ini_set('memory_limit', '512M');

        $request->validate([
            'file' => ['required','file','mimes:xlsx,csv,txt']
        ]);

        try {
            $importer = new \App\Imports\SchuelerImport();
            \Maatwebsite\Excel\Facades\Excel::import($importer, $request->file('file'));

            // Archivieren, die nicht mehr im Import auftauchen
            $archivedCount = 0;
            if (!empty($importer->importedKeys)) {
                $archivedCount = Schueler::whereNotIn('import_key', $importer->importedKeys)
                    ->whereNull('deleted_at')
                    ->update(['deleted_at' => now()]);
            }

            $stats = $importer->getStats();
            $message = "Import abgeschlossen. {$stats['processed']} Schüler verarbeitet";
            if ($stats['skipped'] > 0) $message .= ", {$stats['skipped']} übersprungen";
            if ($archivedCount > 0) $message .= ", {$archivedCount} archiviert";
            if ($stats['errors'] > 0) $message .= ". Fehler bei {$stats['errors']} Zeilen.";

            \Illuminate\Support\Facades\Log::info('Schüler Import erfolgreich beendet', [
                'processed' => $stats['processed'],
                'skipped' => $stats['skipped'],
                'archived' => $archivedCount,
                'errors' => $stats['errors']
            ]);

            $type = $stats['errors'] === 0 ? 'success' : 'warning';
            return redirect()->back()->with(['type'=>$type,'Meldung'=>$message]);

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Schüler Import Fehler', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return back()->with(['type'=>'danger','Meldung'=>'Import fehlgeschlagen: ' . $e->getMessage()]);
        }
    }


    public function edit(Schueler $schueler)
    {
        $klasse = $schueler->klasse;
        $stages = collect();
        if ($klasse && $klasse->grading_system_id) {
            $stages = GradingStage::where('grading_system_id', $klasse->grading_system_id)->orderBy('sort_order')->get();
        }
        return view('schueler.edit', [
            'schueler' => $schueler,
            'klasse' => $klasse,
            'stages' => $stages
        ]);
    }

    public function update(Request $request, Schueler $schueler)
    {
        $rules = [
            'vorname' => ['required','string','max:255'],
            'nachname' => ['required','string','max:255'],
            'geburtsdatum' => ['nullable','date']
        ];

        // Only validate grading_stage_id if user has permission to manage grading systems
        if ($request->user() && $request->user()->can('manage grading systems')) {
            $rules['grading_stage_id'] = ['nullable','integer','exists:grading_stages,id'];
        }

        $validated = $request->validate($rules);

        // Update core fields
        $schueler->update(
            array_intersect_key($validated, array_flip(['vorname','nachname','geburtsdatum']))
        );

        // If permitted, update grading stage (ensure stage belongs to the klasse's system)
        if (isset($validated['grading_stage_id']) && $request->user()->can('manage grading systems')) {
            $stage = GradingStage::find($validated['grading_stage_id']);
            $klasse = $schueler->klasse;
            if ($stage && $klasse && $klasse->grading_system_id && $stage->grading_system_id == $klasse->grading_system_id) {
                $schueler->grading_stage_id = $stage->id;
                $schueler->save();
            } else {
                // invalid stage for this class - ignore or optionally add an error
                return back()->withErrors(['grading_stage_id' => 'Ausgewählte Stufe passt nicht zur Klasse.'])->withInput();
            }
        }

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
