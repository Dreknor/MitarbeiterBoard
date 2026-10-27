<?php

namespace Tests\Feature\Meetings;

use App\Mail\MeetingInvitationMail;
use App\Models\Group;
use App\Models\Meeting;
use App\Models\User;
use App\Services\Meetings\MeetingService;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Einladungen nur an ausgewählte Teilnehmende – der Meetingzugriff bleibt für alle erhalten.
 */
class MeetingInvitationRecipientsTest extends TestCase
{
    private User $leitung;
    private User $mitglied;
    private User $gast;
    private Group $gruppe;
    private Meeting $meeting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->leitung  = User::factory()->create();
        $this->mitglied = User::factory()->create();
        $this->gast     = User::factory()->create();
        $this->gruppe   = Group::factory()->create();
        $this->gruppe->users()->attach([$this->leitung->id, $this->mitglied->id]);

        $this->meeting = Meeting::factory()->for($this->gruppe)->create([
            'creator_id' => $this->leitung->id,
            'title'      => 'Teamsitzung',
        ]);
        app(MeetingService::class)->syncParticipants($this->meeting, ['users' => [$this->gast->id]]);
    }

    /** @test */
    public function ohne_auswahl_geht_die_einladung_an_alle_teilnehmenden(): void
    {
        Mail::fake();

        $this->actingAs($this->leitung)
            ->post(route('meetings.details.invite', $this->meeting))
            ->assertRedirect(route('meetings.show', $this->meeting));

        Mail::assertQueued(MeetingInvitationMail::class, 3);
    }

    /** @test */
    public function gruppen_meeting_einladung_nur_an_ausgewaehlte_personen(): void
    {
        Mail::fake();

        $this->actingAs($this->leitung)
            ->post(route('meetings.details.invite', $this->meeting), [
                'select_recipients' => 1,
                'recipients'        => [$this->gast->id],
            ])
            ->assertRedirect(route('meetings.show', $this->meeting))
            ->assertSessionHas('type', 'success');

        Mail::assertQueued(MeetingInvitationMail::class, 1);
        Mail::assertQueued(MeetingInvitationMail::class, fn ($mail) => $mail->hasTo($this->gast->email));
        $this->assertNotNull($this->meeting->fresh()->invitation_sent_at);

        // Nicht eingeladenes Gruppenmitglied behält den Zugriff
        $this->actingAs($this->mitglied)->get(route('meetings.show', $this->meeting))->assertOk();
    }

    /** @test */
    public function personen_ausserhalb_des_meetings_werden_nicht_eingeladen(): void
    {
        Mail::fake();
        $fremd = User::factory()->create();

        $this->actingAs($this->leitung)
            ->post(route('meetings.details.invite', $this->meeting), [
                'select_recipients' => 1,
                'recipients'        => [$this->mitglied->id, $fremd->id],
            ]);

        Mail::assertQueued(MeetingInvitationMail::class, 1);
        Mail::assertNotQueued(MeetingInvitationMail::class, fn ($mail) => $mail->hasTo($fremd->email));
    }

    /** @test */
    public function leere_auswahl_wird_abgelehnt(): void
    {
        Mail::fake();

        $this->actingAs($this->leitung)
            ->post(route('meetings.details.invite', $this->meeting), ['select_recipients' => 1])
            ->assertSessionHasErrors('recipients');

        Mail::assertNothingQueued();
        $this->assertNull($this->meeting->fresh()->invitation_sent_at);
    }

    /** @test */
    public function auswahl_funktioniert_auch_auf_der_gruppenseite(): void
    {
        Mail::fake();

        $this->actingAs($this->leitung)
            ->post(route('meetings.invite', ['group' => $this->gruppe->name, 'meeting' => $this->meeting->id]), [
                'select_recipients' => 1,
                'recipients'        => [$this->mitglied->id],
            ])
            ->assertRedirect();

        Mail::assertQueued(MeetingInvitationMail::class, 1);
        Mail::assertQueued(MeetingInvitationMail::class, fn ($mail) => $mail->hasTo($this->mitglied->email));
    }

    /** @test */
    public function empfaengerliste_markiert_zusaetzlich_eingeladene(): void
    {
        $empfaenger = app(MeetingService::class)->invitationRecipients($this->meeting)->keyBy('id');

        $this->assertCount(3, $empfaenger);
        $this->assertTrue($empfaenger[$this->gast->id]['extra']);
        $this->assertTrue($empfaenger[$this->leitung->id]['extra']); // Ersteller
        $this->assertFalse($empfaenger[$this->mitglied->id]['extra']);
    }

    /** @test */
    public function einladungsdialog_zeigt_empfaengerauswahl(): void
    {
        $this->actingAs($this->leitung)
            ->get(route('meetings.show', $this->meeting))
            ->assertOk()
            ->assertSee('name="recipients[]"', false)
            ->assertSee('Nur zusätzlich Eingeladene');
    }

    /** @test */
    public function gruppenseite_zeigt_empfaengerauswahl(): void
    {
        $this->meeting->update(['date' => now()->addDays(3)]);

        $this->actingAs($this->leitung)
            ->get(route('meetings.index', ['group' => $this->gruppe->name]))
            ->assertOk()
            ->assertSee('name="recipients[]"', false)
            ->assertSee($this->gast->name);
    }
}
