<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Neue Permissions erstellen
        \Illuminate\Support\Facades\DB::table('permissions')->insert([
            [
                'name' => 'manage procedures',
                'guard_name' => 'web'
            ],
            [
                'name' => 'view assigned procedures',
                'guard_name' => 'web'
            ],
            [
                'name' => 'complete own procedure steps',
                'guard_name' => 'web'
            ]
        ]);

        // Alle Benutzer die "view procedures" haben, bekommen "manage procedures"
        $viewProcedurePermission = \Spatie\Permission\Models\Permission::where('name', 'view procedures')->first();
        if ($viewProcedurePermission) {
            $manageProcedurePermission = \Spatie\Permission\Models\Permission::where('name', 'manage procedures')->first();

            // Rollen mit view procedures bekommen manage procedures
            $roles = $viewProcedurePermission->roles;
            foreach ($roles as $role) {
                $role->givePermissionTo($manageProcedurePermission);
            }

            // Benutzer mit direkter view procedures Permission bekommen manage procedures
            $users = \App\Models\User::permission('view procedures')->get();
            foreach ($users as $user) {
                $user->givePermissionTo($manageProcedurePermission);
            }
        }

        \Illuminate\Support\Facades\Artisan::call('cache:clear');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Support\Facades\DB::table('permissions')
            ->whereIn('name', ['manage procedures', 'view assigned procedures', 'complete own procedure steps'])
            ->delete();

        \Illuminate\Support\Facades\Artisan::call('cache:clear');
    }
};
