<?php

namespace Tests\Feature\API\v1;

use App\Models\Group;
use App\Models\Post;
use App\Models\PostLesestatus;
use App\Models\User;
use App\Services\Posts\PostService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * API v1 – Nachrichten (/api/v1/posts/*): lesen, gelesen markieren, bestätigen.
 */
class PostApiTest extends ApiTestCase
{
    private Group $gruppe;

    private User $autor;

    private User $ich;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-16 09:00:00');
        Notification::fake();

        $this->autor = $this->mitRechten(['create posts', 'request read confirmation']);
        $this->ich = $this->mitRechten(['view paed diary']);
        $this->gruppe = Group::factory()->create(['name' => 'Hort']);
        $this->gruppe->users()->attach([$this->autor->id, $this->ich->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function mitRechten(array $rechte): User
    {
        $user = User::factory()->create();
        foreach ($rechte as $recht) {
            Permission::findOrCreate($recht, 'web');
        }
        $user->givePermissionTo($rechte);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return $user;
    }

    private function als(User $user): void
    {
        Sanctum::actingAs($user->fresh(), ['paed-app']);
    }

    private function nachricht(array $daten = [], array $dateien = []): Post
    {
        return app(PostService::class)->speichern($this->autor, array_merge([
            'header' => 'Hausordnung',
            'kurzfassung' => 'Neue Regeln ab Montag',
            'text' => '<p>Ausführlich</p><script>x()</script>',
            'groups' => [$this->gruppe->id],
            'released' => true,
        ], $daten), $dateien);
    }

    public function test_ohne_app_zugang_403(): void
    {
        $this->nachricht();
        $ohne = User::factory()->create();
        $this->gruppe->users()->attach($ohne->id);
        $this->als($ohne);

        $this->getJson(self::API.'/posts')->assertForbidden();
    }

    public function test_liste_zeigt_nachrichten_der_eigenen_gruppen_offene_zuerst(): void
    {
        $normal = $this->nachricht(['header' => 'Normal']);
        Carbon::setTestNow('2026-11-15 09:00:00');
        $offen = $this->nachricht(['header' => 'Bestätigen', 'bestaetigung_erforderlich' => true, 'bestaetigung_frist' => '2026-11-20']);
        Carbon::setTestNow('2026-11-16 09:00:00');
        $fremd = Group::factory()->create();
        app(PostService::class)->speichern($this->mitRechten(['manage posts']), ['header' => 'Fremd', 'groups' => [$fremd->id], 'released' => true]);
        $this->nachricht(['header' => 'Entwurf', 'released' => false]);

        $this->als($this->ich);
        $antwort = $this->getJson(self::API.'/posts')->assertOk();

        $this->assertSame([$offen->id, $normal->id], array_column($antwort->json('data'), 'id'));
        $antwort->assertJsonPath('data.0.confirmation.pending', true)
            ->assertJsonPath('data.0.confirmation.deadline', '2026-11-20')
            ->assertJsonPath('data.0.confirmation.stats', null)
            ->assertJsonPath('data.1.confirmation', null)
            ->assertJsonPath('data.1.summary', 'Neue Regeln ab Montag')
            ->assertJsonPath('data.1.groups.0.name', 'Hort')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_filter_gruppe_und_offen(): void
    {
        $this->nachricht();
        $offen = $this->nachricht(['bestaetigung_erforderlich' => true]);
        $this->als($this->ich);

        $this->getJson(self::API.'/posts?pending=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $offen->id);
        $this->getJson(self::API.'/posts?group_id='.$this->gruppe->id)->assertOk()->assertJsonCount(2, 'data');
        $this->getJson(self::API.'/posts?group_id='.Group::factory()->create(['protected' => true])->id)->assertNotFound();
    }

    public function test_zaehler(): void
    {
        $this->nachricht();
        $this->nachricht(['bestaetigung_erforderlich' => true]);
        $this->als($this->ich);

        $this->getJson(self::API.'/posts/counts')
            ->assertOk()
            ->assertJsonPath('data.unread', 2)
            ->assertJsonPath('data.pending_confirmations', 1);
    }

    public function test_detail_ohne_nebenwirkung_und_bereinigt(): void
    {
        $post = $this->nachricht();
        $this->als($this->ich);

        $this->getJson(self::API.'/posts/'.$post->id)
            ->assertOk()
            ->assertJsonPath('data.title', 'Hausordnung')
            ->assertJsonPath('data.read_at', null)
            ->assertJsonPath('data.text_html', '<p>Ausführlich</p>');

        $this->assertSame(0, PostLesestatus::count());
    }

    public function test_gelesen_markieren_und_bestaetigen(): void
    {
        $post = $this->nachricht(['bestaetigung_erforderlich' => true]);
        $this->als($this->ich);

        $this->postJson(self::API.'/posts/'.$post->id.'/read')->assertOk()->assertJsonPath('data.read_at', fn ($v) => $v !== null);
        $this->postJson(self::API.'/posts/'.$post->id.'/confirm')->assertOk()->assertJsonPath('data.confirmed_at', fn ($v) => $v !== null);

        $this->getJson(self::API.'/posts/counts')->assertJsonPath('data.pending_confirmations', 0);
    }

    public function test_bestaetigen_ohne_anforderung_422(): void
    {
        $post = $this->nachricht();
        $this->als($this->ich);

        $this->postJson(self::API.'/posts/'.$post->id.'/confirm')->assertStatus(422);
    }

    public function test_fremde_nachricht_403(): void
    {
        $fremd = Group::factory()->create(['protected' => true]);
        $post = app(PostService::class)->speichern($this->mitRechten(['manage posts']), ['header' => 'Geheim', 'groups' => [$fremd->id], 'released' => true]);
        $this->als($this->ich);

        $this->getJson(self::API.'/posts/'.$post->id)->assertForbidden();
        $this->postJson(self::API.'/posts/'.$post->id.'/confirm')->assertForbidden();
    }

    public function test_autor_sieht_bestaetigungsstand(): void
    {
        $post = $this->nachricht(['bestaetigung_erforderlich' => true]);
        app(PostService::class)->bestaetigen($post, $this->ich);

        $autor = $this->autor;
        $autor->givePermissionTo(Permission::findOrCreate('view paed diary', 'web'));
        $this->als($autor);

        $this->getJson(self::API.'/posts/'.$post->id)
            ->assertOk()
            ->assertJsonPath('data.is_author', true)
            ->assertJsonPath('data.confirmation.stats.bestaetigt', 1)
            ->assertJsonPath('data.confirmation.stats.gesamt', 1);
    }

    public function test_anhang_herunterladen(): void
    {
        Storage::fake('public');
        config(['media-library.disk_name' => 'public']);
        $post = $this->nachricht([], [UploadedFile::fake()->create('plan.pdf', 5, 'application/pdf')]);
        $this->als($this->ich);

        $detail = $this->getJson(self::API.'/posts/'.$post->id)->assertOk();
        $detail->assertJsonPath('data.attachments.0.name', 'plan.pdf');

        $this->get($detail->json('data.attachments.0.url'))->assertOk();
    }
}
