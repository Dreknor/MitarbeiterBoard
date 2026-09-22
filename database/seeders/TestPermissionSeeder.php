<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

/**
 * TestPermissionSeeder
 *
 * Legt alle im Projekt verwendeten Permissions an.
 * Wird in Tests über $this->seed(TestPermissionSeeder::class) oder
 * automatisch über RefreshDatabase + DatabaseSeeder verwendet.
 *
 * Alle Permissions sind aus routes/web.php und app/Http/Controllers/** extrahiert.
 */
class TestPermissionSeeder extends Seeder
{
    /**
     * Alle verwendeten Permissions im System.
     */
    public const PERMISSIONS = [
        // ─── Mitarbeiterverwaltung ──────────────────────────────────────────
        'edit employe',
        'has holidays',
        'approve holidays',
        'create roster',
        'has timesheet',

        // ─── Abwesenheiten ──────────────────────────────────────────────────
        'view absences',
        'view old absences',
        'export absence',
        'manage sick_notes',

        // ─── Raumbuchung ────────────────────────────────────────────────────
        'view roomBooking',
        'manage rooms',
        'create roomBooking',

        // ─── Wochenplan ─────────────────────────────────────────────────────
        'create Wochenplan',          // Legacy-Permission (großes W)
        'create wochenplan',
        'view wochenplan',
        'manage wochenplan-faecher',
        'manage wochenplan-formatvorlagen',

        // ─── Klassen & Schüler ──────────────────────────────────────────────
        'edit klassen',

        // ─── Inventar ───────────────────────────────────────────────────────
        'edit inventar',

        // ─── Vertretungsplan ────────────────────────────────────────────────
        'edit vertretungen',

        // ─── Wiki ───────────────────────────────────────────────────────────
        'view wiki',

        // ─── Ticketsystem ───────────────────────────────────────────────────
        'view tickets',
        'edit tickets',

        // ─── Rollen & Berechtigungen ────────────────────────────────────────
        'edit permissions',

        // ─── Thementypen ────────────────────────────────────────────────────
        'create types',

        // ─── Einstellungen & Logs ───────────────────────────────────────────
        'edit settings',
        'view logs',

        // ─── Pädagogisches Tagebuch & Diagnostik ───────────────────────────
        'view paed diary',
        'manage grading systems',
        'view diagnostics',
        'manage diagnostics',

        // ─── Meetings & Themen ──────────────────────────────────────────────
        'manage recurring themes',
        'unarchive theme',

        // ─── Benutzer ───────────────────────────────────────────────────────
        'edit users',

        // ─── Prozesse ───────────────────────────────────────────────────────
        'manage procedures',

        // ─── System ─────────────────────────────────────────────────────────
        'make updates',
    ];

    /**
     * Alle Permissions anlegen (idempotent – kann mehrfach aufgerufen werden).
     */
    public function run(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }
}

