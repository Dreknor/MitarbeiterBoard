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
        $card = new \App\Models\DashboardCard([
            'title' => 'Arbeitszeiterfassung (Anwesende)',
            'view' => 'personal.time_recording.dashboardCard',
            'default_row' => 3,
            'default_col' => 3,
            'permission' => 'view time recording',
        ]);
        $card->save();

        $permission = new Spatie\Permission\Models\Permission([
            'name' => 'view time recording',
            'guard_name' => 'web',
        ]);
        $permission->save();

        $role = Spatie\Permission\Models\Role::findByName('Admin');
        $role->givePermissionTo($permission);

        exec('php artisan cache:clear');

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dashboard_cards', function (Blueprint $table) {
            \App\Models\DashboardCard::where('title', 'Arbeitszeiterfassung (Anwesende)')->delete();
        });

        $permission = Spatie\Permission\Models\Permission::findByName('view time recording');
        $permission->delete();

        exec('php artisan cache:clear');
    }
};
