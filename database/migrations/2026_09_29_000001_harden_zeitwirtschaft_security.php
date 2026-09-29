<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Zeitwirtschaft (Dienstplan, Urlaub, Arbeitszeitnachweis) – Sicherheits-Härtung (P0)
 *
 * - PIN der Zeiterfassung wird gehasht gespeichert (Spalte verlängert, Bestand gehasht).
 * - Neue Bereichs-Rechte: "approve all holidays" und "manage all rosters".
 *   Damit sich das Verhalten bestehender Installationen nicht ändert, erhalten alle
 *   Rollen/Benutzer mit "approve holidays" bzw. "create roster" das neue Recht.
 *   Wer die Rechte einschränken möchte, entzieht es anschließend gezielt – dann gilt
 *   die Vorgesetzten- bzw. Abteilungsprüfung.
 * - Setting für den Geräte-Token des Zeiterfassungs-Terminals.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employes_data', function (Blueprint $table) {
            $table->string('secret_key', 255)->nullable()->change();
        });

        DB::table('employes_data')->whereNotNull('secret_key')->orderBy('id')->each(function ($row) {
            if ($row->secret_key !== '' && !str_starts_with($row->secret_key, '$2y$') && !str_starts_with($row->secret_key, '$argon')) {
                DB::table('employes_data')->where('id', $row->id)->update(['secret_key' => Hash::make($row->secret_key)]);
            }
        });

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->grantAlongside('approve holidays', 'approve all holidays');
        $this->grantAlongside('create roster', 'manage all rosters');

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        if (!Setting::where('setting', 'time_recording_terminal_token')->exists()) {
            Setting::create([
                'module' => 'Zeiterfassung',
                'setting' => 'time_recording_terminal_token',
                'setting_name' => 'Geräte-Token des Terminals',
                'type' => 'string',
                'value' => '',
                'description' => 'Wenn gesetzt, ist das Zeiterfassungs-Terminal nur auf Geräten nutzbar, die einmalig /time_recording/start?token=<Token> aufgerufen haben.',
            ]);
        }
    }

    public function down(): void
    {
        Permission::where('name', 'approve all holidays')->delete();
        Permission::where('name', 'manage all rosters')->delete();
        Setting::where('setting', 'time_recording_terminal_token')->delete();
        // Gehashte PINs lassen sich nicht zurückwandeln.
    }

    private function grantAlongside(string $existing, string $new): void
    {
        $permission = Permission::findOrCreate($new, 'web');
        $source = Permission::where('name', $existing)->where('guard_name', 'web')->first();

        if ($source === null) {
            return;
        }

        foreach (Role::whereHas('permissions', fn ($q) => $q->where('id', $source->id))->get() as $role) {
            $role->givePermissionTo($permission);
        }

        $modelIds = DB::table('model_has_permissions')->where('permission_id', $source->id)->get();
        foreach ($modelIds as $row) {
            DB::table('model_has_permissions')->insertOrIgnore([
                'permission_id' => $permission->id,
                'model_type' => $row->model_type,
                'model_id' => $row->model_id,
            ]);
        }
    }
};
