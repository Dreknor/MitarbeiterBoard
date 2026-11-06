<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Klassenraum-Tools (/klassenraum): eigenes Recht "use classroom tools".
 */
return new class extends Migration
{
    private const PERMISSION = 'use classroom tools';

    public function up(): void
    {
        $vorhanden = DB::table('permissions')
            ->where('name', self::PERMISSION)
            ->where('guard_name', 'web')
            ->exists();

        if (! $vorhanden) {
            DB::table('permissions')->insert([
                'name' => self::PERMISSION,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $id = DB::table('permissions')
            ->where('name', self::PERMISSION)
            ->where('guard_name', 'web')
            ->value('id');

        if ($id !== null) {
            DB::table('model_has_permissions')->where('permission_id', $id)->delete();
            DB::table('role_has_permissions')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
