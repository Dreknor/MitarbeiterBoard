<?php

use App\Models\DashboardCard;
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

        $cards = [];

        $cards[] = [
            'title' => 'Abwesenheiten',
            'view' => 'absences.dashboardCard',
            'permission' => 'view absences',
            'default_row' => 3,
            'default_col' => 1,
        ];
        $cards[] = [
            'title' => 'Dienstplan',
            'view' => 'personal.rosters.homeView',
            'permission' => null,
            'default_row' => 1,
            'default_col' => 2,
        ];

        $cards[] = [
            'title' => 'Aufgaben',
            'view' => 'tasks.tasksCard',
            'permission' => null,
            'default_row' => 1,
            'default_col' => 3,
        ];
        $cards[] = [
            'title' => 'Prozesse',
            'view' => 'procedure.dashboardCard',
            'permission' => 'view procedures',
            'default_row' => 2,
            'default_col' => 3,
        ];
        $cards[] = [
            'title' => 'Vertretungen',
            'view' => 'vertretungsplan.UserVertretungen',
            'permission' => null,
            'default_row' => 2,
            'default_col' => 2,
        ];

        $cards[] = [
            'title' => 'Nachrichten',
            'view' => 'posts.dashboardCard',
            'permission' => null,
            'default_row' => 1,
            'default_col' => 1,
        ];

        DashboardCard::insert($cards);

        Spatie\Permission\Models\Permission::create([
            'name' => 'edit dashboard',
            'guard_name' => 'web'
        ]);

        exec('php artisan cache:clear');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
