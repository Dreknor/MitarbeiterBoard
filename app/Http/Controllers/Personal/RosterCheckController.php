<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use App\Http\Requests\personal\CreateRosterCheckRequest;
use App\Models\personal\RosterCheck;
use App\Models\personal\WorkingTime;

class RosterCheckController extends Controller
{
    public function storeCheck(CreateRosterCheckRequest $request)
    {

        foreach ($request->weekday as $weekday) {
            $check = new RosterCheck($request->validated());
            $check->weekday = $weekday;
            $check->type = WorkingTime::class;

            if ($request->field_name == 'function') {
                $check->operator = "=";
            }

            $check->save();
        }


        return redirectBack('success', 'Check wurde erstellt');
    }
}
