<?php

namespace Tests\Feature\Tickets;

use App\Mail\newTicketCommentMail;
use App\Mail\newTicketMail;
use App\Mail\TicketAssignmentMail;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketComment;
use App\Models\User;
use App\Services\Tickets\TicketService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketSystemTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Notification::fake();
    }

    private function editor(): User
    {
        return $this->createUserWithPermission('view tickets', 'edit tickets');
    }

    private function setSetting(string $key, string $value): void
    {
        Setting::updateOrCreate(
            ['setting' => $key],
            ['module' => 'Ticketsystem', 'setting_name' => $key, 'type' => 'text', 'value' => $value, 'description' => '']
        );
    }

    private function reporter(): User
    {
        return $this->createUserWithPermission('view tickets');
    }

    // ─── Sichtbarkeit ────────────────────────────────────────────────────────

    public function test_ersteller_sieht_eigenes_ticket_aber_kein_fremdes(): void
    {
        $owner = $this->reporter();
        $other = $this->reporter();
        $ticket = Ticket::factory()->for($owner, 'user')->create();

        $this->actingAs($owner)->get(route('tickets.show', $ticket))->assertOk()->assertSee($ticket->title);
        $this->actingAs($other)->get(route('tickets.show', $ticket))->assertForbidden();
        $this->actingAs($other)->get(route('tickets.archiveTicket', $ticket))->assertForbidden();
    }

    public function test_bearbeiter_sieht_alle_offenen_tickets(): void
    {
        $editor = $this->editor();
        $open = Ticket::factory()->create(['title' => 'Beamer defekt']);
        $closed = Ticket::factory()->closed()->create(['title' => 'Drucker leer']);

        $this->actingAs($editor)->get(route('tickets.index'))
            ->assertOk()
            ->assertSee('Beamer defekt')
            ->assertDontSee('Drucker leer');

        $this->actingAs($editor)->get(route('tickets.archive'))
            ->assertOk()
            ->assertSee('Drucker leer');
    }

    public function test_nicht_bearbeiter_sieht_in_liste_nur_eigene_tickets(): void
    {
        $owner = $this->reporter();
        Ticket::factory()->for($owner, 'user')->create(['title' => 'Mein Ticket']);
        Ticket::factory()->create(['title' => 'Fremdes Ticket']);

        $this->actingAs($owner)->get(route('tickets.index'))
            ->assertOk()
            ->assertSee('Mein Ticket')
            ->assertDontSee('Fremdes Ticket');
    }

    public function test_filter_und_suche(): void
    {
        $editor = $this->editor();
        Ticket::factory()->create(['title' => 'WLAN im Haus B', 'assigned_to' => $editor->id, 'priority' => 'high']);
        Ticket::factory()->create(['title' => 'Tafel quietscht', 'priority' => 'low']);

        $this->actingAs($editor)->get(route('tickets.index', ['scope' => 'mine']))
            ->assertSee('WLAN im Haus B')->assertDontSee('Tafel quietscht');

        $this->actingAs($editor)->get(route('tickets.index', ['scope' => 'unassigned']))
            ->assertSee('Tafel quietscht')->assertDontSee('WLAN im Haus B');

        $this->actingAs($editor)->get(route('tickets.index', ['q' => 'tafel']))
            ->assertSee('Tafel quietscht')->assertDontSee('WLAN im Haus B');

        $this->actingAs($editor)->get(route('tickets.index', ['priority' => 'high']))
            ->assertSee('WLAN im Haus B')->assertDontSee('Tafel quietscht');
    }

    public function test_interne_kommentare_sind_fuer_ersteller_unsichtbar(): void
    {
        $owner = $this->reporter();
        $ticket = Ticket::factory()->for($owner, 'user')->create();
        TicketComment::factory()->for($ticket)->internal()->create(['comment' => 'Geheime Notiz']);
        TicketComment::factory()->for($ticket)->create(['comment' => 'Öffentliche Antwort']);

        $this->actingAs($owner)->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Öffentliche Antwort')
            ->assertDontSee('Geheime Notiz');

        $this->actingAs($this->editor())->get(route('tickets.show', $ticket))
            ->assertSee('Geheime Notiz');
    }

    public function test_html_wird_bereinigt(): void
    {
        $owner = $this->reporter();
        $ticket = Ticket::factory()->for($owner, 'user')->create([
            'description' => '<p>Hallo <strong>Welt</strong></p><script>alert("xss")</script><img src="x" onerror="alert(1)">',
        ]);

        $this->actingAs($owner)->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('<strong>Welt</strong>', false)
            ->assertDontSee('alert("xss")', false)
            ->assertDontSee('onerror', false);
    }

    // ─── Anlegen ─────────────────────────────────────────────────────────────

    public function test_ticket_anlegen_benachrichtigt_bearbeiter(): void
    {
        Storage::fake('tickets');
        $editor = $this->editor();
        $owner = $this->reporter();
        $category = TicketCategory::factory()->create();

        $response = $this->actingAs($owner)->post(route('tickets.store'), [
            'title' => 'Laptop startet nicht',
            'description' => '<p>Seit heute Morgen</p>',
            'category_id' => $category->id,
            'priority' => 'high',
            'files' => [UploadedFile::fake()->create('fehler.pdf', 100, 'application/pdf')],
        ]);

        $ticket = Ticket::firstWhere('title', 'Laptop startet nicht');
        $this->assertNotNull($ticket);
        $response->assertRedirect(route('tickets.show', $ticket));
        $this->assertSame($owner->id, $ticket->user_id);
        $this->assertSame('open', $ticket->status);
        $this->assertCount(1, $ticket->getMedia('ticket_files'));
        $this->assertSame('tickets', $ticket->getFirstMedia('ticket_files')->disk);

        Mail::assertQueued(newTicketMail::class, fn ($mail) => $mail->hasTo($editor->email));
        Mail::assertNotQueued(newTicketMail::class, fn ($mail) => $mail->hasTo($owner->email));
    }

    public function test_kategorie_ist_pflicht_wenn_kategorien_existieren(): void
    {
        TicketCategory::factory()->create();
        $owner = $this->reporter();

        $this->actingAs($owner)->post(route('tickets.store'), [
            'title' => 'Ohne Kategorie',
            'description' => 'Text',
            'priority' => 'low',
        ])->assertSessionHasErrors('category_id');
    }

    public function test_ticket_ohne_kategorien_moeglich(): void
    {
        $owner = $this->reporter();

        $this->actingAs($owner)->post(route('tickets.store'), [
            'title' => 'Ohne Kategorie',
            'description' => 'Text',
            'priority' => 'low',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tickets', ['title' => 'Ohne Kategorie', 'category_id' => null]);
    }

    // ─── Kommentare & Warten ─────────────────────────────────────────────────

    public function test_bearbeiter_setzt_ticket_auf_warten_und_kommentar_bleibt_erhalten(): void
    {
        $owner = $this->reporter();
        $editor = $this->editor();
        $ticket = Ticket::factory()->for($owner, 'user')->create();
        $date = now()->addDays(3)->format('Y-m-d');

        $this->actingAs($editor)->post(route('tickets.comments.store', $ticket), [
            'comment' => '<p>Bitte Seriennummer schicken</p>',
            'internal' => 0,
            'waiting_until' => $date,
        ])->assertRedirect(route('tickets.show', $ticket));

        $ticket->refresh();
        $this->assertSame('waiting', $ticket->status);
        $this->assertSame($date, $ticket->waiting_until->format('Y-m-d'));

        // Nutzerkommentar UND Statushinweis werden gespeichert
        $this->assertDatabaseHas('ticket_comments', ['ticket_id' => $ticket->id, 'comment' => '<p>Bitte Seriennummer schicken</p>']);
        $this->assertSame(2, $ticket->comments()->count());

        Mail::assertQueued(newTicketCommentMail::class, function ($mail) use ($owner) {
            return $mail->hasTo($owner->email) && $mail->comment->comment === '<p>Bitte Seriennummer schicken</p>';
        });
    }

    public function test_antwort_des_erstellers_oeffnet_wartendes_ticket(): void
    {
        $owner = $this->reporter();
        $editor = $this->editor();
        $ticket = Ticket::factory()->for($owner, 'user')->waiting()->create(['assigned_to' => $editor->id]);

        $this->actingAs($owner)->post(route('tickets.comments.store', $ticket), [
            'comment' => 'Hier ist die Nummer',
        ])->assertRedirect();

        $ticket->refresh();
        $this->assertSame('open', $ticket->status);
        $this->assertNull($ticket->waiting_until);

        Mail::assertQueued(newTicketCommentMail::class, fn ($mail) => $mail->hasTo($editor->email));
        Mail::assertNotQueued(newTicketCommentMail::class, fn ($mail) => $mail->hasTo($owner->email));
    }

    public function test_antwort_ohne_zuweisung_benachrichtigt_alle_bearbeiter(): void
    {
        $owner = $this->reporter();
        $editorA = $this->editor();
        $editorB = $this->editor();
        $ticket = Ticket::factory()->for($owner, 'user')->create();

        $this->actingAs($owner)->post(route('tickets.comments.store', $ticket), ['comment' => 'Nachtrag']);

        Mail::assertQueued(newTicketCommentMail::class, fn ($mail) => $mail->hasTo($editorA->email));
        Mail::assertQueued(newTicketCommentMail::class, fn ($mail) => $mail->hasTo($editorB->email));
    }

    public function test_interner_kommentar_benachrichtigt_ersteller_nicht(): void
    {
        $owner = $this->reporter();
        $editor = $this->editor();
        $ticket = Ticket::factory()->for($owner, 'user')->create();

        $this->actingAs($editor)->post(route('tickets.comments.store', $ticket), [
            'comment' => 'Nur intern',
            'internal' => 1,
        ]);

        $this->assertDatabaseHas('ticket_comments', ['comment' => 'Nur intern', 'internal' => true]);
        Mail::assertNotQueued(newTicketCommentMail::class, fn ($mail) => $mail->hasTo($owner->email));
    }

    public function test_ersteller_kann_weder_intern_kommentieren_noch_warten_setzen(): void
    {
        $owner = $this->reporter();
        $ticket = Ticket::factory()->for($owner, 'user')->create();

        $this->actingAs($owner)->post(route('tickets.comments.store', $ticket), [
            'comment' => 'Versuch',
            'internal' => 1,
            'waiting_until' => now()->addDay()->format('Y-m-d'),
        ]);

        $this->assertDatabaseHas('ticket_comments', ['comment' => 'Versuch', 'internal' => false]);
        $this->assertSame('open', $ticket->refresh()->status);
    }

    public function test_fremde_und_geschlossene_tickets_koennen_nicht_kommentiert_werden(): void
    {
        $owner = $this->reporter();
        $ticket = Ticket::factory()->for($owner, 'user')->create();
        $closed = Ticket::factory()->for($owner, 'user')->closed()->create();

        $this->actingAs($this->reporter())
            ->post(route('tickets.comments.store', $ticket), ['comment' => 'fremd'])
            ->assertForbidden();

        $this->actingAs($owner)
            ->post(route('tickets.comments.store', $closed), ['comment' => 'zu spät'])
            ->assertForbidden();
    }

    // ─── Status, Zuweisung, Pins ─────────────────────────────────────────────

    public function test_schliessen_und_wiedereroeffnen(): void
    {
        $owner = $this->reporter();
        $editor = $this->editor();
        $ticket = Ticket::factory()->for($owner, 'user')->create();

        $this->actingAs($this->reporter())->post(route('tickets.close', $ticket))->assertForbidden();

        $this->actingAs($editor)->post(route('tickets.close', $ticket), ['reason' => 'Erledigt'])->assertRedirect();
        $ticket->refresh();
        $this->assertTrue($ticket->isClosed());
        $this->assertSame($editor->id, $ticket->closed_by);
        $this->assertNotNull($ticket->closed_at);
        Mail::assertQueued(newTicketCommentMail::class, fn ($mail) => $mail->hasTo($owner->email));

        $this->actingAs($owner)->post(route('tickets.reopen', $ticket))->assertRedirect(route('tickets.show', $ticket));
        $ticket->refresh();
        $this->assertSame('open', $ticket->status);
        $this->assertNull($ticket->closed_at);
    }

    public function test_ersteller_kann_eigenes_ticket_schliessen(): void
    {
        $owner = $this->reporter();
        $ticket = Ticket::factory()->for($owner, 'user')->create();

        $this->actingAs($owner)->post(route('tickets.close', $ticket))->assertRedirect();
        $this->assertTrue($ticket->refresh()->isClosed());
    }

    public function test_zustandsaendernde_aktionen_nicht_per_get(): void
    {
        $editor = $this->editor();
        $ticket = Ticket::factory()->create();

        $this->actingAs($editor)->get('/tickets/'.$ticket->id.'/close')->assertStatus(405);
        $this->actingAs($editor)->get('/tickets/'.$ticket->id.'/pin')->assertStatus(405);
        $this->assertSame('open', $ticket->refresh()->status);
    }

    public function test_zuweisung(): void
    {
        $editor = $this->editor();
        $colleague = $this->editor();
        $owner = $this->reporter();
        $ticket = Ticket::factory()->for($owner, 'user')->create();

        // Nicht-Bearbeiter dürfen nicht zuweisen
        $this->actingAs($owner)->post(route('tickets.assign', $ticket), ['user_id' => $colleague->id])->assertForbidden();

        // Nur Bearbeiter können Zuständige werden
        $this->actingAs($editor)->post(route('tickets.assign', $ticket), ['user_id' => $owner->id]);
        $this->assertNull($ticket->refresh()->assigned_to);

        $this->actingAs($editor)->post(route('tickets.assign', $ticket), ['user_id' => $colleague->id]);
        $this->assertSame($colleague->id, $ticket->refresh()->assigned_to);
        Mail::assertQueued(TicketAssignmentMail::class, fn ($mail) => $mail->hasTo($colleague->email));

        // Zuweisung aufheben
        $this->actingAs($editor)->post(route('tickets.assign', $ticket), ['user_id' => '']);
        $this->assertNull($ticket->refresh()->assigned_to);
        $this->assertDatabaseHas('ticket_comments', ['ticket_id' => $ticket->id, 'comment' => 'Zuweisung an '.e($colleague->name).' aufgehoben.']);
    }

    public function test_kategorie_und_prioritaet_aendern(): void
    {
        $editor = $this->editor();
        $new = TicketCategory::factory()->create();
        $ticket = Ticket::factory()->create(['priority' => 'low']);

        $this->actingAs($editor)->patch(route('tickets.update', $ticket), [
            'category_id' => $new->id,
            'priority' => 'high',
        ])->assertRedirect();

        $ticket->refresh();
        $this->assertSame($new->id, $ticket->category_id);
        $this->assertSame('high', $ticket->priority);
        $this->assertSame(1, $ticket->comments()->count());

        $this->actingAs($this->reporter())->patch(route('tickets.update', $ticket), ['priority' => 'low'])->assertForbidden();
    }

    public function test_anpinnen_schaltet_um_ohne_duplikate(): void
    {
        $editor = $this->editor();
        $ticket = Ticket::factory()->create();

        $this->actingAs($editor)->post(route('tickets.pin', $ticket));
        $this->assertSame(1, $editor->pinned_tickets()->count());

        $this->actingAs($editor)->post(route('tickets.pin', $ticket));
        $this->assertSame(0, $editor->pinned_tickets()->count());

        $this->actingAs($this->reporter())->post(route('tickets.pin', $ticket))->assertForbidden();
    }

    // ─── Anhänge ─────────────────────────────────────────────────────────────

    public function test_anhaenge_nur_fuer_berechtigte(): void
    {
        Storage::fake('tickets');
        $owner = $this->reporter();
        $ticket = Ticket::factory()->for($owner, 'user')->create();
        $media = $ticket->addMedia(UploadedFile::fake()->create('plan.pdf', 10, 'application/pdf'))
            ->toMediaCollection('ticket_files');

        $otherTicket = Ticket::factory()->for($owner, 'user')->create();

        $this->actingAs($owner)->get(route('tickets.files', [$ticket, $media]))->assertOk();
        $this->actingAs($this->reporter())->get(route('tickets.files', [$ticket, $media]))->assertForbidden();
        // Alte /image/{id}-Links landen auf der geprüften Route
        $this->actingAs($this->reporter())->get(route('image.get', $media))
            ->assertRedirect(route('tickets.files', [$ticket, $media]));
        // Anhang muss zum Ticket in der URL gehören
        $this->actingAs($owner)->get(route('tickets.files', [$otherTicket, $media]))->assertNotFound();
    }

    public function test_anhaenge_interner_kommentare_nicht_fuer_ersteller(): void
    {
        Storage::fake('tickets');
        $owner = $this->reporter();
        $ticket = Ticket::factory()->for($owner, 'user')->create();
        $comment = TicketComment::factory()->for($ticket)->internal()->create();
        $media = $comment->addMedia(UploadedFile::fake()->create('intern.pdf', 10, 'application/pdf'))
            ->toMediaCollection('comment_files');

        $this->actingAs($owner)->get(route('tickets.files', [$ticket, $media]))->assertNotFound();
        $this->actingAs($this->editor())->get(route('tickets.files', [$ticket, $media]))->assertOk();
    }

    // ─── Automatisches Schließen ─────────────────────────────────────────────

    public function test_wartende_tickets_werden_nach_frist_automatisch_geschlossen(): void
    {
        $this->setSetting('ticket_closed_automatic', '1');
        $this->setSetting('ticket_closed_automatic_days', '7');
        Cache::flush();

        $owner = $this->reporter();
        $expired = Ticket::factory()->for($owner, 'user')->waiting(now()->subDays(8))->create();
        $expired2 = Ticket::factory()->waiting(now()->subDays(10))->create();
        $stillWaiting = Ticket::factory()->waiting(now()->subDays(3))->create();

        $closed = app(TicketService::class)->closeExpiredWaiting();

        $this->assertSame(2, $closed);
        $this->assertTrue($expired->refresh()->isClosed());
        $this->assertTrue($expired2->refresh()->isClosed());
        $this->assertSame('waiting', $stillWaiting->refresh()->status);
        $this->assertDatabaseHas('ticket_comments', ['ticket_id' => $expired->id, 'user_id' => null]);
        Mail::assertQueued(newTicketCommentMail::class, fn ($mail) => $mail->hasTo($owner->email));
    }

    public function test_automatisches_schliessen_abschaltbar(): void
    {
        $this->setSetting('ticket_closed_automatic', '0');
        Cache::flush();

        $ticket = Ticket::factory()->waiting(now()->subDays(30))->create();

        $this->assertSame(0, app(TicketService::class)->closeExpiredWaiting());
        $this->assertSame('waiting', $ticket->refresh()->status);
    }

    // ─── Kategorien ──────────────────────────────────────────────────────────

    public function test_kategorie_loeschen_entfernt_zuordnung(): void
    {
        $editor = $this->editor();
        $category = TicketCategory::factory()->create();
        $ticket = Ticket::factory()->create(['category_id' => $category->id]);

        $this->actingAs($editor)->delete(route('categories.destroy', $category))->assertRedirect();

        $this->assertNull($ticket->refresh()->category_id);
        $this->assertDatabaseMissing('ticket_categories', ['id' => $category->id]);
    }

    // ─── Darstellung ─────────────────────────────────────────────────────────

    public function test_gepinnte_und_archivierte_tickets_werden_angezeigt(): void
    {
        $owner = $this->reporter();
        $closed = Ticket::factory()->for($owner, 'user')->closed()->create(['title' => 'Altes Problem']);
        $owner->pinned_tickets()->attach($closed->id);

        $this->actingAs($owner)->get(route('tickets.index'))
            ->assertOk()
            ->assertSee('Angepinnt')
            ->assertSee('Altes Problem');

        $this->actingAs($owner)->get(route('tickets.archiveTicket', $closed))
            ->assertOk()
            ->assertSee('Wieder öffnen');
    }

    public function test_mails_lassen_sich_rendern(): void
    {
        $ticket = Ticket::factory()->create(['description' => '<p>Text</p><script>x()</script>']);
        $system = TicketComment::factory()->for($ticket)->create(['user_id' => null, 'comment' => 'Automatisch geschlossen']);

        $html = (new newTicketCommentMail($system, $ticket))->render();
        $this->assertStringContainsString('System', $html);
        $this->assertStringContainsString(route('tickets.show', $ticket), $html);

        $html = (new newTicketMail($ticket))->render();
        $this->assertStringNotContainsString('<script>', $html);

        $this->assertStringContainsString($ticket->title, (new TicketAssignmentMail($ticket))->render());
    }

    public function test_uebersicht_formular_und_kategorien_werden_gerendert(): void
    {
        $editor = $this->editor();
        $category = TicketCategory::factory()->create(['name' => 'Haustechnik']);
        $ticket = Ticket::factory()->waiting(now()->subDay())->create(['category_id' => $category->id, 'assigned_to' => $editor->id]);
        TicketComment::factory()->for($ticket)->create(['user_id' => null, 'system' => true, 'comment' => 'Status geändert']);

        $this->actingAs($editor)->get(route('tickets.index', ['neu' => 1]))
            ->assertOk()
            ->assertSee('Neues Ticket')
            ->assertSee('Dringlichkeit')
            ->assertSee('überfällig');

        $this->actingAs($editor)->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Status geändert')
            ->assertSee('Interne Notiz')
            ->assertDontSee('Ich übernehme das Ticket');

        $this->actingAs($editor)->get(route('categories.index'))
            ->assertOk()
            ->assertSee('Haustechnik')
            ->assertSee('1 offen');
    }
}
