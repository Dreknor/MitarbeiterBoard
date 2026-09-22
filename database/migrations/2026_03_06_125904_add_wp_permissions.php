<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Hinweis: MySQL mit utf8mb4_unicode_ci ist case-insensitive, daher ist
     * "create Wochenplan" und "create wochenplan" für den DB-Constraint identisch.
     * Die bestehende Permission wird daher umbenannt statt neu angelegt.
     */
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // "create Wochenplan" → "create wochenplan" umbenennen (case-insensitive Kollision)
        DB::table('permissions')
            ->where('name', 'create Wochenplan')
            ->where('guard_name', 'web')
            ->update(['name' => 'create wochenplan']);

        // Restliche neue Permissions anlegen (case-sensitiv unterschiedlich, kein Konflikt)
        $newPermissions = [
            'view wochenplan',
            'manage wochenplan-faecher',
            'manage wochenplan-formatvorlagen',
        ];

        foreach ($newPermissions as $permission) {
            // Prüfe case-insensitiv ob bereits vorhanden
            $exists = DB::table('permissions')
                ->whereRaw('LOWER(name) = ?', [strtolower($permission)])
                ->where('guard_name', 'web')
                ->exists();

            if (!$exists) {
                Permission::create([
                    'name'       => $permission,
                    'guard_name' => 'web',
                ]);
            }
        }

        // Cache nach allen Änderungen leeren
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Nutzer die "create wochenplan" haben, bekommen auch "view wochenplan"
        $viewPerm = DB::table('permissions')
            ->whereRaw('LOWER(name) = ?', ['view wochenplan'])
            ->where('guard_name', 'web')
            ->first();

        $createPerm = DB::table('permissions')
            ->whereRaw('LOWER(name) = ?', ['create wochenplan'])
            ->where('guard_name', 'web')
            ->first();

        if ($viewPerm && $createPerm) {
            // Alle User-IDs die create wochenplan haben
            $userIds = DB::table('model_has_permissions')
                ->where('permission_id', $createPerm->id)
                ->where('model_type', 'App\\Models\\User')
                ->pluck('model_id');

            foreach ($userIds as $userId) {
                $alreadyHasView = DB::table('model_has_permissions')
                    ->where('permission_id', $viewPerm->id)
                    ->where('model_type', 'App\\Models\\User')
                    ->where('model_id', $userId)
                    ->exists();

                if (!$alreadyHasView) {
                    DB::table('model_has_permissions')->insert([
                        'permission_id' => $viewPerm->id,
                        'model_type'    => 'App\\Models\\User',
                        'model_id'      => $userId,
                    ]);
                }
            }
        }

        Artisan::call('cache:clear');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // "create wochenplan" zurück zu "create Wochenplan" umbenennen
        DB::table('permissions')
            ->whereRaw('LOWER(name) = ?', ['create wochenplan'])
            ->where('guard_name', 'web')
            ->update(['name' => 'create Wochenplan']);

        // Neu angelegte Permissions entfernen
        $toDelete = ['view wochenplan', 'manage wochenplan-faecher', 'manage wochenplan-formatvorlagen'];
        foreach ($toDelete as $permission) {
            DB::table('permissions')
                ->whereRaw('LOWER(name) = ?', [strtolower($permission)])
                ->where('guard_name', 'web')
                ->delete();
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Artisan::call('cache:clear');
    }
};
