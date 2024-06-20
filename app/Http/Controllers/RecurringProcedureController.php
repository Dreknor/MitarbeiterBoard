<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRecurringProcedureRequest;
use App\Mail\newStepMail;
use App\Models\Positions;
use App\Models\Procedure;
use App\Models\RecurringProcedure;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class RecurringProcedureController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $procedures = RecurringProcedure::all();
        $procedures->load('procedure', 'procedure.category');

        $monate = [
            1 => 'Januar',
            2 => 'Februar',
            3 => 'März',
            4 => 'April',
            5 => 'Mai',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'August',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Dezember',
        ];

        return view('procedure.recurring', [
            'procedures' => $procedures,
            'templates' => Procedure::whereNull('started_at')->get(),
            'monate' => $monate,
        ]);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRecurringProcedureRequest $request)
    {

        if ($request->faelligkeit_typ == 'datum' && $request->month == null) {
            return redirect()->back()->with([
                'Meldung' => 'Bitte wählen Sie einen Monat aus.',
                'type' => 'danger'
            ]);
        } elseif (($request->faelligkeit_typ == 'vor_ferien' || $request->faelligkeit_typ == 'nach_ferien') && $request->wochen == null) {
            return redirect()->back()->with([
                'Meldung' => 'Bitte geben Sie die Anzahl der Wochen an.',
                'type' => 'danger'
            ]);
        } elseif (($request->faelligkeit_typ == 'vor_ferien' || $request->faelligkeit_typ == 'nach_ferien') && $request->ferien == null) {
            return redirect()->back()->with([
                'Meldung' => 'Bitte wählen Sie die Ferien aus.',
                'type' => 'danger'
            ]);
        }

        RecurringProcedure::create($request->validated());

        return redirect()->back()->with([
            'Meldung' => 'Wiederkehrender Prozess wurde erfolgreich erstellt.',
            'type' => 'success'
        ]);
    }



    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RecurringProcedure $recurringProcedure)
    {
        if (auth()->user()->cannot('delete procedures')) {
            return redirect()->back()->with([
                'Meldung' => 'Sie haben keine Berechtigung, diesen wiederkehrenden Prozess zu löschen.',
                'type' => 'danger'
            ]);
        }
        $recurringProcedure->delete();

        return redirect()->back()->with([
            'Meldung' => 'Wiederkehrender Prozess wurde erfolgreich gelöscht.',
            'type' => 'success'
        ]);
    }

    /**
     * Start the specified resource.
     */
    public function start(RecurringProcedure $recurringProcedure, $redirect = true)
    {


        $startedProcedure = $recurringProcedure->procedure->replicate();
        $startedProcedure->name = $recurringProcedure->name . ' - ' . now()->format('Y');
        $startedProcedure->started_at = now();
        $startedProcedure->save();


        foreach ($recurringProcedure->procedure->steps->where('parent', null) as $step) {
            $newStep = $step->replicate();
            $newStep->procedure_id = $startedProcedure->id;
            $newStep->endDate = $startedProcedure->started_at->addDays($startedProcedure->durationDays);
            $newStep->save();

            $users = $step->position->users;

            $newStep->users()->attach($users);
            foreach ($users as $user) {
                Mail::to($user)->queue(new newStepMail(
                    $user->name,
                    Carbon::now()->addDays($newStep->durationDays)->format('d.m.Y'),
                    $newStep->name,
                    $newStep->procedure->name,
                    $step->procedure->id));
            }

            $controller = new ProcedureController();

            $controller->recursiveSteps($step->childs, $newStep);
        }


        if ($redirect) {
            return redirect(url('procedure/'.$startedProcedureID.'/start'))->with([
                'Meldung' => 'Wiederkehrender Prozess wurde erfolgreich gestartet.',
                'type' => 'success'
            ]);
        }

    }
}
