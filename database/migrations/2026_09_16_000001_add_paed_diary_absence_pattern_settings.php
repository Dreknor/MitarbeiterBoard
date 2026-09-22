<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $settings = [
            [
                'setting' => 'paed_diary_absence_pattern_total_absence_days_threshold',
                'setting_name' => 'Gesamtfehltage: Schwellenwert Tage',
                'type' => 'integer',
                'value' => 10,
                'description' => 'Mindestanzahl von Fehltagen innerhalb des Betrachtungszeitraums, ab der ein Muster als auffällig gilt.',
            ],
            [
                'setting' => 'paed_diary_absence_pattern_total_absence_percent_threshold',
                'setting_name' => 'Gesamtfehltage: Schwellenwert Prozent',
                'type' => 'float',
                'value' => 10,
                'description' => 'Prozentualer Anteil der schulischen Tage im Zeitraum, ab dem eine Gesamtfehlens-Häufung als auffällig gewertet wird.',
            ],
            [
                'setting' => 'paed_diary_absence_pattern_total_absence_window_days',
                'setting_name' => 'Gesamtfehltage: Betrachtungszeitraum Tage',
                'type' => 'integer',
                'value' => 28,
                'description' => 'Betrachtungszeitraum in Tagen für die Gesamtfehlens-Häufung.',
            ],
            [
                'setting' => 'paed_diary_absence_pattern_short_cluster_count',
                'setting_name' => 'Kurzzeit-Häufung: Mindestanzahl',
                'type' => 'integer',
                'value' => 3,
                'description' => 'Mindestanzahl kurzer Abwesenheiten (1–2 Tage) innerhalb des Zeitfensters, ab der ein Muster als auffällig gilt.',
            ],
            [
                'setting' => 'paed_diary_absence_pattern_short_cluster_max_days',
                'setting_name' => 'Kurzzeit-Häufung: Maximaldauer Tage',
                'type' => 'integer',
                'value' => 2,
                'description' => 'Maximale Dauer einer kurzen Abwesenheit, die als Kurzzeit-Häufung gezählt wird.',
            ],
            [
                'setting' => 'paed_diary_absence_pattern_short_cluster_window_days',
                'setting_name' => 'Kurzzeit-Häufung: Zeitfenster Tage',
                'type' => 'integer',
                'value' => 30,
                'description' => 'Zeitfenster in Tagen, in dem kurze Abwesenheiten gezählt werden.',
            ],
            [
                'setting' => 'paed_diary_absence_pattern_weekday_threshold',
                'setting_name' => 'Wochentagsmuster: Mindestanzahl',
                'type' => 'integer',
                'value' => 3,
                'description' => 'Mindestanzahl an Fehlzeiten an einem Wochentag innerhalb des Betrachtungsfensters, ab der ein Wochentagsmuster als auffällig gilt.',
            ],
            [
                'setting' => 'paed_diary_absence_pattern_weekday_window_days',
                'setting_name' => 'Wochentagsmuster: Betrachtungszeitraum Tage',
                'type' => 'integer',
                'value' => 42,
                'description' => 'Betrachtungszeitraum in Tagen für Wochentagsmuster.',
            ],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['setting' => $setting['setting']],
                [
                    'module' => 'paed_diary',
                    'setting_name' => $setting['setting_name'],
                    'type' => $setting['type'],
                    'value' => $setting['value'],
                    'description' => $setting['description'],
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $settings = [
            'paed_diary_absence_pattern_total_absence_days_threshold',
            'paed_diary_absence_pattern_total_absence_percent_threshold',
            'paed_diary_absence_pattern_total_absence_window_days',
            'paed_diary_absence_pattern_short_cluster_count',
            'paed_diary_absence_pattern_short_cluster_max_days',
            'paed_diary_absence_pattern_short_cluster_window_days',
            'paed_diary_absence_pattern_weekday_threshold',
            'paed_diary_absence_pattern_weekday_window_days',
        ];

        Setting::whereIn('setting', $settings)->delete();
    }
};
