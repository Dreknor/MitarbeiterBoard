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
        $permission = new Spatie\Permission\Models\Permission(
            [
                'name' => 'view logs',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $permission->save();

        \Illuminate\Support\Facades\Cache::clear();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permission = Spatie\Permission\Models\Permission::where('name', 'view logs')->first();

        if ($permission) {
            $permission->delete();
        }

        \Illuminate\Support\Facades\Cache::clear();
    }
};
