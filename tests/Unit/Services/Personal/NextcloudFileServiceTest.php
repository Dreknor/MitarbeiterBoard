<?php

namespace Tests\Unit\Services\Personal;

use App\Services\Personal\Contracts\NextcloudFileServiceInterface;
use App\Services\Personal\NextcloudFileService;
use Tests\Fakes\FakeNextcloudFileService;
use Tests\TestCase;

class NextcloudFileServiceTest extends TestCase
{
    /** @test */
    public function fake_records_upload_operations(): void
    {
        $fake = new FakeNextcloudFileService();
        $fake->uploadFile('/local/test.pdf', '/Personal/Hort/Angestellt/test.pdf');
        $fake->assertUploaded('/Personal/Hort/Angestellt/test.pdf');
    }

    /** @test */
    public function fake_records_directory_creation(): void
    {
        $fake = new FakeNextcloudFileService();
        $fake->ensureDirectoryExists('/Personal/Hort/Angestellt');
        $fake->assertDirectoryCreated('/Personal/Hort/Angestellt');
    }

    /** @test */
    public function fake_records_move_operations(): void
    {
        $fake = new FakeNextcloudFileService();
        $fake->moveDirectory('/Personal/Hort/Angestellt/Mustermann', '/Personal/Hort/Ausgeschieden/Mustermann');
        $fake->assertMoved('/Personal/Hort/Angestellt/Mustermann', '/Personal/Hort/Ausgeschieden/Mustermann');
    }

    /** @test */
    public function fake_records_delete_operations(): void
    {
        $fake = new FakeNextcloudFileService();
        $fake->deleteFile('/Personal/Hort/Angestellt/test.pdf');
        $fake->assertDeleted('/Personal/Hort/Angestellt/test.pdf');
    }

    /** @test */
    public function fake_can_seed_file_content(): void
    {
        $fake = new FakeNextcloudFileService();
        $fake->seedFileContent('/Personal/test.pdf', 'PDF-Inhalt');

        $this->assertEquals('PDF-Inhalt', $fake->downloadFile('/Personal/test.pdf'));
        $this->assertTrue($fake->exists('/Personal/test.pdf'));
    }

    /** @test */
    public function fake_download_returns_false_for_missing_files(): void
    {
        $fake = new FakeNextcloudFileService();
        $this->assertFalse($fake->downloadFile('/Personal/nicht-vorhanden.pdf'));
    }

    /** @test */
    public function fake_can_be_swapped_via_service_container(): void
    {
        $fake = new FakeNextcloudFileService();
        $this->app->instance(NextcloudFileServiceInterface::class, $fake);

        $resolved = $this->app->make(NextcloudFileServiceInterface::class);
        $this->assertSame($fake, $resolved);
    }

    /** @test */
    public function interface_resolves_to_concrete_implementation(): void
    {
        $service = $this->app->make(NextcloudFileServiceInterface::class);
        $this->assertInstanceOf(NextcloudFileService::class, $service);
    }

    /** @test */
    public function config_is_loaded_from_nextcloud_personal_key(): void
    {
        config(['nextcloud.personal.url' => 'https://test.example.com']);
        $service = new NextcloudFileService();
        $this->assertEquals('https://test.example.com', $service->getBaseUrl());
    }

    /** @test */
    public function fake_can_be_reset(): void
    {
        $fake = new FakeNextcloudFileService();
        $fake->uploadFile('/local/test.pdf', '/Personal/test.pdf');
        $this->assertCount(1, $fake->getOperations());

        $fake->reset();
        $this->assertCount(0, $fake->getOperations());
    }

    /** @test */
    public function sanitize_filename_removes_special_chars(): void
    {
        $this->assertEquals('Max_Mustermann', NextcloudFileService::sanitizeFilename('Max Mustermann'));
        $this->assertEquals('Müller_Hans', NextcloudFileService::sanitizeFilename('Müller Hans'));
        $this->assertEquals('test__datei.pdf', NextcloudFileService::sanitizeFilename('test//datei.pdf'));
    }
}

