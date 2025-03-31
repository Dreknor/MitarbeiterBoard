<?php

namespace App\Http\Controllers;

use App\Http\Requests\createUserRequest;
use App\Http\Requests\UserRequest;
use App\Imports\UsersImport;
use App\Models\ElternInfoBoardUser;
use App\Models\Group;
use App\Models\Positions;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:edit users');
    }

    /**
     * Display a listing of the resource.
     *
     * @return View
     */
    public function index()
    {
        return view('users.index', [
            'users' => User::all(),
            'disabledUser' => User::onlyTrashed()->get()
        ]);
    }

    /**
     * Benutzer reaktivieren
     */
    public function restore(Request $request, $user_id){
        User::withTrashed()->find($user_id)->restore();

        return redirect(url('users/'.$user_id))->with([
            'type' => 'success',
            'Meldung' => 'Benutzer aktiviert'
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
        return view('users.show', [
            'user' => $user->load(['permissions', 'roles']),
            'permissions' => Permission::all(),
            'roles'     => Role::all(),
            'groups'    => Group::all(),
            'positions' => Positions::all(),
            'users' => User::all(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return RedirectResponse
     */
    public function update(UserRequest $request, User $user)
    {
        $user->fill($request->validated());
        $user->remind_assign_themes = $request->remind_assign_themes;


        if (auth()->user()->can('edit permissions')) {
            $permissions = $request->input('permissions');
            $user->syncPermissions($permissions);

            $roles = $request->input('roles');
            $user->syncRoles($roles);
        }

        if (auth()->user()->can('view procedures')) {
            $positions = $request->input('positions');
            $user->positions()->sync($positions);

        }

        $gruppen = $request->input('groups');
        if (! is_null($gruppen)) {
            $gruppen = Group::find($gruppen);
        }

        $user->groups_rel()->detach();
        $user->groups_rel()->attach($gruppen);

        if (auth()->user()->can('set password') and $request->input('new-password') != '') {
            $user->password = Hash::make($request->input('new-password'));
        }

        if ($user->save()) {
            return redirect()->back()->with([
               'type'   => 'success',
               'Meldung'    => 'Daten gespeichert.',
            ]);
        }

        return redirect()->back()->with([
        'type'   => 'danger',
        'Meldung'    => 'Update fehlgeschlagen',
    ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return RedirectResponse
     */
    public function destroy($id)
    {
        $user = User::find($id);
        $user->tasks()->delete();
        $user->delete();

        return redirect(url('users'))->with([
           'type' => 'warning',
           'Meldung'    => 'Benutzer deaktiviert.',
        ]);
    }

    public function loginAsUser($id)
    {
        if (! auth()->user()->hasRole('Admin')) {
            return redirect()->back()->with([
               'Meldung'    => 'Berechtigung fehlt',
               'type'       => 'danger',
            ]);
        }
        session(['ownID' => auth()->user()->id]);

        Auth::loginUsingId($id);

        return redirect(url('/'));
    }

    public function logoutAsUser()
    {
        if (session()->has('ownID')) {
            Auth::loginUsingId(session()->pull('ownID'));
        }

        return redirect(url('/'));
    }

    public function importFromElternInfoBoard()
    {
        $users = ElternInfoBoardUser::where('email', 'LIKE', '%@'.env('MAIL_DOMAIN'))->orWhere('email', 'LIKE', '%@'.env('MAIL_DOMAIN2'))->get();

        foreach ($users as $user) {
            $localUser = User::firstOrCreate([
                'email' => $user->email,
            ], [
               'name'   => $user->name,
                'password' =>$user->password,
                'remember_token' => $user->remember_token,
            ]);

            if ($localUser->password != $user->password) {
                $localUser->update([
                    'password' =>$user->password,
                    'remember_token' => $user->remember_token,
                ]);
            }
        }

        return redirect('users');
    }

    public function import(){
        return view('users.import');
    }
    public function importFromXls(Request $request){
        if ($request->hasFile('file')) {
        Excel::import(new UsersImport(), request()->file('file'));

        return redirect(url('users'))->with([
            'type'  => 'success',
            'Meldung'   => 'Erfolgreicher Import',
        ]);
    }

        return redirect()->back()->with([
            'type' => 'danger',
            'Meldung' => 'Keine Datei ausgewählt',
        ]);
    }


    public function downloadImportFile(){
        return response()->download(storage_path('/app/UsersImport.xls'));
    }
    /**
     * Show the form for creating a new resource.
     *
     * @return View
     */
    public function create()
    {
        return view('users.create', [

        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return RedirectResponse
     */
    public function store(createUserRequest $request)
    {
        $OldUser = User::where('email', $request->email)->withTrashed()->first();
        if (isset($OldUser)){
            $OldUser->restore();
            $OldUser->password = Hash::make($request->input('password'));
            $OldUser->changePassword = true;
            $OldUser->save();

            return redirect(url("users/$OldUser->id"))->with([
                'type'  => 'warning',
                'Meldung'   => 'Der Benutzer bestand bereits und wurde daher reaktiviert.',
            ]);
        }

        $user = new User($request->validated());
        $user->password = Hash::make($request->input('password'));
        $user->changePassword = true;
        $user->save();

        return redirect(url("users/$user->id"))->with([
            'type'  => 'success',
            'Meldung'   => 'Benutzer wurde angelegt',
        ]);
    }
}
