<?php

namespace Tests\Feature\Posts;

use App\Models\Group;
use App\Models\Post;
use App\Models\PostLesestatus;
use App\Models\User;
use App\Notifications\NachrichtErinnerung;
use App\Notifications\NachrichtVeroeffentlicht;
use App\Services\Posts\PostService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Nachrichten in Gruppen mit optionaler Lesebestätigung (PostService, PostsController, PostPolicy).
 */
class PostsTest extends TestCase
{
    private Group $gruppe;

    private User $autor;

    private User $mitglied;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-16 09:00:00'); // Montag
        Notification::fake();
        $this->withoutVite();

        $this->autor = $this->nutzer(['create posts', 'request read confirmation']);
        $this->mitglied = $this->nutzer();
        $this->gruppe = Group::factory()->create(['name' => 'Kollegium', 'creator_id' => User::factory()->create()->id]);
        $this->gruppe->users()->attach([$this->autor->id, $this->mitglied->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function nutzer(array $rechte = []): User
    {
        $user = User::factory()->create();
        foreach ($rechte as $recht) {
            Permission::findOrCreate($recht);
        }
        $user->givePermissionTo($rechte);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return $user;
    }

    private function nachricht(array $daten = [], ?User $autor = null): Post
    {
        return app(PostService::class)->speichern($autor ?? $this->autor, array_merge([
            'header' => 'Dienstanweisung Brandschutz',
            'text' => '<p>Bitte lesen.</p>',
            'groups' => [$this->gruppe->id],
            'released' => true,
        ], $daten));
    }

    private function beitreten(Group $gruppe, User $user): void
    {
        $gruppe->users()->attach($user->id);
        Cache::forget('groups_'.$user->id);
    }

    // ------------------------------------------------------------------
    // Anlegen und Rechte
    // ------------------------------------------------------------------

    public function test_nachricht_anlegen_und_veroeffentlichen_benachrichtigt_empfaenger_ohne_autor(): void
    {
        $this->actingAs($this->autor)
            ->post(route('posts.store'), [
                '_formular' => 1,
                'header' => 'Elternabend',
                'kurzfassung' => 'Am Donnerstag um 18 Uhr',
                'text' => '<p>Ausführliche Infos</p>',
                'groups' => [$this->gruppe->id],
                'auf_dashboard' => 1,
                'released' => 1,
            ])
            ->assertRedirect();

        $post = Post::firstOrFail();
        $this->assertTrue($post->released);
        $this->assertNotNull($post->veroeffentlicht_at);
        $this->assertSame('Am Donnerstag um 18 Uhr', $post->kurztext);

        Notification::assertSentTo($this->mitglied, NachrichtVeroeffentlicht::class);
        Notification::assertNotSentTo($this->autor, NachrichtVeroeffentlicht::class);
    }

    public function test_entwurf_benachrichtigt_niemanden(): void
    {
        $this->nachricht(['released' => false]);

        Notification::assertNothingSent();
    }

    public function test_posten_in_fremde_gruppe_wird_abgelehnt(): void
    {
        $fremd = Group::factory()->create();

        $this->actingAs($this->autor)
            ->post(route('posts.store'), ['header' => 'X', 'groups' => [$fremd->id], 'released' => 1])
            ->assertSessionHasErrors('groups');

        $this->assertSame(0, Post::count());
    }

    public function test_gruppenersteller_darf_ohne_recht_posten(): void
    {
        $ersteller = $this->gruppe->creator;

        $this->actingAs($ersteller)
            ->post(route('posts.store'), ['header' => 'Info', 'groups' => [$this->gruppe->id], 'released' => 1])
            ->assertRedirect();

        $this->assertSame(1, Post::count());
    }

    public function test_manage_posts_darf_in_alle_gruppen_posten(): void
    {
        $leitung = $this->nutzer(['manage posts']);
        $fremd = Group::factory()->create();

        $this->actingAs($leitung)
            ->post(route('posts.store'), ['header' => 'An alle', 'groups' => [$fremd->id], 'released' => 1])
            ->assertRedirect();

        $this->assertTrue(Post::firstOrFail()->groups->contains($fremd));
    }

    public function test_ohne_recht_wird_lesebestaetigung_ignoriert(): void
    {
        $ohneRecht = $this->nutzer(['create posts']);
        $this->beitreten($this->gruppe, $ohneRecht);

        $post = $this->nachricht(['bestaetigung_erforderlich' => true], $ohneRecht);

        $this->assertFalse($post->bestaetigung_erforderlich);
    }

    public function test_fremde_duerfen_nicht_bearbeiten(): void
    {
        $post = $this->nachricht();

        $this->actingAs($this->mitglied)->get(route('posts.edit', $post))->assertForbidden();
        $this->actingAs($this->mitglied)->post(route('posts.archive', $post))->assertForbidden();
        $this->actingAs($this->mitglied)
            ->put(route('posts.update', $post), ['header' => 'gehackt', 'groups' => [$this->gruppe->id]])
            ->assertForbidden();

        $this->assertSame('Dienstanweisung Brandschutz', $post->fresh()->header);
    }

    public function test_html_wird_bereinigt(): void
    {
        $post = $this->nachricht(['text' => '<p>Hallo</p><script>alert(1)</script><img src=x onerror="alert(2)">']);

        $this->actingAs($this->mitglied)
            ->get(route('posts.show', $post))
            ->assertOk()
            ->assertSee('Hallo')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('onerror', false);
    }

    // ------------------------------------------------------------------
    // Archivieren statt Löschen
    // ------------------------------------------------------------------

    public function test_entwurf_wird_geloescht_veroeffentlichte_nur_archiviert(): void
    {
        $entwurf = $this->nachricht(['released' => false]);
        $veroeffentlicht = $this->nachricht();

        $this->actingAs($this->autor)->delete(route('posts.destroy', $entwurf))->assertRedirect();
        $this->actingAs($this->autor)->delete(route('posts.destroy', $veroeffentlicht))->assertRedirect();

        $this->assertSoftDeleted($entwurf);
        $this->assertNotSoftDeleted($veroeffentlicht);
        $this->assertTrue($veroeffentlicht->fresh()->archived);
    }

    public function test_einmal_veroeffentlicht_bleibt_veroeffentlicht(): void
    {
        $post = $this->nachricht();

        $this->actingAs($this->autor)
            ->put(route('posts.update', $post), ['header' => 'Neu', 'groups' => [$this->gruppe->id], 'released' => 0])
            ->assertRedirect();

        $this->assertTrue($post->fresh()->released);
    }

    public function test_archiv_wiederherstellen_und_gruppenarchiv(): void
    {
        $post = $this->nachricht();
        $this->actingAs($this->autor)->post(route('posts.archive', $post))->assertRedirect();

        $this->actingAs($this->mitglied)
            ->get(route('groups.posts', [$this->gruppe->name, 'archiv' => 1]))
            ->assertOk()
            ->assertSee('Dienstanweisung Brandschutz');

        $this->actingAs($this->autor)->post(route('posts.restore', $post))->assertRedirect();
        $this->assertFalse($post->fresh()->archived);
    }

    public function test_abgelaufene_werden_archiviert_aber_nicht_vor_der_frist(): void
    {
        $ohneFrist = $this->nachricht(['sichtbar_bis' => '2026-11-10']);
        $mitFrist = $this->nachricht(['sichtbar_bis' => '2026-11-10', 'bestaetigung_erforderlich' => true, 'bestaetigung_frist' => '2026-11-20']);

        $this->assertSame(1, app(PostService::class)->abgelaufeneArchivieren());
        $this->assertTrue($ohneFrist->fresh()->archived);
        $this->assertFalse($mitFrist->fresh()->archived);

        Carbon::setTestNow('2026-11-21 08:00:00');
        app(PostService::class)->abgelaufeneArchivieren();
        $this->assertTrue($mitFrist->fresh()->archived);
    }

    // ------------------------------------------------------------------
    // Lesebestätigung
    // ------------------------------------------------------------------

    public function test_empfaenger_bestaetigt(): void
    {
        $post = $this->nachricht(['bestaetigung_erforderlich' => true, 'bestaetigung_frist' => '2026-11-23']);

        $this->actingAs($this->mitglied)
            ->get(route('posts.show', $post))
            ->assertOk()
            ->assertSee('Gelesen und verstanden');

        $this->actingAs($this->mitglied)->post(route('posts.confirm', $post))->assertRedirect();

        $status = PostLesestatus::where('post_id', $post->id)->where('user_id', $this->mitglied->id)->firstOrFail();
        $this->assertNotNull($status->bestaetigt_at);
        $this->assertNotNull($status->gelesen_at);
        $this->assertSame('2026-11-23', $status->frist->toDateString());
    }

    public function test_nicht_mitglieder_und_autor_koennen_nicht_bestaetigen(): void
    {
        $post = $this->nachricht(['bestaetigung_erforderlich' => true]);
        $fremd = $this->nutzer();

        $this->actingAs($fremd)->post(route('posts.confirm', $post))->assertForbidden();
        $this->actingAs($this->autor)->post(route('posts.confirm', $post))->assertForbidden();
    }

    public function test_archivierte_nachricht_kann_nicht_mehr_bestaetigt_werden(): void
    {
        $post = $this->nachricht(['bestaetigung_erforderlich' => true]);
        app(PostService::class)->archivieren($post);

        $this->actingAs($this->mitglied)->post(route('posts.confirm', $post->fresh()))->assertForbidden();
        $this->assertSame([], app(PostService::class)->offeneBestaetigungIds($this->mitglied));
    }

    public function test_neue_mitglieder_bekommen_eigene_frist_mindestens_sieben_tage(): void
    {
        $post = $this->nachricht(['bestaetigung_erforderlich' => true, 'bestaetigung_frist' => '2026-11-19']); // 3 Tage
        $neu = $this->nutzer();

        Carbon::setTestNow('2026-11-25 10:00:00');
        $this->beitreten($this->gruppe, $neu);

        $this->assertSame(1, app(PostService::class)->abgleichen());
        $status = PostLesestatus::where('post_id', $post->id)->where('user_id', $neu->id)->firstOrFail();
        $this->assertSame('2026-12-02', $status->frist->toDateString());
        Notification::assertSentTo($neu, NachrichtVeroeffentlicht::class, fn ($n) => $n->grund === NachrichtVeroeffentlicht::GRUND_BEITRITT);

        // Zweiter Lauf weist nicht erneut zu
        $this->assertSame(0, app(PostService::class)->abgleichen());
    }

    public function test_neue_mitglieder_behalten_laengere_urspruengliche_fristdauer(): void
    {
        $post = $this->nachricht(['bestaetigung_erforderlich' => true, 'bestaetigung_frist' => '2026-11-30']); // 14 Tage
        $neu = $this->nutzer();

        Carbon::setTestNow('2026-11-20 10:00:00');
        $this->beitreten($this->gruppe, $neu);
        app(PostService::class)->abgleichen($post);

        $this->assertSame('2026-12-04', PostLesestatus::where('user_id', $neu->id)->firstOrFail()->frist->toDateString());
    }

    public function test_bestaetigungsliste_behaelt_ausgeschiedene_mit_bestaetigung(): void
    {
        $post = $this->nachricht(['bestaetigung_erforderlich' => true]);
        $offenGegangen = $this->nutzer();
        $this->beitreten($this->gruppe, $offenGegangen);
        app(PostService::class)->abgleichen($post);

        app(PostService::class)->bestaetigen($post, $this->mitglied);
        $this->gruppe->users()->detach([$this->mitglied->id, $offenGegangen->id]);

        $liste = app(PostService::class)->bestaetigungsliste($post);

        $this->assertCount(1, $liste);
        $this->assertSame($this->mitglied->id, $liste->first()['user']->id);
        $this->assertTrue($liste->first()['ausgeschieden']);
        $this->assertSame(0, app(PostService::class)->statistik($post)['gesamt']);
    }

    public function test_aenderung_mit_zuruecksetzen_fordert_erneut_an(): void
    {
        $post = $this->nachricht(['bestaetigung_erforderlich' => true]);
        app(PostService::class)->bestaetigen($post, $this->mitglied);

        // Korrektur: Bestätigung bleibt
        $this->actingAs($this->autor)->put(route('posts.update', $post), [
            '_formular' => 1, 'header' => 'Korrigiert', 'groups' => [$this->gruppe->id],
            'bestaetigung_erforderlich' => 1, 'bestaetigungen_zuruecksetzen' => 0, 'released' => 1,
        ])->assertRedirect();
        $this->assertNotNull(PostLesestatus::where('user_id', $this->mitglied->id)->value('bestaetigt_at'));

        // Wesentliche Änderung: zurücksetzen
        $this->actingAs($this->autor)->put(route('posts.update', $post), [
            '_formular' => 1, 'header' => 'Neue Fassung', 'groups' => [$this->gruppe->id],
            'bestaetigung_erforderlich' => 1, 'bestaetigungen_zuruecksetzen' => 1, 'released' => 1,
        ])->assertRedirect();

        $this->assertNull(PostLesestatus::where('user_id', $this->mitglied->id)->value('bestaetigt_at'));
        Notification::assertSentTo($this->mitglied, NachrichtVeroeffentlicht::class, fn ($n) => $n->grund === NachrichtVeroeffentlicht::GRUND_GEAENDERT);
    }

    public function test_nachtraeglich_angeforderte_bestaetigung_wird_zugewiesen(): void
    {
        $post = $this->nachricht();

        $this->actingAs($this->autor)->put(route('posts.update', $post), [
            '_formular' => 1, 'header' => $post->header, 'groups' => [$this->gruppe->id],
            'bestaetigung_erforderlich' => 1, 'released' => 1,
        ])->assertRedirect();

        $this->assertSame([$post->id], app(PostService::class)->offeneBestaetigungIds($this->mitglied));
    }

    public function test_bestaetigungsliste_rechte_und_exporte(): void
    {
        $post = $this->nachricht(['bestaetigung_erforderlich' => true]);

        $this->actingAs($this->mitglied)->get(route('posts.confirmations', $post))->assertForbidden();

        $this->actingAs($this->autor)
            ->get(route('posts.confirmations', $post))
            ->assertOk()
            ->assertSee($this->mitglied->name);

        // Gruppen-Ersteller:in sieht die Liste, darf aber nicht bearbeiten
        $ersteller = $this->gruppe->creator;
        $this->actingAs($ersteller)->get(route('posts.confirmations', $post))->assertOk();
        $this->actingAs($ersteller)->get(route('posts.edit', $post))->assertForbidden();

        $this->actingAs($this->autor)->get(route('posts.confirmations.excel', $post))->assertOk()->assertDownload();
        $this->actingAs($this->autor)->get(route('posts.confirmations.pdf', $post))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    // ------------------------------------------------------------------
    // Erinnerungen
    // ------------------------------------------------------------------

    public function test_automatische_erinnerung_zwei_tage_vorher_und_am_fristtag(): void
    {
        $post = $this->nachricht(['bestaetigung_erforderlich' => true, 'bestaetigung_frist' => '2026-11-20']);
        $service = app(PostService::class);

        Carbon::setTestNow('2026-11-17 07:10:00');
        $this->assertSame(0, $service->erinnerungenVersenden());

        Carbon::setTestNow('2026-11-18 07:10:00');
        $this->assertSame(1, $service->erinnerungenVersenden());
        $this->assertSame(0, $service->erinnerungenVersenden());

        Carbon::setTestNow('2026-11-20 07:10:00');
        $this->assertSame(1, $service->erinnerungenVersenden());

        Carbon::setTestNow('2026-11-21 07:10:00');
        $this->assertSame(0, $service->erinnerungenVersenden());

        Notification::assertSentToTimes($this->mitglied, NachrichtErinnerung::class, 2);
    }

    public function test_keine_erinnerung_nach_bestaetigung(): void
    {
        $post = $this->nachricht(['bestaetigung_erforderlich' => true, 'bestaetigung_frist' => '2026-11-18']);
        app(PostService::class)->bestaetigen($post, $this->mitglied);

        $this->assertSame(0, app(PostService::class)->erinnerungenVersenden());
    }

    public function test_manuelle_erinnerung_hoechstens_einmal_pro_tag(): void
    {
        $post = $this->nachricht(['bestaetigung_erforderlich' => true]);

        $this->actingAs($this->autor)->post(route('posts.remind', $post))->assertRedirect()->assertSessionHas('Meldung', '1 Person erinnert');
        $this->actingAs($this->autor)->post(route('posts.remind', $post->fresh()))->assertSessionHasErrors('post');

        Notification::assertSentToTimes($this->mitglied, NachrichtErinnerung::class, 1);

        Carbon::setTestNow('2026-11-17 09:00:00');
        $this->actingAs($this->autor)->post(route('posts.remind', $post->fresh()))->assertSessionHasNoErrors();
        Notification::assertSentToTimes($this->mitglied, NachrichtErinnerung::class, 2);
    }

    public function test_erinnerung_nutzt_alle_kanaele_unabhaengig_von_einstellung(): void
    {
        $post = $this->nachricht(['bestaetigung_erforderlich' => true]);

        $kanaele = (new NachrichtErinnerung($post))->via($this->mitglied);
        $this->assertContains('mail', $kanaele);

        // Ohne Lesebestätigung keine Sofort-Mail (abendliche Sammelmail)
        $ohne = $this->nachricht();
        $this->assertNotContains('mail', (new NachrichtVeroeffentlicht($ohne))->via($this->mitglied));
    }

    // ------------------------------------------------------------------
    // Ansichten
    // ------------------------------------------------------------------

    public function test_gruppenseite_zeigt_nachrichten_und_verweigert_fremden(): void
    {
        $this->nachricht(['header' => 'Nur für die Gruppe', 'auf_dashboard' => false]);

        $this->actingAs($this->mitglied)
            ->get(route('groups.posts', $this->gruppe->name))
            ->assertOk()
            ->assertSee('Nur für die Gruppe');

        $geschuetzt = Group::factory()->create(['protected' => true]);
        $this->actingAs($this->mitglied)
            ->get(route('groups.posts', $geschuetzt->name))
            ->assertRedirect();
    }

    public function test_dashboard_zeigt_nur_dashboard_nachrichten_und_heftet_offene_an(): void
    {
        $nurGruppe = $this->nachricht(['header' => 'Nur Gruppe', 'auf_dashboard' => false]);
        Carbon::setTestNow('2026-11-16 10:00:00');
        $offen = $this->nachricht(['header' => 'Bitte bestätigen', 'bestaetigung_erforderlich' => true]);
        Carbon::setTestNow('2026-11-16 11:00:00');
        $neu = $this->nachricht(['header' => 'Neueste']);

        $liste = app(PostService::class)->dashboardNachrichten($this->mitglied);

        $this->assertFalse($liste->contains('id', $nurGruppe->id));
        $this->assertSame($offen->id, $liste->first()->id);
        $this->assertTrue($liste->first()->muss_bestaetigen);
        $this->assertTrue($liste->contains('id', $neu->id));
    }

    public function test_zaehler_ungelesen_und_unbestaetigt(): void
    {
        $post = $this->nachricht();
        $this->nachricht(['bestaetigung_erforderlich' => true]);

        $service = app(PostService::class);
        $this->assertSame(['ungelesen' => 2, 'unbestaetigt' => 1], $service->zaehler($this->mitglied));

        $this->actingAs($this->mitglied)->get(route('posts.show', $post))->assertOk();
        $this->assertSame(1, (new PostService())->zaehler($this->mitglied)['ungelesen']);
        $this->assertSame([$this->gruppe->id => 1], (new PostService())->ungeleseneJeGruppe($this->mitglied));
    }

    public function test_entwurf_ist_fuer_andere_nicht_sichtbar(): void
    {
        $entwurf = $this->nachricht(['released' => false]);

        $this->actingAs($this->mitglied)->get(route('posts.show', $entwurf))->assertForbidden();
        $this->actingAs($this->autor)->get(route('posts.show', $entwurf))->assertOk();
    }

    public function test_anhaenge_nur_mit_lesezugriff(): void
    {
        Storage::fake('public');
        config(['media-library.disk_name' => 'public']);

        $post = app(PostService::class)->speichern($this->autor, [
            'header' => 'Mit Anhang',
            'groups' => [$this->gruppe->id],
            'released' => true,
        ], [UploadedFile::fake()->create('plan.pdf', 10, 'application/pdf')]);

        $media = $post->getFirstMedia('files');
        $this->assertNotNull($media);

        $this->actingAs($this->mitglied)->get(route('posts.files', [$post, $media]))->assertOk();
        $this->actingAs($this->nutzer())->get(route('posts.files', [$post, $media]))->assertForbidden();
        $this->actingAs($this->nutzer())->get(route('image.get', $media->id))->assertNotFound();
    }

    public function test_schnellformular_auf_dem_dashboard_legt_dashboard_nachricht_an(): void
    {
        $this->actingAs($this->autor)
            ->postJson(route('posts.store'), ['header' => 'Kurz', 'text' => 'Info', 'groups' => [$this->gruppe->id], 'released' => 1])
            ->assertCreated();

        $this->assertTrue(Post::firstOrFail()->auf_dashboard);
    }

    public function test_beitritt_ueber_gruppenverwaltung_weist_sofort_zu(): void
    {
        $post = $this->nachricht(['bestaetigung_erforderlich' => true]);
        $neu = User::factory()->create(['name' => 'Eindeutige Neue Kollegin']);
        $ersteller = $this->gruppe->creator;

        $this->actingAs($ersteller)
            ->put(url($this->gruppe->name.'/addUser'), ['name' => 'Eindeutige Neue Kollegin'])
            ->assertRedirect();

        $this->assertTrue($this->gruppe->users()->where('users.id', $neu->id)->exists());

        $this->assertContains($post->id, app(PostService::class)->offeneBestaetigungIds($neu));
    }

    public function test_abendliche_sammelmail_ohne_nachrichten_mit_lesebestaetigung(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $this->nachricht(['header' => 'Normale Nachricht']);
        $this->nachricht(['header' => 'Mit Bestätigung', 'bestaetigung_erforderlich' => true]);

        app(\App\Http\Controllers\PostsController::class)->dailyMail();

        \Illuminate\Support\Facades\Mail::assertQueued(\App\Mail\newPostsMail::class, function ($mail) {
            return $mail->hasTo($this->mitglied->email)
                && $mail->posts->pluck('header')->all() === ['Normale Nachricht'];
        });
        \Illuminate\Support\Facades\Mail::assertNotQueued(\App\Mail\newPostsMail::class, fn ($mail) => $mail->hasTo($this->autor->email));
    }
}
