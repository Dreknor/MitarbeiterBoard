<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_bookings', function (Blueprint $table) {
            $table->foreignId('meeting_id')
                ->nullable()
                ->after('room_id')
                ->constrained('meetings')
                ->nullOnDelete();

            $table->index('meeting_id', 'idx_room_bookings_meeting_id');
            $table->index(['room_id', 'booking_date', 'start', 'end'], 'idx_room_bookings_slot');
        });

        \Spatie\Permission\Models\Permission::findOrCreate('create roomBooking', 'web');
    }

    public function down(): void
    {
        Schema::table('room_bookings', function (Blueprint $table) {
            $table->dropIndex('idx_room_bookings_slot');
            $table->dropIndex('idx_room_bookings_meeting_id');
            $table->dropConstrainedForeignId('meeting_id');
        });

        \Spatie\Permission\Models\Permission::where('name', 'create roomBooking')->delete();
    }
};

