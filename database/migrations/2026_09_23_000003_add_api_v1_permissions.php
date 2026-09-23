<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * API v1 (Pädagogen-App): Sonderrechte.
 *
 * - "view all students":               klassenübergreifender Zugriff auf alle Schüler (Schulleitung/Admin)
 * - "view confidential diary entries": Einsicht in vertrauliche Tagebucheinträge (dossier_only = true)
 *
 * Die Rolle "Admin" besitzt beide Rechte implizit (siehe User::hasApiAdminRights()),
 * sie werden hier zusätzlich explizit vergeben, damit die Rechteverwaltung sie anzeigt.
 */
return new class extends Migration
{
    private array $permissions = [
        'view all students',
        'view confidential diary entries',
    ];

    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->permissions as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        foreach (['Admin', 'Schulleitung'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            $role?->givePermissionTo($this->permissions);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::where('guard_name', 'web')->whereIn('name', $this->permissions)->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
