<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DailyNews;
use App\Models\Klasse;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vertretung;
use App\Models\VertretungsplanAbsence;
use App\Services\RoomBookingFromVpService;
use App\Support\Collection;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VertretungsplanImportController extends Controller
{
    public function import(Request $request)
    {
        $key = $request->route('key');
        $setting = Setting::where('setting', 'indiware_import_key')->first();
        if (!$setting || $setting->value != $key) {
            Log::error('Vertretungsplan: Invalid API Key', [
                'key' => $key,
                'setting' => $setting,
            ]);
            return response()->json(['error' => 'Invalid API Key'], 401);
        }

        // Robustere Datenstruktur-Erkennung
        $rawData = json_decode($request->getContent());
        if (!$rawData) {
            Log::error('Vertretungsplan: Error while parsing JSON. No data found.', [
                'request' => $request->all(),
            ]);
            return response()->json(['error' => 'Error while parsing JSON'], 400);
        }

        // Finde den Einstiegspunkt für die Daten
        $data = null;
        if (isset($rawData->request)) {
            $data = $rawData->request;
        } elseif (isset($rawData->data)) {
            $data = $rawData->data;
        } else {
            $data = $rawData;
        }

        // Suche nach Gesamtexport->Vertretungsplan->Vertretungsplan
        if (isset($data->Gesamtexport) && isset($data->Gesamtexport->Vertretungsplan) && isset($data->Gesamtexport->Vertretungsplan->Vertretungsplan)) {
            $vertretungsplan = $data->Gesamtexport->Vertretungsplan->Vertretungsplan;
        } elseif (isset($data->Gesamtexport) && isset($data->Gesamtexport->Vertretungsplan) && is_array($data->Gesamtexport->Vertretungsplan)) {
            $vertretungsplan = $data->Gesamtexport->Vertretungsplan;
        } elseif (isset($data->Vertretungsplan) && isset($data->Vertretungsplan->Vertretungsplan)) {
            $vertretungsplan = $data->Vertretungsplan->Vertretungsplan;
        } elseif (isset($data->Vertretungsplan) && is_array($data->Vertretungsplan)) {
            $vertretungsplan = $data->Vertretungsplan;
        } else {
            Log::error('Vertretungsplan: Error while parsing JSON. No Vertretungsplan found.', [
                'data' => $data,
            ]);
            return response()->json(['error' => 'Error while parsing JSON. No Vertretungsplan found.'], 400);
        }

        // Sammellogik für konsolidiertes Logging
        $summary = [
            'days_processed'              => 0,
            'absences_total'              => 0,
            'absences_created'            => 0,
            'actions_total'               => 0,
            'dailynews_created'           => 0,
            'vertretungen_created'        => 0,
            'vertretungen_updated'        => 0,
            'vertretungen_skipped_hidden' => 0,
            'missing_classes'             => [],
            'missing_teachers'            => [],
        ];
        $hadError = false;

        // Raumintegration: Service für diesen Import-Lauf
        $roomService = new RoomBookingFromVpService();

        foreach ($vertretungsplan as $day) {
            try {
                $summary['days_processed']++;

                $kopfDate = isset($day->Kopf->Datum)
                    ? Carbon::createFromFormat('d.m.Y', $day->Kopf->Datum)
                    : null;

                // A/B-Woche für diesen Tag ermitteln (für Raumintegration)
                $vpWeek = $kopfDate
                    ? \App\Models\VertretungsplanWeek::where('week', $kopfDate->copy()->startOfWeek())->first()
                    : null;
                $week = $vpWeek?->type;

                DB::transaction(function () use ($roomService, $kopfDate, $day, $week, &$summary) {

                    // Idempotenz: VP-Raumbuchungen für diesen Tag zurücksetzen
                    if ($kopfDate) {
                        $roomService->clearVpBookingsForDate($kopfDate);
                    }

                    // Abwesenheiten
                    if (isset($day->Kopf->Kopfinfo->AbwesendeLehrer)) {
                        foreach ($day->Kopf->Kopfinfo->AbwesendeLehrer as $abwesender) {
                            $summary['absences_total']++;
                            $user = User::where('kuerzel', $abwesender->Kurz)->first();
                            if ($user && $kopfDate) {
                                $absence = VertretungsplanAbsence::where('user_id', $user->id)
                                    ->whereDate('start_date', '<=', $kopfDate)
                                    ->whereDate('end_date', '>=', $kopfDate)
                                    ->first();
                                if (!$absence) {
                                    $absence = new VertretungsplanAbsence([
                                        'user_id'    => $user->id,
                                        'start_date' => $kopfDate,
                                        'end_date'   => $kopfDate,
                                    ]);
                                    $absence->save();
                                    $summary['absences_created']++;
                                }
                            } else {
                                $summary['missing_teachers'][] = $abwesender->Kurz;
                            }
                        }
                    }

                    // Vertretungen
                    if (isset($day->Aktionen) && is_array($day->Aktionen)) {

                        $aktionen = collect($day->Aktionen)
                            ->map(fn($a) => (object) $a)
                            ->unique(fn($a) => ($a->Ak_Id ?? '') . '_' . ($a->Ak_StundeVon ?? ''));

                        foreach ($aktionen as $aktion) {
                            $summary['actions_total']++;

                            $aktionDate = $kopfDate;
                            if (isset($aktion->Ak_DatumVon)) {
                                try {
                                    $aktionDate = Carbon::createFromFormat('d.m.Y', $aktion->Ak_DatumVon);
                                } catch (\Exception $e) {
                                    Log::warning('VP: Ungültiges Datum in Ak_DatumVon', [
                                        'value' => $aktion->Ak_DatumVon,
                                        'ak_id' => $aktion->Ak_Id ?? null,
                                    ]);
                                    $aktionDate = $kopfDate; // Fallback auf Kopf-Datum
                                }
                            }

                            $stundeVon = (int) ($aktion->Ak_StundeVon ?? 0);
                            if ($stundeVon < 1 || $stundeVon > 15) {
                                Log::warning('VP: Ungültige Stundennummer', [
                                    'value' => $stundeVon,
                                    'ak_id' => $aktion->Ak_Id ?? null,
                                ]);
                                continue;
                            }

                            if (isset($aktion->InfoK)) {
                                $nachricht = new DailyNews([
                                    'date_start' => $aktionDate,
                                    'date_end'   => $aktionDate,
                                    'news'       => $aktion->InfoK,
                                ]);
                                $nachricht->save();
                                $summary['dailynews_created']++;
                            }

                            $lehrer = null;
                            if (isset($aktion->VLehrer) && is_array($aktion->VLehrer) && count($aktion->VLehrer) > 0) {
                                $lehrer = User::where('kuerzel', $aktion->VLehrer[0])->first();
                                if (!$lehrer) {
                                    $summary['missing_teachers'][] = $aktion->VLehrer[0];
                                }
                            }

                            $klassen = collect();
                            if (isset($aktion->Klassen) && is_array($aktion->Klassen)) {
                                $requested = array_values(array_unique(array_map(
                                    static fn($v) => trim((string) $v),
                                    $aktion->Klassen
                                )));
                                $klassen = Klasse::where(function ($q) use ($requested) {
                                    $q->whereIn('name', $requested)->orWhereIn('kuerzel', $requested);
                                })->get();
                                $gefundenKeys = $klassen->pluck('name')
                                    ->merge($klassen->pluck('kuerzel'))
                                    ->filter()->unique()->values()->toArray();
                                $missing = array_values(array_diff($requested, $gefundenKeys));
                                if (count($missing) > 0) {
                                    $summary['missing_classes'] = array_values(array_unique(
                                        array_merge($summary['missing_classes'], $missing)
                                    ));
                                }
                            }

                            $type = '';
                            switch ($aktion->Ak_Art) {
                                case 'Änd.':
                                case 'Ã„nd.':
                                    if (isset($aktion->Ak_Fach, $aktion->Ak_VFach) && $aktion->Ak_Fach != $aktion->Ak_VFach) {
                                        $type = 'Vertretung (fachfremd)';
                                    } else {
                                        $type = 'Vertretung (fachgerecht)';
                                    }
                                    break;
                                default:
                                    $type = 'Ausfall';
                                    break;
                            }

                            // ── Raumbuchungen verarbeiten ─────────────────────────────────
                            // AUSSERHALB der $klassen-Schleife: Raum-Verarbeitung ist
                            // unabhängig von show_vertretungen und wird immer ausgeführt.
                            if ($aktionDate) {
                                $roomService->processAktion($aktion, $aktionDate, $week);
                            }

                            // ── Vertretungs-Einträge erstellen ────────────────────────────
                            if ($klassen->count() > 0) {
                                foreach ($klassen as $klasse) {
                                    // Nur wenn Klasse öffentlich angezeigt wird
                                    if (!$klasse->show_vertretungen) {
                                        $summary['vertretungen_skipped_hidden']++;
                                        continue;
                                    }

                                    $vertretung = Vertretung::query()
                                        ->where('klassen_id', $klasse->id)
                                        ->where('date', $aktionDate ? $aktionDate->format('Y-m-d') : null)
                                        ->where('stunde', $aktion->Ak_StundeVon)
                                        ->first();

                                    $payload = [
                                        'users_id'     => $lehrer?->id,
                                        'Doppelstunde' => (isset($aktion->Ak_Doppelstunde) || (isset($aktion->Ak_StundenAnz) && $aktion->Ak_StundenAnz >= 2)) ? true : false,
                                        'altFach'      => $aktion->Ak_Fach ?? null,
                                        'neuFach'      => (isset($aktion->Ak_VFach) && $aktion->Ak_VFach !== '') ? $aktion->Ak_VFach : 'Ausfall',
                                        'type'         => $type,
                                        'comment'      => (isset($aktion->Raeume[0], $aktion->VRaeume[0]) && $aktion->Raeume[0] != $aktion->VRaeume[0])
                                            ? 'Raum: ' . $aktion->VRaeume[0]
                                            : null,
                                    ];

                                    if ($vertretung) {
                                        $vertretung->update($payload);
                                        $summary['vertretungen_updated']++;
                                    } else {
                                        $vertretung = new Vertretung(array_merge($payload, [
                                            'klassen_id' => $klasse->id,
                                            'date'       => $aktionDate,
                                            'stunde'     => $aktion->Ak_StundeVon ?? null,
                                            'created_at' => Carbon::now(),
                                            'akt_id'     => $aktion->Ak_Id ?? null,
                                        ]));
                                        $vertretung->save();
                                        $summary['vertretungen_created']++;
                                    }
                                }
                            }
                        }
                    }
                }); // end DB::transaction

            } catch (\Exception $e) {
                $hadError = true;
                Log::error('Vertretungsplan: Error while parsing Day', [
                    'exception' => $e->getMessage(),
                ]);
                continue;
            }
        }

        // Raum-Summary zusammenführen
        $roomSummary = $roomService->getSummary();
        $summary     = array_merge($summary, $roomSummary);

        // Konsolidiertes Logging bei Erfolg
        if (!$hadError) {
            $summary['missing_teachers'] = array_values(array_unique($summary['missing_teachers']));
            $summary['missing_classes']  = array_values(array_unique($summary['missing_classes']));
            Log::info('Vertretungsplan Import Summary', $summary);
        }

        return response()->json([
            'status'  => $hadError ? 'completed_with_errors' : 'ok',
            'summary' => $summary,
        ]);
    }
}
