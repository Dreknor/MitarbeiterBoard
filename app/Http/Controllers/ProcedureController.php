<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateProcedureTemplateRequest;
use App\Http\Requests\CreateStepRequest;
use App\Http\Requests\EditStepRequest;
use App\Mail\newStepMail;
use App\Mail\StepErinnerungMail;
use App\Models\Absence;
use App\Models\Positions;
use App\Models\Procedure;
use App\Models\Procedure_Category;
use App\Models\Procedure_Step;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;


class ProcedureController extends Controller
{
    public function __construct()
    {
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
     * Prüft, ob der Nutzer Zugriff auf einen bestimmten Prozess hat
     */
    private function canAccessProcedure(Procedure $procedure, User $user = null): bool
    {
        $user = $user ?? auth()->user();

        // Admins mit manage procedures haben immer Zugriff
        if ($user->can('manage procedures')) {
            return true;
        }

        // Nutzer ohne view assigned procedures haben keinen Zugriff
        if (!$user->can('view assigned procedures')) {
            return false;
        }

        // Prüfe ob Nutzer in einem Schritt des Prozesses zugewiesen ist
        $hasAssignedStep = $procedure->steps()
            ->whereHas('users', function ($query) use ($user) {
                $query->where('users.id', $user->id);
            })
            ->exists();

        if ($hasAssignedStep) {
            return true;
        }

        // Prüfe ob Nutzer eine Position hat, die in einem Schritt verwendet wird
        if ($user->position_id) {
            $hasPositionStep = $procedure->steps()
                ->where('position_id', $user->position_id)
                ->exists();

            if ($hasPositionStep) {
                return true;
            }
        }

        return false;
    }

    /**
     * Prüft, ob der Nutzer einen Prozess bearbeiten darf
     */
    private function canEditProcedure(Procedure $procedure = null, User $user = null): bool
    {
        $user = $user ?? auth()->user();
        return $user->can('manage procedures');
    }


    public function delete(Procedure $procedure)
    {
        if (!auth()->user()->can('delete procedures')) {
            return redirect()->back()->with([
                'type'=>'danger',
                'Meldung'=> 'Keine Berechtigung.'
            ]);
        }

        $category = $procedure->category;

        $procedure->delete();

        if ($category->procedures->whereNull('started_at')->count() < 1) {
            $category->delete();
        }

        return redirect()->back()->with([
            'type'=>'warning',
            'Meldung'=> 'Prozess wurde gelöscht.'
        ]);
    }



    public function destroy(Procedure_Step $step){
        // Nur Admins können Schritte löschen
        if (!auth()->user()->can('manage procedures')) {
            return redirect()->back()->with([
                'type'=>'danger',
                'Meldung'=> 'Keine Berechtigung Schritte zu löschen.'
            ]);
        }

        try {
            $step->users()->detach();

            $step->childs()->update(['parent' => $step->parent]);
            $procedure = $step->procedure;
            $step->delete();

            if ($procedure->started_at == null) {
                return redirect(url('procedure/'.$procedure->id.'/edit'))->with([
                    'type'=>'warning',
                    'Meldung'=> 'Schritt wurde gelöscht.'
                ]);
            } else {
                return redirect()->back()->with([
                    'type'=>'warning',
                    'Meldung'=> 'Schritt wurde gelöscht.'
                ]);

            }

        } catch (\Exception $exception){
            return redirect()->back()->with([
               'type'=>'danger',
               'Meldung'=> 'Konnte nicht gelöscht werden.'
            ]);
        }


    }

    public function index_templates()
    {
        // Nur Admins können Templates verwalten
        if (!auth()->user()->can('manage procedures')) {
            abort(403, 'Keine Berechtigung Vorlagen zu verwalten.');
        }

        $proceduresTemplate = Procedure::where('started_at', null)->with('category')->get();

        $caregories = Cache::remember('categories', 60 * 5, function () {
            return Procedure_Category::all();
        });

        return view('procedure.template', [
            'proceduresTemplate'=>$proceduresTemplate,
            'categories'=>$caregories,
        ]);
    }
    public function index()
    {
        $user = auth()->user();

        if ($user->can('manage procedures')) {
            // Admins sehen alle laufenden Prozesse
            $procedures = Procedure::whereNotNull('started_at')
                ->whereNull('ended_at')
                ->get();
        } else {
            // Normale Nutzer sehen nur zugewiesene Prozesse
            $steps = $user->steps;
            $steps = $steps->unique('procedure_id');
            $procedureIds = $steps->pluck('procedure_id');

            // Zusätzlich Prozesse mit Steps für die Position des Nutzers
            if ($user->position_id) {
                $positionProcedureIds = Procedure_Step::where('position_id', $user->position_id)
                    ->whereHas('procedure', function($query) {
                        $query->whereNotNull('started_at')->whereNull('ended_at');
                    })
                    ->pluck('procedure_id')
                    ->unique();

                $procedureIds = $procedureIds->merge($positionProcedureIds)->unique();
            }

            $procedures = Procedure::whereIn('id', $procedureIds)
                ->whereNotNull('started_at')
                ->whereNull('ended_at')
                ->get();
        }

        $proceduresTemplate = Procedure::where('started_at', null)->with('category')->get();

        $caregories = Cache::remember('categories', 60 * 5, function () {
            return Procedure_Category::all();
        });

        return view('procedure.index', [
            'procedures'=>$procedures,
            'proceduresTemplate'=>$proceduresTemplate,
            'categories'=>$caregories,
        ]);
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

        $template = new Procedure($request->validated());
        $template->author_id = auth()->id();
        $template->save();

        Cache::forget('prozeduresTemplate');

        return redirect(url('procedure/'.$template->id.'/edit'))->with([
            'type'=>'success',
            'Meldung'=>'Prozess erstellt. Nun können Schritte hinzugefügt werden.',
        ]);
    }

    public function edit($procedure)
    {
        // Prozess direkt aus DB laden (kein Cache wegen häufigen Updates)
        $procedure = Procedure::find($procedure);

        if (!$procedure) {
            abort(404);
        }

        // Zugriffskontrolle
        if (!$this->canAccessProcedure($procedure)) {
            abort(403, 'Keine Berechtigung diesen Prozess zu sehen.');
        }

        $positions = Cache::remember('positions', 60 * 60, function () {
            return Positions::all();
        });

        return view('procedure.edit', [
            'procedure'=>$procedure->load(
                'steps',
                'steps.position',
                'steps.parent_rel',
                'steps.users',
                'steps.childs.position',
                'steps.childs.parent_rel',
                'steps.childs.users'
            ),
            'positions'=>$positions,
            'canEdit'=>$this->canEditProcedure($procedure),
        ]);
    }

    public function start($procedure)
    {
        // Prozess direkt aus DB laden (kein Cache wegen häufigen Updates)
        $procedure = Procedure::find($procedure);

        if (!$procedure) {
            abort(404);
        }

        // Zugriffskontrolle
        if (!$this->canAccessProcedure($procedure)) {
            abort(403, 'Keine Berechtigung diesen Prozess zu sehen.');
        }

        $positions = Cache::remember('positions', 60 * 60, function () {
            return Positions::all();
        });

        $users = User::all();

        view()->composer('procedure.stepStarted', function ($view) use ($users) {
            $view->with('users', $users);
        });

        return view('procedure.start', [
            'procedure'=>$procedure->load(
                'steps',
                'steps.position',
                'steps.parent_rel',
                'steps.users',
                'steps.childs.position',
                'steps.childs.parent_rel'
                , 'steps.childs.users'
            ),
            'positions'=>$positions,
            'users' => $users,
            'canEdit'=>$this->canEditProcedure($procedure),
        ]);
    }

    public function recursiveSteps($steps, $parent)
    {
        foreach ($steps as $step) {
            $newStep = $step->replicate();
            $newStep->procedure_id = $parent->procedure_id;
            $newStep->parent = $parent->id;
            $newStep->save();

            $users = $newStep->position->users;
            $newStep->users()->attach($users);

            if (count($step->childs) > 0) {
                $this->recursiveSteps($step->childs, $newStep);
            }
        }
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

        $startedProcedure = $procedure->replicate();
        $startedProcedure->name = $request->input('name');
        $startedProcedure->started_at = $request->input('started_at');
        $startedProcedure->author_id = auth()->id();
        $startedProcedure->save();

        $copySteps = [];

        foreach ($procedure->steps->where('parent', null) as $step) {
            $newStep = $step->replicate();
            $newStep->procedure_id = $startedProcedure->id;
            $newStep->endDate = $startedProcedure->started_at->addDays($startedProcedure->durationDays);
            $newStep->save();

            $users = $step->position->users;

            if ($users->contains('id', auth()->id())) {
                $newStep->users()->attach(auth()->user());
            } else {
                $newStep->users()->attach($users);
                foreach ($users as $user) {
                    Mail::to($user)->queue(new newStepMail(
                        $user->name,
                        Carbon::now()->addDays($newStep->durationDays)->format('d.m.Y'),
                        $newStep->name,
                        $newStep->procedure->name,
                        $step->procedure->id));
                }
            }




            $this->recursiveSteps($step->childs, $newStep);
        }

        return redirect('procedure/'.$startedProcedure->id.'/start');
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
        $step->save();

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

        $positions = Cache::remember('positions', 60 * 60, function () {
            return Positions::all();
        });

        $procedure = $step->procedure;

        return view('procedure.editStep', [
            'step'=>$step,
            'procedure'=>$procedure,
            'positions'=>$positions,
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

        $step->update($request->validated());

        return redirect(url('procedure/'.$step->procedure_id.'/edit'));
    }

    public function done(Procedure_Step $step)
    {
        if (!auth()->check()) {
            return redirect()->back()->with([
                'type' => 'danger',
                'Meldung' => 'Keine Berechtigung.'
            ]);
        }

        $currentUser = auth()->user();

        // Prüfe ob Nutzer die Permission zum Abschließen hat
        if (!$currentUser->can('complete own procedure steps') && !$currentUser->can('manage procedures')) {
            return redirect()->back()->with([
                'type' => 'danger',
                'Meldung' => 'Keine Berechtigung Schritte abzuschließen.'
            ]);
        }

        // Normale Nutzer dürfen nur ihre eigenen zugewiesenen Schritte abschließen
        $isAssigned = $step->users->contains('id', $currentUser->id);
        if (!$currentUser->can('manage procedures') && !$isAssigned) {
            return redirect()->back()->with([
                'type' => 'danger',
                'Meldung' => 'Sie können nur Ihre eigenen zugewiesenen Schritte abschließen.'
            ]);
        }

        $step->update(['done' => 1]);

        if (count(Procedure_Step::where('procedure_id', $step->procedure_id)->where('done', 0)->get()) < 1) {
            $step->procedure->update([
               'ended_at'   => Carbon::now(),
            ]);

            return redirect(url('procedure'))->with([
                'type'=> 'Success',
                'Meldung' => 'Prozess vollständig abgeschlossen',
            ]);
        }

        foreach ($step->childs as $child) {
            if (!$child->users->contains('id', auth()->id())) {
                foreach ($child->users as $user) {
                    Mail::to($user)->send(new newStepMail(
                        $user->name,
                        Carbon::now()->addDays($child->durationDays)->format('d.m.Y'),
                        $child->name,
                        $child->procedure->name,
                        $step->procedure->id));
                }
            }

            $child->update([
               'endDate' => Carbon::now()->addDays($child->durationDays),
            ]);
        }

        return redirect()->back()->with([
            'type'=> 'Success',
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

        $step->users()->detach($user);

        return redirect()->back();
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
            'step' => 'required|integer',
            'person_id' => 'required|integer'
        ]);

        $step = Procedure_Step::find($data['step']);
        if (!$step) {
            return redirect()->back()->with([
                'type' => 'danger',
                'Meldung' => 'Schritt nicht gefunden.'
            ]);
        }

        $user = User::find($data['person_id']);
        if (!$user) {
            return redirect()->back()->with([
                'type' => 'danger',
                'Meldung' => 'Benutzer nicht gefunden.'
            ]);
        }

        // Verhindere doppelte Zuweisung
        if ($step->users->contains('id', $user->id)) {
            return redirect()->back()->with([
                'type' => 'info',
                'Meldung' => 'Benutzer ist bereits zugewiesen.'
            ]);
        }

        $step->users()->attach($user->id);

        return redirect()->back()->with([
            'type' => 'success',
            'Meldung' => 'Benutzer hinzugefügt.'
        ]);
    }

    public function remindStepMail(): void
    {
        $usersWithPendingSteps = $this->getUsersWithPendingSteps();

        foreach ($usersWithPendingSteps as $user) {
            if (!$user->hasAbsence(Carbon::now())) {
                $pendingSteps = $this->formatPendingSteps($user);
                $this->sendReminderEmail($user, $pendingSteps);
            }
        }
    }

    private function getUsersWithPendingSteps(): Collection
    {
        return User::whereHas('steps', function (Builder $query) {
            $query->where('endDate', '<=', Carbon::now())
                ->where('done', 0);
        })->get();
    }

    private function formatPendingSteps(User $user): array
    {
        $steps = $user->steps()
            ->with('procedure')
            ->where('endDate', '<=', Carbon::now())
            ->where('done', 0)
            ->get();

        return $steps->map(function ($step) {
            return [
                'endDate' => $step->endDate->format('d.m.Y'),
                'procedureName' => $step->procedure->name,
                'procedureId' => $step->procedure_id,
                'stepName' => $step->name,
                'stepId' => $step->id
            ];
        })->toArray();
    }

    private function sendReminderEmail(User $user, array $pendingSteps): void
    {
        Log::debug('Prozesse: Erinnerungsemail senden', ['user' => $user, 'pendingSteps' => $pendingSteps]);;
        Mail::to($user)->queue(new StepErinnerungMail($user->name, $pendingSteps));
    }

    // Public wrapper so the reminder can be triggered for a single user from CLI (Artisan) or elsewhere.
    public function sendReminderEmailForUser(User $user): void
    {
        // If the user is currently absent, do not send a reminder.
        if (method_exists($user, 'hasAbsence') && $user->hasAbsence(Carbon::now())) {
            Log::info('Prozesse: Erinnerung nicht gesendet, Benutzer abwesend', ['user' => $user->id]);
            return;
        }

        $pendingSteps = $this->formatPendingSteps($user);

        if (empty($pendingSteps)) {
            Log::info('Prozesse: Keine ausstehenden Schritte für Benutzer', ['user' => $user->id]);
            return;
        }

        $this->sendReminderEmail($user, $pendingSteps);
    }

    public function endProcedure(Procedure $procedure){
        // Nur Admins können Prozesse beenden
        if (!auth()->user()->can('manage procedures')) {
            return redirect()->back()->with([
                'type'=>'danger',
                'Meldung'=> 'Keine Berechtigung Prozesse zu beenden.'
            ]);
        }

        $procedure->steps()->where('done', '=',0)->update(['done' => 1]);
        $procedure->update([
            'ended_at' => Carbon::now()
        ]);

        return redirect()->back()->with([
            'type' => 'warning',
            'Meldung' => 'Prozess'. $procedure->name.' wurde beendet'
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
            'description' => 'nullable|string',
        ]);

        // Update durchführen
        $procedure->update($validated);

        return redirect()->back()->with([
            'type' => 'success',
            'Meldung' => 'Prozess wurde erfolgreich aktualisiert.'
        ]);
    }
}
