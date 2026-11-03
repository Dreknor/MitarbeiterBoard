<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Gruppenunabhängige Meetings.
 *
 * - meetings.group_id wird optional (freie Besprechungen ohne Gruppe)
 * - meetings erhalten Ersteller, eigenen Meeting-Link und Ort
 * - meeting_participants: eingeladene Personen, Gruppen (Bereiche) und Rollen (polymorph)
 * - themes.group_id wird optional (freie Meeting-Themen)
 * - Recht "create free meetings" für das Anlegen gruppenunabhängiger Meetings
 */
return new class extends Migration
{
    private array $permissions = [
        'create free meetings',
    ];

    public function up(): void
    {
        $isSqlite = DB::getDriverName() === 'sqlite';

        Schema::table('meetings', function (Blueprint $table) use ($isSqlite) {
            if (! $isSqlite) {
                $table->dropForeign(['group_id']);
            }
        });

        Schema::table('meetings', function (Blueprint $table) use ($isSqlite) {
            $table->unsignedBigInteger('group_id')->nullable()->change();
            $table->foreignId('creator_id')->nullable()->after('group_id')->constrained('users')->nullOnDelete();
            $table->string('location')->nullable()->after('end_time');
            $table->string('meeting_url')->nullable()->after('location');

            if (! $isSqlite) {
                $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
            }
        });

        Schema::create('meeting_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->string('participant_type');
            $table->unsignedBigInteger('participant_id');
            $table->boolean('is_organizer')->default(false);
            $table->timestamps();

            $table->unique(['meeting_id', 'participant_type', 'participant_id'], 'meeting_participant_unique');
            $table->index(['participant_type', 'participant_id']);
        });

        Schema::table('themes', function (Blueprint $table) {
            $table->unsignedBigInteger('group_id')->nullable()->change();
        });

        // Bestehende Meetings: Ersteller ist unbekannt, bleibt null.

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->permissions as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        foreach (['Admin', 'Schulleitung'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            $role?->givePermissionTo($this->permissions);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        $isSqlite = DB::getDriverName() === 'sqlite';

        Schema::dropIfExists('meeting_participants');

        // Freie Meetings/Themen können ohne Gruppe nicht zurückgeführt werden.
        DB::table('meetings')->whereNull('group_id')->delete();

        Schema::table('meetings', function (Blueprint $table) use ($isSqlite) {
            $table->dropConstrainedForeignId('creator_id');
            $table->dropColumn(['location', 'meeting_url']);
        });

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::where('guard_name', 'web')->whereIn('name', $this->permissions)->delete();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
