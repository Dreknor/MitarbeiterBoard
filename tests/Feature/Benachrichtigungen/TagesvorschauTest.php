<?php

namespace Tests\Feature\Benachrichtigungen;

use App\Mail\Tagesvorschau;
use App\Models\Absence;
use App\Models\Meeting;
use App\Models\OxCalendar;
use App\Models\OxTermin;
use App\Models\TagesvorschauEinstellung;
use App\Models\User;
use App\Models\Vertretung;
use App\Services\Benachrichtigungen\Tagesvorschau\TagesvorschauEintrag;
use App\Services\Benachrichtigungen\TagesvorschauService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TagesvorschauTest extends TestCase
{
    // Montag, 16.11.2026 (Arbeitstag)
    private const MONTAG = '2026-11-16';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function service(): TagesvorschauService
    {
        return app(TagesvorschauService::class);
    }

    private function einstellung(User $user, array $werte = []): TagesvorschauEinstellung
    {
        return TagesvorschauEinstellung::create(array_merge(
            TagesvorschauEinstellung::standardWerte(),
            $werte,
            ['user_id' => $user->id]
        ));
    }

    public function test_morgens_im_fenster_genau_einmal_versendet(): void
    {
        Carbon::setTestNow(self::MONTAG.' 06:30:00');
        $user = User::factory()->create();
        Vertretung::factory()->create(['users_id' => $user->id, 'date' => self::MONTAG, 'stunde' => 3]);

        $this->assertSame(1, $this->service()->versendeFaellige(now()));
        $this->assertSame(0, $this->service()->versendeFaellige(now()->addMinutes(15)));

        Mail::assertQueued(Tagesvorschau::class, 1);
        Mail::assertQueued(Tagesvorschau::class, fn ($mail) => $mail->hasTo($user->email)
            && !$mail->vorabend
            && $mail->zieltag->toDateString() === self::MONTAG
            && $mail->bereiche[0]['label'] === 'Meine Vertretungen');
    }

    public function test_ausserhalb_des_fensters_wird_nicht_versendet(): void
    {
        $user = User::factory()->create();
        Vertretung::factory()->create(['users_id' => $user->id, 'date' => self::MONTAG]);

        Carbon::setTestNow(self::MONTAG.' 06:15:00');
        $this->assertSame(0, $this->service()->versendeFaellige(now()));

        Carbon::setTestNow(self::MONTAG.' 07:45:00');
        $this->assertSame(0, $this->service()->versendeFaellige(now()));

        Mail::assertNothingQueued();
    }

    public function test_verpasster_lauf_wird_nachgeholt(): void
    {
        $user = User::factory()->create();
        Vertretung::factory()->create(['users_id' => $user->id, 'date' => self::MONTAG]);

        Carbon::setTestNow(self::MONTAG.' 07:15:00');
        $this->assertSame(1, $this->service()->versendeFaellige(now()));
    }

    public function test_ohne_eintraege_keine_mail(): void
    {
        Carbon::setTestNow(self::MONTAG.' 06:30:00');
        $user = User::factory()->create();

        $this->assertSame(0, $this->service()->versendeFaellige(now()));
        Mail::assertNothingQueued();

        $gespeichert = $user->fresh()->tagesvorschauEinstellung;
        $this->assertSame(self::MONTAG, $gespeichert->zuletzt_fuer_tag->toDateString());
        $this->assertSame([], $gespeichert->kalender_ids);
        $this->assertNull($gespeichert->bereiche);
    }

    public function test_am_wochenende_wird_morgens_nichts_versendet(): void
    {
        Carbon::setTestNow('2026-11-14 06:30:00'); // Samstag
        $user = User::factory()->create();
        Vertretung::factory()->create(['users_id' => $user->id, 'date' => '2026-11-14']);

        $this->assertSame(0, $this->service()->versendeFaellige(now()));
    }

    public function test_vorabend_freitag_liefert_montag(): void
    {
        Carbon::setTestNow('2026-11-13 19:00:00'); // Freitag
        $user = User::factory()->create();
        $this->einstellung($user, ['zeitpunkt' => 'vorabend', 'uhrzeit' => '19:00:00']);
        Vertretung::factory()->create(['users_id' => $user->id, 'date' => self::MONTAG]);

        $this->assertSame(1, $this->service()->versendeFaellige(now()));
        Mail::assertQueued(Tagesvorschau::class, fn ($mail) => $mail->vorabend && $mail->zieltag->toDateString() === self::MONTAG);

        // Samstag- und Sonntagabend: Montag ist schon verschickt
        Carbon::setTestNow('2026-11-14 19:00:00');
        $this->assertSame(0, $this->service()->versendeFaellige(now()));
        Carbon::setTestNow('2026-11-15 19:00:00');
        $this->assertSame(0, $this->service()->versendeFaellige(now()));
    }

    public function test_vorabend_ueberspringt_feiertage(): void
    {
        $user = User::factory()->create();
        $einstellung = TagesvorschauEinstellung::standard($user)->fill(['zeitpunkt' => 'vorabend']);

        // Donnerstag, 31.12.2026 → Neujahr (Fr) und Wochenende → Montag, 04.01.2027
        $this->assertSame('2027-01-04', $this->service()->zieltag($einstellung, Carbon::parse('2026-12-31 19:00'))->toDateString());
    }

    public function test_abwesende_person_bekommt_keine_uebersicht(): void
    {
        Carbon::setTestNow(self::MONTAG.' 06:30:00');
        $user = User::factory()->create();
        Vertretung::factory()->create(['users_id' => $user->id, 'date' => self::MONTAG]);
        Absence::factory()->create(['users_id' => $user->id, 'start' => self::MONTAG, 'end' => self::MONTAG]);

        $this->assertSame(0, $this->service()->versendeFaellige(now()));
        Mail::assertNothingQueued();
    }

    public function test_bereichsauswahl_wird_respektiert(): void
    {
        Carbon::setTestNow(self::MONTAG.' 06:30:00');
        $user = User::factory()->create();
        $this->einstellung($user, ['bereiche' => ['meetings']]);
        Vertretung::factory()->create(['users_id' => $user->id, 'date' => self::MONTAG]);

        $this->assertSame(0, $this->service()->versendeFaellige(now()));
    }

    public function test_meetings_der_person_erscheinen(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->create(['group_id' => null, 'creator_id' => $user->id, 'date' => self::MONTAG, 'title' => 'Dienstberatung']);

        $bereiche = $this->service()->fuer($user, Carbon::parse(self::MONTAG));

        $meetings = $bereiche->firstWhere('bereich', 'meetings');
        $this->assertNotNull($meetings);
        $this->assertSame('Dienstberatung', $meetings['eintraege']->first()->titel);
        $this->assertSame('09:00', $meetings['eintraege']->first()->zeit);
    }

    public function test_nur_ausgewaehlte_und_sichtbare_kalender(): void
    {
        $user = $this->createUserWithPermission('view calendar');
        $gewaehlt = OxCalendar::factory()->create(['name' => 'Schulkalender']);
        $nichtGewaehlt = OxCalendar::factory()->create(['name' => 'Verwaltung']);
        $unsichtbar = OxCalendar::factory()->create(['name' => 'Geheim', 'sichtbar' => false]);

        OxTermin::factory()->create(['ox_calendar_id' => $gewaehlt->id, 'titel' => 'Elternabend', 'beginn' => self::MONTAG.' 18:00', 'ende' => self::MONTAG.' 19:30']);
        OxTermin::factory()->create(['ox_calendar_id' => $nichtGewaehlt->id, 'titel' => 'Haushaltsplanung', 'beginn' => self::MONTAG.' 10:00', 'ende' => self::MONTAG.' 11:00']);
        OxTermin::factory()->create(['ox_calendar_id' => $unsichtbar->id, 'titel' => 'Geheimtreffen', 'beginn' => self::MONTAG.' 12:00', 'ende' => self::MONTAG.' 13:00']);
        // Serientermin, der eine Woche vorher beginnt
        OxTermin::factory()->create([
            'ox_calendar_id' => $gewaehlt->id, 'titel' => 'Wochenbesprechung',
            'beginn' => '2026-11-09 07:30', 'ende' => '2026-11-09 08:00', 'rrule' => 'FREQ=WEEKLY;COUNT=5',
        ]);

        $einstellung = $this->einstellung($user, [
            'kalender_ids'        => [$gewaehlt->id, $unsichtbar->id],
            'eingeladene_termine' => false,
        ]);

        $kalender = $this->service()->fuer($user, Carbon::parse(self::MONTAG), $einstellung)->firstWhere('bereich', 'kalender');

        $this->assertNotNull($kalender);
        $titel = $kalender['eintraege']->map(fn (TagesvorschauEintrag $e) => $e->titel)->all();
        $this->assertSame(['Wochenbesprechung', 'Elternabend'], $titel);
        $this->assertSame('18:00–19:30', $kalender['eintraege'][1]->zeit);
    }

    public function test_bereiche_mit_permission_sind_ohne_recht_unsichtbar(): void
    {
        $user = User::factory()->create();
        $bereiche = $this->service()->sichtbareQuellen($user)->map->bereich()->all();

        $this->assertNotContains('tickets', $bereiche);
        $this->assertNotContains('genehmigungen', $bereiche);
        $this->assertContains('vertretungen', $bereiche);

        $editor = $this->createUserWithPermission('edit tickets', 'approve holidays');
        $bereiche = $this->service()->sichtbareQuellen($editor)->map->bereich()->all();
        $this->assertContains('tickets', $bereiche);
        $this->assertContains('genehmigungen', $bereiche);
    }

    public function test_abwesenheiten_nur_wenn_ausgewaehlt(): void
    {
        $user = $this->createUserWithPermission('view absences');
        $kollegin = User::factory()->create(['name' => 'Erika Beispiel']);
        Absence::factory()->create(['users_id' => $kollegin->id, 'start' => self::MONTAG, 'end' => '2026-11-18']);

        $standard = $this->service()->fuer($user, Carbon::parse(self::MONTAG));
        $this->assertNull($standard->firstWhere('bereich', 'abwesenheiten'));

        $einstellung = $this->einstellung($user, ['bereiche' => ['abwesenheiten']]);
        $abwesend = $this->service()->fuer($user, Carbon::parse(self::MONTAG), $einstellung)->firstWhere('bereich', 'abwesenheiten');

        $this->assertNotNull($abwesend);
        $this->assertSame('Erika Beispiel', $abwesend['eintraege']->first()->titel);
        $this->assertSame('bis 18.11.', $abwesend['eintraege']->first()->zeit);
    }

    public function test_mail_template_rendert(): void
    {
        $mail = new Tagesvorschau('Anna', Carbon::parse(self::MONTAG), true, [
            ['label' => 'Meine Vertretungen', 'eintraege' => [
                ['titel' => '5a Mathe', 'zeit' => '3. Std.', 'details' => 'Raum 204', 'url' => url('/'), 'hervorheben' => true],
            ]],
        ]);

        $html = $mail->render();

        $this->assertStringContainsString('Hallo Anna', $html);
        $this->assertStringContainsString('5a Mathe', $html);
        $this->assertStringContainsString('Raum 204', $html);
        $this->assertStringContainsString('morgen', $html);
    }
}
