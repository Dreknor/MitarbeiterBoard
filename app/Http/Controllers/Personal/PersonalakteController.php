<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Personal\PersonalScopeService;
use Illuminate\View\View;

class PersonalakteController extends Controller
{
    public function __construct(
        private readonly PersonalScopeService $scopeService
    ) {}

    /**
     * GET /personal/mitarbeiter/{employe}
     * Personalakte-Übersicht (Hub) für einen Mitarbeiter.
     * IDOR-Schutz: Immer visibleEmployees() nutzen.
     */
    public function show(int $employe): View
    {
        /** @var User $employe */
        $employe = $this->scopeService->visibleEmployees()->findOrFail($employe);

        $employe->load([
            'employe_data',
            'employments' => fn ($q) => $q->active()->with('department'),
        ]);

        return view('personal.personalakte.show', [
            'employe' => $employe,
        ]);
    }
}

