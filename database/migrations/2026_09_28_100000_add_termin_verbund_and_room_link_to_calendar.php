<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Kalender-Überarbeitung:
 * - Terminverbund: ein Termin kann in mehreren OX-Kalendern liegen. Jede Kopie ist
 *   ein eigenes OX-Event (eigene UID), verknüpft über verbund_uid (+ X-MB-VERBUND im iCal).
 * - Raumbuchung: room_bookings.ox_verbund_uid verknüpft eine Buchung mit einem Terminverbund.
 * - Permission für den ICS-Import.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ox_termine', function (Blueprint $table) {
            $table->string('verbund_uid', 64)->nullable()->after('ox_uid');
            $table->index('verbund_uid', 'idx_ox_termine_verbund');
        });

        Schema::table('room_bookings', function (Blueprint $table) {
            $table->string('ox_verbund_uid', 64)->nullable()->after('meeting_id');
            $table->index('ox_verbund_uid', 'idx_room_bookings_ox_verbund');
        });

        $import = Permission::findOrCreate('import calendar events', 'web');

        // Wer bisher Termine anlegen durfte, darf auch importieren.
        Role::query()
            ->whereHas('permissions', fn ($q) => $q->where('name', 'create calendar events'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($import));

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('room_bookings', function (Blueprint $table) {
            $table->dropIndex('idx_room_bookings_ox_verbund');
            $table->dropColumn('ox_verbund_uid');
        });

        Schema::table('ox_termine', function (Blueprint $table) {
            $table->dropIndex('idx_ox_termine_verbund');
            $table->dropColumn('verbund_uid');
        });

        Permission::where('name', 'import calendar events')->delete();
    }
};
