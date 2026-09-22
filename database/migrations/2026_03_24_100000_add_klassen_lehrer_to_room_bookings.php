<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_bookings', function (Blueprint $table) {
            $table->string('klassen')->nullable()->after('name')
                ->comment('Klassen-Kürzel (kommagetrennt), z.B. "5a, 6b"');
            $table->string('lehrer')->nullable()->after('klassen')
                ->comment('Lehrer-Kürzel (kommagetrennt), z.B. "Mül, Sch"');
        });
    }

    public function down(): void
    {
        Schema::table('room_bookings', function (Blueprint $table) {
            $table->dropColumn(['klassen', 'lehrer']);
        });
    }
};

