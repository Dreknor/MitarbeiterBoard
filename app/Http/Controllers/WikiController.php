<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateWikiSiteRequest;
use App\Http\Requests\SearchWikiSiteRequest;
use App\Models\WikiSite;
use Illuminate\Http\Request;

class WikiController extends Controller
{
    public function index($slug = "Start"){
        $site = WikiSite::where('title', str_replace('-', ' ', $slug))->latest()->first();

        if (is_null($site)){
            if (auth()->user()->can('edit wiki')) {
                return redirect(url('wiki/create/'.$slug));
            }  else {
                return redirect()->back()->with(['type' => 'warning', 'Meldung' => 'Seite nicht gefunden']);
            }
        }
        return view('wiki.site')->with([
           'site' => $site
        ]);
    }

    public function create($slug){
        $site = WikiSite::where('title', str_replace('-', ' ', $slug))->latest()->first();

        return view('wiki.create')->with([
            'site' => (!is_null($site))? $site : new WikiSite(['title' => str_replace('-', ' ', $slug)])
        ]);
    }

    public function store(CreateWikiSiteRequest $request){
        $site = new WikiSite($request->validated());
        $site->author_id = auth()->id();

        $site->save();

        return redirect(url('wiki/'.$site->slug));
    }

    public function new (Request $request){
       return $this->create($request->title);
    }

    public function search(SearchWikiSiteRequest $request){
        $sites = WikiSite::search($request->search)->get()->unique('slug');

        return view('wiki.search')->with(['sites' => $sites]);
    }

    public function all_sites(){
        $sites = WikiSite::all()->groupBy('slug');
        $letters = ['a','b','c','d','e','f','g','h','i','j', 'k', 'l', 'm','n','o','p','q','r','s','t','u','v','w','x','y','z'];
        return view('wiki.all')->with([
            'sites' => $sites,
            'letters' => $letters
        ]);

    }
}
