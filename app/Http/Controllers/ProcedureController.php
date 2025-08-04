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
use App\Support\Collection;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;


class ProcedureController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:view procedures');
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
        $steps = auth()->user()->steps;
        $steps = $steps->unique('procedure_id');

        $procedures = Procedure::whereIn('id', $steps->pluck('procedure_id'))->whereNotNull('started_at')->whereNull('ended_at')->get();

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
        $positions = Cache::remember('positions', 60 * 60, function () {
            return Positions::all();
        });
        $procedure = Cache::remember('procedure'.$procedure, 60 * 60, function () use ($procedure) {
            return Procedure::find($procedure);
        });

        return view('procedure.edit', [
            'procedure'=>$procedure->load('steps', 'steps.position', 'steps.childs.position'),
            'positions'=>$positions,
        ]);
    }

    public function start($procedure)
    {
        $positions = Cache::remember('positions', 60 * 60, function () {
            return Positions::all();
        });
        $procedure = Cache::remember('procedure'.$procedure, 60 * 60, function () use ($procedure) {
            return Procedure::find($procedure);
        });

        $users = User::all();

        view()->composer('procedure.stepStarted', function ($view) use ($users) {
            $view->with('users', $users);
        });

        return view('procedure.start', [
            'procedure'=>$procedure->load('steps', 'steps.position', 'steps.childs.position'),
            'positions'=>$positions,
            'users' => $users,

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
        $step->update($request->validated());

        return redirect(url('procedure/'.$step->procedure_id.'/edit'));
    }

    public function done(Procedure_Step $step)
    {
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
        $step->users()->detach($user);

        return redirect()->back();
    }

    public function addUser(Request $request)
    {
        $step = Procedure_Step::where('id', $request->input('step'))->first();

        $step->users()->attach($request->input('person_id'));

        return redirect()->back();
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
        Mail::to($user)->queue(new StepErinnerungMail($user->name, $pendingSteps));
    }

    public function endProcedure(Procedure $procedure){
        $procedure->steps()->where('done', '=',0)->update(['done' => 1]);
        $procedure->update([
            'ended_at' => Carbon::now()
        ]);

        return redirect()->back()->with([
            'type' => 'warning',
            'Meldung' => 'Prozess'. $procedure->name.' wurde beendet'
        ]);
    }
}
