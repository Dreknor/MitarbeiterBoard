<?php

namespace Tests\Feature\Meetings;

use App\Mail\MeetingInvitationMail;
use App\Models\Group;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\Protocol;
use App\Models\Theme;
use App\Models\Type;
use App\Models\User;
use App\Services\Meetings\MeetingService;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FreeMeetingTest extends TestCase
{
    private function freeMeeting(User $creator, array $participants = [], array $attributes = []): Meeting
    {
        $meeting = Meeting::factory()->free()->create(array_merge([
            'creator_id' => $creator->id,
            'title'      => 'Projektrunde Schulfest',
        ], $attributes));

        app(MeetingService::class)->syncParticipants($meeting, $participants);

        return $meeting;
    }

    /** @test */
    public function berechtigter_nutzer_kann_freies_meeting_mit_teilnehmenden_anlegen(): void
    {
        $user   = $this->actingAsWithPermission('create free meetings');
        $gast   = User::factory()->create();
        $orga   = User::factory()->create();
        $gruppe = Group::factory()->create();
        $rolle  = Role::create(['name' => 'Schulsozialarbeit', 'guard_name' => 'web']);

        $response = $this->post(route('meetings.create'), [
            'title'       => 'Ad-hoc Besprechung',
            'date'        => now()->addDay()->format('Y-m-d'),
            'start_time'  => '14:00',
            'end_time'    => '15:00',
            'location'    => 'Lehrerzimmer',
            'meeting_url' => 'https://meet.example.org/abc',
            'users'       => [$gast->id],
            'organizers'  => [$orga->id],
            'groups'      => [$gruppe->id],
            'roles'       => [$rolle->id],
        ]);

        $meeting = Meeting::where('title', 'Ad-hoc Besprechung')->firstOrFail();
        $response->assertRedirect(route('meetings.show', $meeting));

        $this->assertNull($meeting->group_id);
        $this->assertSame($user->id, $meeting->creator_id);
        $this->assertSame('Lehrerzimmer', $meeting->location);
        $this->assertSame(4, $meeting->participants()->count());
        $this->assertDatabaseHas('meeting_participants', [
            'meeting_id'       => $meeting->id,
            'participant_type' => User::class,
            'participant_id'   => $orga->id,
            'is_organizer'     => true,
        ]);
    }

    /** @test */
    public function ohne_recht_kann_kein_freies_meeting_angelegt_werden(): void
    {
        $this->actingAsWithPermission();

        $this->post(route('meetings.create'), [
            'title'      => 'Nicht erlaubt',
            'date'       => now()->addDay()->format('Y-m-d'),
            'start_time' => '14:00',
            'end_time'   => '15:00',
        ])->assertSessionHas('type', 'warning');

        $this->assertDatabaseMissing('meetings', ['title' => 'Nicht erlaubt']);
    }

    /** @test */
    public function gruppenmitglied_kann_ueber_die_uebersicht_ein_gruppen_meeting_anlegen(): void
    {
        $user   = $this->actingAsWithPermission();
        $gruppe = Group::factory()->asMeetingGroup()->create();
        $gruppe->users()->attach($user->id);

        $this->post(route('meetings.create'), [
            'title'      => 'Fachgruppe Mathe',
            'group_id'   => $gruppe->id,
            'date'       => now()->addDay()->format('Y-m-d'),
            'start_time' => '14:00',
            'end_time'   => '15:00',
        ])->assertRedirect();

        $this->assertDatabaseHas('meetings', ['title' => 'Fachgruppe Mathe', 'group_id' => $gruppe->id]);
    }

    /** @test */
    public function eingeladene_personen_gruppen_und_rollen_sehen_das_meeting(): void
    {
        $creator = User::factory()->create();
        $direkt  = User::factory()->create();
        $ueberGruppe = User::factory()->create();
        $ueberRolle  = User::factory()->create();
        $fremd   = User::factory()->create();

        $gruppe = Group::factory()->create();
        $gruppe->users()->attach($ueberGruppe->id);
        $rolle = Role::create(['name' => 'Schulleitung-Test', 'guard_name' => 'web']);
        $ueberRolle->assignRole($rolle);

        $meeting = $this->freeMeeting($creator, [
            'users'  => [$direkt->id],
            'groups' => [$gruppe->id],
            'roles'  => [$rolle->id],
        ]);

        foreach ([$creator, $direkt, $ueberGruppe, $ueberRolle] as $user) {
            $this->actingAs($user)->get(route('meetings.show', $meeting))
                ->assertOk()
                ->assertSee('Projektrunde Schulfest');
        }

        $this->actingAs($fremd)->get(route('meetings.show', $meeting))->assertForbidden();

        $this->assertSame(
            [$creator->id, $direkt->id, $ueberGruppe->id, $ueberRolle->id],
            $meeting->fresh()->resolvedParticipants()->pluck('id')->sort()->values()->all()
        );
    }

    /** @test */
    public function uebersicht_zeigt_freie_meetings_nur_teilnehmenden(): void
    {
        $creator = User::factory()->create();
        $gast    = User::factory()->create();
        $fremd   = User::factory()->create();
        $this->freeMeeting($creator, ['users' => [$gast->id]]);

        $this->actingAs($gast)->get(route('meetings.overview', ['filter' => 'free']))
            ->assertOk()
            ->assertSee('Projektrunde Schulfest');

        $this->actingAs($fremd)->get(route('meetings.overview'))
            ->assertOk()
            ->assertDontSee('Projektrunde Schulfest');
    }

    /** @test */
    public function teilnehmende_legen_freie_themen_an_und_protokollieren(): void
    {
        $creator = User::factory()->create();
        $gast    = User::factory()->create();
        $type    = Type::factory()->create();
        $meeting = $this->freeMeeting($creator, ['users' => [$gast->id]]);

        $this->actingAs($gast)->post(route('meetings.agenda.store', $meeting), [
            'theme'    => 'Budget Schulfest',
            'goal'     => 'Budget beschließen',
            'duration' => 20,
            'type'     => $type->id,
        ])->assertRedirect(route('meetings.show', $meeting));

        $theme = Theme::where('theme', 'Budget Schulfest')->firstOrFail();
        $this->assertNull($theme->group_id);
        $this->assertTrue($meeting->themes()->whereKey($theme->id)->exists());

        $this->actingAs($gast)->get(route('meetings.themes.show', [$meeting, $theme]))
            ->assertOk()
            ->assertSee('Budget Schulfest');

        $this->actingAs($gast)->post(route('meetings.themes.protocols.store', [$meeting, $theme]), [
            'protocol'  => '<p>Budget von 500 Euro beschlossen.</p>',
            'completed' => 1,
        ])->assertRedirect(route('meetings.themes.show', [$meeting, $theme]));

        $this->assertDatabaseHas('protocols', ['theme_id' => $theme->id, 'protocol' => '<p>Budget von 500 Euro beschlossen.</p>']);
        $this->assertDatabaseHas('protocols', ['theme_id' => $theme->id, 'protocol' => 'Thema geschlossen']);
        $this->assertEquals(1, $theme->fresh()->completed);
    }

    /** @test */
    public function neues_thema_kann_einer_eingeladenen_gruppe_zugeordnet_werden(): void
    {
        $creator = User::factory()->create();
        $gast    = User::factory()->create();
        $gruppe  = Group::factory()->create();
        $type    = Type::factory()->create();
        $meeting = $this->freeMeeting($creator, ['users' => [$gast->id], 'groups' => [$gruppe->id]]);

        $this->actingAs($creator)->get(route('meetings.show', $meeting))
            ->assertOk()
            ->assertSee('Gruppe ' . $gruppe->name);

        $this->actingAs($gast)->post(route('meetings.agenda.store', $meeting), [
            'theme'    => 'Hofpausen',
            'goal'     => 'Aufsichtsplan klären',
            'duration' => 15,
            'type'     => $type->id,
            'group_id' => $gruppe->id,
        ])->assertRedirect(route('meetings.show', $meeting));

        $theme = Theme::where('theme', 'Hofpausen')->firstOrFail();
        $this->assertSame($gruppe->id, $theme->group_id);
        $this->assertTrue($meeting->themes()->whereKey($theme->id)->exists());
    }

    /** @test */
    public function neues_thema_kann_keiner_nicht_eingeladenen_gruppe_zugeordnet_werden(): void
    {
        $creator = User::factory()->create();
        $fremd   = Group::factory()->create();
        $type    = Type::factory()->create();
        $meeting = $this->freeMeeting($creator);

        $this->actingAs($creator)->post(route('meetings.agenda.store', $meeting), [
            'theme'    => 'Eingeschleust',
            'goal'     => 'Ziel',
            'duration' => 15,
            'type'     => $type->id,
            'group_id' => $fremd->id,
        ])->assertSessionHas('type', 'warning');

        $this->assertDatabaseMissing('themes', ['theme' => 'Eingeschleust']);
    }

    /** @test */
    public function gruppen_meeting_kann_thema_an_eingeladene_gruppe_geben_aber_nicht_frei_anlegen(): void
    {
        $creator     = User::factory()->create();
        $eigene      = Group::factory()->create();
        $eingeladen  = Group::factory()->create();
        $eigene->users()->attach($creator->id);
        $type        = Type::factory()->create();
        $meeting     = Meeting::factory()->create(['group_id' => $eigene->id, 'creator_id' => $creator->id]);
        app(MeetingService::class)->syncParticipants($meeting, ['groups' => [$eingeladen->id]]);

        $service = app(MeetingService::class);
        $this->assertFalse($service->isValidThemeGroup($meeting, null));
        $this->assertTrue($service->isValidThemeGroup($meeting, $eigene->id));
        $this->assertTrue($service->isValidThemeGroup($meeting, $eingeladen->id));

        $payload = ['goal' => 'Ziel', 'duration' => 15, 'type' => $type->id];

        $this->actingAs($creator)->post(route('meetings.agenda.store', $meeting), $payload + ['theme' => 'Standard'])
            ->assertRedirect(route('meetings.show', $meeting));
        $this->assertSame($eigene->id, Theme::where('theme', 'Standard')->firstOrFail()->group_id);

        $this->actingAs($creator)->post(route('meetings.agenda.store', $meeting), $payload + ['theme' => 'Weitergegeben', 'group_id' => $eingeladen->id])
            ->assertRedirect(route('meetings.show', $meeting));
        $this->assertSame($eingeladen->id, Theme::where('theme', 'Weitergegeben')->firstOrFail()->group_id);
    }

    /** @test */
    public function freies_thema_kann_in_eingeladene_gruppe_umgehaengt_werden(): void
    {
        $creator = User::factory()->create();
        $gast    = User::factory()->create();
        $gruppe  = Group::factory()->create();
        $meeting = $this->freeMeeting($creator, ['users' => [$gast->id], 'groups' => [$gruppe->id]]);
        $theme   = Theme::factory()->create(['group_id' => null, 'creator_id' => $creator->id, 'completed' => 0]);
        $meeting->themes()->attach($theme->id);

        $this->actingAs($gast)->get(route('meetings.themes.show', [$meeting, $theme]))
            ->assertOk()
            ->assertSee('Zuordnung ändern');

        $this->actingAs($gast)->put(route('meetings.themes.move', [$meeting, $theme]), ['group_id' => $gruppe->id])
            ->assertRedirect(route('meetings.themes.show', [$meeting, $theme]))
            ->assertSessionHas('type', 'success');
        $this->assertSame($gruppe->id, (int) $theme->fresh()->group_id);

        // und zurück zum freien Thema
        $this->actingAs($gast)->put(route('meetings.themes.move', [$meeting, $theme]), ['group_id' => ''])
            ->assertSessionHas('type', 'success');
        $this->assertNull($theme->fresh()->group_id);
    }

    /** @test */
    public function umhaengen_ist_nur_in_gruppen_des_meetings_und_fuer_teilnehmende_moeglich(): void
    {
        $creator = User::factory()->create();
        $fremd   = User::factory()->create();
        $fremdeGruppe = Group::factory()->create();
        $meeting = $this->freeMeeting($creator);
        $theme   = Theme::factory()->create(['group_id' => null, 'creator_id' => $creator->id, 'completed' => 0]);
        $meeting->themes()->attach($theme->id);

        $this->actingAs($creator)->put(route('meetings.themes.move', [$meeting, $theme]), ['group_id' => $fremdeGruppe->id])
            ->assertSessionHas('type', 'warning');
        $this->actingAs($fremd)->put(route('meetings.themes.move', [$meeting, $theme]), ['group_id' => $fremdeGruppe->id])
            ->assertForbidden();

        // Gruppenthema einer nicht eingeladenen Gruppe darf nicht aus ihr herausgelöst werden
        $gruppenThema = Theme::factory()->create(['group_id' => $fremdeGruppe->id, 'completed' => 0]);
        $meeting->themes()->attach($gruppenThema->id);
        $this->actingAs($creator)->put(route('meetings.themes.move', [$meeting, $gruppenThema]), ['group_id' => ''])
            ->assertSessionHas('type', 'warning');

        $this->assertNull($theme->fresh()->group_id);
        $this->assertSame($fremdeGruppe->id, (int) $gruppenThema->fresh()->group_id);
    }

    /** @test */
    public function nicht_teilnehmende_sehen_keine_meeting_themen(): void
    {
        $creator = User::factory()->create();
        $fremd   = User::factory()->create();
        $meeting = $this->freeMeeting($creator);
        $theme   = Theme::factory()->create(['group_id' => null, 'creator_id' => $creator->id]);
        $meeting->themes()->attach($theme->id);

        $this->actingAs($fremd)->get(route('meetings.themes.show', [$meeting, $theme]))->assertForbidden();
        $this->actingAs($fremd)->post(route('meetings.themes.protocols.store', [$meeting, $theme]), [
            'protocol' => 'Eingeschleust',
        ])->assertForbidden();

        $this->assertDatabaseMissing('protocols', ['protocol' => 'Eingeschleust']);
    }

    /** @test */
    public function thema_ausserhalb_der_agenda_ist_im_meeting_kontext_nicht_sichtbar(): void
    {
        $creator = User::factory()->create();
        $meeting = $this->freeMeeting($creator);
        $fremdesThema = Theme::factory()->create();

        $this->actingAs($creator)->get(route('meetings.themes.show', [$meeting, $fremdesThema]))->assertForbidden();
    }

    /** @test */
    public function gruppenthema_kann_nur_von_gruppenmitgliedern_zugewiesen_werden(): void
    {
        $creator = User::factory()->create();
        $gruppe  = Group::factory()->create();
        $gruppe->users()->attach($creator->id);
        $gast    = User::factory()->create();
        $meeting = $this->freeMeeting($creator, ['users' => [$gast->id]]);
        $thema   = Theme::factory()->create(['group_id' => $gruppe->id, 'theme' => 'Gruppenthema']);

        // Nicht-Mitglied darf das Gruppenthema nicht verknüpfen
        $this->actingAs($gast)->post(route('meetings.agenda.store', $meeting), ['existing_theme_id' => $thema->id])
            ->assertSessionHas('type', 'warning');
        $this->assertFalse($meeting->themes()->whereKey($thema->id)->exists());

        // Mitglied darf es
        $this->actingAs($creator)->post(route('meetings.agenda.store', $meeting), ['existing_theme_id' => $thema->id])
            ->assertSessionHas('type', 'success');
        $this->assertTrue($meeting->themes()->whereKey($thema->id)->exists());

        // Danach sieht auch der Gast das Thema im Meeting-Kontext
        $this->actingAs($gast)->get(route('meetings.themes.show', [$meeting, $thema]))->assertOk()->assertSee('Gruppenthema');
    }

    /** @test */
    public function nur_organisatoren_duerfen_verwalten(): void
    {
        $creator = User::factory()->create();
        $gast    = User::factory()->create();
        $orga    = User::factory()->create();
        $meeting = $this->freeMeeting($creator, ['users' => [$gast->id], 'organizers' => [$orga->id]]);

        $payload = [
            'title'      => 'Umbenannt',
            'date'       => $meeting->date->format('Y-m-d'),
            'start_time' => '10:00',
            'end_time'   => '11:00',
            'users'      => [$gast->id],
            'organizers' => [$orga->id],
        ];

        $this->actingAs($gast)->put(route('meetings.details.update', $meeting), $payload)->assertForbidden();
        $this->actingAs($gast)->post(route('meetings.details.cancel', $meeting))->assertForbidden();
        $this->actingAs($gast)->delete(route('meetings.details.destroy', $meeting))->assertForbidden();

        $this->actingAs($orga)->put(route('meetings.details.update', $meeting), $payload)
            ->assertRedirect(route('meetings.show', $meeting));
        $this->assertSame('Umbenannt', $meeting->fresh()->title);

        $this->actingAs($orga)->post(route('meetings.details.cancel', $meeting))->assertRedirect();
        $this->assertTrue($meeting->fresh()->cancelled);
    }

    /** @test */
    public function einladung_geht_an_alle_aufgeloesten_teilnehmenden(): void
    {
        Mail::fake();

        $creator = User::factory()->create();
        $gast    = User::factory()->create();
        $gruppe  = Group::factory()->create();
        $mitglied = User::factory()->create();
        $gruppe->users()->attach([$mitglied->id, $gast->id]);
        $meeting = $this->freeMeeting($creator, ['users' => [$gast->id], 'groups' => [$gruppe->id]]);

        $this->actingAs($creator)->post(route('meetings.details.invite', $meeting), ['message' => 'Bitte vorbereiten'])
            ->assertRedirect(route('meetings.show', $meeting));

        // creator + gast + mitglied (gast nur einmal)
        Mail::assertQueued(MeetingInvitationMail::class, 3);
        $this->assertNotNull($meeting->fresh()->invitation_sent_at);
    }

    /** @test */
    public function globale_suche_findet_themen_und_protokolle_aus_freien_meetings_nur_fuer_teilnehmende(): void
    {
        $creator = User::factory()->create();
        $gast    = User::factory()->create();
        $fremd   = User::factory()->create();
        $meeting = $this->freeMeeting($creator, ['users' => [$gast->id]]);

        $theme = Theme::factory()->create(['group_id' => null, 'theme' => 'Hofgestaltung', 'creator_id' => $creator->id]);
        $meeting->themes()->attach($theme->id);
        Protocol::create(['theme_id' => $theme->id, 'creator_id' => $creator->id, 'protocol' => '<p>Hochbeete aus Lärchenholz bestellen</p>']);

        $response = $this->actingAs($gast)->postJson(url('search/search'), ['text' => 'Lärchenholz'])->assertOk();
        $items = collect($response->json('sections'))->firstWhere('key', 'meeting-themes')['items'] ?? [];
        $this->assertCount(1, $items);
        $this->assertSame('Hofgestaltung', $items[0]['title']);
        $this->assertSame(route('meetings.themes.show', [$meeting, $theme]), $items[0]['url']);
        $this->assertStringContainsString('Lärchenholz', $items[0]['snippet']);

        $response = $this->actingAs($gast)->postJson(url('search/search'), ['text' => 'Hofgest'])->assertOk();
        $this->assertNotNull(collect($response->json('sections'))->firstWhere('key', 'meeting-themes'));

        $meetingHit = $this->actingAs($gast)->postJson(url('search/search'), ['text' => 'Schulfest'])->json('sections');
        $this->assertNotNull(collect($meetingHit)->firstWhere('key', 'meetings'));

        $this->actingAs($fremd)->postJson(url('search/search'), ['text' => 'Lärchenholz'])
            ->assertOk()
            ->assertJson(['sections' => []]);
    }

    /** @test */
    public function gruppen_meeting_ist_fuer_gruppenmitglieder_ueber_die_detailseite_erreichbar(): void
    {
        $user   = $this->actingAsWithPermission();
        $gruppe = Group::factory()->asMeetingGroup()->create();
        $gruppe->users()->attach($user->id);
        $meeting = Meeting::factory()->create(['group_id' => $gruppe->id, 'title' => 'Dienstberatung']);

        $this->get(route('meetings.show', $meeting))->assertOk()->assertSee('Dienstberatung');
        $this->get(route('meetings.index', ['group' => $gruppe->name]))->assertOk();
    }

    /** @test */
    public function teilnehmer_synchronisation_ersetzt_bestehende_eintraege(): void
    {
        $creator = User::factory()->create();
        $a = User::factory()->create();
        $b = User::factory()->create();
        $meeting = $this->freeMeeting($creator, ['users' => [$a->id]]);

        app(MeetingService::class)->syncParticipants($meeting, ['users' => [$b->id], 'organizers' => [$b->id]]);

        $this->assertSame(1, MeetingParticipant::where('meeting_id', $meeting->id)->count());
        $this->assertTrue($meeting->isOrganizer($b));
        $this->assertFalse($meeting->hasParticipant($a));
    }
}
