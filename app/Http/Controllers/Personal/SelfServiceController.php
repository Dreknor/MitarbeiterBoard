<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use App\Http\Resources\Personal\EmployeeSelfServiceResource;
use App\Models\personal\Consent;
use App\Models\personal\ConsentType;
use App\Models\personal\PersonalDocument;
use App\Models\personal\EmployeeQualification;
use App\Services\Personal\Zeit\UrlaubskontoService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SelfServiceController extends Controller
{
    /**
     * GET /mein-profil
     * IDOR-Schutz by Design: Immer auth()->user(), kein ID-Parameter.
     */
    public function index(Request $request, UrlaubskontoService $konto)
    {
        $employe = auth()->user()->load([
            'employe_data',
            'employments' => fn($q) => $q->active()->with('department', 'currentTeacherDetail', 'hour_type'),
        ]);

        $resource = (new EmployeeSelfServiceResource($employe))->toArray(request());

        // Anstellungshistorie: alle Vertragsversionen inkl. beendeter – für Übersicht und Reiter „Verträge“
        $historie = $employe->employments()->with('department', 'currentTeacherDetail.schoolType', 'hour_type', 'replacedEmployment.department')->orderByDesc('start')->get();
        $resource['eintrittsdatum'] = $historie->min('start')?->format('d.m.Y') ?? $resource['eintrittsdatum'];

        $jahr = (int) $request->query('jahr', now()->year);
        $jahr = max(2000, min(2100, $jahr));

        return view('personal.self-service.index', [
            'employe'     => $resource,
            'rawEmploye'  => $employe,
            'anstellungshistorie' => $historie,
            'employments' => $historie,
            'jahr'        => $jahr,
            'konto'       => $employe->can('has holidays') ? $konto->uebersicht($employe, $jahr) : null,
            'abwesenheiten' => $this->abwesenheiten($employe, $jahr),
        ]);
    }

    /**
     * Eigener Urlaub und sonstige Abwesenheiten eines Jahres in einer Liste.
     * Abwesenheiten, die aus einem Urlaubsantrag entstanden sind (holiday_id), erscheinen nur einmal – als Urlaub.
     */
    private function abwesenheiten($employe, int $jahr): Collection
    {
        $von = Carbon::create($jahr, 1, 1)->toDateString();
        $bis = Carbon::create($jahr, 12, 31)->toDateString();

        $urlaub = $employe->holidays()->withTrashed()
            ->ueberschneidet($von, $bis)
            ->get()
            ->map(fn ($h) => [
                'art'    => 'urlaub',
                'start'  => $h->start_date,
                'ende'   => $h->end_date,
                'titel'  => 'Urlaub',
                'info'   => trim($h->days_label.($h->comment ? ' · '.$h->comment : '')),
                'status' => $h->trashed() ? 'Storniert' : $h->status_label,
                'badge'  => $h->trashed() ? 'badge-gray' : [
                    'beantragt' => 'badge-yellow', 'genehmigt' => 'badge-green',
                    'abgelehnt' => 'badge-red', 'storno_beantragt' => 'badge-blue',
                ][$h->status] ?? 'badge-gray',
                'blass'  => $h->trashed() || $h->status === 'abgelehnt',
            ]);

        $sonstige = $employe->absences()
            ->whereNull('holiday_id')
            ->whereDate('start', '<=', $bis)
            ->whereDate('end', '>=', $von)
            ->get()
            ->map(fn ($a) => [
                'art'    => 'abwesenheit',
                'start'  => $a->start,
                'ende'   => $a->end,
                'titel'  => $a->reason ?: 'Abwesenheit',
                'info'   => $a->days.' '.($a->days === 1 ? 'Arbeitstag' : 'Arbeitstage'),
                'status' => null,
                'badge'  => null,
                'blass'  => false,
            ]);

        return $urlaub->concat($sonstige)->sortByDesc(fn ($e) => $e['start']->timestamp)->values();
    }

    /**
     * GET /mein-profil/vertraege
     */
    public function vertraege()
    {
        $employe = auth()->user()->load([
            'employments' => fn($q) => $q->with('department', 'salaryTable', 'currentTeacherDetail.subjects', 'hour_type', 'replacedEmployment.department'),
        ]);

        return view('personal.self-service.vertraege', [
            'employments'  => $employe->employments,
            'canViewSalary' => auth()->user()->can('view salary'),
        ]);
    }

    /**
     * GET /mein-profil/dokumente
     */
    public function dokumente()
    {
        $documents = PersonalDocument::where('employe_id', auth()->id())
            ->with('documentType')
            ->orderByDesc('created_at')
            ->get();

        return view('personal.self-service.dokumente', [
            'documents' => $documents,
        ]);
    }

    /**
     * GET /mein-profil/qualifikationen
     */
    public function qualifikationen()
    {
        $qualifikationen = EmployeeQualification::where('employe_id', auth()->id())
            ->with('qualificationType')
            ->orderBy('expiry_date')
            ->get();

        return view('personal.self-service.qualifikationen', [
            'qualifikationen' => $qualifikationen,
        ]);
    }

    /**
     * GET /mein-profil/gespraeche
     */
    public function gespraeche()
    {
        return view('personal.self-service.gespraeche', [
            'gespraeche' => collect(), // Phase 3: Mitarbeitergespräche
        ]);
    }

    /**
     * GET /mein-profil/einwilligungen
     */
    public function einwilligungen()
    {
        $consentTypes = ConsentType::where('is_active', true)->get();
        $myConsents   = auth()->user()->consents()->with('consentType')->get()
            ->keyBy('consent_type_id');

        return view('personal.self-service.einwilligungen', compact('consentTypes', 'myConsents'));
    }

    /**
     * GET /mein-profil/stundenzettel (Passwort-Bestätigung erforderlich)
     */
    public function stundenzettel()
    {
        $employe = auth()->user()->load([
            'timesheets' => fn($q) => $q->latest()->limit(12),
        ]);

        return view('personal.self-service.stundenzettel', compact('employe'));
    }
}

