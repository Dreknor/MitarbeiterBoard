<?php

namespace App\Http\Controllers;

use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    public function search($groupname, Request $request) {

        $group = Group::where('name', $groupname)->first();

        if (!auth()->user()->groups->contains($group)){
            return response()->json();
        }

        // get the search term
        $text = '%'.$request->input('text').'%';

        // search the members table
        $results = DB::table('themes')
            ->where('group_id', $group->id)
            ->where(function ($query) use ($text){
                $query->where('theme', 'Like', $text)
                    ->orWhere('goal', 'Like', $text)
                    ->orWhere('information', 'Like', $text);
            })->get();


        // return the results
        return response()->json($results);
    }

    public function show($groupname){
        return view('search.search');
    }
}
