<?php

namespace App\Http\Controllers\Personal;

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentStatusReason;
use App\Enums\TerminationReason;
use App\Http\Controllers\Controller;
use App\Models\personal\Employment;
use App\Models\personal\SchoolType;
use App\Models\personal\SalaryTable;
use App\Models\personal\TeacherDetail;
use App\Models\personal\TeacherSubject;
use App\Models\User;
use App\Services\Personal\ContractValidationService;
use App\Services\Personal\PersonalScopeService;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    public function __construct(
        private readonly PersonalScopeService $scopeService,
        private readonly ContractValidationService $contractValidation
    ) {}

    /**
     * Vertragsübersicht eines Mitarbeiters.
     */
    public function index(int $employe)
    {
        $employe = $this->scopeService->visibleEmployees()->findOrFail($employe);
        $this->authorize('view', $employe->employments->first() ?? new Employment(['employe_id' => $employe->id]));

        $employments  = $employe->employments()
            ->with(['department', 'salaryTable', 'currentTeacherDetail.subjects', 'hour_type'])
            ->latest('start')
            ->get();

        $activeContracts = $employments->filter(fn($e) => $e->status === EmploymentStatus::Aktiv);
        $pastContracts   = $employments->filter(fn($e) => $e->status === EmploymentStatus::Beendet);
        $ruhendeContracts = $employments->filter(fn($e) => $e->status === EmploymentStatus::Ruhend);
        $hasTeacher      = $employments->contains(fn($e) => $e->employment_type?->requiresTeacherDetail());

        return view('personal.contracts.index', compact(
            'employe', 'activeContracts', 'pastContracts', 'ruhendeContracts', 'hasTeacher'
        ));
    }

    /**
     * Formular: Neue Anstellung anlegen.
     */
    public function create(int $employe)
    {
        $this->authorize('create', Employment::class);
        $employe     = $this->scopeService->visibleEmployees()->findOrFail($employe);
        $schoolTypes = SchoolType::where('is_active', true)->get();
        $salaryTables = SalaryTable::whereNull('valid_until')
            ->orWhere('valid_until', '>=', now())
            ->get();

        return view('personal.contracts.create', compact('employe', 'schoolTypes', 'salaryTables'));
    }

    /**
     * Neue Anstellung speichern.
     */
    public function store(Request $request, int $employe)
    {
        $this->authorize('create', Employment::class);
        $employe = $this->scopeService->visibleEmployees()->findOrFail($employe);

        $rules = $this->getValidationRules($request->input('employment_type'));
        $data  = $request->validate($rules);

        // Befristungswarnung prüfen
        if (in_array($data['contract_type'] ?? '', ['befristet', 'befristet_sachgrund'])) {
            $warnung = $this->contractValidation->checkBefristungsketten($employe->id);
            if ($warnung['warnung']) {
                session()->flash('befristungs_warnung', $warnung['nachricht']);
            }
        }

        $employment = Employment::create(array_merge(
            $data,
            ['employe_id' => $employe->id, 'status' => 'aktiv']
        ));

        // Lehrer-Details anlegen
        if (($data['employment_type'] ?? '') === 'lehrer' && isset($data['school_type_id'])) {
            TeacherDetail::create([
                'employment_id'      => $employment->id,
                'school_type_id'     => $data['school_type_id'],
                'deputat_hours'      => $data['deputat_hours'],
                'reduction_hours'    => $data['reduction_hours'] ?? 0,
                'reduction_reason'   => $data['reduction_reason'] ?? null,
                'anrechnungsstunden' => $data['anrechnungsstunden'] ?? 0,
                'valid_from'         => $data['start'],
                'valid_until'        => null,
            ]);
        }

        return redirectBack(route('personal.contracts.index', $employe->id))
            ->with('Meldung', 'Anstellung wurde erfolgreich angelegt.')
            ->with('type', 'success');
    }

    /**
     * Bearbeitungsformular.
     */
    public function edit(Employment $employment)
    {
        $this->authorize('update', $employment);
        $employe      = $employment->employe;
        $schoolTypes  = SchoolType::where('is_active', true)->get();
        $salaryTables = SalaryTable::whereNull('valid_until')
            ->orWhere('valid_until', '>=', now())
            ->get();

        return view('personal.contracts.edit', compact('employe', 'employment', 'schoolTypes', 'salaryTables'));
    }

    /**
     * Anstellung aktualisieren.
     */
    public function update(Request $request, Employment $employment)
    {
        $this->authorize('update', $employment);

        $rules = $this->getValidationRules($request->input('employment_type', $employment->employment_type?->value));
        $data  = $request->validate($rules);

        if (in_array($data['contract_type'] ?? '', ['befristet', 'befristet_sachgrund'])) {
            $warnung = $this->contractValidation->checkBefristungsketten($employment->employe_id, $employment->id);
            if ($warnung['warnung']) {
                session()->flash('befristungs_warnung', $warnung['nachricht']);
            }
        }

        $employment->update($data);

        return redirectBack(route('personal.contracts.index', $employment->employe_id))
            ->with('Meldung', 'Anstellung wurde aktualisiert.')
            ->with('type', 'success');
    }

    /**
     * Status auf 'ruhend' setzen.
     */
    public function setRuhend(Request $request, Employment $employment)
    {
        $this->authorize('update', $employment);
        $data = $request->validate(['reason' => ['required', 'string']]);

        try {
            $employment->setRuhend(EmploymentStatusReason::from($data['reason']));
        } catch (\LogicException $e) {
            return redirectBack()->with('Meldung', $e->getMessage())->with('type', 'danger');
        }

        return redirectBack(route('personal.contracts.index', $employment->employe_id))
            ->with('Meldung', 'Anstellung wurde auf ruhend gesetzt.')
            ->with('type', 'warning');
    }

    /**
     * Status auf 'beendet' setzen.
     */
    public function setBeendet(Request $request, Employment $employment)
    {
        $this->authorize('update', $employment);
        $data = $request->validate([
            'reason'   => ['required', 'string'],
            'end_date' => ['nullable', 'date'],
        ]);

        try {
            $employment->setBeendet(
                TerminationReason::from($data['reason']),
                isset($data['end_date']) ? \Carbon\Carbon::parse($data['end_date']) : null
            );
        } catch (\LogicException $e) {
            return redirectBack()->with('Meldung', $e->getMessage())->with('type', 'danger');
        }

        return redirectBack(route('personal.contracts.index', $employment->employe_id))
            ->with('Meldung', 'Anstellung wurde beendet.')
            ->with('type', 'warning');
    }

    private function getValidationRules(string $employmentType = 'regulaer'): array
    {
        $rules = [
            'employment_type'    => ['required', 'string'],
            'contract_type'      => ['required', 'string'],
            'start'              => ['required', 'date'],
            'end'                => ['nullable', 'date', 'after_or_equal:start'],
            'hours'              => ['required', 'numeric', 'min:1', 'max:168'],
            'hour_type_id'       => ['nullable', 'integer', 'exists:hour_types,id'],
            'department_id'      => ['nullable', 'integer', 'exists:groups,id'],
            'probation_end'      => ['nullable', 'date'],
            'notice_period'      => ['nullable', 'string', 'max:50'],
            'comment'            => ['nullable', 'string', 'max:1000'],
            'is_amendment'       => ['boolean'],
            'amendment_description' => ['nullable', 'string', 'max:500'],
            'is_internal_transfer' => ['boolean'],
            // Gehalt (optional, nur wenn berechtigt)
            'salary_group'       => ['nullable', 'string', 'max:20'],
            'salary_level'       => ['nullable', 'string', 'max:20'],
            'salary_table_id'    => ['nullable', 'integer', 'exists:pers_salary_tables,id'],
        ];

        // Lehrer-spezifische Felder
        if ($employmentType === 'lehrer') {
            $rules = array_merge($rules, [
                'school_type_id'     => ['required', 'integer', 'exists:pers_school_types,id'],
                'deputat_hours'      => ['required', 'numeric', 'min:0'],
                'reduction_hours'    => ['nullable', 'numeric', 'min:0'],
                'reduction_reason'   => ['nullable', 'string', 'max:200'],
                'anrechnungsstunden' => ['nullable', 'numeric', 'min:0'],
            ]);
        }

        return $rules;
    }
}

