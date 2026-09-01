<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Arbeitspaket 4: Berechtigungen für die Prüfengine (Web-Controller & Frontend).
 * Neu (siehe 2026_04_01_000001_add_personal_p1_permissions.php für die Rollendefinitionen
 * Mitarbeiter/Abteilungsleiter/Personalleitung/Schulleitung): Die Permissions werden zusätzlich
 * den bestehenden Personal-Rollen zugewiesen, damit die Prüfengine tatsächlich nutzbar ist.
 */
return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'view timesheet anomalies',
            'resolve timesheet anomalies',
            'run timesheet validation',
        ];

        foreach ($permissions as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        // Personalleitung: volle Kontrolle über die Prüfengine
        Role::findByName('Personalleitung', 'web')?->givePermissionTo($permissions);

        // Abteilungsleiter: dürfen für ihre Abteilung prüfen und die Ergebnisse einsehen
        Role::findByName('Abteilungsleiter', 'web')?->givePermissionTo([
            'view timesheet anomalies',
            'run timesheet validation',
        ]);

        // Schulleitung: reines Einsichtsrecht (übergeordnete Kontrolle)
        Role::findByName('Schulleitung', 'web')?->givePermissionTo([
            'view timesheet anomalies',
        ]);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::where('guard_name', 'web')
            ->whereIn('name', [
                'view timesheet anomalies',
                'resolve timesheet anomalies',
                'run timesheet validation',
            ])
            ->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};


