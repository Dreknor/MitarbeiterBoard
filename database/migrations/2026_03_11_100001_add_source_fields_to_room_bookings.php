<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Schritt 1: FK droppen, users_id nullable machen, FK neu setzen
        Schema::table('room_bookings', function (Blueprint $table) {
            $table->dropForeign(['users_id']);
        });

        Schema::table('room_bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('users_id')->nullable()->change();
        });

        Schema::table('room_bookings', function (Blueprint $table) {
            $table->foreign('users_id')->references('id')->on('users')->nullOnDelete();
        });

        // Schritt 2: Neue Felder ergänzen
        Schema::table('room_bookings', function (Blueprint $table) {
            $table->string('source', 20)->default('manual')
                ->after('booking_date')
                ->comment('Herkunft: manual | indiware_xml | indiware_vp');

            $table->string('source_id')->nullable()
                ->after('source')
                ->comment('Externe ID für Duplikat-Erkennung (z.B. Ak_Id aus Indiware)');

            $table->boolean('cancelled')->default(false)
                ->after('source_id')
                ->comment('true = Stornierung, Raum wurde durch VP freigegeben');

            $table->index(['source', 'source_id'], 'idx_room_bookings_source');
            $table->index(['source', 'booking_date'], 'idx_room_bookings_source_date');
        });
    }

    public function down(): void
    {
        Schema::table('room_bookings', function (Blueprint $table) {
            $table->dropIndex('idx_room_bookings_source');
            $table->dropIndex('idx_room_bookings_source_date');
            $table->dropColumn(['source', 'source_id', 'cancelled']);
        });

        // FK zurücksetzen auf NOT NULL
        Schema::table('room_bookings', function (Blueprint $table) {
            $table->dropForeign(['users_id']);
        });
        Schema::table('room_bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('users_id')->nullable(false)->change();
        });
        Schema::table('room_bookings', function (Blueprint $table) {
            $table->foreign('users_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};

