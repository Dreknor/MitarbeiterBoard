<?php

namespace Tests\Unit\Services\Personal;

use App\Models\Group;
use App\Models\personal\Employment;
use App\Models\User;
use App\Services\Personal\PersonalScopeService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PersonalScopeServiceTest extends TestCase
{
    private PersonalScopeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PersonalScopeService::class);
    }

    /** @test */
    public function self_scope_returns_only_own_user(): void
    {
        $user  = $this->actingAsWithPermission('view personal_data');
        $other = User::factory()->create();

        $visible = $this->service->visibleEmployees()->pluck('id');

        $this->assertContains($user->id, $visible);
        $this->assertNotContains($other->id, $visible);
    }

    /** @test */
    public function all_scope_returns_all_users(): void
    {
        $this->actingAsWithPermission('view personal_data', 'view personal_data:all');
        User::factory()->count(3)->create();

        $visible = $this->service->visibleEmployees()->count();
        $this->assertGreaterThanOrEqual(4, $visible);
    }

    /** @test */
    public function get_scope_returns_self_for_basic_permission(): void
    {
        $user = $this->actingAsWithPermission('view personal_data');
        $this->assertEquals('self', $this->service->getScope($user));
    }

    /** @test */
    public function get_scope_returns_all_for_all_permission(): void
    {
        $user = $this->actingAsWithPermission('view personal_data:all');
        $this->assertEquals('all', $this->service->getScope($user));
    }

    /** @test */
    public function get_scope_returns_department_for_department_permission(): void
    {
        $user = $this->actingAsWithPermission('view personal_data:department');
        $this->assertEquals('department', $this->service->getScope($user));
    }

    /** @test */
    public function scope_is_cached(): void
    {
        $user = $this->actingAsWithPermission('view personal_data');
        $this->service->getScope($user);

        $this->assertTrue(Cache::has("personal_scope_{$user->id}_view"));
    }

    /** @test */
    public function cache_is_invalidated(): void
    {
        $user = $this->actingAsWithPermission('view personal_data');
        $this->service->getScope($user);

        $this->service->invalidateCache($user);

        $this->assertFalse(Cache::has("personal_scope_{$user->id}_view"));
        $this->assertFalse(Cache::has("personal_scope_{$user->id}_edit"));
    }

    /** @test */
    public function edit_scope_differs_from_view_scope(): void
    {
        $user = $this->actingAsWithPermission('view personal_data', 'view personal_data:all');
        // Kein 'edit personal_data:all' → edit-Scope ist 'self'

        $this->assertEquals('all',  $this->service->getScope($user, 'view'));
        $this->assertEquals('self', $this->service->getScope($user, 'edit'));
    }

    /** @test */
    public function can_access_own_data(): void
    {
        $user = $this->actingAsWithPermission('view personal_data');

        $this->assertTrue($this->service->canAccess($user, $user));
    }

    /** @test */
    public function cannot_access_foreign_data_without_scope(): void
    {
        $user  = $this->actingAsWithPermission('view personal_data');
        $other = User::factory()->create();

        $this->assertFalse($this->service->canAccess($user, $other));
    }

    /** @test */
    public function can_access_foreign_data_with_all_scope(): void
    {
        $user  = $this->actingAsWithPermission('view personal_data:all');
        $other = User::factory()->create();

        $this->assertTrue($this->service->canAccess($user, $other));
    }
}

