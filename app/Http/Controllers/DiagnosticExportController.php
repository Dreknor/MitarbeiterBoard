<?php

namespace App\Http\Controllers;

use App\Models\DiagnosticArea;
use App\Models\DiagnosticSession;
use App\Models\Schueler;
use Illuminate\Http\Request;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;

class DiagnosticExportController extends Controller
{
    /**
     * Exportiert eine einzelne Session als PDF
     */
    public function exportSessionPdf(DiagnosticSession $session)
    {
        // Policy-Check
        $this->authorize('view', $session);

        $session->load([
            'schueler',
            'area.stages.goals',
            'assessments.goal.stage',
            'stageNotes.stage',
            'user'
        ]);

        $pdf = PDF::loadView('diagnostics.pdf.session', [
            'session' => $session,
            'schueler' => $session->schueler,
            'area' => $session->area,
            'singleSession' => true
        ]);

        $filename = sprintf(
            'Diagnosebogen_%s_%s_%s.pdf',
            $session->schueler->name,
            $session->area->name,
            $session->session_date->format('Y-m-d')
        );

        return $pdf->download($filename);
    }

    /**
     * Exportiert alle Sessions eines Schülers für einen Bereich als PDF
     * Zeigt Sessions aufsteigend nach Datum mit Leer-Spalten
     */
    public function exportStudentAreaPdf(Schueler $schueler, DiagnosticArea $area)
    {
        // Policy-Check über erste Session oder direkt prüfen
        $this->authorize('viewArea', $area);

        // Hole alle Sessions für diesen Schüler und Bereich (aufsteigend nach Datum)
        $sessions = DiagnosticSession::where('schueler_id', $schueler->id)
            ->where('diagnostic_area_id', $area->id)
            ->with([
                'assessments.goal.stage',
                'stageNotes.stage',
                'user'
            ])
            ->orderBy('session_date', 'asc')
            ->get();

        if ($sessions->isEmpty()) {
            abort(404, 'Keine Sessions gefunden.');
        }

        // Lade den Bereich mit allen Stufen und Zielen
        $area->load('stages.goals');

        // Anzahl der Leer-Spalten für zukünftige Erfassungen (z.B. 3)
        $emptyColumns = 3;

        $pdf = PDF::loadView('diagnostics.pdf.area-history', [
            'schueler' => $schueler,
            'area' => $area,
            'sessions' => $sessions,
            'emptyColumns' => $emptyColumns
        ]);

        // Querformat für bessere Darstellung mehrerer Spalten
        $pdf->setPaper('A4', 'landscape');

        $filename = sprintf(
            'Diagnosebogen_Verlauf_%s_%s.pdf',
            $schueler->name,
            $area->name
        );

        return $pdf->download($filename);
    }

    /**
     * Exportiert ein leeres Formular zum manuellen Ausfüllen
     */
    public function exportBlankFormPdf(DiagnosticArea $area)
    {
        $this->authorize('viewArea', $area);

        $area->load('stages.goals');

        // Anzahl der Leer-Spalten (z.B. 5 für 5 Erfassungen)
        $emptyColumns = 5;

        $pdf = PDF::loadView('diagnostics.pdf.blank-form', [
            'area' => $area,
            'emptyColumns' => $emptyColumns
        ]);

        // Querformat
        $pdf->setPaper('A4', 'landscape');

        $filename = sprintf('Diagnosebogen_Leer_%s.pdf', $area->name);

        return $pdf->download($filename);
    }
}

