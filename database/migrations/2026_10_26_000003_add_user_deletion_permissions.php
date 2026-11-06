<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Löschkonzept: Berechtigungen für die Bestätigung der Löschstufen und das Personalarchiv.
 */
return new class extends Migration
{
    private array $permissions = [
        'manage user deletion',
        'view archived personal',
    ];

    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->permissions as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        Role::where('name', 'Admin')->where('guard_name', 'web')->first()?->givePermissionTo($this->permissions);
        Role::where('name', 'Personalleitung')->where('guard_name', 'web')->first()?->givePermissionTo('view archived personal');

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::where('guard_name', 'web')->whereIn('name', $this->permissions)->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
