<?php

namespace Tests\Feature\Personal;

use App\Enums\SyncStatus;
use App\Jobs\Personal\CheckNextcloudConsistency;
use App\Jobs\Personal\UploadDocumentToNextcloud;
use App\Models\personal\DocumentType;
use App\Models\personal\PersonalDocument;
use App\Models\User;
use App\Services\Personal\Contracts\NextcloudFileServiceInterface;
use App\Services\Personal\PersonalDocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\FakeNextcloudFileService;
use Tests\TestCase;

class DocumentManagementTest extends TestCase
{
    protected FakeNextcloudFileService $fakeNc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeNc = new FakeNextcloudFileService();
        $this->app->instance(NextcloudFileServiceInterface::class, $this->fakeNc);
        Storage::fake('local');
    }

    /** @test */
    public function document_index_requires_permission(): void
    {
        $user   = User::factory()->create();
        $target = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('personal.documents.index', $target->id))
            ->assertStatus(403);
    }

    /** @test */
    public function upload_dispatches_queue_job(): void
    {
        Queue::fake();
        $user   = $this->actingAsWithPermission('manage personal_documents', 'view personal_data:all', 'edit personal_data:all');
        $target = User::factory()->create();
        $type   = DocumentType::factory()->create();

        $this->post(route('personal.documents.upload', $target->id), [
            'file'             => UploadedFile::fake()->create('test.pdf', 100, 'application/pdf'),
            'document_type_id' => $type->id,
            'title'            => 'Test-Dokument',
        ]);

        Queue::assertPushed(UploadDocumentToNextcloud::class);
    }

    /** @test */
    public function document_has_pending_sync_status_after_upload(): void
    {
        Queue::fake();
        $this->actingAsWithPermission('manage personal_documents', 'view personal_data:all', 'edit personal_data:all');
        $target = User::factory()->create();
        $type   = DocumentType::factory()->create();

        $this->post(route('personal.documents.upload', $target->id), [
            'file'             => UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf'),
            'document_type_id' => $type->id,
            'title'            => 'Vertrag',
        ]);

        $this->assertDatabaseHas('pers_documents', [
            'employe_id'  => $target->id,
            'sync_status' => 'pending',
            'title'       => 'Vertrag',
        ]);
    }

    /** @test */
    public function job_sets_synced_status_on_success(): void
    {
        $document = PersonalDocument::factory()->pending()->create();
        $tmpFile  = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, 'test content');

        (new UploadDocumentToNextcloud($document, $tmpFile))->handle($this->fakeNc);

        $this->assertEquals('synced', $document->fresh()->sync_status->value);

        // Temp-Datei aufgeräumt?
        $this->assertFileDoesNotExist($tmpFile);
    }

    /** @test */
    public function job_sets_sync_fehler_status_when_upload_fails(): void
    {
        // Fake NC der fehlschlägt
        $failingNc = new class extends FakeNextcloudFileService {
            public function uploadFile(string $localPath, string $remotePath): bool
            {
                return false;
            }
        };

        $document = PersonalDocument::factory()->pending()->create();
        $tmpFile  = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, 'test content');

        (new UploadDocumentToNextcloud($document, $tmpFile))->handle($failingNc);

        $this->assertEquals('sync_fehler', $document->fresh()->sync_status->value);
    }

    /** @test */
    public function user_cannot_view_other_users_documents(): void
    {
        $user  = $this->actingAsWithPermission('view personal_documents');
        $other = User::factory()->create();
        $doc   = PersonalDocument::factory()->create(['employe_id' => $other->id]);

        $this->get(route('personal.documents.download', $doc->id))->assertStatus(403);
    }

    /** @test */
    public function user_can_download_own_document(): void
    {
        $user = $this->actingAsWithPermission('view personal_documents');
        $doc  = PersonalDocument::factory()->create([
            'employe_id'     => $user->id,
            'sync_status'    => SyncStatus::Synced,
            'nextcloud_path' => '/Personal/Test/doc.pdf',
        ]);
        $this->fakeNc->seedFileContent('/Personal/Test/doc.pdf', 'file-content');

        $this->get(route('personal.documents.download', $doc->id))->assertStatus(200);
    }

    /** @test */
    public function nightly_sync_marks_missing_files_as_error(): void
    {
        $document = PersonalDocument::factory()->create([
            'sync_status'    => SyncStatus::Synced,
            'nextcloud_path' => '/Personal/Hort/Angestellt/Test/doc.pdf',
        ]);

        // Fake-NC hat diese Datei NICHT
        (new CheckNextcloudConsistency())->handle($this->fakeNc);

        $this->assertEquals('sync_fehler', $document->fresh()->sync_status->value);
    }

    /** @test */
    public function nightly_sync_leaves_existing_files_as_synced(): void
    {
        $document = PersonalDocument::factory()->create([
            'sync_status'    => SyncStatus::Synced,
            'nextcloud_path' => '/Personal/Hort/Angestellt/Test/existing.pdf',
        ]);
        $this->fakeNc->seedFileContent('/Personal/Hort/Angestellt/Test/existing.pdf', 'content');

        (new CheckNextcloudConsistency())->handle($this->fakeNc);

        $this->assertEquals('synced', $document->fresh()->sync_status->value);
    }

    /** @test */
    public function employee_path_includes_primary_group_name(): void
    {
        $group = \App\Models\Group::factory()->asDepartment()->create(['name' => 'Hort']);
        $user  = User::factory()->create();

        \App\Models\personal\Employment::factory()->create([
            'employe_id'    => $user->id,
            'department_id' => $group->id,
            'hours'         => 20,
            'status'        => 'aktiv',
        ]);

        $path = app(PersonalDocumentService::class)->getEmployeePath($user);

        $this->assertStringContainsString('/Personal/Hort/', $path);
        $this->assertStringContainsString('/Angestellt/', $path);
    }

    /** @test */
    public function employee_path_uses_ausgeschieden_when_no_active_employment(): void
    {
        $group = \App\Models\Group::factory()->asDepartment()->create(['name' => 'Verwaltung']);
        $user  = User::factory()->create();

        \App\Models\personal\Employment::factory()->create([
            'employe_id'    => $user->id,
            'department_id' => $group->id,
            'status'        => 'beendet',
        ]);

        $path = app(PersonalDocumentService::class)->getEmployeePath($user);

        $this->assertStringContainsString('/Ausgeschieden/', $path);
    }

    /** @test */
    public function sync_errors_route_requires_manage_permission(): void
    {
        $user = $this->actingAsWithPermission('view personal_documents');

        $this->get(route('personal.documents.sync-errors'))->assertStatus(403);
    }

    /** @test */
    public function sync_errors_page_shows_failed_documents(): void
    {
        $this->actingAsWithPermission('manage personal_documents', 'view personal_data:all', 'edit personal_data:all');

        PersonalDocument::factory()->syncFehler()->create(['title' => 'Fehlerhaftes Dokument']);

        $this->get(route('personal.documents.sync-errors'))
            ->assertStatus(200)
            ->assertSee('Fehlerhaftes Dokument');
    }

    /** @test */
    public function destroy_requires_manage_permission(): void
    {
        $user = $this->actingAsWithPermission('view personal_documents');
        $doc  = PersonalDocument::factory()->create(['employe_id' => $user->id]);

        $this->delete(route('personal.documents.destroy', $doc->id))->assertStatus(403);
    }

    /** @test */
    public function can_delete_document_with_manage_permission(): void
    {
        $this->actingAsWithPermission('manage personal_documents', 'view personal_data:all', 'edit personal_data:all');
        $target = User::factory()->create();
        $doc    = PersonalDocument::factory()->create(['employe_id' => $target->id]);

        $this->delete(route('personal.documents.destroy', $doc->id))
            ->assertRedirect();

        $this->assertSoftDeleted('pers_documents', ['id' => $doc->id]);
    }
}




