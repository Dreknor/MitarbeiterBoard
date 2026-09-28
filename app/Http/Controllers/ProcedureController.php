<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateProcedureTemplateRequest;
use App\Http\Requests\CreateStepRequest;
use App\Http\Requests\EditStepRequest;
use App\Models\Positions;
use App\Models\Procedure;
use App\Models\Procedure_Category;
use App\Models\Procedure_Step;
use App\Models\ProcedureStepHistory;
use App\Models\ProcedureTemplate;
use App\Models\RecurringProcedure;
use App\Models\User;
use App\Services\Procedure\ProcedureNotificationService;
use App\Services\Procedure\ProcedureService;
use App\Services\Procedure\ProcedureStepService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class ProcedureController extends Controller
{
    public function __construct(
        private readonly ProcedureService $procedureService,
        private readonly ProcedureStepService $stepService,
        private readonly ProcedureNotificationService $notifications,
    ) {
        $this->middleware(function ($request, $next) {
            $user = auth()->user();

            // Entweder manage procedures ODER view assigned procedures
            if (!$user->can('manage procedures') && !$user->can('view assigned procedures')) {
                abort(403, 'Keine Berechtigung.');
            }

            return $next($request);
        });
    }

    /**
     * Lädt alle Schritte eines Prozesses in einer Query (inkl. Position und Verantwortliche)
     * und baut die `childs`-Relationen im Speicher auf. Vermeidet N+1-Queries beim
     * rekursiven Rendern des Schritt-Baums – unabhängig von der Baumtiefe.
     */
    private function loadStepTree(Procedure $procedure): Procedure
    {
        $steps = $procedure->steps()
            ->with('position', 'users')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $byParent = $steps->groupBy('parent');

        foreach ($steps as $step) {
            $step->setRelation('childs', new EloquentCollection($byParent->get($step->id, collect())->all()));
        }

        return $procedure->setRelation('steps', $steps)->loadMissing('category');
    }

    private function positions(): EloquentCollection
    {
        return Positions::orderBy('name')->get();
    }

    public function delete(Procedure $procedure)
    {
        if (!auth()->user()->can('delete procedures')) {
            return redirect()->back()->with([
                'type'=>'danger',
                'Meldung'=> 'Keine Berechtigung.'
            ]);
        }

        if ($procedure->isTemplate()) {
            $this->procedureService->deleteTemplate($procedure);
        } else {
            $procedure->delete();
        }

        return redirect()->back()->with([
            'type'=>'warning',
            'Meldung'=> $procedure->isTemplate() ? 'Vorlage wurde gelöscht.' : 'Prozess wurde gelöscht.'
        ]);
    }

    public function destroy(Procedure_Step $step)
    {
        // Nur Admins können Schritte löschen
        if (!auth()->user()->can('manage procedures')) {
            return redirect()->back()->with([
                'type'=>'danger',
                'Meldung'=> 'Keine Berechtigung Schritte zu löschen.'
            ]);
        }

        $procedure = $step->procedure;

        DB::transaction(function () use ($step) {
            $step->users()->detach();
            // Kinder rücken eine Ebene nach oben
            $step->childs()->update(['parent' => $step->parent]);
            $step->delete();
        });

        $redirect = $procedure && $procedure->isTemplate()
            ? redirect(url('procedure/'.$procedure->id.'/edit'))
            : redirect()->back();

        return $redirect->with([
            'type'=>'warning',
            'Meldung'=> 'Schritt wurde gelöscht.'
        ]);
    }

    public function index()
    {
        $user = auth()->user();

        $procedures = Procedure::laufend()
            ->sichtbarFuer($user)
            ->with('category', 'steps')
            ->orderByDesc('started_at')
            ->get();

        $viewData = [
            'procedures'          => $procedures,
            'proceduresTemplate'  => collect(),
            'categories'          => collect(),
            'positions'           => collect(),
            'users'               => collect(),
            'recurringProcedures' => collect(),
        ];

        // Verwaltungs-Tabs (Vorlagen, Automatisierung) sehen nur Admins – Daten nur dann laden.
        if ($user->can('manage procedures')) {
            $viewData = array_merge($viewData, [
                'proceduresTemplate'  => Procedure::vorlagen()->with('category')->orderBy('name')->get(),
                'categories'          => Procedure_Category::orderBy('name')->get(),
                'positions'           => Positions::with('users')->orderBy('name')->get(),
                'users'               => User::orderBy('name')->get(),
                'recurringProcedures' => RecurringProcedure::with('procedure.category')->orderBy('name')->get(),
            ]);
        }

        $viewData['monate'] = [
            1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April',
            5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
        ];

        return view('procedure.index', $viewData);
    }

    public function storeTemplate(CreateProcedureTemplateRequest $request)
    {
        // Nur Admins können Templates erstellen
        if (!auth()->user()->can('manage procedures')) {
            return redirect()->back()->with([
                'type'=>'danger',
                'Meldung'=> 'Keine Berechtigung Vorlagen zu erstellen.'
            ]);
        }

        $template = $this->procedureService->createTemplate($request->validated(), auth()->id())['legacy'];

        return redirect(url('procedure/'.$template->id.'/edit'))->with([
            'type'=>'success',
            'Meldung'=>'Prozess erstellt. Nun können Schritte hinzugefügt werden.',
        ]);
    }

    public function edit(Procedure $procedure)
    {
        $this->authorize('view', $procedure);

        return view('procedure.edit', [
            'procedure' => $this->loadStepTree($procedure),
            'positions' => $this->positions(),
            'canEdit'   => auth()->user()->can('manage procedures'),
        ]);
    }

    public function start(Procedure $procedure)
    {
        $this->authorize('view', $procedure);

        // Positionen, die im Template mehreren Personen zugeordnet sind
        // – dafür muss beim Start eine Auswahl getroffen werden.
        $multiPositions = $procedure->isTemplate()
            ? $this->procedureService->multiUserPositions($procedure)
            : collect();

        return view('procedure.start', [
            'procedure'      => $this->loadStepTree($procedure),
            'positions'      => $this->positions(),
            'users'          => User::orderBy('name')->get(),
            'canEdit'        => auth()->user()->can('manage procedures'),
            'multiPositions' => $multiPositions,
        ]);
    }

    public function startNow(Request $request, Procedure $procedure)
    {
        // Nur Admins können Prozesse starten
        if (!auth()->user()->can('manage procedures')) {
            return redirect()->back()->with([
                'type'=>'danger',
                'Meldung'=> 'Keine Berechtigung Prozesse zu starten.'
            ]);
        }

        if (!$procedure->isTemplate()) {
            return redirect()->back()->with([
                'type'    => 'danger',
                'Meldung' => 'Nur Vorlagen können gestartet werden.',
            ]);
        }

        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'started_at' => 'required|date',
        ]);

        // Positionen mit mehreren zugeordneten Personen ermitteln und Auswahl validieren
        $selectedInput = $request->input('selected_users', []);
        $selectedUsersByPosition = [];

        foreach ($this->procedureService->multiUserPositions($procedure) as $position) {
            $selectedIds = collect($selectedInput[$position->id] ?? [])
                ->map(fn ($id) => (int) $id)
                ->intersect($position->users->pluck('id'))
                ->values();

            if ($selectedIds->isEmpty()) {
                return redirect()->back()->withInput()->with([
                    'type' => 'danger',
                    'Meldung' => 'Bitte wählen Sie für die Position "'.$position->name.'" mindestens eine Person aus.',
                ]);
            }

            $selectedUsersByPosition[$position->id] = $selectedIds->all();
        }

        $startedProcedure = $this->procedureService->startFromTemplate(
            $procedure,
            $validated,
            auth()->id(),
            auth()->id(),
            $selectedUsersByPosition
        );

        return redirect('procedure/'.$startedProcedure->id.'/start')->with([
            'type'    => 'success',
            'Meldung' => 'Prozess wurde gestartet.',
        ]);
    }

    public function addStep(CreateStepRequest $request, Procedure $procedure)
    {
        // Nur Admins können Schritte hinzufügen
        if (!auth()->user()->can('manage procedures')) {
            return redirect()->back()->with([
                'type'=>'danger',
                'Meldung'=> 'Keine Berechtigung Schritte hinzuzufügen.'
            ]);
        }

        $step = new Procedure_Step($request->validated());
        $step->procedure_id = $procedure->id;
        $step->sort_order = (int) Procedure_Step::where('procedure_id', $procedure->id)
            ->where('parent', $step->parent)
            ->max('sort_order') + 1;

        if ($procedure->isTemplate()) {
            $step->endDate = null;
            $step->save();
        } else {
            // Laufender Prozess: Der Schritt ist sofort aktiv, wenn er keinen Vorgänger hat
            // oder der Vorgänger bereits erledigt ist. Sonst wird er erst mit Abschluss
            // des Vorgängers fällig (endDate + Mail setzt dann ProcedureStepService::complete).
            $parent = $step->parent ? Procedure_Step::find($step->parent) : null;
            $isActive = !$parent || $parent->done;

            if ($isActive && !$step->endDate) {
                $step->endDate = Carbon::today()->addDays((int) $step->durationDays);
            }
            $step->save();

            $position = Positions::with('users')->find($step->position_id);
            if ($position && $position->users->isNotEmpty()) {
                $step->users()->attach($position->users->pluck('id')->all());

                if ($isActive) {
                    $step->load('users', 'procedure');
                    $this->notifications->notifyStepAssigned($step, auth()->user());
                }
            }
        }

        return redirect()->back()->with([
            'type'=> 'success',
            'Meldung'=>'Schritt gespeichert',
        ]);
    }

    public function editStep(Procedure_Step $step)
    {
        // Nur Admins können Schritte bearbeiten
        if (!auth()->user()->can('manage procedures')) {
            abort(403, 'Keine Berechtigung Schritte zu bearbeiten.');
        }

        return view('procedure.editStep', [
            'step'      => $step,
            'procedure' => $step->procedure()->with('steps')->first(),
            'positions' => $this->positions(),
        ]);
    }

    public function storeStep(EditStepRequest $request, Procedure_Step $step)
    {
        // Nur Admins können Schritte speichern
        if (!auth()->user()->can('manage procedures')) {
            return redirect()->back()->with([
                'type'=>'danger',
                'Meldung'=> 'Keine Berechtigung Schritte zu bearbeiten.'
            ]);
        }

        $data = $request->validated();

        if (!empty($data['parent']) && $this->stepService->isDescendant($step, (int) $data['parent'])) {
            return redirect()->back()->withInput()->with([
                'type'    => 'danger',
                'Meldung' => 'Ein Schritt kann nicht auf sich selbst oder einen seiner Nachfolger folgen.',
            ]);
        }

        $oldPositionId = $step->position_id;
        $step->update($data);

        $positionChanged = (int) $step->position_id !== (int) $oldPositionId;
        $procedure = $step->procedure;

        // Positions-Änderung im Verlauf festhalten
        if ($positionChanged) {
            ProcedureStepHistory::logPositionChanged(
                $step->id,
                $oldPositionId ? Positions::find($oldPositionId) : null,
                $step->position,
                auth()->id()
            );
        }

        // Bei laufenden Prozessen: Zuweisungen bei Positionswechsel aktualisieren
        if (!$procedure->isTemplate() && $positionChanged) {
            $step->users()->detach();

            $users = $step->position?->users ?? collect();
            if ($users->isNotEmpty()) {
                $step->users()->attach($users->pluck('id')->all());

                // Nur benachrichtigen, wenn der Schritt bereits aktiv (fällig) ist.
                if (!$step->done && $step->endDate) {
                    $step->load('users', 'procedure');
                    $this->notifications->notifyStepAssigned($step, auth()->user());
                }
            }
        }

        // Weiterleitung: bei laufenden Prozessen zur Prozessansicht, sonst zur Vorlage
        $target = $procedure->isTemplate() ? '/edit' : '/start';

        return redirect(url('procedure/'.$procedure->id.$target))->with([
            'type' => 'success',
            'Meldung' => 'Schritt gespeichert.',
        ]);
    }

    public function done(Procedure_Step $step)
    {
        $currentUser = auth()->user();

        // Prüfe ob Nutzer die Permission zum Abschließen hat
        if (!$currentUser->can('complete own procedure steps') && !$currentUser->can('manage procedures')) {
            return redirect()->back()->with([
                'type' => 'danger',
                'Meldung' => 'Keine Berechtigung Schritte abzuschließen.'
            ]);
        }

        // Normale Nutzer dürfen nur ihre eigenen zugewiesenen Schritte abschließen
        if (!$currentUser->can('complete', $step)) {
            return redirect()->back()->with([
                'type' => 'danger',
                'Meldung' => 'Sie können nur Ihre eigenen zugewiesenen Schritte abschließen.'
            ]);
        }

        if ($step->procedure === null || $step->procedure->isTemplate() || $step->procedure->ended_at !== null) {
            return redirect()->back()->with([
                'type' => 'danger',
                'Meldung' => 'Schritte können nur in laufenden Prozessen erledigt werden.'
            ]);
        }

        if ($step->done) {
            return redirect()->back()->with([
                'type' => 'info',
                'Meldung' => 'Schritt ist bereits erledigt.'
            ]);
        }

        if ($this->stepService->complete($step, $currentUser)) {
            return redirect(url('procedure'))->with([
                'type'=> 'success',
                'Meldung' => 'Prozess vollständig abgeschlossen',
            ]);
        }

        return redirect()->back()->with([
            'type'=> 'success',
            'Meldung' => 'Schritt erledigt und nachfolgende Verantwortliche informiert',
        ]);
    }

    public function removeUser(Procedure_Step $step, User $user)
    {
        // Nur Admins können Benutzer entfernen
        if (!auth()->user()->can('manage procedures')) {
            return redirect()->back()->with([
                'type'=>'danger',
                'Meldung'=> 'Keine Berechtigung Benutzer zu entfernen.'
            ]);
        }

        $this->stepService->removeUser($step, $user, auth()->user());

        return redirect()->back()->with([
            'type' => 'success',
            'Meldung' => 'Benutzer entfernt.'
        ]);
    }

    public function addUser(Request $request)
    {
        // Nur Admins können Benutzer zuweisen
        if (!auth()->user()->can('manage procedures')) {
            return redirect()->back()->with([
                'type'=>'danger',
                'Meldung'=> 'Keine Berechtigung Benutzer zuzuweisen.'
            ]);
        }

        $data = $request->validate([
            'step' => 'required|integer|exists:procedure_steps,id',
            'person_id' => 'required|integer|exists:users,id'
        ]);

        $step = Procedure_Step::findOrFail($data['step']);

        if ($this->stepService->assignUsers($step, [(int) $data['person_id']], auth()->user()) === 0) {
            return redirect()->back()->with([
                'type' => 'info',
                'Meldung' => 'Benutzer ist bereits zugewiesen.'
            ]);
        }

        // Neu zugewiesene Person informieren, wenn der Schritt bereits fällig ist
        if (!$step->done && $step->endDate && !$step->procedure->isTemplate()) {
            $step->setRelation('users', User::whereKey($data['person_id'])->get());
            $step->load('procedure');
            $this->notifications->notifyStepAssigned($step, auth()->user());
        }

        return redirect()->back()->with([
            'type' => 'success',
            'Meldung' => 'Benutzer hinzugefügt.'
        ]);
    }

    /**
     * Scheduler (werktags): Sammel-Erinnerung an alle Personen mit fälligen Schritten.
     */
    public function remindStepMail(): void
    {
        $this->notifications->sendDueReminders();
    }

    public function endProcedure(Request $request, Procedure $procedure)
    {
        // Nur Admins können Prozesse beenden
        if (!auth()->user()->can('manage procedures')) {
            return redirect()->back()->with([
                'type'=>'danger',
                'Meldung'=> 'Keine Berechtigung Prozesse zu beenden.'
            ]);
        }

        if ($procedure->isTemplate() || $procedure->ended_at !== null) {
            return redirect()->back()->with([
                'type'    => 'danger',
                'Meldung' => 'Nur laufende Prozesse können beendet werden.',
            ]);
        }

        $validated = $request->validate(['reason' => 'nullable|string|max:255']);

        $this->procedureService->endProcedure($procedure, $validated['reason'] ?? null);

        return redirect(url('procedure'))->with([
            'type' => 'warning',
            'Meldung' => 'Prozess "'.$procedure->name.'" wurde beendet.'
        ]);
    }

    public function updateProcedure(Request $request, Procedure $procedure)
    {
        // Nur Admins können Prozesse bearbeiten
        if (!auth()->user()->can('manage procedures')) {
            return redirect()->back()->with([
                'type'=>'danger',
                'Meldung'=> 'Keine Berechtigung den Prozess zu bearbeiten.'
            ]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
        ]);

        $procedure->update($validated);

        // Vorlagen-Datensatz (procedure_templates) synchron halten
        if ($procedure->isTemplate() && $procedure->template_id) {
            ProcedureTemplate::whereKey($procedure->template_id)->update($validated);
        }

        return redirect()->back()->with([
            'type' => 'success',
            'Meldung' => 'Prozess wurde erfolgreich aktualisiert.'
        ]);
    }
}
