<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. dashboard_card_user: + width (string, default 'md'), + order (integer, default 0)
        Schema::table('dashboard_card_user', function (Blueprint $table) {
            $table->string('width')->default('md')->after('active');
            $table->integer('order')->default(0)->after('width');
        });

        // 2. dashboard_cards: + default_width, + icon, + skeleton
        Schema::table('dashboard_cards', function (Blueprint $table) {
            $table->string('default_width')->default('md')->after('default_col');
            $table->string('icon')->nullable()->after('default_width');
            $table->string('skeleton')->default('default')->after('icon');
        });

        // 3. Permission 'use dashboard v2' anlegen
        Permission::findOrCreate('use dashboard v2');

        // 4. Bestehende Rows migrieren: order = row * 10 + col
        DB::table('dashboard_card_user')->get()->each(function ($row) {
            DB::table('dashboard_card_user')
                ->where('id', $row->id)
                ->update(['order' => ($row->row * 10) + $row->col]);
        });

        // 5. Icon-Defaults setzen für alle bestehenden Cards
        $iconMap = [
            'posts.dashboardCard'                               => ['icon' => 'fas fa-newspaper',      'skeleton' => 'list'],
            'absences.dashboardCard'                            => ['icon' => 'fas fa-user-slash',      'skeleton' => 'table'],
            'tasks.tasksCard'                                   => ['icon' => 'fas fa-tasks',           'skeleton' => 'list'],
            'procedure.dashboardCard'                           => ['icon' => 'fas fa-project-diagram', 'skeleton' => 'list'],
            'wiki.dashboardCard'                                => ['icon' => 'fas fa-book',            'skeleton' => 'list'],
            'personal.holidays.dashboardCard'                   => ['icon' => 'fas fa-umbrella-beach',  'skeleton' => 'list'],
            'personal.time_recording.dashboardCard'             => ['icon' => 'fas fa-clock',           'skeleton' => 'list'],
            'personal.time_recording.dashboardCardOwn'          => ['icon' => 'fas fa-user-clock',      'skeleton' => 'table'],
            'ticketsystem.dashboardCard'                        => ['icon' => 'fas fa-headset',         'skeleton' => 'list'],
            'rooms.rooms.freeRoomsCard'                         => ['icon' => 'fas fa-door-open',       'skeleton' => 'default'],
            'calendar.dashboardCard'                            => ['icon' => 'fas fa-calendar-alt',    'skeleton' => 'list'],
            'atom-feed.dashboardCard'                           => ['icon' => 'fas fa-rss',             'skeleton' => 'list'],
            'personal.hort_planung.dashboardCard'               => ['icon' => 'fas fa-child',           'skeleton' => 'stats'],
        ];

        foreach ($iconMap as $view => $data) {
            DB::table('dashboard_cards')
                ->where('view', $view)
                ->update([
                    'icon'     => $data['icon'],
                    'skeleton' => $data['skeleton'],
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Spalten width, order aus dashboard_card_user entfernen
        Schema::table('dashboard_card_user', function (Blueprint $table) {
            $table->dropColumn(['width', 'order']);
        });

        // 2. Spalten default_width, icon, skeleton aus dashboard_cards entfernen
        Schema::table('dashboard_cards', function (Blueprint $table) {
            $table->dropColumn(['default_width', 'icon', 'skeleton']);
        });

        // 3. Permission löschen
        Permission::findByName('use dashboard v2')?->delete();
    }
};

