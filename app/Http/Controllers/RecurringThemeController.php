<?php

namespace App\Http\Controllers;

use App\Http\Requests\createRecurringThemeRequest;
use App\Models\Group;
use App\Models\Protocol;
use App\Models\RecurringTheme;
use App\Models\Theme;
use App\Models\Type;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class RecurringThemeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return View | RedirectResponse
     */
    public function index($groupname)
    {
        $group = Group::where('name', $groupname)->first();

        if (!auth()->user()->groups()->contains($group)) {
            return redirect()->back()->with([
                'type' => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        $themes = $group->recurringThemes()->get();
        $themes->load('ersteller', 'type');

        return view('recurringThemes.index',[
            'themes' => $themes
        ]);
    }
    /**
     * Show the form for creating a new resource.
     *
     * @return View
     */
    public function create($groupname)
    {
        $group = Group::where([
            'name'  => $groupname,
        ])->first();

        return view('recurringThemes.create', [
            'types' => Type::all(),
            'group' => $group,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return RedirectResponse
     */
    public function store(createRecurringThemeRequest $request, $groupname)
    {
        $group = Group::where('name', $groupname)->first();

        if (!auth()->user()->groups()->contains($group)) {
            return redirect()->back()->with([
                'type' => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        $theme = new RecurringTheme($request->validated());
        $theme->type_id = $request->type;
        $theme->creator_id = auth()->id();
        $theme->group_id = $group->id;
        $theme->save();

        if ($request->hasFile('files')) {
            $files = $request->files->all();
            foreach ($files['files'] as $file) {
                $theme
                    ->addMedia($file)
                    ->toMediaCollection();
            }
        }

        return redirect(url($groupname.'/themes/recurring'))->with([
            'type' => 'success',
            'Meldung' => 'Wiederkehrendes Thema wurde erstellt'
        ]);

    }



    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\RecurringTheme  $recurringTheme
     * @return View | RedirectResponse
     */
    public function edit($groupname, $recurringTheme)
    {

        $group = Group::where('name', $groupname)->first();

        if (!auth()->user()->groups()->contains($group)) {
            return redirect()->back()->with([
                'type' => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        return view('recurringThemes.edit',[
            'theme' => RecurringTheme::find($recurringTheme),
            'types' => Type::all()
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\RecurringTheme  $recurringTheme
     * @return RedirectResponse
     */
    public function update(createRecurringThemeRequest $request, $groupname,  $theme)
    {

        $group = Group::where('name', $groupname)->first();

        if (!auth()->user()->groups()->contains($group)) {
            return redirect()->back()->with([
                'type' => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        $theme = RecurringTheme::where('group_id', $group->id)->where('id', $theme)->firstOrFail();


        if ($theme->type_id != $request->type){
            $theme->type_id = $request->type;

        };
        $theme->update($request->validated());

        if ($request->hasFile('files')) {
            $files = $request->files->all();
            foreach ($files['files'] as $file) {
                $theme
                    ->addMedia($file)
                    ->toMediaCollection();
            }
        }

        return redirect($groupname.'/themes/recurring')->with([
            'type' => 'success',
            'Meldung' => 'Thema gespeichert',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  $recurringTheme
     * @return RedirectResponse
     */
    public function destroy($groupname, $recurringTheme)
    {

        $group = Group::where('name', $groupname)->first();

        if (!auth()->user()->groups()->contains($group)) {
            return redirect()->back()->with([
                'type' => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        RecurringTheme::where('group_id', $group->id)->where('id', $recurringTheme)->delete();

        return redirect($groupname.'/themes/recurring')->with([
            'type' => 'success',
            'Meldung' => 'Thema gelöscht',
        ]);
    }

    public function createNewThemes(){

        $month = Carbon::today()->addWeeks(config('config.startRecurringThemeWeeksBefore'));
        if ($month->day == 1){
           $themes = RecurringTheme::query()->where('month', $month->month)->get();


           foreach ($themes as $theme){
               $newTheme = new Theme($theme->getAttributes());
               $newTheme->date = $month;
               $newTheme->duration = 10;
               $newTheme->theme = $newTheme->theme.' '.$month->year;
               $newTheme->save();

               if ($theme->hasMedia()){
                   foreach ($theme->getMedia() as $media){
                       $media->copy($newTheme);
                   }
               }

               $protocol = new Protocol([
                   'theme_id' => $newTheme->id,
                   'protocol' => 'aus wiederkehrendem Thema erstellt'
               ]);
               $protocol->save();
           }

        }

    }
}
