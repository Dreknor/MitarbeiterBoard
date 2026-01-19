<?php
namespace App\Imports;

use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Row;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use App\Models\Klasse;
use App\Models\Schueler;
use App\Models\GradingStage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SchuelerImport implements OnEachRow, WithHeadingRow, WithChunkReading, SkipsEmptyRows
{
    public $importedKeys = [];
    public $processed = 0;
    public $skipped = 0;
    public $errors = [];

    public function onRow(Row $row)
    {
        $row = $row->toArray();

        try {
            if (empty($row['import_key']) || empty($row['vorname']) || empty($row['nachname'])) {
                $this->skipped++;
                return;
            }

            $klasseValue = $row['klasse'] ?? null;
            $klasse = null;
            if (!empty($klasseValue)) {
                $query = Klasse::where('name', $klasseValue);
                if (Schema::hasColumn('klassen', 'kuerzel')) {
                    $query->orWhere('kuerzel', $klasseValue);
                }
                $klasse = $query->first();
                if (!$klasse) {
                    Log::warning('Klasse nicht gefunden', ['klasse_value' => $klasseValue]);
                }
            }

            $data = [
                'vorname' => trim($row['vorname']),
                'nachname' => trim($row['nachname']),
                'geburtsdatum' => $row['geburtsdatum'] ?? null,
                'klasse_id' => $klasse?->id
            ];

            if ($klasse && $klasse->grading_system_id) {
                $default = GradingStage::where('grading_system_id', $klasse->grading_system_id)
                    ->where('is_default', true)
                    ->first();
                if ($default) $data['grading_stage_id'] = $default->id;
            }

            $schueler = Schueler::withTrashed()->where('import_key', $row['import_key'])->first();

            if ($schueler) {
                $schueler->fill($data);
                if (is_null($klasse)) {
                    if (is_null($schueler->deleted_at)) $schueler->delete();
                } else {
                    if (!is_null($schueler->deleted_at)) $schueler->restore();
                }
                if (empty($schueler->grading_stage_id) && !empty($data['grading_stage_id'])) {
                    $schueler->grading_stage_id = $data['grading_stage_id'];
                }
                $schueler->save();
            } else {
                $schueler = Schueler::create(array_merge($data, ['import_key' => $row['import_key']]));
                if (is_null($klasse)) $schueler->delete();
            }

            $this->importedKeys[] = $row['import_key'];
            $this->processed++;

        } catch (\Throwable $e) {
            $this->errors[] = $e->getMessage();
            Log::error('Fehler beim Importieren einer Zeile', ['error' => $e->getMessage(), 'row' => $row]);
        }
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function getStats(): array
    {
        return [
            'processed' => $this->processed,
            'skipped' => $this->skipped,
            'errors' => count($this->errors),
            'importedKeys' => $this->importedKeys
        ];
    }
}
