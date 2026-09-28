<?php

namespace App\Services\Api;

use App\Models\DiagnosticDevelopmentGoal;
use App\Models\DiagnosticSession;
use App\Models\PaedDiaryGoal;
use App\Models\Schueler;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dossier eines Schülers (Tagebuch, Graduierung, Diagnose) für Entwicklungs- und Elterngespräche.
 * Gemeinsame Datenbasis für GET /students/{id}/dossier (JSON), /dossier.pdf (API) und den PDF-Export im Web.
 */
class DossierService
{
    public function __construct(
        private StudentDataService $data,
        private PaedAppService $app
    ) {
    }

    /**
     * Zeitraum aus den Request-Parametern (Standard: Beginn des Schuljahres bis heute).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function period(Request $request): array
    {
        $from = $request->filled('from_date')
            ? Carbon::parse($request->input('from_date'))->startOfDay()
            : Carbon::parse(config('config.schuljahresbeginn'))->startOfDay();
        $to = $request->filled('to_date') ? Carbon::parse($request->input('to_date'))->endOfDay() : now()->endOfDay();
        if ($to->lt($from)) {
            $from = $to->copy()->startOfDay();
        }

        return [$from, $to];
    }

    /**
     * Sammelt alle Dossier-Daten. Rechte: vertrauliche Einträge fremder Autoren nur mit Sonderrecht,
     * Diagnose nur mit "view diagnostics".
     */
    public function build(Schueler $schueler, User $user, Request $request): array
    {
        [$from, $to] = $this->period($request);

        $includeConfidentialRequested = $request->has('include_confidential') ? $request->boolean('include_confidential') : true;
        $canViewConfidential = $user->canViewConfidentialDiaryEntries();
        $canViewDiagnostics = $user->can('viewDiagnostics', $schueler);

        $schueler->loadMissing(['klasse:id,name', 'grading_stage']);

        // Tagebuch
        $entries = $this->data->diaryEntriesQuery($schueler, $user, $includeConfidentialRequested)
            ->whereBetween('datum', [$from->toDateString(), $to->format('Y-m-d 23:59:59')])
            ->reorder()
            ->orderBy('datum')
            ->orderBy('id')
            ->get();

        $byCategory = $entries->groupBy(fn ($e) => $e->category_id ?? 0)->map(fn ($group) => [
            'category_id' => $group->first()->category_id,
            'category_name' => $group->first()->category?->name ?? 'Ohne Kategorie',
            'category_color' => $group->first()->category?->color,
            'count' => $group->count(),
        ])->values();

        $diaryGoals = PaedDiaryGoal::with(['user:id,name', 'achievedByUser:id,name'])
            ->where('schueler_id', $schueler->id)
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('created_at', [$from, $to])
                    ->orWhereBetween('achieved_at', [$from, $to])
                    ->orWhereNull('achieved_at');
            })
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($g) => [
                'id' => $g->id,
                'goal_text' => $g->goal_text,
                'created_at' => $g->created_at?->toIso8601String(),
                'created_by_name' => $g->user?->name,
                'achieved_at' => $g->achieved_at?->toIso8601String(),
                'achieved_by_name' => $g->achievedByUser?->name,
            ]);

        // Graduierung
        $grading = [
            'current_stage' => $this->data->currentStage($schueler),
            'stage_history' => $this->data->gradingHistory($schueler, $from, $to),
            'completed_sessions' => $this->data->completedGradingSessions($schueler, $from, $to),
        ];

        // Diagnose
        $diagnostic = null;
        if ($canViewDiagnostics) {
            $sessions = DiagnosticSession::where('schueler_id', $schueler->id)
                ->whereBetween('session_date', [$from->toDateString(), $to->format('Y-m-d 23:59:59')])
                ->with(['area:id,name', 'user:id,name', 'stageNotes.stage:id,name', 'assessments', 'developmentGoals.area:id,name', 'developmentGoals.creator:id,name'])
                ->orderBy('session_date')
                ->get();

            $goals = DiagnosticDevelopmentGoal::with(['area:id,name', 'creator:id,name'])
                ->forSchueler($schueler->id)
                ->notArchived()
                ->where(function ($q) use ($from, $to) {
                    $q->whereIn('status', DiagnosticDevelopmentGoal::ACTIVE_STATUSES)
                        ->orWhereBetween('completed_at', [$from->toDateString(), $to->format('Y-m-d 23:59:59')])
                        ->orWhereBetween('created_at', [$from, $to]);
                })
                ->orderBy('target_date')
                ->get();

            $diagnostic = [
                'sessions' => $sessions,
                'development_goals' => $goals,
                'current_criterion_goals' => $this->data->currentCriterionGoals($schueler),
            ];
        }

        return [
            'schueler' => $schueler,
            'from' => $from,
            'to' => $to,
            'entries' => $entries,
            'by_category' => $byCategory,
            'diary_goals' => $diaryGoals,
            'grading' => $grading,
            'diagnostic' => $diagnostic,
            'meta' => [
                'generated_at' => now(),
                'generated_by' => $user->name,
                // Vertrauliche Einträge fremder Autoren nur mit Sonderrecht
                'includes_confidential' => $includeConfidentialRequested && $canViewConfidential,
                'includes_diagnostics' => $canViewDiagnostics,
            ],
        ];
    }

    /**
     * PDF-Antwort (inline) – genutzt von API (/dossier.pdf) und Web-Export.
     */
    public function pdfResponse(array $dossier): Response
    {
        $pdf = Pdf::loadView('pdf.dossier', array_merge($dossier, [
            'schoolName' => $this->app->schoolName(),
            'logo' => $this->logoDataUri(),
        ]))
            ->setPaper('a4')
            // Nur verwendete Glyphen einbetten (sonst ~1 MB je Schriftschnitt – relevant beim Teilen aus der App)
            ->setOption('enable_font_subsetting', true);

        // Seitenzahlen über die Canvas-API (PHP in Views ist in dompdf deaktiviert)
        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text($canvas->get_width() - 110, $canvas->get_height() - 47, 'Seite {PAGE_NUM} von {PAGE_COUNT}', $font, 7, [0.4, 0.4, 0.4]);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $this->filename($dossier) . '"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /** Dossier_<Nachname>_<Vorname>_<von>_<bis>.pdf (ASCII, ohne Sonderzeichen) */
    public function filename(array $dossier): string
    {
        $part = fn (?string $value) => trim(preg_replace('/[^A-Za-z0-9\-]+/', '-', Str::ascii((string) $value)), '-') ?: 'Unbekannt';

        return sprintf(
            'Dossier_%s_%s_%s_%s.pdf',
            $part($dossier['schueler']->nachname),
            $part($dossier['schueler']->vorname),
            $dossier['from']->toDateString(),
            $dossier['to']->toDateString()
        );
    }

    private function logoDataUri(): ?string
    {
        $path = public_path('img/' . config('app.logo'));
        if (!config('app.logo') || !is_file($path)) {
            return null;
        }

        $mime = mime_content_type($path) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
    }
}
