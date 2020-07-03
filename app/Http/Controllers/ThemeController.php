<?php

namespace App\Http\Controllers;

use App\Http\Requests\createThemeRequest;
use App\Models\Group;
use App\Models\Protocol;
use App\Models\Theme;
use App\Models\Type;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ThemeController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return View|RedirectResponse
     */
    public function index($groupname)
    {
        $group = Group::where('name', $groupname)->first();

        if (!auth()->user()->groups->contains($group)){
           return redirect()->back()->with([
              'type'    => 'warning',
              'Meldung' => "Kein Zugriff auf diese Gruppe"
           ]);
        }

        $themes=$group->themes()->where('completed', 0)->get();
        $themes->load('priorities', 'ersteller', 'type', 'protocols');
        $themes = $themes->sortBy('date')->groupBy(function($item){
            return  $item->date->format('d.m.Y');
        });

        return view('themes.index',[
           'themes' => $themes,
        ]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return View
     */
    public function archive($groupname)
    {
        $group = Group::where('name', $groupname)->first();

        if (!auth()->user()->groups->contains($group)){
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => "Kein Zugriff auf diese Gruppe"
            ]);
        }

        $themes=$group->themes()->where('completed', 1)->orderByDesc('date')->get();
        $themes->load('ersteller','type', 'priorities');

        $themes = $themes->groupBy(function($item){
            return $item->date->format('d.m.Y');
        });

        $themes = new \Illuminate\Support\Collection($themes);


        return view('themes.archive',[
           'themes' => $themes->paginate( 5 )
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('themes.create',[
            'types' => Type::all()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(createThemeRequest $request, $groupname)
    {
        if (!auth()->user()->can('create themes')){
            return redirect(url("/"))->with([
                'type'    => 'danger',
                'Meldung' => "Berechtigung fehlt"
            ]);
        }

        $group = Group::where('name', $groupname)->first();

        if (!auth()->user()->groups->contains($group)){
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => "Kein Zugriff auf diese Gruppe"
            ]);
        }


        $theme = new Theme($request->validated());
        $theme->group_id = $group->id;
        $theme->creator_id = auth()->id();
        $theme->type_id = $request->type;
        $theme->save();

        if ($request->hasFile('files')) {
            $files = $request->files->all();
            foreach ($files['files'] as $file){

                $theme
                    ->addMedia($file)
                    ->toMediaCollection();
            }

        }

        return redirect(url($groupname.'/themes'))->with([
           'type'   => 'success',
           'Meldung'    => "Thema erstellt"
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Theme  $theme
     * @return \Illuminate\Http\Response
     */
    public function show($groupname,Theme $theme)
    {
        $group = Group::where('name', $groupname)->first();

        if (!auth()->user()->groups->contains($group)){
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => "Kein Zugriff auf diese Gruppe"
            ]);
        }

        return view('themes.show',[
            'theme' => $theme->load(['protocols', 'tasks', 'type', 'priorities', 'tasks.taskable',])
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Theme  $theme
     * @return \Illuminate\Http\Response
     */
    public function edit($groupname,Theme $theme)
    {
        $group = Group::where('name', $groupname)->first();

        if (!auth()->user()->groups->contains($group)){
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => "Kein Zugriff auf diese Gruppe"
            ]);
        }


        return view('themes.edit',[
            'theme' => $theme,
            'types' => Type::all()
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Theme  $theme
     * @return RedirectResponse
     */
    public function update( $groupname,createThemeRequest $request, Theme $theme)
    {
        $group = Group::where('name', $groupname)->first();

        if (!auth()->user()->groups->contains($group)){
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => "Kein Zugriff auf diese Gruppe"
            ]);
        }

        if ($request->input('date') != $theme->date){
            $newDate = Carbon::parse($request->input('date'));
            $protocol = Protocol::create([
                'creator_id' => auth()->id(),
                'theme_id' => $theme->id,
                'protocol'  => 'Verschoben zum '.$newDate->format('d.m.Y')
            ]);
            $protocol->save();
        }


        $theme->update($request->validated());
        $theme->type_id = $request->type;
        $theme->save();

        if ($request->hasFile('files')) {
            $files = $request->files->all();
            foreach ($files['files'] as $file){
                $theme
                    ->addMedia($file)
                    ->toMediaCollection();
            }

        }

        return redirect(url($groupname."/themes/$theme->id"))->with([
            'type'  => "success",
            'Meldung'=> "Änderungen gespeichert."
        ]);
    }

    public function destroy($groupname, Theme $theme){
        if (auth()->user()->id == $theme->creator_id and $theme->protocols->count() == 0 and $theme->priority == null and $theme->date->startOfDay()->greaterThan(Carbon::now()->startOfDay()->addDays(config('config.themes.addDays')))){
            $theme->delete();
            return redirect(url($groupname.'/themes'))->with([
                'type'  => 'info',
                'Meldung'   => "Thema wurde gelöscht."
            ]);
        }

        return redirect()->back()->with([
            'type'  => 'warning',
            'Meldung'   => "Thema kann nicht gelöscht werden"
        ]);
    }

   public function move(Theme $theme, $date = null){

   }
}
