<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $permissions = [
            'view wochenplan',
            'create wochenplan',
            'manage wochenplan-faecher',
            'manage wochenplan-formatvorlagen',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name'       => $permission,
                'guard_name' => 'web',
            ]);
        }

        // Nutzer mit alter Permission "create Wochenplan" bekommen neue Permissions
        // (nur wenn die alte Permission existiert)
        if (Permission::where('name', 'create Wochenplan')->exists()) {
            \App\Models\User::all()
                ->filter(fn($u) => $u->hasPermissionTo('create Wochenplan'))
                ->each(fn($u) => $u->givePermissionTo(['view wochenplan', 'create wochenplan']));
        }

        Artisan::call('cache:clear');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissions = [
            'view wochenplan',
            'create wochenplan',
            'manage wochenplan-faecher',
            'manage wochenplan-formatvorlagen',
        ];

        foreach ($permissions as $permission) {
            Permission::where('name', $permission)->delete();
        }

        Artisan::call('cache:clear');
    }
};
