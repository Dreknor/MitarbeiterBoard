<?php

namespace Tests\Unit\Providers;

use App\Services\Personal\Contracts\NextcloudFileServiceInterface;
use App\Services\Personal\NextcloudFileService;
use App\Services\Personal\PersonalScopeService;
use Tests\Fakes\FakeNextcloudFileService;
use Tests\TestCase;

class PersonalServiceProviderTest extends TestCase
{
    /** @test */
    public function it_resolves_nextcloud_service_interface(): void
    {
        $service = $this->app->make(NextcloudFileServiceInterface::class);
        $this->assertInstanceOf(NextcloudFileServiceInterface::class, $service);
        $this->assertInstanceOf(NextcloudFileService::class, $service);
    }

    /** @test */
    public function scope_service_is_singleton(): void
    {
        $a = $this->app->make(PersonalScopeService::class);
        $b = $this->app->make(PersonalScopeService::class);
        $this->assertSame($a, $b);
    }

    /** @test */
    public function fake_nextcloud_service_can_be_swapped(): void
    {
        $this->app->bind(
            NextcloudFileServiceInterface::class,
            FakeNextcloudFileService::class
        );
        $service = $this->app->make(NextcloudFileServiceInterface::class);
        $this->assertInstanceOf(FakeNextcloudFileService::class, $service);
    }
}

