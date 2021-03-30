<?php

namespace App\Http\Controllers;

use App\Http\Requests\PriorityRequest;
use App\Models\Priority;
use Illuminate\Http\Request;

class PriorityController extends Controller
{
    public function store(Request $request)
    {
        $priority = Priority::firstOrCreate([
            'creator_id' => auth()->id(),
            'theme_id'   => $request->theme,
        ], [
            'priority'  => $request->priority,
        ]);

        //$priority->save();

        return response()->json([

            'theme_id'  => $request->theme,
            'priority'  => $request->priority,
        ]);
    }
}
