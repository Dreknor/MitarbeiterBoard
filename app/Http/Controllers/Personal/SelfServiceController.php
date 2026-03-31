<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use App\Http\Resources\Personal\EmployeeSelfServiceResource;
use App\Models\personal\Consent;
use App\Models\personal\ConsentType;
use App\Models\personal\PersonalDocument;
use App\Models\personal\EmployeeQualification;

class SelfServiceController extends Controller
{
    /**
     * GET /mein-profil
     * IDOR-Schutz by Design: Immer auth()->user(), kein ID-Parameter.
     */
    public function index()
    {
        $employe = auth()->user()->load([
            'employe_data',
            'employments' => fn($q) => $q->active()->with('department', 'currentTeacherDetail', 'hour_type'),
        ]);

        $resource = (new EmployeeSelfServiceResource($employe))->toArray(request());

        return view('personal.self-service.index', [
            'employe'     => $resource,
            'rawEmploye'  => $employe,
        ]);
    }

    /**
     * GET /mein-profil/vertraege
     */
    public function vertraege()
    {
        $employe = auth()->user()->load([
            'employments' => fn($q) => $q->with('department', 'salaryTable', 'currentTeacherDetail.subjects', 'hour_type'),
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

