<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Standard-Fächer anlegen
        $faecher = [
            ['name' => 'Deutsch',        'sort_order' => 1, 'is_default' => true],
            ['name' => 'Mathe',          'sort_order' => 2, 'is_default' => true],
            ['name' => 'Sachunterricht', 'sort_order' => 3, 'is_default' => true],
            ['name' => 'Englisch',       'sort_order' => 4, 'is_default' => false],
            ['name' => 'Kunst',          'sort_order' => 5, 'is_default' => false],
            ['name' => 'Musik',          'sort_order' => 6, 'is_default' => false],
            ['name' => 'Sport',          'sort_order' => 7, 'is_default' => false],
            ['name' => 'Ethik/Religion', 'sort_order' => 8, 'is_default' => false],
            ['name' => 'Werken',         'sort_order' => 9, 'is_default' => false],
        ];

        foreach ($faecher as $fach) {
            DB::table('wp_faecher')->insert(array_merge($fach, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // Standard-Formatvorlage anlegen (created_by = erster Admin-User)
        $firstUserId = DB::table('users')->orderBy('id')->value('id') ?? 1;

        $layoutConfig = json_encode([
            'seitenraender' => ['oben' => 20, 'unten' => 20, 'links' => 15, 'rechts' => 15],
            'spalten' => [
                'fach_breite'         => '15%',
                'aufgaben_breite'     => '55%',
                'check_breite'        => '5%',
                'unterschrift_breite' => '25%',
            ],
            'header' => [
                'zeige_name_feld' => true,
                'zeige_klasse'    => true,
                'zeige_zeitraum'  => true,
            ],
            'footer' => [
                'zeige_selbsteinschaetzung' => true,
                'zeige_eltern_unterschrift' => true,
                'zeige_lehrer_unterschrift' => true,
            ],
            'zeige_dauer_spalte' => false,
        ]);

        DB::table('wp_formatvorlagen')->insert([
            'name'           => 'Standard',
            'beschreibung'   => 'Standard-Layout für Wochenpläne (A4, Portrait)',
            'schriftgroesse' => 'normal',
            'schriftart'     => null,
            'layout_config'  => $layoutConfig,
            'blade_template' => 'wochenplan.pdf.standard',
            'is_default'     => true,
            'created_by'     => $firstUserId,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        // Formatvorlage "Große Schrift" anlegen
        $grossConfig = json_encode([
            'seitenraender' => ['oben' => 25, 'unten' => 25, 'links' => 20, 'rechts' => 20],
            'spalten' => [
                'fach_breite'         => '20%',
                'aufgaben_breite'     => '80%',
                'check_breite'        => '0%',
                'unterschrift_breite' => '0%',
            ],
            'header' => [
                'zeige_name_feld' => true,
                'zeige_klasse'    => false,
                'zeige_zeitraum'  => true,
            ],
            'footer' => [
                'zeige_selbsteinschaetzung' => false,
                'zeige_eltern_unterschrift' => false,
                'zeige_lehrer_unterschrift' => false,
            ],
            'zeige_dauer_spalte' => false,
        ]);

        DB::table('wp_formatvorlagen')->insert([
            'name'           => 'Große Schrift',
            'beschreibung'   => 'Für Kinder mit Sehbehinderung – größere Schrift, vereinfachtes Layout',
            'schriftgroesse' => 'gross',
            'schriftart'     => 'Arial, sans-serif',
            'layout_config'  => $grossConfig,
            'blade_template' => 'wochenplan.pdf.standard',
            'is_default'     => false,
            'created_by'     => $firstUserId,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('wp_formatvorlagen')->delete();
        DB::table('wp_faecher')->delete();
    }
};
