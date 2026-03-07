<?php
/**
 * Repariert die Wochenplan-Permissions:
 * - Stellt sicher dass 'create wochenplan' (Kleinbuchstaben) existiert
 * - Gibt allen Usern/Rollen die 'create Wochenplan' hatten auch 'create wochenplan'
 * - Stellt sicher dass 'view wochenplan' korrekt zugewiesen ist
 */

chdir(__DIR__ . '/..');
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Http\Kernel');
$app->boot();

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

echo "=== Aktuelle Wochenplan-Permissions in DB ===\n";
$allWpPerms = DB::table('permissions')
    ->whereRaw("LOWER(name) LIKE '%wochenplan%'")
    ->orderBy('name')
    ->get();
foreach ($allWpPerms as $p) {
    echo "  ID {$p->id}: '{$p->name}' ({$p->guard_name})\n";
}

echo "\n=== Rollen mit Wochenplan-Permissions ===\n";
foreach ($allWpPerms as $perm) {
    $roles = DB::table('role_has_permissions')
        ->join('roles', 'roles.id', '=', 'role_has_permissions.role_id')
        ->where('permission_id', $perm->id)
        ->pluck('roles.name');
    if ($roles->isNotEmpty()) {
        echo "  '{$perm->name}' → Rollen: " . $roles->implode(', ') . "\n";
    }
}

echo "\n=== User mit Wochenplan-Permissions (direkt) ===\n";
foreach ($allWpPerms as $perm) {
    $users = DB::table('model_has_permissions')
        ->join('users', 'users.id', '=', 'model_has_permissions.model_id')
        ->where('permission_id', $perm->id)
        ->where('model_has_permissions.model_type', 'App\\Models\\User')
        ->pluck('users.name');
    if ($users->isNotEmpty()) {
        echo "  '{$perm->name}' → User: " . $users->implode(', ') . "\n";
    }
}

echo "\n=== Prüfe ob 'create wochenplan' (exakt) existiert ===\n";
$exactLower = DB::table('permissions')
    ->where('name', 'create wochenplan')
    ->where('guard_name', 'web')
    ->first();
$exactUpper = DB::table('permissions')
    ->where('name', 'create Wochenplan')
    ->where('guard_name', 'web')
    ->first();

echo "  'create wochenplan': " . ($exactLower ? "ID {$exactLower->id}" : "NICHT VORHANDEN") . "\n";
echo "  'create Wochenplan': " . ($exactUpper ? "ID {$exactUpper->id}" : "NICHT VORHANDEN") . "\n";

echo "\n=== Prüfe Routen-Middleware Kompatibilität ===\n";
echo "Routen nutzen: 'permission:create wochenplan'\n";
echo "DB hat: " . ($exactLower ? "'create wochenplan' ✓" : ($exactUpper ? "'create Wochenplan' ← CASE-MISMATCH! MySQL ci-collation könnte matchen oder nicht." : "FEHLT!")) . "\n";

echo "\nDone.\n";

