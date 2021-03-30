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
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class ThemeController extends Controller
{
    public function setView($groupname, $viewType)
    {
        $group = Group::where('name', $groupname)->first();

        if (! auth()->user()->groups()->contains($group)) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        if ($viewType != null) {
            Cache::forever('viewType_'.$groupname.'_'.auth()->id(), $viewType);
        }

        return redirect(url($groupname.'/themes'));
    }

    /**
     * Display a listing of the resource.
     *
     * @return View|RedirectResponse
     */
    public function index($groupname)
    {
        $group = Group::where('name', $groupname)->first();

        if (! auth()->user()->groups()->contains($group)) {
            return redirect()->back()->with([
              'type'    => 'warning',
              'Meldung' => 'Kein Zugriff auf diese Gruppe',
           ]);
        }

        $themes = $group->themes()->where('completed', 0)->get();
        $themes->load('priorities', 'ersteller', 'type', 'protocols');

        $viewType = Cache::get('viewType_'.$groupname.'_'.auth()->id(), $group->viewType);

        switch ($viewType) {
            case 'date':
                    $themes = $themes->sortBy('date')->groupBy(function ($item) {
                        return  $item->date->format('d.m.Y');
                    });
                break;
            case 'type':
                $themes = $themes->sortBy('date')->groupBy(function ($item) {
                    return  $item->type->type;
                });
                break;
            case 'priority':
                $themes = $themes->sortByDesc('priority');
                break;
        }

        $views = [
            'date'  => 'index',
            'type'  => 'indexType',
            'priority' => 'indexPriority',
        ];

        $subscription = auth()->user()->subscriptions->where('subscriptionable_type', Group::class)->where('subscriptionable_id', $group->id)->first();

        return view('themes.'.$views[$viewType], [
           'themes' => $themes,
            'viewType' => $viewType,
            'subscription'  => $subscription,
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

        if (! auth()->user()->groups()->contains($group)) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        $themes = $group->themes()->where('completed', 1)->orderByDesc('date')->get();
        $themes->load('ersteller', 'type', 'priorities');

        $themes = $themes->groupBy(function ($item) {
            return $item->date->format('d.m.Y');
        });

        $themes = new \Illuminate\Support\Collection($themes);

        return view('themes.archive', [
           'themes' => $themes->paginate(5),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create($groupname)
    {
        $group = Group::where([
            'name'  => $groupname,
        ])->first();

        return view('themes.create', [
            'types' => Type::all(),
            'group' => $group,
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
        if (! auth()->user()->can('create themes')) {
            return redirect(url('/'))->with([
                'type'    => 'danger',
                'Meldung' => 'Berechtigung fehlt',
            ]);
        }

        $group = Group::where('name', $groupname)->first();

        if (! auth()->user()->groups()->contains($group) and $group->proteced) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        $date = Carbon::createFromFormat('Y-m-d', $request->date);
        if ($date->lessThan(Carbon::now()->addDays($group->InvationDays)->startOfDay())) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Thema kann für diesen Tag nicht mehr erstellt werden',
            ]);
        }

        $theme = new Theme($request->validated());
        $theme->group_id = $group->id;
        $theme->creator_id = auth()->id();
        $theme->type_id = $request->type;
        $theme->save();

        if ($request->hasFile('files')) {
            $files = $request->files->all();
            foreach ($files['files'] as $file) {
                $theme
                    ->addMedia($file)
                    ->toMediaCollection();
            }
        }

        return redirect(url($groupname.'/themes'))->with([
           'type'   => 'success',
           'Meldung'    => 'Thema erstellt',
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Theme  $theme
     * @return \Illuminate\Http\Response
     */
    public function show($groupname, Theme $theme)
    {
        $group = Group::where('name', $groupname)->first();

        if (! auth()->user()->groups()->contains($group) and $group->protected) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        $subscription = auth()->user()->subscriptions->where('subscriptionable_type', Theme::class)->where('subscriptionable_id', $theme->id)->first();

        return view('themes.show', [
            'theme' => $theme->load(['protocols', 'tasks', 'type', 'priorities', 'tasks.taskable']),
            'subscription' => $subscription,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Theme  $theme
     * @return \Illuminate\Http\Response
     */
    public function edit($groupname, Theme $theme)
    {
        $group = Group::where('name', $groupname)->first();

        if (! auth()->user()->groups()->contains($group) and $group->protected) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        return view('themes.edit', [
            'theme' => $theme,
            'types' => Type::all(),
            'group' => $group,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Theme  $theme
     * @return RedirectResponse
     */
    public function update($groupname, createThemeRequest $request, Theme $theme)
    {
        $group = Group::where('name', $groupname)->first();

        if (! auth()->user()->groups()->contains($group) and $group->protected) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        $date = Carbon::createFromFormat('Y-m-d', $request->date);

        if ($date->lessThan(Carbon::now()->addDays($group->InvationDays)->startOfDay())) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Thema kann für diesen Tag nicht mehr erstellt werden',
            ]);
        }

        if ($request->input('date') != $theme->date) {
            $newDate = Carbon::parse($request->input('date'));
            $protocol = Protocol::create([
                'creator_id' => auth()->id(),
                'theme_id' => $theme->id,
                'protocol'  => 'Verschoben zum '.$newDate->format('d.m.Y'),
            ]);
            $protocol->save();
        }

        $theme->update($request->validated());
        $theme->type_id = $request->type;
        $theme->save();

        if ($request->hasFile('files')) {
            $files = $request->files->all();
            foreach ($files['files'] as $file) {
                $theme
                    ->addMedia($file)
                    ->toMediaCollection();
            }
        }

        return redirect(url($groupname."/themes/$theme->id"))->with([
            'type'  => 'success',
            'Meldung'=> 'Änderungen gespeichert.',
        ]);
    }

    public function destroy($groupname, Theme $theme)
    {
        if (auth()->user()->id == $theme->creator_id and $theme->protocols->count() == 0 and $theme->priority == null and $theme->date->startOfDay()->greaterThan(Carbon::now()->startOfDay()->addDays(config('config.themes.addDays')))) {
            $theme->delete();

            return redirect(url($groupname.'/themes'))->with([
                'type'  => 'info',
                'Meldung'   => 'Thema wurde gelöscht.',
            ]);
        }

        return redirect()->back()->with([
            'type'  => 'warning',
            'Meldung'   => 'Thema kann nicht gelöscht werden',
        ]);
    }

    public function closeTheme($groupname, Theme $theme)
    {
        if (! auth()->user()->can('complete theme')) {
            return redirect()->back()->with([
                'type'  => 'danger',
               'Meldung'=> 'Berechtigung fehlt',
            ]);
        }

        $theme->update([
               'completed' => 1,
           ]);

        $protocol = new Protocol([
               'creator_id' => auth()->id(),
               'theme_id'   => $theme->id,
               'protocol'   => 'Thema geschlossen',
           ]);
        $protocol->save();

        return redirect(url($groupname.'/themes'))->with([
               'type'  => 'success',
               'Meldung'=> 'Thema geschlossen',
           ]);
    }
}
