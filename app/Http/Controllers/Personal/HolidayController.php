<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use App\Http\Requests\personal\createHolidayRequest;
use App\Models\Group;
use App\Models\personal\Holiday;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class HolidayController extends Controller
{

    /**
     * Display a listing of the resource.
     */
    public function index( $month = null, $year = null)
    {
        if (!auth()->user()->can('has holidays')){
            return redirectBack('danger', 'Sie haben keine Berechtigung für diese Aktion.');
        }


        if ($month == null or $year == null){
            $startMonth = Carbon::now()->startOfMonth();
            $endMonth = Carbon::now()->endOfMonth();
        } else {
            $startMonth = Carbon::createFromFormat('m-Y', $month.'-'.$year)->startOfMonth();
            $endMonth = Carbon::createFromFormat('m-Y', $month.'-'.$year)->endOfMonth();
        }


        if (settings('show_holidays') == 1 or auth()->user()->can('approve holidays'))
        {
            $holidays = Holiday::query()
                ->whereBetween('start_date', [$startMonth, $endMonth])
                ->orWhereBetween('end_date', [$startMonth, $endMonth])
                ->get();
        }else{
            $holidays = Holiday::where('employe_id', auth()->id())
                ->whereBetween('start_date', [$startMonth, $endMonth])
                ->orWhereBetween('end_date', [$startMonth, $endMonth])
                ->get();
        }
        $users = collect([]);
        if (auth()->user()->can('approve holidays')){
            $usersAll = User::permission('has holidays')->with('groups_rel')->get();

            foreach ($usersAll as $user){
                if ($user->employments_date($startMonth->startOfMonth(), $endMonth->endOfMonth())->count() > 0){
                    $users->push($user);
                } elseif ($user->employments->count() == 0){
                    $users->push($user);
                }
            }
        } elseif( settings('show_holidays', 'holidays') == 1) {

            $usersAll = User::query()
                ->permission('has holidays')
                ->with('groups_rel')
                ->get();

            foreach ($usersAll as $user){
                $groups = auth()->user()->groups_rel;

                if ($user->groups_rel->intersect($groups)->count() > 0){
                    $users->push($user);
                }

            }
        } else {
            $users = collect([auth()->user()]);
        }

        foreach ($holidays as $holiday){
            if ($holiday->days == null) {
                $holiday->update([
                    'days' => workdays($holiday->start_date, $holiday->end_date)
                ]);
            }
        }

        return view('personal.holidays.index', [
            'holidays' => $holidays,
            'month' => $startMonth,
            'users' => $users->sortBy('name'),
            'unapproved' => auth()->user()->can('approve holidays') ? Holiday::where('approved', false)->where('rejected', false)->get() : []
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return redirect()->back();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(createHolidayRequest $request)
    {

        if(!auth()->user()->can('has holidays')){
            return redirectBack('danger', 'Sie haben keine Berechtigung für diese Aktion.');
        }

        if ($request->employe_id != auth()->id() and !auth()->user()->can('approve holidays')){
            return redirectBack('danger', 'Sie haben keine Berechtigung für diese Aktion.');
        }

        if ($request->end_date < $request->start_date){
            return redirectBack('danger', 'Enddatum kann nicht vor dem Startdatum liegen.');
        }

        if ($request->employe_id != 'all'){
            $user = User::findOrFail($request->employe_id);

            if ($user->hasHoliday(Carbon::createFromFormat('Y-m-d',$request->start_date), Carbon::createFromFormat('Y-m-d',$request->end_date))){
                return redirectBack('danger', 'Der Mitarbeiter hat bereits Urlaub an diesem Tag.');
            }

            $date = Carbon::createFromFormat('Y-m-d',$request->start_date);

            $start = Carbon::createFromFormat('Y-m-d',$request->start_date);
            $end = Carbon::createFromFormat('Y-m-d',$request->end_date);

            if ($start->year != $end->year){

                $user->holidays()->create([
                    'start_date' => $start,
                    'end_date' => $start->copy()->endOfYear(),
                    'approved' => auth()->user()->can('approve holidays'),
                    'approved_by' => auth()->user()->can('approve holidays') ? auth()->id() : null,
                    'approved_at' => auth()->user()->can('approve holidays') ? Carbon::now() : null,
                    'days' => workdays(Carbon::createFromFormat('Y-m-d',$start), Carbon::createFromFormat('Y-m-d',$start->copy()->endOfYear()))
                ],
                [
                    'start_date' => $end->copy()->startOfYear(),
                    'end_date' => $end,
                    'approved' => auth()->user()->can('approve holidays'),
                    'approved_by' => auth()->user()->can('approve holidays') ? auth()->id() : null,
                    'approved_at' => auth()->user()->can('approve holidays') ? Carbon::now() : null,
                    'days' => workdays(Carbon::createFromFormat('Y-m-d',$end->copy()->startOfYear()), Carbon::createFromFormat('Y-m-d',$end))
                ]);
            } else {
                $user->holidays()->create([
                    'start_date' => $request->start_date,
                    'end_date' => $request->end_date,
                    'approved' => auth()->user()->can('approve holidays'),
                    'approved_by' => auth()->user()->can('approve holidays') ? auth()->id() : null,
                    'approved_at' => auth()->user()->can('approve holidays') ? Carbon::now() : null,
                    'days' => workdays(Carbon::createFromFormat('Y-m-d', $request->start_date), Carbon::createFromFormat('Y-m-d', $request->end_date))
                ]);
            }
            return redirect(url('holidays/'.$date->month.'/'.$date->year))
                ->with([
                    'type' => 'success',
                    'Meldung' => 'Urlaub wurde erfolgreich beantragt.'
                ]);
        } else {
            $date = Carbon::createFromFormat('Y-m-d',$request->start_date);

            $cookie = $request->cookie('group');
            $group = Group::query()->where('name', $cookie)->first();
            if ($group != null and $cookie != null){
                $users = User::permission('has holidays')->whereHas('groups_rel', function ($query) use ($group){
                    $query->where('name', $group);
                })->get();

            } else {
                $users = User::permission('has holidays')->get();
            }

            $holidays = [];
            foreach ($users as $user){

                $start = Carbon::createFromFormat('Y-m-d',$request->start_date);
                $end = Carbon::createFromFormat('Y-m-d',$request->end_date);

                if (!$user->hasHoliday(Carbon::createFromFormat('Y-m-d',$request->start_date), Carbon::createFromFormat('Y-m-d',$request->end_date))) {
                    if ($start->year != $end->year) {

                        $user->holidays()->create([
                            'start_date' => $start,
                            'end_date' => $start->copy()->endOfYear(),
                            'approved' => auth()->user()->can('approve holidays'),
                            'approved_by' => auth()->user()->can('approve holidays') ? auth()->id() : null,
                            'approved_at' => auth()->user()->can('approve holidays') ? Carbon::now() : null,
                            'days' => workdays(Carbon::createFromFormat('Y-m-d', $start), Carbon::createFromFormat('Y-m-d', $start->copy()->endOfYear()))
                        ],
                            [
                                'start_date' => $end->copy()->startOfYear(),
                                'end_date' => $end,
                                'approved' => auth()->user()->can('approve holidays'),
                                'approved_by' => auth()->user()->can('approve holidays') ? auth()->id() : null,
                                'approved_at' => auth()->user()->can('approve holidays') ? Carbon::now() : null,
                                'days' => workdays(Carbon::createFromFormat('Y-m-d', $end->copy()->startOfYear()), Carbon::createFromFormat('Y-m-d', $end))
                            ]);
                    } else {
                        $user->holidays()->create([
                            'start_date' => $request->start_date,
                            'end_date' => $request->end_date,
                            'approved' => auth()->user()->can('approve holidays'),
                            'approved_by' => auth()->user()->can('approve holidays') ? auth()->id() : null,
                            'approved_at' => auth()->user()->can('approve holidays') ? Carbon::now() : null,
                            'days' => workdays(Carbon::createFromFormat('Y-m-d', $request->start_date), Carbon::createFromFormat('Y-m-d', $request->end_date))
                        ]);
                    }
                }

            }

            Holiday::insert($holidays);

            return redirect(url('holidays/'.$date->month.'/'.$date->year))->with([
                'type' => 'success',
                'Meldung' => 'Urlaub wurde für alle erfolgreich eingetragen.']);
        }



    }

    /**
     * Display the specified resource.
     */
    public function show(Holiday $holiday)
    {
        return redirect()->back();
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Holiday $holiday)
    {
        return redirect()->back();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Holiday $holiday)
    {
        if (!auth()->user()->can('approve holidays')){
            return redirectBack('danger', 'Sie haben keine Berechtigung für diese Aktion.');
        }

        if ($request->action == 'rejected'){
            $holiday->update([
                'rejected' => true,
                'approved' => false,
                'approved_by' => auth()->id(),
                'approved_at' => Carbon::now(),
            ]);

            return redirectBack('success', 'Urlaub wurde abgelehnt.');
        }

        $holiday->update([
            'approved' => true,
            'approved_by' => auth()->id(),
            'approved_at' => Carbon::now(),
        ]);

        return redirectBack('success', 'Urlaub wurde erfolgreich genehmigt.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function delete(Holiday $holiday)
    {
        if ($holiday->employe_id != auth()->id() and !auth()->user()->can('approve holidays')){
            return redirectBack('danger', 'Sie haben keine Berechtigung für diese Aktion.');
        }

        if ($holiday->start_date->isPast()){
            return redirectBack('danger', 'Urlaub kann nicht mehr gelöscht werden.');
        }

        $holiday->delete();


        return redirectBack('success', 'Urlaub wurde erfolgreich gelöscht.');
    }

    public function export($year = null, $group = null){

        if (!auth()->user()->can('approve holidays')){
            return redirectBack('danger', 'Sie haben keine Berechtigung für diese Aktion.');
        }

        if ($year == null){
            $startMonth = Carbon::now()->startOfYear();
            $endMonth = Carbon::now()->endOfYear();
        } else {
            $startMonth = Carbon::createFromFormat('Y', $year)->startOfYear();
            $endMonth = Carbon::createFromFormat('Y', $year)->endOfYear();
        }

            $holidays = Holiday::query()
                ->whereBetween('start_date', [$startMonth, $endMonth])
                ->orWhereBetween('end_date', [$startMonth, $endMonth])
                ->get();


            if ($group != null){
                $users = User::permission('has holidays')->whereHas('groups_rel', function ($query) use ($group){
                    $query->where('name', $group);
                })->get();

            } else {
                $users = User::permission('has holidays')->get();
            }

            $pdf = \PDF::loadView('personal.holidays.export', [
                        'holidays' => $holidays,
                        'monthStart' => $startMonth,
                        'users' => $users->sortBy('name'),
                    ])
                    ->setOption(
                        'orientation',
                        'landscape')
                    ->setOption(
                        'margin-bottom',
                        10)
                    ->setOption(
                        'margin-top',
                        10)
                    ->setOption(
                        'margin-left',
                        10)
                    ->setOption(
                        'margin-right',
                        10);

        return $pdf->download('urlaub_'.$year.'.pdf');
    }

    public function updateDays(Holiday $holiday){
        $holiday->update([
            'days' => workdays($holiday->start_date, $holiday->end_date)
        ]);
    }

    public function destroy(Holiday $holiday)
    {
        if ($holiday->employe_id != auth()->id() and !auth()->user()->can('approve holidays')){
            return redirectBack('danger', 'Sie haben keine Berechtigung für diese Aktion.');
        }

        if ($holiday->start_date->isPast()){
            return redirectBack('danger', 'Urlaub kann nicht mehr gelöscht werden.');
        }

        $holiday->delete();

        return redirectBack('success', 'Urlaub wurde erfolgreich gelöscht.');
    }
}
