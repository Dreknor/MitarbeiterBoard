<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * Graduierung: "change grading stage" trennt das Vergeben von Stufen im Alltag (Tagebuch, Schülerdaten,
 * API) vom Verwalten der Graduierungssysteme ("manage grading systems").
 *
 * Damit niemand Rechte verliert, erhalten alle Rollen und Benutzer, die heute "manage grading systems"
 * besitzen, das neue Recht zusätzlich. Danach kann "manage grading systems" bei Lehrkräften entzogen werden.
 */
return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $neu = Permission::findOrCreate('change grading stage', 'web');
        $alt = Permission::where('name', 'manage grading systems')->where('guard_name', 'web')->first();

        if ($alt) {
            $tables = config('permission.table_names');
            $pivotKey = config('permission.column_names.permission_pivot_key') ?? 'permission_id';

            foreach (['role_has_permissions', 'model_has_permissions'] as $key) {
                $table = $tables[$key];
                $rows = DB::table($table)->where($pivotKey, $alt->id)->get();
                foreach ($rows as $row) {
                    $data = (array) $row;
                    $data[$pivotKey] = $neu->id;
                    DB::table($table)->insertOrIgnore($data);
                }
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::where('guard_name', 'web')->where('name', 'change grading stage')->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
