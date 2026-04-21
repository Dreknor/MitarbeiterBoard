<?php

namespace App\Http\Controllers\Personal;

use App\Enums\EmploymentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\personal\StoreQualificationTypeRequest;
use App\Models\personal\QualificationType;
use Illuminate\Http\RedirectResponse;

/**
 * Verwaltung der Qualifikationstypen (Qualifikationsmatrix-Vorgaben).
 * Nur für Nutzer mit „manage qualifications" zugänglich.
 */
class QualificationTypeController extends Controller
{
    public function index()
    {
        $this->authorize('manage qualifications');

        $types = QualificationType::orderBy('is_active', 'desc')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $employmentTypes = collect(EmploymentType::cases())
            ->mapWithKeys(fn ($c) => [$c->value => $c->label()])
            ->all();

        return view('personal.qualifications.types.index', compact('types', 'employmentTypes'));
    }

    public function create()
    {
        $this->authorize('manage qualifications');

        $employmentTypes = collect(EmploymentType::cases())
            ->mapWithKeys(fn ($c) => [$c->value => $c->label()])
            ->all();

        return view('personal.qualifications.types.form', [
            'type'            => new QualificationType(['category' => 'pflicht', 'reminder_days' => 90, 'is_active' => true]),
            'employmentTypes' => $employmentTypes,
        ]);
    }

    public function store(StoreQualificationTypeRequest $request): RedirectResponse
    {
        $this->authorize('manage qualifications');

        $data = $this->prepareData($request);

        QualificationType::create($data);
        $this->flushMatrixCache();

        return redirect()->route('personal.qualification-types.index')
            ->with('Meldung', 'Qualifikationstyp wurde angelegt.')
            ->with('type', 'success');
    }

    public function edit(QualificationType $qualificationType)
    {
        $this->authorize('manage qualifications');

        $employmentTypes = collect(EmploymentType::cases())
            ->mapWithKeys(fn ($c) => [$c->value => $c->label()])
            ->all();

        return view('personal.qualifications.types.form', [
            'type'            => $qualificationType,
            'employmentTypes' => $employmentTypes,
        ]);
    }

    public function update(StoreQualificationTypeRequest $request, QualificationType $qualificationType): RedirectResponse
    {
        $this->authorize('manage qualifications');

        $qualificationType->update($this->prepareData($request));
        $this->flushMatrixCache();

        return redirect()->route('personal.qualification-types.index')
            ->with('Meldung', 'Qualifikationstyp wurde aktualisiert.')
            ->with('type', 'success');
    }

    public function destroy(QualificationType $qualificationType): RedirectResponse
    {
        $this->authorize('manage qualifications');

        // Wenn bereits Qualifikationen daran hängen, nur deaktivieren statt löschen.
        if ($qualificationType->employeeQualifications()->exists() || $qualificationType->trainings()->exists()) {
            $qualificationType->update(['is_active' => false]);
            $this->flushMatrixCache();

            return redirect()->route('personal.qualification-types.index')
                ->with('Meldung', 'Typ wird verwendet und wurde nur deaktiviert.')
                ->with('type', 'warning');
        }

        $qualificationType->delete();
        $this->flushMatrixCache();

        return redirect()->route('personal.qualification-types.index')
            ->with('Meldung', 'Qualifikationstyp wurde gelöscht.')
            ->with('type', 'success');
    }

    private function prepareData(StoreQualificationTypeRequest $request): array
    {
        $validated = $request->validated();
        $validated['applies_to'] = empty($validated['applies_to']) ? null : array_values($validated['applies_to']);
        $validated['is_active']  = (bool) ($validated['is_active'] ?? false);

        return $validated;
    }

    private function flushMatrixCache(): void
    {
        // Matrix-Cache ist per-user; via Versions-Timestamp im Cache-Key wird alles invalidiert.
        \Illuminate\Support\Facades\Cache::put('qualification_matrix_version', now()->timestamp, now()->addYear());
    }
}


