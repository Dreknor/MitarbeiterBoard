<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use App\Http\Requests\personal\CreateRosterCheckRequest;
use App\Models\personal\RosterCheck;
use App\Models\personal\RosterEvents;
use App\Models\personal\WorkingTime;

class RosterCheckController extends Controller
{
    public function storeCheck(CreateRosterCheckRequest $request)
    {

        foreach ($request->weekday as $weekday) {
            $check = new RosterCheck($request->validated());
            $check->weekday = $weekday;

            if ($request->field_name != 'event'){
                $check->type = WorkingTime::class;
            } else {
                $check->type = RosterEvents::class;
            }

            if ($request->field_name == 'function' or $request->field_name == 'event') {
                $check->operator = "=";
            }

            $check->save();
        }


        return redirectBack('success', 'Check wurde erstellt');
    }
}
