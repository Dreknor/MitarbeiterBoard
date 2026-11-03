<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Personal\PersonalRetentionService;
use App\Services\UserDeletion\Handover;
use App\Services\UserDeletion\UserDeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Löschkonzept: Übergabe/Deaktivierung, Anonymisierung und endgültige Löschung von Benutzerkonten.
 * Stufe 2 und 3 werden nur nach Bestätigung auf der Auswirkungsübersicht ausgeführt.
 */
class UserDeletionController extends Controller
{
    public function __construct(
        private readonly UserDeletionService $service,
        private readonly PersonalRetentionService $retention,
    ) {
    }

    public function index(): View
    {
        $deactivated = $this->service->deactivatedAccounts();
        $anonymized = $this->service->anonymizedAccounts();

        return view('users.deletion.index', [
            'deactivated' => $deactivated,
            'anonymized' => $anonymized,
            'service' => $this->service,
            'retention' => $this->retention,
            'canManage' => auth()->user()->can('manage user deletion'),
        ]);
    }

    public function deactivateForm(int $user_id): View|RedirectResponse
    {
        $user = User::findOrFail($user_id);

        if ($user->is_placeholder || $user->id === auth()->id()) {
            return redirectBack('danger', 'Dieses Konto kann nicht deaktiviert werden.');
        }

        return view('users.deletion.deactivate', [
            'user' => $user,
            'items' => $this->service->handoverItems($user),
            'candidates' => $this->service->handoverCandidates($user),
            'suggested' => $this->service->suggestedHandoverTarget($user),
            'report' => $this->service->impactReport($user, 1),
            'externalSteps' => $this->service->externalSteps($user, 1),
        ]);
    }

    public function deactivate(Request $request, int $user_id): RedirectResponse
    {
        $user = User::findOrFail($user_id);

        $keys = collect(Handover::all())->pluck('key')->all();
        $data = $request->validate([
            'left_at' => ['required', 'date'],
            'handover' => ['array'],
            'handover.*' => ['nullable', 'integer'],
            'confirm' => ['accepted'],
        ]);

        $handovers = array_intersect_key($data['handover'] ?? [], array_flip($keys));

        if ($user->id === auth()->id()) {
            return redirectBack('danger', 'Das eigene Konto kann nicht deaktiviert werden.');
        }

        try {
            $this->service->deactivate($user, $handovers, auth()->user(), \Carbon\Carbon::parse($data['left_at']));
        } catch (RuntimeException $e) {
            return redirectBack('danger', $e->getMessage());
        }

        return redirect(url('users'))->with([
            'type' => 'warning',
            'Meldung' => 'Benutzer deaktiviert. Die Anonymisierung ist ab '
                .$this->retention->anonymizeDueAt($user)->format('d.m.Y').' möglich.',
        ]);
    }

    public function confirm(int $user_id, int $stage): View
    {
        $user = User::withTrashed()->where('is_placeholder', false)->findOrFail($user_id);

        return view('users.deletion.confirm', [
            'user' => $user,
            'stage' => $stage,
            'report' => $this->service->impactReport($user, $stage),
            'externalSteps' => $this->service->externalSteps($user, $stage),
            'allowed' => $stage === 2 ? $this->service->canAnonymize($user) : $this->service->canPurge($user),
            'anonymizeDueAt' => $this->retention->anonymizeDueAt($user),
            'service' => $this->service,
        ]);
    }

    public function anonymize(Request $request, int $user_id): RedirectResponse
    {
        $user = User::withTrashed()->findOrFail($user_id);

        $data = $request->validate([
            'confirm' => ['accepted'],
            'early' => ['nullable', 'boolean'],
            'early_reason' => ['required_if:early,1', 'nullable', 'string', 'max:255'],
        ]);

        try {
            $this->service->anonymize($user, auth()->user(), (bool) ($data['early'] ?? false));
        } catch (RuntimeException $e) {
            return redirectBack('danger', $e->getMessage());
        }

        return redirect(url('user-deletion'))->with([
            'type' => 'success',
            'Meldung' => 'Konto #'.$user_id.' wurde anonymisiert.',
        ]);
    }

    public function purge(Request $request, int $user_id): RedirectResponse
    {
        $user = User::withTrashed()->findOrFail($user_id);

        $request->validate(['confirm' => ['accepted']]);

        if (!$this->service->canPurge($user)) {
            return redirectBack('danger', 'Für dieses Konto ist derzeit nichts endgültig zu löschen.');
        }

        try {
            $result = $this->service->purge($user, auth()->user());
        } catch (RuntimeException $e) {
            return redirectBack('danger', $e->getMessage());
        }

        return redirect(url('user-deletion'))->with([
            'type' => 'success',
            'Meldung' => $result['deleted']
                ? 'Konto #'.$user_id.' wurde endgültig gelöscht.'
                : 'Abgelaufene Daten von Konto #'.$user_id.' wurden gelöscht. Weitere Daten bleiben bis Fristende gesperrt.',
        ]);
    }

    public function keep(Request $request, int $user_id): RedirectResponse
    {
        $user = User::onlyTrashed()->where('is_placeholder', false)->findOrFail($user_id);

        $data = $request->validate(['note' => ['required', 'string', 'max:1000']]);

        $this->service->keep($user, auth()->user(), $data['note']);

        return redirectBack('success', 'Entscheidung gespeichert: Konto bleibt vorerst deaktiviert.');
    }

    public function legalHold(Request $request, int $user_id): RedirectResponse
    {
        $user = User::withTrashed()->where('is_placeholder', false)->findOrFail($user_id);

        $data = $request->validate([
            'hold' => ['required', 'boolean'],
            'reason' => ['required_if:hold,1', 'nullable', 'string', 'max:255'],
        ]);

        $this->service->setLegalHold($user, auth()->user(), (bool) $data['hold'], $data['reason'] ?? null);

        return redirectBack('success', $data['hold'] ? 'Aufbewahrungssperre gesetzt.' : 'Aufbewahrungssperre aufgehoben.');
    }
}
