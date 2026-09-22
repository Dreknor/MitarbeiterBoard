<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Http\Kernel::class);
$app->boot();

echo "=== Permissions in DB ===\n";
$perms = DB::table('permissions')->where('name', 'like', '%wochenplan%')->get();
foreach ($perms as $p) {
    echo "  [{$p->id}] {$p->name} ({$p->guard_name})\n";
}

echo "\n=== Users mit Wochenplan-Permission ===\n";
$users = App\Models\User::with('permissions', 'roles.permissions')->get();
foreach ($users as $u) {
    $perms = $u->getAllPermissions()->pluck('name')->filter(fn($n) => str_contains(strtolower($n), 'wochenplan'))->values();
    if ($perms->isNotEmpty()) {
        echo "  {$u->name}: " . implode(', ', $perms->toArray()) . "\n";
    }
}

echo "\n=== Alle User (erste 5) ===\n";
foreach ($users->take(5) as $u) {
    $allPerms = $u->getAllPermissions()->pluck('name')->values();
    echo "  {$u->name}: " . $allPerms->count() . " Permissions, Roles: " . $u->roles->pluck('name')->implode(', ') . "\n";
}

