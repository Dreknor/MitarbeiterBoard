<?php

namespace Tests\Feature\Benachrichtigungen;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\Channels\BenachrichtigungDatabaseChannel;
use App\Notifications\Push;
use App\Services\Benachrichtigungen\BenachrichtigungsService;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\TestCase;

class BenachrichtigungsServiceTest extends TestCase
{
    private BenachrichtigungsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BenachrichtigungsService();
    }

    public function test_ohne_gespeicherte_einstellung_gelten_standardwerte_aus_config(): void
    {
        $user = User::factory()->create();

        $this->assertSame(
            ['push' => config('benachrichtigungen.kategorien.vertretungen.push'), 'mail' => config('benachrichtigungen.kategorien.vertretungen.mail')],
            $this->service->einstellungFuer($user, 'vertretungen')
        );
    }

    public function test_gespeicherte_einstellung_ueberschreibt_standard(): void
    {
        $user = User::factory()->create();
        NotificationPreference::create(['user_id' => $user->id, 'kategorie' => 'aufgaben', 'push' => false, 'mail' => 'zusammenfassung']);

        $this->assertSame(['push' => false, 'mail' => 'zusammenfassung'], $this->service->einstellungFuer($user, 'aufgaben'));
    }

    public function test_glocke_immer_mail_nur_bei_sofort_und_adresse(): void
    {
        $user = User::factory()->create();
        NotificationPreference::create(['user_id' => $user->id, 'kategorie' => 'aufgaben', 'push' => true, 'mail' => 'sofort']);
        NotificationPreference::create(['user_id' => $user->id, 'kategorie' => 'themen', 'push' => true, 'mail' => 'zusammenfassung']);

        $this->assertSame([BenachrichtigungDatabaseChannel::class, 'mail'], $this->service->kanaeleFuer($user, 'aufgaben'));
        $this->assertSame([BenachrichtigungDatabaseChannel::class], $this->service->kanaeleFuer($user, 'themen'));

        $ohneMail = User::factory()->create(['email' => '']);
        $this->assertNotContains('mail', (new BenachrichtigungsService())->kanaeleFuer($ohneMail, 'aufgaben'));
    }

    public function test_webpush_nur_mit_registriertem_geraet(): void
    {
        $user = User::factory()->create();
        NotificationPreference::create(['user_id' => $user->id, 'kategorie' => 'aufgaben', 'push' => true, 'mail' => 'aus']);

        $this->assertNotContains(WebPushChannel::class, $this->service->kanaeleFuer($user, 'aufgaben'));

        $user->updatePushSubscription('https://push.example.test/abc', 'schluessel', 'token');

        $this->assertContains(WebPushChannel::class, (new BenachrichtigungsService())->kanaeleFuer($user, 'aufgaben'));
    }

    public function test_kategorien_werden_nach_permission_gefiltert(): void
    {
        $ohne = User::factory()->create();
        $mit = $this->createUserWithPermission('edit employe');

        $this->assertArrayNotHasKey('personal', $this->service->sichtbareKategorien($ohne));
        $this->assertArrayHasKey('personal', $this->service->sichtbareKategorien($mit));
        $this->assertArrayHasKey('vertretungen', $this->service->sichtbareKategorien($ohne));
    }

    public function test_benachrichtigung_landet_mit_kategorie_in_der_glocke(): void
    {
        $user = User::factory()->create();

        $user->notify(new Push('Titel', 'Text', 'tickets', '/tickets'));

        $eintrag = $user->notifications()->first();
        $this->assertNotNull($eintrag);
        $this->assertSame('tickets', $eintrag->kategorie);
        $this->assertSame('Titel', $eintrag->data['subject']);
        $this->assertSame('Text', $eintrag->data['message']);
        $this->assertSame('/tickets', $eintrag->data['url']);
    }

    public function test_mail_erlaubt_respektiert_aus(): void
    {
        $user = User::factory()->create();
        NotificationPreference::create(['user_id' => $user->id, 'kategorie' => 'nachrichten', 'push' => false, 'mail' => 'aus']);

        $this->assertFalse($this->service->mailErlaubt($user, 'nachrichten'));
        $this->assertTrue($this->service->mailErlaubt($user, 'aufgaben'));
    }
}
