<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $perms = ['comment procedure steps', 'manage procedure categories'];

        foreach ($perms as $name) {
            $exists = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->exists();
            if (!$exists) {
                DB::table('permissions')->insert([
                    'name'       => $name,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Default-Vergabe: alle Rollen mit „view assigned procedures" bekommen „comment procedure steps".
        $viewAssigned = DB::table('permissions')->where('name', 'view assigned procedures')->first();
        $comment      = DB::table('permissions')->where('name', 'comment procedure steps')->first();

        if ($viewAssigned && $comment) {
            $roleIds = DB::table('role_has_permissions')
                ->where('permission_id', $viewAssigned->id)
                ->pluck('role_id');

            foreach ($roleIds as $roleId) {
                $already = DB::table('role_has_permissions')
                    ->where('permission_id', $comment->id)
                    ->where('role_id', $roleId)
                    ->exists();
                if (!$already) {
                    DB::table('role_has_permissions')->insert([
                        'permission_id' => $comment->id,
                        'role_id'       => $roleId,
                    ]);
                }
            }
        }

        try { Artisan::call('cache:clear'); } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        DB::table('permissions')
            ->whereIn('name', ['comment procedure steps', 'manage procedure categories'])
            ->delete();

        try { Artisan::call('cache:clear'); } catch (\Throwable $e) {}
    }
};

