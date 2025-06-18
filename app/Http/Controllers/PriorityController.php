<?php

namespace App\Http\Controllers;

use App\Http\Requests\PriorityRequest;
use App\Models\Priority;
use App\Models\Theme;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PriorityController extends Controller
{
    public function store(Request $request)
    {

        $request->validate([
            'theme' => 'required|exists:themes,id',
            'priority' => 'required|integer|min:1|max:100',

        ]);

        $priority = Priority::firstOrCreate([
            'creator_id' => auth()->id(),
            'theme_id'   => $request->theme,
        ], [
            'priority'  => $request->priority,
        ]);

        //$priority->save();

        $theme = Theme::find($request->theme);

        return response()->json([
            'theme_id'  => $request->theme,
            'priority'  => $theme->priority,
            'day'   => $theme->date->format('d.m.Y')
        ]);
    }

    public function delete(Request $request, $theme){
       Priority::where([
            'theme_id' => $theme,
            'creator_id' => auth()->id()
        ])->delete();

       return redirect()->back();
    }
}
