<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ausnahmen für einzelne Termine in wiederkehrenden Serien.
     * Wenn ein Eintrag existiert, wird das Vorkommen am exception_date übersprungen.
     */
    public function up(): void
    {
        if (!Schema::hasTable('paed_diary_appointment_exceptions')) {
            Schema::create('paed_diary_appointment_exceptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('appointment_id')
                    ->constrained('paed_diary_appointments')
                    ->cascadeOnDelete();
                $table->date('exception_date');
                $table->timestamps();

                $table->unique(['appointment_id', 'exception_date'], 'uk_pda_exception');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('paed_diary_appointment_exceptions');
    }
};

