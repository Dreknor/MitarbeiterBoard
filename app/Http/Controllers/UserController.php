<?php

namespace App\Http\Controllers;

use App\Http\Requests\createUserRequest;
use App\Http\Requests\UserRequest;
use App\Imports\UsersImport;
use App\Models\ElternInfoBoardUser;
use App\Models\Group;
use App\Models\Positions;
use App\Models\User;
use App\Services\UserDeletion\UserDeletionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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
            'disabledUser' => User::onlyTrashed()->where('is_placeholder', false)->where('deletion_stage', '<', UserDeletionService::STAGE_ANONYMIZED)->get()
        ]);
    }

    /**
     * Benutzer reaktivieren
     */
    public function restore(Request $request, $user_id){
        $user = User::onlyTrashed()->findOrFail($user_id);

        try {
            app(UserDeletionService::class)->restore($user);
        } catch (\RuntimeException $e) {
            return redirectBack('danger', $e->getMessage());
        }

        Log::info(
            'Benutzer: Benutzer reaktiviert',
            [
                'wiederhergestellt' => $user_id,
                'user' => auth()->user()->id,
                'ip' => $request->ip(),
            ]
        );

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

        if (auth()->user()->can('manage procedures') || auth()->user()->can('view assigned procedures')) {
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

        Log::debug(
            'Benutzer: Benutzer aktualisiert',
            [
                'user' => $user->id,
                'ip' => $request->ip(),
                'changed' => $request->all(),
            ]
        );

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
        // Deaktivierung läuft über das Löschkonzept mit Übergabe offener Verantwortungen
        return redirect(url('users/'.$id.'/deactivate'));
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

        Log::info(
            'Benutzer: Benutzer importiert',
            [
                'user' => auth()->user()->id,
                'ip' => $request->ip(),
            ]
        );

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
            try {
                app(UserDeletionService::class)->restore($OldUser);
            } catch (\RuntimeException $e) {
                return redirectBack('danger', $e->getMessage());
            }
            $OldUser->password = Hash::make($request->input('password'));
            $OldUser->changePassword = true;
            $OldUser->save();

            Log::info(
                'Benutzer: Benutzer reaktiviert',
                [
                    'user_id' => $OldUser->id,
                    'ip' => $request->ip(),
                    'verantwortlich_id' => auth()->id(),
                ]
            );

            return redirect(url("users/$OldUser->id"))->with([
                'type'  => 'warning',
                'Meldung'   => 'Der Benutzer bestand bereits und wurde daher reaktiviert.',
            ]);
        }

        $user = new User($request->validated());
        $user->password = Hash::make($request->input('password'));
        $user->changePassword = true;
        $user->save();

        Log::info(
            'Benutzer: Benutzer angelegt',
            [
                'user_id' => $user->id,
                'ip' => $request->ip(),
                'verantwortlich_id' => auth()->id(),
            ]
        );

        return redirect(url("users/$user->id"))->with([
            'type'  => 'success',
            'Meldung'   => 'Benutzer wurde angelegt',
        ]);
    }
}
