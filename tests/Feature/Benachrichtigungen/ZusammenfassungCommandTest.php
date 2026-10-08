<?php

namespace Tests\Feature\Benachrichtigungen;

use App\Mail\BenachrichtigungsZusammenfassung;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\Push;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ZusammenfassungCommandTest extends TestCase
{
    public function test_eine_mail_pro_person_nur_fuer_zusammenfassungs_kategorien(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        NotificationPreference::create(['user_id' => $user->id, 'kategorie' => 'themen', 'push' => false, 'mail' => 'zusammenfassung']);
        NotificationPreference::create(['user_id' => $user->id, 'kategorie' => 'tickets', 'push' => false, 'mail' => 'aus']);

        $user->notify(new Push('Thema 1', 'a', 'themen'));
        $user->notify(new Push('Thema 2', 'b', 'themen'));
        $user->notify(new Push('Ticket', 'c', 'tickets'));

        $this->artisan('benachrichtigungen:zusammenfassung')->assertSuccessful();

        Mail::assertQueued(BenachrichtigungsZusammenfassung::class, 1);
        Mail::assertQueued(BenachrichtigungsZusammenfassung::class, fn ($mail) => $mail->hasTo($user->email) && $mail->anzahl === 2);

        $this->assertSame(2, $user->notifications()->whereNotNull('zusammenfassung_versendet_at')->count());
        $this->assertNull($user->notifications()->where('kategorie', 'tickets')->first()->zusammenfassung_versendet_at);
    }

    public function test_zweiter_lauf_versendet_nichts_und_gelesenes_wird_ignoriert(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        NotificationPreference::create(['user_id' => $user->id, 'kategorie' => 'themen', 'push' => false, 'mail' => 'zusammenfassung']);
        $user->notify(new Push('Gelesen', 'a', 'themen'));
        $user->notifications()->first()->markAsRead();

        $this->artisan('benachrichtigungen:zusammenfassung');
        Mail::assertNothingQueued();

        $user->notify(new Push('Neu', 'b', 'themen'));
        $this->artisan('benachrichtigungen:zusammenfassung');
        $this->artisan('benachrichtigungen:zusammenfassung');

        Mail::assertQueued(BenachrichtigungsZusammenfassung::class, 1);
    }
}
