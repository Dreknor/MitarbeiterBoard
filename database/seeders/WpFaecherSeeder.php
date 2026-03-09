<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Wochenplan\WpFach;

class WpFaecherSeeder extends Seeder
{
    public function run(): void
    {
        $faecher = [
            ['name' => 'Deutsch',          'sort_order' => 1,  'is_default' => true,  'symbol_typ' => 'emoji', 'symbol_wert' => '📖', 'symbol_farbe' => '#3b82f6'],
            ['name' => 'Mathe',            'sort_order' => 2,  'is_default' => true,  'symbol_typ' => 'emoji', 'symbol_wert' => '🔢', 'symbol_farbe' => '#ef4444'],
            ['name' => 'Sachunterricht',   'sort_order' => 3,  'is_default' => true,  'symbol_typ' => 'emoji', 'symbol_wert' => '🌍', 'symbol_farbe' => '#22c55e'],
            ['name' => 'Englisch',         'sort_order' => 4,  'is_default' => false, 'symbol_typ' => 'emoji', 'symbol_wert' => '🇬🇧', 'symbol_farbe' => '#8b5cf6'],
            ['name' => 'Kunst',            'sort_order' => 5,  'is_default' => false, 'symbol_typ' => 'emoji', 'symbol_wert' => '🎨', 'symbol_farbe' => '#f97316'],
            ['name' => 'Musik',            'sort_order' => 6,  'is_default' => false, 'symbol_typ' => 'emoji', 'symbol_wert' => '🎵', 'symbol_farbe' => '#ec4899'],
            ['name' => 'Sport',            'sort_order' => 7,  'is_default' => false, 'symbol_typ' => 'emoji', 'symbol_wert' => '⚽', 'symbol_farbe' => '#14b8a6'],
            ['name' => 'Ethik/Religion',   'sort_order' => 8,  'is_default' => false, 'symbol_typ' => 'emoji', 'symbol_wert' => '🕊️', 'symbol_farbe' => '#a78bfa'],
            ['name' => 'Werken',           'sort_order' => 9,  'is_default' => false, 'symbol_typ' => 'emoji', 'symbol_wert' => '🔨', 'symbol_farbe' => '#78716c'],
        ];

        foreach ($faecher as $fach) {
            WpFach::updateOrCreate(
                ['name' => $fach['name']],
                $fach
            );
        }
    }
}

