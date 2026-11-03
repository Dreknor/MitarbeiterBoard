<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Anwendungstokens (API) unter „Mein Profil“: Recht "create api tokens".
 * Wer die Pädagogen-App nutzen darf (und damit bereits API-Tokens per App-Login erhält),
 * darf zunächst auch Anwendungstokens erstellen.
 */
return new class extends Migration
{
    public function up(): void
    {
        $perm = Permission::findOrCreate('create api tokens', 'web');

        Role::query()
            ->whereHas('permissions', fn ($q) => $q->where('name', 'view paed diary'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($perm));

        // Direkt (ohne Rolle) vergebene Rechte ebenfalls übernehmen
        User::query()
            ->whereHas('permissions', fn ($q) => $q->where('name', 'view paed diary'))
            ->get()
            ->each(fn (User $user) => $user->givePermissionTo($perm));

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'create api tokens')->delete();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
