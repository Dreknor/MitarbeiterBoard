<?php

namespace App\Http\Controllers;

use App\Http\Requests\createThemeRequest;
use App\Models\Protocol;
use App\Models\Theme;
use App\Models\Type;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ThemeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return View
     */
    public function index()
    {
        $themes=Theme::where('completed', 0)->get();
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
    public function archive()
    {

        $themes=Theme::where('completed', 1)->orderByDesc('date')->get();
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
    public function store(createThemeRequest $request)
    {
        $theme = new Theme($request->validated());
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

        return redirect(url('themes'))->with([
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
    public function show(Theme $theme)
    {
        return view('themes.show',[
            'theme' => $theme
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Theme  $theme
     * @return \Illuminate\Http\Response
     */
    public function edit(Theme $theme)
    {

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
     * @return \Illuminate\Http\Response
     */
    public function update(createThemeRequest $request, Theme $theme)
    {
        if ($request->input('date') != $theme->date){
            $newDate = Carbon::parse($request->input('date'));
            $protocol = new Protocol([
                'creator_id' => auth()->id(),
                'theme_id' => $theme->id,
                'protocol'  => 'Verschoben zum '.$newDate->format('d.m.Y')
            ]);
            //$protocol->save();
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

        return redirect(url("themes/$theme->id"))->with([
            'type'  => "success",
            'Meldung'=> "Änderungen gespeichert."
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Theme  $theme
     * @return \Illuminate\Http\Response
     */
    public function destroy(Theme $theme)
    {
        //
    }
}
