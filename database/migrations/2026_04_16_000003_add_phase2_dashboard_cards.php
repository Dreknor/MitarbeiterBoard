<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Neue Tabelle user_quicklinks (N10 – Schnellzugriff)
        Schema::create('user_quicklinks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('label');
            $table->string('url');
            $table->string('icon')->default('fas fa-link')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        // 2. Neue Dashboard-Cards für Phase 2 registrieren
        $newCards = [
            [
                'title'         => 'Nächste Meetings',
                'view'          => 'dashboard.cards.meetings',
                'permission'    => null,
                'default_row'   => 3,
                'default_col'   => 0,
                'default_width' => 'md',
                'icon'          => 'fas fa-users',
                'skeleton'      => 'list',
            ],
            [
                'title'         => 'Offene Terminlisten',
                'view'          => 'dashboard.cards.terminlisten',
                'permission'    => 'see terminlisten',
                'default_row'   => 3,
                'default_col'   => 1,
                'default_width' => 'sm',
                'icon'          => 'fas fa-clipboard-list',
                'skeleton'      => 'list',
            ],
            [
                'title'         => 'Qualifikationen & Fortbildungen',
                'view'          => 'dashboard.cards.qualifikationen',
                'permission'    => null,
                'default_row'   => 3,
                'default_col'   => 2,
                'default_width' => 'md',
                'icon'          => 'fas fa-graduation-cap',
                'skeleton'      => 'list',
            ],
            [
                'title'         => 'Schnellzugriff',
                'view'          => 'dashboard.cards.schnellzugriff',
                'permission'    => null,
                'default_row'   => 3,
                'default_col'   => 3,
                'default_width' => 'sm',
                'icon'          => 'fas fa-star',
                'skeleton'      => 'default',
            ],
        ];

        foreach ($newCards as $card) {
            if (DB::table('dashboard_cards')->where('view', $card['view'])->doesntExist()) {
                DB::table('dashboard_cards')->insert(array_merge($card, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_quicklinks');

        DB::table('dashboard_cards')->whereIn('view', [
            'dashboard.cards.meetings',
            'dashboard.cards.terminlisten',
            'dashboard.cards.qualifikationen',
            'dashboard.cards.schnellzugriff',
        ])->delete();
    }
};

