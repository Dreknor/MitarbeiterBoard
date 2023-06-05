<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Protocol;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    public function globalSearch(){
        return view('search.global');
    }


    public function searchGlobal( Request $request){
        // get the search term
        $text = '%'.$request->input('text').'%';
        $text_protocol = '*'.$request->input('text').'*';

        $results['Nachrichten'] = auth()->user()->posts()
            ->where('header', 'LIKE', $text)
            ->orWhere('text', 'LIKE', $text)
            ->get()->unique();

        foreach (auth()->user()->groups_rel as $group){
            $results[$group->name] = DB::table('themes')
                ->where('group_id', $group->id)
                ->where(function ($query) use ($text) {
                    $query->where('theme', 'Like', $text)
                        ->orWhere('goal', 'Like', $text)
                        ->orWhere('information', 'Like', $text);
                })
                ->orderByDesc('date')
                ->get();

            // search the protocols table

            $resultsProtocol = Protocol::whereHas('theme', function ($query) use ($group){
                return $query->where('group_id', $group->id);
            })
                ->whereRaw('MATCH (protocol) AGAINST (? IN BOOLEAN MODE)', [$text_protocol])
                ->get();

            if ($resultsProtocol->count() > 0) {
                foreach ($resultsProtocol as $protocol) {
                    $themes[] = $protocol->theme;
                }
                $results['Protokolle_'.$group->name] = $themes;
            }
        }

        // return the results
        return response()->json($results);
    }

    public function search($groupname, Request $request)
    {
        $group = Group::where('name', $groupname)->first();

        if (! auth()->user()->groups()->contains($group)) {
            return response()->json();
        }

        // get the search term
        $text = '%'.$request->input('text').'%';

        // search the themes table
        $results = DB::table('themes')
            ->where('group_id', $group->id)
            ->where(function ($query) use ($text) {
                $query->where('theme', 'Like', $text)
                    ->orWhere('goal', 'Like', $text)
                    ->orWhere('information', 'Like', $text);
            })
            ->orderByDesc('date')
            ->get();

        // search the protocols table
        $text = '*'.$request->input('text').'*';

        $resultsProtocol = Protocol::with(['theme' => function ($query) use ($group) {
            $query->where('group_id', $group->id);
        }])->whereRaw('MATCH (protocol) AGAINST (? IN BOOLEAN MODE)', [$text])
            ->get();

        if ($resultsProtocol->count() > 0) {
            foreach ($resultsProtocol as $protocol) {
                $theme = $protocol->theme;
                $results->push($theme);
            }
        }

        $results->sortByDesc('date');

        // return the results
        return response()->json($results);
    }

    public function show($groupname)
    {
        return view('search.search');
    }
}
