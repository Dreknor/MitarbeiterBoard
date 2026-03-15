<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Spatie Permission Cache zurücksetzen
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Verhindert echte HTTP-Requests in Tests – schützt vor ungewollten API-Calls
        // die den Test-Prozess zum Hängen bringen könnten.
        // Tests die HTTP benötigen, müssen Http::fake() explizit aufrufen.
        Http::preventStrayRequests();
    }

    /**
     * Erstellt einen User mit den angegebenen Permissions und meldet ihn an.
     */
    protected function actingAsWithPermission(string ...$permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $perm) {
            Permission::findOrCreate($perm);
        }

        $user->givePermissionTo($permissions);

        // Permission-Cache nach dem Zuweisen zurücksetzen
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->actingAs($user);

        return $user;
    }

    /**
     * Erstellt einen User mit den angegebenen Permissions, ohne ihn einzuloggen.
     * Nützlich für Tests mit mehreren Usern.
     */
    protected function createUserWithPermission(string ...$permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $perm) {
            Permission::findOrCreate($perm);
        }

        $user->givePermissionTo($permissions);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return $user;
    }
}
