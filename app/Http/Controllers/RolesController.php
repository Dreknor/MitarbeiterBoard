<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:edit permissions');
    }

    public function delete($roleid, $rolename){
        if ($rolename != 'Admin'){
            $role = Role::whereId($roleid)->where('name', $rolename)->first();
            $role->syncPermissions([]);
            $role->users()->detach();
            $role->delete();
        }
        return redirectBack('success', 'Rolle gelöscht');
    }

    public function edit()
    {
        return view('permissions.edit', [
            'roles'       => Role::all(),
            'permissions' => Permission::all(),
            'groups'      => Group::with('users')->orderBy('name')->get(),
        ]);
    }

    public function assignToGroup(Request $request)
    {
        $request->validate([
            'group_id' => 'required|exists:groups,id',
            'roles'    => 'nullable|array',
            'roles.*'  => 'exists:roles,name',
        ]);

        $group = Group::with('users')->findOrFail($request->group_id);
        $roles = $request->input('roles', []);

        foreach ($group->users as $user) {
            foreach ($roles as $role) {
                $user->assignRole($role);
            }
        }

        return redirect()->back()->with([
            'type'    => 'success',
            'Meldung' => 'Rollen für alle Mitglieder der Gruppe „' . $group->name . '" hinzugefügt.',
        ]);
    }

    public function update(Request $request)
    {
        foreach (Role::all() as $role) {
            $role->syncPermissions($request->input($role->name));
        }

        return  redirect()->back()->with([
            'type'   => 'success',
            'Meldung'    => 'Berechtigungen gespeichert',
        ]);
    }

    public function store(Request $request)
    {
        $Role = Role::firstOrCreate(['name' => $request->name]);

        return redirect()->back()->with([
            'type'   => 'success',
            'Meldung'    => 'Rolle erstellt',
        ]);
    }

    public function storePermission(Request $request)
    {
        $Role = Permission::firstOrCreate(['name' => $request->name]);

        return redirect()->back()->with([
            'type'   => 'success',
            'Meldung'    => 'Berechtigung erstellt',
        ]);
    }
}
