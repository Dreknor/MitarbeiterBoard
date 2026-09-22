<?php

namespace Tests\Unit\Mail;

use App\Mail\MeetingInvitationMail;
use App\Models\Group;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MeetingInvitationMailTest extends TestCase
{
    /** @test */
    public function it_builds_mail_with_correct_subject(): void
    {
        $group   = Group::factory()->asMeetingGroup()->create();
        $meeting = Meeting::factory()->for($group)->create(['title' => 'Teamsitzung']);
        $user    = User::factory()->create(['email' => 'empfaenger@example.com', 'name' => 'Max Muster']);

        $mail = new MeetingInvitationMail($meeting, $group, $user, null, 'Absender');
        $mail->build();

        $this->assertEquals('Einladung zum Meeting: Teamsitzung', $mail->subject);
    }

    /** @test */
    public function it_sets_reply_to_with_sender_email(): void
    {
        $group   = Group::factory()->asMeetingGroup()->create();
        $meeting = Meeting::factory()->for($group)->create(['title' => 'Teamsitzung']);
        $user    = User::factory()->create(['email' => 'empfaenger@example.com', 'name' => 'Max Muster']);

        $mail = new MeetingInvitationMail($meeting, $group, $user, null, 'Absender Name', 'absender@example.com');
        $mail->build();

        $this->assertTrue($mail->hasReplyTo('absender@example.com', 'Absender Name'));
    }

    /** @test */
    public function it_does_not_set_reply_to_without_sender_email(): void
    {
        $group   = Group::factory()->asMeetingGroup()->create();
        $meeting = Meeting::factory()->for($group)->create(['title' => 'Teamsitzung']);
        $user    = User::factory()->create(['email' => 'empfaenger@example.com', 'name' => 'Max Muster']);

        $mail = new MeetingInvitationMail($meeting, $group, $user, null, 'Absender');
        $mail->build();

        $this->assertEmpty($mail->replyTo);
    }

    /** @test */
    public function ical_attachment_contains_method_request(): void
    {
        $group   = Group::factory()->asMeetingGroup()->create();
        $meeting = Meeting::factory()->for($group)->create(['title' => 'Teamsitzung']);
        $user    = User::factory()->create(['email' => 'empfaenger@example.com', 'name' => 'Max Muster']);

        $mail = new MeetingInvitationMail($meeting, $group, $user, null, 'Absender', 'absender@example.com');
        $built = $mail->build();

        // Render the mail to Symfony Email to check attachments
        $symMessage = $built->buildViewData();

        // Prüfe ICS-Inhalt über Reflection (buildIcal ist private)
        $reflection = new \ReflectionMethod($mail, 'buildIcal');
        $reflection->setAccessible(true);
        $ical = $reflection->invoke($mail);

        // VCALENDAR muss METHOD:REQUEST enthalten
        $this->assertStringContainsString('METHOD:REQUEST', $ical, 'VCALENDAR fehlt METHOD:REQUEST');

        // VEVENT muss die korrekten Eigenschaften enthalten
        $this->assertStringContainsString('BEGIN:VEVENT', $ical);
        $this->assertStringContainsString('SUMMARY:Teamsitzung', $ical);
        $this->assertStringContainsString('ORGANIZER', $ical);
        $this->assertStringContainsString('ATTENDEE', $ical);

        // ORGANIZER muss die E-Mail des Versenders enthalten
        $this->assertStringContainsString('absender@example.com', $ical, 'ORGANIZER enthält nicht die Absender-E-Mail');

        // ATTENDEE muss RFC-konforme Parameter haben
        $this->assertStringContainsString('PARTSTAT=NEEDS-ACTION', $ical, 'ATTENDEE fehlt PARTSTAT');
        $this->assertStringContainsString('RSVP=TRUE', $ical, 'ATTENDEE fehlt RSVP');
        $this->assertStringContainsString('ROLE=REQ-PARTICIPANT', $ical, 'ATTENDEE fehlt ROLE');
        $this->assertStringContainsString('empfaenger@example.com', $ical);

        // UID darf nicht @mitarbeiter.local verwenden wenn APP_URL anders gesetzt ist
        $this->assertStringContainsString('UID:meeting-' . $meeting->id . '@', $ical);
    }

    /** @test */
    public function ical_uid_does_not_use_local_domain_in_production(): void
    {
        config(['app.url' => 'https://board.esz-radebeul.de']);

        $group   = Group::factory()->asMeetingGroup()->create();
        $meeting = Meeting::factory()->for($group)->create(['title' => 'Test']);
        $user    = User::factory()->create(['email' => 'test@example.com']);

        $mail = new MeetingInvitationMail($meeting, $group, $user);

        $reflection = new \ReflectionMethod($mail, 'buildIcal');
        $reflection->setAccessible(true);
        $ical = $reflection->invoke($mail);

        $this->assertStringContainsString('@board.esz-radebeul.de', $ical);
        $this->assertStringNotContainsString('@mitarbeiter.local', $ical);
    }

    /** @test */
    public function ical_includes_location_when_group_has_meeting_url(): void
    {
        $group   = Group::factory()->asMeetingGroup()->create(['meeting_url' => 'https://meet.example.com/room1']);
        $meeting = Meeting::factory()->for($group)->create();
        $user    = User::factory()->create();

        $mail = new MeetingInvitationMail($meeting, $group, $user);

        $reflection = new \ReflectionMethod($mail, 'buildIcal');
        $reflection->setAccessible(true);
        $ical = $reflection->invoke($mail);

        $this->assertStringContainsString('LOCATION:https://meet.example.com/room1', $ical);
    }

    /** @test */
    public function it_can_be_queued(): void
    {
        Mail::fake();

        $group   = Group::factory()->asMeetingGroup()->create();
        $meeting = Meeting::factory()->for($group)->create();
        $user    = User::factory()->create(['email' => 'test@example.com']);

        Mail::to($user->email)->queue(
            new MeetingInvitationMail($meeting, $group, $user, 'Testnachricht', 'Admin', 'admin@example.com')
        );

        Mail::assertQueued(MeetingInvitationMail::class);
    }
}


