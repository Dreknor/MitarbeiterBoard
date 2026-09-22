<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('dashboard_cards')->where('view', 'dashboard.cards.paed_diary')->doesntExist()) {
            DB::table('dashboard_cards')->insert([
                'title'         => 'Pädagogisches Tagebuch',
                'view'          => 'dashboard.cards.paed_diary',
                'permission'    => 'view paed diary',
                'default_row'   => 4,
                'default_col'   => 0,
                'default_width' => 'md',
                'icon'          => 'fas fa-book-reader',
                'skeleton'      => 'list',
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('dashboard_cards')
            ->where('view', 'dashboard.cards.paed_diary')
            ->delete();
    }
};

