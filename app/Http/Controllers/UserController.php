<?php

namespace App\Http\Controllers;

use App\Models\ElternInfoBoardRoles;
use App\Models\ElternInfoBoardUser;
use App\Models\Group;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct()
    {
    }

    /**
     * Display a listing of the resource.
     *
     * @return View
     */
    public function index()
    {
        return view('users.index', [
            'users' => User::all()
        ]);
    }


    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return View
     */
    public function show(User $user)
    {
        return view('users.show',[
            "user" => $user->load(['groups', 'permissions', 'roles']),
            'permissions' => Permission::all(),
            'roles'     => Role::all(),
            'groups'    => Group::all()
        ]);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return RedirectResponse
     */
    public function update(Request $request, User $user)
    {
        $user->fill($request->all());

        if (auth()->user()->can('edit permissions')){
            $permissions= $request->input('permissions');
            $user->syncPermissions($permissions);

            $roles= $request->input('roles');
            $user->syncRoles($roles);
        }

        $gruppen= $request->input('groups');
        if (!is_null($gruppen) ){
                $gruppen = Group::find($gruppen);
        }

        $user->groups()->detach();
        $user->groups()->attach($gruppen);

        if (auth()->user()->can('set password') and $request->input('new-password') != ""){
            $user->password = Hash::make($request->input('new-password'));
        }

        if ($user->save()){
            return redirect()->back()->with([
               "type"   => "success",
               "Meldung"    => "Daten gespeichert."
            ]);
        }

        return redirect()->back()->with([
        "type"   => "danger",
        "Meldung"    => "Update fehlgeschlagen"
    ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function destroy($id)
    {
        $user = User::find($id);
        $user->delete();

        return response()->json([
            "message"   => "Gelöscht"
        ], 200);
    }

    public function loginAsUser($id){
        if (!auth()->user()->hasRole('Admin')){
            return redirect()->back()->with([
               'Meldung'    => "Berechtigung fehlt",
               'type'       => "danger"
            ]);
        }
        session(['ownID' => auth()->user()->id]);

        Auth::loginUsingId($id);

        return redirect(url('/'));

    }

    public function logoutAsUser(){
        if (session()->has('ownID')){
            Auth::loginUsingId(session()->pull('ownID'));
        }
        return redirect(url('/'));
    }



    public function importFromElternInfoBoard(){
        $users = ElternInfoBoardUser::where('email', 'LIKE', '%@esz-radebeul.de')->orWhere('email', 'LIKE', '%@ev-schulverein.de')->get();
        $group = Group::where('name', 'Schulzentrum')->first();
        foreach ($users as $user){
            $localUser = User::firstOrCreate([
                'email' => $user->email
            ], [
               'name'   => $user->name,
                "password" =>$user->password,
                "remember_token" => $user->remember_token
            ]);

            if ( $localUser->password != $user->password ){
                $localUser->update([
                    "password" =>$user->password,
                    "remember_token" => $user->remember_token
                ]);
            }

            if (!$localUser->groups->where('name', 'Schulzentrum')->first()){
                $localUser->groups()->attach($group);
            }

        }

        return redirect('users');
    }
}
