<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use App\Models\personal\OrgPosition;
use App\Models\personal\PersonalAccessLog;
use App\Services\Personal\PersonalScopeService;
use Illuminate\Http\Request;

class OrgChartController extends Controller
{
    public function __construct(private readonly PersonalScopeService $scopeService)
    {}

    /**
     * Organigramm-Übersicht.
     */
    public function index()
    {
        $this->authorize('view orgchart');

        $rootPosition = OrgPosition::whereNull('parent_position_id')
            ->with('allChildren.currentUsers', 'allChildren.currentDeputy')
            ->orderBy('sort_order')
            ->first();

        $treeData = $rootPosition ? $this->buildTreeJson($rootPosition) : null;

        return view('personal.orgchart.index', compact('treeData'));
    }

    /**
     * PDF-Export des Organigramms.
     */
    public function exportPdf()
    {
        $this->authorize('export orgchart');

        PersonalAccessLog::create([
            'user_id'       => auth()->id(),
            'action'        => 'export',
            'resource_type' => 'orgchart',
            'resource_id'   => null,
            'route'         => 'personal.orgchart.export.pdf',
            'ip_address'    => request()->ip(),
            'metadata'      => ['format' => 'pdf'],
        ]);

        $rootPosition = OrgPosition::whereNull('parent_position_id')
            ->with('allChildren.currentUsers')
            ->first();

        $pdf = \Barryvdh\Snappy\Facades\SnappyPdf::loadView(
            'personal.orgchart.pdf',
            ['rootPosition' => $rootPosition, 'generatedAt' => now()]
        );

        return $pdf->setOptions([
            'orientation'   => 'Landscape',
            'page-size'     => 'A3',
            'encoding'      => 'utf-8',
            'margin-top'    => '10',
            'margin-bottom' => '10',
            'margin-left'   => '10',
            'margin-right'  => '10',
        ])->download('Organigramm_' . now()->format('Y-m-d') . '.pdf');
    }

    // --- Positions-CRUD (Personalleitung) ---

    public function positionsIndex()
    {
        $this->authorize('manage orgchart');
        $positions = OrgPosition::with('parent', 'department')->orderBy('sort_order')->get();
        return view('personal.orgchart.positions.index', compact('positions'));
    }

    public function positionsCreate()
    {
        $this->authorize('manage orgchart');
        $parents = OrgPosition::orderBy('name')->get();
        $departments = \App\Models\Group::all();
        return view('personal.orgchart.positions.create', compact('parents', 'departments'));
    }

    public function positionsStore(Request $request)
    {
        $this->authorize('manage orgchart');
        $data = $request->validate([
            'name'               => ['required', 'string', 'max:100'],
            'parent_position_id' => ['nullable', 'integer', 'exists:pers_org_positions,id'],
            'department_id'      => ['nullable', 'integer', 'exists:groups,id'],
            'is_leadership'      => ['boolean'],
            'sort_order'         => ['integer', 'min:0'],
            'color'              => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        // Level automatisch berechnen
        $data['level'] = $data['parent_position_id']
            ? (OrgPosition::find($data['parent_position_id'])?->level ?? 0) + 1
            : 0;

        OrgPosition::create($data);

        return redirectBack(route('personal.orgchart.positions.index'))
            ->with('Meldung', 'Position wurde angelegt.')
            ->with('type', 'success');
    }

    public function positionsEdit(OrgPosition $position)
    {
        $this->authorize('manage orgchart');
        $parents     = OrgPosition::where('id', '!=', $position->id)->orderBy('name')->get();
        $departments = \App\Models\Group::all();
        return view('personal.orgchart.positions.edit', compact('position', 'parents', 'departments'));
    }

    public function positionsUpdate(Request $request, OrgPosition $position)
    {
        $this->authorize('manage orgchart');
        $data = $request->validate([
            'name'               => ['required', 'string', 'max:100'],
            'parent_position_id' => ['nullable', 'integer', 'exists:pers_org_positions,id'],
            'department_id'      => ['nullable', 'integer', 'exists:groups,id'],
            'is_leadership'      => ['boolean'],
            'sort_order'         => ['integer', 'min:0'],
            'color'              => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $position->update($data);

        return redirectBack(route('personal.orgchart.positions.index'))
            ->with('Meldung', 'Position wurde aktualisiert.')
            ->with('type', 'success');
    }

    public function positionsDestroy(OrgPosition $position)
    {
        $this->authorize('manage orgchart');
        $position->delete();
        return redirectBack(route('personal.orgchart.positions.index'))
            ->with('Meldung', 'Position wurde gelöscht.')
            ->with('type', 'warning');
    }

    public function positionsAssign(Request $request, OrgPosition $position)
    {
        $this->authorize('manage orgchart');
        $data = $request->validate([
            'user_id'    => ['required', 'integer', 'exists:users,id'],
            'is_deputy'  => ['boolean'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after:valid_from'],
        ]);

        $position->users()->attach($data['user_id'], [
            'is_deputy'  => $data['is_deputy'] ?? false,
            'valid_from' => $data['valid_from'],
            'valid_until' => $data['valid_until'] ?? null,
        ]);

        return redirectBack()
            ->with('Meldung', 'Mitarbeiter wurde der Position zugewiesen.')
            ->with('type', 'success');
    }

    // --- Hilfsmethoden ---

    private function buildTreeJson(?OrgPosition $position): ?array
    {
        if (!$position) return null;

        return [
            'id'         => $position->id,
            'name'       => $position->name,
            'color'      => $position->color,
            'level'      => $position->level,
            'leadership' => $position->is_leadership,
            'users'      => $position->currentUsers->map(fn($u) => [
                'id'     => $u->id,
                'name'   => $u->name,
                'avatar' => $u->profile_photo_url ?? null,
                'email'  => $u->email,
                'hasConsent_foto_organigramm' => method_exists($u, 'hasConsent')
                    ? $u->hasConsent('foto_organigramm') : false,
            ])->toArray(),
            'deputy' => $position->currentDeputy->map(fn($u) => [
                'id'   => $u->id,
                'name' => $u->name,
            ])->toArray(),
            'children' => $position->children->map(
                fn($child) => $this->buildTreeJson($child)
            )->filter()->values()->toArray(),
        ];
    }
}

