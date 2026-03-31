<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Scope-Permissions
            'view personal_data',
            'view personal_data:department',
            'view personal_data:all',
            'edit personal_data:department',
            'edit personal_data:all',

            // Vertragsmanagement
            'view contracts', 'edit contracts',
            'view salary',    'edit salary',

            // Dokumentenmanagement
            'view personal_documents', 'manage personal_documents',
            'create personal_documents', 'manage document_templates',

            // Qualifikationen
            'view qualifications', 'manage qualifications',
            'view trainings',      'manage trainings', 'approve trainings',

            // Gespräche
            'view own reviews', 'manage reviews', 'view all reviews',

            // Organigramm
            'view orgchart', 'manage orgchart', 'export orgchart',

            // Reporting
            'view personal_reports', 'export personal_reports',

            // BEM
            'view bem', 'manage bem',

            // Erinnerungen & Datenschutz
            'manage personal_reminders',
            'manage retention_policies',
            'view personal_audit',
            'manage personal_consents',
        ];

        foreach ($permissions as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        // --- Rollen anlegen und Permissions zuweisen ---

        $mitarbeiter = Role::findOrCreate('Mitarbeiter', 'web');
        $mitarbeiter->givePermissionTo([
            'view personal_data',
            'view own reviews',
            'view qualifications',
            'view trainings',
            'view orgchart',
            'export orgchart',
            'manage personal_consents',
        ]);

        $abteilungsleiter = Role::findOrCreate('Abteilungsleiter', 'web');
        $abteilungsleiter->givePermissionTo([
            'view personal_data',
            'view personal_data:department',
            'view contracts',
            'view qualifications',
            'view trainings',
            'approve trainings',
            'manage reviews',
            'view own reviews',
            'view orgchart',
            'export orgchart',
            'manage personal_consents',
        ]);

        $personalleitung = Role::findOrCreate('Personalleitung', 'web');
        $personalleitung->givePermissionTo($permissions);

        $schulleitung = Role::findOrCreate('Schulleitung', 'web');
        $schulleitung->givePermissionTo([
            'view personal_data',
            'view personal_data:department',
            'view personal_data:all',
            'view contracts',
            'view salary',
            'view personal_documents',
            'create personal_documents',
            'view qualifications',
            'view trainings',
            'manage reviews',
            'view all reviews',
            'view own reviews',
            'view orgchart',
            'manage orgchart',
            'export orgchart',
            'view personal_reports',
            'export personal_reports',
            'view bem',
            'manage personal_consents',
        ]);
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'view personal_data', 'view personal_data:department', 'view personal_data:all',
            'edit personal_data:department', 'edit personal_data:all',
            'view contracts', 'edit contracts', 'view salary', 'edit salary',
            'view personal_documents', 'manage personal_documents',
            'create personal_documents', 'manage document_templates',
            'view qualifications', 'manage qualifications',
            'view trainings', 'manage trainings', 'approve trainings',
            'view own reviews', 'manage reviews', 'view all reviews',
            'view orgchart', 'manage orgchart', 'export orgchart',
            'view personal_reports', 'export personal_reports',
            'view bem', 'manage bem',
            'manage personal_reminders',
            'manage retention_policies',
            'view personal_audit',
            'manage personal_consents',
        ];

        foreach ($permissions as $perm) {
            Permission::findByName($perm, 'web')?->delete();
        }

        foreach (['Mitarbeiter', 'Abteilungsleiter', 'Personalleitung', 'Schulleitung'] as $roleName) {
            Role::findByName($roleName, 'web')?->delete();
        }
    }
};

