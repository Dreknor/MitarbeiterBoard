<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use App\Models\personal\Consent;
use App\Models\personal\ConsentType;
use App\Services\Personal\PersonalScopeService;
use Illuminate\Http\RedirectResponse;

class ConsentController extends Controller
{
    public function __construct(private readonly PersonalScopeService $scopeService)
    {}

    /**
     * GET /mein-profil/einwilligungen – Self-Service
     */
    public function index()
    {
        $consentTypes = ConsentType::where('is_active', true)->get();
        $myConsents   = auth()->user()->consents()->with('consentType')->get()
            ->keyBy('consent_type_id');

        return view('personal.consents.index', compact('consentTypes', 'myConsents'));
    }

    /**
     * POST /mein-profil/einwilligungen/{type}/erteilen
     */
    public function grant(ConsentType $type): RedirectResponse
    {
        $employe = auth()->user();

        Consent::updateOrCreate(
            ['employe_id' => $employe->id, 'consent_type_id' => $type->id],
            ['granted_at' => now(), 'revoked_at' => null, 'granted_via' => 'self_service']
        );

        return redirectBack()
            ->with('Meldung', 'Einwilligung wurde erteilt.')
            ->with('type', 'success');
    }

    /**
     * POST /mein-profil/einwilligungen/{type}/widerrufen
     */
    public function revoke(ConsentType $type): RedirectResponse
    {
        $consent = Consent::where('employe_id', auth()->id())
            ->where('consent_type_id', $type->id)
            ->whereNull('revoked_at')
            ->firstOrFail();

        $consent->update(['revoked_at' => now()]);

        return redirectBack()
            ->with('Meldung', 'Einwilligung wurde widerrufen.')
            ->with('type', 'warning');
    }

    /**
     * GET /personal/einwilligungen – Personalleitung: Übersicht aller MA
     */
    public function adminIndex()
    {
        $this->authorize('manage personal_consents');

        $consentTypes = ConsentType::where('is_active', true)->get();
        $users = $this->scopeService->visibleEmployees()
            ->with(['consents.consentType'])
            ->get();

        return view('personal.consents.admin', compact('consentTypes', 'users'));
    }
}

