<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_times', function (Blueprint $table) {
            $table->id();
            $table->integer('period')->comment('Stundennummer (1, 2, 3, ...)');
            $table->time('start')->comment('Beginn der Stunde, z.B. 07:30');
            $table->time('end')->comment('Ende der Stunde, z.B. 08:15');
            $table->string('week', 5)->nullable()->comment('A/B-Woche oder null für alle Wochen');
            $table->timestamps();

            // Unique: Ein Stunden-Slot pro Wochen-Typ (NULL = alle Wochen)
            // MySQL erlaubt mehrere NULLs in UNIQUE-Spalten → eigener Index
            $table->unique(['period', 'week'], 'unique_period_week');
        });

        // Standard-Zeitraster seedn (kann über Admin-Seite überschrieben werden)
        $defaultTimes = [
            ['period' => 1, 'start' => '07:30', 'end' => '08:15', 'week' => null],
            ['period' => 2, 'start' => '08:25', 'end' => '09:10', 'week' => null],
            ['period' => 3, 'start' => '09:30', 'end' => '10:15', 'week' => null],
            ['period' => 4, 'start' => '10:25', 'end' => '11:10', 'week' => null],
            ['period' => 5, 'start' => '11:30', 'end' => '12:15', 'week' => null],
            ['period' => 6, 'start' => '12:20', 'end' => '13:05', 'week' => null],
            ['period' => 7, 'start' => '13:35', 'end' => '14:20', 'week' => null],
            ['period' => 8, 'start' => '14:25', 'end' => '15:10', 'week' => null],
        ];

        foreach ($defaultTimes as $time) {
            \App\Models\LessonTime::create($time);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_times');
    }
};

