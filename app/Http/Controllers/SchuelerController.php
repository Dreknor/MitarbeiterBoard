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

        try {
            $collections = Excel::toCollection(null, $request->file('file'));

            if ($collections->isEmpty() || $collections->first()->isEmpty()) {
                Log::warning('Schüler Import: Datei ist leer');
                return back()->with(['type'=>'warning','Meldung'=>'Datei leer.']);
            }

            $collection = $collections->first();
            Log::info('Schüler Import gestartet', ['rows_count' => $collection->count()]);

            // Die erste Zeile enthält die Spaltennamen
            $headerRow = $collection->first();
            if (!$headerRow) {
                Log::error('Schüler Import: Keine Header-Zeile gefunden');
                return back()->with(['type'=>'danger','Meldung'=>'Datei enthält keine Header-Zeile.']);
            }

            // Verwende die Werte der ersten Zeile als Spaltennamen
            $header = $headerRow->values();
            Log::info('CSV Header erkannt', ['header' => $header->toArray()]);

            $required = collect(['import_key','vorname','nachname','klasse']);
            if ($required->diff($header)->isNotEmpty()){
                $missing = $required->diff($header)->implode(', ');
                Log::error('Schüler Import: Fehlende Spalten', ['missing' => $missing, 'found' => $header->toArray()]);
                return back()->with(['type'=>'danger','Meldung'=>'Fehlende Spalten: '.$missing]);
            }

            // Entferne die Header-Zeile und verwende die Spaltennamen als Schlüssel
            $dataRows = $collection->skip(1)->map(function($row) use ($header) {
                return $header->combine($row->values());
            });

            $importedKeys = [];
            $processedRows = 0;
            $skippedRows = 0;
            $errors = [];

            DB::beginTransaction();

            foreach ($dataRows as $rowIndex => $row){
                try {
                    if (blank($row['import_key']) || blank($row['vorname']) || blank($row['nachname'])){
                        $skippedRows++;
                        Log::debug("Zeile $rowIndex übersprungen: Leere Pflichtfelder", ['row' => $row->toArray()]);
                        continue; // Zeile überspringen
                    }

                    $klasseValue = $row['klasse'] ?? null;
                    $klasse = null;
                    if (!blank($klasseValue)){
                        // Prüfe sowohl 'kuerzel' als auch 'name' Spalte
                        $klasse = Klasse::where(function($query) use ($klasseValue) {
                            $query->where('name', $klasseValue);
                            if (\Schema::hasColumn('klassen', 'kuerzel')) {
                                $query->orWhere('kuerzel', $klasseValue);
                            }
                        })->first();

                        if (!$klasse) {
                            Log::warning("Klasse nicht gefunden", ['klasse_value' => $klasseValue, 'row' => $rowIndex]);
                        }
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
                        Log::debug("Schüler aktualisiert", ['import_key' => $row['import_key'], 'name' => $data['vorname'].' '.$data['nachname']]);
                    } else {
                        $schueler = Schueler::create(array_merge($data,[ 'import_key' => $row['import_key'] ]));
                        if (is_null($klasse)){
                            $schueler->delete(); // direkt archivieren wenn keine Klasse
                        }
                        Log::debug("Schüler erstellt", ['import_key' => $row['import_key'], 'name' => $data['vorname'].' '.$data['nachname']]);
                    }
                    $importedKeys[] = $row['import_key'];
                    $processedRows++;

                } catch (\Throwable $rowError) {
                    $errors[] = "Zeile $rowIndex: " . $rowError->getMessage();
                    Log::error("Fehler bei Zeile $rowIndex", ['error' => $rowError->getMessage(), 'row' => $row->toArray()]);
                }
            }

            // Schüler archivieren, die nicht mehr im Import auftauchen
            $archivedCount = 0;
            if (!empty($importedKeys)){
                $archivedCount = Schueler::whereNotIn('import_key', $importedKeys)->whereNull('deleted_at')->update(['deleted_at'=>now()]);
                Log::info("Schüler archiviert", ['count' => $archivedCount]);
            }

            DB::commit();

            $message = "Import abgeschlossen. $processedRows Schüler verarbeitet";
            if ($skippedRows > 0) $message .= ", $skippedRows übersprungen";
            if ($archivedCount > 0) $message .= ", $archivedCount archiviert";
            if (!empty($errors)) $message .= ". Fehler bei " . count($errors) . " Zeilen.";

            Log::info('Schüler Import erfolgreich beendet', [
                'processed' => $processedRows,
                'skipped' => $skippedRows,
                'archived' => $archivedCount,
                'errors' => count($errors)
            ]);

            $type = empty($errors) ? 'success' : 'warning';
            return redirect()->back()->with(['type'=>$type,'Meldung'=>$message]);

        } catch (\Throwable $e){
            DB::rollBack();
            Log::error('Schüler Import Fehler', [
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
