<?php

namespace App\Http\Controllers;

use App\Exports\GeburtstagVorlageExport;
use App\Services\Personal\GeburtstagImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Geburtstage aus einer Excel-Liste übernehmen (Benutzerverwaltung):
 * Datei hochladen → Vorschau → Übernehmen. Bereits gesetzte Geburtstage
 * werden nie überschrieben.
 */
class GeburtstagImportController extends Controller
{
    public function __construct(protected GeburtstagImportService $import)
    {
        $this->middleware('permission:edit users');
    }

    public function index()
    {
        return view('users.geburtstage.index', [
            'maxZeilen' => GeburtstagImportService::MAX_ZEILEN,
        ]);
    }

    public function vorlage()
    {
        return Excel::download(new GeburtstagVorlageExport(), 'Geburtstage_Vorlage.xlsx');
    }

    public function vorschau(Request $request)
    {
        $request->validate([
            'datei' => 'required|file|max:5120',
        ], [
            'datei.required' => 'Bitte eine Excel-Datei auswählen.',
            'datei.max'      => 'Die Datei darf höchstens 5 MB groß sein.',
        ]);

        $datei = $request->file('datei');
        $endung = strtolower($datei->getClientOriginalExtension());
        if (!in_array($endung, ['xlsx', 'xls', 'ods'], true)) {
            throw ValidationException::withMessages(['datei' => 'Bitte eine Excel-Datei (.xlsx, .xls oder .ods) hochladen.']);
        }

        try {
            $zeilen = $this->import->lesen($datei);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['datei' => $e->getMessage()]);
        }

        $token = $this->import->vorschauSpeichern($request->user(), $datei->getClientOriginalName(), $zeilen);

        return redirect()->route('users.geburtstage.show', $token);
    }

    public function show(Request $request, string $token)
    {
        $vorschau = $this->import->vorschauLaden($request->user(), $token);
        if (!$vorschau) {
            return $this->abgelaufen();
        }

        $auswertung = $this->import->auswerten($vorschau['zeilen']);

        return view('users.geburtstage.show', [
            'token'     => $token,
            'dateiname' => $vorschau['dateiname'],
            'gruppen'   => $this->import->gruppieren($auswertung),
            'status'    => GeburtstagImportService::STATUS,
            'anzahl'    => count($auswertung),
        ]);
    }

    public function store(Request $request, string $token)
    {
        $user = $request->user();
        $vorschau = $this->import->vorschauLaden($user, $token);
        if (!$vorschau) {
            return $this->abgelaufen();
        }

        $ergebnis = $this->import->importieren($vorschau['zeilen']);
        $this->import->vorschauVerwerfen($user, $token);

        $anzahl = count($ergebnis['gesetzt']);
        Log::info('Geburtstags-Import', [
            'user_id'       => $user->id,
            'datei'         => $vorschau['dateiname'],
            'gesetzt'       => $anzahl,
            'uebersprungen' => $ergebnis['uebersprungen'],
        ]);

        return redirect()->route('users.geburtstage.index')
            ->with('geburtstag_import_bericht', [
                'dateiname' => $vorschau['dateiname'],
                'gruppen'   => $this->import->gruppieren($ergebnis['auswertung']),
            ])
            ->with([
                'type'    => $anzahl > 0 ? 'success' : 'info',
                'Meldung' => $anzahl . ' Geburtstag(e) übernommen, ' . $ergebnis['uebersprungen'] . ' Zeile(n) übersprungen.',
            ]);
    }

    private function abgelaufen()
    {
        return redirect()->route('users.geburtstage.index')->with([
            'type'    => 'warning',
            'Meldung' => 'Die Import-Vorschau ist abgelaufen. Bitte die Datei erneut hochladen.',
        ]);
    }
}
