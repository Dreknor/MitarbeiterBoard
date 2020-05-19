<?php

namespace App\Http\Controllers;

use App\Http\Requests\createThemeRequest;
use App\Models\Theme;
use App\Models\Type;
use Illuminate\Http\Request;

class ThemeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $themes=Theme::where('completed', 0)->get();
        $themes->load('priorities', 'ersteller', 'type');
        $themes = $themes->sortByDesc('priority');
        return view('themes.index',[
           'themes' => $themes
        ]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function archive()
    {
        $themes=Theme::where('completed', 1)->paginate(25);
        $themes->sortByDesc('created_at');
        $themes->load('ersteller','type');
        return view('themes.archive',[
           'themes' => $themes
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
        $theme->update($request->validated());
        $theme->type_id = $request->type;

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
