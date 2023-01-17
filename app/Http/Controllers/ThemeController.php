<?php

namespace App\Http\Controllers;

use App\Http\Requests\createThemeRequest;
use App\Http\Requests\moveThemesRequest;
use App\Mail\newThemeAssignMail;
use App\Models\Group;
use App\Models\Protocol;
use App\Models\Subscription;
use App\Models\Theme;
use App\Models\Type;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ThemeController extends Controller
{

    public function change_group(Theme $theme, Group $group){
        if (! auth()->user()->groups()->contains($theme->group) or (! auth()->user()->groups()->contains($group) and $group->protected)) {
            return redirect()->back()->with([
                'type'    => 'danger',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        if (! auth()->user()->can('move themes')) {
            return redirect()->back()->with([
                'type'    => 'danger',
                'Meldung' => 'Berechtigung fehlt',
            ]);
        }

        $oldGroup = $theme->group;

        $theme->update([
           'group_id' => $group->id
        ]);

        $protocol = new Protocol([
            'theme_id' => $theme->id,
            'creator_id' => auth()->id(),
            'protocol' =>  auth()->user()->name.' hat das Thema aus der Gruppe '.$oldGroup->name.' nach '.$group->name.' verschoben.'
        ]);
        $protocol->save();

        return redirect(url($oldGroup->name.'/themes#'.$theme->date->format('Ymd')))->with([
            'type'    => 'success',
            'Meldung' => 'Thema zur Gruppe '.$group->name.' verschoben.',
        ]);
    }

    public function assgin_to(Theme $theme, User $user){
        if (! auth()->user()->groups()->contains($theme->group)) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        if (! $user->groups()->contains($theme->group)) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Benutzer ist nicht in dieser Gruppe',
            ]);
        }

        $theme->update([
           'assigned_to' => $user->id
        ]);


        //Subscription erstellen
        $type = Theme::class;
        $subscription = $user->subscriptions()->where('subscriptionable_type', $type)->where('subscriptionable_id', $theme->id)->first();
        if ($subscription == null) {
            $subscription = new Subscription([
                'users_id' => $user->id,
                'subscriptionable_type' => $type,
                'subscriptionable_id'=>$theme->id,
            ]);

            $subscription->save();
        }

        //Benachrichtigen
        Mail::to($user->email)->queue(new newThemeAssignMail($theme, $user));

        //Log erstellen
        $protocol = new Protocol([
           'theme_id' => $theme->id,
           'creator_id' => auth()->id(),
           'protocol' =>  auth()->user()->name.' hat das Thema '.$user->name.' zugewiesen.'
        ]);
        $protocol->save();


        return redirect()->back()->with([
            'type'    => 'success',
            'Meldung' => 'Thema zugewiesen',
        ]);

    }
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

        $themes = $group->themes()->where('completed', 0)->where('memory', 0)->get();
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
            'group' => $group
        ]);
    }
    public function memory($groupname)
    {
        $group = Group::where('name', $groupname)->first();

        if (! auth()->user()->groups()->contains($group)) {
            return redirect()->back()->with([
              'type'    => 'warning',
              'Meldung' => 'Kein Zugriff auf diese Gruppe',
           ]);
        }

        $themes = $group->themes()->where('completed', 0)->where('memory', 1)->get();
        $themes->load('priorities', 'ersteller');

        $themes = $themes->sortByDesc('priority');

        return view('themes.memory', [
           'themes' => $themes,
        ]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return View|RedirectResponse
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

    public function unArchive(Theme $theme)
    {

        if (! auth()->user()->can('unarchive theme')) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        $theme->update(['completed' => 0]);

        $protocol = Protocol::create([
            'creator_id' => auth()->id(),
            'theme_id' => $theme->id,
            'protocol'  => 'Thema wieder aktiviert',
        ]);
        $protocol->save();


        return redirect()->back()->with([
            'type' => 'success',
            'Meldung' => 'Thema erneut geöffnet'
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return View
     */
    public function create($groupname, $speicher=null)
    {
        $group = Group::where([
            'name'  => $groupname,
        ])->first();

        return view('themes.create', [
            'types' => Type::all(),
            'group' => $group,
            'speicher' => $speicher
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
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
        if ($date->lessThan(Carbon::today()->startOfDay())) {
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

        if ($group->protected == null){
            $subscription = new Subscription([
                'users_id' => auth()->id(),
                'subscriptionable_type' => Theme::class,
                'subscriptionable_id'=>$theme->id,
            ]);
            $subscription->save();
        }

        return redirect(url($groupname.'/themes#'.$theme->date->format('Ymd')))->with([
           'type'   => 'success',
           'Meldung'    => 'Thema erstellt',
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Theme  $theme
     * @return \Illuminate\Http\RedirectResponse
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

        if ($group->id != $theme->group_id) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Thema nicht gefunden',
            ]);
        }

        if ($theme->memory == true and $theme->completed == false) {
            return redirect(url($groupname.'/memory'))->with([
                'type'    => 'warning',
                'Meldung' => 'Thema ist im Themenspeicher',
            ]);
        }

        $subscription = auth()->user()->subscriptions->where('subscriptionable_type', Theme::class)->where('subscriptionable_id', $theme->id)->first();

        return view('themes.show', [
            'theme' => $theme->load(['protocols', 'tasks', 'type', 'priorities', 'tasks.taskable']),
            'subscription' => $subscription,
        ]);
    }

    public function memoryTheme($groupname, Theme $theme)
    {
        $group = Group::where('name', $groupname)->first();

        if (! auth()->user()->groups()->contains($group) and $group->protected) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        $theme->update([
            'memory' => true
        ]);

        $protocol = Protocol::create([
            'creator_id' => auth()->id(),
            'theme_id' => $theme->id,
            'protocol'  => 'Thema in Themenspeicher verschoben',
        ]);
        $protocol->save();

        return redirect(url($groupname."/themes#".$theme->date->format('Ymd')))->with([
            'type'  => 'success',
            'Meldung' => 'Thema wurde aktiviert.'
        ]);
    }
    public function activate($groupname, Theme $theme)
    {
        $group = Group::where('name', $groupname)->first();

        if (! auth()->user()->groups()->contains($group) and $group->protected) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        $theme->update([
            'memory' => false
        ]);

        $protocol = Protocol::create([
            'creator_id' => auth()->id(),
            'theme_id' => $theme->id,
            'protocol'  => 'Thema aktiviert',
        ]);
        $protocol->save();

        return redirect(url($groupname."/themes/".$theme->id))->with([
            'type'  => 'success',
            'Meldung' => 'Thema wurde aktiviert.'
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Theme  $theme
     * @return View|RedirectResponse
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

        (!$date->eq($theme->date))? $redirectDate = $date->format('Ymd') : $redirectDate = $theme->date->format('Ymd');

        if ($date->lessThan(Carbon::now()->addDays($group->InvationDays)->startOfDay()) and !$date->isSameDay(Carbon::today())) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Thema kann für diesen Tag nicht mehr erstellt werden',
            ]);
        }

        if ($request->memory == true) {
            $protocol = Protocol::create([
                'creator_id' => auth()->id(),
                'theme_id' => $theme->id,
                'protocol'  => 'In Themenspeicher verschoben'
            ]);
            $protocol->save();
        }
        if (!$date->eq($theme->date)) {
            $protocol = Protocol::create([
                'creator_id' => auth()->id(),
                'theme_id' => $theme->id,
                'protocol'  => 'Verschoben zum '.$date->format('d.m.Y'),
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

        return redirect(url($groupname."/themes#$redirectDate"))->with([
            'type'  => 'success',
            'Meldung'=> 'Änderungen gespeichert.',
        ]);
    }

    public function destroy($groupname, Theme $theme)
    {
        $date = $theme->date->format('Ymd');
        if (auth()->user()->id == $theme->creator_id and $theme->protocols->count() == 0 and $theme->priority == null and $theme->date->startOfDay()->greaterThan(Carbon::now()->startOfDay()->addDays(config('config.themes.addDays')))) {
            $theme->delete();

            return redirect(url($groupname.'/themes#'.$date))->with([
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

        return redirect(url($groupname.'/themes#'.$theme->date->format('Ymd')))->with([
               'type'  => 'success',
               'Meldung'=> 'Thema geschlossen',
           ]);
    }

    public function move($groupname, moveThemesRequest $request){

        $group = Group::where('name', $groupname)->first();

        if (! auth()->user()->groups()->contains($group) and $group->protected) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        $oldDate = Carbon::createFromFormat('Y-m-d', $request->oldDate);
        $date = Carbon::createFromFormat('Y-m-d', $request->date);

        $themes = $group->themes()->where('date', $oldDate->format('Y-m-d'))->update([
            'date' => $date->format("Y-m-d")
        ]);


        return redirect(url($groupname."/themes#".$oldDate->format('Ymd')))->with([
            'type'  => 'success',
            'Meldung'=> $themes.' Themen wurden verschoben',
        ]);
    }
}
