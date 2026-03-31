<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use App\Models\personal\EmployeeQualification;
use App\Models\personal\QualificationType;
use App\Models\User;
use App\Services\Personal\PersonalScopeService;
use App\Services\Personal\QualificationService;
use Illuminate\Http\Request;

class QualificationController extends Controller
{
    public function index(User $employe)
    {
        $employe = app(PersonalScopeService::class)->visibleEmployees()->findOrFail($employe->id);
        $this->authorize('view qualifications');

        $qualificationService = app(QualificationService::class);
        $qualifications       = $qualificationService->getQualificationStatus($employe);
        $missing              = $qualificationService->getMissingRequired($employe);
        $qualificationTypes   = QualificationType::where('is_active', true)->orderBy('category')->orderBy('name')->get();

        return view('personal.qualifications.index', compact('employe', 'qualifications', 'missing', 'qualificationTypes'));
    }

    public function store(Request $request, User $employe)
    {
        $employe = app(PersonalScopeService::class)->visibleEmployees()->findOrFail($employe->id);
        $this->authorize('manage qualifications');

        $request->validate([
            'qualification_type_id' => 'required|exists:pers_qualification_types,id',
            'acquired_date'         => 'required|date',
            'expiry_date'           => 'nullable|date|after:acquired_date',
            'document_id'           => 'nullable|exists:pers_documents,id',
            'notes'                 => 'nullable|string|max:2000',
        ]);

        $type       = QualificationType::findOrFail($request->qualification_type_id);
        $expiryDate = $request->expiry_date;

        // Ablaufdatum automatisch berechnen wenn nicht angegeben
        if (! $expiryDate && $type->validity_months) {
            $expiryDate = now()->addMonths($type->validity_months)->toDateString();
        }

        EmployeeQualification::updateOrCreate(
            [
                'employe_id'            => $employe->id,
                'qualification_type_id' => $request->qualification_type_id,
            ],
            [
                'acquired_date'  => $request->acquired_date,
                'expiry_date'    => $expiryDate,
                'document_id'    => $request->document_id,
                'notes'          => $request->notes,
                'verified_by'    => auth()->id(),
                'verified_at'    => now(),
            ]
        );

        // Status neu berechnen
        $qual = EmployeeQualification::where('employe_id', $employe->id)
            ->where('qualification_type_id', $request->qualification_type_id)
            ->first();

        if ($qual) {
            $qual->status = app(QualificationService::class)->calculateStatus($qual);
            $qual->saveQuietly();
        }

        return redirectBack()
            ->with('Meldung', 'Qualifikation wurde gespeichert.')
            ->with('type', 'success');
    }

    public function destroy(EmployeeQualification $qualification)
    {
        $this->authorize('manage qualifications');

        $qualification->delete();

        return redirectBack()
            ->with('Meldung', 'Qualifikation wurde gelöscht.')
            ->with('type', 'success');
    }

    public function matrix()
    {
        $this->authorize('view qualifications');

        $data = app(QualificationService::class)->getQualificationMatrix(auth()->user());

        return view('personal.qualifications.matrix', $data);
    }
}

