<?php

namespace Tests\Feature;

use App\Models\UserIcalFeed;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Feature-Tests für TODO 30: User-spezifische iCal-Feeds.
 */
class CalendarIcalFeedTest extends TestCase
{
    private const VALID_ICAL = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nSUMMARY:Test\r\nDTSTART:20260316T100000Z\r\nDTEND:20260316T110000Z\r\nUID:test-uid-1\r\nEND:VEVENT\r\nEND:VCALENDAR";

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    // ── store ──────────────────────────────────────────────────────────────

    public function test_user_kann_ical_feed_erstellen(): void
    {
        $user = $this->createUserWithPermission('view calendar');

        Http::fake([
            'https://example.com/feed.ics' => Http::response(self::VALID_ICAL, 200),
        ]);

        $this->actingAs($user)
            ->post(route('calendar.ical.store'), [
                'name'  => 'Testfeed',
                'url'   => 'https://example.com/feed.ics',
                'farbe' => '#ff5733',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('user_ical_feeds', [
            'user_id' => $user->id,
            'name'    => 'Testfeed',
            'farbe'   => '#ff5733',
        ]);
    }

    public function test_store_erfordert_view_calendar_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('calendar.ical.store'), [
                'name'  => 'Feed',
                'url'   => 'https://example.com/feed.ics',
                'farbe' => '#000000',
            ])
            ->assertForbidden();
    }

    public function test_store_validiert_fehlende_felder(): void
    {
        $user = $this->createUserWithPermission('view calendar');

        $this->actingAs($user)
            ->post(route('calendar.ical.store'), [])
            ->assertSessionHasErrors(['name', 'url', 'farbe']);
    }

    public function test_store_validiert_ungueltige_hex_farbe(): void
    {
        $user = $this->createUserWithPermission('view calendar');

        Http::fake(['*' => Http::response(self::VALID_ICAL, 200)]);

        $this->actingAs($user)
            ->post(route('calendar.ical.store'), [
                'name'  => 'Feed',
                'url'   => 'https://example.com/feed.ics',
                'farbe' => 'rot',
            ])
            ->assertSessionHasErrors('farbe');
    }

    public function test_store_lehnt_nicht_erreichbare_url_ab(): void
    {
        $user = $this->createUserWithPermission('view calendar');

        Http::fake([
            'https://example.com/broken.ics' => Http::response('', 404),
        ]);

        $this->actingAs($user)
            ->post(route('calendar.ical.store'), [
                'name'  => 'Broken',
                'url'   => 'https://example.com/broken.ics',
                'farbe' => '#000000',
            ])
            ->assertRedirect()
            ->assertSessionHas('type', 'danger');
    }

    public function test_max_10_feeds_pro_user(): void
    {
        $user = $this->createUserWithPermission('view calendar');

        for ($i = 0; $i < 10; $i++) {
            UserIcalFeed::create([
                'user_id' => $user->id,
                'name'    => "Feed $i",
                'url'     => "https://example.com/feed{$i}.ics",
                'farbe'   => '#000000',
            ]);
        }

        Http::fake(['*' => Http::response(self::VALID_ICAL, 200)]);

        $this->actingAs($user)
            ->post(route('calendar.ical.store'), [
                'name'  => 'Feed 11',
                'url'   => 'https://example.com/feed11.ics',
                'farbe' => '#000000',
            ])
            ->assertRedirect()
            ->assertSessionHas('type', 'warning');

        $this->assertEquals(10, UserIcalFeed::where('user_id', $user->id)->count());
    }

    // ── destroy ────────────────────────────────────────────────────────────

    public function test_user_kann_eigenen_feed_loeschen(): void
    {
        $user = $this->createUserWithPermission('view calendar');
        $feed = UserIcalFeed::create([
            'user_id' => $user->id,
            'name'    => 'Mein Feed',
            'url'     => 'https://example.com/feed.ics',
            'farbe'   => '#000000',
        ]);

        $this->actingAs($user)
            ->delete(route('calendar.ical.destroy', $feed))
            ->assertRedirect();

        $this->assertDatabaseMissing('user_ical_feeds', ['id' => $feed->id]);
    }

    public function test_user_kann_nicht_fremde_feeds_loeschen(): void
    {
        $user  = $this->createUserWithPermission('view calendar');
        $other = $this->createUserWithPermission('view calendar');

        $feed = UserIcalFeed::create([
            'user_id' => $other->id,
            'name'    => 'Anderer Feed',
            'url'     => 'https://example.com/other.ics',
            'farbe'   => '#000000',
        ]);

        $this->actingAs($user)
            ->delete(route('calendar.ical.destroy', $feed))
            ->assertForbidden();

        $this->assertDatabaseHas('user_ical_feeds', ['id' => $feed->id]);
    }

    // ── update ─────────────────────────────────────────────────────────────

    public function test_user_kann_eigenen_feed_aktualisieren(): void
    {
        $user = $this->createUserWithPermission('view calendar');
        $feed = UserIcalFeed::create([
            'user_id' => $user->id,
            'name'    => 'Alter Name',
            'url'     => 'https://example.com/feed.ics',
            'farbe'   => '#000000',
        ]);

        $this->actingAs($user)
            ->put(route('calendar.ical.update', $feed), [
                'name'  => 'Neuer Name',
                'url'   => 'https://example.com/feed.ics',
                'farbe' => '#ff0000',
            ])
            ->assertRedirect()
            ->assertSessionHas('type', 'success');

        $this->assertDatabaseHas('user_ical_feeds', [
            'id'   => $feed->id,
            'name' => 'Neuer Name',
        ]);
    }

    public function test_user_kann_nicht_fremde_feeds_aktualisieren(): void
    {
        $user  = $this->createUserWithPermission('view calendar');
        $other = $this->createUserWithPermission('view calendar');

        $feed = UserIcalFeed::create([
            'user_id' => $other->id,
            'name'    => 'Fremder Feed',
            'url'     => 'https://example.com/feed.ics',
            'farbe'   => '#000000',
        ]);

        $this->actingAs($user)
            ->put(route('calendar.ical.update', $feed), [
                'name'  => 'Gehackt',
                'url'   => 'https://example.com/feed.ics',
                'farbe' => '#ff0000',
            ])
            ->assertForbidden();
    }

    // ── events-Endpoint ────────────────────────────────────────────────────

    public function test_events_endpoint_liefert_ical_feed_termine(): void
    {
        $user = $this->createUserWithPermission('view calendar');

        $ical = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nSUMMARY:Externer Termin\r\nDTSTART:20260320T100000Z\r\nDTEND:20260320T110000Z\r\nUID:ext-uid-1\r\nEND:VEVENT\r\nEND:VCALENDAR";

        UserIcalFeed::create([
            'user_id' => $user->id,
            'name'    => 'Testfeed',
            'url'     => 'https://example.com/feed.ics',
            'farbe'   => '#ff5733',
        ]);

        Http::fake([
            'https://example.com/feed.ics' => Http::response($ical, 200),
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('calendar.events', [
                'start' => '2026-03-01',
                'end'   => '2026-03-31',
            ]));

        $response->assertOk();
        $events = $response->json();
        $feedEvent = collect($events)->firstWhere('title', 'Externer Termin');
        $this->assertNotNull($feedEvent, 'iCal-Feed-Event wurde nicht zurückgegeben');
        $this->assertEquals('ical_feed', $feedEvent['extendedProps']['source']);
        $this->assertEquals('#ff5733', $feedEvent['color']);
    }

    public function test_deaktivierte_feeds_werden_nicht_geladen(): void
    {
        $user = $this->createUserWithPermission('view calendar');

        UserIcalFeed::create([
            'user_id' => $user->id,
            'name'    => 'Inaktiver Feed',
            'url'     => 'https://example.com/inactive.ics',
            'farbe'   => '#000000',
            'aktiv'   => false,
        ]);

        // Kein Http::fake() – wenn der Feed trotzdem abgerufen wird, schlägt der Test fehl
        // da Http::preventStrayRequests() aktiv ist (TestCase::setUp)

        $this->actingAs($user)
            ->getJson(route('calendar.events', [
                'start' => '2026-03-01',
                'end'   => '2026-03-31',
            ]))
            ->assertOk();
    }

    public function test_feeds_sind_nur_fuer_eigenen_user_sichtbar(): void
    {
        $user1 = $this->createUserWithPermission('view calendar');
        $user2 = $this->createUserWithPermission('view calendar');

        $ical = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nSUMMARY:User1 Termin\r\nDTSTART:20260320T100000Z\r\nDTEND:20260320T110000Z\r\nUID:u1-uid\r\nEND:VEVENT\r\nEND:VCALENDAR";

        UserIcalFeed::create([
            'user_id' => $user1->id,
            'name'    => 'User1 Feed',
            'url'     => 'https://example.com/user1.ics',
            'farbe'   => '#ff0000',
        ]);

        Http::fake([
            'https://example.com/user1.ics' => Http::response($ical, 200),
        ]);

        // User2 soll die Termine von User1-Feed NICHT sehen
        $response = $this->actingAs($user2)
            ->getJson(route('calendar.events', [
                'start' => '2026-03-01',
                'end'   => '2026-03-31',
            ]));

        $response->assertOk();
        $events = $response->json();
        $this->assertEmpty(
            collect($events)->filter(fn ($e) => ($e['extendedProps']['source'] ?? '') === 'ical_feed'),
            'User2 sieht Feed-Events von User1'
        );
    }
}

