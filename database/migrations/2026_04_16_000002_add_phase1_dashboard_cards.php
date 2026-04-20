<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Geburtstage Card
        if (DB::table('dashboard_cards')->where('view', 'dashboard.cards.geburtstage')->doesntExist()) {
            DB::table('dashboard_cards')->insert([
                'title'         => 'Geburtstage',
                'view'          => 'dashboard.cards.geburtstage',
                'permission'    => null,
                'default_row'   => 1,
                'default_col'   => 0,
                'default_width' => 'md',
                'icon'          => 'fas fa-birthday-cake',
                'skeleton'      => 'list',
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }

        // Tagesinfos Card
        if (DB::table('dashboard_cards')->where('view', 'dashboard.cards.tagesinfos')->doesntExist()) {
            DB::table('dashboard_cards')->insert([
                'title'         => 'Tagesinfos',
                'view'          => 'dashboard.cards.tagesinfos',
                'permission'    => null,
                'default_row'   => 1,
                'default_col'   => 1,
                'default_width' => 'md',
                'icon'          => 'fas fa-bullhorn',
                'skeleton'      => 'list',
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }

        // Benachrichtigungen Card
        if (DB::table('dashboard_cards')->where('view', 'dashboard.cards.benachrichtigungen')->doesntExist()) {
            DB::table('dashboard_cards')->insert([
                'title'         => 'Benachrichtigungen',
                'view'          => 'dashboard.cards.benachrichtigungen',
                'permission'    => null,
                'default_row'   => 1,
                'default_col'   => 2,
                'default_width' => 'md',
                'icon'          => 'fas fa-bell',
                'skeleton'      => 'list',
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }

        // Auslaufende Verträge Card (TODO-1.13 – View und Composer existieren bereits)
        if (DB::table('dashboard_cards')->where('view', 'personal.employes._expiring_contracts_card')->doesntExist()) {
            DB::table('dashboard_cards')->insert([
                'title'         => 'Auslaufende Verträge',
                'view'          => 'personal.employes._expiring_contracts_card',
                'permission'    => 'view contracts',
                'default_row'   => 2,
                'default_col'   => 0,
                'default_width' => 'md',
                'icon'          => 'fas fa-file-contract',
                'skeleton'      => 'list',
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('dashboard_cards')->whereIn('view', [
            'dashboard.cards.geburtstage',
            'dashboard.cards.tagesinfos',
            'dashboard.cards.benachrichtigungen',
            'personal.employes._expiring_contracts_card',
        ])->delete();
    }
};

