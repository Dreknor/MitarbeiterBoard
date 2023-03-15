<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateWikiSiteRequest;
use App\Http\Requests\SearchWikiSiteRequest;
use App\Models\WikiSite;
use Illuminate\Http\Request;

class WikiController extends Controller
{
    public function index($slug = "Start", $version=null){
        if (is_null($version)){
            $site = WikiSite::where('title', str_replace('-', ' ', $slug))->latest()->first();
            $akt_site = null;
        } else {
            $site = WikiSite::where('title', str_replace('-', ' ', $slug))->where('id', $version)->first();
            $akt_site = WikiSite::where('title', str_replace('-', ' ', $slug))->latest()->first();
        }

        if (is_null($site)){
            if (auth()->user()->can('edit wiki') and is_null($version)) {
                return redirect(url('wiki/create/'.$slug));
            }  else {
                return redirect()->back()->with(['type' => 'warning', 'Meldung' => 'Seite nicht gefunden']);
            }
        }
        return view('wiki.site')->with([
           'site' => $site,
            'akt_site' => $akt_site
        ]);
    }

    public function create($slug){
        $site = WikiSite::where('title', str_replace('-', ' ', $slug))->latest()->first();
        $sites = WikiSite::all()->unique('slug');
        return view('wiki.create')->with([
            'site' => (!is_null($site))? $site : new WikiSite(['title' => str_replace('-', ' ', $slug)]),
            'sites' => $sites
        ]);
    }

    public function store(CreateWikiSiteRequest $request){
        $site = new WikiSite($request->validated());
        $site->author_id = auth()->id();

        $site->save();

        if ($request->hasFile('files')) {
            $files = $request->files->all();
            foreach ($files['files'] as $file) {
                $site
                    ->addMedia($file)
                    ->toMediaCollection();
            }
        }

        if ($site->previous_version != $site->id and $site->previous()->first()?->getMedia()->count() > 0){
            foreach ($site->previous()->first()->getMedia() as $media){
                $site
                    ->copyMedia($media->getPath())
                    ->toMediaCollection();
            }
        }


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
