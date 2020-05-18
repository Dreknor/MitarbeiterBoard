<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request) {
        // get the search term
        $text = $request->input('text');

        // search the members table
        $results = DB::table('themes')->where('theme', 'Like', $text)->orWhere('goal', 'Like', $text)->orWhere('information', 'Like', $text)->get();


        // return the results
        return response()->json($results);
    }

    public function show(){
        return view('search.search');
    }
}
