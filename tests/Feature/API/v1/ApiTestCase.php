<?php

namespace Tests\Feature\API\v1;

use App\Models\Klasse;
use App\Models\Schueler;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Basisklasse für Feature-Tests der API v1 (Pädagogen-App).
 */
abstract class ApiTestCase extends TestCase
{
    protected const API = '/api/v1';

    /**
     * Legt eine Lehrkraft mit Permissions an, ordnet ihr Klassen zu und authentifiziert sie via Sanctum.
     */
    protected function actingAsTeacher(array $permissions = ['view paed diary'], array $klassen = []): User
    {
        $user = $this->createTeacher($permissions, $klassen);
        Sanctum::actingAs($user, ['paed-app']);

        return $user;
    }

    protected function createTeacher(array $permissions = ['view paed diary'], array $klassen = []): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $perm) {
            Permission::findOrCreate($perm, 'web');
        }
        $user->givePermissionTo($permissions);

        foreach ($klassen as $klasse) {
            $user->paed_klassen()->attach($klasse->id);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return $user;
    }

    protected function actingAsAdmin(array $permissions = ['view paed diary']): User
    {
        $user = $this->createTeacher($permissions);
        $role = Role::findOrCreate('Admin', 'web');
        $user->assignRole($role);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Sanctum::actingAs($user, ['paed-app']);

        return $user;
    }

    /**
     * @return array{0: Klasse, 1: Schueler}
     */
    protected function classWithStudent(array $schuelerAttributes = []): array
    {
        $klasse = Klasse::factory()->create();
        $schueler = Schueler::factory()->create(array_merge(['klasse_id' => $klasse->id], $schuelerAttributes));

        return [$klasse, $schueler];
    }
}
