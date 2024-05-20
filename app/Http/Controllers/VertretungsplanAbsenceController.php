<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateVertretungsplanAbsenceRequest;
use App\Models\User;
use App\Models\VertretungsplanAbsence;
use Carbon\Carbon;
use Illuminate\Http\Request;

class VertretungsplanAbsenceController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:edit vertretungen');
    }

    public function index()
    {
        $absences = VertretungsplanAbsence::whereDate('end_date', '>=', Carbon::today() )->with('user')->get();
        return view('vertretungsplan.absences.index', [
            'absences' => $absences,
            'users' => User::whereNotNull('kuerzel')->get()
        ]);
    }

    public function store(CreateVertretungsplanAbsenceRequest $request)
    {


        VertretungsplanAbsence::create($request->validated());


        return redirect()->route('vertretungsplan.absences.index');
    }

    public function destroy(VertretungsplanAbsence $absence)
    {
        $absence->delete();
        return redirect()->route('vertretungsplan.absences.index');
    }
}
