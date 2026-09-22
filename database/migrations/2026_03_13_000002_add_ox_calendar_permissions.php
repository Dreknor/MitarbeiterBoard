<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'view calendar',
            'create calendar events',
            'edit calendar events',
            'manage calendar',
        ];

        foreach ($permissions as $permission) {
            Permission::create([
                'name'       => $permission,
                'guard_name' => 'web',
            ]);
        }

        Artisan::call('cache:clear');
    }

    public function down(): void
    {
        $permissions = [
            'view calendar',
            'create calendar events',
            'edit calendar events',
            'manage calendar',
        ];

        foreach ($permissions as $permission) {
            Permission::where('name', $permission)->delete();
        }

        Artisan::call('cache:clear');
    }
};

