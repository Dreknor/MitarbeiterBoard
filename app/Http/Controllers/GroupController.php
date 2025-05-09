<?php

namespace App\Http\Controllers;

use App\Http\Requests\GroupRequest;
use App\Models\Group;
use App\Models\Protocol;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GroupController extends Controller
{


    /**
     * Display a listing of the View.
     *
     * @return View
     */
    public function index()
    {
        if (auth()->user()->can('edit groups')) {
            $groups = Group::all();
        } else {
            $groups = auth()->user()->groups();
        }

        $groups->load('users');

        return view('groups.index', [
            'groups'    =>$groups,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(GroupRequest $request)
    {
        $group = new Group($request->validated());
        $group->creator_id = auth()->id();
        $group->save();

        Log::info(
            'Gruppen: Gruppe '.$group->name. ' erstellt',
            [
                'user' => auth()->user()->name,
                'group' => $group,
            ]
        );

        $group->users()->attach(auth()->user());

        Cache::forget('groups_'.auth()->id());

        return redirect(url('groups'))->with([
           'type'   => 'success',
           'Meldung'    => 'Gruppe '.$group->name.' wurde erstellt.',
        ]);
    }

    public function edit(Group $group){
        $groups = Group::all();

        return response()->view('groups.edit',[
            'gruppe' => $group,
            'groups' => $groups
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Group  $group
     * @return RedirectResponse
     */
    public function update(GroupRequest $request, Group $group){
        $group->update($request->validated());

        Log::info(
            'Gruppen: Gruppe '.$group->name. ' bearbeitet',
            [
                'user' => auth()->user()->name,
                'group' => $group,
            ]
        );

        return redirect(url('groups'))->with([
            'type'   => 'success',
            'Meldung'    => 'Gruppe '.$group->name.' wurde bearbeitet.',
        ]);
    }



    public function addUser(Request $request, $groupname)
    {
        $group = Group::where('name', $groupname)->first();



        if (! $group) {
            return redirect()->back()->with([
               'type'   => 'danger',
               'Meldung' => 'Gruppe exsistiert nicht',
            ]);
        }

        if (! auth()->user()->can('edit groups') and $group->creator_id != auth()->id()) {
            return redirect()->back()->with([
                'type'   => 'danger',
                'Meldung' => 'Berechtigung fehlt nicht',
            ]);
        }

        $user = User::where('name', 'LIKE', '%'.$request->input('name').'%')->get();

        if ($user->count() == 1) {
            $group->users()->attach($user);

            Log::info(
                'Gruppen: Benutzer '.$user[0]->name. ' zur Gruppe '.$group->name. ' hinzugefügt',
                [
                    'user' => auth()->user()->name,
                    'group' => $group,
                ]
            );

            return  redirect()->back()->with([
                'type'   => 'success',
                'Meldung' => 'Benutzer hinzugefügt',
            ]);
        } else {
            return  redirect()->back()->with([
                'type'   => 'warning',
                'Meldung' => 'Benutzer nicht gefunden oder nicht eindeutig',
            ]);
        }
    }

    public function removeUser(Request $request, $groupname)
    {
        $group = Group::where('name', $groupname)->first();

        if (! $group) {
            return redirect()->back()->with([
                'type'   => 'danger',
                'Meldung' => 'Gruppe exsistiert nicht',
            ]);
        }

        if (! auth()->user()->can('edit groups') and $group->creator_id != auth()->id()) {
            return redirect()->back()->with([
                'type'   => 'danger',
                'Meldung' => 'Berechtigung fehlt nicht',
            ]);
        }

        $user_id = $request->input('user_id');

        $user = User::where('id', $user_id)->first();

        if (isset($user)) {
            $group->users()->detach($user);

            Log::info(
                'Gruppen: Benutzer '.$user->name. ' aus Gruppe '.$group->name. ' entfernt',
                [
                    'user' => auth()->user()->name,
                    'group' => $group,
                ]
            );

            return  redirect()->back()->with([
                'type'   => 'success',
                'Meldung' => 'Benutzer entfernt',
            ]);
        } else {
            return  redirect()->back()->with([
                'type'   => 'warning',
                'Meldung' => 'Benutzer nicht gefunden oder nicht eindeutig',
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Group  $group
     * @return \Illuminate\Http\Response
     */
    public function destroy(Group $group)
    {
        $group->users()->sync([]);
        $group->subscriptionable()->delete();



        if ($group->homegroup != '') {
            $themes = $group->themes;
            foreach ($themes as $theme) {

                $theme->subscriptionable()->delete();


                $protocol = new Protocol([
                        'creator_id' => 1,
                        'theme_id'   => $theme->id,
                        'protocol'   => 'Gruppe wurde geschlossen und das Thema der Hauptgruppe hinzugefügt',
                    ]);
                $protocol->save();

                $theme->group_id = $group->homegroup;
                $theme->save();


            }

            Log::info(
                'Gruppen: Gruppe '.$group->name. ' gelöscht und Themen der Hauptgruppe hinzugefügt',
                [
                    'user' => (auth()->check() ? auth()->user()->name : 'System'),
                    'group' => $group,
                ]
            );
        }

        $group->delete();
    }

    public function deleteOldGroups()
    {
        $groups = Group::where('enddate', '!=', '')->whereDate('enddate', '=', Carbon::today())->get();

        foreach ($groups as $group) {

            Log::info(
                'Gruppen: Gruppe '.$group->name. ' gelöscht',
                [
                    'user' => (auth()->check() ? auth()->user()->name : 'System'),
                    'group' => $group,
                ]
            );

            $this->destroy($group);
        }
    }
}
